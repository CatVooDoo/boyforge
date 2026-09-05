(function () {
  'use strict';

  var canvas = document.getElementById('dropMatrix');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');

  var fontSize = 16;
  var columns, drops;

  function resize() {
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
    columns = Math.floor(canvas.width / fontSize);
    drops = [];
    for (var i = 0; i < columns; i++) {
      // случайный старт по вертикали, чтобы шло не одной волной
      drops[i] = Math.floor(Math.random() * -50);
    }
  }

  function draw() {
    // светлый полупрозрачный слой — оставляет "хвост" за цифрами
    ctx.fillStyle = 'rgba(244,244,244,0.10)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.fillStyle = 'rgba(0,0,0,0.55)';   // чёрные цифры
    ctx.font = fontSize + 'px monospace';

    for (var i = 0; i < drops.length; i++) {
      var char = Math.random() > 0.5 ? '0' : '1';
      var x = i * fontSize;
      var y = drops[i] * fontSize;
      ctx.fillText(char, x, y);

      // сброс столбца вниз с рандомом
      if (y > canvas.height && Math.random() > 0.975) {
        drops[i] = 0;
      }
      drops[i]++;
    }
  }

  resize();
  window.addEventListener('resize', resize);

  // ~20 fps — плавно и не грузит
  setInterval(draw, 50);
})();
