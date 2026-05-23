<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class co_contact_us_privacy_condition
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;
  private mixed $cache_block;
  private mixed $lang;
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'contact_us_privacy_condition_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('modules_contact_us_privacy_condition_title');
    $this->description = CLICSHOPPING::getDef('modules_contact_us_privacy_condition_description');

    if (\defined('MODULES_CONTACT_US_PRIVACY_CONDITION_STATUS')) {
      $this->sort_order = \defined('MODULES_CONTACT_US_PRIVACY_CONDITION_SORT_ORDER') ? (int)MODULES_CONTACT_US_PRIVACY_CONDITION_SORT_ORDER : 0;
      $this->enabled = \defined('MODULES_CONTACT_US_PRIVACY_CONDITION_STATUS') ? (MODULES_CONTACT_US_PRIVACY_CONDITION_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');

    if (isset($_GET['Info'], $_GET['Contact']) && !isset($_GET['Success'])) {
      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        // Cache based only on language as the introduction text is static
        $cache_id = $this->cache_block . $this->lang;
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

      $content_width = \defined('MODULES_CONTACT_US_PRIVACY_CONDITION_CONTENT_WIDTH') ? (int)MODULES_CONTACT_US_PRIVACY_CONDITION_CONTENT_WIDTH : 12;

      if (\defined('DISPLAY_PRIVACY_CONDITIONS') && DISPLAY_PRIVACY_CONDITIONS == 'true') {
        $privacy_condition = '<!-- Start contact us privacy condition -->' . "\n";

        ob_start();
        require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/contact_us_privacy_condition'));
        $privacy_condition .= ob_get_clean();

        $privacy_condition .= '<!-- End contact us privacy condition -->' . "\n";

        if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
          $CLICSHOPPING_TemplateCache->setCache($cache_id, $privacy_condition);
        }

        $CLICSHOPPING_Template->addBlock($privacy_condition, $this->group);
      }
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_CONTACT_US_PRIVACY_CONDITION_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_CONTACT_US_PRIVACY_CONDITION_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the module',
        'configuration_key' => 'MODULES_CONTACT_US_PRIVACY_CONDITION_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );


    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULES_CONTACT_US_PRIVACY_CONDITION_SORT_ORDER',
        'configuration_value' => '450',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '10',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  public function keys()
  {
    return array('MODULES_CONTACT_US_PRIVACY_CONDITION_STATUS',
      'MODULES_CONTACT_US_PRIVACY_CONDITION_CONTENT_WIDTH',
      'MODULES_CONTACT_US_PRIVACY_CONDITION_SORT_ORDER'
    );
  }
}
