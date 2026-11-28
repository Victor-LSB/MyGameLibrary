<?php
namespace Victi\MyGameLibrary\Controllers;

use PDO;
use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\Game;
use Victi\MyGameLibrary\Models\User;

/**
 * Cards de compartilhamento (imagem 1200x630, tamanho padrão de prévia de link do
 * WhatsApp/Discord/X). O fundo é o banner do perfil, escurecido (sem banner: fundo roxo).
 *
 *   Perfil:         index.php?action=profile_card&u=victor
 *                   avatar, nome, números da biblioteca e o Top 5
 *   Retrospectiva:  index.php?action=year_card&u=victor&y=2026
 *                   ano, nome, números do ano e os favoritos do ano
 *
 * A imagem fica em cache em storage/cache/cards e só é desenhada de novo quando algo que
 * aparece nela muda. Precisa da extensão GD do PHP (pacote php-gd); sem ela, redireciona
 * para o avatar (ou o ícone do site).
 */
class ProfileCardController {
    private const W = 1200;
    private const H = 630;

    private $db;
    private $fontDir;

    public function __construct() {
        $this->db = (new Database())->connect();
        $this->fontDir = dirname(__DIR__, 2) . '/resources/fonts';
    }

    public static function isSupported(): bool {
        return function_exists('imagecreatetruecolor') && function_exists('imagettftext');
    }

    public function image() {
        $username = trim((string) ($_GET['u'] ?? ''));
        $user = $username !== '' ? (new User($this->db))->getUserByUsername($username) : null;
        if (!$user) {
            http_response_code(404);
            exit;
        }

        if (!self::isSupported()) {
            error_log('ProfileCard: extensão GD (com FreeType) não instalada; instale php-gd.');
            header('Location: ' . (mgl_avatar_url($user['avatar'] ?? null) ?: 'assets/icon/icong.png'));
            exit;
        }

        $data = $this->collect($user);
        $this->serve((string) (int) $user['id'], $data, fn($file) => $this->render($data, $file));
    }

    /** Card da retrospectiva do ano. */
    public function yearImage() {
        $username = trim((string) ($_GET['u'] ?? ''));
        $user = $username !== '' ? (new User($this->db))->getUserByUsername($username) : null;
        $year = (int) ($_GET['y'] ?? date('Y'));
        if (!$user || $year < 1980 || $year > (int) date('Y')) {
            http_response_code(404);
            exit;
        }

        if (!self::isSupported()) {
            error_log('ProfileCard: extensão GD (com FreeType) não instalada; instale php-gd.');
            header('Location: ' . (mgl_avatar_url($user['banner'] ?? null) ?: 'assets/icon/icong.png'));
            exit;
        }

        $review = (new YearReviewController())->buildReview((int) $user['id'], $year);
        $gameModel = new Game($this->db);
        // Capas: favoritos do ano; se forem poucos, completa com os outros zerados
        $covers = [];
        foreach (array_merge($review['favorites'], array_reverse($review['completed'])) as $g) {
            $cover = !empty($g['cover_image']) ? $gameModel->normalizeCover($g['cover_image']) : null;
            if ($cover && !in_array($cover, $covers, true)) $covers[] = $cover;
            if (count($covers) >= 5) break;
        }
        $data = [
            'name'      => ($user['display_name'] ?? '') ?: $user['username'],
            'username'  => $user['username'],
            'banner'    => $user['banner'] ?? null,
            'year'      => $year,
            'completed' => (int) $review['completedCount'],
            'hours'     => (int) round((float) $review['hours']),
            'platinums' => (int) $review['platinums'],
            'rating'    => $review['avgRating'] !== null ? round((float) $review['avgRating'], 1) : null,
            'covers'    => $covers,
        ];
        $this->serve('y' . $year . '-' . (int) $user['id'], $data, fn($file) => $this->renderYear($data, $file));
    }

