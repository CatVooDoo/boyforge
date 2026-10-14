<?php
declare(strict_types=1);
?>
<!-- 3. Описание товара -->
<div class="form-card">
  <div class="form-card-title">
    <?= renderSvgIcon('edit') ?>
    <span>3. Описание товара</span>
  </div>

  <div class="form-group" style="margin-bottom: 0;">
    <label for="description" class="form-label">Полный текст описания</label>
    <textarea id="description" name="description" class="form-textarea" placeholder="Подробное описание ткани, принта, посадки и особенностей..."><?= $descVal ?></textarea>
  </div>
</div>

<!-- 4. Характеристики (Таблица спецификации) -->
<div class="form-card">
  <div class="form-card-title" style="justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <?= renderSvgIcon('filter') ?>
      <span>4. Характеристики (Таблица спецификации)</span>
    </div>

    <div style="display: flex; gap: 8px;">
      <button type="button" class="btn btn-secondary btn-sm" id="btnPresetTshirt">Шаблон футболки</button>
      <button type="button" class="btn btn-secondary btn-sm" id="btnPresetSweat">Шаблон свитшота</button>
    </div>
  </div>

  <table class="specs-table">
    <thead>
      <tr>
        <th style="width: 35%;">Параметр</th>
        <th style="width: 55%;">Значение</th>
        <th style="width: 10%; text-align: right;">Удалить</th>
      </tr>
    </thead>
    <tbody id="specsTableBody">
      <?php foreach ($currentSpecs as $row): ?>
        <?php
          $sKey = htmlspecialchars($row[0] ?? '');
          $sVal = htmlspecialchars($row[1] ?? '');
        ?>
        <tr class="specs-row">
          <td><input type="text" class="form-input spec-key" value="<?= $sKey ?>" placeholder="Параметр"></td>
          <td><input type="text" class="form-input spec-val" value="<?= $sVal ?>" placeholder="Значение"></td>
          <td style="text-align: right;">
            <button type="button" class="btn-icon btn-icon-danger btn-remove-spec" title="Удалить">
              <?= renderSvgIcon('x') ?>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <button type="button" class="btn btn-secondary btn-sm" id="btnAddSpec">
    <?= renderSvgIcon('plus') ?>
    <span>Добавить строку</span>
  </button>
</div>
