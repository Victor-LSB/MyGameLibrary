<?php
namespace Victi\MyGameLibrary\Controllers;
use Resend;
use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\User;
use Victi\MyGameLibrary\Services\RateLimiter;
use Victi\MyGameLibrary\Services\Csrf;
use Victi\MyGameLibrary\Services\PasswordPolicy;
use Victi\MyGameLibrary\Services\Session;
use Victi\MyGameLibrary\Services\ClientIp;
use Victi\MyGameLibrary\Services\Turnstile;
use Victi\MyGameLibrary\Services\EmailPolicy;

class AuthController {
    private $db;
    private $userModel;
    private $rateLimiter;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->userModel = new User($this->db);
        $this->rateLimiter = new RateLimiter($this->db);
    }

    /**
     * IP real do visitante. Só confia no header do Cloudflare quando a conexão
     * realmente veio do Cloudflare (X-Forwarded-For é forjável e não é usado).
     */
    private function clientIp() {
        return ClientIp::get();
    }

    /**
     * Envia (ou reenvia) o e-mail de verificação de conta via Resend.
     */
    private function sendVerificationEmail($email, $userId) {
        $token = bin2hex(random_bytes(50));
        $expires_at = date('Y-m-d H:i:s', time() + 86400); // 24h
        $this->userModel->saveVerificationToken($userId, $token, $expires_at);

        $baseUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?? 'http://localhost/MyGameLibrary/public';
        $verify_link = rtrim($baseUrl, '/') . "/index.php?action=verify_email&token=$token";

        $apiKey = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY');
        $resend = Resend::client($apiKey);

        try {
            $resend->emails->send([
                'from' => 'My Game Library <suporte@mygamelibrary.com.br>',
                'to' => [$email],
                'subject' => 'Confirme o seu e-mail',
                'html' => '<p>Olá!</p><p>Clique no link abaixo para confirmar o seu e-mail e ativar a sua conta:</p><p><a href="'.$verify_link.'">Confirmar e-mail</a></p><p>Este link expira em 24 horas.</p>',
            ]);
            return true;
        } catch (\Exception $e) {
            error_log('Erro ao enviar e-mail de verificação: ' . $e->getMessage());
            return false;
        }
    }

    private function startSession() {
        Session::start();
    }

    public function login() {
        $this->startSession();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Email e senha são obrigatórios.';
                include __DIR__ . '/../Views/auth/login.php';
                return;
            }

            // No máximo 5 tentativas de login a cada 15 minutos por IP + email
            $rlKey = $this->rateLimiter->key('login', $this->clientIp(), $email);
            if ($this->rateLimiter->tooManyAttempts($rlKey, 5, 15)) {
                $wait = $this->rateLimiter->minutesUntilReset($rlKey, 5);
                $error = "Muitas tentativas de login. Tente novamente em {$wait} minuto(s).";
                include __DIR__ . '/../Views/auth/login.php';
                return;
            }

            $user = $this->userModel->login($email, $password);

            if ($user) {
                // Bloqueia o acesso enquanto o e-mail não for confirmado.
                if (empty($user['email_verified_at'])) {
                    $error = 'Você precisa confirmar o seu e-mail antes de entrar. Verifique a sua caixa de entrada.';
                    $unverifiedEmail = $email;
                    include __DIR__ . '/../Views/auth/login.php';
                    return;
                }

                // Login válido: limpa o contador de tentativas dessa chave não é necessário,
                // ele expira sozinho pela janela de tempo.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email_verified'] = true;
                header("Location: index.php?action=home");
                exit();
            } else {
                $this->rateLimiter->hit($rlKey);
                $error = 'Email ou senha inválidos.';
                include __DIR__ . '/../Views/auth/login.php';
                return;
            }
        }

        include __DIR__ . '/../Views/auth/login.php';
    }

    public function register() {
        $this->startSession();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $ip = $this->clientIp();

            // 1) Limites de tentativas (contam TODA tentativa, não só as bem-sucedidas),
            //    checados antes de qualquer consulta ao banco ou chamada externa.
            $attemptKey = $this->rateLimiter->key('register_attempt', $ip);
            if ($this->rateLimiter->tooManyAttempts($attemptKey, 15, 60)) {
                $error = 'Muitas tentativas de cadastro a partir deste endereço. Tente novamente mais tarde.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }
            $this->rateLimiter->hit($attemptKey);

            // Disjuntor global: se o site inteiro está sendo inundado de cadastros, segura.
            $globalKey = $this->rateLimiter->key('register_global');
            if ($this->rateLimiter->tooManyAttempts($globalKey, 60, 60)) {
                $error = 'Cadastros temporariamente limitados. Tente novamente em alguns minutos.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            // 2) Honeypot: campo escondido que humanos nunca preenchem.
            //    Responde como se tivesse dado certo, para o bot não aprender o que o denunciou.
            if (!empty($_POST['website'])) {
                $this->rateLimiter->hit($globalKey);
                $success = 'Usuário registrado com sucesso! Enviámos um e-mail de confirmação — você só poderá entrar depois de confirmá-lo.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            // 3) Tempo mínimo entre abrir o formulário e enviar (bots enviam instantaneamente).
            $openedAt = $_SESSION['register_form_at'] ?? 0;
            if ($openedAt === 0 || (time() - $openedAt) < 3) {
                $error = 'Envio rápido demais. Aguarde alguns segundos e tente novamente.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            // 4) Turnstile validado no servidor.
            if (!Turnstile::verify($_POST['cf-turnstile-response'] ?? '', $ip)) {
                $error = 'Não foi possível validar a verificação de segurança. Tente novamente.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
            $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $passwordConfirm = filter_input(INPUT_POST, 'password_confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

            if (empty($username) || empty($email) || empty($password) || empty($passwordConfirm)) {
                $error = 'Todos os campos são obrigatórios.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            // Mesma regra do validacaoForm.js, agora também no servidor
            // (o JavaScript do navegador não vale nada contra um bot).
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                $error = 'O nome de usuário deve ter de 3 a 20 caracteres (letras, números e underscore).';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            if ($password !== $passwordConfirm) {
                $error = 'As senhas não coincidem.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            $passwordError = PasswordPolicy::validate($password);
            if ($passwordError !== null) {
                $error = $passwordError;
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
                $error = 'Email inválido.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            if (EmailPolicy::isBlocked($email)) {
                $error = 'Este provedor de e-mail não é aceito. Use um e-mail pessoal ou institucional.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            if ($this->userModel->emailExists($email)) {
                $error = 'Este email já está registrado.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            if ($this->userModel->usernameExists($username)) {
                $error = 'Este nome de usuário já está em uso.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            // No máximo 5 contas por hora a partir do mesmo IP
            $rlKey = $this->rateLimiter->key('register', $ip);
            if ($this->rateLimiter->tooManyAttempts($rlKey, 5, 60)) {
                $error = 'Muitas contas criadas a partir deste endereço. Tente novamente mais tarde.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            if ($this->userModel->register($username, $email, $password)) {
                $this->rateLimiter->hit($rlKey);
                $this->rateLimiter->hit($globalKey);
                unset($_SESSION['register_form_at']);

                $newUser = $this->userModel->getUserByEmail($email);
                if ($newUser) {
                    $this->sendVerificationEmail($email, $newUser['id']);
                }

                $success = 'Usuário registrado com sucesso! Enviámos um e-mail de confirmação — você só poderá entrar depois de confirmá-lo.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            } else {
                $error = 'Erro ao registrar usuário. Tente novamente.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }
        }

        // GET: marca o momento em que o formulário foi aberto.
        $_SESSION['register_form_at'] = time();
        include __DIR__ . '/../Views/auth/register.php';
    }

    public function forgotPassword() {
        $this->startSession();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $ip = $this->clientIp();

            // Limite por IP (qualquer e-mail) e por IP + e-mail, checados antes de tudo.
            $ipKey = $this->rateLimiter->key('forgot_password_ip', $ip);
            if ($this->rateLimiter->tooManyAttempts($ipKey, 10, 60)) {
                $error = 'Muitos pedidos de recuperação de senha. Tente novamente mais tarde.';
                include __DIR__ . '/../Views/auth/forgot_password.php';
                return;
            }
            $this->rateLimiter->hit($ipKey);

            if (!Turnstile::verify($_POST['cf-turnstile-response'] ?? '', $ip)) {
                $error = 'Não foi possível validar a verificação de segurança. Tente novamente.';
                include __DIR__ . '/../Views/auth/forgot_password.php';
                return;
            }

            $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');

            if (empty($email)) {
                $error = 'Email é obrigatório.';
                include __DIR__ . '/../Views/auth/forgot_password.php';
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email inválido.';
                include __DIR__ . '/../Views/auth/forgot_password.php';
                return;
            }

            $rlKey = $this->rateLimiter->key('forgot_password', $ip, $email);
            if ($this->rateLimiter->tooManyAttempts($rlKey, 3, 60)) {
                $error = 'Muitos pedidos de recuperação de senha. Tente novamente mais tarde.';
                include __DIR__ . '/../Views/auth/forgot_password.php';
                return;
            }
            $this->rateLimiter->hit($rlKey);

            // Só envia se o e-mail existir, mas a resposta é SEMPRE a mesma,
            // para não deixar bots descobrirem quais e-mails têm conta.
            if ($this->userModel->emailExists($email)) {
                $token = bin2hex(random_bytes(50));
                $expires_at = date('Y-m-d H:i:s', time() + 3600);
                $this->userModel->savePasswordResetToken($email, $token, $expires_at);

                $baseUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?? 'http://localhost/MyGameLibrary/public';
                $reset_link = rtrim($baseUrl, '/') . "/index.php?action=reset_password&token=$token";

                $apiKey = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY');

                try {
                    $resend = Resend::client($apiKey);
                    $resend->emails->send([
                        'from' => 'My Game Library <suporte@mygamelibrary.com.br>',
                        'to' => [$email],
                        'subject' => 'Recuperação de Palavra-passe',
                        'html' => '<p>Olá!</p><p>Clique no link abaixo para redefinir a sua palavra-passe:</p><p><a href="'.$reset_link.'">Redefinir Palavra-passe</a></p>',
                    ]);
                } catch (\Exception $e) {
                    error_log('Erro ao enviar e-mail de recuperação: ' . $e->getMessage());
                }
            }

            $success = 'Se existir uma conta com este e-mail, enviámos um link para redefinir a senha.';
        }
        include __DIR__ . '/../Views/auth/forgot_password.php';
    }

    public function resetPassword() {
        $this->startSession();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

            if (empty($token)) {
                $error = 'Token inválido ou ausente.';
                include __DIR__ . '/../Views/auth/reset_password.php';
                return;
            }

            $user = $this->userModel->getUserByResetToken($token);
            
            if (!$user) {
                $error = 'Token inválido ou expirado. Solicite um novo link de recuperação.';
                include __DIR__ . '/../Views/auth/reset_password.php';
                return;
            }

            include __DIR__ . '/../Views/auth/reset_password.php';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $new_password = filter_input(INPUT_POST, 'new_password', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $password_confirm = filter_input(INPUT_POST, 'password_confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

            if (empty($token)) {
                $error = 'Token inválido ou ausente.';
                include __DIR__ . '/../Views/auth/reset_password.php';
                return;
            }

            if (empty($new_password) || empty($password_confirm)) {
                $error = 'Todos os campos são obrigatórios.';
                include __DIR__ . '/../Views/auth/reset_password.php';
                return;
            }

            if ($new_password !== $password_confirm) {
                $error = 'As senhas não coincidem.';
                include __DIR__ . '/../Views/auth/reset_password.php';
                return;
            }

            $passwordError = PasswordPolicy::validate($new_password);
            if ($passwordError !== null) {
                $error = $passwordError;
                include __DIR__ . '/../Views/auth/reset_password.php';
                return;
            }

            $user = $this->userModel->getUserByResetToken($token);
            
            if (!$user) {
                $error = 'Token inválido ou expirado. Solicite um novo link de recuperação.';
                include __DIR__ . '/../Views/auth/reset_password.php';
                return;
            }

            if ($this->userModel->updatePassword($user['id'], $new_password)) {
                $success = 'Senha redefinida com sucesso! Faça login com sua nova senha.';
                include __DIR__ . '/../Views/auth/reset_password.php';
                return;
            } else {
                $error = 'Erro ao redefinir senha. Tente novamente.';
                include __DIR__ . '/../Views/auth/reset_password.php';
                return;
            }
        }
    }

    public function verifyEmail() {
        $this->startSession();

        $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

        if (empty($token)) {
            $error = 'Token de verificação inválido ou ausente.';
            include __DIR__ . '/../Views/auth/verify_email.php';
            return;
        }

        $user = $this->userModel->getUserByVerificationToken($token);

        if (!$user) {
            $error = 'Este link de verificação é inválido ou já expirou. Peça um novo e-mail de confirmação.';
            include __DIR__ . '/../Views/auth/verify_email.php';
            return;
        }

        $this->userModel->markEmailVerified($user['id']);

        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']) {
            $_SESSION['email_verified'] = true;
        }

        $success = 'E-mail confirmado com sucesso! A sua conta está totalmente ativa.';
        include __DIR__ . '/../Views/auth/verify_email.php';
    }

    public function resendVerification() {
        $this->startSession();

        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        // No máximo 3 reenvios por hora por usuário
        $rlKey = $this->rateLimiter->key('resend_verification', $_SESSION['user_id']);
        if ($this->rateLimiter->tooManyAttempts($rlKey, 3, 60)) {
            $_SESSION['verification_notice'] = 'Muitos pedidos de reenvio. Tente novamente mais tarde.';
            header("Location: index.php?action=home");
            exit();
        }
        $this->rateLimiter->hit($rlKey);

        $user = $this->userModel->getUserById($_SESSION['user_id']);
        if ($user && empty($user['email_verified_at'] ?? null)) {
            $this->sendVerificationEmail($user['email'], $user['id']);
            $_SESSION['verification_notice'] = 'E-mail de confirmação reenviado! Verifique a sua caixa de entrada.';
        }

        header("Location: index.php?action=home");
        exit();
    }

    /**
     * Reenvio de e-mail de verificação a partir da tela de login (sem sessão ativa),
     * já que agora o login fica bloqueado até o e-mail ser confirmado.
     */
    public function resendVerificationPublic() {
        $this->startSession();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // No máximo 3 reenvios por hora por IP + email, e 10 por hora por IP
                // (sem o limite por IP, um bot trocando o e-mail a cada tentativa nunca seria barrado).
                $ip = $this->clientIp();
                $rlKey = $this->rateLimiter->key('resend_verification', $ip, $email);
                $ipKey = $this->rateLimiter->key('resend_verification_ip', $ip);
                if (!$this->rateLimiter->tooManyAttempts($rlKey, 3, 60)
                    && !$this->rateLimiter->tooManyAttempts($ipKey, 10, 60)) {
                    $this->rateLimiter->hit($rlKey);
                    $this->rateLimiter->hit($ipKey);
                    $user = $this->userModel->getUserByEmail($email);
                    if ($user && empty($user['email_verified_at'])) {
                        $this->sendVerificationEmail($user['email'], $user['id']);
                    }
                }
            }
        }

        // Mensagem genérica sempre igual, para não revelar se o e-mail existe ou já está verificado.
        $success = 'Se existir uma conta com este e-mail ainda não confirmada, reenviamos o link de verificação.';
        include __DIR__ . '/../Views/auth/login.php';
    }

    public function logout() {
        $this->startSession();
        session_destroy();
        header("Location: index.php?action=login"); 
        exit();
    }

    
}
?>
