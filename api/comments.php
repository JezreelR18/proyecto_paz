<?php
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
          $suggestion = analyzeAndRespond($comment);
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

function analyzeAndRespond(string $text): string {
    // Normalizamos para análisis, pero guardamos también el original
    $norm = normalize($text);

    // Analizar mensaje (emociones, intención, temas, riesgo, etc.)
    $analysis = analyzeMessage($norm, $text);

    // Generar respuesta en lenguaje natural
    return generateResponse($analysis, $text);
}

/**
 * Analiza el mensaje y devuelve un arreglo con MUCHOS datos:
 * - sentiment: positive | negative | neutral
 * - sentiment_score: número (más alto = más positivo)
 * - emotional_intensity: low | medium | high
 * - intent: vent | ask_advice | celebrate | share_update | general
 * - is_question: bool
 * - needs_empathy: bool
 * - needs_advice: bool
 * - needs_grounding: bool
 * - risk: none | medium | high
 * - risk_triggers: palabras/frases detectadas de riesgo
 * - topics: familia, amigos, pareja, escuela, trabajo, salud, futuro, emociones, dinero
 * - subtopics: bullying, autoestima, soledad, estres, motivacion, duelo, ansiedad, ira
 * - emotions: tristeza, ansiedad, enojo, miedo, soledad, frustracion, cansancio, alegria
 */
