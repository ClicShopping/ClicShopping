<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\Gdpr;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;
use function is_array;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/Shop/Account/';

      if (is_dir($source_folder)) {
        $files_get = $CLICSHOPPING_Template->getSpecificFiles($source_folder, 'AccountGdprCall*');

        if (is_array($files_get)) {
          foreach ($files_get as $value)
          {
            $name = Hash::displayDecryptedDataText($value['name']);
            if (!empty($name)) {
              $CLICSHOPPING_Hooks->call('Account', $name);
            }
          }
        }
      }

      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_success_gdpr'), 'success');
    }

    CLICSHOPPING::redirect(null, 'Account&Main');
  }
}