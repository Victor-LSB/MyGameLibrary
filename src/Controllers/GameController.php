<?php
namespace Victi\MyGameLibrary\Controllers;

use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\Game;
use Victi\MyGameLibrary\Models\User;
use Victi\MyGameLibrary\Models\Activity;
use Victi\MyGameLibrary\Services\GameAPI;
use Victi\MyGameLibrary\Services\Csrf;

class GameController {
    private $db;
    private $gameModel;
    private $api;
    private $activityModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        
        if ($this->db) {
            $this->gameModel = new Game($this->db);
            $this->api = new GameAPI();
            $this->activityModel = new Activity($this->db);
        } else {
            die("Erro na conexão com o banco de dados.");
        }
    }

    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function index() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            include __DIR__ . '/../Views/auth/login.php';
            exit();
        }

        $user_id = $_SESSION['user_id'];
        $search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $filter_status = filter_input(INPUT_GET, 'filter_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $filter_tag = filter_input(INPUT_GET, 'tag', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $per_page = 25;
        $current_page = (int) (filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT) ?: 1);
        if ($current_page < 1) {
            $current_page = 1;
        }

        $total_games = $this->gameModel->countGamesByUserId($user_id, $filter_status, $search_query, $filter_tag);
        $total_pages = max(1, (int) ceil($total_games / $per_page));

        // Se a pessoa cair numa página que não existe mais (ex.: aplicou um
        // filtro que reduziu o total), volta pra última página válida.
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
        }

        $userGames = $this->gameModel->getGamesByUserId($user_id, $filter_status, $search_query, $filter_tag, $current_page, $per_page);
        $userTags = $this->gameModel->getUniqueTagsForUser($user_id);
        include __DIR__ . '/../Views/games/index.php';
    }

    public function search() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        $query = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $searchResults = [];

        if ($query) {
            $searchResults = $this->api->searchGames($query);
        }

        include __DIR__ . '/../Views/games/search.php';
    }

    public function ajaxSearch() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autorizado']);
            exit();
        }

        $query = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        if (empty($query)) {
            echo json_encode([]);
            exit();
        }

        $results = $this->api->searchGames($query);
        
        header('Content-Type: application/json');
        echo json_encode($results);
        exit();
    }

    public function details() {
        $this->startSession();
        
        $game_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
        $username_profile = $_GET['u'] ?? null; 

        if (!$game_id) {
            header("Location: index.php?action=home");
            exit();
        }

        // AUTO-REDIRECIONAMENTO: Se não houver ?u= na URL, força a URL a ter o nome do usuário logado
        if (!$username_profile && isset($_SESSION['username'])) {
            header("Location: index.php?action=details&id=" . $game_id . "&u=" . urlencode($_SESSION['username']));
            exit();
        }

        // Por padrão, o alvo é o usuário logado
        $target_user_id = $_SESSION['user_id'] ?? null;
        $isOwner = true; 

        if ($username_profile) {
            $userModel = new User($this->db);
            $targetUser = $userModel->getUserByUsername($username_profile);
            
            if ($targetUser) {
                $target_user_id = $targetUser['id'];
                
                // Se não estiver logado OU o id logado for diferente do dono do perfil, é visitante
                if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $target_user_id) {
                    $isOwner = false;
                }
            }
        }

        // Se não encontrou alvo (ex: não logado e não passou usuário na URL), manda pro login
        if (!$target_user_id) {
            header("Location: index.php?action=login");
            exit();
        }

        $game = $this->gameModel->getUserGameInfo($target_user_id, $game_id);
        $gameTags = $this->gameModel->getTagsForGame($target_user_id, $game_id);

        include __DIR__ . '/../Views/games/details.php';
    }

    public function add() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $external_id = filter_input(INPUT_POST, 'external_id', FILTER_SANITIZE_NUMBER_INT);
            // NOTA: usar FILTER_SANITIZE_FULL_SPECIAL_CHARS aqui converte aspas/apóstrofos em
            // entidades HTML (&#039;) ANTES de salvar no banco. Como as views já escapam com
            // htmlspecialchars() na hora de exibir, isso causava double-encoding
            // (ex.: "Senua's" -> banco guarda "Senua&#039;s" -> tela mostra "Senua&amp;#039;s").
            // O valor deve ser guardado "cru" (só com trim) e escapado apenas na exibição.
            $title = trim((string) filter_input(INPUT_POST, 'title', FILTER_UNSAFE_RAW));
            $title = $title !== '' ? $title : null;
            $platform = trim((string) filter_input(INPUT_POST, 'platform', FILTER_UNSAFE_RAW));
            $platform = $platform !== '' ? $platform : 'Desconhecida';
            $genre = trim((string) filter_input(INPUT_POST, 'genre', FILTER_UNSAFE_RAW));
            $genre = $genre !== '' ? $genre : 'Desconhecido';
            $release_date = trim((string) filter_input(INPUT_POST, 'release_date', FILTER_UNSAFE_RAW));
            $release_date = $release_date !== '' ? $release_date : null;
            $cover_image = filter_input(INPUT_POST, 'cover', FILTER_SANITIZE_URL);

            if ($external_id && $title) {
                $existingGame = $this->gameModel->findGameByExternalId($external_id);
                
                if ($existingGame) {
                    $game_id = $existingGame['id'];
                } else {
                    $gameDetails = $this->api->getGameDetails($external_id);
                    $raw_description = $gameDetails['description'] ?? '';
                    $description = $this->api->translateHTML($raw_description, 'EN', 'PT');
                    $normalized_genres = $this->api->formatGenresForStorage($gameDetails['genres'] ?? $genre);
                    $normalized_genres = $normalized_genres !== '' ? $normalized_genres : trim(mb_strtolower((string) $genre));
                    
                    $game_id = $this->gameModel->addGame($external_id, $title, $platform, $normalized_genres, $release_date, $cover_image);
                    
                    if ($game_id && $description) {
                        $this->gameModel->updateGameDescription($game_id, $description);
                    }
                }

                if ($game_id) {
                    if ($this->gameModel->checkUserGame($_SESSION['user_id'], $game_id)) {
                        $_SESSION['search_error'] = "Este jogo já está na sua biblioteca!";
                    } else {
                        if ($this->gameModel->addGameToUser($_SESSION['user_id'], $game_id)) {
                            $this->activityModel->log($_SESSION['user_id'], 'game_added', $game_id);
                            header("Location: index.php?action=home");
                            exit();
                        } else {
                            $_SESSION['search_error'] = "Erro ao adicionar jogo à sua lista.";
                        }
                    }
                } else {
                    $_SESSION['search_error'] = "Erro ao registrar o jogo no sistema.";
                }
            }
        }
        header("Location: index.php?action=search");
        exit();
    }

    public function changeStatus() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $game_id = filter_input(INPUT_POST, 'game_id', FILTER_SANITIZE_NUMBER_INT);
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            
            if ($status === '') $status = null;

            $completion_date = null;
            $time_spent_hours = null;

            $completion_date_raw = $_POST['completion_date'] ?? null;
            if (!empty($completion_date_raw)) {
                $dateTime = \DateTime::createFromFormat('Y-m-d\TH:i', $completion_date_raw) ?: \DateTime::createFromFormat('Y-m-d\TH:i:s', $completion_date_raw);
                // Não permite marcar como concluído em uma data futura.
                if ($dateTime && $dateTime <= new \DateTime()) {
                    $completion_date = $dateTime->format('Y-m-d');
                }
            }

            $time_spent_raw = $_POST['time_spent_hours'] ?? null;
            if ($time_spent_raw !== null && $time_spent_raw !== '' && is_numeric($time_spent_raw)) {
                $time_spent_hours = number_format((float) $time_spent_raw, 2, '.', '');
            }

            if ($game_id) {
                // BUSCA SEGURA: Pega os dados atuais do jogo no banco para não sobrescrever nada com vazio
                $gameInfo = $this->gameModel->getUserGameInfo($_SESSION['user_id'], $game_id);

                if (!$gameInfo) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Jogo não encontrado na sua biblioteca.']);
                    exit();
                }

                $existing_rating = $gameInfo['rating'];
                $existing_completion_date = $gameInfo['completion_date'] ?? null;
                $existing_time_spent = $gameInfo['time_spent_hours'] ?? null;

                // Se não vier nota no POST (ou for vazia), mantém a nota que já estava no banco
                $rating_post = $_POST['rating'] ?? null;
                $rating = ($rating_post !== null && $rating_post !== '') ? $rating_post : $existing_rating;

                if ($status === 'Zerado') {
                    $completion_date = $completion_date ?? $existing_completion_date;
                    $time_spent_hours = $time_spent_hours ?? $existing_time_spent;
                } else {
                    $completion_date = $existing_completion_date;
                    $time_spent_hours = $existing_time_spent;
                }

                try {
                    // Atualiza o banco com o novo status e preserva a nota
                    $affected = $this->gameModel->updateGameStatus($_SESSION['user_id'], $game_id, $status, $rating, $completion_date, $time_spent_hours);

                    if ($affected === 0) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Nenhum registro foi atualizado. Verifique se o jogo pertence à sua biblioteca.']);
                        exit();
                    }

                    // Registra no feed de atividade só quando o status realmente mudou
                    if (!empty($status) && $status !== ($gameInfo['status'] ?? null)) {
                        $this->activityModel->log($_SESSION['user_id'], 'status_changed', $game_id, $status);
                    }

                    // Responde perfeitamente para o Javascript (fetch) em JSON
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit();
                } catch (\PDOException $e) {
                    error_log('changeStatus falhou: ' . $e->getMessage());
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Erro ao salvar o status no banco de dados.']);
                    exit();
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ID do jogo inválido.']);
            exit();
        }
    }
    
    public function togglePlatinum() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $user_id = $_SESSION['user_id'];
            $game_id = filter_input(INPUT_POST, 'game_id', FILTER_SANITIZE_NUMBER_INT);

            if (!$game_id) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID do jogo inválido.']);
                exit();
            }

            $gameInfo = $this->gameModel->getUserGameInfo($user_id, $game_id);

            if (!$gameInfo) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Jogo não encontrado na sua biblioteca.']);
                exit();
            }

            if (($gameInfo['status'] ?? null) !== 'Zerado') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Só é possível platinar um jogo que já está Zerado.']);
                exit();
            }

            $newValue = empty($gameInfo['platinum_at']);

            try {
                $affected = $this->gameModel->setPlatinum($user_id, $game_id, $newValue);

                if ($affected === 0) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Nenhum registro foi atualizado.']);
                    exit();
                }

                if ($newValue) {
                    $this->activityModel->log($user_id, 'status_changed', $game_id, 'Platina');
                }

                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'platinum' => $newValue]);
                exit();
            } catch (\PDOException $e) {
                error_log('togglePlatinum falhou: ' . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco de dados.']);
                exit();
            }
        }
    }

    public function changeRating() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $game_id = filter_input(INPUT_POST, 'game_id', FILTER_SANITIZE_NUMBER_INT);
            
            // Pega a nota diretamente para lidar com valores vazios de forma segura
            $rating = $_POST['rating'] ?? null;
            if ($rating === '') $rating = null;

            if ($game_id) {
                // BUSCA SEGURA: Pega o status atual do jogo no banco
                $gameInfo = $this->gameModel->getUserGameInfo($_SESSION['user_id'], $game_id);

                if (!$gameInfo) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Jogo não encontrado na sua biblioteca.']);
                    exit();
                }

                $existing_status = $gameInfo['status'];
                $existing_completion_date = $gameInfo['completion_date'] ?? null;
                $existing_time_spent = $gameInfo['time_spent_hours'] ?? null;

                // Se não vier status no POST (ou for vazio), mantém o status que já estava no banco
                $status_post = $_POST['status'] ?? null;
                $status = ($status_post !== null && $status_post !== '') ? $status_post : $existing_status;

                try {
                    // Envia para o banco o novo rating e preserva o status atual + completion_date/time_spent_hours
                    $affected = $this->gameModel->updateGameStatus($_SESSION['user_id'], $game_id, $status, $rating, $existing_completion_date, $existing_time_spent);

                    if ($affected === 0) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Nenhum registro foi atualizado. Verifique se o jogo pertence à sua biblioteca.']);
                        exit();
                    }

                    // Responde perfeitamente para o Javascript (fetch) em JSON
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit();
                } catch (\PDOException $e) {
                    error_log('changeRating falhou: ' . $e->getMessage());
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Erro ao salvar a avaliação no banco de dados.']);
                    exit();
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                exit();
            }
        }
    }

    public function delete() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $game_id = filter_input(INPUT_POST, 'game_id', FILTER_SANITIZE_NUMBER_INT);
            if ($game_id) {
                $this->gameModel->deleteGameFromUser($_SESSION['user_id'], $game_id);
            }
        }

        // Volta pra mesma página/filtro em que a pessoa estava, em vez de
        // sempre jogar de volta pro início da biblioteca.
        $redirectParams = ['action' => 'home'];

        $page = filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT);
        if ($page && (int) $page > 1) {
            $redirectParams['page'] = (int) $page;
        }

        $search = filter_input(INPUT_POST, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($search)) {
            $redirectParams['search'] = $search;
        }

        $filterStatus = filter_input(INPUT_POST, 'filter_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($filterStatus)) {
            $redirectParams['filter_status'] = $filterStatus;
        }

        $tag = filter_input(INPUT_POST, 'tag', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (!empty($tag)) {
            $redirectParams['tag'] = $tag;
        }

        header("Location: index.php?" . http_build_query($redirectParams));
        exit();
    }

    public function removeCustomTag() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $game_id = filter_input(INPUT_POST, 'game_id', FILTER_SANITIZE_NUMBER_INT);
            $tag_id = filter_input(INPUT_POST, 'tag_id', FILTER_SANITIZE_NUMBER_INT);

            if ($game_id && $tag_id) {
                $this->gameModel->removeCustomTagFromGame($_SESSION['user_id'], $game_id, $tag_id);
                $_SESSION['review_success'] = 'Tag removida com sucesso!';
            }
        }

        header("Location: index.php?action=details&id=" . urlencode((string) ($_POST['game_id'] ?? '')) . "&u=" . urlencode($_SESSION['username'] ?? ''));
        exit();
    }

    public function deleteSavedTag() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $tag_id = filter_input(INPUT_POST, 'tag_id', FILTER_SANITIZE_NUMBER_INT);

            if ($tag_id) {
                $this->gameModel->deleteSavedTagForUser($_SESSION['user_id'], $tag_id);
                $_SESSION['review_success'] = 'Tag excluída com sucesso!';
            }
        }

        header("Location: index.php?action=home");
        exit();
    }

     public function saveReview() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        $tags_string = $_POST['tags'] ?? '';
        $tags_array = array_values(array_filter(array_map('trim', explode(',', $tags_string))));
        $completion_date_raw = $_POST['completion_date'] ?? '';
        $completion_date = null;
        if ($completion_date_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $completion_date_raw)) {
            // Não permite marcar como concluído em uma data futura.
            if ($completion_date_raw <= date('Y-m-d')) {
                $completion_date = $completion_date_raw;
            }
        }

        $time_spent_raw = $_POST['time_spent_hours'] ?? '';
        $time_spent_hours = null;
        if ($time_spent_raw !== '' && is_numeric($time_spent_raw)) {
            $time_spent_hours = number_format((float) $time_spent_raw, 2, '.', '');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyOrFail();

            $game_id = filter_input(INPUT_POST, 'game_id', FILTER_SANITIZE_NUMBER_INT);
            $review = $_POST['review'] ?? '';

            if ($game_id) {
                $this->gameModel->updateReviewWithCompletionData($review, $completion_date, $time_spent_hours, $_SESSION['user_id'], $game_id);
                $this->gameModel->saveTagsForGame($_SESSION['user_id'], $game_id, $tags_array);

                // Notifica quem segue este usuário, se a análise tiver conteúdo de fato
                if (trim((string) $review) !== '') {
                    $game = $this->gameModel->getGameById($game_id);
                    $notificationController = new NotificationController($this->db);
                    $notificationController->notifyFollowers(
                        $_SESSION['user_id'],
                        $game_id,
                        ($_SESSION['username'] ?? 'Alguém') . ' publicou uma análise de ' . ($game['title'] ?? 'um jogo')
                    );
                    $this->activityModel->log($_SESSION['user_id'], 'review_posted', $game_id);
                }

                $_SESSION['review_success'] = "Análise guardada com sucesso!";
                
                // Retorna mantendo a URL formatada perfeitamente
                header("Location: index.php?action=details&id=" . $game_id . "&u=" . urlencode($_SESSION['username']));
                exit();
            }
        }
        header("Location: index.php?action=home");
        exit();
    }
}