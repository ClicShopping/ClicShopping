<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Archive\Module\Hooks\ClicShoppingAdmin\StatsDashboard;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Archive\Archive as ArchiveApp;

/**
 * Class PageTabContent
 *
 * Implements the HooksInterface to interact with and display content in the ClicShopping Admin Stats Dashboard module.
 * This class handles the initialization of the Archive application, retrieves statistical data for archived products,
 * and generates output to be displayed in the designated area of the admin dashboard.
 */
class PageTabContent implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Archive application instance.
   *
   * Ensures that the Archive application is registered and accessible within the Registry.
   * Loads necessary definitions related to the ClicShoppingAdmin Stats Dashboard module.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Archive')) {
      Registry::set('Archive', new ArchiveApp());
    }

    $this->app = Registry::get('Archive');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/StatsDashboard/page_tab_content');
  }

  /**
   * Retrieves the count of archived products from the database.
   *
   * Executes a database query to count the number of products marked as archived
   * within the products table. The method returns the total count of such products.
   *
   * @return int The total number of archived products.
   */
  private function statsCountProductsArchive()
  {
    $QproductsArchives = $this->app->db->prepare('select count(products_id) as count
                                                    from :table_products
                                                    where products_archive = 1
                                                    limit 1
                                                   ');
    $QproductsArchives->execute();

    $products_archives_total = $QproductsArchives->valueInt('count');

    return $products_archives_total;
  }

  /**
   * Displays content related to the archived products.
   *
   * This method checks if the Archive application is enabled and has archived products.
   * If conditions are met, it generates and returns an HTML structure showcasing the
   * count of archived products.
   *
   * @return string|bool Generated HTML output if the Archive application is active and has archived products, false otherwise.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_ARCHIVE_AR_STATUS') || CLICSHOPPING_APP_ARCHIVE_AR_STATUS == 'False') {
      return false;
    }

    if ($this->statsCountProductsArchive() != 0) {
      $content = '
        <div class="row">
          <div class="col-md-11 mainTable">
            <div class="form-group row">
              <label for="' . $this->app->getDef('box_entry_products_archives') . '" class="col-9 col-form-label"><a href="' . $this->app->link('Archive') . '">' . $this->app->getDef('box_entry_products_archives') . '</a></label>
              <div class="col-md-3">
                ' . $this->statsCountProductsArchive() . '
              </div>
            </div>
          </div>
        </div>
       ';

      $output = <<<EOD
  <!-- ######################## -->
  <!--  Start Products      -->
  <!-- ######################## -->
             {$content}
  <!-- ######################## -->
  <!--  Start Products      -->
  <!-- ######################## -->
EOD;
      return $output;
    }
  }
}
