/* Reviews page — modal + fallback initials */
(function () {
  // подставляем инициалы в fallback карточек
  document.querySelectorAll('.rv-card').forEach(function (card) {
    var name = card.getAttribute('data-name') || '';
    var img = card.getAttribute('data-img');
    var fb = card.querySelector('.rv-media-fallback');
    if (fb) fb.textContent = name.charAt(0).toUpperCase();
    // если фото не указано — сразу показываем инициал
    var media = card.querySelector('.rv-media');
    if (media && (!img || img.trim() === '')) {
      media.classList.add('is-empty');
    }
  });

  var modal = document.getElementById('reviewModal');
  if (!modal) return;

  var mImg = document.getElementById('reviewModalImg');
  var mMedia = document.getElementById('reviewModalMedia');
  var mFallback = document.getElementById('reviewModalFallback');
  var mText = document.getElementById('reviewModalText');
  var mName = document.getElementById('reviewModalName');
  var mItem = document.getElementById('reviewModalItem');

  function openModal(card) {
    var name = card.getAttribute('data-name') || '';
    var item = card.getAttribute('data-item') || '';
    var text = card.getAttribute('data-text') || '';
    var img = card.getAttribute('data-img') || '';

    mText.textContent = text;
    mName.textContent = name;
    mItem.textContent = item;
    mFallback.textContent = name.charAt(0).toUpperCase();

    mMedia.classList.remove('is-empty');
    if (img && img.trim() !== '') {
      mImg.src = img;
    } else {
      mImg.removeAttribute('src');
      mMedia.classList.add('is-empty');
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.rv-card').forEach(function (card) {
    card.addEventListener('click', function () { openModal(card); });
  });

  modal.querySelectorAll('[data-close]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });
})();
