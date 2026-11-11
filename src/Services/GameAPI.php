<?php
namespace Victi\MyGameLibrary\Services;

class GameAPI {
    // IGDB usa o OAuth da Twitch (client credentials): você cria um app em
    // https://dev.twitch.tv/console, e usa Client ID + Client Secret para gerar um
    // access token (válido ~60 dias). O token é guardado em cache em disco.
    private $twitchClientId;
    private $twitchClientSecret;
    private $igdbUrl = 'https://api.igdb.com/v4/games';
    private $cacheDir;
    private $cacheTtl = 300; // 5 minutos: buscas repetidas (ex: "the", "witcher", "the witcher") saem do cache

    // Os ids guardados em games.external_id precisam continuar sendo só dígitos e nunca colidir
    // entre fontes. Faixas:
    //   < 500.000.000          -> ids antigos da RAWG (jogos já salvos no banco; a RAWG não é mais consultada)
    //   500.000.000 .. 899.999.999 -> id da IGDB + igdbIdOffset
    //   >= 900.000.000         -> appid da Steam + steamIdOffset (fallback)
    // getGameDetails() olha só o número para saber em qual API buscar.
    private $igdbIdOffset = 500000000;
    private $steamIdOffset = 900000000;

    // game_type da IGDB que NÃO queremos na busca (DLC, expansão, bundle, mod, episódio,
    // temporada, pack, update). Filtrado no PHP em vez de no "where" da query para não
    // perder jogos que vêm com game_type nulo.
    private $igdbExcludedTypes = [1, 2, 3, 5, 6, 7, 13, 14];

    // Campos pedidos à IGDB nas buscas por lista (normal e tolerante)
    private $igdbListFields = 'name,first_release_date,genres.name,platforms.name,cover.image_id,screenshots.image_id,total_rating_count,follows,game_type,version_parent';

