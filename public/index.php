<?php


// Garante que "hoje" seja calculado no fuso horário do Brasil, evitando que o
// servidor (geralmente em UTC) libere datas de conclusão "futuras" que na
// verdade ainda não chegaram no horário local do usuário.
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Controllers/DashboardController.php';
require_once __DIR__ . '/../src/Controllers/NotificationController.php';
require_once __DIR__ . '/../src/Controllers/FollowController.php';
require_once __DIR__ . '/../src/Controllers/FeedController.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Erros detalhados na tela só em desenvolvimento (APP_ENV=local). Em produção
// eles vazam caminhos de arquivos e detalhes internos; vão para o log.
error_reporting(E_ALL);
if (($_ENV['APP_ENV'] ?? 'production') === 'local') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

use Victi\MyGameLibrary\Controllers\AuthController;
use Victi\MyGameLibrary\Controllers\DashboardController;
use Victi\MyGameLibrary\Controllers\GameController;
use Victi\MyGameLibrary\Controllers\ProfileController;
use Victi\MyGameLibrary\Controllers\ProfileCommentController;
use Victi\MyGameLibrary\Controllers\ReviewCommentController;
use Victi\MyGameLibrary\Controllers\FollowController;
use Victi\MyGameLibrary\Controllers\FeedController;
use Victi\MyGameLibrary\Controllers\TopGamesController;
use Victi\MyGameLibrary\Controllers\NotificationController;
use Victi\MyGameLibrary\Controllers\YearReviewController;
use Victi\MyGameLibrary\Controllers\ProfileCardController;


// URLs amigáveis: /perfil/victor vira action=profile&u=victor (veja src/pretty_urls.php).
// Os redirecionamentos relativos dos controllers são ancorados na pasta do site.
mgl_absolutize_redirects();
if (!mgl_route_request()) {
    mgl_redirect_to_pretty();   // só age com PRETTY_URLS=true
}

$action = $_GET['action'] ?? 'home';
mgl_start_pretty_output($action);   // links do HTML já saem bonitos (só com PRETTY_URLS=true)


$routes = [
    'login'          => [AuthController::class, 'login'],
    'register'       => [AuthController::class, 'register'],
    'logout'         => [AuthController::class, 'logout'],
    'forgot_password' => [AuthController::class, 'forgotPassword'],
    'reset_password' => [AuthController::class, 'resetPassword'],
    'add_game'       => [GameController::class, 'add'],
    'delete_game'    => [GameController::class, 'delete'],
    'remove_custom_tag' => [GameController::class, 'removeCustomTag'],
    'add_tag' => [GameController::class, 'addTag'],
    'delete_saved_tag' => [GameController::class, 'deleteSavedTag'],
    'change_status'  => [GameController::class, 'changeStatus'],
    'toggle_platinum' => [GameController::class, 'togglePlatinum'],
    'change_rating'  => [GameController::class, 'changeRating'],
    'search'         => [GameController::class, 'search'],
    'ajax_search'    => [GameController::class, 'ajaxSearch'],
    'details'        => [GameController::class, 'details'],
    'game'           => [GameController::class, 'page'],
    'export_library' => [GameController::class, 'exportCsv'],
    'year_review'    => [YearReviewController::class, 'index'],
    'profile_card'   => [ProfileCardController::class, 'image'],
    'year_card'      => [ProfileCardController::class, 'yearImage'],
    'save_review'    => [GameController::class, 'saveReview'],
    'dashboard'      => [DashboardController::class, 'index'],
    'dashboard_data' => [DashboardController::class, 'data'],
    'home'           => [GameController::class, 'index'],
    'profile'        => [ProfileController::class, 'view'],
    'friends_list'   => [ProfileController::class, 'friendsList'],
    'edit_profile'   => [ProfileController::class, 'edit'],
    'update_profile' => [ProfileController::class, 'update'],
    'change_password' => [ProfileController::class, 'changePassword'],
    'profile_comment_add' => [ProfileCommentController::class, 'add'],
    'profile_comment_delete' => [ProfileCommentController::class, 'delete'],
    'review_comment_add' => [ReviewCommentController::class, 'add'],
    'review_comment_delete' => [ReviewCommentController::class, 'delete'],
    'follow_toggle'  => [FollowController::class, 'toggle'],
    'search_users'   => [FollowController::class, 'searchUsers'],
    'top_games_library' => [TopGamesController::class, 'library'],
    'top_games_save' => [TopGamesController::class, 'save'],
    'feed'           => [FeedController::class, 'index'],
    'activity_toggle_like' => [FeedController::class, 'toggleLike'],
    'verify_email'   => [AuthController::class, 'verifyEmail'],
    'resend_verification' => [AuthController::class, 'resendVerification'],
    'resend_verification_public' => [AuthController::class, 'resendVerificationPublic'],
    'notifications_get' => [NotificationController::class, 'getNotifications'],
    'notifications_count' => [NotificationController::class, 'countUnread'],
    'notifications_stream' => [NotificationController::class, 'streamNotifications'],
    'notification_mark_read' => [NotificationController::class, 'markAsRead'],
    'notifications_mark_all_read' => [NotificationController::class, 'markAllAsRead'],
    'notifications_clear_all' => [NotificationController::class, 'clearAll']
];

if (array_key_exists($action, $routes)) {
    $controllerName = $routes[$action][0];
    $methodName = $routes[$action][1];

    $controller = new $controllerName();
    $controller->$methodName();
} else {
    mgl_not_found();
}