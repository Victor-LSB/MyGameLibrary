<?php
namespace Victi\MyGameLibrary\Services;

class GameAPI {
    private $apiKey;
    private $baseUrl = 'https://api.rawg.io/api/games';

    public function __construct() {
        $this->apiKey = $_ENV['RAWG_API_KEY'] ?? '';
    }

    public function searchGames($query) {
        
        $url = "https://api.rawg.io/api/games?key={$this->apiKey}&search=" . urlencode($query) . "&page_size=20";
        
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
        return json_decode($response, true);
    }


    public function getGameDetails($gameID) {
    $url = "https://api.rawg.io/api/games/" . $gameID . "?key=" . $this->apiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GameLoggd');
    $response = curl_exec($ch);
    curl_close($ch);

   
    return json_decode($response, true);
    }

    public function translateHTML($htmlText) {
        if (empty($htmlText)) return $htmlText;

        $authKey = trim($_ENV['DEEPL_API_KEY']); 
        
        $isFree = str_ends_with($authKey, ':fx');
        $url = $isFree ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';

        $data = http_build_query([
            'text' => $htmlText,
            'target_lang' => 'PT-BR',
            'tag_handling' => 'html'
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        
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