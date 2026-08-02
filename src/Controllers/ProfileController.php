<?php
namespace Victi\MyGameLibrary\Controllers;
use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\User;
use Victi\MyGameLibrary\Models\Game;
use Victi\MyGameLibrary\Models\ProfileComment;
use Victi\MyGameLibrary\Services\Csrf;
use Victi\MyGameLibrary\Services\PasswordPolicy;

class ProfileController {
    private $db;
    private $userModel;
    private $gameModel;
    private $commentModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        
        if ($this->db) {
            $this->userModel = new User($this->db);
            $this->gameModel = new Game($this->db);
            $this->commentModel = new ProfileComment($this->db);
        }
    }

    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function view() {
        $this->startSession();
        
        $username = filter_input(INPUT_GET, 'u', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        if (!$username && isset($_SESSION['username'])) {
            header("Location: index.php?action=profile&u=" . $_SESSION['username']);
            exit();
        }

        if (!$username) {
            header("Location: index.php?action=home");
            exit();
        }

        $profileUser = $this->userModel->getUserByUsername($username);

        if (!$profileUser) {
            http_response_code(404);
            echo "Perfil não encontrado.";
            return;
        }

        $currentUserId = $_SESSION['user_id'] ?? null;
        $profileUserId = $profileUser['id'];
        $isOwner = ($currentUserId !== null && $currentUserId === $profileUserId);
        $recentGames = $this->gameModel->getRecentGamesByUserId($profileUser['id'], 10);

        $isFollowing = false;
        $followsMe = false;
        if ($currentUserId !== null && !$isOwner) {
            $followStmt = $this->db->prepare("SELECT id FROM user_follows WHERE follower_id = ? AND following_id = ? LIMIT 1");
            $followStmt->execute([$currentUserId, $profileUserId]);
            $isFollowing = $followStmt->fetch() !== false;

            // Mútuo: o dono do perfil também segue quem está a ver a página?
            $followsMeStmt = $this->db->prepare("SELECT id FROM user_follows WHERE follower_id = ? AND following_id = ? LIMIT 1");
            $followsMeStmt->execute([$profileUserId, $currentUserId]);
            $followsMe = $followsMeStmt->fetch() !== false;
        }

        $followingStmt = $this->db->prepare("
            SELECT u.id, u.username, u.display_name, u.avatar
            FROM user_follows uf
            INNER JOIN users u ON u.id = uf.following_id
            WHERE uf.follower_id = ?
            ORDER BY uf.created_at DESC
        ");
        $followingStmt->execute([$profileUserId]);
        $followingList = $followingStmt->fetchAll(\PDO::FETCH_ASSOC);

        $followersStmt = $this->db->prepare("
            SELECT u.id, u.username, u.display_name, u.avatar
            FROM user_follows uf
            INNER JOIN users u ON u.id = uf.follower_id
            WHERE uf.following_id = ?
            ORDER BY uf.created_at DESC
        ");
        $followersStmt->execute([$profileUserId]);
        $followersList = $followersStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Sugestões de quem seguir só fazem sentido para o dono a ver o próprio perfil
        $followSuggestions = [];
        if ($isOwner) {
            require_once __DIR__ . '/FollowController.php';
            $followController = new FollowController();
            $followSuggestions = $followController->getFollowSuggestions($currentUserId, 5);
        }

        $profileComments = $this->commentModel->getByProfile($profileUserId);

        include __DIR__ . '/../Views/profile/view.php';
    }

    public function edit() {
        $this->startSession();
        
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        $user = $this->userModel->getUserById($_SESSION['user_id']);
        include __DIR__ . '/../Views/profile/edit.php';
    }

    public function changePassword() {
        $this->startSession();

        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            include __DIR__ . '/../Views/profile/change_password.php';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $userId = $_SESSION['user_id'];
            $currentPassword = filter_input(INPUT_POST, 'current_password', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $newPassword = filter_input(INPUT_POST, 'new_password', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
            $passwordConfirm = filter_input(INPUT_POST, 'password_confirm', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($passwordConfirm)) {
                $error = 'Todos os campos são obrigatórios.';
                include __DIR__ . '/../Views/profile/change_password.php';
                return;
            }

            if (!$this->userModel->verifyPassword($userId, $currentPassword)) {
                $error = 'Senha atual incorreta.';
                include __DIR__ . '/../Views/profile/change_password.php';
                return;
            }

            if ($newPassword !== $passwordConfirm) {
                $error = 'As novas senhas não coincidem.';
                include __DIR__ . '/../Views/profile/change_password.php';
                return;
            }

            $passwordError = PasswordPolicy::validate($newPassword);
            if ($passwordError !== null) {
                $error = $passwordError;
                include __DIR__ . '/../Views/profile/change_password.php';
                return;
            }

            if ($newPassword === $currentPassword) {
                $error = 'A nova senha deve ser diferente da senha atual.';
                include __DIR__ . '/../Views/profile/change_password.php';
                return;
            }

            if ($this->userModel->updatePassword($userId, $newPassword)) {
                $success = 'Senha alterada com sucesso!';
                include __DIR__ . '/../Views/profile/change_password.php';
                return;
            }

            $error = 'Erro ao alterar a senha. Tente novamente.';
            include __DIR__ . '/../Views/profile/change_password.php';
        }
    }

    /**
     * Função auxiliar para lidar com o upload e validação de imagens
     */
    private function handleImageUpload($file, $prefix) {
        // Verifica se houve erro no upload
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Tipos MIME permitidos (Apenas Imagens)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // Validação real do arquivo (evita que renomeiem um .exe para .jpg)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedTypes)) {
            return false;
        }

        // Diretório de destino (public/uploads/profile/) - CORRIGIDO
        $uploadDir = __DIR__ . '/../../public/uploads/profile/';
        
        // Cria a pasta se não existir
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Gera um nome único para o arquivo
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = $prefix . '_' . uniqid() . '.' . $extension;
        $destination = $uploadDir . $fileName;

        // Move o arquivo da pasta temporária para a final
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // GUARDA O CAMINHO COM UMA BARRA INICIAL PARA SER ABSOLUTO A PARTIR DA RAIZ DO SERVIDOR WEB
            return '/uploads/profile/' . $fileName; 
        }

        return false;
    }

    public function update() {
        $this->startSession();
        
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $displayName = trim((string) ($_POST['display_name'] ?? ''));
            $bio = trim((string) ($_POST['bio'] ?? ''));
            
            // Pega os dados atuais do usuário
            $currentUser = $this->userModel->getUserById($_SESSION['user_id']);
            $avatar = $currentUser['avatar']; // Mantém o antigo por padrão
            $banner = $currentUser['banner']; // Mantém o antigo por padrão

            // Processa o upload do Avatar (se foi enviado um novo)
            if (!empty($_FILES['avatar']['name'])) {
                $uploadResult = $this->handleImageUpload($_FILES['avatar'], 'avatar');
                if ($uploadResult) {
                    $avatar = $uploadResult;
                } else {
                    $_SESSION['profile_error'] = "A foto de perfil deve ser uma imagem válida (JPG, PNG, GIF, WEBP).";
                    header("Location: index.php?action=edit_profile");
                    exit();
                }
            }

            // Processa o upload do Banner (se foi enviado um novo)
            if (!empty($_FILES['banner']['name'])) {
                $uploadResult = $this->handleImageUpload($_FILES['banner'], 'banner');
                if ($uploadResult) {
                    $banner = $uploadResult;
                } else {
                    $_SESSION['profile_error'] = "O banner deve ser uma imagem válida (JPG, PNG, GIF, WEBP).";
                    header("Location: index.php?action=edit_profile");
                    exit();
                }
            }

            // Atualiza no banco de dados
            $this->userModel->updateProfile($_SESSION['user_id'], $displayName, $bio, $avatar, $banner);
            
            // Garante que o nome seja atualizado na sessão instantaneamente
            $_SESSION['display_name'] = $displayName;
            
            $_SESSION['profile_success'] = "Perfil atualizado com sucesso!";
            header("Location: index.php?action=profile&u=" . $_SESSION['username']);
            exit();
        }
    }
}
?>