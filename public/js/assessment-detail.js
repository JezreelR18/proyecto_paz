(() => {
  const params = new URLSearchParams(location.search);
  const id = params.get('id_result');

  const elTitle = document.getElementById('adTitle');
  const elDate  = document.getElementById('adDate');
  const elCat   = document.getElementById('adCategory');
  const elScores= document.getElementById('adScores');
  const elQA    = document.getElementById('adQA');
  const elReco  = document.getElementById('adReco');

  if (!id) {
    document.body.innerHTML = '<p style="padding:16px">Falta id_result</p>';
    return;
  }

  fetch(`api/questionnaires.php?action=resultDetail&id_result=${encodeURIComponent(id)}`)
    .then(r => r.json())
    .then(data => {
      if (!data.ok) throw new Error(data.error || 'No se pudo cargar el detalle');

      const { result, answers, recommendations } = data.data;

      elTitle.textContent = result.title;
      elDate.textContent = new Date(result.completed_at).toLocaleString();
      elCat.textContent = result.mental_state_category;

      const scores = [
        ['Total', result.total_score],
        ['Emocional', result.emotional_score],
        ['Estrés', result.stress_score],
        ['Conflicto', result.conflict_score],
        ['Autoconciencia', result.self_awareness_score],
      ];
      elScores.innerHTML = '';
      scores.forEach(([label, val]) => {
        const div = document.createElement('div');
        div.className = 'score-box';
        div.innerHTML = `<strong>${label}</strong><div>${Number(val).toFixed(2)}</div>`;
        elScores.appendChild(div);
      });

      elQA.innerHTML = '';
      answers.forEach((row, i) => {
        const card = document.createElement('div');
        card.className = 'qa-card';
        card.innerHTML = `
          <div class="q">${row.question_order}. ${row.question_text}</div>
          <div class="a">${escapeHtml(formatAnswer(row))}</div>
        `;
        elQA.appendChild(card);
      });

      elReco.innerHTML = '';
      (recommendations || []).forEach(r => {
        const card = document.createElement('div'); card.className = 'reco';
        card.innerHTML = `
          <h5>${r.title} <small>(${r.type_resource})</small></h5>
          <p>${r.description ?? ''}</p>
          <a href="${r.url}" target="_blank" rel="noopener">Abrir recurso</a>
          <div style="margin-top:6px; color:#666; font-size:12px">${r.recommendation_reason ?? ''}</div>
        `;
        elReco.appendChild(card);
      });
    })
    .catch(err => {
      document.body.innerHTML = `<p style="padding:16px">Error: ${err.message}</p>`;
    });

  function formatAnswer(row) {
    // para likert devolvemos número 1–5; para abiertas mostramos tal cual
    if (row.question_type === 'likert_scale') return row.answer_value ?? '(sin respuesta)';
    return row.answer_value ?? '(sin respuesta)';
  }
  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }
})();
