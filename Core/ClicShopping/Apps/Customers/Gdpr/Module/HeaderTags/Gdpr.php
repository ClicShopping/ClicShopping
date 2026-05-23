<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Module\HeaderTags;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Gdpr\Gdpr as GdprApp;

class Gdpr extends \ClicShopping\OM\Modules\HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $app;

  /**
   * Initializes the GDPR module by setting up the required application bindings,
   * loading language definitions, and configuring the module's properties such
   * as title, description, sort order, and enabled status.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('Gdpr')) {
      Registry::set('Gdpr', new GdprApp());
    }

    $this->app = Registry::get('Gdpr');
    $this->lang = Registry::get('Language');
    $this->group = 'footer_scripts'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/HeaderTags/gdpr');

    $this->title = $this->app->getDef('module_header_tags_gdpr_title');
    $this->description = $this->app->getDef('module_header_tags_gdpr_description');

    if (\defined('MODULE_HEADER_TAGS_GDPR_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_GDPR_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_GDPR_STATUS == 'True');
    }
  }

  /**
   * Checks if the module is enabled.
   *
   * @return bool Returns true if the module is enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the GDPR compliance footer script for integration into the template.
   *
   * The method builds a GDPR compliance banner script, retrieves necessary definitions and configurations,
   * and injects the script into the template's footer section.
   *
   * @return string|bool The generated GDPR footer script if enabled, or false if GDPR compliance is disabled.
   */
  public function getOutput()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (!\defined('CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_STATUS') || CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_STATUS == 'False') {
      return false;
    }

    $text_policy = $this->app->getDef('module_header_tags_text_info');
    $text_read_more = $this->app->getDef('module_header_tags_text_info_read_more');
    $text_banner_heading = $this->app->getDef('module_header_tags_text_info_title', ['store_name' => STORE_NAME]);
    $text_reject = $this->app->getDef('module_header_tags_text_info_reject');
    $text_accept = $this->app->getDef('module_header_tags_text_info_accept');
    $text_privacy = CLICSHOPPING::link(null, SHOP_CODE_URL_CONFIDENTIALITY, false, true);

    $footer_tag = '<!-- gdpr policies -->' . "\n";
    $footer_tag .= '<script src="https://cdn.jsdelivr.net/gh/manucaralmo/GlowCookies@3.1.7/src/glowCookies.min.js"></script>';
    $footer_tag .= '<script>';
    $footer_tag .= ' glowCookies.start(\'en\', { ';
    $footer_tag .= ' analytics: \'' . MODULE_HEADER_TAGS_GDPR_GOOGLE_ANALYTICS . '\', ';
    $footer_tag .= ' facebookPixel: \'' . MODULE_HEADER_TAGS_GDPR_FACEBOOK_PIXEL . '\', ';
    $footer_tag .= ' hideAfterClick: true, ';
    $footer_tag .= ' border: \'' . MODULE_HEADER_TAGS_GDPR_BORDER . '\', ';
    $footer_tag .= ' position:  \'' . MODULE_HEADER_TAGS_GDPR_POSITION . '\', ';
    $footer_tag .= ' policyLink: \'' . $text_privacy . '\', ';
    $footer_tag .= ' bannerDescription: \'<h6>' . $text_policy . '</h6>\', ';
    $footer_tag .= ' bannerLinkText: \'<h6>' . $text_read_more . '</h6>\', ';
    $footer_tag .= ' bannerBackground: \'' . MODULE_HEADER_TAGS_GDPR_BACKGROUND_COLOR . '\', ';
    $footer_tag .= ' bannerColor: \'' . MODULE_HEADER_TAGS_GDPR_FONT_BACKGROUND_COLOR . '\', ';
    $footer_tag .= ' bannerHeading: \'<h6> ' . $text_banner_heading . '</h6>\', ';
    $footer_tag .= ' acceptBtnText: \'' . $text_accept . '\', ';
    $footer_tag .= ' acceptBtnColor:  \'' . MODULE_HEADER_TAGS_GDPR_BUTTON_FONT_BACKGROUND_ACCEPT_COLOR . '\', ';
    $footer_tag .= ' acceptBtnBackground: \'' . MODULE_HEADER_TAGS_GDPR_BUTTON_BACKGROUND_ACCEPT_COLOR . '\', ';
    $footer_tag .= ' rejectBtnText: \'' . $text_reject . '\', ';
    $footer_tag .= ' rejectBtnBackground: \'' . MODULE_HEADER_TAGS_GDPR_BUTTON_BACKGROUND_REJECT_COLOR . '\', ';
    $footer_tag .= ' rejectBtnColor: \'' . MODULE_HEADER_TAGS_GDPR_BUTTON_FONT_BACKGROUND_REJECT_COLOR . '\' ';
    $footer_tag .= ' }); ';
    $footer_tag .= ' </script>' . "\n";

    $footer_tag .= '<!-- end products condition json_ltd -->' . "\n";

    $display_result = $CLICSHOPPING_Template->addBlock($footer_tag, $this->group);

    $output =
      <<<EOD
           {$display_result}
         EOD;

    return $output;
  }

  /**
   * Installs the GDPR module by saving its configuration settings into the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to install this module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Please insert the google analytics code',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_GOOGLE_ANALYTICS',
        'configuration_value' => '',
        'configuration_description' => 'google analytics code',
        'configuration_group_id' => '6',
        'sort_order' => '12',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'please insert the facebook pixel code',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_FACEBOOK_PIXEL',
        'configuration_value' => '',
        'configuration_description' => 'Facebook pixel code',
        'configuration_group_id' => '6',
        'sort_order' => '12',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Select the pop up position ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_POSITION',
        'configuration_value' => 'right',
        'configuration_description' => 'Please choose your position',
        'configuration_group_id' => '6',
        'sort_order' => '10',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'left\', \'right\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to display a border ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_BORDER',
        'configuration_value' => 'none',
        'configuration_description' => 'Display a border',
        'configuration_group_id' => '6',
        'sort_order' => '11',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'none\', \'border\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Background Color?',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_BACKGROUND_COLOR',
        'configuration_value' => '#fff',
        'configuration_description' => 'Display a background Color',
        'configuration_group_id' => '6',
        'sort_order' => '12',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Choose a background font color?',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_FONT_BACKGROUND_COLOR',
        'configuration_value' => '#505050',
        'configuration_description' => 'Display a font Color',
        'configuration_group_id' => '6',
        'sort_order' => '13',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Choose a background button accept color?',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_BUTTON_BACKGROUND_ACCEPT_COLOR',
        'configuration_value' => '#24273F',
        'configuration_description' => 'Display a background Color',
        'configuration_group_id' => '6',
        'sort_order' => '13',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Choose a button Accept font color?',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_BUTTON_FONT_BACKGROUND_ACCEPT_COLOR',
        'configuration_value' => '#fff',
        'configuration_description' => 'Display a font Color',
        'configuration_group_id' => '6',
        'sort_order' => '13',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Choose a background button reject color?',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_BUTTON_BACKGROUND_REJECT_COLOR',
        'configuration_value' => '#E8E8E8',
        'configuration_description' => 'Display a background Color',
        'configuration_group_id' => '6',
        'sort_order' => '14',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Choose a  button reject font color?',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_BUTTON_FONT_BACKGROUND_REJECT_COLOR',
        'configuration_value' => '#636363',
        'configuration_description' => 'Display a font Color',
        'configuration_group_id' => '6',
        'sort_order' => '13',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Display sort order',
        'configuration_key' => 'MODULE_HEADER_TAGS_GDPR_SORT_ORDER',
        'configuration_value' => '20',
        'configuration_description' => 'Display sort order (The lower is display in first)',
        'configuration_group_id' => '6',
        'sort_order' => '1000',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the configuration keys required for the GDPR header tags module.
   *
   * @return array An array of configuration key names for the module.
   */
  public function keys()
  {
    return [
      'MODULE_HEADER_TAGS_GDPR_STATUS',
      'MODULE_HEADER_TAGS_GDPR_GOOGLE_ANALYTICS',
      'MODULE_HEADER_TAGS_GDPR_FACEBOOK_PIXEL',
      'MODULE_HEADER_TAGS_GDPR_POSITION',
      'MODULE_HEADER_TAGS_GDPR_BORDER',
      'MODULE_HEADER_TAGS_GDPR_BACKGROUND_COLOR',
      'MODULE_HEADER_TAGS_GDPR_FONT_BACKGROUND_COLOR',
      'MODULE_HEADER_TAGS_GDPR_BUTTON_BACKGROUND_ACCEPT_COLOR',
      'MODULE_HEADER_TAGS_GDPR_BUTTON_FONT_BACKGROUND_ACCEPT_COLOR',
      'MODULE_HEADER_TAGS_GDPR_BUTTON_BACKGROUND_REJECT_COLOR',
      'MODULE_HEADER_TAGS_GDPR_BUTTON_FONT_BACKGROUND_REJECT_COLOR',
      'MODULE_HEADER_TAGS_GDPR_SORT_ORDER'
    ];
  }
}
