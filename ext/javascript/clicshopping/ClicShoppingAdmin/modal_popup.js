/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.

 */

$(document).ready(function () {
  $("#myModal").on("show.bs.modal", function (e) {
    const link = $(e.relatedTarget);
    $(this).find(".modal-body").load(link.attr("href"));
  });
});

$(document).ready(function () {
  $("#myModal1").on("show.bs.modal", function (e) {
    const link = $(e.relatedTarget);
    $(this).find(".modal-body").load(link.attr("href"));
  });
});

$(document).ready(function () {
  $("#myModal2").on("show.bs.modal", function (e) {
    const link = $(e.relatedTarget);
    $(this).find(".modal-body").load(link.attr("href"));
  });
});

$(document).ready(function () {
  $("#myModal3").on("show.bs.modal", function (e) {
    const link = $(e.relatedTarget);
    $(this).find(".modal-body").load(link.attr("href"));
  });
});

$(document).ready(function () {
  $("#myModal4").on("show.bs.modal", function (e) {
    const link = $(e.relatedTarget);
    $(this).find(".modal-body").load(link.attr("href"));
  });
});

$(document).ready(function () {
  $("#myModal5").on("show.bs.modal", function (e) {
    const link = $(e.relatedTarget);
    $(this).find(".modal-body").load(link.attr("href"));
  });
});