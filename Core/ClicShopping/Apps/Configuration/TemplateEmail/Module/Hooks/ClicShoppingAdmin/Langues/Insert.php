<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TemplateEmail\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\Apps\Configuration\TemplateEmail\TemplateEmail;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;

class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  private mixed $lang;

  /**
   * Class constructor initializes the TemplateEmail instance in the Registry
   * if it does not already exist. Retrieves the TemplateEmail and Language
   * objects from the Registry and assigns them to the class properties.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('TemplateEmail')) {
      Registry::set('TemplateEmail', new TemplateEmail());
    }

    $this->app = Registry::get('TemplateEmail');
    $this->lang = Registry::get('Language');
  }

  /**
   * Inserts new records into the template_email_description table by copying existing records
   * for the current language and associating them with the latest language ID.
   *
   * @return void
   */
  private function insert()
  {
    $insert_language_id = LanguageAdmin::getLatestLanguageID();
    $QtemplateEmailDescription = $this->app->db->prepare('select t.template_email_id as orig_template_email_id,
                                                                     te.*
                                                              from :table_template_email t left join :table_template_email_description te on t.template_email_id = te.template_email_id
                                                              where te.language_id = :language_id
                                                             ');

    $QtemplateEmailDescription->bindInt(':language_id', (int)$this->lang->getId());
    $QtemplateEmailDescription->execute();

    while ($QtemplateEmailDescription->fetch()) {
      $cols = $QtemplateEmailDescription->toArray();

      $cols['template_email_id'] = $cols['orig_template_email_id'];
      $cols['language_id'] = (int)$insert_language_id;

      unset($cols['orig_template_email_id']);

      $this->app->db->save('template_email_description', $cols);
    }
  }

  /**
   * Executes the main logic for the Template Email module.
   * Checks if the module is enabled and performs insertion logic if specific parameters are set.
   *
   * @return bool Returns false if the module is disabled or the status is set to 'False'.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_TEMPLATE_EMAIL_TE_STATUS') || CLICSHOPPING_APP_TEMPLATE_EMAIL_TE_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Langues'], $_GET['Insert'])) {
      $this->insert();
    }
  }
}