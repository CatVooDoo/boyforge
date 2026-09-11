/**
 * Google Apps Script для приема заказов BOYFORGE
 * Принимает JSON и записывает строку в таблицу "Заказы"
 */
function doPost(e) {
  try {
    var SPREADSHEET_ID = "15TQEi8I2dkhpXoZfunY2PCaoL1zuQjZ7iTyEz9C7nbE";
    var SHEET_NAME = "Заказы";
    
    var spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
    var sheet = spreadsheet.getSheetByName(SHEET_NAME) || spreadsheet.getActiveSheet();
    
    // Создаем заголовки, если таблица пустая
    if (sheet.getLastRow() === 0) {
      sheet.appendRow([
        "Дата",
        "ID Заказа (BOYFORGE)",
        "5Post Order ID",
        "5Post Штрихкод / Трек",
        "ФИО получателя",
        "Телефон",
        "Telegram",
        "Адрес ПВЗ 5Post",
        "Товар",
        "Пол",
        "Размер",
        "Сумма",
        "Транзакция оплаты",
        "Статус заказа"
      ]);
    }
    
    var data = JSON.parse(e.postData.contents);
    
    sheet.appendRow([
      data.date || new Date().toLocaleString("ru-RU"),
      data.orderId || "",
      data.fivepostOrderId || "",
      data.fivepostBarcode || "",
      data.fio || "",
      data.phone || "",
      data.tgUsername || "",
      data.fivepostPointAddress || "",
      data.productName || "",
      data.gender || "",
      data.size || "",
      data.price || "",
      data.transactionId || "",
      data.status || "Оплачен"
    ]);
    
    return ContentService.createTextOutput(JSON.stringify({ "result": "success" }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ "result": "error", "error": err.toString() }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}
