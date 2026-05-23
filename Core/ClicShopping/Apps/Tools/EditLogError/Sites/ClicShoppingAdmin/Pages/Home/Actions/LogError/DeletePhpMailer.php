<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\EditLogError\Sites\ClicShoppingAdmin\Pages\Home\Actions\LogError;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\ErrorHandler;
use ClicShopping\OM\Registry;

class DeletePhpMailer extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EditLogError = Registry::get('EditLogError');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $files = [];

    foreach (glob(ErrorHandler::getDirectory() . 'phpmail_error-*.txt') as $f) {
      $key = basename($f, '.txt');

      if (preg_match('/^phpmail_error-([0-9]{4})([0-9]{2})([0-9]{2})$/', $key, $matches)) {
        $files[$key] = [
          'path' => $f,
          'key' => $key,
          'date' => DateTime::toShort($matches[1] . '-' . $matches[2] . '-' . $matches[3]),
          'size' => filesize($f)
        ];
      }
    }

    if (isset($_GET['log']) && isset($_GET['log'], $files)) {
      if (unlink($files[$_GET['log']]['path'])) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditLogError->getDef('ms_success_delete', ['log' => $files[$_GET['log']]['key']]), 'success');
      } else {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditLogError->getDef('ms_error_delete', ['log' => $files[$_GET['log']]['key']]), 'error');
      }
    }


    if (is_file(CLICSHOPPING::BASE_DIR . 'Work/Log/errors-' . date('Ymd') . '.txt')) {
      unlink(CLICSHOPPING::BASE_DIR . 'Work/Log/errors-' . date('Ymd') . '.txt');
    }

    $CLICSHOPPING_EditLogError->redirect('LogError');
  }
}