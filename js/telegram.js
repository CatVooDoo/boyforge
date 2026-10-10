document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('contactForm');
  if (!form) return;

  const TOKEN = '8810938459:AAHLtTmTd7vlkFgDuA-ty1SBD6JVK4aNHxY';
  const CHAT_ID = '-1003895656779';

  const tgInput = document.getElementById('tgName');
  const qInput = document.getElementById('question');
  const statusEl = document.getElementById('formStatus');
  const submitBtn = form.querySelector('button[type="submit"]');
  const btnDefaultText = submitBtn ? submitBtn.textContent : 'Отправить';

  function showMessage(text, type) {
    if (!statusEl) { if (text) alert(text); return; }
    statusEl.textContent = text || '';
    statusEl.className = 'form-status' + (type ? ' ' + (type === 'success' ? 'is-ok' : 'is-err') : '');
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function isNickValid() {
    const v = (tgInput?.value || '').trim();
    return /@.+/.test(v);
  }

  function refreshBtnState() {
    if (submitBtn) submitBtn.disabled = !isNickValid();
  }

  if (tgInput) {
    tgInput.addEventListener('input', function () {
      refreshBtnState();
      const v = tgInput.value.trim();
      if (v && !isNickValid()) {
        showMessage('Ник должен содержать @ (например, @username)', 'error');
      } else {
        showMessage('', null);
      }
    });
  }
  refreshBtnState();

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const tg = (tgInput?.value || '').trim();
    const question = (qInput?.value || '').trim();

    if (!isNickValid()) { showMessage('Укажите ник в Telegram с символом @ (например, @username)', 'error'); return; }
    if (!question) { showMessage('Напишите ваш вопрос', 'error'); return; }

    const message =
      '<b>Новый вопрос с сайта BOYFORGE</b>\n\n' +
      '<b>Telegram:</b> ' + escapeHtml(tg) + '\n' +
      '<b>Вопрос:</b> ' + escapeHtml(question);

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Отправляем…';
    }
    showMessage('Отправляем…', 'success');

    fetch('https://api.telegram.org/bot' + TOKEN + '/sendMessage', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chat_id: CHAT_ID,
        text: message,
        parse_mode: 'HTML'
      })
    })
      .then(res => res.json())
      .then(data => {
        if (data.ok) {
          showMessage('Спасибо! Ваш вопрос отправлен — ответим в Telegram.', 'success');
          form.reset();
        } else {
          showMessage('Не удалось отправить. Попробуйте позже.', 'error');
        }
      })
      .catch(() => {
        showMessage('Ошибка соединения. Проверьте интернет и попробуйте снова.', 'error');
      })
      .finally(() => {
        if (submitBtn) {
          submitBtn.textContent = btnDefaultText;
        }
        refreshBtnState();
      });
  });
});