function analyzeMessage(string $norm, string $original = ''): array {

    $tokens = preg_split('/\s+/', trim($norm));
    $tokens = array_filter($tokens, function($t) { return $t !== ''; });
    $wordCount = count($tokens);

    $analysis = [
        'sentiment'            => 'neutral',
        'sentiment_score'      => 0,
        'emotional_intensity'  => 'low',
        'intent'               => 'general',
        'is_question'          => false,
        'needs_empathy'        => false,
        'needs_advice'         => false,
        'needs_grounding'      => false,
        'topics'               => [],
        'subtopics'            => [],
        'emotions'             => [],
        'risk'                 => 'none',
        'risk_triggers'        => [],
        'length'               => $wordCount,
    ];

    // ---------------------------
    // 1. Diccionarios de palabras
    // ---------------------------

    // Palabras positivas (normalizadas, sin acentos)
    $positiveWords = [
        'feliz','contento','contenta','alegre','tranquilo','tranquila','calmado','calmada',
        'motivado','motivada','animado','animada','agradecido','agradecida','orgulloso','orgullosa',
        'logre','logro','lograr','consegui','conseguir','mejor','mejorando','aprendi','aprendiendo',
        'exito','exitazo','bien','muy bien','todo bien','me fue bien','ganar','gane','aprobe','aprobe el examen'
    ];

    // Palabras negativas
    $negativeWords = [
        'triste','tristeza','solo','sola','vacío','vacio','cansado','cansada','agotado','agotada',
        'estres','estresado','estresada','ansioso','ansiosa','nervioso','nerviosa','preocupado','preocupada',
        'enojado','enojada','molesto','molesta','frustrado','frustrada','harto','harta',
        'fatal','horrible','espantoso','miedo','temor','panico','ansiedad',
        'fracaso','fracase','fallo','falle','perdi','perder','inutil','inutiles',
        'no sirvo','no valgo','no puedo','no doy mas','no aguanto','me duele','me siento mal','me siento muy mal',
        'solo quiero llorar','llorando'
    ];

    // Intensificadores de emoción
    $intensifiers = [
        'muy','demasiado','demaciado','horrible','super','tan','bastante','demasiado fuerte',
        'no aguanto','no soporto','no doy mas'
    ];

    // Palabras que suavizan/relativizan
    $softeners = [
        'un poco','algo','mas o menos','no tanto','un poquito'
    ];

    // Temas generales
    $topicMap = [
        'familia' => [
            'mama','papa','madre','padre','hermano','hermana','hermanos','hermanas',
            'abuelo','abuela','tio','tia','primo','prima','familia','familiares','hogar','casa'
        ],
        'amigos' => [
            'amigo','amiga','amigos','amigas','compa','companero','companera','grupo','bolita','pandilla'
        ],
        'pareja' => [
            'novio','novia','pareja','crush','me gusta','relacion','relacion de pareja','terminamos','corte',
            'cortamos','me corto','me corto mi pareja','ex','exnovio','exnovia'
        ],
        'escuela' => [
            'escuela','colegio','uni','universidad','prepa','secundaria','clase','clases','tarea','tareas',
            'examen','examenes','proyecto','proyectos','maestro','maestra','profesor','profesora','companeros'
        ],
        'trabajo' => [
            'trabajo','jefe','jefa','oficina','empleo','chamba','turno'
        ],
        'salud' => [
            'salud','enfermo','enferma','dolor','duele','medico','doctor','doctora','hospital',
            'ansiedad','depresion','ataque de panico','crisis','insomnio','no puedo dormir'
        ],
        'futuro' => [
            'futuro','meta','metas','sueno','suenos','plan','planes','no se que hacer con mi vida',
            'que hacer con mi vida','proximo ano','proximo año','universidad','carrera'
        ],
        'emociones' => [
            'me siento','siento que','emocion','emociones','sentimientos','estado de animo'
        ],
        'dinero' => [
            'dinero','plata','pago','pagos','deuda','deudas','gastos','no me alcanza','no alcanza'
        ]
    ];

    // Subtemas específicos
    $subtopicMap = [
        'bullying' => [
            'me molestan','me hacen bullying','se burlan','se burlan de mi','acoso','acoso escolar',
            'me excluyen','no me hablan','me hacen a un lado','me empujaron','me insultan'
        ],
        'autoestima' => [
            'no valgo','no sirvo','no soy suficiente','soy un fracaso','soy una basura',
            'me odio','me odio a mi mismo','me odio a mi misma','no me gusta como soy',
            'no me gusta mi cuerpo','me siento feo','me siento fea'
        ],
        'soledad' => [
            'solo','sola','nadie me habla','no tengo amigos','no tengo a nadie','me siento aislado',
            'me siento aislada','siento que estorbo','siento que sobro','siento que no pertenezco'
        ],
        'estres' => [
            'estres','estresado','estresada','presion','presionado','presionada',
            'no doy mas','no aguanto','tengo mucho que hacer','demasiadas cosas','no alcanzo'
        ],
        'motivacion' => [
            'no tengo ganas','no tengo energia','no quiero hacer nada','me cuesta empezar',
            'quiero mejorar','quiero cambiar','quiero salir adelante','quiero hacerlo mejor'
        ],
        'duelo' => [
            'murio','murio mi','fallecio','fallecio mi','perdi a','perdi a mi','se fue para siempre'
        ],
        'ansiedad' => [
            'ataque de panico','ataque de ansiedad','me falta el aire','me cuesta respirar',
            'muchos nervios','muy nervioso','muy nerviosa','ansiedad'
        ],
        'ira' => [
            'mucho coraje','estoy muy enojado','estoy muy enojada','rabia','odio a','lo odio','la odio'
        ]
    ];

    // Emociones "principales" (para etiquetar)
    $emotionWords = [
        'tristeza' => ['triste','tristeza','ganas de llorar','solo quiero llorar','llorando'],
        'ansiedad' => ['ansioso','ansiosa','ansiedad','nervioso','nerviosa','preocupado','preocupada','me preocupa'],
        'enojo'    => ['enojado','enojada','coraje','molesto','molesta','odio','rabia'],
        'miedo'    => ['miedo','temor','panico','tengo miedo','me da miedo'],
        'soledad'  => ['solo','sola','nadie me habla','no tengo amigos','no tengo a nadie'],
        'frustracion' => ['frustrado','frustrada','harto','harta','nada sale bien','todo sale mal'],
        'cansancio' => ['cansado','cansada','agotado','agotada','no doy mas','no aguanto'],
        'alegria'  => ['feliz','contento','contenta','alegre','emocionado','emocionada','orgulloso','orgullosa']
    ];

    // ---------------------------
    // 2. Detectar riesgo (suicidio / autolesión)
    // ---------------------------
    $crisisPatternsHigh = [
        'me quiero morir',
        'no quiero vivir',
        'quitarme la vida',
        'quitarme mi vida',
        'matarme',
        'suicid', // suicidio, suicidarme, etc.
        'ojala no despertara',
        'ojala no me despertara',
        'no le veo sentido a la vida',
        'no tiene sentido vivir',
        'lastimarme de verdad',
        'hacerme dano de verdad',
        'cortarme profundo',
    ];

    $crisisPatternsMedium = [
        'no me importa si me pasa algo',
        'a veces quisiera desaparecer',
        'quisiera desaparecer',
        'solo quiero dormir y no despertar',
        'a nadie le importo',
        'siento que estorbo',
        'siento que sobro',
        'me quiero hacer dano',
        'quiero hacerme dano',
        'autolesion',
        'me corto',
        'me he cortado',
        'me he lastimado'
    ];

    $riskTriggers = [];
    foreach ($crisisPatternsHigh as $p) {
        if (mb_strpos($norm, $p) !== false) {
            $riskTriggers[] = $p;
            $analysis['risk'] = 'high';
        }
    }
    if ($analysis['risk'] !== 'high') {
        foreach ($crisisPatternsMedium as $p) {
            if (mb_strpos($norm, $p) !== false) {
                $riskTriggers[] = $p;
                if ($analysis['risk'] !== 'high') {
                    $analysis['risk'] = 'medium';
                }
            }
        }
    }

    if (!empty($riskTriggers)) {
        $analysis['risk_triggers'] = $riskTriggers;
        $analysis['needs_empathy'] = true;
        $analysis['needs_grounding'] = true;
    }

    // ---------------------------
    // 3. Sentimiento + intensidad
    // ---------------------------

    $score = 0;
    $hasPositive = false;
    $hasNegative = false;

    foreach ($positiveWords as $w) {
        if (mb_strpos($norm, $w) !== false) {
            $score += 2;
            $hasPositive = true;
        }
    }
    foreach ($negativeWords as $w) {
        if (mb_strpos($norm, $w) !== false) {
            $score -= 2;
            $hasNegative = true;
        }
    }

    // Intensificadores
    $intensityBoost = 0;
    foreach ($intensifiers as $w) {
        if (mb_strpos($norm, $w) !== false) {
            $intensityBoost += 1;
        }
    }
    foreach ($softeners as $w) {
        if (mb_strpos($norm, $w) !== false) {
            $intensityBoost -= 1;
        }
    }

    // Ajuste por longitud (mensajes largos tienden a ser más intensos emocionalmente)
    if ($wordCount > 40) {
        $intensityBoost += 1;
    }
    if ($wordCount > 80) {
        $intensityBoost += 1;
    }

    // Determinar sentimiento general
    $analysis['sentiment_score'] = $score;
    if ($score >= 2) {
        $analysis['sentiment'] = 'positive';
    } elseif ($score <= -2) {
        $analysis['sentiment'] = 'negative';
    } else {
        $analysis['sentiment'] = 'neutral';
    }

    // Necesidad de empatía
    if ($analysis['sentiment'] === 'negative' || $analysis['risk'] !== 'none') {
        $analysis['needs_empathy'] = true;
    }

    // Intensidad emocional basada en score + intensificadores
    $totalIntensity = abs($score) + max(0, $intensityBoost);
    if ($analysis['risk'] === 'high') {
        $analysis['emotional_intensity'] = 'high';
    } elseif ($analysis['risk'] === 'medium' || $totalIntensity >= 4) {
        $analysis['emotional_intensity'] = 'medium';
    } elseif ($totalIntensity >= 7) {
        $analysis['emotional_intensity'] = 'high';
    } else {
        $analysis['emotional_intensity'] = 'low';
    }

    // ---------------------------
    // 4. Detectar si es pregunta / pide consejo
    // ---------------------------
    if (
        mb_strpos($original, '?') !== false ||
        preg_match('/\b(c[oó]mo|como|qu[eé]|que|por qu[eé]|porque|para qu[eé]|cuando|cu[aá]ndo|d[oó]nde|donde|debo|puedo|deber[ií]a|que hago|qu[eé] hago)\b/iu', $original)
    ) {
        $analysis['is_question'] = true;
        $analysis['needs_advice'] = true;
    }

    // ---------------------------
    // 5. Detectar temas
    // ---------------------------
    foreach ($topicMap as $topic => $list) {
        foreach ($list as $kw) {
            if (mb_strpos($norm, $kw) !== false) {
                $analysis['topics'][] = $topic;
                break;
            }
        }
    }
    $analysis['topics'] = array_values(array_unique($analysis['topics']));

    // ---------------------------
    // 6. Detectar subtemas
    // ---------------------------
    foreach ($subtopicMap as $sub => $list) {
        foreach ($list as $kw) {
            if (mb_strpos($norm, $kw) !== false) {
                $analysis['subtopics'][] = $sub;
                break;
            }
        }
    }
    $analysis['subtopics'] = array_values(array_unique($analysis['subtopics']));

    // ---------------------------
    // 7. Detectar emociones específicas
    // ---------------------------
    $emotionFlags = [];
    foreach ($emotionWords as $label => $list) {
        foreach ($list as $kw) {
            if (mb_strpos($norm, $kw) !== false) {
                $emotionFlags[$label] = true;
                break;
            }
        }
    }
    // Si no detectamos pero el sentimiento es claramente negativo/positivo, inferimos algo
    if (empty($emotionFlags)) {
        if ($analysis['sentiment'] === 'negative') {
            // Inferir una emoción negativa genérica
            $emotionFlags['tristeza'] = true;
        } elseif ($analysis['sentiment'] === 'positive') {
            $emotionFlags['alegria'] = true;
        }
    }
    $analysis['emotions'] = array_keys($emotionFlags);

    // ---------------------------
    // 8. Detectar intención (vent, advice, celebrate...)
    // ---------------------------
    if ($analysis['is_question'] && $analysis['sentiment'] !== 'positive') {
        $analysis['intent'] = 'ask_advice';
    } elseif ($analysis['is_question'] && $analysis['sentiment'] === 'positive') {
        $analysis['intent'] = 'ask_advice';
    } elseif ($analysis['sentiment'] === 'positive' && (
        mb_strpos($norm, 'logre') !== false ||
        mb_strpos($norm, 'logro') !== false ||
        mb_strpos($norm, 'aprobe') !== false ||
        mb_strpos($norm, 'me fue bien') !== false ||
        mb_strpos($norm, 'ganar') !== false ||
        mb_strpos($norm, 'gane') !== false
    )) {
        $analysis['intent'] = 'celebrate';
    } elseif (
        $analysis['sentiment'] === 'negative' &&
        !$analysis['is_question']
    ) {
        $analysis['intent'] = 'vent'; // desahogarse
    } elseif (
        preg_match('/\b(gracias|solo queria contar|solo queria decir|te cuento que)\b/iu', $original)
    ) {
        $analysis['intent'] = 'share_update';
    } else {
        $analysis['intent'] = 'general';
    }

    return $analysis;
}

