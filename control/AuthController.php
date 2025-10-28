<?php
// control/AuthController.php
require_once __DIR__ . '/../model/UserModel.php';

class AuthController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new UserModel();
    }
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if ($this->userModel->validateUser($username, $password)) {
                session_start();
                $_SESSION['user'] = $username;
                header('Location: index.php?page=home');
                exit;
            } else {
                return "Usuario o contraseña incorrectos";
            }
        }
        return null;
    }
    
    public function logout() {
        session_start();
        session_destroy();
        header('Location: index.php?page=home');
        exit;
    }
}
?>