    /**
     * Entrega a imagem do cache (storage/cache/cards), desenhando de novo só quando
     * algo que aparece nela mudou. $key separa os cards (perfil, cada ano...).
     */
    private function serve(string $key, array $data, callable $draw): void {
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/cards';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $hash = substr(md5(json_encode($data)), 0, 12);
        $file = $cacheDir . '/' . $key . '-' . $hash . '.jpg';

        if (!is_file($file)) {
            // Versões antigas deste card não servem mais
            foreach (glob($cacheDir . '/' . $key . '-*.jpg') ?: [] as $old) {
                @unlink($old);
            }
            $draw($file);
        }

        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=3600');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    /** Tudo que aparece no card (também serve de chave do cache). */
    private function collect(array $user): array {
        $userId = (int) $user['id'];
        $stmt = $this->db->prepare("SELECT COUNT(*) AS games,
                SUM(status = 'Zerado') AS completed,
                SUM(platinum_at IS NOT NULL) AS platinums,
                AVG(rating) AS avg_rating
            FROM user_games WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $gameModel = new Game($this->db);
        $covers = [];
        $label = 'TOP 5';
        // Mantém a posição de cada jogo no Top 5 (slot vazio = null), para o #N bater
        foreach ($gameModel->getTopGames($userId) as $slot) {
            $covers[] = ($slot && !empty($slot['cover'])) ? $slot['cover'] : null;
        }
        if (!array_filter($covers)) {
            $covers = [];
            // Sem Top 5: usa os jogos adicionados mais recentemente
            $label = 'NA BIBLIOTECA';
            foreach ($gameModel->getRecentGamesByUserId($userId, 5) as $g) {
                if (!empty($g['cover_image'])) {
                    $covers[] = $gameModel->normalizeCover($g['cover_image']);
                }
            }
        }

        return [
            'name'      => ($user['display_name'] ?? '') ?: $user['username'],
            'username'  => $user['username'],
            'avatar'    => $user['avatar'] ?? null,
            'banner'    => $user['banner'] ?? null,
            'games'     => (int) ($stats['games'] ?? 0),
            'completed' => (int) ($stats['completed'] ?? 0),
            'platinums' => (int) ($stats['platinums'] ?? 0),
            'rating'    => $stats['avg_rating'] !== null ? round((float) $stats['avg_rating'], 1) : null,
            'covers'    => array_slice($covers, 0, 5),  // índice = posição
            'label'     => $label,
        ];
    }

    private function render(array $d, string $file): void {
        $im = imagecreatetruecolor(self::W, self::H);
        imagealphablending($im, true);

        $c = fn($hex, $alpha = 0) => imagecolorallocatealpha($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)), $alpha);
        $black = $this->font('Black');
        $bold = $this->font('Bold');
        $medium = $this->font('Medium');

        $this->paintBackground($im, $c, $d['banner'] ?? null);

        // Avatar
        $ax = 64; $ay = 64; $as = 200;
        imagefilledrectangle($im, $ax - 6, $ay - 6, $ax + $as + 5, $ay + $as + 5, $c('#27272a'));
        $avatar = $this->loadAvatar($d['avatar']);
        if ($avatar) {
            $this->copyCover($im, $avatar, $ax, $ay, $as, $as);
            imagedestroy($avatar);
        } else {
            imagefilledrectangle($im, $ax, $ay, $ax + $as - 1, $ay + $as - 1, $c('#18181b'));
            $initial = mb_strtoupper(mb_substr($d['username'], 0, 1));
            $this->textCentered($im, 110, $black, $c('#52525b'), $initial, $ax, $ax + $as, $ay + 150);
        }

        // Nome e usuário
        $tx = 304;
        $maxW = self::W - $tx - 64;
        $nameSize = 64;
        while ($nameSize > 40 && $this->textWidth($nameSize, $black, $d['name']) > $maxW) {
            $nameSize -= 4;
        }
        imagettftext($im, $nameSize, 0, $tx, 132, $c('#ffffff'), $black, $this->fit($d['name'], $nameSize, $black, $maxW));
        imagettftext($im, 26, 0, $tx, 182, $c('#a78bfa'), $medium, $this->fit('@' . $d['username'], 26, $medium, $maxW));

        // Números
        $tiles = [
            ['JOGOS', (string) $d['games'], '#ffffff'],
            ['ZERADOS', (string) $d['completed'], '#34d399'],
            ['PLATINAS', (string) $d['platinums'], '#fbbf24'],
            ['NOTA MÉDIA', $d['rating'] !== null ? number_format($d['rating'], 1, ',', '') : '—', '#fbbf24'],
        ];
        $tw = 196; $th = 96; $gap = 12; $ty = 206;
        foreach ($tiles as $i => [$label, $value, $color]) {
            $x = $tx + $i * ($tw + $gap);
            imagefilledrectangle($im, $x, $ty, $x + $tw, $ty + $th, $c('#18181b'));
            imagerectangle($im, $x, $ty, $x + $tw, $ty + $th, $c('#27272a'));
            imagettftext($im, 40, 0, $x + 18, $ty + 56, $c($color), $black, $value);
            imagettftext($im, 13, 0, $x + 18, $ty + 82, $c('#71717a'), $bold, $label);
        }

        // Capas (Top 5 ou recentes)
        $cy = 390; $ch = 180; $cw = (int) round($ch * 3 / 4); $cg = 16;
        imagettftext($im, 16, 0, 64, $cy - 18, $c('#a1a1aa'), $bold, $d['label']);
        $images = $this->fetchImages($d['covers']);
        for ($i = 0; $i < 5; $i++) {
            $x = 64 + $i * ($cw + $cg);
            if (isset($images[$i])) {
                $this->copyCover($im, $images[$i], $x, $cy, $cw, $ch);
                imagedestroy($images[$i]);
                imagerectangle($im, $x, $cy, $x + $cw - 1, $cy + $ch - 1, $i === 0 && $d['label'] === 'TOP 5' ? $c('#fbbf24') : $c('#27272a'));
            } else {
                imagerectangle($im, $x, $cy, $x + $cw - 1, $cy + $ch - 1, $c('#27272a'));
            }
            if ($d['label'] === 'TOP 5') {
                imagefilledrectangle($im, $x + 8, $cy + 8, $x + 44, $cy + 32, $c('#09090b', 20));
                imagettftext($im, 13, 0, $x + 13, $cy + 26, $c('#ffffff'), $black, '#' . ($i + 1));
            }
        }

        // Marca
        $bx = 64 + 5 * ($cw + $cg) + 32;
        $brandW = self::W - 64 - $bx;
        $brandSize = 30;
        while ($brandSize > 16 && $this->textWidth($brandSize, $black, 'MYGAMELIBRARY') > $brandW) {
            $brandSize -= 1;
        }
        imagettftext($im, $brandSize, 0, $bx, $cy + 118, $c('#ffffff'), $black, 'MYGAMELIBRARY');
        imagefilledrectangle($im, $bx, $cy + 134, $bx + 60, $cy + 139, $c('#7c3aed'));
        $host = parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST);
        imagettftext($im, 17, 0, $bx, $cy + 172, $c('#71717a'), $medium, $this->fit($host ?: 'Sua biblioteca de jogos', 17, $medium, $brandW));

