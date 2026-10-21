<?php
// Funções auxiliares globais, carregadas por public/index.php em toda requisição.

if (!function_exists('mgl_asset')) {
    /**
     * Endereço de um arquivo de public/ com ?v=<data de modificação>.
     * Quando o arquivo muda, o endereço muda, e o Cloudflare/navegador baixam a
     * versão nova em vez de servir a que estava guardada em cache.
     */
    function mgl_asset(string $path): string {
        $path = ltrim($path, './');
        $file = __DIR__ . '/../public/' . $path;
        return './' . $path . '?v=' . (int) @filemtime($file);
    }
}
