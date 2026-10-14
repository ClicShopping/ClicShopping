document.addEventListener("DOMContentLoaded", function() {
  // Use the form NAME (stable): the Stripe module rewrites the form id to "payment-form".
  var form = document.querySelector("form[name='checkout_confirmation']");
  var btn = document.querySelector("#payNow");

  if (!form || !btn) {
    return;
  }

  // Lock the pay button once the form actually submits, to prevent a second click while the
  // payment AND its post-processing run. That post-processing can be long (order creation plus
  // AI insights / embedding generation has been measured at 15s+), so a fixed re-enable timer
  // would unlock the button mid-processing and allow a double payment.
  form.addEventListener("submit", function() {
    // The submit event only fires when native validation passes (e.g. the required "agree"
    // checkbox), so we never strand the button on a blocked submit. Defer the disable to the
    // next tick so it does not cancel the in-flight submission (Chrome / Firefox / Edge cancel a
    // form submission if its submit button is disabled during the same click).
    setTimeout(function() {
      btn.disabled = true;
    }, 0);

    // No auto re-enable: the button stays locked until the page navigates away. Payment modules
    // that drive their own client-side flow (e.g. Stripe) re-enable it themselves on error.
  });
});
