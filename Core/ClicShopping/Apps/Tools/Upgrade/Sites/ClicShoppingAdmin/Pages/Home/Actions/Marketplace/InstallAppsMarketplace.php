<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Sites\ClicShoppingAdmin\Pages\Home\Actions\Marketplace;

use ClicShopping\Apps\Tools\Upgrade\Classes\ClicShoppingAdmin\ExtractFile;
use ClicShopping\Apps\Tools\Upgrade\Upgrade as UpgradeApp;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Upload;

class InstallAppsMarketplace extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  private mixed $extractFile;

  public function __construct()
  {
    if (!Registry::exists('Upgrade')) {
      Registry::set('Upgrade', new UpgradeApp());
    }

    $this->app = Registry::get('Upgrade');

    if (!Registry::exists('ExtractFile')) {
      Registry::set('ExtractFile', new ExtractFile());
    }

    $this->extractFile = Registry::get('ExtractFile');
    $this->messageStack = Registry::get('MessageStack');
  }

  /**
   * @return bool
   */
  private function saveFileUpload(): bool
  {
    $array_extension = ['zip'];

    $upload_file = new Upload('uploadApp', CLICSHOPPING::BASE_DIR . 'Work/Temp', null, $array_extension);

    if ($upload_file->check() && $upload_file->save()) {
      $uploadApp = $upload_file->getFilename();
      $_SESSION['app_json'] = $uploadApp;

      $dir = HTML::removeFileAccents($uploadApp);
      $dir_path = CLICSHOPPING::BASE_DIR . 'Work/Temp/' . $dir;

      $this->extractFile->getCloseOpenStore('true');
      $this->extractFile->installFiles($dir_path);
      $this->extractFile->getCloseOpenStore('false');

      return true;
    } else {
      return false;
    }
  }

  public function execute()
  {
    if (isset($_GET['InstallAppsMarketplace'], $_GET['Marketplace'])) {
      $error = false;
      $check_directory = $this->extractFile->checkDirectory();

      if ($check_directory === false) {
        $error = true;
      }

      if ($error === false) {
        $this->saveFileUpload();

        $this->messageStack->add($this->app->getDef('text_success_files_installed'), 'success', 'main');
        $this->app->redirect('Upgrade&MarketplaceSuccess');
      } else {
        $this->messageStack->add($this->app->getDef('error_file_not_installed'), 'error', 'main');
        $this->app->redirect('Upgrade&Marketplace');
      }
    }
  }
}