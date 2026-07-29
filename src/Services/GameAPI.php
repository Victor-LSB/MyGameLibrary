<?php
namespace Victi\MyGameLibrary\Services;

class GameAPI {
    private $apiKey;
    private $baseUrl = 'https://api.rawg.io/api/games';
    private $cacheDir;
    private $cacheTtl = 300; // 5 minutos: buscas repetidas (ex: "the", "witcher", "the witcher") saem do cache

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

        // 1) Cache primeiro: se alguém já buscou isso nos últimos minutos, devolve na hora sem tocar na RAWG
        $cached = $this->getFromCache($query);
        if ($cached !== null) {
            return $cached;
        }

        // 2) page_size um pouco maior que o exibido: parte desses resultados vira "ruído"
        // (fangames, demos, mods) e é filtrado depois, então pedimos uma margem extra
        $url = "https://api.rawg.io/api/games?key={$this->apiKey}&search=" . urlencode($query) . "&page_size=15";
        
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
       //otimização
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); 
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate'); 
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
        curl_setopt($ch, CURLOPT_TCP_FASTOPEN, true); 
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); 
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, false); 
        curl_setopt($ch, CURLOPT_FORBID_REUSE, false); 
        curl_setopt($ch, CURLOPT_MAXREDIRS, 0); 
        
        // Headers otimizados
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'User-Agent: MyGameLibrary/1.0'
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => $error];
        }
        
        curl_close($ch);
        $payload = json_decode($response, true);

        if (is_array($payload) && !empty($payload['results']) && is_array($payload['results'])) {
            $trimmed = array_map(
                fn($game) => $this->trimGameForList($this->normalizeRawgGameData($game)),
                $payload['results']
            );

            $relevant = $this->filterByRelevance($trimmed, $query);
            $payload['results'] = array_slice($this->filterLowQualityResults($relevant), 0, 10);

            // Só guarda em cache respostas válidas (evita cachear erro/timeout)
            $this->saveToCache($query, $payload);
        }

        return $payload;
    }


    public function getGameDetails($gameID) {
    $url = "https://api.rawg.io/api/games/" . $gameID . "?key=" . $this->apiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GameLoggd');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);

   
    $payload = json_decode($response, true);

    return is_array($payload) ? $this->normalizeRawgGameData($payload) : $payload;
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