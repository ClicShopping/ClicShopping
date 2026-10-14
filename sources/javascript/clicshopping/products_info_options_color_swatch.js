/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
/**
 * products_info_options_color_swatch.js
 * * Version finale :
 * - Gère la sélection par groupe.
 * - Autorise l'envoi du panier si AU MOINS une option est choisie.
 * - Empêche l'envoi avec une alerte si rien n'est sélectionné.
 */
(function () {
  'use strict';

  function initColorSwatches() {
    const swatches = document.querySelectorAll('[data-swatch-group]');
    const form = document.querySelector('form[name="cart_add"]');

    if (swatches.length === 0) return;

    swatches.forEach(function (btn) {
      btn.onclick = function (e) {
        e.preventDefault();

        const groupId = btn.getAttribute('data-swatch-group');
        const val = btn.getAttribute('data-swatch-value'); // C'est l'ID numérique (ex: 3 ou 4)

        // 1. NETTOYAGE COMPLET
        document.querySelectorAll('[data-color-sentinel]').forEach(function(s) {
          s.value = "";
          s.removeAttribute('required');
          s.disabled = false; // On s'assure qu'ils sont activés pour la sélection
        });

        // Reset visuel
        document.querySelectorAll('[data-swatch-group]').forEach(function(b) {
          b.classList.remove('swatch-selected');
          b.style.border = "none";
          b.style.boxShadow = "none";
        });

        // 2. ACTIVATION DE LA SÉLECTION
        const sentinel = document.querySelector('[data-color-sentinel="' + groupId + '"]');
        if (sentinel) {
          sentinel.value = val;

          // Feedback visuel sur le bouton sélectionné
          btn.classList.add('swatch-selected');
          btn.style.border = "2px solid #000";
          btn.style.boxShadow = "0 0 5px rgba(0,0,0,0.5)";

          console.log("Option sélectionnée : Groupe ID " + groupId + " / Valeur ID " + val);
        }
      };
    });

    // 3. VALIDATION ET NETTOYAGE AVANT ENVOI (CRUCIAL POUR PHP)
    if (form) {
      form.onsubmit = function (e) {
        const sentinels = form.querySelectorAll('[data-color-sentinel]');
        let isAnySelected = false;

        sentinels.forEach(function (s) {
          if (s.value === "" || s.value === null) {
            // IMPORTANT : On désactive les champs vides pour que PHP
            // ne reçoive pas de id[3]="" (ce qui fait échouer is_numeric)
            s.disabled = true;
          } else {
            isAnySelected = true;
            s.disabled = false; // On s'assure qu'il est activé pour l'envoi
          }
        });

        if (!isAnySelected) {
          e.preventDefault();
          alert("Veuillez sélectionner une option de couleur.");
          // On réactive les champs pour que l'utilisateur puisse corriger
          sentinels.forEach(s => s.disabled = false);
          return false;
        }

        return true;
      };
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initColorSwatches);
  } else {
    initColorSwatches();
  }
})();