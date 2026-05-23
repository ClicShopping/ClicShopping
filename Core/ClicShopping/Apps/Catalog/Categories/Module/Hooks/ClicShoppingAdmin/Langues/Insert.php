<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;
use ClicShopping\ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdminApps\Catalog\Categories\Categories as CategoriesApp;
use ClicShopping\OM\Registry;



class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  private mixed $lang;

  /**
   * Initializes the Categories application and sets up the language.
   *
   * This constructor checks if the 'Categories' registry entry exists; if it does not,
   * it creates a new instance of CategoriesApp and registers it. The constructor
   * also retrieves and assigns the 'Categories' application and 'Language' registry entries.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Categories')) {
      Registry::set('Categories', new CategoriesApp());
    }

    $this->app = Registry::get('Categories');
    $this->lang = Registry::get('Language');
  }

  /**
   * Inserts category descriptions into the database for a newly added language.
   *
   * The method retrieves category descriptions from the database for the current language, modifies the data
   * to replace the language ID with the ID of the new language, and saves the updated descriptions back into
   * the database for that new language.
   *
   * @return void
   */
  private function insert()
  {
    $insert_language_id = LanguageAdmin::getLatestLanguageID();

    $Qcategories = $this->app->db->prepare('select c.categories_id as orig_category_id,
                                                     cd.*
                                              from :table_categories c left join :table_categories_description cd on c.categories_id = cd.categories_id
                                              where cd.language_id = :language_id
                                              ');

    $Qcategories->bindInt(':language_id', (int)$this->lang->getId());
    $Qcategories->execute();

    while ($Qcategories->fetch()) {
      $cols = $Qcategories->toArray();

      $cols['categories_id'] = $cols['orig_category_id'];
      $cols['language_id'] = (int)$insert_language_id;

      unset($cols['orig_category_id']);

      $this->app->db->save('categories_description', $cols);
    }
  }

  /**
   * Executes the method logic based on the application status and input parameters.
   *
   * @return bool Returns false if the application status is not defined or is set to 'False'; otherwise, proceeds with execution.
   */
  public function execute()
  {

    if (!\defined('CLICSHOPPING_APP_CATEGORIES_CT_STATUS') || CLICSHOPPING_APP_CATEGORIES_CT_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Langues'], $_GET['Insert'])) {
      $this->insert();
    }
  }
}