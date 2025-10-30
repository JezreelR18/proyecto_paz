(() => {
  const sel = (q, el = document) => el.querySelector(q);
  const elSelect = sel('#questionnaireSelect');
  const startBtn = sel('#startBtn');
  const area = sel('#assessmentArea');
  const form = sel('#quizForm');
  const header = sel('#quizHeader');
  const submitBtn = sel('#submitBtn');
  const cancelBtn = sel('#cancelBtn');
  const resultPanel = sel('#resultPanel');
  const scoresBox = sel('#scores');
  const badge = sel('#categoryBadge');
  const recoGrid = sel('#recoGrid');
  const newAttemptBtn = sel('#newAttemptBtn');

  let current = null; // cuestionario con preguntas

  // Load list
  async function loadQuestionnaires() {
    const r = await fetch('api/questionnaires.php?action=listActive');
    const data = await r.json();
    elSelect.innerHTML = '';
    if (data.ok && data.data?.length) {
      data.data.forEach(q => {
        const opt = document.createElement('option');
        opt.value = q.id_questionnaire;
        opt.textContent = q.title;
        elSelect.appendChild(opt);
      });
      startBtn.disabled = false;
    } else {
      const opt = document.createElement('option');
      opt.textContent = 'No hay cuestionarios activos';
      elSelect.appendChild(opt);
      startBtn.disabled = true;
    }
  }

  async function loadOne(id) {
    const r = await fetch(`api/questionnaires.php?action=get&id=${id}`);
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'No se pudo cargar el cuestionario');
    current = data.data;
    renderQuiz(current);
  }

  function renderQuiz(q) {
    area.hidden = false;
    resultPanel.hidden = true;
    header.innerHTML = `<h3>${q.title}</h3><p>${q.description || ''}</p>`;
    form.innerHTML = '';

    q.questions.sort((a,b) => a.question_order - b.question_order).forEach((x, idx) => {
      const card = document.createElement('div');
      card.className = 'q-card';
      card.dataset.qid = x.id_question;
      const title = document.createElement('div');
      title.className = 'q-title';
      title.textContent = `${idx+1}. ${x.question_text}`;
      card.appendChild(title);

      const optsWrap = document.createElement('div');
      optsWrap.className = 'q-options';

      if (x.question_type === 'likert_scale') {
        const scale = [
          {label:'Totalmente en desacuerdo', value:1},
          {label:'En desacuerdo', value:2},
          {label:'Neutral', value:3},
          {label:'De acuerdo', value:4},
          {label:'Totalmente de acuerdo', value:5},
        ];
        const row = document.createElement('div'); row.className = 'likert';
        scale.forEach(s => {
          const id = `q${x.id_question}_${s.value}`;
          const lab = document.createElement('label');
          lab.innerHTML = `<input type="radio" name="q_${x.id_question}" value="${s.value}" id="${id}" required> ${s.label}`;
          row.appendChild(lab);
        });
        optsWrap.appendChild(row);
      } else if (x.question_type === 'multiple_choice') {
        let options = [];
        try { options = JSON.parse(x.options||'[]'); } catch { options = []; }
        options.forEach((op, k) => {
          const lab = document.createElement('label');
          lab.className = 'opt';
          lab.innerHTML = `<input type="radio" name="q_${x.id_question}" value="${op.value ?? op.text ?? k}" required> ${op.text ?? op.label ?? String(op.value)}`;
          optsWrap.appendChild(lab);
        });
      } else {
        const ta = document.createElement('textarea');
        ta.className = 'q-open';
        ta.name = `q_${x.id_question}`;
        ta.placeholder = 'Escribe tu respuesta...';
        ta.required = true;
        optsWrap.appendChild(ta);
      }

      card.appendChild(optsWrap);
      form.appendChild(card);
    });
  }

  function collectAnswers() {
    const fd = new FormData(form);
    const answers = [];
    current.questions.forEach(q => {
      const name = `q_${q.id_question}`;
      const val = fd.get(name);
      if (val == null) return;
      answers.push({
        id_question: q.id_question,
        answer_value: String(val)
      });
    });
    return answers;
  }

  async function submitAnswers() {
    const answers = collectAnswers();
    const payload = {
      id_questionnaire: current.id_questionnaire,
      answers
    };
    const r = await fetch('api/questionnaires.php?action=submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'No se pudo enviar');
    showResult(data.data);
  }

  function showResult(result) {
    area.hidden = true;
    resultPanel.hidden = false;

    // Scores
    scoresBox.innerHTML = '';
    const items = [
      ['Total', result.total_score],
      ['Emocional', result.emotional_score],
      ['Estrés', result.stress_score],
      ['Conflicto', result.conflict_score],
      ['Autoconciencia', result.self_awareness_score],
    ];
    items.forEach(([label, val]) => {
      const box = document.createElement('div');
      box.className = 'score-box';
      box.innerHTML = `<strong>${label}</strong><div>${Number(val).toFixed(2)}</div>`;
      scoresBox.appendChild(box);
    });

    badge.textContent = `Tu estado: ${result.mental_state_category}`;
    badge.style.background = {
      'excelente':'#dcfce7',
      'bueno':'#e0f2fe',
      'regular':'#fef9c3',
      'necesita_mejora':'#fee2e2',
      'preocupante':'#fecaca'
    }[result.mental_state_category] || '#e5e7eb';

    // Recos
    recoGrid.innerHTML = '';
    (result.recommendations || []).forEach(r => {
      const card = document.createElement('div'); card.className = 'reco';
      card.innerHTML = `
        <h5>${r.title} <small>(${r.type_resource})</small></h5>
        <p>${r.description ?? ''}</p>
        <a href="${r.url}" target="_blank" rel="noopener">Abrir recurso</a>
        <div style="margin-top:6px; color:#666; font-size:12px">${r.recommendation_reason ?? ''}</div>
      `;
      recoGrid.appendChild(card);
    });
  }

  // Events
  startBtn.addEventListener('click', () => {
    const id = elSelect.value;
    if (id) loadOne(id).catch(err => alert(err.message));
  });
  submitBtn.addEventListener('click', (e) => {
    e.preventDefault();
    submitAnswers().catch(err => alert(err.message));
  });
  cancelBtn.addEventListener('click', () => {
    area.hidden = true; resultPanel.hidden = true;
  });
  newAttemptBtn.addEventListener('click', () => {
    resultPanel.hidden = true; area.hidden = false;
  });
  // ======== Mostrar cuestionarios respondidos ========

  async function loadUserStats() {
    const statsBox = document.getElementById('userStats');
    const totalEl = document.getElementById('totalAnswered');
    const listEl = document.getElementById('answeredList');

    try {
      const r = await fetch('api/questionnaires.php?action=userStats');
      console.log('LoadQUestionnaires', r);
      const data = await r.json();
      if (!data.ok) throw new Error(data.error || 'Error al consultar estadísticas');

      const { total, results } = data.data;
      totalEl.textContent = total ?? 0;
      listEl.innerHTML = '';

      if (results && results.length) {
        results.forEach(r => {
          const li = document.createElement('li');
          li.innerHTML = `
            <button class="as-link" data-id="${r.id_result}">
              <strong>${r.title}</strong>
              <span class="date">${new Date(r.completed_at).toLocaleDateString()}</span>
            </button>
          `;
          listEl.appendChild(li);
        });

        // delegación de eventos
        listEl.addEventListener('click', (e) => {
          const btn = e.target.closest('button.as-link');
          if (!btn) return;
          const id = btn.dataset.id;
          // navega a la vista de detalle (ajusta la ruta a tu router)
          window.location.href = `index.php?page=cuestionario_resultado&id_result=${encodeURIComponent(id)}`;
        }, { once: true });
      } else {
        listEl.innerHTML = '<li>No hay cuestionarios respondidos.</li>';
      }


    } catch (err) {
      console.error('[userStats] ', err);
    }
  }

  // Ejecuta junto con la carga inicial
  loadQuestionnaires().catch(console.error);
  loadUserStats().catch(console.error);



})();
