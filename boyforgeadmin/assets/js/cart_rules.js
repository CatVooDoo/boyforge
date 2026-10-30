"use strict";
document.getElementById("generatePromoBtn").addEventListener("click", function () {
  const alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  const bytes = new Uint8Array(10);
  window.crypto.getRandomValues(bytes);
  const input = document.getElementById("promoCode");
  input.value = Array.from(bytes, value => alphabet[value % alphabet.length]).join("");
  input.focus();
});
