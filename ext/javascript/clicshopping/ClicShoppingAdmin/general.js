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