/**
 * Genera una respuesta en lenguaje natural en base al análisis del mensaje.
 */
function generateResponse(array $a, string $original): string {

    $sent      = $a['sentiment']           ?? 'neutral';
    $intent    = $a['intent']              ?? 'general';
    $topics    = $a['topics']              ?? [];
    $subs      = $a['subtopics']           ?? [];
    $emotions  = $a['emotions']            ?? [];
    $risk      = $a['risk']                ?? 'none';
    $intensity = $a['emotional_intensity'] ?? 'low';

    // ---------------------------
    // 1. Respuesta especial en caso de alto riesgo
    // ---------------------------
    if ($risk === 'high') {
        return generateCrisisResponseHigh($a, $original);
    }

    // Riesgo medio: mensaje muy cuidadoso
    if ($risk === 'medium') {
        return generateCrisisResponseMedium($a, $original);
    }

    // ---------------------------
    // 2. Mapas para hacer la respuesta más "humana"
    // ---------------------------

    $topicLabels = [
        'familia' => 'tu familia',
        'amigos'  => 'tus amistades',
        'pareja'  => 'tu relación de pareja',
        'escuela' => 'la escuela y los estudios',
        'trabajo' => 'tu trabajo o proyectos',
        'salud'   => 'tu salud y tu cuerpo',
        'futuro'  => 'tus planes a futuro',
        'emociones' => 'cómo te sientes por dentro',
        'dinero'  => 'el tema del dinero y los gastos'
    ];

    $emotionLabels = [
        'tristeza'   => 'tristeza',
        'ansiedad'   => 'ansiedad o nervios',
        'enojo'      => 'enojo o coraje',
        'miedo'      => 'miedo o preocupación',
        'soledad'    => 'sensación de soledad',
        'frustracion'=> 'frustración',
        'cansancio'  => 'cansancio',
        'alegria'    => 'alegría'
    ];

    // Frases base de empatía
    $empatheticOpeners = [
        "Gracias por animarte a escribir esto.",
        "Lo que compartes es importante, gracias por confiarlo.",
        "Valoro que pongas en palabras lo que estás sintiendo.",
        "No debe ser fácil hablar de esto, gracias por hacerlo.",
    ];

    $negativeEmpathy = [
        "Lamento que estés pasando por algo tan pesado.",
        "Suena muy difícil lo que estás viviendo.",
        "Entiendo que te puedas sentir así con todo lo que cuentas.",
        "No estás exagerando, tus emociones son válidas."
    ];

    $positiveOpeners = [
        "Me alegra mucho leer esto.",
        "Suena muy bonito lo que cuentas.",
        "Se nota que esto es importante para ti de forma positiva.",
        "Me da gusto que compartas algo bueno que está pasando."
    ];

    $neutralOpeners = [
        "Lo que comentas suena significativo para ti.",
        "Entiendo, es un tema que puede mover muchas cosas.",
        "Interesante lo que mencionas, vale la pena detenerse a verlo.",
    ];

    // Sugerencias de acciones pequeñitas y seguras
    $smallSteps = [
        "A veces ayuda escribir en una nota o cuaderno exactamente lo que sientes, sin censura.",
        "Pausar un momento, respirar profundo unas cuantas veces y notar qué pasa en tu cuerpo.",
        "Hablar con alguien de confianza (amigo, familiar, maestro) y contarle un poco de lo que estás viviendo.",
        "Dividir el problema en pasos más pequeños y enfocarte solo en el siguiente paso.",
        "Darte un ratito para hacer algo que te calme o te guste, aunque sea algo sencillo."
    ];

    // Preguntas de seguimiento
    $followUpQuestions = [
        "De todo lo que contaste, ¿qué es lo que más te está pesando ahora mismo?",
        "¿Qué te gustaría que fuera diferente en esta situación?",
        "¿Hay algo pequeño que podría hacer que tu día fuera un poco más llevadero?",
        "¿A quién podrías acercarte para pedir apoyo con esto?",
        "¿Te gustaría que pensemos juntos alguna forma concreta de cuidarte en esta situación?"
    ];

    // ---------------------------
    // 3. Construir frases según temas y emociones
    // ---------------------------
    $topicText = '';
    if (!empty($topics)) {
        $humanTopics = [];
        foreach ($topics as $t) {
            if (isset($topicLabels[$t])) {
                $humanTopics[] = $topicLabels[$t];
            }
        }
        if (!empty($humanTopics)) {
            $topicText = 'Por lo que dices, parece que esto tiene que ver con ' . joinWithCommaAndY($humanTopics) . '. ';
        }
    }

    $emotionText = '';
    if (!empty($emotions)) {
        $humanEmotions = [];
        foreach ($emotions as $e) {
            if (isset($emotionLabels[$e])) {
                $humanEmotions[] = $emotionLabels[$e];
            }
        }
        if (!empty($humanEmotions)) {
            $emotionText = 'Noto mucha ' . joinWithCommaAndY($humanEmotions) . ' en lo que cuentas. ';
        }
    }

    // ---------------------------
    // 4. Respuestas especiales por subtemas
    // ---------------------------
    if (!empty($subs)) {
        $sub = $subs[0]; // tomamos el más relevante
        if ($sub === 'bullying') {
            return
                "Lo que describes suena muy parecido a una situación de acoso o bullying, y eso nunca es tu culpa. " .
                "Que se burlen, te excluyan o te hagan sentir menos duele mucho y puede desgastar tu autoestima. " .
                "Sería importante que no lo enfrentes completamente solo/a. " .
                "Si puedes, intenta contar esto a un adulto de confianza (algún familiar, maestro, orientador) y describe con detalle qué pasa, cuándo y con quién. " .
                "También podría ayudar pensar en espacios o personas con las que sí te sientas más seguro/a. " .
                "Si quieres, puedes contarme un ejemplo concreto de lo último que pasó y pensamos juntos cómo podrías cuidarte un poco más.";
        } elseif ($sub === 'autoestima') {
            return
                "Veo que estás siendo muy duro/a contigo mismo/a, con ideas como que no vales o no sirves. " .
                "Esos pensamientos pegan muy fuerte y a veces no reflejan todo lo que realmente eres. " .
                "Una cosa que podría ayudar es identificar de dónde vienen esas frases: ¿son tuyas, o las escuchaste de alguien más? " .
                "Otra es preguntarte: si un buen amigo estuviera pasando por lo mismo, ¿le hablarías con las mismas palabras que te dices a ti? " .
                "Si te animas, cuéntame qué situación suele disparar más esos pensamientos y lo exploramos con calma.";
        } elseif ($sub === 'soledad') {
            return
                "La sensación de soledad puede sentirse muy pesada, sobre todo cuando parece que nadie entiende lo que te pasa. " .
                "El hecho de que lo escribas ya es una forma de buscar conexión, y eso habla de tu necesidad de ser escuchado/a. " .
                "Podría ser útil revisar si hay aunque sea una persona con la que te sientas un poquito más cómodo/a: alguien de tu casa, algún compañero, un maestro, un familiar. " .
                "A veces empezar con una conversación pequeña, sobre algo sencillo, abre la puerta a algo más profundo. " .
                "¿Hay alguien así en tu entorno con quien podrías intentar platicar un poco más?";
        } elseif ($sub === 'estres') {
            return
                "Suena a que llevas muchas cosas encima y el estrés se está acumulando. " .
                "Cuando hay tantas tareas, responsabilidades o preocupaciones, es fácil sentir que no se llega a todo. " .
                "Una estrategia puede ser escribir todo lo que tienes pendiente y luego elegir solo una o dos cosas pequeñas para hoy, en lugar de intentar abarcarlo todo. " .
                "También ayuda acordarse de hacer pausas cortas: levantarte, estirarte, tomar agua, respirar profundo. " .
                "Si quieres, dime qué es lo que más te está presionando y pensamos en un siguiente paso pequeño.";
        } elseif ($sub === 'motivacion') {
            return
                "Es comprensible sentir poca motivación cuando las cosas se sienten pesadas o repetitivas. " .
                "A veces no se trata de fuerza de voluntad, sino de cómo está tu energía, tus emociones y el contexto alrededor. " .
                "Puedes empezar preguntándote: ¿qué pequeña acción, muy sencilla, podría acercarme un poquito a lo que quiero, aunque no tenga muchas ganas? " .
                "Hacer acciones muy pequeñas y realistas suele ayudar a que la motivación venga después, no antes. " .
                "¿Qué te gustaría conseguir o mejorar a mediano plazo, aunque hoy te cueste arrancar?";
        } elseif ($sub === 'duelo') {
            return
                "Siento mucho que estés pasando por una pérdida. El duelo puede sentirse como una mezcla de tristeza, enojo, confusión y cansancio. " .
                "Cada persona vive ese proceso de forma diferente, y no hay una manera 'correcta' de sentir. " .
                "Algo que suele ayudar es permitirte recordar a esa persona o situación a tu propio ritmo, ya sea hablando, escribiendo o guardando algunos recuerdos significativos. " .
                "También es importante que no lo atravieses en soledad: hablar con alguien de confianza puede hacer que el peso se sienta un poco menos duro. " .
                "Si te parece, podrías contarme cómo ha cambiado tu día a día desde esa pérdida.";
        } elseif ($sub === 'ansiedad') {
            return
                "Lo que describes se parece mucho a momentos de ansiedad o nervios muy fuertes. " .
                "Cuando eso pasa, el cuerpo se acelera, cuesta concentrarse y los pensamientos se van a escenarios muy feos. " .
                "Una pequeña técnica que a veces ayuda es la de 5-4-3-2-1: mirar a tu alrededor y nombrar 5 cosas que ves, 4 que puedes tocar, 3 que escuchas, 2 que hueles y 1 que puedas saborear. " .
                "No soluciona todo, pero puede bajar un poco la intensidad del momento. " .
                "Si quieres, cuéntame cómo empiezan esas sensaciones en tu cuerpo, y exploramos juntos opciones para manejarlas mejor.";
        }
    }

    // ---------------------------
    // 5. Construir respuesta general según sentimiento + intención
    // ---------------------------
    $response = '';

    // A) Frase inicial según sentimiento
    if ($sent === 'positive') {
        $response .= chooseRandom($positiveOpeners) . ' ';
    } elseif ($sent === 'negative') {
        $response .= chooseRandom(array_merge($empatheticOpeners, $negativeEmpathy)) . ' ';
    } else { // neutral
        $response .= chooseRandom(array_merge($empatheticOpeners, $neutralOpeners)) . ' ';
    }

    // B) Añadir texto de temas y emociones
    $response .= $topicText . $emotionText;

    // C) Ajustar según intención
    if ($intent === 'celebrate') {
        $response .=
            "Se nota que esto es un logro para ti y vale la pena reconocerlo. " .
            "Tal vez podrías pausar un momento para darte crédito por lo que has hecho y pensar qué aprendiste en el camino. ";
        $response .= "Si quieres, cuéntame qué fue lo más difícil de lograr esto y cómo lo superaste. ";
    } elseif ($intent === 'ask_advice') {
        $response .=
            "Entiendo que estés buscando qué hacer o cómo manejar la situación. " .
            "Yo solo puedo ofrecerte ideas generales, no decisiones definitivas, pero podemos explorar algunas opciones juntos/as. ";
        $response .=
            "Podrías empezar por identificar qué sí está en tus manos cambiar y qué no. " .
            "Luego elegir un paso pequeño, realista, que puedas intentar en los próximos días. ";
    } elseif ($intent === 'vent') {
        $response .=
            "A veces lo que más necesitamos es justo esto: sacar todo lo que traemos dentro. " .
            "No siempre hace falta una solución inmediata; a veces el primer paso es que alguien escuche sin juzgar. ";
        $response .=
            "Si te sirve, podrías seguir contándome con un poco más de detalle qué ha pasado recientemente y qué es lo que más te dolio o molesto. ";
    } elseif ($intent === 'share_update') {
        $response .=
            "Parece que querías dejar registro de cómo te sientes o de lo que ha pasado, y eso también es valioso. " .
            "A veces mirar hacia atrás lo que hemos ido escribiendo nos ayuda a ver cambios o patrones que normalmente no notaríamos. ";
    } else { // general
        $response .=
            "Lo que estás viviendo tiene impacto en ti, y es comprensible que te muevan tantas cosas. ";
    }

    // D) Añadir sugerencia de pequeño paso si hay algo de malestar
    if ($sent !== 'positive' || $intent === 'ask_advice') {
        $response .= chooseRandom($smallSteps) . ' ';
    }

    // E) Añadir pregunta de seguimiento para continuar la conversación
    $response .= chooseRandom($followUpQuestions) . ' ';

    // F) Nota suave de límites del bot si la intensidad es media/alta
    if ($intensity !== 'low') {
        $response .=
            "Recuerda que soy solo un programa y lo que te digo es orientación general. " .
            "No reemplaza la ayuda de un profesional de la salud mental ni de un adulto de confianza, " .
            "así que si sientes que esto te sobrepasa, buscar apoyo cara a cara podría ser muy importante para ti.";
    }

    return $response;
}

