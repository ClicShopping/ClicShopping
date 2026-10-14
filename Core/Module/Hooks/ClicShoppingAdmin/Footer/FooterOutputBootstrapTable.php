<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

use ClicShopping\OM\CLICSHOPPING;

class FooterOutputBootstrapTable
{
  /**
   * Outputs the necessary scripts and elements required for initializing and using a Bootstrap table,
   * including support for features like checkboxes, export functionality, and mobile responsive design.
   *
   * If the session does not have an admin user authenticated, the method returns false.
   *
   * @return string|bool The generated HTML and script elements for the Bootstrap table if the admin session exists, or false otherwise.
   */
  public function display(): string|bool
  {
    $output = '';

    if (isset($_SESSION['admin'])) {
      $output .= '<!-- Start BootStrap Table -->' . "\n";
      $output .= '<script defer src="https://unpkg.com/bootstrap-table@1.27.3/dist/bootstrap-table.min.js"></script>' . "\n";
//checkbox
      $output .= '<script defer src="' . CLICSHOPPING::link('Shop/ext/javascript/bootstrapTable/table_checkbox.js') . '"></script>' . "\n";
//export
      $output .= '<script defer src="https://unpkg.com/bootstrap-table@1.27.3/dist/extensions/export/bootstrap-table-export.min.js"></script>' . "\n";
      $output .= '<script defer src="https://unpkg.com/tableexport.jquery.plugin/tableExport.min.js"></script>' . "\n";
//mobile
      $output .= '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.24.2/extensions/mobile/bootstrap-table-mobile.min.js"></script>' . "\n";
      $output .= '
<script defer>
  window.icons = {
        paginationSwitchDown: \'bi-arrow-bar-down\',
        paginationSwitchUp: \'bi-arrow-bar-up\',
        refresh: \'bi-arrow-repeat\',
        toggleOff: \'bi-toggle-off\',
        toggleOn: \'bi-toggle-on\',
        columns: \'bi-list\',
        fullscreen: \'bi-fullscreen\',
        detailOpen: \'bi-plus\',
        detailClose: \'bi-minus\'
  }
</script>' . "\n";

      $output .= '<!-- End bootstrap Table -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}