<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Settings\Sites\ClicShoppingAdmin\Pages\Home\Actions\SettingsPopUp;

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Settings');
  }

  /**
   * @return void
   */
  public function execute()
  {
    $configuration_value = $_POST['configuration_value'] ?? '';

    if (isset($_POST['configuration'])) {
      foreach ($_POST['configuration'] as $value) {
        $configuration_value = $value;
      }
    }

    $cID = HTML::sanitize($_GET['cID']);
    $gID = HTML::sanitize($_GET['gID']);

    if (!isset($_POST['formid']) || !\is_string($_POST['formid']) || empty($_SESSION['sessiontoken']) || !hash_equals($_SESSION['sessiontoken'], $_POST['formid'])) {
      Registry::get('MessageStack')->add(CLICSHOPPING::getDef('no_access_warning'), 'warning');
      $this->app->redirect('Settings&gID=' . $gID);
    }

    if (!$this->app->saveValue((int)$cID, (int)$gID, $configuration_value)) {
      Registry::get('MessageStack')->add(CLICSHOPPING::getDef('no_access_warning'), 'warning');
      $this->app->redirect('Settings&gID=' . $gID);
    }

    Cache::clear('configuration');

    $this->app->redirect('Settings&gID=' . $gID . '&cID=' . $cID);
  }
}