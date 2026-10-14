<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

class CfgmService
{
  public string $code = 'service';
  public string $directory;
  public string $site = 'Shop';
  public string $key = 'MODULE_SERVICES_INSTALLED';
  public $title;
  public $language_directory;
  public bool $template_integration = false;

  /**
   * Initializes the object and sets the directory path for the service.
   *
   * @return void
   */
  public function __construct()
  {
    $this->directory = CLICSHOPPING::BASE_DIR . 'Service/Shop/';
  }
}