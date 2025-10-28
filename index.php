<?php
// Mostrar todos los errores en pantalla (solo para desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// También logear a un archivo específico
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
// index.php
define('ROOT_PATH', __DIR__);

session_start();

// Manejo de rutas básico
$page = $_GET['page'] ?? 'home';

// Incluir header
include ROOT_PATH . '/view/templates/header.html';

// Cargar la vista correspondiente
$viewPath = ROOT_PATH . "/view/{$page}.html";
if (file_exists($viewPath)) {
    include $viewPath;
} else {
    include ROOT_PATH . '/view/home.html';
}

// Incluir footer
include ROOT_PATH . '/view/templates/footer.html';
?>