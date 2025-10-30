<?php
// model/AssessmentService.php
declare(strict_types=1);

class AssessmentService {
  private PDO $db;
  public function __construct(PDO $db) { $this->db = $db; }

  /** Convierte una respuesta textual en puntaje numérico según tipo/opciones */
  private function scoreAnswer(array $question, string $answer): float {
    $type = $question['question_type'];
    $weight = (float)$question['weight'];
    $dim = $question['dimension'];

    if ($type === 'likert_scale') {
      $v = (float)$answer; // 1..5
      return $v * $weight;
    }

    if ($type === 'multiple_choice') {
      $opts = [];
      try { $opts = json_decode($question['options'] ?? '[]', true, 512, JSON_THROW_ON_ERROR); }
      catch (\Throwable $e) { $opts = []; }
      // Cada opción puede tener "value" y opcionalmente "score"
      foreach ($opts as $op) {
        $val = (string)($op['value'] ?? $op['text'] ?? '');
        if ($val === $answer) {
          $s = isset($op['score']) ? (float)$op['score'] : 0.0;
          return $s * $weight;
        }
      }
      return 0.0;
    }

    // open_ended -> sin puntaje directo
    return 0.0;
  }

  /** Calcula y persiste el resultado + retorna datos y recomendaciones (array) */
  public function calculateAndStore(
    int $userId, array $questionnaire, array $answers
  ): array {
    $qid = (int)$questionnaire['id_questionnaire'];
    $mapQ = [];
    foreach ($questionnaire['questions'] as $q) { $mapQ[(int)$q['id_question']] = $q; }

    $dimTotals = ['emotional'=>0.0,'stress'=>0.0,'conflict'=>0.0,'self_awareness'=>0.0];
    $dimMax    = ['emotional'=>0.0,'stress'=>0.0,'conflict'=>0.0,'self_awareness'=>0.0];
    $total = 0.0; $totalMax = 0.0;

    foreach ($answers as $a) {
      $iq = (int)$a['id_question'];
      if (!isset($mapQ[$iq])) continue;
      $q = $mapQ[$iq];

      $score = $this->scoreAnswer($q, (string)$a['answer_value']);
      $maxForQ = ((float)$q['weight']) * ($q['question_type']==='likert_scale' ? 5 : (isset($q['options']) ? $this->maxScoreFromOptions($q['options']) : 1));
      $dim = $q['dimension'];

      $dimTotals[$dim] += $score;
      $dimMax[$dim]    += $maxForQ;
      $total += $score; $totalMax += $maxForQ;
    }

    // Normalizados a 100
    $toPct = function(float $s, float $m): float { return $m > 0 ? round(($s/$m)*100, 2) : 0.0; };
    $em  = $toPct($dimTotals['emotional'], $dimMax['emotional']);
    $st  = $toPct($dimTotals['stress'], $dimMax['stress']);
    $co  = $toPct($dimTotals['conflict'], $dimMax['conflict']);
    $sa  = $toPct($dimTotals['self_awareness'], $dimMax['self_awareness']);
    $tot = $toPct($total, $totalMax);

    $category = $this->classify($tot, $st); // usa total y estrés como atenuante
    $reco = $this->recommendResources($qid, $em, $st, $co, $sa, $category);

    // guardamos resultado
    $this->db->beginTransaction();
    try {
      $ins = $this->db->prepare(
        "INSERT INTO assessment_results (id_user,id_questionnaire,total_score,emotional_score,stress_score,conflict_score,self_awareness_score,mental_state_category,recommendations,duration_minutes)
         VALUES (?,?,?,?,?,?,?,?,?,NULL)"
      );
      $ins->execute([$userId, $qid, $tot, $em, $st, $co, $sa, $category, json_encode($reco, JSON_UNESCAPED_UNICODE)]);

      $idResult = (int)$this->db->lastInsertId();

      if (!empty($reco)) {
        $stmt = $this->db->prepare("INSERT INTO result_recommendations (id_result,id_resource,recommendation_reason,priority_level) VALUES (?,?,?,?)");
        $prio = 1;
        foreach ($reco as $r) {
          $stmt->execute([$idResult, (int)$r['id_resource'], (string)($r['recommendation_reason'] ?? ''), $prio++]);
        }
      }

      $this->db->commit();
    } catch (\Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'id_questionnaire' => $qid,
      'total_score' => $tot,
      'emotional_score' => $em,
      'stress_score' => $st,
      'conflict_score' => $co,
      'self_awareness_score' => $sa,
      'mental_state_category' => $category,
      'recommendations' => $reco
    ];
  }

  private function maxScoreFromOptions(?string $json): float {
    try {
      $ops = json_decode($json ?? '[]', true, 512, JSON_THROW_ON_ERROR);
      $max = 0.0;
      foreach ($ops as $op) { $max = max($max, (float)($op['score'] ?? 0)); }
      return $max > 0 ? $max : 1.0;
    } catch (\Throwable $e) { return 1.0; }
  }

  private function classify(float $totalPct, float $stressPct): string {
    // Regla simple: estrés alto baja una categoría
    $base = match (true) {
      $totalPct >= 85 => 'excelente',
      $totalPct >= 70 => 'bueno',
      $totalPct >= 55 => 'regular',
      $totalPct >= 40 => 'necesita_mejora',
      default => 'preocupante'
    };
    if ($stressPct <= 40 && $base==='excelente') return 'bueno';
    if ($stressPct <= 35 && $base==='bueno') return 'regular';
    return $base;
  }

  /** Devuelve top 3 recursos en base a la dimensión más baja y categoría */
  private function recommendResources(
    int $idQuestionnaire, float $em, float $st, float $co, float $sa, string $category
  ): array {
    // dimensión “más necesitada” (menor porcentaje)
    $dimOrder = [
      'emotional' => $em,
      'stress' => $st,
      'conflict' => $co,
      'self_awareness' => $sa,
    ];
    asort($dimOrder); // menor a mayor
    $worstDim = array_key_first($dimOrder);

    // mapa de tipos sugeridos por dimensión
    $typeMap = [
      'emotional' => ['meditation','audio','article'],
      'stress' => ['breathing','exercise','video'],
      'conflict' => ['negotiation','article','guide'],
      'self_awareness' => ['reflection','journal','article'],
    ];
    $types = $typeMap[$worstDim] ?? ['article','video'];

    // busco recursos por tipo (y dejo el sistema libre por ahora del cuestionario)
    $in = implode(',', array_fill(0, count($types), '?'));
    $sql = "SELECT id_resource, title, type_resource, url, description
            FROM resources
            WHERE type_resource IN ($in)
            ORDER BY date_of_register DESC
            LIMIT 3";
    $stmt = $this->db->prepare($sql);
    $stmt->execute($types);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reason = match ($worstDim) {
      'stress' => 'Tu indicador de estrés fue el más bajo. Estos recursos ayudan a regularlo.',
      'conflict' => 'Tu indicador de manejo de conflictos fue el más bajo. Te sugerimos estas herramientas.',
      'self_awareness' => 'La autoconciencia requiere refuerzo. Te dejamos prácticas para desarrollarla.',
      default => 'Estos recursos refuerzan tu bienestar emocional.',
    };

    return array_map(function($r) use ($reason){
      return $r + ['recommendation_reason' => $reason];
    }, $rows);
  }
}
