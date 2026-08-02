<?php
namespace Victi\MyGameLibrary\Services;

class GameAPI {
    private $apiKey;
    private $baseUrl = 'https://api.rawg.io/api/games';
    private $cacheDir;
    private $cacheTtl = 300; // 5 minutos: buscas repetidas (ex: "the", "witcher", "the witcher") saem do cache

    // Offset somado ao appid da Steam para gerar um "id" que nunca colide com um id da RAWG
    // (hoje a RAWG não chega nem perto disso) e continua sendo só dígitos, já que o resto do
    // código sanitiza external_id com FILTER_SANITIZE_NUMBER_INT. getGameDetails() usa esse
    // mesmo offset pra saber, só olhando o número, se deve buscar na RAWG ou na Steam.
    private $steamIdOffset = 900000000;

    public function __construct() {
        $this->apiKey = $_ENV['RAWG_API_KEY'] ?? '';
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

    // Mantém só os campos que a tela de busca realmente usa, deixando o JSON
    // bem menor e mais rápido de transferir/renderizar
    private function trimGameForList(array $game) {
        return [
            'id' => $game['id'] ?? null,
            'name' => $game['name'] ?? '',
            'background_image' => $game['background_image'] ?? null,
            'released' => $game['released'] ?? null,
            'genres' => array_map(fn($g) => ['name' => $g['name'] ?? ''], $game['genres'] ?? []),
            'platforms' => array_map(fn($p) => ['platform' => ['name' => $p['platform']['name'] ?? '']], $game['platforms'] ?? []),
            // Usado só para filtrar resultados ruins (fangames, demos, mods obscuros);
            // não é usado na tela, removido antes de devolver a resposta
            '_added' => $game['added'] ?? 0,
        ];
    }

    // Tira da lista jogos com engajamento praticamente nulo na RAWG (fangames, demos
    // caseiras, mods, entradas de teste), que "batem" com o texto buscado mas não são
    // o que o usuário está procurando. Se filtrar demais (busca por algo bem de nicho),
    // recua e devolve a lista original pra não sumir com resultados válidos.
    private function filterLowQualityResults(array $results, $minAdded = 10) {
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

    // true quando o id já veio "deslocado" pelo offset da Steam (ver comentário
    // em $steamIdOffset), ou seja, é um appid da Steam e não um id da RAWG.
    private function isSteamId($id) {
        return (int) $id >= $this->steamIdOffset;
    }

    // "21 Aug, 2012" (formato que a Steam devolve com l=english) -> "2012-08-21",
    // pra ficar no mesmo formato ISO que a RAWG usa (a tela mostra só o ano,
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

    private function normalizeRawgGameData(array $gameData) {
        if (!empty($gameData['genres']) && is_array($gameData['genres'])) {
            $gameData['genres'] = array_values(array_filter(array_map(function ($genre) {
                $name = is_array($genre) ? ($genre['name'] ?? '') : $genre;
                $normalized = $this->normalizeGenreName($name);

                return $normalized !== '' ? ['name' => $normalized] : null;
            }, $gameData['genres'])));
        }

        return $gameData;
    }

    public function searchGames($query) {

        // 1) Cache primeiro: se alguém já buscou isso nos últimos minutos, devolve na hora sem tocar em nenhuma API
        $cached = $this->getFromCache($query);
        if ($cached !== null) {
            return $cached;
        }

        // RAWG e o storesearch da Steam saem AO MESMO TEMPO (curl_multi). Antes a Steam só
        // era chamada depois da RAWG dar timeout (3s) e falhar — daí a soma dos tempos batia
        // uns 10s com a RAWG fora do ar. Disparando os dois juntos, o pior caso vira o maior
        // dos dois tempos, não a soma.
        [$rawgPayload, $steamSearchPayload] = $this->fetchRawgAndSteamSearchConcurrently($query);

        $payload = $this->processRawgSearchPayload($rawgPayload, $query);

        // RAWG fora do ar, com timeout, rate-limited etc.: cai pro fallback da Steam
        // em vez de devolver busca vazia/erro pro usuário
        if ($payload === null) {
            $payload = $this->searchGamesSteam($query, $steamSearchPayload);
        }

        // Só guarda em cache respostas válidas (evita cachear erro/timeout), sejam
        // elas da RAWG ou do fallback da Steam
        if (is_array($payload) && isset($payload['results']) && is_array($payload['results'])) {
            $this->saveToCache($query, $payload);
        }

        return $payload;
    }

    // Dispara a busca da RAWG e o storesearch da Steam ao mesmo tempo e espera os dois.
    // Devolve [rawgPayload, steamSearchPayload], cada um null se aquela chamada falhou/expirou.
    private function fetchRawgAndSteamSearchConcurrently($query) {
        $rawgUrl = "https://api.rawg.io/api/games?key={$this->apiKey}&search=" . urlencode($query) . "&page_size=15";
        $steamUrl = 'https://store.steampowered.com/api/storesearch/?term=' . urlencode($query) . '&l=english&cc=us';

        $multiHandle = curl_multi_init();

        $rawgCh = curl_init();
        curl_setopt_array($rawgCh, [
            CURLOPT_URL => $rawgUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: MyGameLibrary/1.0'],
        ]);
        curl_multi_add_handle($multiHandle, $rawgCh);

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

        $rawgOk = !curl_errno($rawgCh) && curl_getinfo($rawgCh, CURLINFO_HTTP_CODE) < 400;
        $steamOk = !curl_errno($steamCh) && curl_getinfo($steamCh, CURLINFO_HTTP_CODE) < 400;

        $rawgResponse = $rawgOk ? curl_multi_getcontent($rawgCh) : null;
        $steamResponse = $steamOk ? curl_multi_getcontent($steamCh) : null;

        curl_multi_remove_handle($multiHandle, $rawgCh);
        curl_multi_remove_handle($multiHandle, $steamCh);
        curl_close($rawgCh);
        curl_close($steamCh);
        curl_multi_close($multiHandle);

        $rawgPayload = $rawgResponse ? json_decode($rawgResponse, true) : null;
        $steamPayload = $steamResponse ? json_decode($steamResponse, true) : null;

        return [
            is_array($rawgPayload) ? $rawgPayload : null,
            is_array($steamPayload) ? $steamPayload : null,
        ];
    }

    // Recebe o payload cru da RAWG (ou null) e devolve null se ele não for confiável,
    // ou já filtrado/pronto pra exibição se for.
    private function processRawgSearchPayload($payload, $query) {
        if ($payload === null || !isset($payload['results']) || !is_array($payload['results'])) {
            return null;
        }

        $trimmed = array_map(
            fn($game) => $this->trimGameForList($this->normalizeRawgGameData($game)),
            $payload['results']
        );

        $relevant = $this->filterByRelevance($trimmed, $query);
        $payload['results'] = array_slice($this->filterLowQualityResults($relevant), 0, 10);

        return $payload;
    }

    // Fallback usando a Steam Store API (storesearch + appdetails) quando a RAWG está indisponível.
    // storesearch/appdetails são endpoints públicos da loja da Steam, não precisam de STEAM_API_KEY
    // (essa chave é da Web API oficial, usada por outros endpoints que não existem aqui).
    // $searchPayload: se já veio de fetchRawgAndSteamSearchConcurrently(), reaproveita em vez de
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
                // igual à RAWG (que também usa screenshot como capa da lista).
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
        // (buscado enquanto a RAWG estava fora do ar), então busca na Steam também
        if ($this->isSteamId($gameID)) {
            return $this->getGameDetailsSteam((int) $gameID - $this->steamIdOffset);
        }

        $url = "https://api.rawg.io/api/games/" . $gameID . "?key=" . $this->apiKey;
        $payload = $this->fetchUrl($url, 5, 3);

        // Se a RAWG cair bem nesse instante (raro: geralmente já caiu na busca antes),
        // não dá pra "adivinhar" um appid da Steam pra esse id — só devolve null e o
        // jogo é salvo sem descrição/gêneros vindos da API, que é o que já acontecia
        // antes quando essa chamada falhava.
        return $payload !== null ? $this->normalizeRawgGameData($payload) : null;
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

        return $this->normalizeRawgGameData([
            'id' => $appId + $this->steamIdOffset,
            'name' => $data['name'] ?? '',
            // A Steam separa descrição curta/longa; usamos a longa (equivalente ao
            // campo "description" que a RAWG devolve e que vai pro translateHTML)
            'description' => $data['detailed_description'] ?? ($data['short_description'] ?? ''),
            'genres' => $genres,
            'background_image' => $data['header_image'] ?? null,
            'released' => $this->normalizeSteamDate($data['release_date']['date'] ?? null),
        ]);
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