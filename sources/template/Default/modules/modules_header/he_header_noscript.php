<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class he_header_noscript
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;
  private mixed $cache_block;
  private mixed $lang;

 /**
   * he_header_noscript module
   *
   * Displays a noscript message in the header if JavaScript is disabled.
   * Handles module configuration, installation, removal, and caching.
   */
  public function __construct()
  {
    /**
     * Initializes the module properties and loads language definitions.
     */
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'header_noscript_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_header_noscript_title');
    $this->description = CLICSHOPPING::getDef('module_header_noscript_description');

    if (\defined('MODULE_HEADER_NOSCRIPT_STATUS')) {
      $this->sort_order = \defined('MODULE_HEADER_NOSCRIPT_SORT_ORDER') ? (int)MODULE_HEADER_NOSCRIPT_SORT_ORDER : 0;
      $this->enabled = (MODULE_HEADER_NOSCRIPT_STATUS == 'True');
    }
  }

  /**
   * Executes the module logic to display the noscript message.
   * Handles caching and adds the block to the template.
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');

    if ($this->enabled) {
      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $cache_id = $this->cache_block . $this->lang;
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

      $content_width = \defined('MODULE_HEADER_NOSCRIPT_CONTENT_WIDTH') ? (int)MODULE_HEADER_NOSCRIPT_CONTENT_WIDTH : 12;

      $header_template = '<!-- Start noscript header message -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/header_noscript'));
      $header_template .= ob_get_clean();

      $header_template .= '<!-- End noscript header message -->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $header_template);
      }

      $CLICSHOPPING_Template->addBlock($header_template, $this->group);
    }
  }

  /**
   * Checks if the module is enabled.
   *
   * @return bool
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Checks if the module configuration is defined.
   *
   * @return bool
   */
  public function check()
  {
    return \defined('MODULE_HEADER_NOSCRIPT_STATUS');
  }

  /**
   * Installs the module configuration in the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to activate this module?',
        'configuration_key' => 'MODULE_HEADER_NOSCRIPT_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to activate this module?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the width of the content',
        'configuration_key' => 'MODULE_HEADER_NOSCRIPT_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Please specify a display width',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_HEADER_NOSCRIPT_SORT_ORDER',
        'configuration_value' => '1',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes the module configuration from the database.
   *
   * @return int Number of affected rows
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Returns the configuration keys used by this module.
   *
   * @return array
   */
  public function keys()
  {
    return array('MODULE_HEADER_NOSCRIPT_STATUS',
      'MODULE_HEADER_NOSCRIPT_CONTENT_WIDTH',
      'MODULE_HEADER_NOSCRIPT_SORT_ORDER'
    );
  }
}
