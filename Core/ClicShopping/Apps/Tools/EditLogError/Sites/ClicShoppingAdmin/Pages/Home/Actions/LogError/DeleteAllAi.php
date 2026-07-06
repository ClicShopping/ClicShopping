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

class DeleteAllAi extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EditLogError = Registry::get('EditLogError');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $result = true;

    $files = [];

    foreach (glob(ErrorHandler::getDirectory() . 'ai_errors-*.txt') as $f) {
      $key = basename($f, '.txt');

      if (preg_match('/^ai_errors-([0-9]{4})([0-9]{2})([0-9]{2})$/', $key, $matches)) {
        $files[$key] = [
          'path' => $f,
          'key' => $key,
          'date' => DateTime::toShort($matches[1] . '-' . $matches[2] . '-' . $matches[3]),
          'size' => filesize($f)
        ];
      }
    }

    foreach ($files as $f) {
      if (!unlink($f['path'])) {
        $result = false;
      }
    }

    if ($result === true) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditLogError->getDef('ms_success_delete_all'), 'success');
    } else {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditLogError->getDef('ms_error_delete_all'), 'error');
    }

    $CLICSHOPPING_EditLogError->redirect('LogErrorAI');
  }
}
