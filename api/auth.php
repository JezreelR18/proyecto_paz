<?php
// api/auth.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../model/UserModel.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Convierte notices/warnings en excepciones y responde JSON
set_error_handler(function($severity, $message, $file, $line) {
  throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function($e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Server error',
    'error'   => $e->getMessage(),
    'file'    => $e->getFile(),
    'line'    => $e->getLine(),
  ], JSON_UNESCAPED_UNICODE);
  exit;
});

// Iniciar sesión una sola vez
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("=== AUTH API CALLED ===");
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$userModel = new UserModel();

// Manejar preflight request (CORS)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'session' && $method === 'GET') {
    sendResponse(true, 'Estado de sesión obtenido', [
        'loggedIn' => isset($_SESSION['user']),
        'user' => $_SESSION['user'] ?? null
    ]);
}

if ($action === 'signup' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validar campos
    $required = ['fullname', 'username', 'password', 'email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse(false, "El campo $field es requerido", null, 400);
        }
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Email no válido', null, 400);
    }

    if ($userModel->userExists($data['username'], $data['email'])) {
        sendResponse(false, 'El usuario o email ya existen', null, 409);
    }

    $userData = [
        'fullname' => $data['fullname'],
        'username' => $data['username'],
        'password' => $data['password'],
        'email' => $data['email'],
        'role' => $data['role'] ?? 'estudiante'
    ];

    $result = $userModel->createUser($userData);
    if ($result) {
        sendResponse(true, 'Usuario registrado exitosamente', null, 201);
    } else {
        sendResponse(false, 'Error al crear el usuario', null, 500);
    }
}

/**
 * === ENDPOINT: Inicio de sesión (signin) ===
 */
if ($action === 'signin' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['username']) || empty($data['password'])) {
        sendResponse(false, 'Usuario y contraseña requeridos', null, 400);
    }

    $user = $userModel->validateUser($data['username'], $data['password']);

    if ($user) {
        $_SESSION['user'] = [
            'id' => $user['id_user'],
            'username' => $user['username'],
            'fullname' => $user['fullname'],
            'role' => $user['role']
        ];

        sendResponse(true, 'Login exitoso', $_SESSION['user']);
    } else {
        sendResponse(false, 'Usuario o contraseña incorrectos', null, 401);
    }
}

if ($action === 'signout' && $method === 'POST') {
    session_unset();
    session_destroy();
    sendResponse(true, 'Sesión cerrada exitosamente');
}


if ($action === 'check' && $method === 'GET') {
    if (isset($_SESSION['user'])) {
        sendResponse(true, 'Usuario autenticado', $_SESSION['user']);
    } else {
        sendResponse(false, 'No autenticado', null, 401);
    }
}

// Si ninguna ruta coincide:
sendResponse(false, 'Ruta o método no válido', null, 400);
