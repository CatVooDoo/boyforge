/* ============================================================
   BOYFORGE — основной скрипт сайта
   Минимальный набор: меню, форма, аккордеон, галерея, мелочи
   ============================================================ */
(function () {
  'use strict';

  /* ---------- утилиты ---------- */
  const $  = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const on = (el, ev, fn) => el && el.addEventListener(ev, fn);


/* ===== МОБИЛЬНОЕ МЕНЮ ===== */
(function () {
  const burger  = document.querySelector('.burger');
  const menu    = document.querySelector('.mobile-menu');
  const overlay = document.querySelector('.menu-overlay, .overlay');
  const closeBtn = document.querySelector('.menu-close');
  if (!burger || !menu) return;

  function openMenu() {
    menu.classList.add('open');
    if (overlay) { overlay.hidden = false; overlay.classList.add('open'); }
    document.body.classList.add('no-scroll');
    burger.setAttribute('aria-expanded', 'true');
    menu.setAttribute('aria-hidden', 'false');
  }

  function closeMenu() {
    menu.classList.remove('open');
    if (overlay) {
      overlay.classList.remove('open');
      // прячем оверлей после завершения анимации
      setTimeout(() => { overlay.hidden = true; }, 350);
    }
    document.body.classList.remove('no-scroll');
    burger.setAttribute('aria-expanded', 'false');
    menu.setAttribute('aria-hidden', 'true');
  }

  burger.addEventListener('click', () => {
    menu.classList.contains('open') ? closeMenu() : openMenu();
  });

  if (closeBtn) closeBtn.addEventListener('click', closeMenu);

  // клик по пустому месту (оверлей)
  if (overlay) overlay.addEventListener('click', closeMenu);

  // закрытие по ссылке в меню
  menu.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', closeMenu);
  });

  // закрытие по Esc
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && menu.classList.contains('open')) closeMenu();
  });
})();


  /* ---------- 3. Форма заявки в Telegram ---------- */
  (function contactForm() {
    const form = $('#contactForm');
    if (!form) return;

    const status = $('#formStatus');
    const setStatus = (msg, ok) => {
      if (!status) return;
      status.textContent = msg;
      status.className = 'form-status ' + (ok ? 'is-ok' : 'is-err');
    };

    on(form, 'submit', function (e) {
      e.preventDefault();

      const tg  = ($('#tgName', form)?.value || '').trim();
      const msg = ($('#question', form)?.value || '').trim();

      if (!tg) { setStatus('Укажите ваш ник в Telegram', false); return; }
      if (!msg) { setStatus('Напишите ваш вопрос', false); return; }

      const btn = $('button[type="submit"]', form);
      btn && (btn.disabled = true, btn.dataset.txt = btn.textContent, btn.textContent = 'Отправляем…');

      // TODO: подключить реальную отправку (Telegram-бот / Formspree / почта).
      // Пока — имитация успешной отправки:
      setTimeout(() => {
        setStatus('Заявка отправлена! Мы свяжемся с вами в Telegram.', true);
        form.reset();
        if (btn) { btn.disabled = false; btn.textContent = btn.dataset.txt; }
      }, 700);
    });
  })();


  /* ---------- 5. Галерея на карточке товара ---------- */
  (function gallery() {
    const main  = $('#galleryMain');
    const thumbs = $$('.gallery-thumb');
    if (!main || !thumbs.length) return;

    thumbs.forEach(thumb => {
      on(thumb, 'click', () => {
        const src = thumb.dataset.full || thumb.querySelector('img')?.src;
        if (src) main.src = src;
        thumbs.forEach(t => t.classList.remove('is-active'));
        thumb.classList.add('is-active');
      });
    });
  })();


  /* ---------- 6. Выбор размера на карточке товара ---------- */
  (function sizePicker() {
    const sizes = $$('.size-option');
    if (!sizes.length) return;
    const hidden = $('#selectedSize');

    sizes.forEach(btn => {
      on(btn, 'click', () => {
        if (btn.classList.contains('is-disabled')) return;
        sizes.forEach(s => s.classList.remove('is-selected'));
        btn.classList.add('is-selected');
        hidden && (hidden.value = btn.dataset.size || btn.textContent.trim());
      });
    });
  })();


  /* ---------- 7. Плавная прокрутка по якорям ---------- */
  (function smoothAnchors() {

    $$('a[href^="#"]').forEach(a => {
      on(a, 'click', e => {
        const id = a.getAttribute('href');
        if (id.length < 2) return;
        const target = document.querySelector(id);
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  })();


  /* ---------- 8. Появление блоков при скролле (.reveal) ---------- */
  (function reveal() {
    const els = $$('.reveal');
    if (!els.length || !('IntersectionObserver' in window)) {
      els.forEach(el => el.classList.add('is-visible'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    els.forEach(el => io.observe(el));
  })();


  /* ---------- 9. Год в футере ---------- */
  (function year() {
    const el = $('#year');
    el && (el.textContent = new Date().getFullYear());
  })();

})();

  /* ---------- Прозрачный хедер → белая плашка (без дёрганья) ---------- */
(function headerScroll() {
  const header = document.querySelector('.site-header');
  if (!header) return;

  const isTransparent = header.classList.contains('header-transparent');
  let ticking = false;
  let lastState = false;

  function compute() {
    ticking = false;
    const y = window.scrollY;
    // гистерезис: включаем на 70% экрана, выключаем только на 55% —
    // между ними класс не мигает
    const onPoint  = isTransparent ? window.innerHeight * 0.70 : 8;
    const offPoint = isTransparent ? window.innerHeight * 0.55 : 4;

    let next = lastState;
    if (!lastState && y > onPoint)  next = true;
    if (lastState  && y < offPoint) next = false;

    if (next !== lastState) {
      lastState = next;
      header.classList.toggle('scrolled', next);
    }
  }

  function onScroll() {
    if (!ticking) {
      window.requestAnimationFrame(compute);
      ticking = true;
    }
  }

  compute();
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll, { passive: true });
})();

   /* ============================================================
   HERO-СЛАЙДЕР (автопереключение изображений)
   ============================================================ */
(function () {
  const slider = document.querySelector('.hero-slider');
  if (!slider) return;

  const slides = [...slider.querySelectorAll('.hero-slide')];
  const dotsWrap = slider.querySelector('.hero-dots');
  const prevBtn = slider.querySelector('.hero-prev');
  const nextBtn = slider.querySelector('.hero-next');
  let index = 0;
  let timerId;

  // точки
  slides.forEach((_, i) => {
    const dot = document.createElement('button');
    dot.setAttribute('aria-label', 'Слайд ' + (i + 1));
    if (i === 0) dot.classList.add('is-active');
    dot.addEventListener('click', () => go(i));
    dotsWrap.appendChild(dot);
  });
  const dots = [...dotsWrap.children];

  function startTimer() {
    clearInterval(timerId);
    timerId = setInterval(() => next(), 5000);
  }

  function go(i) {
    slides[index].classList.remove('is-active');
    dots[index].classList.remove('is-active');
    index = (i + slides.length) % slides.length;
    slides[index].classList.add('is-active');
    dots[index].classList.add('is-active');
    startTimer();
  }
  
  const next = () => go(index + 1);
  const prev = () => go(index - 1);

  nextBtn && nextBtn.addEventListener('click', next);
  prevBtn && prevBtn.addEventListener('click', prev);

  // свайп на мобиле
  let startX = 0;
  let isDragging = false;

  slider.addEventListener('touchstart', e => {
    isDragging = false;
    startX = e.touches[0].clientX;
    clearInterval(timerId);
  }, { passive: true });

  slider.addEventListener('touchmove', e => {
    isDragging = true;
  }, { passive: true });

  slider.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) {
      if (dx < 0) next(); else prev();
    } else {
      isDragging = false;
      startTimer();
    }
  }, { passive: true });

  // Предотвращаем переход по ссылке, если был свайп
  slides.forEach(slide => {
    slide.addEventListener('click', e => {
      if (isDragging) {
        e.preventDefault();
      }
    });
  });

  // старт
  startTimer();
})();


/* ============================================================
   ГАЛЕРЕЯ (авто-marquee) + МОДАЛКА
   ============================================================ */
(function () {
  const marquee = document.getElementById('galleryMarquee');
  const track = document.getElementById('galleryTrack');
  const modal = document.getElementById('galleryModal');
  if (!marquee || !track || !modal) return;

  // собираем список src ДО дублирования
  const originals = [...track.querySelectorAll('.gallery-item')];
  const sources = originals.map(el => el.dataset.src);

  // дублируем содержимое для бесшовной прокрутки (-50%)
  track.innerHTML += track.innerHTML;

  // пауза при наведении / касании (удержании)
  marquee.addEventListener('mouseenter', () => marquee.classList.add('is-paused'));
  marquee.addEventListener('mouseleave', () => marquee.classList.remove('is-paused'));
  marquee.addEventListener('touchstart', () => marquee.classList.add('is-paused'), { passive: true });
  marquee.addEventListener('touchend', () => marquee.classList.remove('is-paused'), { passive: true });

  // модалка
  const modalImg = document.getElementById('galleryModalImg');
  const closeBtn = modal.querySelector('.gallery-modal-close');
  const prevBtn = modal.querySelector('.gm-prev');
  const nextBtn = modal.querySelector('.gm-next');
  let current = 0;

  function open(i) {
    current = (i + sources.length) % sources.length;
    modalImg.src = sources[current];
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
  }
  function close() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
  }
  const showPrev = () => open(current - 1);
  const showNext = () => open(current + 1);

  // клик по любой плитке (в т.ч. по дублям) открывает модалку
  track.addEventListener('click', e => {
    const item = e.target.closest('.gallery-item');
    if (!item) return;
    const idx = sources.indexOf(item.dataset.src);
    open(idx < 0 ? 0 : idx);
  });

  closeBtn.addEventListener('click', close);
  prevBtn.addEventListener('click', showPrev);
  nextBtn.addEventListener('click', showNext);
  modal.addEventListener('click', e => { if (e.target === modal) close(); });
  document.addEventListener('keydown', e => {
    if (!modal.classList.contains('is-open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') showPrev();
    if (e.key === 'ArrowRight') showNext();
  });
})();

/* ===== КАТАЛОГ: фильтры + сортировка ===== */
(function () {
  const grid = document.getElementById('catalogGrid');
  if (!grid) return;

  const chips = document.querySelectorAll('.chip');
  const sortSelect = document.getElementById('sortSelect');
  const countEl = document.getElementById('catalogCount');
  const emptyState = document.getElementById('emptyState');
  const emptyBack = document.getElementById('emptyBack');
  const cards = Array.from(grid.querySelectorAll('.card'));

  function plural(n) {
    const a = Math.abs(n) % 100, b = n % 10;
    if (a > 10 && a < 20) return 'товаров';
    if (b > 1 && b < 5) return 'товара';
    if (b === 1) return 'товар';
    return 'товаров';
  }

  function apply() {
    const activeChip = document.querySelector('.chip.is-active');
    const filter = activeChip ? activeChip.dataset.cat : 'all';
    const sort = sortSelect ? sortSelect.value : 'pop';

    let visible = cards.filter(c => filter === 'all' || c.dataset.cat === filter);

    if (sort === 'cheap') visible.sort((a, b) => a.dataset.price - b.dataset.price);
    if (sort === 'exp')   visible.sort((a, b) => b.dataset.price - a.dataset.price);
    if (sort === 'new')   visible.sort((a, b) => b.dataset.order - a.dataset.order);
    if (sort === 'pop')   visible.sort((a, b) => a.dataset.order - b.dataset.order);

    cards.forEach(c => { c.style.display = 'none'; });
    visible.forEach(c => { c.style.display = ''; grid.appendChild(c); });

    if (countEl) countEl.textContent = visible.length + ' ' + plural(visible.length);
    if (emptyState) emptyState.hidden = visible.length !== 0;
  }

  chips.forEach(chip => chip.addEventListener('click', () => {
    chips.forEach(c => c.classList.remove('is-active'));
    chip.classList.add('is-active');
    apply();
  }));

  if (sortSelect) sortSelect.addEventListener('change', apply);

  // кнопка «Сбросить фильтр» в пустом состоянии
  if (emptyBack) emptyBack.addEventListener('click', () => {
    chips.forEach(c => c.classList.remove('is-active'));
    const allChip = document.querySelector('.chip[data-cat="all"]');
    if (allChip) allChip.classList.add('is-active');
    apply();
  });

  apply(); // первый запуск — покажет все товары
})();


/* ===== СТРАНИЦА ТОВАРА ===== */
(function () {
  // смена главного фото по клику на миниатюру
  const main = document.getElementById('galleryMain');
  const thumbs = document.querySelectorAll('.thumbs img');
  if (main && thumbs.length) {
    thumbs.forEach(t => t.addEventListener('click', () => {
      main.src = t.src;
      thumbs.forEach(x => x.classList.remove('active'));
      t.classList.add('active');
    }));
  }

  // выбор размера (недоступные размеры отмечены disabled — их пропускаем)
  document.querySelectorAll('.sizes button:not([disabled])').forEach(b => {
    b.addEventListener('click', () => {
      document.querySelectorAll('.sizes button').forEach(x => x.classList.remove('active'));
      b.classList.add('active');
    });
  });

  // аккордеон (делегирование — работает всегда)
  document.addEventListener('click', function (e) {
    var head = e.target.closest('.accordion-head');
    if (!head) return;
    var item = head.closest('.accordion-item');
    if (item) item.classList.toggle('open');
  });
})();

/* ===== МОДАЛКА ОТЗЫВОВ ===== */
(function () {
  const track = document.getElementById('reviewsTrack');
  const modal = document.getElementById('reviewModal');
  if (!track || !modal) return;

  const mImg  = document.getElementById('reviewModalImg');
  const mText = document.getElementById('reviewModalText');
  const mName = document.getElementById('reviewModalName');
  const mItem = document.getElementById('reviewModalItem');
  const closeBtn = modal.querySelector('.review-modal-close');

  track.querySelectorAll('.review-card').forEach(card => {
    card.addEventListener('click', () => {
      mImg.src = card.dataset.img || '';
      mText.textContent = card.dataset.text || '';
      mName.textContent = card.dataset.name || '';
      mItem.textContent = card.dataset.item || '';
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('no-scroll');
    });
  });

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
  }
  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
})();
