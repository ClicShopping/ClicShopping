<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\Apps\Marketing\SEO\SEO as SEOApp;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;

class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  private mixed $lang;

  /**
   * Initializes the instance by setting up the SEO application and language.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('SEO')) {
      Registry::set('SEO', new SEOApp());
    }

    $this->app = Registry::get('SEO');
    $this->lang = Registry::get('Language');
  }

  /**
   * Inserts SEO data for a newly added language by duplicating existing records
   * from the current language into the database with the new language ID.
   *
   * @return void
   */
  private function insert()
  {
    $insert_language_id = LanguageAdmin::getLatestLanguageID();
    $QsubmitDescription = $this->app->db->get('seo', '*', ['language_id' => $this->lang->getId()]);

    while ($QsubmitDescription->fetch()) {
      $cols = $QsubmitDescription->toArray();

      $cols['language_id'] = (int)$insert_language_id;

      $this->app->db->save('seo', $cols);
    }
  }

  /**
   * Executes the primary logic for the method.
   * Checks if the required constant is defined and ensures the application SEO status is enabled.
   * If specific parameters are present in the request, it triggers the insert method.
   *
   * @return bool Returns false if the SEO application is disabled or not properly defined, otherwise no return value.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_SEO_SE_STATUS') || CLICSHOPPING_APP_SEO_SE_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Langues'], $_GET['Insert'])) {
      $this->insert();
    }
  }
}