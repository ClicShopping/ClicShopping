<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class cc_checkout_confirmation_law_hamon
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_checkout_confirmation_law_hamon_title');
    $this->description = CLICSHOPPING::getDef('module_checkout_confirmation_law_hamon_description');

    if (\defined('MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_STATUS')) {
      $this->sort_order = (int)MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_SORT_ORDER;
      $this->enabled = (MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_STATUS == 'True');
    }
  }

  public function execute()
  {

    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_GET['Checkout'], $_GET['Confirmation']) && $CLICSHOPPING_Customer->isLoggedOn()) {

      if (CONFIGURATION_LAW_HAMON == 'true') {
        $content_width = (int)MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_CONTENT_WIDTH;

        $footer = '<script>' . "\n";
        $footer .= ' function checkCheckBox(f){';
        $footer .= ' if (f.agree.checked === false )';
        $footer .= ' {';
        $footer .= ' alert(\'' . CLICSHOPPING::getDef('module_checkout_confirmation_law_hamon_text_error_agreement') . '\');';
        $footer .= ' return false;';
        $footer .= ' } else';
        $footer .= ' return true;';
        $footer .= ' }';
        $footer .= ' </script>' . "\n";

        $CLICSHOPPING_Template->addBlock($footer, 'footer_scripts');

        $confirmation = '  <!-- checkout confirmation law Hamon start -->' . "\n";

        ob_start();
        require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/checkout_confirmation_law_hamon'));

        $confirmation .= ob_get_clean();

        $confirmation .= '<!--  checkout confirmation law Hamon end -->' . "\n";

        $CLICSHOPPING_Template->addBlock($confirmation, $this->group);
      }
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_STATUS',
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
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_SORT_ORDER',
        'configuration_value' => '300',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
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
    return array(
      'MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_STATUS',
      'MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_CONTENT_WIDTH',
      'MODULE_CHECKOUT_CONFIRMATION_LAW_HAMON_SORT_ORDER'
    );
  }
}