        imagejpeg($im, $file, 86);
        imagedestroy($im);
    }

    /**
     * Fundo do card: o banner do perfil cobrindo tudo, escurecido para o texto ficar
     * legível (mais escuro à esquerda, onde fica o texto). Sem banner: fundo escuro
     * com brilho roxo. Sempre termina com a faixa roxa no topo.
     */
    private function paintBackground($im, callable $c, ?string $banner): void {
        imagefilledrectangle($im, 0, 0, self::W, self::H, $c('#09090b'));
        $img = $this->loadImage($banner);
        if ($img) {
            $this->copyCover($im, $img, 0, 0, self::W, self::H);
            imagedestroy($img);
            // véu escuro por cima de tudo (alpha GD: 0 = opaco, 127 = transparente)
            imagefilledrectangle($im, 0, 0, self::W, self::H, $c('#09090b', 40));
            // e mais escuro da esquerda para a direita, em faixas
            for ($x = 0; $x < self::W; $x += 20) {
                $alpha = (int) min(127, 50 + ($x / self::W) * 110);
                imagefilledrectangle($im, $x, 0, $x + 19, self::H, $c('#09090b', $alpha));
            }
        } else {
            for ($i = 0; $i < 40; $i++) {
                // camadas quase transparentes empilhadas: mais forte no canto, some em direção ao centro
                imagefilledellipse($im, 0, 0, 1400 - $i * 30, 1000 - $i * 22, $c('#6d28d9', 124));
            }
        }
        imagefilledrectangle($im, 0, 0, self::W, 8, $c('#7c3aed'));
    }

    /** Card da retrospectiva: ano grande, nome, números do ano e as capas dos favoritos. */
    private function renderYear(array $d, string $file): void {
        $im = imagecreatetruecolor(self::W, self::H);
        imagealphablending($im, true);
        $c = fn($hex, $alpha = 0) => imagecolorallocatealpha($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)), $alpha);
        $black = $this->font('Black');
        $bold = $this->font('Bold');
        $medium = $this->font('Medium');

        $this->paintBackground($im, $c, $d['banner']);

        // "RETROSPECTIVA" espaçado, ano enorme, "O ano em jogos de ..."
        $x = 64;
        foreach (mb_str_split('RETROSPECTIVA') as $ch) {
            imagettftext($im, 16, 0, $x, 84, $c('#c4b5fd'), $black, $ch);
            $x += $this->textWidth(16, $black, $ch) + 6;
        }
        imagettftext($im, 120, 0, 58, 222, $c('#ffffff'), $black, (string) $d['year']);
        $line = 'O ano em jogos de ' . $d['name'];
        imagettftext($im, 26, 0, 64, 272, $c('#d4d4d8'), $medium, $this->fit($line, 26, $medium, self::W - 128));

        // Números do ano (grade 2x2 à esquerda)
        $tiles = [
            ['ZERADOS', (string) $d['completed'], '#34d399'],
            ['HORAS JOGADAS', number_format($d['hours'], 0, ',', '.'), '#ffffff'],
            ['PLATINAS', (string) $d['platinums'], '#fbbf24'],
            ['NOTA MÉDIA', $d['rating'] !== null ? number_format($d['rating'], 1, ',', '') : '—', '#fbbf24'],
        ];
        $tw = 250; $th = 86; $gap = 12; $ty0 = 302;
        foreach ($tiles as $i => [$label, $value, $color]) {
            $tx = 64 + ($i % 2) * ($tw + $gap);
            $ty = $ty0 + intdiv($i, 2) * ($th + $gap);
            imagefilledrectangle($im, $tx, $ty, $tx + $tw, $ty + $th, $c('#18181b', 20));
            imagerectangle($im, $tx, $ty, $tx + $tw, $ty + $th, $c('#3f3f46'));
            $vs = 36;
            while ($vs > 22 && $this->textWidth($vs, $black, $value) > $tw - 36) $vs -= 2;
            imagettftext($im, $vs, 0, $tx + 18, $ty + 50, $c($color), $black, $value);
            imagettftext($im, 12, 0, $tx + 18, $ty + 74, $c('#a1a1aa'), $bold, $label);
        }

        // Capas dos favoritos à direita, sobrepostas em leque (a 1ª fica por cima)
        $images = $this->fetchImages($d['covers']);
        $n = count($d['covers']);
        if ($n > 0) {
            $ch = 300; $cw = (int) round($ch * 3 / 4);
            $areaX = 640; $areaW = self::W - 64 - $areaX;
            $step = $n > 1 ? (int) floor(($areaW - $cw) / ($n - 1)) : 0;
            $step = min($step, $cw + 12);
            $startX = $areaX + (int) (($areaW - ($cw + $step * ($n - 1))) / 2);
            imagettftext($im, 14, 0, $startX, 128, $c('#a1a1aa'), $bold, 'FAVORITOS DO ANO');
            for ($i = $n - 1; $i >= 0; $i--) {
                $cx = $startX + $i * $step;
                $cy = 150 + $i * 14;
                // moldura escura separa as capas sobrepostas
                imagefilledrectangle($im, $cx - 4, $cy - 4, $cx + $cw + 3, $cy + $ch + 3, $c('#09090b'));
                if (isset($images[$i])) {
                    $this->copyCover($im, $images[$i], $cx, $cy, $cw, $ch);
                } else {
                    imagefilledrectangle($im, $cx, $cy, $cx + $cw - 1, $cy + $ch - 1, $c('#18181b'));
                }
                imagerectangle($im, $cx, $cy, $cx + $cw - 1, $cy + $ch - 1, $i === 0 ? $c('#fbbf24') : $c('#3f3f46'));
            }
        }
        foreach ($images as $img) imagedestroy($img);

        // Marca no rodapé
        imagettftext($im, 26, 0, 64, 560, $c('#ffffff'), $black, 'MYGAMELIBRARY');
        imagefilledrectangle($im, 64, 576, 124, 581, $c('#7c3aed'));
        $host = parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST);
        imagettftext($im, 17, 0, 64, 610, $c('#a1a1aa'), $medium, $host ?: 'Sua biblioteca de jogos');

        imagejpeg($im, $file, 86);
        imagedestroy($im);
    }

    private function font(string $weight): string {
        return $this->fontDir . '/Inter-' . $weight . '.otf';
    }

    private function textWidth(int $size, string $font, string $text): int {
        $box = imagettfbbox($size, 0, $font, $text);
        return $box ? abs($box[2] - $box[0]) : 0;
    }

    /** Corta o texto com "…" até caber na largura. */
    private function fit(string $text, int $size, string $font, int $maxW): string {
        if ($this->textWidth($size, $font, $text) <= $maxW) {
            return $text;
        }
        while (mb_strlen($text) > 1 && $this->textWidth($size, $font, $text . '…') > $maxW) {
            $text = mb_substr($text, 0, -1);
        }
        return rtrim($text) . '…';
    }

    private function textCentered($im, int $size, string $font, int $color, string $text, int $x1, int $x2, int $y): void {
        $w = $this->textWidth($size, $font, $text);
        imagettftext($im, $size, 0, (int) ($x1 + ($x2 - $x1 - $w) / 2), $y, $color, $font, $text);
    }

    /** Copia a imagem preenchendo a área inteira (como object-fit: cover). */
    private function copyCover($dst, $src, int $x, int $y, int $w, int $h): void {
        $sw = imagesx($src); $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = (int) round($w / $scale); $ch = (int) round($h / $scale);
        $sx = (int) (($sw - $cw) / 2); $sy = (int) (($sh - $ch) / 2);
        imagecopyresampled($dst, $src, $x, $y, $sx, $sy, $w, $h, $cw, $ch);
    }

    private function loadAvatar(?string $avatar) {
        return $this->loadImage($avatar);
    }

    /** Avatar/banner: URL completa ou arquivo em public/uploads/profile. */
    private function loadImage(?string $value) {
        if (empty($value)) {
            return null;
        }
        if (str_starts_with($value, 'http')) {
            $fetched = $this->fetchImages([$value]);
            return $fetched[0] ?? null;
        }
        $path = dirname(__DIR__, 2) . '/public/uploads/profile/' . basename($value);
        $bytes = is_file($path) ? @file_get_contents($path) : false;
        $img = $bytes ? @imagecreatefromstring($bytes) : false;
        return $img ?: null;
    }

    /** Baixa as imagens em paralelo (timeout curto); devolve [mesmo índice da entrada => imagem GD]. */
    private function fetchImages(array $urls): array {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($urls as $i => $url) {
            if (!preg_match('#^https?://#i', (string) $url)) {
                continue;
            }
            // http:// sobe para https:// (exceto no ambiente local)
            $ch = curl_init(preg_replace('#^http://(?!localhost|127\.)#i', 'https://', $url));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_USERAGENT => 'MyGameLibrary/1.0',
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$i] = $ch;
        }
        do {
            curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi);
            }
        } while ($running > 0);

        $images = [];
        foreach ($handles as $i => $ch) {
            $bytes = curl_multi_getcontent($ch);
            $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400;
            if ($ok && $bytes && strlen($bytes) < 8 * 1024 * 1024) {
                $img = @imagecreatefromstring($bytes);
                if ($img) {
                    $images[$i] = $img;
                }
            }
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
        return $images;
    }
}
