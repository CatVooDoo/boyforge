<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Отзывы — BOYFORGE</title>
  <meta name="description" content="Отзывы покупателей BOYFORGE — кузницы стильной одежды.">
  <link rel="icon" href="images/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=19">
</head>
<body>

  <!-- ===== HEADER ===== -->
  <header class="site-header">
    <div class="container header-inner">
      <a href="index.php" class="logo-photo" aria-label="BOYFORGE — на главную">
        <img class="logo-dark" src="images/logo.png" alt="BOYFORGE"
             onerror="this.replaceWith(document.createTextNode('BOYFORGE'))">
      </a>
      <button class="burger" aria-label="Открыть меню" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <!-- ===== SIDE MENU ===== -->
  <div class="menu-overlay overlay" hidden></div>
  <nav class="mobile-menu" aria-hidden="true" aria-label="Основное меню">
    <button class="menu-close" aria-label="Закрыть меню">&times;</button>

    <div class="menu-group">
      <a href="index.php">Главная</a>
      <a href="catalog.php">Каталог</a>
    </div>

    <span class="menu-divider" aria-hidden="true"></span>

    <div class="menu-group">
      <a href="delivery.php">Доставка</a>
      <a href="payment.php">Оплата</a>
      <a href="returns.php">Возврат</a>
    </div>

    <span class="menu-divider" aria-hidden="true"></span>

    <div class="menu-group">
      <a href="about.php">О бренде</a>
      <a href="reviews.php">Отзывы</a>
      <a href="contacts.php">Контакты</a>
    </div>

  </nav>

  <main>

    <!-- ===== HERO ===== -->
    <section class="rv-hero">
      <div class="container">
        <div class="rv-eyebrow reveal">Отзывы</div>
        <h1 class="rv-title reveal">Что говорят<br>о BOYFORGE</h1>
      </div>
    </section>

    <!-- хлебные крошки -->
    <div class="container">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="index.php">Главная</a> <span>/</span> <span>Отзывы</span>
      </nav>
    </div>

    <!-- ===== СЕТКА ОТЗЫВОВ ===== -->
    <section class="rv-section">
      <div class="container">
        <div class="rv-head reveal">
          <h2>Отзывы покупателей</h2>
          <div class="rv-sub">Нажмите на отзыв, чтобы открыть его полностью</div>
        </div>

        <div class="rv-grid" id="reviewsGrid">

          <!-- Чтобы добавить фото: впишите путь в data-img И в src внутри .rv-media img.
               Если фото нет — оставьте data-img пустым, покажется инициал. -->


          <button class="rv-card" data-name="Alina" data-item="Варяга меч кормит" data-img="images/review-11.jpg"
                  data-text="Добрый вечер, забрала футболку. Спасибо большое, все довольны 😍😍😍">
            <div class="rv-media">
              <img src="images/review-11.jpg" alt="Отзыв Alina" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">Р</span>
            </div>
            <p class="rv-text">Добрый вечер, забрала футболку. Спасибо большое, все довольны 😍😍😍</p>
            <div class="rv-meta"><span class="rv-name">Alina</span><span class="rv-item">Варяга меч кормит</span></div>
          </button>

          <button class="rv-card" data-name="Di" data-item="Варяга меч кормит" data-img="images/review-12.jpg"
                  data-text="Добрый вечер, забрали товар, отличное качество, приятная к телу! Обязательно вернусь,повторно за заказом 🥰🙏🏽">
            <div class="rv-media">
              <img src="images/review-12.jpg" alt="Отзыв Di" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">В</span>
            </div>
            <p class="rv-text">Добрый вечер, забрали товар, отличное качество, приятная к телу! Обязательно вернусь,повторно за заказом 🥰🙏🏽</p>
            <div class="rv-meta"><span class="rv-name">Di</span><span class="rv-item">Варяга меч кормит</span></div>
          </button>
          
          <button class="rv-card" data-name="Варвара" data-item="Варяга меч кормит" data-img="images/review-13.jpg"
                  data-text="Здравствуйте. футболку получила, презентовала её мужу Рисунок оч понравился и говорит, что качество кайф. Спасибо вам за такую красоту">
            <div class="rv-media">
              <img src="images/review-13.jpg" alt="Отзыв Варвары" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">В</span>
            </div>
            <p class="rv-text">Здравствуйте. футболку получила, презентовала её мужу Рисунок оч понравился и говорит, что качество кайф. Спасибо вам за такую красоту</p>
            <div class="rv-meta"><span class="rv-name">Варвара</span><span class="rv-item">Варяга меч кормит</span></div>
          </button>
                    
          <button class="rv-card" data-name="Алёна" data-item="Варяга меч кормит" data-img="images/review-13.jpg"
                  data-text="Здравствуйте. Получила футболку, все супер, качество вообще отпад! 😍">
            <div class="rv-media">
              <img src="images/review-14.jpg" alt="Отзыв Алёны" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">В</span>
            </div>
            <p class="rv-text">Здравствуйте. Получила футболку, все супер, качество вообще отпад! 😍</p>
            <div class="rv-meta"><span class="rv-name">Алёна</span><span class="rv-item">Варяга меч кормит</span></div>
          </button>

          <button class="rv-card" data-name="Валерия" data-item="Князь Владимир III" data-img="images/review-1.jpg"
                  data-text="Наконец-то дошли руки сфоткать подарок и написать вам. Спасибо большое, муж в восторге, так как обожает этот мультик. Сейчас поехал на работу в ней">
            <div class="rv-media">
              <img src="images/review-1.jpg" alt="Отзыв Валерии" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">В</span>
            </div>
            <p class="rv-text">Наконец-то дошли руки сфоткать подарок и написать вам. Спасибо большое, муж в восторге, так как обожает этот мультик. Сейчас поехал на работу в ней.</p>
            <div class="rv-meta"><span class="rv-name">Валерия</span><span class="rv-item">Князь Владимир III</span></div>
          </button>

          <button class="rv-card" data-name="svmysoull" data-item="Князь Владимир III" data-img="images/review-2.jpg"
                  data-text="здравствуйте! спасибо вам большое за такие прекрасные толстовки, покупала сестре в подарок, ей очень понравились!!">
            <div class="rv-media">
              <img src="images/review-2.jpg" alt="Отзыв svmysoull" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">А</span>
            </div>
            <p class="rv-text">здравствуйте! спасибо вам большое за такие прекрасные толстовки, покупала сестре в подарок, ей очень понравились!!</p>
            <div class="rv-meta"><span class="rv-name">svmysoull</span><span class="rv-item">Князь Владимир III</span></div>
          </button>

          <button class="rv-card" data-name="Кристина" data-item="Князь Владимир II" data-img="images/review-3.jpg"
                  data-text="Здравствуйте 💛 Обещала вам фото, сегодня только первый раз удалось выгулять вещичку! Спасибо большое за отличное качество и прекрасную задумку🙏">
            <div class="rv-media">
              <img src="images/review-3.jpg" alt="Отзыв Кристина" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">А</span>
            </div>
            <p class="rv-text">Здравствуйте 💛 Обещала вам фото, сегодня только первый раз удалось выгулять вещичку! Спасибо большое за отличное качество и прекрасную задумку🙏</p>
            <div class="rv-meta"><span class="rv-name">Кристина</span><span class="rv-item">Князь Владимир II</span></div>
          </button>

          <button class="rv-card" data-name="Дарья" data-item="Несколько" data-img="images/review-4.jpg"
                  data-text="Добрый вечер!!! Они супер, часто носим с подругой, даже сфотографировались в них (прикрепляю фото) Очень мягкий, приятный к телу, печать - просто пушка бомба, качество! Спасибо!">
            <div class="rv-media">
              <img src="images/review-4.jpg" alt="Отзыв Дарьи" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">Е</span>
            </div>
            <p class="rv-text">Добрый вечер!!! Они супер, часто носим с подругой, даже сфотографировались в них (прикрепляю фото) Очень мягкий, приятный к телу, печать - просто пушка бомба, качество! Спасибо!</p>
            <div class="rv-meta"><span class="rv-name">Дарья</span><span class="rv-item">Несколько</span></div>
          </button>

          <button class="rv-card" data-name="сончоус" data-item="Князь Владимир I" data-img="images/review-5.jpg"
                  data-text="Добрый вечер! Свитшот прекрасный! Плотная ткань и теплая подкладка, крепкие швы. Особенно радует принт - и дизайн, и качество супер! На самом деле настоящая находка, все еще очень рада и благодарна, что вы тогда в рекомендациях появились ❤️">
            <div class="rv-media">
              <img src="images/review-5.jpg" alt="Отзыв сончоус" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">М</span>
            </div>
            <p class="rv-text">Добрый вечер! Свитшот прекрасный! Плотная ткань и теплая подкладка, крепкие швы. Особенно радует принт - и дизайн, и качество супер! На самом деле настоящая находка, все еще очень рада и благодарна, что вы тогда в рекомендациях появились ❤️</p>
            <div class="rv-meta"><span class="rv-name">сончоус</span><span class="rv-item">Князь Владимир I</span></div>
          </button>

          <button class="rv-card" data-name="Catherine" data-item="Князь Владимир II" data-img="images/review-6.jpg"
                  data-text="Добрый вечер! Я в восторге, это моя любимая вещь в гардеробе. Отдельно хочу поблагодарить за подробную инструкцию, как гладить/ стирать и прочие детали">
            <div class="rv-media">
              <img src="images/review-6.jpg" alt="Отзыв Catherine" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">О</span>
            </div>
            <p class="rv-text">Добрый вечер! Я в восторге, это моя любимая вещь в гардеробе. Отдельно хочу поблагодарить за подробную инструкцию, как гладить/ стирать и прочие детали</p>
            <div class="rv-meta"><span class="rv-name">Catherine</span><span class="rv-item">Князь Владимир II</span></div>
          </button>

          <button class="rv-card" data-name="Аня" data-item="Князь Владимир III" data-img="images/review-7.jpg"
                  data-text="здравствуйте! мои хорошие, посылку получила, большое спасибо, исполнили детскую мечту! 😭💔">
            <div class="rv-media">
              <img src="images/review-7.jpg" alt="Отзыв Ани" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">И</span>
            </div>
            <p class="rv-text">здравствуйте! мои хорошие, посылку получила, большое спасибо, исполнили детскую мечту! 😭💔</p>
            <div class="rv-meta"><span class="rv-name">Аня</span><span class="rv-item">Князь Владимир III</span></div>
          </button>

          <button class="rv-card" data-name="tmk" data-item="Князь Владимир II" data-img="images/review-8.jpg"
                  data-text="Добрый вечер! Получила свитшот, прекрасно просто все от качества до принта! Молодой человек подарком обеспечен! Спасибо Вам большое!🐈">
            <div class="rv-media">
              <img src="images/review-8.jpg" alt="Отзыв tmk" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">К</span>
            </div>
            <p class="rv-text">Добрый вечер! Получила свитшот, прекрасно просто все от качества до принта! Молодой человек подарком обеспечен! Спасибо Вам большое!🐈</p>
            <div class="rv-meta"><span class="rv-name">tmk</span><span class="rv-item">Князь Владимир II</span></div>
          </button>

          <button class="rv-card" data-name="Dr. Villanelle" data-item="Князь Владимир II" data-img="images/review-9.jpg"
                  data-text="Здравствуйте! Приехал свитшот. Мч в восторге, говорит, снимать никогда не будет 😂😂 Мне лично очень понравилась ткань, нравится, приятная на ощупь и  тёплая. Спасибо большое!">
            <div class="rv-media">
              <img src="images/review-9.jpg" alt="Отзыв Dr. Villanelle" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">Р</span>
            </div>
            <p class="rv-text">Здравствуйте! Приехал свитшот. Мч в восторге, говорит, снимать никогда не будет 😂😂 Мне лично очень понравилась ткань, нравится, приятная на ощупь и  тёплая. Спасибо большое!</p>
            <div class="rv-meta"><span class="rv-name">Dr. Villanelle</span><span class="rv-item">Князь Владимир II</span></div>
          </button>

          <button class="rv-card" data-name="Deaddynasty" data-item="Князь Владимир III" data-img="images/review-10.jpg"
                  data-text="Добрый вечер! Забрала свитшот, он восхитительный, спасибо огромное 🥰🤍">
            <div class="rv-media">
              <img src="images/review-10.jpg" alt="Отзыв Deaddynasty" loading="lazy" onerror="this.closest('.rv-media').classList.add('is-empty')">
              <span class="rv-media-fallback">В</span>
            </div>
            <p class="rv-text">Добрый вечер! Забрала свитшот, он восхитительный, спасибо огромное 🥰🤍</p>
            <div class="rv-meta"><span class="rv-name">Deaddynasty</span><span class="rv-item">Князь Владимир III</span></div>
          </button>

        </div>
      </div>
    </section>

    <!-- ===== CTA ===== -->
    <section class="rv-cta-section">
      <div class="container">
        <div class="rv-cta reveal">
          <h2>Оставьте свой отзыв</h2>
          <p>Поделитесь впечатлениями в нашем Telegram — мы читаем каждый отзыв.</p>
          <a href="https://t.me/theboyforge" target="_blank" rel="noopener" class="btn btn-primary">Написать в Telegram</a>
        </div>
      </div>
    </section>

  </main>

  <!-- ===== МОДАЛКА ОТЗЫВА ===== -->
  <div class="rv-modal" id="reviewModal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="rv-modal-backdrop" data-close></div>
    <div class="rv-modal-box">
      <button class="rv-modal-close" aria-label="Закрыть" data-close>&times;</button>
      <div class="rv-modal-media" id="reviewModalMedia">
        <img id="reviewModalImg" src="" alt="Отзыв" onerror="this.closest('.rv-modal-media').classList.add('is-empty')">
        <span class="rv-modal-fallback" id="reviewModalFallback"></span>
      </div>
      <div class="rv-modal-body">
        <p class="rv-modal-text" id="reviewModalText"></p>
        <div class="rv-meta">
          <span class="rv-name" id="reviewModalName"></span>
          <span class="rv-item" id="reviewModalItem"></span>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== FOOTER ===== -->
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-col">
          <h4>Компания</h4>
          <a href="about.php">О бренде</a>
          <a href="contacts.php">Контакты</a>
        </div>
        <div class="footer-col">
          <h4>Помощь</h4>
          <a href="delivery.php">Доставка</a>
          <a href="payment.php">Оплата</a>
          <a href="returns.php">Возврат товара</a>
        </div>
        <div class="footer-col">
          <h4>Мы в соцсетях</h4>
          <a href="https://t.me/boyforge" target="_blank" rel="noopener">Telegram</a>
        </div>
      </div>
      <div class="footer-bottom">
        <span>© <span id="year">2026</span> BOYFORGE. Все права защищены</span>
        <a href="privacy.php">Политика конфиденциальности</a>
        <a href="terms.php">Пользовательское соглашение</a>
        <a href="offer.php">Публичная оферта</a>
      </div>
    </div>
  </footer>

  <script src="js/main.js?v=18"></script>
  <script src="js/reviews.js?v=18"></script>
</body>
</html>
