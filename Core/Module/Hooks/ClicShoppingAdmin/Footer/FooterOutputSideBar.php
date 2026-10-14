<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

class FooterOutputSideBar
{
  /**
   * Generates and returns the sidebar vertical menu script if the admin session is set
   * and the vertical menu configuration is enabled.
   *
   * @return string|bool Returns the script as a string if conditions are met, or false otherwise.
   */
  public function display(): string|bool
  {
    $output = '';

    if (isset($_SESSION['admin']) && VERTICAL_MENU_CONFIGURATION == 'true') {
      $output .= '<!--Sidebar Vertical Menu Script start-->' . "\n";
      $output .= '<script defer>' . "\n";
      $output .= '(function ($) {' . "\n";
      $output .= '  const STORAGE_KEY = "clicAdminSidebarCollapsed";' . "\n";
      $output .= '  const isDesktop = () => window.matchMedia("(min-width: 769px)").matches;' . "\n";
      $output .= '  function toggleSidebar() {' . "\n";
      $output .= '    const collapsed = $("#content, #sidebar").toggleClass("active").filter("#content").hasClass("active");' . "\n";
      $output .= '    $("#sidebarCollapse, #sidebarCollapse1").attr("aria-expanded", (!collapsed).toString());' . "\n";
      $output .= '    if (isDesktop()) { try { localStorage.setItem(STORAGE_KEY, collapsed ? "1" : "0"); } catch (e) {} }' . "\n";
      $output .= '  }' . "\n";
      $output .= '  $(function () { $("#sidebarCollapse, #sidebarCollapse1").on("click", toggleSidebar); });' . "\n";
      $output .= '})(jQuery);' . "\n";
      $output .= '</script>' . "\n";
      $output .= '<!--End Sidebar Vertical Menu -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}