<?php
/**
 * URLs amigáveis (/perfil/victor, /jogo/12, /biblioteca...) em cima do roteamento
 * atual por ?action=. Nenhum controller muda: o roteador só traduz o caminho para o
 * mesmo $_GET que o site já usa, e as URLs antigas (index.php?action=...) continuam
 * funcionando.
 *
 * - Traduzir /perfil/victor -> action=profile&u=victor funciona sempre que o servidor
 *   mandar o pedido para o index.php (veja public/.htaccess).
 * - Fazer o site MOSTRAR as URLs bonitas (redirecionando as antigas e gerando os links
 *   novos) só acontece com PRETTY_URLS=true no .env.
 */

if (!function_exists('mgl_base_path')) {

    /**
     * Pasta pública do site, sempre terminando em "/" ("/" na raiz do domínio).
     * Funciona também quando o projeto roda numa subpasta (ex.: localhost/MyGameLibrary/public/).
     */
    function mgl_base_path(): string {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        return $dir === '' ? '/' : $dir . '/';
    }

    /**
     * <base href>: faz todo caminho relativo do site (index.php?..., ./assets/..., fetch do JS)
     * partir da raiz, mesmo numa URL como /perfil/victor.
     */
    function mgl_base_tag(): string {
        return '<base href="' . htmlspecialchars(mgl_base_path(), ENT_QUOTES) . '">';
    }

    function mgl_pretty_enabled(): bool {
        $value = $_ENV['PRETTY_URLS'] ?? getenv('PRETTY_URLS') ?: 'false';
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * action => [caminho, parâmetro que vira o 2º trecho da URL (ou null)].
     * Só as páginas "de verdade". Endpoints do JS, ações de formulário, links de e-mail
     * (verificar e-mail, redefinir senha) continuam como index.php?action=...
     */
    function mgl_pretty_routes(): array {
        return [
            'home'            => ['biblioteca', null],
            'profile'         => ['perfil', 'u'],
            'details'         => ['jogo', 'id'],
            'feed'            => ['feed', null],
            'dashboard'       => ['dashboard', null],
            'search'          => ['adicionar-jogo', null],
            'login'           => ['entrar', null],
            'register'        => ['cadastro', null],
            'edit_profile'    => ['editar-perfil', null],
            'change_password' => ['alterar-senha', null],
        ];
    }

    /**
     * Endereço de uma página: bonito quando PRETTY_URLS=true, senão o formato antigo.
     * mgl_url('profile', ['u' => 'victor', 'view' => 'all']) -> /perfil/victor?view=all
     */
    function mgl_url(string $action, array $params = []): string {
        $routes = mgl_pretty_routes();

        if (!mgl_pretty_enabled() || !isset($routes[$action])) {
            return 'index.php?' . http_build_query(['action' => $action] + $params);
        }

        [$slug, $pathParam] = $routes[$action];
        $path = mgl_base_path() . $slug;
        if ($pathParam !== null && isset($params[$pathParam]) && $params[$pathParam] !== '') {
            $path .= '/' . rawurlencode((string) $params[$pathParam]);
            unset($params[$pathParam]);
        }
        return $path . ($params ? '?' . http_build_query($params) : '');
    }

    /**
     * Se o pedido veio como /perfil/victor, preenche $_GET['action'] (+ u, id...) como se
     * fosse index.php?action=profile&u=victor e devolve true. Caminho desconhecido = 404.
     * Pedido normal (index.php ou raiz) não muda e devolve false.
     */
    function mgl_route_request(): bool {
        $uri  = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = mgl_base_path();

        if (rtrim($uri, '/') === rtrim($base, '/')) {
            return false;
        }
        $path = strpos($uri, $base) === 0 ? substr($uri, strlen($base)) : ltrim($uri, '/');
        $path = trim($path, '/');

        if ($path === '' || $path === 'index.php') {
            return false;
        }

        $segments = explode('/', $path);
        foreach (mgl_pretty_routes() as $action => [$slug, $pathParam]) {
            if ($segments[0] !== $slug) {
                continue;
            }
            $maxSegments = $pathParam !== null ? 2 : 1;
            if (count($segments) > $maxSegments) {
                break;
            }
            $_GET['action'] = $action;
            if ($pathParam !== null && isset($segments[1]) && $segments[1] !== '') {
                $_GET[$pathParam] = rawurldecode($segments[1]);
            }
            return true;
        }

        http_response_code(404);
        echo '404 - Página não encontrada.';
        exit;
    }

    /**
     * Com PRETTY_URLS=true, quem abrir o formato antigo (index.php?action=profile&u=victor)
     * é levado para o novo (/perfil/victor). Só para páginas abertas por GET: formulários
     * (POST), chamadas do JS (fetch/JSON) e o ?partial= da biblioteca ficam de fora.
     * Redirecionamento temporário (302) de propósito, para poder desligar o recurso sem
     * deixar o navegador "lembrando" a URL nova.
     */
    function mgl_redirect_to_pretty(): void {
        if (!mgl_pretty_enabled() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }
        if (!isset($_GET['action']) || isset($_GET['partial'])) {
            return;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            || strpos($accept, 'application/json') !== false
            || strpos($accept, 'text/event-stream') !== false) {
            return;
        }

        $routes = mgl_pretty_routes();
        $action = (string) $_GET['action'];
        if (!isset($routes[$action])) {
            return;
        }

        $params = $_GET;
        unset($params['action']);
        $pathParam = $routes[$action][1];
        if ($pathParam === 'id' && empty($params['id'])) {
            return; // /jogo precisa do id; deixa o controller tratar
        }

        header('Location: ' . mgl_url($action, $params), true, 302);
        exit;
    }

    /**
     * Lê um parâmetro de $_GET aplicando um filtro, igual ao filter_input(INPUT_GET, ...).
     * Existe porque filter_input lê a query ORIGINAL do pedido e não enxerga o que o roteador
     * coloca em $_GET (o "victor" de /perfil/victor, o "12" de /jogo/12).
     * Devolve null quando o parâmetro não existe, como o filter_input.
     */
    function mgl_get(string $key, int $filter = FILTER_DEFAULT) {
        return isset($_GET[$key]) ? filter_var($_GET[$key], $filter) : null;
    }

    /**
     * Troca href="index.php?action=profile&u=x" por href="/perfil/x" no HTML da página.
     * Assim os links do site já saem bonitos e o clique não precisa passar pelo redirecionamento.
     * Só mexe em páginas conhecidas; links de endpoints/formulários ficam como estão.
     */
    function mgl_prettify_html(string $html): string {
        $routes = mgl_pretty_routes();
        return preg_replace_callback('/href="index\.php\?([^"#]*)(#[^"]*)?"/', function ($m) use ($routes) {
            parse_str(html_entity_decode($m[1], ENT_QUOTES), $params);
            $action = $params['action'] ?? '';
            if (!is_string($action) || !isset($routes[$action])) {
                return $m[0];
            }
            unset($params['action']);
            if ($routes[$action][1] === 'id' && empty($params['id'])) {
                return $m[0];
            }
            return 'href="' . htmlspecialchars(mgl_url($action, $params), ENT_QUOTES) . ($m[2] ?? '') . '"';
        }, $html) ?? $html;
    }

    /** Liga a troca de links acima nas páginas HTML (só com PRETTY_URLS=true). */
    function mgl_start_pretty_output(string $action): void {
        if (mgl_pretty_enabled() && isset(mgl_pretty_routes()[$action])) {
            ob_start('mgl_prettify_html');
        }
    }

    /**
     * Os controllers redirecionam com "Location: index.php?action=...". Como esse caminho é
     * relativo, a partir de /jogo/12 ele viraria /jogo/index.php. Este gancho roda na hora
     * de enviar os headers e ancora esses endereços na pasta do site, sem editar cada header().
     */
    function mgl_absolutize_redirects(): void {
        $base = mgl_base_path();
        header_register_callback(function () use ($base) {
            foreach (headers_list() as $header) {
                if (stripos($header, 'Location:') !== 0) {
                    continue;
                }
                $location = trim(substr($header, 9));
                if ($location === '' || $location[0] === '/' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
                    return;
                }
                $target = $base . ltrim($location, './');
                // Com PRETTY_URLS=true, já manda direto para a URL bonita (sem um 2º redirecionamento)
                if (mgl_pretty_enabled() && preg_match('/^index\.php\?([^#]*)(#.*)?$/', $location, $m)) {
                    parse_str($m[1], $params);
                    $page = $params['action'] ?? '';
                    $routes = mgl_pretty_routes();
                    if (is_string($page) && isset($routes[$page])
                        && !($routes[$page][1] === 'id' && empty($params['id']))) {
                        unset($params['action']);
                        $target = mgl_url($page, $params) . ($m[2] ?? '');
                    }
                }
                header('Location: ' . $target, true);
                return;
            }
        });
    }
}
