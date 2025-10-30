<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../model/CommentsModel.php';

function send(array $payload, int $code = 200): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
  $model = new CommentsModel();

  if ($method === 'GET' && $action === 'list') {
    $limit  = max(1, (int)($_GET['limit']  ?? 100));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $comments = $model->list($limit, $offset);
    send(['ok' => true, 'data' => ['comments' => $comments]]);
  }


  if ($method === 'POST' && $action === 'create') {
      $userId = $_SESSION['user']['id_user'] ?? $_SESSION['user']['id'] ?? null;
      if (!$userId) send(['ok'=>false,'error'=>'No autenticado'], 401);

      $raw = file_get_contents('php://input') ?: '';
      $in  = json_decode($raw, true);
      if (!is_array($in)) send(['ok'=>false,'error'=>'JSON inválido'], 400);

      $comment = (string)($in['comment'] ?? '');
      $level   = (string)($in['level_monitoring'] ?? 'normal');

      if (containsBadWords($comment)) {
        send(['ok'=>false,'error'=>'Mensaje con palabras no permitidas'], 400);
      }

      $pdo = $model->getPdo();
      $pdo->beginTransaction();
      try {
          $model->create((int)$userId, $comment, $level);

          $botId = 1;
          $suggestion = makeSuggestion($comment);
          $model->create($botId, $suggestion, 'system');

          $pdo->commit();
          send(['ok'=>true, 'data'=>['saved'=>true]]);
      } catch (Throwable $e) {
          $pdo->rollBack();
          send(['ok'=>false,'error'=>'No se pudo guardar', 'detail'=>$e->getMessage()], 500);
      }
  }

  send(['ok'=>false,'error'=>'Acción no soportada'], 400);

} catch (Throwable $e) {
  send(['ok'=>false,'error'=>'Server error','detail'=>$e->getMessage()], 500);
}


function normalize(string $s): string {
  $s = mb_strtolower($s, 'UTF-8');
  if (class_exists('Transliterator')) {
    $tr = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
    if ($tr) $s = $tr->transliterate($s);
  } else {
    $s = iconv('UTF-8','ASCII//TRANSLIT',$s);
    $s = preg_replace('/\p{Mn}+/u','',$s);
  }
  $s = strtr($s, ['@'=>'a','$'=>'s','0'=>'o','1'=>'i','3'=>'e','4'=>'a','5'=>'s','7'=>'t']);
  $s = preg_replace('/[^\p{L}\p{N}\s]/u',' ',$s);
  $s = preg_replace('/\s+/',' ',$s);
  return trim($s);
}


function containsBadWords(string $text): bool {
  $bad = [
    'gilipollas', 'imbécil', 'analfabeto', 'retrasado', 'anormal', 'pendejo', 'pendeja',
    'puta', 'puto', 'mierda', 'carajo', 'coño', 'verga',
    'cabrón', 'cabrona', 'chinga', 'chingar', 'pinche', 'culero', 'culera',
    'joder', 'hostia', 'estúpido', 'idiota', 
    'malparido', 'hijueputa', 'marica', 'maricón', 'naco', 'pendejada',
    'maldito', 'maldita', 'diablo', 'demonio', 'carajo', 'cojones',
    'culo', 'gay', 'joto', 'perra', 'perro', 'zorra', 'mames', 'mamador'
  ];
  $norm = normalize($text);
  $tokens = array_flip(explode(' ', $norm));
  foreach ($bad as $b) {
    $b = normalize($b);
    if (isset($tokens[$b])) return true;
    if (mb_strpos($norm, $b) !== false && mb_strlen($b) >= 4) return true;
  }
  return false;
}


