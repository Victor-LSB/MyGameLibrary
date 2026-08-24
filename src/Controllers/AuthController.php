<?php
namespace Victi\MyGameLibrary\Controllers;
use Resend;
use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\User;
use Victi\MyGameLibrary\Services\RateLimiter;
use Victi\MyGameLibrary\Services\Csrf;
use Victi\MyGameLibrary\Services\PasswordPolicy;

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
     * IP do visitante (considerando proxy reverso, quando presente).
     */
    private function clientIp() {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
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

            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $passwordConfirm = filter_input(INPUT_POST, 'password_confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

            if (empty($username) || empty($email) || empty($password) || empty($passwordConfirm)) {
                $error = 'Todos os campos são obrigatórios.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            if ($password !== $passwordConfirm) {
                $error = '';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            $passwordError = PasswordPolicy::validate($password);
            if ($passwordError !== null) {
                $error = $passwordError;
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email inválido.';
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

            // No máximo 5 registos por hora a partir do mesmo IP (evita criação em massa de contas)
            $rlKey = $this->rateLimiter->key('register', $this->clientIp());
            if ($this->rateLimiter->tooManyAttempts($rlKey, 5, 60)) {
                $error = 'Muitas contas criadas a partir deste endereço. Tente novamente mais tarde.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }

            if ($this->userModel->register($username, $email, $password)) {
                $this->rateLimiter->hit($rlKey);

                $newUser = $this->userModel->getUserByEmail($email);
                if ($newUser) {
                    $this->sendVerificationEmail($email, $newUser['id']);
                }

                $success = 'Usuário registrado com sucesso! Enviámos um e-mail de confirmação — verifique a sua caixa de entrada. Já pode fazer login.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            } else {
                $error = 'Erro ao registrar usuário. Tente novamente.';
                include __DIR__ . '/../Views/auth/register.php';
                return;
            }
        }

        include __DIR__ . '/../Views/auth/register.php';
    }

    public function forgotPassword() {
        $this->startSession();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '';

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

            if (!$this->userModel->emailExists($email)) {
                $error = 'Email não encontrado em nossa base de dados.';
                include __DIR__ . '/../Views/auth/forgot_password.php';
                return;
            }

            // No máximo 3 pedidos de recuperação por hora por IP + email
            $rlKey = $this->rateLimiter->key('forgot_password', $this->clientIp(), $email);
            if ($this->rateLimiter->tooManyAttempts($rlKey, 3, 60)) {
                $error = 'Muitos pedidos de recuperação de senha. Tente novamente mais tarde.';
                include __DIR__ . '/../Views/auth/forgot_password.php';
                return;
            }
            $this->rateLimiter->hit($rlKey);

            $token = bin2hex(random_bytes(50));
            $expires_at = date('Y-m-d H:i:s', time() + 3600);
            $this->userModel->savePasswordResetToken($email, $token, $expires_at);

            $baseUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?? 'http://localhost/MyGameLibrary/public';
            $reset_link = rtrim($baseUrl, '/') . "/index.php?action=reset_password&token=$token";

            $apiKey = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY');

            $resend = Resend::client($apiKey);


            try {
                $resend->emails->send([
                    // Se ainda não verificou o domínio, use 'onboarding@resend.dev'
                    'from' => 'My Game Library <suporte@mygamelibrary.com.br>', 
                    'to' => [$email], // Variável com o e-mail do utilizador
                    'subject' => 'Recuperação de Palavra-passe',
                    'html' => '<p>Olá!</p><p>Clique no link abaixo para redefinir a sua palavra-passe:</p><p><a href="'.$reset_link.'">Redefinir Palavra-passe</a></p>',
                ]);
    
                    // Sucesso: Redirecionar com mensagem de sucesso
                    // ...
    
                } catch (\Exception $e) {
                    // Erro: Lidar com a exceção (ex: registar no log)
                    echo "Erro ao enviar e-mail: " . $e->getMessage();
                }
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
                // No máximo 3 reenvios por hora por IP + email
                $rlKey = $this->rateLimiter->key('resend_verification', $this->clientIp(), $email);
                if (!$this->rateLimiter->tooManyAttempts($rlKey, 3, 60)) {
                    $this->rateLimiter->hit($rlKey);
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
