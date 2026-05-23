<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class securityCheck_check_github_directory
{
  public string $type = 'warning';
  public string $title;
  /**
   * Constructor for the class.
   * It initializes the necessary language definitions and sets the title property.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/SecurityCheck/check_github_directory', null, null, 'Shop');

    $this->title = CLICSHOPPING::getDef('check_github_directory_title');
  }

  /**
   * Checks whether the '.github' directory does not exist in the specified root directory.
   *
   * @return bool Returns true if the '.github' directory does not exist; otherwise, false.
   */
  public function pass()
  {
    return !is_dir(CLICSHOPPING::getConfig('dir_root', 'Shop') . '.github');
  }

  /**
   * Retrieves a localized warning message indicating the existence of the GitHub directory.
   *
   * @return string The warning message with the GitHub directory path included.
   */
  public function getMessage()
  {
    return CLICSHOPPING::getDef('warning_github_directory_exists', [
      'github_path' => CLICSHOPPING::getConfig('dir_root', 'Shop') . '.github'
    ]);
  }
}
  