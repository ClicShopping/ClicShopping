<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\EditLogError\Sites\ClicShoppingAdmin\Pages\Home\Actions\LogError;

use ClicShopping\OM\DateTime;
use ClicShopping\OM\ErrorHandler;
use ClicShopping\OM\Registry;

class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EditLogError = Registry::get('EditLogError');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $files = [];

    foreach (glob(ErrorHandler::getDirectory() . 'errors-*.txt') as $f) {
      $key = basename($f, '.txt');

      if (preg_match('/^errors-([0-9]{4})([0-9]{2})([0-9]{2})$/', $key, $matches)) {
        $files[$key] = [
          'path' => $f,
          'key' => $key,
          'date' => DateTime::toShort($matches[1] . '-' . $matches[2] . '-' . $matches[3]),
          'size' => filesize($f)
        ];
      }
    }

    $requested = $_GET['log'] ?? '';

    if (isset($files[$requested])) {
      if (unlink($files[$requested]['path'])) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditLogError->getDef('ms_success_delete', ['log' => $files[$requested]['key']]), 'success');
      } else {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditLogError->getDef('ms_error_delete', ['log' => $files[$requested]['key']]), 'error');
      }
    }

    $CLICSHOPPING_EditLogError->redirect('LogError');
  }
}