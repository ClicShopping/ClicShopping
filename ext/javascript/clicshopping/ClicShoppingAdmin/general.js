/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.

 */

function SetFocus() {
  if (document.forms.length > 0) {
    isNotAdminLanguage:
      for (f = 0; f < document.forms.length; f++) {
        if (document.forms[f].name != "adminlanguage") {
          const field = document.forms[f];
          for (i = 0; i < field.length; i++) {
            if ((field.elements[i].type != "image") &&
              (field.elements[i].type != "hidden") &&
              (field.elements[i].type != "reset") &&
              (field.elements[i].type != "button") &&
              (field.elements[i].type != "submit") &&
              (field.elements[i].disabled != true)
            ) {

              document.forms[f].elements[i].focus();

              if ((field.elements[i].type == "text") ||
                (field.elements[i].type == "password")
              )
                document.forms[f].elements[i].select();

              break isNotAdminLanguage;
            }
          }
        }
      }
  }
}

function toggleDivBlock(id) {
  let itm;

  if (document.getElementById) {
    itm = document.getElementById(id);
  }

  if (document.all) {
    itm = document.all[id];
  }

  if (document.layers) {
    itm = document.layers[id];
  }

  if (itm) {
    if (itm.style.display != 'none') {
      itm.style.display = 'none';
    } else {
      itm.style.display = 'block';
    }
  }
}

/*
 * Delegated handlers for HTML::button (and any element).
 *
 * HTML::button routes its params through HTML::sanitizeHtmlAttributes, which strips inline
 * on* event handlers (onclick, ...). Use these sanitize-safe data- attributes instead — they
 * survive sanitization and need no per-page inline <script>:
 *
 *   data-confirm-text="msg"  -> window.confirm() guard; cancels the action (preventDefault) on No.
 *                               Pair with a link/submit so OK proceeds (e.g. a SQL update button).
 *                               NB: the attribute name must NOT be "data-confirm" — sanitizeHtml-
 *                               Attributes strips on\w+= and "data-c[onfirm=]" matches, leaving "data-c".
 *                               "data-confirm-text" is safe (the = follows "text", not "on\w+").
 *   data-fn="name"           -> calls the global function <name>() on click. Dotted paths work,
 *                               e.g. data-fn="location.reload".
 *   data-fn-arg="value"      -> optional single string argument passed to data-fn.
 *
 * Bound once via event delegation on document, so it also covers dynamically added elements.
 */
document.addEventListener('click', function (event) {
  const el = event.target.closest('[data-confirm-text], [data-fn]');

  if (el === null) {
    return;
  }

  const confirmMessage = el.getAttribute('data-confirm-text');

  if (confirmMessage !== null && window.confirm(confirmMessage) !== true) {
    event.preventDefault();
    return;
  }

  const fnPath = el.getAttribute('data-fn');

  if (fnPath) {
    const parts = fnPath.split('.');
    const fnName = parts.pop();
    let context = window;

    for (let i = 0; i < parts.length && context; i++) {
      context = context[parts[i]];
    }

    const fn = context ? context[fnName] : undefined;

    if (typeof fn === 'function') {
      const arg = el.getAttribute('data-fn-arg');

      if (arg !== null) {
        fn.call(context, arg);
      } else {
        fn.call(context);
      }
    }
  }
});
