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
 * Class ht_googlefont
 *
 * This class is a module that integrates Google Fonts into the header tags of the ClicShopping template system.
 * It manages enabling, disabling, installation, and configuration of the Google Fonts integration within the system.
 */
class ht_googlefont
{
  public string $code;
  public $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  /**
   * Constructor method for initializing the header tags Google Font module.
   *
   * @return void
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = 'header_tags';
    $this->title = CLICSHOPPING::getDef('module_header_tags_google_font_title');
    $this->description = CLICSHOPPING::getDef('module_header_tags_google_font_description');

    if (\defined('MODULE_HEADER_TAGS_GOOGLE_FONT_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_GOOGLE_FONT_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_HEADER_TAGS_GOOGLE_FONT_STATUS == 'True');
    }
  }

  /**
   * Executes the process of adding a preconnect link block for Google Fonts to the template.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $google = '<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>';

    $CLICSHOPPING_Template->addBlock($google . "\n", $this->group);
  }

  /**
   * Checks whether the current instance is enabled.
   *
   * @return bool Returns true if the instance is enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Checks if the constant 'MODULE_HEADER_TAGS_GOOGLE_FONT_STATUS' is defined.
   *
   * @return bool Returns true if the constant is defined, false otherwise.
   */
  public function check()
  {
    return \defined('MODULE_HEADER_TAGS_GOOGLE_FONT_STATUS');
  }

  /**
   * Installs the required configuration settings for the module in the database.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_FONT_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to install this module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_HEADER_TAGS_GOOGLE_FONT_SORT_ORDER',
        'configuration_value' => '50',
        'configuration_description' => 'Sort order. Lowest is displayed in first',
        'configuration_group_id' => '6',
        'sort_order' => '25',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

  }

  /**
   * Removes configuration entries from the database where the keys match
   * the values returned by the keys() method.
   *
   * @return int The number of rows affected by the delete operation.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Retrieves the configuration keys associated with the Google Font module.
   *
   * @return array An array of configuration keys related to the Google Font module.
   */
  public function keys()
  {
    return array('MODULE_HEADER_TAGS_GOOGLE_FONT_STATUS',
      'MODULE_HEADER_TAGS_GOOGLE_FONT_SORT_ORDER');
  }
}

