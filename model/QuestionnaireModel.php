<?php
// model/QuestionnaireModel.php
declare(strict_types=1);

class QuestionnaireModel {
  private PDO $db;
  public function __construct(PDO $db) { $this->db = $db; }
  public function pdo(): PDO { return $this->db; }

  public function listActive(): array {
    $sql = "SELECT id_questionnaire, title, description, category, total_questions
            FROM questionnaires WHERE is_active = 1 ORDER BY created_at DESC";
    return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  public function getWithQuestions(int $id): array {
    $q = $this->db->prepare("SELECT * FROM questionnaires WHERE id_questionnaire = ?");
    $q->execute([$id]);
    $quest = $q->fetch(PDO::FETCH_ASSOC);
    if (!$quest) throw new RuntimeException('Cuestionario no encontrado');

    $qq = $this->db->prepare("SELECT id_question, id_questionnaire, question_text, question_type, options, weight, question_order, dimension
                              FROM questions WHERE id_questionnaire = ? ORDER BY question_order ASC");
    $qq->execute([$id]);
    $quest['questions'] = $qq->fetchAll(PDO::FETCH_ASSOC);
    return $quest;
  }

  public function saveUserAnswers(int $userId, int $idQuestionnaire, array $answers): void {
    $ins = $this->db->prepare("INSERT INTO user_answers (id_user, id_question, id_questionnaire, answer_value) VALUES (?,?,?,?)");
    foreach ($answers as $a) {
      $qid = (int)$a['id_question'];
      $val = (string)$a['answer_value'];
      $ins->execute([$userId, $qid, $idQuestionnaire, $val]);
    }
  }
}
