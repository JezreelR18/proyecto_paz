<?php
// model/UserModel.php
require_once __DIR__ . '/Database.php';

class UserModel {
    private $db;
    
    public function __construct() {
        $this->db = (new Database())->getConnection();
        error_log("Database connection: " . ($this->db ? "SUCCESS" : "FAILED"));
    }
    
    public function createUser($userData) {
        try {
            error_log("=== CREATE USER ===");
            error_log("User data: " . print_r($userData, true));
            
            $query = "INSERT INTO users (fullname, username, password, email, role) 
                     VALUES (:fullname, :username, :password, :email, :role)";
            
            error_log("Query: $query");
            
            $stmt = $this->db->prepare($query);
            
            // Hash de la contraseña
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            error_log("Password hashed: " . ($hashedPassword ? "YES" : "NO"));
            
            $stmt->bindParam(":fullname", $userData['fullname']);
            $stmt->bindParam(":username", $userData['username']);
            $stmt->bindParam(":password", $hashedPassword);
            $stmt->bindParam(":email", $userData['email']);
            $stmt->bindParam(":role", $userData['role']);
            
            $result = $stmt->execute();
            error_log("Execute result: " . ($result ? "TRUE" : "FALSE"));
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("PDO Error: " . print_r($errorInfo, true));
            }
            
            return $result;
        } catch(PDOException $e) {
            error_log("PDO Exception: " . $e->getMessage());
            error_log("Error Code: " . $e->getCode());
            return false;
        }
    }
    
    public function userExists($username, $email) {
        try {
            $query = "SELECT id_user FROM users WHERE username = :username OR email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            $exists = $stmt->rowCount() > 0;
            error_log("User exists check - Username: $username, Email: $email, Exists: " . ($exists ? "YES" : "NO"));
            
            return $exists;
        } catch(PDOException $e) {
            error_log("userExists Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserById($id) {
        try {
            $query = "SELECT id_user, username, fullname, email, role FROM users WHERE id_user = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>