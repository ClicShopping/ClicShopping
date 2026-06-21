<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Communication\PageManager\PageManager as PageManagerApp;
use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;

class Insert implements HooksInterface
{
  public mixed $app;
  protected $insert_language_id;

  /**
   * Constructor method.
   *
   * Initializes the PageManager application if it does not already exist in the Registry,
   * and sets the `app` and `lang` properties with the PageManager and Language instances respectively.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('PageManager')) {
      Registry::set('PageManager', new PageManagerApp());
    }

    $this->app = Registry::get('PageManager');
    /**
     *
     */
      $this->lang = Registry::get('Language');
  }

  /**
   * Inserts new language entries into the pages_manager_description table based on the latest language ID.
   *
   * @return void
   */
  private function insert()
  {
    $insert_language_id = LanguageAdmin::getLatestLanguageID();

    $QpageManagerDescription = $this->app->db->prepare('select pm.pages_id as orig_pages_id,
                                                                 pmd.*
                                                          from :table_pages_manager pm left join :table_pages_manager_description pmd on pm.pages_id = pmd.pages_id
                                                          where pmd.language_id = :language_id
                                                        ');

    $QpageManagerDescription->bindInt(':language_id', (int)$this->lang->getId());
    $QpageManagerDescription->execute();

    while ($QpageManagerDescription->fetch()) {
      $cols = $QpageManagerDescription->toArray();

      $cols['pages_id'] = $cols['orig_pages_id'];
      $cols['language_id'] = (int)$insert_language_id;

      unset($cols['orig_pages_id']);

      $this->app->db->save('pages_manager_description', $cols);
    }
  }

  /**
   * Executes the necessary logic based on the defined application status.
   * It checks specific conditions related to language and insertion parameters and initiates the insert operation if applicable.
   *
   * @return bool Returns false if the application status is not defined or is disabled.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS') || CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Langues'], $_GET['Insert'])) {
      $this->insert();
    }
  }
}