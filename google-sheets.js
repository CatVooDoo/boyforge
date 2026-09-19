var SPREADSHEET_ID = "15TQEi8I2dkhpXoZfunY2PCaoL1zuQjZ7iTyEz9C7nbE";
var SHEET_NAME = "Заказы";

function doGet(e) {
  try {
    var ss = null;
    try {
      ss = SpreadsheetApp.getActiveSpreadsheet();
    } catch (e1) {}
    if (!ss) {
      try {
        ss = SpreadsheetApp.openById(SPREADSHEET_ID);
      } catch (e2) {}
    }

    var sheetName = "—";
    var rows = 0;
    if (ss) {
      var sheet = ss.getSheetByName(SHEET_NAME) || ss.getActiveSheet();
      if (sheet) {
        sheetName = sheet.getName();
        ensureHeaders(sheet);
        rows = sheet.getLastRow();
      }
    }

    return ContentService.createTextOutput(JSON.stringify({
      status: "ok",
      spreadsheetFound: ss !== null,
      sheetName: sheetName,
      totalRows: rows,
      message: "BOYFORGE Google Sheets Webhook активен и готов принимать заказы!"
    })).setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({
      status: "error",
      error: err.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

function doPost(e) {
  try {
    var ss = null;
    try {
      ss = SpreadsheetApp.getActiveSpreadsheet();
    } catch (e1) {}
    if (!ss) {
      try {
        ss = SpreadsheetApp.openById(SPREADSHEET_ID);
      } catch (e2) {}
    }
    if (!ss) {
      throw new Error("Не удалось получить доступ к таблице (проверьте права доступа в Apps Script)");
    }

    var sheet = ss.getSheetByName(SHEET_NAME) || ss.getActiveSheet();
    
    ensureHeaders(sheet);
    
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
    
    var orderDate = data.date || Utilities.formatDate(new Date(), "GMT+3", "dd.MM.yyyy HH:mm:ss");
    
    var rawPhone = (data.phone || "").toString().trim();
    var safePhone = rawPhone;
    if (safePhone.charAt(0) === '+') {
      safePhone = "'" + safePhone;
    }

    var targetOrderId = (data.orderId || "").toString().trim();
    var safeBarcode = (data.fivepostBarcode || "—").toString().trim();
    if (safeBarcode.charAt(0) === '+') {
      safeBarcode = "'" + safeBarcode;
    }

    var rowData = [
      orderDate,
      targetOrderId || "—",
      safeBarcode,
      data.status || "Оплачен",
      data.transactionId || "—",
      data.productName || "Товар BOYFORGE",
      data.gender || "Мужской",
      data.size || "M",
      data.price || "3 200 ₽",
      data.fivepostPointAddress || "—",
      data.fio || "—",
      safePhone || "—",
      data.tgUsername || "—",
      data.fivepostOrderId || "—"
    ];

    var targetRow = 0;
    if (targetOrderId && targetOrderId !== "—") {
      var lastRow = sheet.getLastRow();
      if (lastRow > 1) {
        var existingIds = sheet.getRange(2, 2, lastRow - 1, 1).getValues();
        for (var i = 0; i < existingIds.length; i++) {
          if (existingIds[i][0] && existingIds[i][0].toString().trim() === targetOrderId) {
            targetRow = i + 2;
            break;
          }
        }
      }
    }

    if (targetRow === 0) {
      targetRow = sheet.getLastRow() + 1;
    }

    sheet.getRange(targetRow, 1, 1, rowData.length).setValues([rowData]);

    sheet.getRange(targetRow, 12).setNumberFormat("@");
    sheet.getRange(targetRow, 3).setNumberFormat("@").setFontFamily("Courier New").setFontWeight("bold");
    sheet.getRange(targetRow, 2).setNumberFormat("@").setFontFamily("Courier New");
    sheet.getRange(targetRow, 1, 1, 14).setVerticalAlignment("middle");
    
    return ContentService.createTextOutput(JSON.stringify({ result: "success", row: targetRow, updated: targetRow !== sheet.getLastRow() }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ result: "error", error: err.toString() }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

function ensureHeaders(sheet) {
  var headers = [
    "Дата",
    "ID Заказа (BOYFORGE)",
    "Штрихкод / Трек 5Post",
    "Статус заказа",
    "Транзакция ЮKassa",
    "Товар",
    "Пол",
    "Размер",
    "Сумма",
    "Адрес ПВЗ 5Post",
    "ФИО получателя",
    "Телефон",
    "Telegram",
    "5Post Order ID"
  ];
  
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
    formatHeaderRow(sheet);
    return;
  }
  
  var firstCell = sheet.getRange(1, 1).getValue().toString();
  var secondCell = sheet.getRange(1, 2).getValue().toString();
  if (secondCell !== "ID Заказа (BOYFORGE)") {
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
