<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

class CfgmShipping
{
  public string $code = 'shipping';
  public string $directory;
  public $language_directory;
  public string $site = 'Shop';
  public string $key = 'MODULE_SHIPPING_INSTALLED';
  public $title;
  public bool $template_integration = false;

  /**
   * Constructor method for initializing the shipping module.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $this->directory = $CLICSHOPPING_Template->getDirectoryPathModuleShop() . '/shipping/';
    $this->language_directory = $CLICSHOPPING_Template->getPathLanguageShopDirectory();
  }
}