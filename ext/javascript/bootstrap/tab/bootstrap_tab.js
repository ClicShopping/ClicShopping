/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.

 */

$(document).ready(function () {

  if (location.hash !== '') {
    $('a[href="' + location.hash + '"]').tab('show');
  }

  $("a[data-bs-toggle='tab']").on("shown.bs.tab", function (e) {
    const hash = $(e.target).prop("href");
    if (hash.substr(0, 1) == "#") {
      const position = $(window).scrollTop();
      location.replace("#" + hash.substr(1));
      $(window).scrollTop(position);
    }
  });
});

