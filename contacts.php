<?php
declare(strict_types=1);

$pageTitle = 'Контакты — BOYFORGE';
$bodyClass = 'page-contacts';

require __DIR__ . '/includes/components/header.php';
?>

<main>
    <div class="container">
      <nav class="breadcrumbs" aria-label="Хлебные крошки">
        <a href="index.php">Главная</a> <span>/</span> <span>Контакты</span>
      </nav>
    </div>

    <!-- ===== SPLIT HERO ===== -->
    <section class="container ct-hero">
      <div class="ct-hero-left reveal">
        <div class="ct-eyebrow">Контакты</div>
        <h1>Мы всегда<br>на связи</h1>
        <p>Напишите удобным способом — ответим в течение рабочего дня. Быстрее всего в Telegram.</p>
      </div>

      <div class="ct-hero-right">
        <a href="https://t.me/theboyforge" target="_blank" rel="noopener" class="ct-primary reveal">
          <div class="ct-primary-tag">Быстрее всего</div>
          <h3>Telegram</h3>
          <span class="ct-primary-value">@theboyforge</span>
          <span class="ct-primary-arrow">Написать →</span>
        </a>
        <a href="mailto:boyforge@bk.ru" class="ct-secondary reveal">
          <h3>Почта</h3>
          <span class="ct-secondary-value">boyforge@bk.ru</span>
          <p>Сотрудничество</p>
        </a>
      </div>
    </section>

    <!-- ===== ФОРМА (светлая) ===== -->
    <section class="container ct-form-section">
      <div class="ct-form-card reveal">
        <div class="ct-form-head">
          <h2>Написать нам</h2>
          <p>Укажите ник в Telegram и вопрос — поможем с размером, оплатой и доставкой.</p>
        </div>

        <form class="ct-form" id="contactForm" novalidate>
          <div class="ct-field">
            <label for="tgName">Ваш ник в Telegram</label>
            <input type="text" id="tgName" name="tg" placeholder="@username" autocomplete="off">
          </div>
          <div class="ct-field">
            <label for="question">Ваш вопрос</label>
            <textarea id="question" name="question" placeholder="Опишите ваш вопрос"></textarea>
          </div>
          <button type="submit">Отправить</button>
          <p class="form-status" id="formStatus" role="status" aria-live="polite"></p>
        </form>
      </div>
    </section>

    <!-- ===== РЕКВИЗИТЫ ===== -->
    <section class="container ct-req-section">
      <div class="ct-req reveal">
        <h2>Реквизиты</h2>
        <div class="ct-req-grid">
          <div class="ct-req-item">
            <span class="ct-req-label">Продавец</span>
            <span class="ct-req-value">ИП Бояров Тимофей Петрович</span>
          </div>
          <div class="ct-req-item">
            <span class="ct-req-label">ИНН</span>
            <span class="ct-req-value">580319807587</span>
          </div>
          <div class="ct-req-item">
            <span class="ct-req-label">Адрес</span>
            <span class="ct-req-value">г. Пенза, ул. Галетная, 22А</span>
          </div>
          <div class="ct-req-item">
            <span class="ct-req-label">Почта</span>
            <span class="ct-req-value">boyforge@bk.ru</span>
          </div>
          <div class="ct-req-item">
            <span class="ct-req-label">Банк</span>
            <span class="ct-req-value">АО «ТБанк», БИК 044525974</span>
          </div>
          <div class="ct-req-item">
            <span class="ct-req-label">Расчётный счёт</span>
            <span class="ct-req-value">40802810700009414372</span>
          </div>
        </div>
      </div>
    </section>

  </main>

  <?php require __DIR__ . '/includes/components/footer.php'; ?>
