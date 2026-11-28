<?php
namespace Victi\MyGameLibrary\Controllers;
use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\User;
use Victi\MyGameLibrary\Models\Game;
use Victi\MyGameLibrary\Models\ProfileComment;
use Victi\MyGameLibrary\Services\Csrf;
use Victi\MyGameLibrary\Services\PasswordPolicy;
use Victi\MyGameLibrary\Services\Session;

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
        Session::start();
    }

    public function view() {
        $this->startSession();
        
        // mgl_get lê $_GET (filter_input não enxerga o "u" de /perfil/victor)
        $username = mgl_get('u', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
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
        $recentGames = $this->gameModel->getRecentGamesByUserId($profileUser['id'], 12);
        $topGames = $this->gameModel->getTopGames($profileUser['id']);
        $playingGames = $this->gameModel->getPlayingGames($profileUser['id'], 4);

        // Biblioteca completa (?view=all): paginada, com busca e ordenação por data de adição, nome ou nota
        $libraryTotalAll = $this->gameModel->countGamesByUserId($profileUser['id']);
        $libraryMode = (($_GET['view'] ?? '') === 'all');
        $librarySort = $_GET['sort'] ?? 'recent';
        if (!array_key_exists($librarySort, Game::LIBRARY_SORTS)) {
            $librarySort = 'recent';
        }
        $librarySearch = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $libraryPage = max(1, (int) ($_GET['page'] ?? 1));
        $libraryPerPage = 24;
        $libraryGames = [];
        $libraryTotal = 0;
        $libraryPages = 1;
        if ($libraryMode) {
            $libraryTotal = $this->gameModel->countGamesByUserId($profileUser['id'], null, $librarySearch !== '' ? $librarySearch : null);
            $libraryPages = max(1, (int) ceil($libraryTotal / $libraryPerPage));
            $libraryPage = min($libraryPage, $libraryPages);
            $libraryGames = $this->gameModel->getGamesByUserId(
                $profileUser['id'], null, $librarySearch !== '' ? $librarySearch : null, null,
                $libraryPage, $libraryPerPage, $librarySort
            );
        }

        // Pedido do JS (?partial=library): devolve só a seção da biblioteca, sem recarregar o perfil inteiro
        if (($_GET['partial'] ?? '') === 'library') {
            header('Content-Type: text/html; charset=utf-8');
            include __DIR__ . '/../Views/profile/_library.php';
            return;
        }

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

        // Em vez de carregar todo mundo de uma vez, o perfil recebe só contagens e uma prévia.
        // A lista completa (paginada, com busca) vem sob demanda por friendsList().
        $countsStmt = $this->db->prepare("
            SELECT
                (SELECT COUNT(*) FROM user_follows WHERE follower_id = ?) AS following_count,
                (SELECT COUNT(*) FROM user_follows WHERE following_id = ?) AS followers_count,
                (SELECT COUNT(*)
                   FROM user_follows a
                   INNER JOIN user_follows b ON b.follower_id = a.following_id AND b.following_id = a.follower_id
                  WHERE a.follower_id = ?) AS mutual_count
        ");
        $countsStmt->execute([$profileUserId, $profileUserId, $profileUserId]);
        $counts = $countsStmt->fetch(\PDO::FETCH_ASSOC);
        $followingCount = (int) $counts['following_count'];
        $followersCount = (int) $counts['followers_count'];
        $mutualCount    = (int) $counts['mutual_count'];
        // Pessoas distintas ligadas ao perfil (mútuos contam uma vez só)
        $friendsTotal   = $followingCount + $followersCount - $mutualCount;

        // Prévia em avatares: mútuos primeiro; se faltar, completa com quem segue ou é seguido
        $previewSize = 7;
        $friendsPreview = [];

        $mutualPreviewStmt = $this->db->prepare("
            SELECT u.id, u.username, u.display_name, u.avatar
            FROM user_follows a
            INNER JOIN user_follows b ON b.follower_id = a.following_id AND b.following_id = a.follower_id
            INNER JOIN users u ON u.id = a.following_id
            WHERE a.follower_id = ?
            ORDER BY a.created_at DESC
            LIMIT $previewSize
        ");
        $mutualPreviewStmt->execute([$profileUserId]);
        $friendsPreview = $mutualPreviewStmt->fetchAll(\PDO::FETCH_ASSOC);

        $missing = $previewSize - count($friendsPreview);
        if ($missing > 0 && $friendsTotal > count($friendsPreview)) {
            $excludeIds = array_map('intval', array_column($friendsPreview, 'id'));
            $excludeIds[] = (int) $profileUserId;
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));

            $fillStmt = $this->db->prepare("
                SELECT u.id, u.username, u.display_name, u.avatar
                FROM users u
                WHERE u.id NOT IN ($placeholders)
                  AND (
                      u.id IN (SELECT following_id FROM user_follows WHERE follower_id = ?)
                      OR u.id IN (SELECT follower_id FROM user_follows WHERE following_id = ?)
                  )
                ORDER BY u.id DESC
                LIMIT $missing
            ");
            $fillStmt->execute(array_merge($excludeIds, [$profileUserId, $profileUserId]));
            $friendsPreview = array_merge($friendsPreview, $fillStmt->fetchAll(\PDO::FETCH_ASSOC));
        }

        // Para quem visita: quantas pessoas o visitante e o dono do perfil seguem em comum
        $commonCount = 0;
        if ($currentUserId !== null && !$isOwner) {
            $commonStmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM user_follows a
                INNER JOIN user_follows b ON b.following_id = a.following_id
                WHERE a.follower_id = ? AND b.follower_id = ?
            ");
            $commonStmt->execute([$profileUserId, $currentUserId]);
            $commonCount = (int) $commonStmt->fetchColumn();
        }

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

    /**
     * Lista paginada de seguidores / seguindo / mútuos / em comum de um perfil (JSON).
     * GET: u=<username>, tab=followers|following|mutual|common, q=<busca>, offset=<n>
     * Público como o perfil: visitante sem login só não recebe botão de seguir.
     */
    public function friendsList() {
        $this->startSession();
        header('Content-Type: application/json; charset=utf-8');

        $username = trim((string) ($_GET['u'] ?? ''));
        $tab = (string) ($_GET['tab'] ?? 'followers');
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 50);
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $limit = 20;

        $profileUser = $username !== '' ? $this->userModel->getUserByUsername($username) : null;
        if (!$profileUser || !in_array($tab, ['followers', 'following', 'mutual', 'common'], true)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Perfil não encontrado']);
            return;
        }

        $profileId = (int) $profileUser['id'];
        $viewerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

        if ($tab === 'common' && $viewerId === 0) {
            echo json_encode(['success' => true, 'users' => [], 'has_more' => false, 'next_offset' => 0]);
            return;
        }

        // Os dois primeiros "?" são do SELECT (relação do visitante com cada linha)
        $params = [$viewerId, $viewerId];

        switch ($tab) {
            case 'following':
                $from = "FROM user_follows uf INNER JOIN users u ON u.id = uf.following_id WHERE uf.follower_id = ?";
                $params[] = $profileId;
                break;
            case 'mutual':
                $from = "FROM user_follows uf INNER JOIN users u ON u.id = uf.following_id
                         WHERE uf.follower_id = ?
                           AND EXISTS (SELECT 1 FROM user_follows b WHERE b.follower_id = u.id AND b.following_id = ?)";
                array_push($params, $profileId, $profileId);
                break;
            case 'common':
                $from = "FROM user_follows uf INNER JOIN users u ON u.id = uf.following_id
                         WHERE uf.follower_id = ?
                           AND EXISTS (SELECT 1 FROM user_follows c WHERE c.follower_id = ? AND c.following_id = u.id)";
                array_push($params, $profileId, $viewerId);
                break;
            case 'followers':
            default:
                $from = "FROM user_follows uf INNER JOIN users u ON u.id = uf.follower_id WHERE uf.following_id = ?";
                $params[] = $profileId;
                break;
        }

        $search = '';
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $search = " AND (u.username LIKE ? OR u.display_name LIKE ?)";
            array_push($params, $like, $like);
        }

        // Pede uma linha a mais só para saber se existe próxima página
        $fetch = $limit + 1;
        $sql = "
            SELECT u.id, u.username, u.display_name, u.avatar,
                   EXISTS (SELECT 1 FROM user_follows v WHERE v.follower_id = ? AND v.following_id = u.id) AS i_follow,
                   EXISTS (SELECT 1 FROM user_follows w WHERE w.follower_id = u.id AND w.following_id = ?) AS follows_me
            $from
            $search
            ORDER BY uf.created_at DESC, uf.id DESC
            LIMIT $fetch OFFSET $offset
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);

        $users = array_map(function ($row) use ($viewerId) {
            $isMe = $viewerId !== 0 && (int) $row['id'] === $viewerId;
            return [
                'id'           => (int) $row['id'],
                'username'     => $row['username'],
                'display_name' => $row['display_name'] ?: $row['username'],
                'avatar_url'   => mgl_avatar_url($row['avatar']),
                'i_follow'     => (bool) $row['i_follow'],
                'follows_me'   => (bool) $row['follows_me'],
                'is_me'        => $isMe,
                'can_follow'   => $viewerId !== 0 && !$isMe,
            ];
        }, $rows);

        echo json_encode([
            'success'     => true,
            'users'       => $users,
            'has_more'    => $hasMore,
            'next_offset' => $offset + count($users),
        ]);
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
                    header("Location: index.php?action=profile&u=" . urlencode($_SESSION['username']));
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
                    header("Location: index.php?action=profile&u=" . urlencode($_SESSION['username']));
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