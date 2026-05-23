<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Module\Hooks\ClicShoppingAdmin\Stats;

use ClicShopping\Apps\Catalog\Categories\Categories as categoriesApp;
use ClicShopping\OM\Registry;

/**
 * Class responsible for managing and displaying statistics related to categories within the application.
 */
class StatsCategories implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Categories module.
   *
   * Ensures the 'Categories' instance is registered. If not, it registers
   * a new instance of the categoriesApp class. Additionally, it assigns
   * the 'Categories' module to the current object and loads the necessary
   * definitions for the module hooks.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Categories')) {
      Registry::set('Categories', new categoriesApp());
    }

    $this->app = Registry::get('Categories');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Stats/stat_categories');
  }

  /**
   * Retrieves the total count of active categories from the database.
   *
   * @return int The number of active categories with a status of 1.
   */
  private function getCategoriesOn(): int
  {
    $QCategories = $this->app->db->prepare('select count(categories_id) as count
                                              from :table_categories
                                              where status = 1
                                            ');
    $QCategories->execute();

    return $QCategories->valueInt('count');
  }

  /**
   * Retrieves the count of categories with a status set to 0.
   *
   * @return int The number of categories where the status is 0.
   */
  private function getCategoriesOff(): int
  {
    $QCategories = $this->app->db->prepare('select count(categories_id) as count
                                              from :table_categories
                                              where status = 0
                                            ');
    $QCategories->execute();

    return $QCategories->valueInt('count');
  }

  /**
   * Generates and returns the HTML output for the categories display card.
   * The card shows an alert with the number of categories that are active or inactive.
   * If categories functionality is disabled or there are no categories, it returns false or an empty string.
   *
   * @return string|false Returns the generated HTML output as a string, an empty string if no categories exist, or false if the functionality is disabled.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_CATEGORIES_CT_STATUS') || CLICSHOPPING_APP_CATEGORIES_CT_STATUS == 'False') {
      return false;
    }

    if ($this->getCategoriesOn() == 0 && $this->getCategoriesOff() == 0) {
      $output = '';
    } else {
      $output = '
<div class="col-md-2 col-12">
    <div class="card bg-warning">
     <div class="card-body">
      <h6 class="card-title text-white">' . $this->app->getDef('text_categories_alert') . '</h6>
      <div class="card-text">
        <div class="col-sm-12">
          <span class="float-start">
            <i class="bi bi-bell-fill text-white"></i>
          </span>
          <span class="float-end">
            <div class="col-sm-12 text-white">' . $this->getCategoriesOn() . ' - ' . $this->app->getDef('text_categories_on') . '</div>
            <div class="col-sm-12 text-white">' . $this->getCategoriesOff() . ' - ' . $this->app->getDef('text_categories_off') . '</div>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>  
      ';
    }

    return $output;
  }
}