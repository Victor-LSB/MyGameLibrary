<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use Victi\GameLoggd\Controllers\AuthController;
use Victi\GameLoggd\Controllers\GameController;
use Victi\GameLoggd\Controllers\ProfileController;


$action = $_GET['action'] ?? 'home';


$routes = [
    'login'          => [AuthController::class, 'login'],
    'register'       => [AuthController::class, 'register'],
    'logout'         => [AuthController::class, 'logout'],
    'forgot_password' => [AuthController::class, 'forgotPassword'],
    'reset_password' => [AuthController::class, 'resetPassword'],
    'add_game'       => [GameController::class, 'add'],
    'delete_game'    => [GameController::class, 'delete'],
    'change_status'  => [GameController::class, 'changeStatus'],
    'change_rating'  => [GameController::class, 'changeRating'],
    'search'         => [GameController::class, 'search'],
    'ajax_search'    => [GameController::class, 'ajaxSearch'],
    'details'        => [GameController::class, 'details'],
    'save_review'    => [GameController::class, 'saveReview'],
    'home'           => [GameController::class, 'index'],
    
    // Novas rotas de Perfil
    'profile'        => [ProfileController::class, 'view'],
    'edit_profile'   => [ProfileController::class, 'edit'],
    'update_profile' => [ProfileController::class, 'update']
];

if (array_key_exists($action, $routes)) {
    $controllerName = $routes[$action][0];
    $methodName = $routes[$action][1];

    $controller = new $controllerName();
    $controller->$methodName();
} else {
    http_response_code(404);
    echo "404 - Página não encontrada.";
}