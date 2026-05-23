<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Class si_sitemap_summary
 *
 * Displays a summary of the sitemap on the site. Handles module configuration, caching, and rendering.
 *
 * @package ClicShopping\Modules\Sitemap
 * @copyright 2008 - https://www.clicshopping.org
 * @license GPL 2 & MIT
 */
class si_sitemap_summary
{
  /**
   * @var string Module code (class name)
   */
  public string $code;

  /**
   * @var string Module group (directory name)
   */
  public string $group;

  /**
   * @var string Module title
   */
  public $title;

  /**
   * @var string Module description
   */
  public $description;

  /**
   * @var int|null Sort order for display
   */
  public int|null $sort_order = 0;

  /**
   * @var bool Module enabled status
   */
  public bool $enabled = false;

  /**
   * @var string Cache block identifier prefix
   */
  private mixed $cache_block;

  /**
   * @var int Language ID
   */
  private mixed $lang;

  /**
   * Constructor. Initializes module properties and configuration.
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('modules_sitemap_summary_title');
    $this->description = CLICSHOPPING::getDef('modules_sitemap_summary_description');

    if (\defined('MODULES_SITEMAP_SUMMARY_STATUS')) {
      $this->sort_order = \defined('MODULES_SITEMAP_SUMMARY_SORT_ORDER') ? (int)MODULES_SITEMAP_SUMMARY_SORT_ORDER : 0;
      $this->enabled = (MODULES_SITEMAP_SUMMARY_STATUS == 'True');
    }

    $this->cache_block = 'sitemap_summary_';
    $this->lang = Registry::get('Language')->getId();
  }

  /**
   * Executes the module logic: displays the sitemap summary if enabled and handles caching.
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');
    $content_width = \defined('MODULES_SITEMAP_SUMMARY_CONTENT_WIDTH') ? (int)MODULES_SITEMAP_SUMMARY_CONTENT_WIDTH : 0 ;

    // Essential to avoid conflicts
    if (isset($_GET['SiteMap'])) {
      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        // Cache based on language as the sitemap may vary by language
        $cache_id = $this->cache_block . $this->lang;
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

      $sitemap_summary = '<!-- sitemap summary start -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/sitemap_summary'));
      $sitemap_summary .= ob_get_clean();

      $sitemap_summary .= '<!-- sitemap summary end -->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $sitemap_summary);
      }

      $CLICSHOPPING_Template->addBlock($sitemap_summary, $this->group);
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
    return \defined('MODULES_SITEMAP_SUMMARY_STATUS');
  }

  /**
   * Installs the module configuration into the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_SITEMAP_SUMMARY_STATUS',
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
        'configuration_key' => 'MODULES_SITEMAP_SUMMARY_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULES_SITEMAP_SUMMARY_SORT_ORDER',
        'configuration_value' => '100',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes the module configuration from the database.
   *
   * @return int
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
    return array('MODULES_SITEMAP_SUMMARY_STATUS',
      'MODULES_SITEMAP_SUMMARY_CONTENT_WIDTH',
      'MODULES_SITEMAP_SUMMARY_SORT_ORDER'
    );
  }
}
