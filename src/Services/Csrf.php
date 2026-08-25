<?php
namespace Victi\MyGameLibrary\Services;

/**
 * Proteção contra CSRF (Cross-Site Request Forgery).
 *
 * Sem isso, um site malicioso poderia montar um formulário escondido
 * apontando para as rotas deste sistema e fazer o navegador de alguém
 * que já está logado aqui (ex: pelo cookie de sessão) enviar a
 * requisição sem que a pessoa perceba ou queira (ex: seguir alguém,
 * apagar um jogo, trocar o e-mail do perfil).
 *
 * Como funciona: geramos um token aleatório por sessão. Toda página
 * que tem um form (ou faz uma chamada fetch/AJAX) manda esse token de
 * volta junto com a requisição. Se o token não bater com o da sessão,
 * a requisição é recusada — só o navegador que carregou nossa própria
 * página tem como saber o valor certo.
 */
class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    private static function ensureSession(): void
    {
        Session::start();
    }

    /**
     * Retorna o token da sessão atual, gerando um novo se ainda não existir.
     * Usado nas views para preencher o campo hidden dos formulários e a
     * meta tag lida pelo JavaScript.
     */
    public static function token(): string
    {
        self::ensureSession();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Compara o token recebido com o da sessão. hash_equals evita que a
     * comparação vaze informação por timing attack.
     */
    public static function isValid(?string $token): bool
    {
        self::ensureSession();

        if (empty($token) || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * Chame no início de toda ação POST que altera dados. Procura o token
     * em três lugares possíveis (form clássico, header usado pelo fetch/JS,
     * ou corpo JSON já decodificado) e interrompe a requisição com 403 se
     * não encontrar um token válido em nenhum deles.
     *
     * @param array|null $jsonBody Corpo JSON já decodificado (rotas que recebem
     *                             fetch com Content-Type: application/json).
     */
    public static function verifyOrFail(?array $jsonBody = null): void
    {
        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? ($jsonBody['csrf_token'] ?? null);

        if (self::isValid($token)) {
            return;
        }

        http_response_code(403);

        if (self::clientWantsJson()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Token de segurança inválido ou expirado. Recarregue a página e tente novamente.',
            ]);
        } else {
            echo 'Token de segurança inválido ou expirado. Volte e recarregue a página antes de tentar novamente.';
        }

        exit();
    }

    private static function clientWantsJson(): bool
    {
        return stripos($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'xmlhttprequest') !== false
            || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'json') !== false;
    }
}