    public function __construct() {
        $this->twitchClientId = trim($_ENV['TWITCH_CLIENT_ID'] ?? '');
        $this->twitchClientSecret = trim($_ENV['TWITCH_CLIENT_SECRET'] ?? '');
        $this->cacheDir = dirname(__DIR__, 2) . '/storage/cache/game_search';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    private function getCachePath($query) {
        return $this->cacheDir . '/' . md5(mb_strtolower(trim($query))) . '.json';
    }

    private function getFromCache($query) {
        $path = $this->getCachePath($query);
        if (!is_file($path)) {
            return null;
        }
        if (time() - filemtime($path) > $this->cacheTtl) {
            return null; // expirado
        }
        $content = file_get_contents($path);
        $decoded = $content !== false ? json_decode($content, true) : null;
        return is_array($decoded) ? $decoded : null;
    }

    private function saveToCache($query, array $payload) {
        $path = $this->getCachePath($query);
        @file_put_contents($path, json_encode($payload), LOCK_EX);
    }

    // Extrai as "palavras que importam" de uma busca, ignorando artigos/conectivos
    // curtos (ex: "de", "the", "of") que não ajudam a decidir relevância
    private function significantWords($text) {
        $stopwords = ['de', 'da', 'do', 'the', 'of', 'a', 'o', 'e', 'and'];
        $words = preg_split('/\s+/u', mb_strtolower(trim((string) $text)));
        return array_values(array_filter($words, fn($w) => $w !== '' && !in_array($w, $stopwords, true)));
    }

    // Muitos jogos usam número romano no título ("Dark Souls III"), mas quem
    // busca digita o número normal ("dark souls 3"). Sem isso, o próprio jogo
    // certo seria descartado pelo filtro de relevância abaixo.
    private function normalizeRomanNumerals($text) {
        static $map = null;
        if ($map === null) {
            $romanToArabic = [
                'xx' => '20', 'xix' => '19', 'xviii' => '18', 'xvii' => '17', 'xvi' => '16',
                'xv' => '15', 'xiv' => '14', 'xiii' => '13', 'xii' => '12', 'xi' => '11', 'x' => '10',
                'ix' => '9', 'viii' => '8', 'vii' => '7', 'vi' => '6', 'v' => '5',
                'iv' => '4', 'iii' => '3', 'ii' => '2', 'i' => '1',
            ];
            // ordena as chaves da mais longa pra mais curta, senão "iii" seria
            // cortado por um "i" solto antes de chegar nela
            uksort($romanToArabic, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
            $map = $romanToArabic;
        }

        $pattern = '/\b(' . implode('|', array_map(fn($k) => preg_quote($k, '/'), array_keys($map))) . ')\b/ui';
        return preg_replace_callback($pattern, fn($m) => $map[mb_strtolower($m[1])], $text);
    }

    // Lowercase + números romanos convertidos + pontuação fora, só letras/números/espaço.
    // É a forma "achatada" usada pra comparar nome do jogo com o texto buscado.
    private function normalizeForMatch($text) {
        $text = $this->normalizeRomanNumerals(mb_strtolower((string) $text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    // Exige que o nome do jogo contenha a busca como frase (na mesma ordem, mesmo que
    // com prefixo/sufixo no nome), e não só "as mesmas palavras em qualquer lugar" —
    // é isso que evita que "Dark Fall 3: Lost Souls" passe numa busca por "dark souls 3"
    // só porque "dark", "souls" e "3" aparecem em pontos diferentes do nome.
    private function filterByRelevance(array $results, $query) {
        $normQuery = $this->normalizeForMatch($query);
        $queryWords = $this->significantWords($normQuery);
        if (count($queryWords) < 2) {
            return $results;
        }

        // Round 1: o nome precisa conter a busca inteira, como frase, em sequência
        $strict = array_values(array_filter($results, function ($g) use ($normQuery) {
            $name = $this->normalizeForMatch($g['name'] ?? '');
            return mb_strpos($name, $normQuery) !== false;
        }));

        if (!empty($strict)) {
            return $strict;
        }

        // Round 2 (fallback): pelo menos metade das palavras da busca aparecem no nome,
        // sem exigir ordem (cobre buscas com fraseado diferente do título oficial)
        $minMatches = (int) ceil(count($queryWords) / 2);
        $loose = array_values(array_filter($results, function ($g) use ($queryWords, $minMatches) {
            $name = $this->normalizeForMatch($g['name'] ?? '');
            $matches = 0;
            foreach ($queryWords as $word) {
                if (mb_strpos($name, $word) !== false) {
                    $matches++;
                }
            }
            return $matches >= $minMatches;
        }));

        // Se nem isso sobrar nada, devolve a lista original pra não zerar a busca
        return !empty($loose) ? $loose : $results;
    }

    // URL de imagem da IGDB. Usa a capa oficial (retrato 3:4, t_cover_big_2x = 528x748,
    // nítida em telas retina). Se o jogo não tiver capa, cai para uma screenshot.
    private function igdbImageUrl($imageId, $size) {
        return $imageId ? "https://images.igdb.com/igdb/image/upload/{$size}/{$imageId}.jpg" : null;
    }

    private function igdbBackgroundImage(array $game) {
        $cover = $game['cover']['image_id'] ?? null;
        if ($cover) {
            return $this->igdbImageUrl($cover, 't_cover_big_2x');
        }
        return $this->igdbImageUrl($game['screenshots'][0]['image_id'] ?? null, 't_screenshot_big');
    }

    // Converte um jogo cru da IGDB para o mesmo formato que as views/JS já esperam
    // (id, name, background_image, released, genres[], platforms[]).
    private function trimGameForList(array $game) {
        $released = isset($game['first_release_date']) ? gmdate('Y-m-d', (int) $game['first_release_date']) : null;

        return [
            'id' => isset($game['id']) ? (int) $game['id'] + $this->igdbIdOffset : null,
            'name' => $game['name'] ?? '',
            'background_image' => $this->igdbBackgroundImage($game),
            'released' => $released,
            'genres' => array_map(fn($g) => ['name' => $g['name'] ?? ''], $game['genres'] ?? []),
            'platforms' => array_map(fn($p) => ['platform' => ['name' => $p['name'] ?? '']], $game['platforms'] ?? []),
            // Usado só para filtrar resultados ruins (fangames, entradas vazias);
            // não é usado na tela, removido antes de devolver a resposta
            '_added' => (int) ($game['total_rating_count'] ?? 0) + (int) ($game['follows'] ?? 0),
        ];
    }

    // Tira da lista jogos com engajamento praticamente nulo na IGDB, medido por avaliações + seguidores (fangames, demos
    // caseiras, mods, entradas de teste), que "batem" com o texto buscado mas não são
    // o que o usuário está procurando. Se filtrar demais (busca por algo bem de nicho),
    // recua e devolve a lista original pra não sumir com resultados válidos.
    private function filterLowQualityResults(array $results, $minAdded = 3) {
        $filtered = array_values(array_filter($results, fn($g) => ($g['_added'] ?? 0) >= $minAdded));

        if (count($filtered) < 3) {
            $filtered = $results;
        }

        // Remove o campo interno antes de expor pro front
        return array_map(function ($g) {
            unset($g['_added']);
            return $g;
        }, $filtered);
    }

    // Faz o GET e devolve o JSON decodificado, ou null se algo deu errado
    // (timeout, erro de conexão, HTTP >= 400, corpo que não é um objeto/array JSON).
    // Esse "null" é o sinal que searchGames()/getGameDetails() usam pra saber que
    // devem cair no fallback da Steam.
    private function fetchUrl($url, $timeout = 5, $connectTimeout = 3) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: MyGameLibrary/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($curlError !== null) {
            error_log('GameAPI fetchUrl falhou (' . $url . '): ' . $curlError);
            return null;
        }

        if ($httpCode >= 400 || $response === false) {
            error_log('GameAPI fetchUrl HTTP ' . $httpCode . ' (' . $url . ')');
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    // true quando o id está na faixa da IGDB (ver comentário em $igdbIdOffset)
    private function isIgdbId($id) {
        $id = (int) $id;
        return $id >= $this->igdbIdOffset && $id < $this->steamIdOffset;
    }

    // true quando o id já veio "deslocado" pelo offset da Steam (ver comentário
    // em $steamIdOffset), ou seja, é um appid da Steam e não um id da IGDB.
    private function isSteamId($id) {
        return (int) $id >= $this->steamIdOffset;
    }

    // "21 Aug, 2012" (formato que a Steam devolve com l=english) -> "2012-08-21",
    // pra ficar no mesmo formato ISO que a IGDB usa (a tela mostra só o ano,
    // com substr($released, 0, 4), então precisa começar com o ano).
    private function normalizeSteamDate($rawDate) {
        if (empty($rawDate)) {
            return null;
        }
        $timestamp = strtotime($rawDate);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function normalizeGenreName($genreName) {
        $genreName = trim((string) $genreName);
        // Segurança extra: decodifica entidades HTML residuais (ex: "&bull;" literal)
        // que possam ter vindo de algum fallback ou fonte externa
        $genreName = html_entity_decode($genreName, ENT_QUOTES, 'UTF-8');
        $genreName = preg_replace('/\s+/', ' ', $genreName);

        if ($genreName === '') {
            return '';
        }

        return mb_strtolower($genreName);
    }

    public function formatGenresForStorage($genres) {
        $normalizedGenres = [];

        if (is_string($genres)) {
            // Aceita vírgula, bullet ou pipe como separador (evita que um fallback
            // com separador diferente vire "um gênero só" com o separador dentro do nome)
            $genres = preg_split('/\s*(?:,|•|\|)\s*/u', $genres);
        }

        if (!is_array($genres)) {
            return '';
        }

        foreach ($genres as $genre) {
            $genreName = is_array($genre) ? ($genre['name'] ?? '') : $genre;
            $normalizedGenre = $this->normalizeGenreName($genreName);

            if ($normalizedGenre === '') {
                continue;
            }

            if (!in_array($normalizedGenre, $normalizedGenres, true)) {
                $normalizedGenres[] = $normalizedGenre;
            }
        }

        return implode(', ', $normalizedGenres);
    }

    private function normalizeGameData(array $gameData) {
        if (!empty($gameData['genres']) && is_array($gameData['genres'])) {
            $gameData['genres'] = array_values(array_filter(array_map(function ($genre) {
                $name = is_array($genre) ? ($genre['name'] ?? '') : $genre;
                $normalized = $this->normalizeGenreName($name);

                return $normalized !== '' ? ['name' => $normalized] : null;
            }, $gameData['genres'])));
        }

        return $gameData;
    }

    // ---------- Token da Twitch (OAuth client credentials) ----------

    private function tokenCachePath() {
        return dirname($this->cacheDir) . '/igdb_token.json';
    }

    // Devolve um access token válido (do cache em disco ou novo), ou null se falhar.
    private function getIgdbToken() {
        if ($this->twitchClientId === '' || $this->twitchClientSecret === '') {
            error_log('GameAPI: TWITCH_CLIENT_ID / TWITCH_CLIENT_SECRET não configurados no .env');
            return null;
        }

        $path = $this->tokenCachePath();
        if (is_file($path)) {
            $cached = json_decode((string) @file_get_contents($path), true);
            // margem de 1 dia antes de expirar
            if (is_array($cached) && !empty($cached['token']) && ($cached['expires_at'] ?? 0) > time() + 86400) {
                return $cached['token'];
            }
        }

        $ch = curl_init('https://id.twitch.tv/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $this->twitchClientId,
                'client_secret' => $this->twitchClientSecret,
                'grant_type' => 'client_credentials',
            ]),
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        $decoded = $response ? json_decode($response, true) : null;
        if ($curlError !== null || $httpCode >= 400 || empty($decoded['access_token'])) {
            error_log('GameAPI: falha ao obter token da Twitch (HTTP ' . $httpCode . ') ' . ($curlError ?? ''));
            return null;
        }

        @file_put_contents($path, json_encode([
            'token' => $decoded['access_token'],
            'expires_at' => time() + (int) ($decoded['expires_in'] ?? 0),
        ]), LOCK_EX);
        @chmod($path, 0600);

        return $decoded['access_token'];
    }

    private function invalidateIgdbToken() {
        @unlink($this->tokenCachePath());
    }

    // Escapa o texto digitado para ir dentro de aspas na query Apicalypse da IGDB
    private function apicalypseString($text) {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $text);
    }

    // Monta o handle cURL de uma query Apicalypse (POST com o corpo em texto puro)
    private function buildIgdbHandle($body, $token, $timeout = 3, $connectTimeout = 2) {
        $ch = curl_init($this->igdbUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_HTTPHEADER => [
                'Client-ID: ' . $this->twitchClientId,
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
                'Content-Type: text/plain',
                'User-Agent: MyGameLibrary/1.0',
            ],
        ]);
        return $ch;
    }

    // Faz uma query na IGDB e devolve o array decodificado (lista de jogos) ou null se falhou.
    // Se a IGDB responder 401 (token revogado/expirado), descarta o token em cache e
    // tenta UMA vez de novo com um token novo.
    private function igdbRequest($body, $timeout = 5, $connectTimeout = 3, $retry = true) {
        $token = $this->getIgdbToken();
        if ($token === null) {
            return null;
        }

        $ch = $this->buildIgdbHandle($body, $token, $timeout, $connectTimeout);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($httpCode === 401 && $retry) {
            $this->invalidateIgdbToken();
            return $this->igdbRequest($body, $timeout, $connectTimeout, false);
        }

        if ($curlError !== null || $httpCode >= 400 || $response === false) {
            error_log('GameAPI igdbRequest falhou (HTTP ' . $httpCode . '): ' . ($curlError ?? ''));
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function igdbSearchBody($query) {
        return 'search "' . $this->apicalypseString($query) . '"; '
            . 'fields ' . $this->igdbListFields . '; '
            . 'limit 25;';
    }

    public function searchGames($query) {

        // 1) Cache primeiro: se alguém já buscou isso nos últimos minutos, devolve na hora sem tocar em nenhuma API
        $cached = $this->getFromCache($query);
        if ($cached !== null) {
            return $cached;
        }

        // IGDB e o storesearch da Steam saem AO MESMO TEMPO (curl_multi). Assim, com a IGDB
        // fora do ar, o pior caso vira o maior dos dois tempos, não a soma.
        [$igdbPayload, $steamSearchPayload] = $this->fetchIgdbAndSteamSearchConcurrently($query);

        $payload = $this->processIgdbSearchPayload($igdbPayload, $query);

        // A IGDB respondeu, mas nada parece com o que foi digitado (provável erro de
        // digitação, ex: "belsmont" em vez de "belmont"): tenta a busca tolerante
        if ($payload !== null && $this->shouldTryTolerantSearch($payload['results'], $query)) {
            $payload = $this->applyTolerantSearch($payload, $igdbPayload, $query);
        }

        // IGDB fora do ar, com timeout, rate-limited etc.: cai pro fallback da Steam
        // em vez de devolver busca vazia/erro pro usuário
        if ($payload === null) {
            $payload = $this->searchGamesSteam($query, $steamSearchPayload);
        }

        // Só guarda em cache respostas válidas (evita cachear erro/timeout)
        if (is_array($payload) && isset($payload['results']) && is_array($payload['results'])) {
            $this->saveToCache($query, $payload);
        }

        return $payload;
    }

    // Dispara a busca da IGDB e o storesearch da Steam ao mesmo tempo e espera os dois.
    // Devolve [igdbGames, steamSearchPayload], cada um null se aquela chamada falhou/expirou.
    private function fetchIgdbAndSteamSearchConcurrently($query) {
        $token = $this->getIgdbToken(); // quase sempre vem do cache em disco, sem rede
        $steamUrl = 'https://store.steampowered.com/api/storesearch/?term=' . urlencode($query) . '&l=english&cc=us';

        $multiHandle = curl_multi_init();

        $igdbCh = null;
        if ($token !== null) {
            $igdbCh = $this->buildIgdbHandle($this->igdbSearchBody($query), $token, 3, 2);
            curl_multi_add_handle($multiHandle, $igdbCh);
        }

        $steamCh = curl_init();
        curl_setopt_array($steamCh, [
            CURLOPT_URL => $steamUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: MyGameLibrary/1.0'],
        ]);
        curl_multi_add_handle($multiHandle, $steamCh);

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle);
            }
        } while ($running > 0);

        $igdbGames = null;
        if ($igdbCh !== null) {
            $igdbCode = curl_getinfo($igdbCh, CURLINFO_HTTP_CODE);
            if ($igdbCode === 401) {
                $this->invalidateIgdbToken(); // a próxima busca gera um token novo
            }
            if (!curl_errno($igdbCh) && $igdbCode < 400) {
                $decoded = json_decode((string) curl_multi_getcontent($igdbCh), true);
                $igdbGames = is_array($decoded) ? $decoded : null;
            } else {
                error_log('GameAPI: busca na IGDB falhou (HTTP ' . $igdbCode . ') ' . curl_error($igdbCh));
            }
            curl_multi_remove_handle($multiHandle, $igdbCh);
            curl_close($igdbCh);
        }

        $steamOk = !curl_errno($steamCh) && curl_getinfo($steamCh, CURLINFO_HTTP_CODE) < 400;
        $steamResponse = $steamOk ? curl_multi_getcontent($steamCh) : null;
        curl_multi_remove_handle($multiHandle, $steamCh);
        curl_close($steamCh);
        curl_multi_close($multiHandle);

        $steamPayload = $steamResponse ? json_decode($steamResponse, true) : null;

        return [$igdbGames, is_array($steamPayload) ? $steamPayload : null];
    }

    // Remove DLC/mod/bundle etc. e edições duplicadas (version_parent preenchido)
    private function filterIgdbGameTypes(array $games) {
        return array_values(array_filter($games, function ($g) {
            if (!is_array($g) || empty($g['id'])) return false;
            if (in_array($g['game_type'] ?? null, $this->igdbExcludedTypes, true)) return false;
            if (!empty($g['version_parent'])) return false;
            return true;
        }));
    }

    // Recebe a lista crua da IGDB (ou null) e devolve null se não for confiável,
    // ou ['results' => [...]] já filtrado/pronto pra exibição.
    // Obs: uma lista vazia [] é uma resposta válida ("a IGDB não achou nada") e NÃO cai
    // no fallback da Steam.
    private function processIgdbSearchPayload($games, $query) {
        if ($games === null || !array_is_list($games)) {
            return null; // formato inesperado (ex: objeto de erro) -> trata como falha
        }

        $games = $this->filterIgdbGameTypes($games);

        $trimmed = array_map(fn($game) => $this->trimGameForList($this->normalizeGameData($game)), $games);

        $relevant = $this->filterByRelevance($trimmed, $query);
        $results = array_slice($this->filterLowQualityResults($relevant), 0, 10);

        return ['results' => $results];
    }

    // Fallback usando a Steam Store API (storesearch + appdetails) quando a IGDB está indisponível.
    // storesearch/appdetails são endpoints públicos da loja da Steam, não precisam de STEAM_API_KEY
    // (essa chave é da Web API oficial, usada por outros endpoints que não existem aqui).
    // $searchPayload: se já veio de fetchIgdbAndSteamSearchConcurrently(), reaproveita em vez de
    // bater na Steam de novo (só chama de novo se for usado fora de searchGames(), ex. testes).
    private function searchGamesSteam($query, $searchPayload = null) {
        if ($searchPayload === null) {
            $searchUrl = 'https://store.steampowered.com/api/storesearch/?term=' . urlencode($query) . '&l=english&cc=us';
            $searchPayload = $this->fetchUrl($searchUrl, 3, 2);
        }


        if (!is_array($searchPayload) || empty($searchPayload['items']) || !is_array($searchPayload['items'])) {
            return ['results' => []];
        }

        // Limita a 5: cada item ainda gera uma chamada extra pro appdetails (feitas em
        // paralelo) — mais que isso volta a deixar o fallback lento e aumenta a chance
        // da própria Steam segurar alguma conexão concorrente
        $items = array_slice($searchPayload['items'], 0, 5);
        $appIds = array_map(fn($item) => (int) $item['id'], $items);
        $detailsByAppId = $this->fetchSteamAppDetailsBatch($appIds);

        $results = [];
        foreach ($items as $item) {
            $appId = (int) $item['id'];
            $details = $detailsByAppId[$appId] ?? null;

            $genres = [];
            foreach (($details['genres'] ?? []) as $genre) {
                if (!empty($genre['description'])) {
                    $genres[] = ['name' => $genre['description']];
                }
            }

            $platformFlags = $details['platforms'] ?? ($item['platforms'] ?? []);
            $platforms = [];
            if (!empty($platformFlags['windows'])) $platforms[] = ['platform' => ['name' => 'PC (Windows)']];
            if (!empty($platformFlags['mac'])) $platforms[] = ['platform' => ['name' => 'Mac']];
            if (!empty($platformFlags['linux'])) $platforms[] = ['platform' => ['name' => 'Linux']];

            $results[] = [
                'id' => $appId + $this->steamIdOffset,
                'name' => $item['name'] ?? '',
                // header_image é o banner com o LOGO do jogo escrito nele — como o card
                // corta em formato quase quadrado, o corte comia o nome (ex: "DARK SOULS"
                // virando "K SOUL"). Uma screenshot não tem texto e corta bem melhor,
                // igual à IGDB (que também usa screenshot como capa da lista).
                'background_image' => $details['screenshots'][0]['path_full']
                    ?? ($details['header_image'] ?? ($item['tiny_image'] ?? null)),
                'released' => $this->normalizeSteamDate($details['release_date']['date'] ?? null),
                'genres' => $genres,
                'platforms' => $platforms,
            ];
        }

        return ['results' => $this->filterByRelevance($results, $query)];
    }

    // Busca appdetails de vários appids em paralelo (curl_multi), senão 8 chamadas
    // sequenciais deixariam o fallback lento demais pra uma busca ao vivo.
    private function fetchSteamAppDetailsBatch(array $appIds) {
        if (empty($appIds)) {
            return [];
        }

        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($appIds as $appId) {
            $url = 'https://store.steampowered.com/api/appdetails?appids=' . $appId . '&l=english&cc=us';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MyGameLibrary/1.0');
            curl_multi_add_handle($multiHandle, $ch);
            $handles[$appId] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle);
            }
        } while ($running > 0);

        $detailsByAppId = [];
        foreach ($handles as $appId => $ch) {
            $response = curl_multi_getcontent($ch);
            $decoded = $response ? json_decode($response, true) : null;

            if (is_array($decoded) && !empty($decoded[$appId]['success']) && !empty($decoded[$appId]['data'])) {
                $detailsByAppId[$appId] = $decoded[$appId]['data'];
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        curl_multi_close($multiHandle);

        return $detailsByAppId;
    }


    public function getGameDetails($gameID) {
        // Id "deslocado" pelo offset da Steam = veio de um resultado do fallback
        // (buscado enquanto a IGDB estava fora do ar), então busca na Steam também
        if ($this->isSteamId($gameID)) {
            return $this->getGameDetailsSteam((int) $gameID - $this->steamIdOffset);
        }

        // Id antigo da RAWG (jogo que já existia antes da migração): não há mais como
        // consultar a RAWG. Na prática esse caminho nem é usado, porque o controller só
        // chama getGameDetails() para jogos que ainda NÃO estão no banco.
        if (!$this->isIgdbId($gameID)) {
            return null;
        }

        return $this->getGameDetailsIgdb((int) $gameID - $this->igdbIdOffset);
    }

    private function getGameDetailsIgdb($igdbId) {
        $body = 'fields name,summary,storyline,first_release_date,genres.name,cover.image_id,screenshots.image_id; '
            . 'where id = ' . (int) $igdbId . '; limit 1;';
        $games = $this->igdbRequest($body, 5, 3);

        if (!is_array($games) || empty($games[0]) || !is_array($games[0])) {
            // Se a IGDB cair bem nesse instante, devolve null e o jogo é salvo sem
            // descrição/gêneros vindos da API (mesmo comportamento de antes).
            return null;
        }

        $game = $games[0];

        return $this->normalizeGameData([
            'id' => (int) $game['id'] + $this->igdbIdOffset,
            'name' => $game['name'] ?? '',
            // A IGDB devolve texto puro em inglês; vai pro translateHTML como antes
            'description' => $game['summary'] ?? ($game['storyline'] ?? ''),
            'genres' => array_map(fn($g) => ['name' => $g['name'] ?? ''], $game['genres'] ?? []),
            'background_image' => $this->igdbBackgroundImage($game),
            'released' => isset($game['first_release_date']) ? gmdate('Y-m-d', (int) $game['first_release_date']) : null,
        ]);
    }

    // Mesmo propósito do getGameDetails de cima, mas puxando da Steam Store API
    // (appdetails) para um id que veio do fallback searchGamesSteam().
    private function getGameDetailsSteam($appId) {
        $url = 'https://store.steampowered.com/api/appdetails?appids=' . $appId . '&l=english&cc=us';
        $payload = $this->fetchUrl($url, 5, 3);

        if (!is_array($payload) || empty($payload[$appId]['success']) || empty($payload[$appId]['data'])) {
            return null;
        }

        $data = $payload[$appId]['data'];

        $genres = [];
        foreach (($data['genres'] ?? []) as $genre) {
            if (!empty($genre['description'])) {
                $genres[] = ['name' => $genre['description']];
            }
        }

        return $this->normalizeGameData([
            'id' => $appId + $this->steamIdOffset,
            'name' => $data['name'] ?? '',
            // A Steam separa descrição curta/longa; usamos a longa (equivalente ao
            // campo "description" que a IGDB devolve e que vai pro translateHTML)
            'description' => $data['detailed_description'] ?? ($data['short_description'] ?? ''),
            'genres' => $genres,
            'background_image' => $data['header_image'] ?? null,
            'released' => $this->normalizeSteamDate($data['release_date']['date'] ?? null),
        ]);
    }

    // ---------- Busca tolerante a erros de digitação ----------
    // A busca da IGDB exige as palavras escritas certas. Quando nada do resultado normal
    // parece com o que foi digitado, buscamos pelas palavras que provavelmente estão certas
    // (ex: "castlevania" em "castlevania belsmont curse") e ranqueamos os candidatos aqui,
    // comparando palavra por palavra com tolerância a letras trocadas/faltando/sobrando.
    // Limite conhecido: precisa de pelo menos uma palavra (ou o começo dela) escrita certa.

    // Troca letras acentuadas por equivalentes simples (só para comparar; não depende de intl)
    private function foldAccents($text) {
        return strtr($text, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }

    // Distância de edição (Damerau-Levenshtein simplificada): inserir, apagar, trocar
    // uma letra ou inverter duas letras vizinhas ("wtich" -> "witch") custa 1.
    private function editDistance($a, $b) {
        $a = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY);
        $n = count($a);
        $m = count($b);
        if ($n === 0) return $m;
        if ($m === 0) return $n;

        $d = [];
        for ($i = 0; $i <= $n; $i++) $d[$i][0] = $i;
        for ($j = 0; $j <= $m; $j++) $d[0][$j] = $j;

        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }
        return $d[$n][$m];
    }

    // 0..1: quão bem a palavra digitada ($q) casa com uma palavra do nome ($n)
    private function tokenSimilarity($q, $n) {
        if ($q === $n) return 1.0;

        $lq = mb_strlen($q);
        // números ("3") só valem se forem exatamente iguais; senão "dark souls 2"
        // casaria com "dark souls 3". Palavras de 1-2 letras também.
        if ($lq < 3 || ctype_digit($q) || ctype_digit($n)) return 0.0;

        // Palavras de 3 letras: tolera UMA letra a mais/menos/trocada ("ing" -> "ring"),
        // mas com nota menor, porque 3 letras é pouca informação. Sozinha não passa do
        // mínimo; só ajuda quando as outras palavras da busca casam bem.
        if ($lq === 3) {
            return (abs(mb_strlen($n) - 3) <= 1 && $this->editDistance($q, $n) === 1) ? 0.75 : 0.0;
        }

        // palavra ainda sendo digitada ("belmo" -> "belmont")
        if (mb_strpos($n, $q) === 0) return 0.9;

        $sim = 1 - $this->editDistance($q, $n) / max($lq, mb_strlen($n));
        return $sim >= 0.75 ? $sim : 0.0;
    }

    // 0..1: média, sobre as palavras digitadas, da melhor correspondência no nome do jogo.
    // 1.0 = todas as palavras digitadas aparecem (exatas) no nome.
    private function fuzzyScore($name, $query) {
        $qTokens = $this->significantWords($this->foldAccents($this->normalizeForMatch($query)));
        $nTokens = preg_split('/\s+/u', $this->foldAccents($this->normalizeForMatch($name)), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($qTokens) || empty($nTokens)) {
            return 0.0;
        }

        $sum = 0.0;
        $numberMissing = false;
        $lastIndex = count($qTokens) - 1;
        foreach ($qTokens as $index => $q) {
            $best = 0.0;
            foreach ($nTokens as $n) {
                $best = max($best, $this->tokenSimilarity($q, $n));
                // a ÚLTIMA palavra pode estar sendo digitada ainda ("elden ri" -> "ring")
                if ($index === $lastIndex && mb_strlen($q) >= 2 && !ctype_digit($q) && mb_strpos($n, $q) === 0) {
                    $best = max($best, 0.9);
                }
                if ($best === 1.0) break;
            }
            // "dark souls 3" não pode casar com "Dark Souls II": o número decide qual jogo é
            if ($best === 0.0 && ctype_digit($q)) {
                $numberMissing = true;
            }
            $sum += $best;
        }
        $score = $sum / count($qTokens);

        return $numberMissing ? $score * 0.5 : $score;
    }

    // Só vale a pena gastar requisições extras se NENHUM resultado atual parece com a busca
    private function shouldTryTolerantSearch(array $results, $query) {
        if (mb_strlen(trim($query)) < 4) {
            return false;
        }
        foreach ($results as $game) {
            if ($this->fuzzyScore($game['name'] ?? '', $query) >= 0.85) {
                return false;
            }
        }
        return true;
    }

    // Palavras da busca (sem stopwords, sem números, 3+ letras), na forma usada para comparar
    private function tolerantWords($query) {
        $normalized = $this->foldAccents($this->normalizeForMatch($query));
        return array_values(array_unique(array_filter(
            $this->significantWords($normalized),
            fn($w) => mb_strlen($w) >= 3 && !ctype_digit($w)
        )));
    }

    // FASE 1: uma busca por palavra "âncora" (as mais longas, que são as mais distintivas e
    // as que menos provavelmente têm erro). Se a busca é uma palavra só, já foi feita
    // antes (a normal), então não repete.
    private function tolerantAnchorBodies($query) {
        $words = $this->tolerantWords($query);
        usort($words, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
        // "ing" em "elden ing" é só ruído como busca: se há palavra de 4+ letras, usa só elas
        $longWords = array_values(array_filter($words, fn($w) => mb_strlen($w) >= 4));
        if (!empty($longWords)) {
            $words = $longWords;
        }
        $words = array_slice($words, 0, 3);

        $normalizedQuery = $this->foldAccents($this->normalizeForMatch($query));
        if (count($words) === 1 && $words[0] === $normalizedQuery) {
            return [];
        }

        return array_map(
            fn($w) => 'search "' . $this->apicalypseString($w) . '"; fields ' . $this->igdbListFields . '; limit 100;',
            $words
        );
    }

    // FASE 2 (só se a fase 1 não achou nada decente): a palavra mais longa que não aparece
    // em NENHUM candidato provavelmente tem o erro de digitação. Como o erro pode estar em
    // qualquer lugar da palavra, busca pelo COMEÇO e pelo FIM dela (um dos dois quase
    // sempre fica antes/depois do erro): "mineceraft" -> nomes com "mine" e nomes com "raft".
    // Ordenado por popularidade, para o jogo certo cair dentro do limite de 100.
    private function tolerantAffixBodies($query, array $pool) {
        $poolTokens = [];
        foreach ($this->filterIgdbGameTypes($pool) as $g) {
            foreach (preg_split('/\s+/u', $this->foldAccents($this->normalizeForMatch($g['name'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) as $t) {
                $poolTokens[$t] = true;
            }
        }

        $unmatched = array_filter($this->tolerantWords($query), function ($w) use ($poolTokens) {
            if (mb_strlen($w) < 4) return false;
            foreach (array_keys($poolTokens) as $t) {
                if ($this->tokenSimilarity($w, (string) $t) > 0.0) return false;
            }
            return true;
        });
        if (empty($unmatched)) {
            return [];
        }

        usort($unmatched, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
        $word = $unmatched[0];
        $len = mb_strlen($word);
        $k = $len >= 11 ? 5 : ($len >= 6 ? 4 : 3);

        $affixes = array_unique([mb_substr($word, 0, $k), mb_substr($word, -$k)]);
        $bodies = [];
        foreach ($affixes as $affix) {
            $bodies[] = 'fields ' . $this->igdbListFields . '; '
                . 'where name ~ *"' . $this->apicalypseString($affix) . '"* & total_rating_count != null; '
                . 'sort total_rating_count desc; limit 100;';
        }
        return $bodies;
    }

    // Dispara várias queries da IGDB ao mesmo tempo. Devolve uma lista de arrays de jogos
    // (as que falharam são ignoradas — a busca tolerante é um "extra", nunca derruba a busca).
    private function igdbMultiRequest(array $bodies) {
        $token = $this->getIgdbToken();
        if ($token === null || empty($bodies)) {
            return [];
        }

        $multiHandle = curl_multi_init();
        $handles = [];
        foreach ($bodies as $body) {
            $ch = $this->buildIgdbHandle($body, $token, 3, 2);
            curl_multi_add_handle($multiHandle, $ch);
            $handles[] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle);
            }
        } while ($running > 0);

        $lists = [];
        foreach ($handles as $ch) {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code === 401) {
                $this->invalidateIgdbToken();
            }
            if (!curl_errno($ch) && $code < 400) {
                $decoded = json_decode((string) curl_multi_getcontent($ch), true);
                if (is_array($decoded) && array_is_list($decoded)) {
                    $lists[] = $decoded;
                }
            } else {
                error_log('GameAPI: busca tolerante falhou (HTTP ' . $code . ')');
            }
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        curl_multi_close($multiHandle);

        return $lists;
    }

    // Junta os candidatos (sem repetir), dá nota de semelhança com a busca e devolve os
    // melhores já no formato final da lista. Nota mínima 0.66 (ex: 2 de 3 palavras casam;
    // para busca de uma palavra só, 0.75).
    private function rankTolerantCandidates(array $games, $query) {
        $qWordCount = count($this->significantWords($this->normalizeForMatch($query)));
        $minScore = $qWordCount <= 1 ? 0.75 : 0.66;

        $seen = [];
        $scored = [];
        foreach ($this->filterIgdbGameTypes($games) as $g) {
            $id = (int) $g['id'];
            if (isset($seen[$id])) continue;
            $seen[$id] = true;

            $score = $this->fuzzyScore($g['name'] ?? '', $query);
            if ($score < $minScore) continue;

            $nameWords = count(preg_split('/\s+/u', $this->normalizeForMatch($g['name'] ?? ''), -1, PREG_SPLIT_NO_EMPTY));
            $extraWords = max(0, $nameWords - $qWordCount);

            $scored[] = [
                // palavras sobrando no nome pesam pouco (máx. 0.1): só ajudam a colocar
                // "Castlevania" na frente de "Castlevania: Symphony of the Night"
                'score' => $score - min(0.1, 0.02 * $extraWords),
                'pop' => (int) ($g['total_rating_count'] ?? 0) + (int) ($g['follows'] ?? 0),
                'game' => $g,
            ];
        }

        // mais parecido primeiro; empate decide pelo mais popular
        usort($scored, fn($a, $b) => [$b['score'], $b['pop']] <=> [$a['score'], $a['pop']]);

        $results = [];
        foreach (array_slice($scored, 0, 10) as $row) {
            $item = $this->trimGameForList($this->normalizeGameData($row['game']));
            unset($item['_added']);
            $results[] = $item;
        }
        return $results;
    }

    private function mergeIgdbLists(array $pool, array $lists) {
        foreach ($lists as $list) {
            $pool = array_merge($pool, $list);
        }
        return $pool;
    }

    private function applyTolerantSearch(array $payload, array $primaryGames, $query) {
        $pool = $primaryGames;

        // Fase 1: busca pelas palavras âncora
        $pool = $this->mergeIgdbLists($pool, $this->igdbMultiRequest($this->tolerantAnchorBodies($query)));
        $results = $this->rankTolerantCandidates($pool, $query);

        // Fase 2: nada decente ainda -> busca por começo/fim da palavra que parece errada
        if (empty($results)) {
            $pool = $this->mergeIgdbLists($pool, $this->igdbMultiRequest($this->tolerantAffixBodies($query, $pool)));
            $results = $this->rankTolerantCandidates($pool, $query);
        }

        // Se a busca tolerante também não achou nada decente, mantém o que já tinha
        if (!empty($results)) {
            $payload['results'] = $results;
        }
        return $payload;
    }

    // ---------- Usado por scripts/update_covers.php (manutenção) ----------

    // Converte um id da IGDB no valor que fica em games.external_id
    public function toExternalId($igdbId) {
        return (int) $igdbId + $this->igdbIdOffset;
    }

    // Devolve [igdbId => urlDaCapa] para uma lista de ids da IGDB (só os que têm capa).
    // Lança RuntimeException se a IGDB falhar, para o script parar em vez de gravar errado.
    public function getCoverUrlsByIgdbIds(array $igdbIds) {
        $igdbIds = array_values(array_unique(array_map('intval', $igdbIds)));
        $covers = [];

        foreach (array_chunk($igdbIds, 100) as $chunk) {
            $body = 'fields cover.image_id; where id = (' . implode(',', $chunk) . '); limit 500;';
            $games = $this->igdbRequest($body, 10, 3);
            if (!is_array($games)) {
                throw new \RuntimeException('Falha ao consultar a IGDB (ver error_log).');
            }
            foreach ($games as $g) {
                $url = $this->igdbImageUrl($g['cover']['image_id'] ?? null, 't_cover_big_2x');
                if ($url !== null && isset($g['id'])) {
                    $covers[(int) $g['id']] = $url;
                }
            }
            usleep(300000); // respeita o limite de 4 req/s da IGDB
        }

        return $covers;
    }

    // Procura na IGDB o jogo que corresponde a um título já salvo no banco (ids antigos da
    // RAWG ou da Steam não têm ligação direta com a IGDB). Devolve
    // ['igdb_id' => int, 'name' => string, 'cover' => url] ou null se não achou com segurança.
    // Lança RuntimeException se a IGDB falhar.
    public function findIgdbMatchByTitle($title, $releaseDate = null) {
        $body = 'search "' . $this->apicalypseString($title) . '"; '
            . 'fields name,first_release_date,cover.image_id,total_rating_count,game_type,version_parent; limit 10;';
        $games = $this->igdbRequest($body, 8, 3);
        if (!is_array($games)) {
            throw new \RuntimeException('Falha ao consultar a IGDB (ver error_log).');
        }

        return $this->pickBestIgdbMatch($games, $title, $releaseDate);
    }

    // Só aceita candidato com NOME IGUAL ao do banco (ignorando maiúsculas, pontuação e
    // número romano) e com capa. Se houver mais de um com o mesmo nome (ex: "Prey" 2006 e
    // 2017), o ano de lançamento desempata; se não der pra desempatar, devolve null em vez
    // de arriscar a capa errada.
    private function pickBestIgdbMatch(array $games, $title, $releaseDate) {
        $normTitle = $this->normalizeForMatch($title);
        $year = $releaseDate ? (int) substr((string) $releaseDate, 0, 4) : null;

        $candidates = [];
        foreach ($games as $g) {
            if (!is_array($g) || empty($g['id'])) continue;
            if (in_array($g['game_type'] ?? null, $this->igdbExcludedTypes, true)) continue;
            if (!empty($g['version_parent'])) continue;
            if (empty($g['cover']['image_id'])) continue;
            if ($this->normalizeForMatch($g['name'] ?? '') !== $normTitle) continue;
            $candidates[] = $g;
        }

        if (empty($candidates)) {
            return null;
        }

        if ($year) {
            $sameYear = array_values(array_filter($candidates, function ($g) use ($year) {
                return isset($g['first_release_date'])
                    && abs((int) gmdate('Y', (int) $g['first_release_date']) - $year) <= 1;
            }));
            if (!empty($sameYear)) {
                $candidates = $sameYear;
            } elseif (count($candidates) > 1) {
                return null; // vários com o mesmo nome e nenhum do ano certo
            }
        } elseif (count($candidates) > 1) {
            return null;
        }

        // Entre os que sobraram, o mais avaliado (mais "popular") ganha
        usort($candidates, fn($a, $b) => ($b['total_rating_count'] ?? 0) <=> ($a['total_rating_count'] ?? 0));
        $best = $candidates[0];

        return [
            'igdb_id' => (int) $best['id'],
            'name' => $best['name'],
            'cover' => $this->igdbImageUrl($best['cover']['image_id'], 't_cover_big_2x'),
        ];
    }

    public function translateHTML($htmlText, $sourceLang = 'EN', $targetLang = 'PT-BR') {
        if (empty($htmlText)) return $htmlText;

        $authKey = trim($_ENV['DEEPL_API_KEY']); 
        
        $isFree = str_ends_with($authKey, ':fx');
        $url = $isFree ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';

        $data = http_build_query([
            'text' => $htmlText,
            'target_lang' => $targetLang ?: 'PT-BR',
            'tag_handling' => 'html'
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: DeepL-Auth-Key ' . $authKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('Erro cURL (DeepL): ' . curl_error($ch));
            curl_close($ch);
            return $htmlText;
        }
        
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['translations'][0]['text'] ?? $htmlText;
    }
}

?>