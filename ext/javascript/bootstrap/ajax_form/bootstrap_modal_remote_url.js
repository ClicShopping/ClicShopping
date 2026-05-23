/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.

 */

$('body').on('click', '[data-bs-toggle="modal"]', function () {
  $($(this).data("target") + ' .modal-body').load($(this).data("remote"));
});
