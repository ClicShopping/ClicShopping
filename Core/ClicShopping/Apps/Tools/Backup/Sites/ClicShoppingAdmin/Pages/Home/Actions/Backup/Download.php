<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Backup\Sites\ClicShoppingAdmin\Pages\Home\Actions\Backup;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Download extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Backup');
  }


  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $extension = substr($_GET['file'], -3);
    $backup_directory = CLICSHOPPING::BASE_DIR . 'Work/Backups/';

    if (($extension == 'zip') || ($extension == '.gz') || ($extension == 'sql')) {
      if ($fp = fopen($backup_directory . $_GET['file'], 'rb')) {
        $buffer = fread($fp, filesize($backup_directory . $_GET['file']));
        fclose($fp);

        header('Content-type: application/x-octet-stream');
        header('Content-disposition: attachment; filename=' . $_GET['file']);

        echo $buffer;

        exit;
      }
    } else {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_download_link_not_acceptable'), 'error');
    }

    $this->app->redirect('Backup');
  }
}