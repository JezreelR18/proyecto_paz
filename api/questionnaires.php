<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../model/Database.php';
require_once __DIR__ . '/../model/QuestionnaireModel.php';
require_once __DIR__ . '/../model/AssessmentService.php';

function send($payload, int $code=200){ http_response_code($code); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }

try {
  $db = (new Database())->getConnection();
  $qm = new QuestionnaireModel($db);
  $svc = new AssessmentService($db);

  $action = $_GET['action'] ?? $_POST['action'] ?? '';

  if ($action === 'listActive') {
    $list = $qm->listActive();
    send(['ok'=>true,'data'=>$list]);
  }

  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) send(['ok'=>false,'error'=>'id requerido'], 400);
    $q = $qm->getWithQuestions($id);
    send(['ok'=>true,'data'=>$q]);
  }

  if ($action === 'submit') {
    $uid = $_SESSION['user']['id_user'] ?? $_SESSION['user']['id'] ?? null;
    if (!$uid) send(['ok'=>false,'error'=>'No autenticado'], 401);

    $raw = file_get_contents('php://input');
    $in = json_decode($raw, true);
    if (!is_array($in)) send(['ok'=>false,'error'=>'JSON inválido'], 400);

    $idq = (int)($in['id_questionnaire'] ?? 0);
    $answers = $in['answers'] ?? [];
    if (!$idq || !is_array($answers) || !count($answers)) {
      send(['ok'=>false,'error'=>'Faltan datos'], 400);
    }

    $db->beginTransaction();
    try {
      $q = $qm->getWithQuestions($idq);
      $qm->saveUserAnswers((int)$uid, $idq, $answers);
      $db->commit();
    } catch (\Throwable $e) {
      $db->rollBack();
      throw $e;
    }

    $result = $svc->calculateAndStore((int)$uid, $q, $answers);
    send(['ok'=>true,'data'=>$result], 201);
  }

  if ($action === 'userStats') {
    $uid = $_SESSION['user']['id_user'] ?? $_SESSION['user']['id'] ?? null;
    if (!$uid) send(['ok'=>false,'error'=>'No autenticado'], 401);

    $sql = "SELECT r.id_result, q.title, r.completed_at, r.total_score
            FROM assessment_results r
            INNER JOIN questionnaires q ON q.id_questionnaire = r.id_questionnaire
            WHERE r.id_user = ?
            ORDER BY r.completed_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    send(['ok'=>true, 'data'=>[
      'total'=>count($rows),
      'results'=>$rows
    ]]);
  }

  if ($action === 'resultDetail') {
    $uid = $_SESSION['user']['id_user'] ?? $_SESSION['user']['id'] ?? null;
    if (!$uid) send(['ok'=>false,'error'=>'No autenticado'], 401);

    $idResult = (int)($_GET['id_result'] ?? 0);
    if (!$idResult) send(['ok'=>false,'error'=>'id_result requerido'], 400);

    $qHead = $db->prepare(
      "SELECT r.id_result, r.id_user, r.id_questionnaire, r.total_score, r.emotional_score,
              r.stress_score, r.conflict_score, r.self_awareness_score, r.mental_state_category,
              r.completed_at, q.title, q.description
      FROM assessment_results r
      JOIN questionnaires q ON q.id_questionnaire = r.id_questionnaire
      WHERE r.id_result=? AND r.id_user=?"
    );
    $qHead->execute([$idResult, $uid]);
    $head = $qHead->fetch(PDO::FETCH_ASSOC);
    if (!$head) send(['ok'=>false,'error'=>'Resultado no encontrado'], 404);

    $qReco = $db->prepare(
      "SELECT rr.id_resource, rr.recommendation_reason, rr.priority_level,
              res.title, res.type_resource, res.url, res.description
      FROM result_recommendations rr
      JOIN resources res ON res.id_resource = rr.id_resource
      WHERE rr.id_result=?
      ORDER BY rr.priority_level ASC, rr.id_recommendation ASC"
    );
    $qReco->execute([$idResult]);
    $reco = $qReco->fetchAll(PDO::FETCH_ASSOC);

    $completedAt = $head['completed_at'];

    $qAns = $db->prepare(
      "SELECT qu.id_question, qu.question_text, qu.question_type, qu.question_order, qu.dimension,
              ua.answer_value, ua.answered_at
      FROM questions qu
      LEFT JOIN user_answers ua
        ON ua.id_question = qu.id_question
        AND ua.id_user = ?
        AND ua.id_questionnaire = qu.id_questionnaire
        AND ua.answered_at = (
            SELECT MAX(ua2.answered_at)
            FROM user_answers ua2
            WHERE ua2.id_user = ?
              AND ua2.id_question = qu.id_question
              AND ua2.id_questionnaire = qu.id_questionnaire
              AND ua2.answered_at <= ?
        )
      WHERE qu.id_questionnaire = ?
      ORDER BY qu.question_order ASC"
    );
    $qAns->execute([$uid, $uid, $completedAt, $head['id_questionnaire']]);
    $answers = $qAns->fetchAll(PDO::FETCH_ASSOC);

    send(['ok'=>true, 'data'=>[
      'result' => $head,
      'answers' => $answers,
      'recommendations' => $reco
    ]]);
  }


  send(['ok'=>false,'error'=>'Acción no soportada'], 400);
  
} catch (\Throwable $e) {
  send(['ok'=>false,'error'=>'Server error','detail'=>$e->getMessage()], 500);
}
