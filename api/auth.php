<?php
// api/auth.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Habilitar logging para debug
error_log("=== AUTH API CALLED ===");

require_once __DIR__ . '/../model/UserModel.php';

$method = $_SERVER['REQUEST_METHOD'];
$userModel = new UserModel();

error_log("Method: $method");

// Manejar preflight request
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

switch($method) {
    case 'POST':
        $action = $_GET['action'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true);
        
        error_log("Action: $action");
        error_log("Input: " . print_r($input, true));
        
        switch($action) {
            case 'signup':
                signup($input, $userModel);
                break;
            case 'signin':
                signin($input, $userModel);
                break;
            case 'signout':
                signout();
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
        break;
        
    case 'GET':
        $action = $_GET['action'] ?? '';
        switch($action) {
            case 'check':
                checkAuth();
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

function signup($data, $userModel) {
    error_log("=== SIGNUP FUNCTION ===");
    
    // Validar campos requeridos
    $required = ['fullname', 'username', 'password', 'email'];
    foreach($required as $field) {
        if (empty($data[$field])) {
            error_log("Campo faltante: $field");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "El campo $field es requerido"]);
            return;
        }
    }
    
    // Validar email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        error_log("Email inválido: " . $data['email']);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email no válido']);
        return;
    }
    
    // Verificar si el usuario ya existe
    if ($userModel->userExists($data['username'], $data['email'])) {
        error_log("Usuario ya existe: " . $data['username']);
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'El usuario o email ya existen']);
        return;
    }
    
    // Crear usuario
    $userData = [
        'fullname' => $data['fullname'],
        'username' => $data['username'],
        'password' => $data['password'],
        'email' => $data['email'],
        'role' => $data['role'] ?? 'estudiante'
    ];
    
    error_log("Intentando crear usuario: " . print_r($userData, true));
    
    $result = $userModel->createUser($userData);
    error_log("Resultado de createUser: " . ($result ? 'TRUE' : 'FALSE'));
    
    if ($result) {
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Usuario registrado exitosamente']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear el usuario']);
    }
}

function signin($data, $userModel) {
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña requeridos']);
        return;
    }
    
    $user = $userModel->validateUser($data['username'], $data['password']);
    
    if ($user) {
        session_start();
        $_SESSION['user'] = [
            'id' => $user['id_user'],
            'username' => $user['username'],
            'fullname' => $user['fullname'],
            'role' => $user['role']
        ];
        
        echo json_encode([
            'success' => true, 
            'message' => 'Login exitoso',
            'user' => [
                'id' => $user['id_user'],
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'role' => $user['role']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos']);
    }
}

function signout() {
    session_start();
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Sesión cerrada exitosamente']);
}

function checkAuth() {
    session_start();
    if (isset($_SESSION['user'])) {
        echo json_encode([
            'success' => true, 
            'user' => $_SESSION['user']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
    }
}
?>