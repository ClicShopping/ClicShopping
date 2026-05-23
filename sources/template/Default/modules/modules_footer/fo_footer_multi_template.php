<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class fo_footer_multi_template
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;
  public $pages;
  private mixed $cache_block;
  private mixed $lang;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'footer_multi_template_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_footer_multi_template_title');
    $this->description = CLICSHOPPING::getDef('module_footer_multi_template_description');

    if (\defined('MODULE_FOOTER_MULTI_TEMPLATE_STATUS')) {
      $this->sort_order = \defined('MODULE_FOOTER_MULTI_TEMPLATE_SORT_ORDER') ? (int)MODULE_FOOTER_MULTI_TEMPLATE_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_FOOTER_MULTI_TEMPLATE_STATUS') ? (MODULE_FOOTER_MULTI_TEMPLATE_STATUS == 'True') : false;
      $this->pages = \defined('MODULE_FOOTER_MULTI_TEMPLATE_DISPLAY_PAGES') ? MODULE_FOOTER_MULTI_TEMPLATE_DISPLAY_PAGES : 'all';
    }
  }

 /**
   * Executes the module logic, handles caching, and renders the multi-template footer.
   *
   * - Checks if the module is enabled and if the customer is allowed to view.
   * - Uses cache if enabled and available.
   * - Prepares social network URLs and structured data for the footer.
   * - Loads the selected footer template and renders it.
   * - Adds the rendered block to the template system.
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_PageManagerShop = Registry::get('PageManagerShop');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');

    if (MODE_VENTE_PRIVEE == 'false' || (MODE_VENTE_PRIVEE == 'true' && $CLICSHOPPING_Customer->isLoggedOn())) {
      if ($this->enabled) {
        if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
          $cache_id = $this->cache_block . $this->lang;
          $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

          if ($cache_output !== false) {
            $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
            return;
          }
        }

        $content_width = \defined('MODULE_FOOTER_MULTI_TEMPLATE_CONTENT_WIDTH') ? (int)MODULE_FOOTER_MULTI_TEMPLATE_CONTENT_WIDTH : 12;
        $menu_footer = $CLICSHOPPING_PageManagerShop->pageManagerDisplayFooterMenu();

        $footer_tag = '<!-- Start footer social footer -->' . "\n";
        $footer_tag .= '<script type="application/ld+json">' . "\n";

        $facebook_url  = \defined('MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_FACEBOOK_URL')  ? MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_FACEBOOK_URL  : '';
        $twitter_url   = \defined('MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_TWITTER_URL')   ? MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_TWITTER_URL   : '';
        $pinterest_url = \defined('MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_PINTEREST_URL') ? MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_PINTEREST_URL : '';

        $json = [
          '@context' => 'https://schema.org',
          '@type'    => 'Organization',
          'name'     => STORE_NAME,
          'url'      => CLICSHOPPING::getConfig('http_server', 'Shop'),
        ];

        $sameAs = [];

        if (!empty($facebook_url))  $sameAs[] = $facebook_url;
        if (!empty($twitter_url))   $sameAs[] = $twitter_url;
        if (!empty($pinterest_url)) $sameAs[] = $pinterest_url;

        if (!empty($sameAs)) {
          $json['sameAs'] = $sameAs;
        }

        $footer_tag .= json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        $footer_tag .= '</script>' . "\n";
        $footer_tag .= '<!-- end footer social footer -->' . "\n";

        $CLICSHOPPING_Template->addBlock($footer_tag, 'footer_scripts');

        $social_footer = '<!-- footer social footer -->' . "\n";

        $footer_template = '<!-- footer multi template start -->' . "\n";

        $filename = $CLICSHOPPING_Template->getTemplateModulesFilename($this->group . '/template_html/' . MODULE_FOOTER_MULTI_TEMPLATE);

        $facebook = \defined('MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_FACEBOOK_URL') ? MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_FACEBOOK_URL : '';
        if (!empty($facebook)) {
          $facebook_url = rawurldecode($facebook);
        } else {
          $facebook_url = '#';
        }

        $twitter = \defined('MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_TWITTER_URL') ? MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_TWITTER_URL : '';
        if (!empty($twitter)) {
          $twitter_url = rawurldecode(MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_TWITTER_URL);
        } else {
          $twitter_url = '#';
        }

        $pinterest = \defined('MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_PINTEREST_URL') ? MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_PINTEREST_URL : '';
        if (!empty($pinterest)) {
          $pinterest_url = rawurldecode($pinterest);
        } else {
          $pinterest_url = '#';
        }

        if (\defined('MODULES_HEADER_TAGS_MAILCHIMP_LIST_ANONYMOUS')) {
          if (!empty(MODULES_HEADER_TAGS_MAILCHIMP_LIST_ANONYMOUS)) {
            $mailchimp_list_anonymous = MODULES_HEADER_TAGS_MAILCHIMP_LIST_ANONYMOUS;
          }
        }

        if (is_file($filename)) {
          ob_start();
          require_once($filename);
          $footer_template .= ob_get_clean();
        } else {
          echo '<div class="alert alert-warning text-center" role="alert">' . CLICSHOPPING::getDef('template_does_not_exist') . '</div>';
          exit;
        }

        $footer_template .= '<!-- footer multi template end -->' . "\n";

        if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
          $CLICSHOPPING_TemplateCache->setCache($cache_id, $footer_template);
        }

        $CLICSHOPPING_Template->addBlock($footer_template, $this->group);
      }
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
    return \defined('MODULE_FOOTER_MULTI_TEMPLATE_STATUS');
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
        'configuration_key' => 'MODULE_FOOTER_MULTI_TEMPLATE_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the template you want to use ?',
        'configuration_key' => 'MODULE_FOOTER_MULTI_TEMPLATE',
        'configuration_value' => 'footer_multi_template.php',
        'configuration_description' => 'Select the the template you want to use.',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_multi_template_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please, select the width of your module ?',
        'configuration_key' => 'MODULE_FOOTER_MULTI_TEMPLATE_CONTENT_WIDTH',
        'configuration_value' => '3',
        'configuration_description' => 'Indicate a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the privacy message (need mailchimp module) ?',
        'configuration_key' => 'MODULE_FOOTER_MULTI_TEMPLATE_MAILCHIMP_DISPLAY_PRIVACY',
        'configuration_value' => 'False',
        'configuration_description' => 'Display the privacy message (need mailchimp module)',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the Facebook URL ?',
        'configuration_key' => 'MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_FACEBOOK_URL',
        'configuration_value' => '',
        'configuration_description' => '',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the Twitter URL ?',
        'configuration_key' => 'MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_TWITTER_URL',
        'configuration_value' => '',
        'configuration_description' => '',
        'configuration_group_id' => '6',
        'sort_order' => '6',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the Pointerest URL ?',
        'configuration_key' => 'MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_PINTEREST_URL',
        'configuration_value' => '',
        'configuration_description' => '',
        'configuration_group_id' => '6',
        'sort_order' => '7',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_FOOTER_MULTI_TEMPLATE_SORT_ORDER',
        'configuration_value' => '200',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '9',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate the page where the module is displayed',
        'configuration_key' => 'MODULE_FOOTER_MULTI_TEMPLATE_DISPLAY_PAGES',
        'configuration_value' => 'all',
        'configuration_description' => 'Select the pages where the boxe must be present.',
        'configuration_group_id' => '6',
        'sort_order' => '10',
        'set_function' => 'clic_cfg_set_select_pages_list',
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
    return array('MODULE_FOOTER_MULTI_TEMPLATE_STATUS',
      'MODULE_FOOTER_MULTI_TEMPLATE',
      'MODULE_FOOTER_MULTI_TEMPLATE_CONTENT_WIDTH',
      'MODULE_FOOTER_MULTI_TEMPLATE_MAILCHIMP_DISPLAY_PRIVACY',
      'MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_FACEBOOK_URL',
      'MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_TWITTER_URL',
      'MODULE_FOOTER_MULTI_TEMPLATE_CONTENTS_PINTEREST_URL',
      'MODULE_FOOTER_MULTI_TEMPLATE_SORT_ORDER',
      'MODULE_FOOTER_MULTI_TEMPLATE_DISPLAY_PAGES'
    );
  }
}