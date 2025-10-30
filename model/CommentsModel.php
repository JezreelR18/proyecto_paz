<?php
require_once __DIR__ . '/Database.php';

class CommentsModel {
    private PDO $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function create(int $userId, string $comment, string $level = 'normal'): bool {
        $comment = trim($comment);
        if ($comment === '') return false;
        $level = mb_substr($level, 0, 20);

        $sql = "INSERT INTO comments (comment, level_monitoring, id_user_register)
                VALUES (:c, :lvl, :uid)";
        $st = $this->pdo->prepare($sql);
        return $st->execute([
            ':c'   => $comment,
            ':lvl' => $level,
            ':uid' => $userId
        ]);
    }

    public function createSystemSuggestion(string $comment): bool {
        return $this->create(1, $comment, 'suggestion');
    }

    public function list(int $limit = 100, int $offset = 0): array {
        $sql = "SELECT
                    c.id_comment,
                    c.comment,
                    c.level_monitoring,
                    c.date_of_register,
                    u.id_user   AS author_id,
                    u.fullname  AS author_fullname,
                    u.username  AS author_username
                FROM comments c
                LEFT JOIN users u ON u.id_user = c.id_user_register
                ORDER BY c.date_of_register ASC
                LIMIT :lim OFFSET :off";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPdo(): PDO { return $this->pdo; }

}
