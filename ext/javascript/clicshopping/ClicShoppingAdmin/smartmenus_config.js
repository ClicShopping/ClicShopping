/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.

 */

$(function () {
  $('#main-menu').smartmenus({
    subMenusSubOffsetX: 6,
    subMenusSubOffsetY: 0,
    showOnClick: false,
  });
});

// SmartMenus mobile menu toggle button
$(function () {
  const $mainMenuState = $('#main-menu-state');
  if ($mainMenuState.length) {
    // animate mobile menu
    $mainMenuState.change(function (e) {
      var $menu = $('#main-menu');
      if (this.checked) {
        $menu.hide().slideDown(250, function () {
          $menu.css('display', '');
        });
      } else {
        $menu.show().slideUp(250, function () {
          $menu.css('display', '');
        });
      }
    });
    // reset mobile menu state on page hide (pagehide replaces the deprecated unload event and also covers bfcache)
    $(window).on('pagehide', function () {
      if ($mainMenuState[0].checked) {
        $mainMenuState[0].click();
      }
    });
  }
});

