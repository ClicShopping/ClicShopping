<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Settings\Sites\ClicShoppingAdmin\Pages\Home\Actions\SettingsPopUp;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Hash;
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
    if (isset($_POST['configuration'])) {
      foreach ($_POST['configuration'] as $value) {
        $configuration_value = $value;
      }
    } else {
      $configuration_value = $_POST['configuration_value'] ?? '';
    }

    $cID = HTML::sanitize($_GET['cID']);
    $gID = HTML::sanitize($_GET['gID']);

    // --- Récupération de la config actuelle ---
    $Qconf = $this->app->db->prepare('select set_function 
                                      from :table_configuration 
                                      where configuration_id = :cID
                                      ');
    $Qconf->bindInt(':cID', (int)$cID);
    $Qconf->execute();

    // --- Détection si champ password ---
    if (!empty($Qconf->value('set_function')) && str_contains($Qconf->value('set_function'), 'password')) {
      $configuration_value = Hash::encryptDatatext($configuration_value);
    }

    $this->app->db->save('configuration', [
      'configuration_value' => $configuration_value,
      'last_modified' => 'now()'
    ], [
        'configuration_id' => (int)$cID
      ]
    );

    Cache::clear('configuration');

    $this->app->redirect('Settings&gID=' . $gID . '&cID=' . $cID);
  }
}