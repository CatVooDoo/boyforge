function doPost(e) {
  try {
    // Указываем ID таблицы и название листа
    var SPREADSHEET_ID = "15TQEi8I2dkhpXoZfunY2PCaoL1zuQjZ7iTyEz9C7nbE";
    var SHEET_NAME = "Заказы"; // Название вкладки внизу таблицы
    
    var spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
    var sheet = spreadsheet.getSheetByName(SHEET_NAME) || spreadsheet.getActiveSheet();
    
    var data = JSON.parse(e.postData.contents);
    
    sheet.appendRow([
      data.date || new Date().toLocaleString("ru-RU"),
      data.productName || "",
      data.price || "",
      data.gender || "",
      data.size || "",
      data.source || "",
      data.ip || ""
    ]);
    
    return ContentService.createTextOutput(JSON.stringify({ "result": "success" }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ "result": "error", "error": err.toString() }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}