function makeSuggestion(string $text): string {
  $norm = normalize($text);

  $general = [
    "Gracias por compartir. ¿Qué aprendizaje te llevas de esto y cómo podrías aplicarlo en tu entorno?",
    "Si volvieras a vivir esta situación, ¿qué harías igual y qué harías distinto?",
    "¿Qué necesidad estaba detrás de lo que sentiste o pensaste?",
    "Piensa en una acción pequeña y concreta que puedas hacer hoy para cuidar la convivencia.",
    "¿A quién podrías pedir ayuda o con quién quisieras conversar sobre esto?",
    "¿Qué te sorprendió de la situación y por qué?",
    "Nombra en una frase tu intención para mañana respecto a este tema.",
  ];

  $gratitud = [
    "¡Qué bien leer eso! Reconocer lo positivo fortalece la convivencia. ¿Qué te gustaría repetir mañana?",
    "La gratitud amplifica lo bueno. ¿A quién quisieras agradecerle explícitamente?",
    "Piensa en dos detalles pequeños por los que te sientes agradecido/a hoy.",
  ];

  $emociones_dificiles = [
    "Lamento que te sientas así. Respirar y nombrar la emoción ayuda. ¿Qué acción pequeña puedes tomar ahora?",
    "Valida lo que sientes: es válido. ¿Qué te ayudaría a estar 1% mejor?",
    "Si esa emoción tuviera voz, ¿qué te estaría pidiendo con respeto?",
  ];

  $conflicto = [
    "En un desacuerdo, ayuda describir hechos antes que juicios. ¿Cómo relatarías lo ocurrido en 3 frases objetivas?",
    "Prueba con un mensaje en primera persona: 'Yo siento ___ cuando ___ porque ___ y me gustaría ___'.",
    "Explora intereses: ¿qué quería realmente cada parte más allá de tener razón?",
  ];

  $empatia = [
    "Imagina la situación desde la otra orilla: ¿qué podría estar necesitando la otra persona?",
    "Una escucha breve y sin interrupciones puede cambiar el tono. ¿Qué pregunta curiosa podrías hacer?",
    "Nombra algo que valoras de la otra persona antes de plantear tu petición.",
  ];

  $logros = [
    "Celebra el avance, por pequeño que parezca. ¿Qué hábito te ayudó a lograrlo?",
    "Identifica qué aprendiste y cómo puedes repetir la fórmula.",
    "Comparte tu logro con alguien: cuando lo contamos, lo consolidamos.",
  ];

  $colaboracion = [
    "Define el próximo paso y quién puede apoyarte. Pequeño y con fecha.",
    "Aclara expectativas: ¿qué necesitas exactamente y cómo sabrás que se cumplió?",
    "Propón una solución donde ambas partes ganen algo tangible.",
  ];

  $pool = [];

  if (mb_strpos($norm,'gracias') !== false || mb_strpos($norm,'agradec') !== false) {
    $pool = array_merge($pool, $gratitud);
  }
  if (mb_strpos($norm,'triste') !== false || mb_strpos($norm,'enoj') !== false ||
      mb_strpos($norm,'ansio') !== false || mb_strpos($norm,'miedo') !== false ||
      mb_strpos($norm,'frustr') !== false) {
    $pool = array_merge($pool, $emociones_dificiles);
  }
  if (mb_strpos($norm,'conflict') !== false || mb_strpos($norm,'pelea') !== false ||
      mb_strpos($norm,'discusi') !== false || mb_strpos($norm,'desacuerd') !== false) {
    $pool = array_merge($pool, $conflicto);
  }
  if (mb_strpos($norm,'empati') !== false || mb_strpos($norm,'escucha') !== false ||
      mb_strpos($norm,'comprend') !== false) {
    $pool = array_merge($pool, $empatia);
  }
  if (mb_strpos($norm,'logr') !== false || mb_strpos($norm,'meta') !== false ||
      mb_strpos($norm,'orgull') !== false || mb_strpos($norm,'avanz') !== false) {
    $pool = array_merge($pool, $logros);
  }
  if (mb_strpos($norm,'juntos') !== false || mb_strpos($norm,'equipo') !== false ||
      mb_strpos($norm,'apoy') !== false || mb_strpos($norm,'colabor') !== false) {
    $pool = array_merge($pool, $colaboracion);
  }

  if (empty($pool)) {
    $pool = $general;
  } else {
    $pool = array_merge($pool, $general);
  }

  try {
    $idx = random_int(0, count($pool) - 1);
  } catch (Throwable $e) {
    $idx = mt_rand(0, count($pool) - 1);
  }
  return $pool[$idx];
}
