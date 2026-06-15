<?php
namespace Victi\GameLoggd\Controllers;

use Victi\GameLoggd\Database\Database;
use Victi\GameLoggd\Models\Game;
use Victi\GameLoggd\Models\User;
use Victi\GameLoggd\Services\GameAPI;

class GameController {
    private $db;
    private $gameModel;
    private $api;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        
        if ($this->db) {
            $this->gameModel = new Game($this->db);
            $this->api = new GameAPI();
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
            header("Location: index.php?action=login");
            exit();
        }

        $user_id = $_SESSION['user_id'];
        $search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $filter_status = filter_input(INPUT_GET, 'filter_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $userGames = $this->gameModel->getGamesByUserId($user_id, $filter_status, $search_query);
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

        include __DIR__ . '/../Views/games/details.php';
    }

    public function add() {
        $this->startSession();
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?action=login");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $external_id = filter_input(INPUT_POST, 'external_id', FILTER_SANITIZE_NUMBER_INT);
            $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $platform = filter_input(INPUT_POST, 'platform', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'Desconhecida';
            $genre = filter_input(INPUT_POST, 'genre', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'Desconhecido';
            $release_date = filter_input(INPUT_POST, 'release_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $cover_image = filter_input(INPUT_POST, 'cover', FILTER_SANITIZE_URL);

            if ($external_id && $title) {
                $existingGame = $this->gameModel->findGameByExternalId($external_id);
                
                if ($existingGame) {
                    $game_id = $existingGame['id'];
                } else {
                    $gameDetails = $this->api->getGameDetails($external_id);
                    $raw_description = $gameDetails['description'] ?? '';
                    $description = $this->api->translateHTML($raw_description, 'EN', 'PT');
                    
                    $game_id = $this->gameModel->addGame($external_id, $title, $platform, $genre, $release_date, $cover_image);
                    
                    if ($game_id && $description) {
                        $this->gameModel->updateGameDescription($game_id, $description);
                    }
                }

                if ($game_id) {
                    if ($this->gameModel->checkUserGame($_SESSION['user_id'], $game_id)) {
                        $_SESSION['search_error'] = "Este jogo já está na sua biblioteca!";
                    } else {
                        if ($this->gameModel->addGameToUser($_SESSION['user_id'], $game_id)) {
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
            $game_id = filter_input(INPUT_POST, 'game_id', FILTER_SANITIZE_NUMBER_INT);
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            
            if ($status === '') $status = null;

            if ($game_id) {
                // BUSCA SEGURA: Pega os dados atuais do jogo no banco para não sobrescrever nada com vazio
                $gameInfo = $this->gameModel->getUserGameInfo($_SESSION['user_id'], $game_id);
                $existing_rating = $gameInfo ? $gameInfo['rating'] : null;

                // Se não vier nota no POST (ou for vazia), mantém a nota que já estava no banco
                $rating_post = $_POST['rating'] ?? null;
                $rating = ($rating_post !== null && $rating_post !== '') ? $rating_post : $existing_rating;

                // Atualiza o banco com o novo status e preserva a nota
                $this->gameModel->updateGameStatus($_SESSION['user_id'], $game_id, $status, $rating);
                
                // Responde perfeitamente para o Javascript (fetch) em JSON
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
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
            $game_id = filter_input(INPUT_POST, 'game_id', FILTER_SANITIZE_NUMBER_INT);
            
            // Pega a nota diretamente para lidar com valores vazios de forma segura
            $rating = $_POST['rating'] ?? null;
            if ($rating === '') $rating = null;

            if ($game_id) {
                // BUSCA SEGURA: Pega o status atual do jogo no banco
                $gameInfo = $this->gameModel->getUserGameInfo($_SESSION['user_id'], $game_id);
                $existing_status = $gameInfo ? $gameInfo['status'] : null;

                // Se não vier status no POST (ou for vazio), mantém o status que já estava no banco
                $status_post = $_POST['status'] ?? null;
                $status = ($status_post !== null && $status_post !== '') ? $status_post : $existing_status;

                // Envia para o banco o novo rating e preserva o status atual
                $this->gameModel->updateGameStatus($_SESSION['user_id'], $game_id, $status, $rating);
                
                // Responde perfeitamente para o Javascript (fetch) em JSON
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
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
            $game_id = filter_input(INPUT_POST, 'game_id', FILTER_SANITIZE_NUMBER_INT);
            if ($game_id) {
                $this->gameModel->deleteGameFromUser($_SESSION['user_id'], $game_id);
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

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $game_id = filter_input(INPUT_POST, 'game_id', FILTER_SANITIZE_NUMBER_INT);
            $review = $_POST['review'] ?? '';

            if ($game_id) {
                $this->gameModel->updateReview($review, $_SESSION['user_id'], $game_id);
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