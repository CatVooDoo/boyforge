(function () {
  'use strict';

  // ⬇ ДАТА И ВРЕМЯ ОТКРЫТИЯ: год, месяц-1, день, час, минута
  // Сейчас стоит: 15 июля 2026, 20:00 (месяц июль = 6)
  var OPEN_AT = new Date(2026, 6, 15, 20, 0, 0).getTime();

  var lock = document.getElementById('dropLock');
  var content = document.getElementById('dropContent');
  var timer = document.getElementById('lockTimer');
  if (!lock || !content) return;

  function pad(n){ return n < 10 ? '0' + n : '' + n; }

  var iv;

  function tick() {
    var diff = OPEN_AT - Date.now();

    if (diff <= 0) {
      lock.hidden = true;
      content.hidden = false;
      if (iv) clearInterval(iv);
      return;
    }

    var h = Math.floor(diff / 3600000);
    var m = Math.floor((diff % 3600000) / 60000);
    var s = Math.floor((diff % 60000) / 1000);
    if (timer) timer.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
  }

  tick();
  iv = setInterval(tick, 1000);
})();
