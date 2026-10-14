/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.

 */
$(function () {
  $("#ajaxform").submit(async function (e) {
    e.preventDefault(); //STOP default action do this first in case any errors thrown in code
    var form = this;
    var postData = $(this).serializeArray();
    var formURL = $(this).attr("action");

    try {
      const response = await $.ajax({
        url: formURL,
        type: "POST",
        data: postData,
      });
      $("#simple-msg").html('<pre><code class="prettyprint">' + response + '</code></pre>');
    } catch (error) {
      $("#simple-msg").html('<pre><code class="prettyprint">AJAX Request Failed<br/> textStatus=' + error.statusText + ', errorThrown=' + error.status + '</code></pre>');
    }
  });
});



