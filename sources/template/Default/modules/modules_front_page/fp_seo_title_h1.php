<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class fp_seo_title_h1
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

    $this->title = CLICSHOPPING::getDef('module_seo_title_h1_title');
    $this->description = CLICSHOPPING::getDef('module_seo_title_h1_description');

    if (\defined('MODULE_SEO_TITLE_H1_STATUS')) {
      $this->sort_order = \defined('MODULE_SEO_TITLE_H1_SORT_ORDER') ? (int)MODULE_SEO_TITLE_H1_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_SEO_TITLE_H1_STATUS') ? (MODULE_SEO_TITLE_H1_STATUS == 'True') : false;
    }
  }

  /**
   * Get the default title H1
   * @param int $language_id
   * @return string
   */
  private static function getSeoDefaultLanguageTitleH1(int $language_id): string
  {
    $Qseo = Registry::get('Db')->get('seo', 'seo_defaut_language_title_h1', ['language_id' => $language_id]);

    return $Qseo->value('seo_defaut_language_title_h1');
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Category = Registry::get('Category');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (CLICSHOPPING::getBaseNameIndex() && !$CLICSHOPPING_Category->getPath()) {
      $content_width = \defined('MODULE_SEO_TITLE_H1_CONTENT_WIDTH') ? (int)MODULE_SEO_TITLE_H1_CONTENT_WIDTH : 12;
      $language_id = $CLICSHOPPING_Language->getId();

// Recuperation de la page d'acceuil personnalisee
      if (!empty(static::getSeoDefaultLanguageTitleH1($language_id))) {
        $content = '<!-- page_seo_title start -->' . "\n";
        $title = static::getSeoDefaultLanguageTitleH1($language_id);
        ob_start();
        require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/page_seo_title_h1'));
        $content = ob_get_clean();

        $content .= '<!-- page_seo_title end -->' . "\n";

        $CLICSHOPPING_Template->addBlock($content, $this->group);
      }
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_SEO_TITLE_H1_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');


    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_SEO_TITLE_H1_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the module width',
        'configuration_key' => 'MODULE_SEO_TITLE_H1_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_SEO_TITLE_H1_SORT_ORDER',
        'configuration_value' => '5',
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
      'MODULE_SEO_TITLE_H1_STATUS',
      'MODULE_SEO_TITLE_H1_CONTENT_WIDTH',
      'MODULE_SEO_TITLE_H1_SORT_ORDER'
    );
  }
}
