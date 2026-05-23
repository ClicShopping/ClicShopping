<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class sf_footer_suffix_copyright
{
  /**
   * @var string Module code
   */
  public string $code;

  /**
   * @var string Module group
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
   * @var int|null Sort order
   */
  public int|null $sort_order = 0;

  /**
   * @var bool Module enabled status
   */
  public bool $enabled = false;

  /**
   * @var string Cache block identifier
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
    $this->cache_block = 'footer_suffix_copyright_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('modules_footer_suffix_copyright_title');
    $this->description = CLICSHOPPING::getDef('modules_footer_suffix_copyright_description');

    if (\defined('MODULES_FOOTER_SUFFIX_COPYRIGHT_STATUS')) {
      $this->sort_order = \defined('MODULES_FOOTER_SUFFIX_COPYRIGHT_SORT_ORDER') ? (int)MODULES_FOOTER_SUFFIX_COPYRIGHT_SORT_ORDER : 0;
      $this->enabled = (MODULES_FOOTER_SUFFIX_COPYRIGHT_STATUS == 'True');
    }
  }

  /**
   * Executes the module logic, handles caching and rendering.
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

      $logo = '<img width="24" height="24" alt="ClicShopping AI , Free Artificial intelligence E-commerce Open Source Solution B2B - B2C for everybody" title="ClicShopping, Free E-commerce Open Source Solution B2B - B2C for everybody" src="' . HTTP::getShopUrlDomain() . 'images/logo_clicshopping_24.webp">';
      $clicshopping_copyright = date('Y');
      $shop_owner_copyright = date('Y') . ' - ' . \defined('STORE_NAME') ? STORE_NAME : '';

      $footer_copyright = '<!-- footer copyright start -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/suffix_footer_copyright'));
      $footer_copyright .= ob_get_clean();

      $footer_copyright .= '<!-- footer copyright end -->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $footer_copyright);
      }

      $CLICSHOPPING_Template->addBlock($footer_copyright, $this->group);
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
    return \defined('MODULES_FOOTER_SUFFIX_COPYRIGHT_STATUS');
  }

  /**
   * Installs the module configuration in the database.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_FOOTER_SUFFIX_COPYRIGHT_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULES_FOOTER_SUFFIX_COPYRIGHT_SORT_ORDER',
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
    return array(
      'MODULES_FOOTER_SUFFIX_COPYRIGHT_STATUS',
      'MODULES_FOOTER_SUFFIX_COPYRIGHT_SORT_ORDER'
    );
  }
}
