<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string) (30 * 24 * 60 * 60));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
date_default_timezone_set('America/Fortaleza');

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/Aplication');
define('VIEW_PATH', APP_PATH . '/Views');
define('ASSET_PATH', 'Arquivos');

require_once APP_PATH . '/Config/Database.php';
require_once APP_PATH . '/Base/Models/BaseModel.php';
require_once APP_PATH . '/Base/Controllers/BaseController.php';

require_once APP_PATH . '/Models/UserModel.php';
require_once APP_PATH . '/Models/PasswordResetModel.php';
require_once APP_PATH . '/Models/DashboardModel.php';
require_once APP_PATH . '/Models/AccessModel.php';
require_once APP_PATH . '/Models/StockModel.php';
require_once APP_PATH . '/Models/CatalogModel.php';
require_once APP_PATH . '/Models/ReportsModel.php';
require_once APP_PATH . '/Services/DemoSeeder.php';
require_once APP_PATH . '/Controllers/LoginController.php';
require_once APP_PATH . '/Controllers/PasswordResetController.php';
require_once APP_PATH . '/Controllers/DashboardController.php';
require_once APP_PATH . '/Controllers/StockController.php';
require_once APP_PATH . '/Controllers/AdminController.php';
require_once APP_PATH . '/Controllers/ReportsController.php';
require_once APP_PATH . '/Controllers/LegalController.php';

$routes = require APP_PATH . '/Config/routes.php';
$page = (string) ($_GET['page'] ?? 'login');
$action = (string) ($_GET['action'] ?? 'index');
$route = $routes[$page] ?? $routes['404'];
if (!isset($routes[$page])) {
    $action = 'notFound';
}
$controller = new $route['controller']();

if (!method_exists($controller, $action) || !in_array($action, $route['actions'], true)) {
    http_response_code(404);
    (new LoginController())->notFound();
    exit;
}

$controller->{$action}();