// ==========================================================
// HELPERS ADICIONALES PARA LAS RESPUESTAS
// ==========================================================

/**
 * Une elementos como "a, b y c".
 */
function joinWithCommaAndY(array $items): string {
    $items = array_values(array_filter($items, function($i) { return trim($i) !== ''; }));
    $count = count($items);
    if ($count === 0) return '';
    if ($count === 1) return $items[0];
    if ($count === 2) return $items[0] . ' y ' . $items[1];
    $last = array_pop($items);
    return implode(', ', $items) . ' y ' . $last;
}

/**
 * Devuelve un elemento aleatorio de un arreglo (si está vacío, devuelve cadena vacía).
 */
function chooseRandom(array $arr): string {
    if (empty($arr)) return '';
    return $arr[array_rand($arr)];
}

/**
 * Respuesta cuando se detecta ALTO riesgo (frases suicidas directas).
 */
function generateCrisisResponseHigh(array $a, string $original): string {
    $msg  = "Lo que estás compartiendo es muy serio y me preocupa tu bienestar. ";
    $msg .= "Cuando aparecen pensamientos de querer morir, quitarse la vida o hacerse daño, es señal de que estás cargando con algo muy grande como para llevarlo solo/a. ";
    $msg .= "Yo soy solo un programa y no puedo ayudar en una emergencia, pero de verdad mereces apoyo real, de personas que puedan estar contigo. ";

    $msg .= "Si en este momento corres peligro o sientes que podrías lastimarte, trata de buscar ayuda urgente: ";
    $msg .= "habla con un adulto de confianza (mamá, papá, familiar, maestro, orientador) o comunícate con los servicios de emergencia o una línea de ayuda emocional en tu país. ";

    $msg .= "También podría ayudar que le muestres este mensaje a alguien cercano para que entienda cómo te estás sintiendo. ";
    $msg .= "No eres una carga por pedir ayuda: que te apoyen es justo lo que mereces en un momento así. ";

    $msg .= "Aquí podemos seguir hablando de cómo te sientes, pero es muy importante que también busques apoyo fuera de la pantalla. ";
    return $msg;
}

