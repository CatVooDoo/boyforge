"use strict";

function filterLogs(type, btn) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const entries = document.querySelectorAll('.log-entry');
  entries.forEach(e => {
    if (type === 'all' || e.classList.contains('log-entry-' + type)) {
      e.style.display = 'block';
    } else {
      e.style.display = 'none';
    }
  });
}

function copyLogEntry(btn) {
  const pre = btn.closest('.log-entry')?.querySelector('pre');
  if (pre) {
    navigator.clipboard.writeText(pre.innerText).then(() => {
      const orig = btn.innerText;
      btn.innerText = 'Скопировано!';
      setTimeout(() => btn.innerText = orig, 1500);
    });
  }
}

let refreshTimer = null;
function toggleAutoRefresh(checkbox) {
  if (checkbox.checked) {
    refreshTimer = setInterval(() => {
      window.location.reload();
    }, 10000);
  } else if (refreshTimer) {
    clearInterval(refreshTimer);
    refreshTimer = null;
  }
}
