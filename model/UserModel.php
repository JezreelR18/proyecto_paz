<?php
require_once __DIR__ . '/Database.php';

class UserModel {
    private $pdo;
    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function userExists(string $username, string $email): bool {
        $sql = "SELECT 1 FROM users WHERE username = :u OR email = :e LIMIT 1";
        $st  = $this->pdo->prepare($sql);
        $st->execute([':u'=>$username, ':e'=>$email]);
        return (bool)$st->fetchColumn();
    }

    public function createUser(array $data): bool {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (fullname, username, email, password, role)
                VALUES (:f, :u, :e, :p, :r)";
        $st = $this->pdo->prepare($sql);
        return $st->execute([
            ':f' => $data['fullname'],
            ':u' => $data['username'],
            ':e' => $data['email'],
            ':p' => $hash,
            ':r' => $data['role'] ?? 'estudiante',
        ]);
    }

    public function validateUser(string $usernameOrEmail, string $password) {
        $sql = "SELECT id_user, username, fullname, role, password
                FROM users
                WHERE username = :u OR email = :u
                LIMIT 1";
        $st = $this->pdo->prepare($sql);
        $st->execute([':u' => $usernameOrEmail]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (!$user) return false;

        $stored = $user['password'];

        $info = password_get_info($stored);
        $isHashed = !empty($info['algo']);

        $valid = false;

        if ($isHashed) {
            $valid = password_verify($password, $stored);

            if ($valid && password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                $this->updatePasswordHash((int)$user['id_user'], $password);
            }
        } else {
            $valid = hash_equals($stored, $password);
            if ($valid) {
                $this->updatePasswordHash((int)$user['id_user'], $password);
            }
        }

        if (!$valid) return false;

        unset($user['password']);
        return $user;
    }

    private function updatePasswordHash(int $userId, string $plainPassword): void {
        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = :p WHERE id_user = :id";
        $st  = $this->pdo->prepare($sql);
        $st->execute([':p'=>$newHash, ':id'=>$userId]);
    }
}

?>