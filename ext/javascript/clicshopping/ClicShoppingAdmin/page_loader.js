/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.

 */


window.addEventListener("load", function () {
  // Page Preloader
  $('#preloader').delay(100).fadeOut(function () {
    $('body').delay(100);
  });
});
