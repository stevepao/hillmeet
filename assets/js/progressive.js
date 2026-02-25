/**
 * progressive.js
 * Purpose: Progressive enhancement (check availability form feedback, toasts).
 * Project: Hillmeet
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
(function() {
  'use strict';

  var checkBtn = document.getElementById('check-availability');
  if (checkBtn && checkBtn.form) {
    checkBtn.form.addEventListener('submit', function() {
      checkBtn.disabled = true;
      checkBtn.textContent = 'Checking…';
    });
  }
})();
