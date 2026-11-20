<?php
namespace Victi\MyGameLibrary\Controllers;

use Victi\MyGameLibrary\Database\Database;
use Victi\MyGameLibrary\Models\Game;
use Victi\MyGameLibrary\Services\Csrf;
use Victi\MyGameLibrary\Services\RateLimiter;
use Victi\MyGameLibrary\Services\Session;

/**
 * Top 5 jogos do perfil (só o dono edita; todo mundo vê no perfil).
 */
class TopGamesController {
    private $db;
    private $gameModel;
    private $userId;
    private $rateLimiter;

    public function __construct() {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $this->db = (new Database())->connect();
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro de conexão']);
            exit;
        }

        Session::start();
        $this->userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

        if (!$this->userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado']);
            exit;
        }

        $this->gameModel = new Game($this->db);
        $this->rateLimiter = new RateLimiter($this->db);
    }

    /**
     * GET: busca na biblioteca do próprio usuário (para escolher o jogo do slot).
     * q=<texto>
     */
    public function library() {
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $games = $this->gameModel->getGamesByUserId($this->userId, null, $q !== '' ? $q : null, null, 1, 12);

        echo json_encode([
            'success' => true,
            'games' => array_map(function ($g) {
                return [
                    'id'    => (int) $g['id'],
                    'title' => $g['title'],
                    'cover' => $this->gameModel->normalizeCover($g['cover_image'] ?? null),
                ];
            }, $games),
        ]);
    }

    /**
     * POST (JSON): { "slots": [id|null, id|null, id|null, id|null, id|null] }
     * O índice do array é o rank (0 = #1). Substitui o Top 5 inteiro.
     */
    public function save() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método inválido']);
            return;
        }

        Csrf::verifyOrFail();

        $rlKey = $this->rateLimiter->key('top_games_save', $this->userId);
        if ($this->rateLimiter->tooManyAttempts($rlKey, 60, 1)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Muitas alterações seguidas. Aguarde um instante.']);
            return;
        }
        $this->rateLimiter->hit($rlKey);

        $input = json_decode(file_get_contents('php://input'), true);
        $slots = is_array($input) ? ($input['slots'] ?? null) : null;

        if (!is_array($slots) || count($slots) !== Game::TOP_GAMES_SIZE) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            return;
        }

        $clean = [];
        foreach (array_values($slots) as $value) {
            if ($value === null || $value === '') {
                $clean[] = null;
            } elseif (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $clean[] = (int) $value;
            } else {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
                return;
            }
        }

        $error = $this->gameModel->saveTopGames($this->userId, $clean);
        if ($error !== null) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $error]);
            return;
        }

        echo json_encode(['success' => true]);
    }
}
