<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Manufacturers\Manufacturers as ManufacturersApp;
use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;

class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  protected $insert_language_id;

  public function __construct()
  {
    if (!Registry::exists('Manufacturers')) {
      Registry::set('Manufacturers', new ManufacturersApp());
    }

    $this->app = Registry::get('Manufacturers');
    $this->lang = Registry::get('Language');
  }

  /**
   * Inserts manufacturer information for a new language into the database.
   *
   * This method retrieves manufacturer data from the database, duplicates the data,
   * adjusts it for the new language, and saves it. Certain fields such as click information
   * and last click date are omitted during the process.
   *
   * @return void
   */
  private function insert()
  {
    $insert_language_id = LanguageAdmin::getLatestLanguageID();

    $Qmanufacturers = $this->app->db->prepare('select m.manufacturers_id as orig_manufacturer_id,
                                                        mi.*
                                                  from :table_manufacturers m left join :table_manufacturers_info mi on m.manufacturers_id = mi.manufacturers_id
                                                  where mi.languages_id = :languages_id
                                                ');

    $Qmanufacturers->bindInt(':languages_id', $this->lang->getId());
    $Qmanufacturers->execute();

    while ($Qmanufacturers->fetch()) {
      $cols = $Qmanufacturers->toArray();

      $cols['manufacturers_id'] = $cols['orig_manufacturer_id'];
      $cols['languages_id'] = (int)$insert_language_id;

      unset($cols['orig_manufacturer_id']);
      unset($cols['url_clicks']);
      unset($cols['date_last_click']);

      $this->app->db->save('manufacturers_info', $cols);
    }
  }

  /**
   * Executes the main functionality of the method based on predefined conditions.
   *
   * @return bool|void Returns false if the application status is not defined or disabled. Otherwise, executes the insert process when specific parameters are set.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS') || CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Langues'], $_GET['Insert'])) {
      $this->insert();
    }
  }
}