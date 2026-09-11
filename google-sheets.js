/**
 * Google Apps Script для приема заказов BOYFORGE (5Post + CloudPayments)
 * Инструкция по обновлению в Google Таблицах:
 * 1. В таблице откройте: Расширения -> Apps Script
 * 2. Полностью замените код на этот
 * 3. Нажмите "Сохранить" (значок дискеты)
 * 4. Нажмите синюю кнопку "Развернуть" (Deploy) -> "Управление развертываниями" (Manage deployments)
 * 5. Нажмите значок карандаша (Редактировать) -> в поле "Версия" выберите "Новая версия" (New version) -> нажмите "Развернуть" (Deploy)!
 */

var SPREADSHEET_ID = "15TQEi8I2dkhpXoZfunY2PCaoL1zuQjZ7iTyEz9C7nbE";
var SHEET_NAME = "Заказы";

function doGet(e) {
  return ContentService.createTextOutput(JSON.stringify({
    status: "ok",
    message: "BOYFORGE Google Sheets Webhook активен и готов принимать заказы!"
  })).setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  try {
    var ss = SpreadsheetApp.openById(SPREADSHEET_ID);
    var sheet = ss.getSheetByName(SHEET_NAME) || ss.getActiveSheet();
    
    // Проверяем наличие заголовков таблицы
    ensureHeaders(sheet);
    
    // Парсим входящие данные (JSON или Form Data)
    var data = {};
    if (e && e.postData && e.postData.contents) {
      try {
        data = JSON.parse(e.postData.contents);
      } catch (err) {
        data = e.parameter || {};
      }
    } else if (e && e.parameter) {
      data = e.parameter;
    }
    
    // Получаем текущую дату и время
    var orderDate = data.date || Utilities.formatDate(new Date(), "GMT+3", "dd.MM.yyyy HH:mm:ss");
    
    // Добавляем строку заказа
    sheet.appendRow([
      orderDate,                                  // A: Дата
      data.orderId || "—",                        // B: ID Заказа (BOYFORGE)
      data.fivepostBarcode || "—",                // C: Штрихкод / Трек 5Post
      data.status || "Оплачен",                   // D: Статус заказа
      data.fio || "—",                            // E: ФИО получателя
      data.phone || "—",                          // F: Телефон
      data.tgUsername || "—",                     // G: Telegram
      data.productName || "Товар BOYFORGE",       // H: Товар
      data.gender || "Мужской",                   // I: Пол
      data.size || "M",                           // J: Размер
      data.price || "3 200 ₽",                    // K: Сумма
      data.fivepostPointAddress || "—",           // L: Адрес ПВЗ 5Post
      data.transactionId || "—",                  // M: ID оплаты (CloudPayments)
      data.fivepostOrderId || "—"                 // N: 5Post Order ID (UUID)
    ]);
    
    // Форматируем последнюю строку
    var lastRow = sheet.getLastRow();
    sheet.getRange(lastRow, 1, 1, 14).setVerticalAlignment("middle");
    sheet.getRange(lastRow, 3).setFontFamily("Courier New").setFontWeight("bold"); // Выделяем штрихкод
    sheet.getRange(lastRow, 2).setFontFamily("Courier New"); // ID заказа
    
    return ContentService.createTextOutput(JSON.stringify({ result: "success", row: lastRow }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ result: "error", error: err.toString() }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

/**
 * Проверка и автоматическое создание красивой шапки таблицы
 */
function ensureHeaders(sheet) {
  var headers = [
    "Дата",
    "ID Заказа (BOYFORGE)",
    "Штрихкод / Трек 5Post",
    "Статус заказа",
    "ФИО получателя",
    "Телефон",
    "Telegram",
    "Товар",
    "Пол",
    "Размер",
    "Сумма",
    "Адрес ПВЗ 5Post",
    "Транзакция CloudPayments",
    "5Post Order ID"
  ];
  
  // Если таблица пустая
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
    formatHeaderRow(sheet);
    return;
  }
  
  // Если в первой строке не заголовки, а данные первого заказа
  var firstCell = sheet.getRange(1, 1).getValue().toString();
  var secondCell = sheet.getRange(1, 2).getValue().toString();
  if (secondCell !== "ID Заказа (BOYFORGE)") {
    // Вставляем новую строку сверху для заголовков
    sheet.insertRowBefore(1);
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    formatHeaderRow(sheet);
  }
}

function formatHeaderRow(sheet) {
  var range = sheet.getRange(1, 1, 1, 14);
  range.setBackground("#18181b")
       .setFontColor("#ffffff")
       .setFontWeight("bold")
       .setFontSize(10)
       .setVerticalAlignment("middle")
       .setHorizontalAlignment("center");
  sheet.setRowHeight(1, 36);
  sheet.setFrozenRows(1);
}