/**
 * Respuesta cuando se detecta riesgo MEDIO (deseos de desaparecer, hacerse daño, etc.).
 */
function generateCrisisResponseMedium(array $a, string $original): string {
    $msg  = "Gracias por confiar lo que estás sintiendo, se nota que lo estás pasando mal. ";
    $msg .= "Cuando aparecen ideas de desaparecer, de que no importa lo que te pase o de hacerte daño, es una señal de que el dolor emocional es muy fuerte. ";
    $msg .= "No quiero que lleves esto completamente solo/a. ";

    $msg .= "Aunque yo sea solo un programa, puedo decirte que es importante que alguien cercano sepa por lo que estás pasando. ";
    $msg .= "Podrías intentar hablar con algún adulto de confianza (un familiar, maestro, orientador, tutor) y decirle con claridad que no te estás sintiendo bien y que tienes pensamientos que te preocupan. ";
    $msg .= "Si te cuesta hablarlo, podrías escribirlo en un mensaje o nota y mostrárselo. ";

    $msg .= "Si en algún momento sientes que podría convertirse en una emergencia, por favor busca ayuda más directa: servicios de emergencia o líneas de ayuda emocional en tu país. ";
    $msg .= "Aquí podemos seguir platicando sobre lo que te pasa por dentro, pero no reemplazo la ayuda profesional ni la presencia de alguien a tu lado. ";

    return $msg;
}
