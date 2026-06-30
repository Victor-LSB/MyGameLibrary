<?php
namespace Victi\MyGameLibrary\Controllers;
use Resend;
use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\User;

class AuthController {
    private $db;
    private $userModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->userModel = new User($this->db);
    }

    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login() {
        $this->startSession();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Email e senha são obrigatórios.';
                include __DIR__ . '/../Views/auth/login.php';
                return;
            }

            $user = $this->userModel->login($email, $password);

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: index.php?action=home");
                exit();
            } else {
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

            if (strlen($password) < 6) {
                $error = 'A senha deve ter no mínimo 6 caracteres.';
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

            if ($this->userModel->register($username, $email, $password)) {
                $success = 'Usuário registrado com sucesso! Faça login para continuar.';
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

            $token = bin2hex(random_bytes(50));
            $expires_at = date('Y-m-d H:i:s', time() + 3600);
            $this->userModel->savePasswordResetToken($email, $token, $expires_at);

            $reset_link = "http://MyGameLibrary/public/index.php?action=reset_password&token=$token";

            $apiKey = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY');
            $resend = Resend::client($apiKey);


            try {
                $resend->emails->send([
                    'from' => 'My Game Library <suporte@mygamelibrary.com.br>', 
                    'to' => [$email], 
                    'subject' => 'Recuperação de Palavra-passe',
                    'html' => '<p>Olá!</p><p>Clique no link abaixo para redefinir a sua palavra-passe:</p><p><a href="'.$reset_link.'">Redefinir Palavra-passe</a></p>',
                ]);
                } catch (\Exception $e) {
                    echo "Erro ao enviar e-mail: " . $e->getMessage();
                }
        }
        include __DIR__ . '/../Views/auth/forgot_password.php';
    }

    public function resetPassword() {
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

            if (strlen($new_password) < 6) {
                $error = 'A senha deve ter no mínimo 6 caracteres.';
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

    public function logout() {
        $this->startSession();
        session_destroy();
        header("Location: index.php?action=login"); 
        exit();
    }

    
}
?>
