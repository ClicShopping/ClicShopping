<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\EditDesign\Sites\ClicShoppingAdmin\Pages\Home\Actions\EditDesign;

use ClicShopping\Apps\Tools\EditDesign\Classes\CodeSecurity;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\Registry;

class UpdateContent extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EditDesign = Registry::get('EditDesign');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $directory_selected = $_POST['directory_html'] ?? '';
    $filename_selected = $_POST['filename'] ?? '';
    $code = $_POST['code'] ?? '';

    $basePath = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/modules/' . $directory_selected . '/content/';
    $filePath = realpath($basePath . $filename_selected);

    // Sécurité chemin
    if ($filePath === false || strpos($filePath, realpath($basePath)) !== 0 || !is_file($filePath)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_does_not_exist'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditModuleContent&action=directory&directory_html=' . $directory_selected);
      return false;
    }

    $extension = pathinfo($filename_selected, PATHINFO_EXTENSION);

    if ($extension === 'css') {
      if (CodeSecurity::isCssSafe($code) === false) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_insert_php_code'), 'error');
        $CLICSHOPPING_EditDesign->redirect('EditModuleContent&action=directory&directory_html=' . $directory_selected . '&filename=' . $filename_selected);
        return false;
      }
    } else {
      if (CodeSecurity::isPhpSafe($code) === false) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_insert_php_code'), 'error');
        $CLICSHOPPING_EditDesign->redirect('EditModuleContent&action=directory&directory_html=' . $directory_selected . '&filename=' . $filename_selected);
        return false;
      }
    }

    if (FileSystem::isWritable($filePath)) {
      $file = new \SplFileObject($filePath, "w");
      $file->fwrite($code);
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('success_file_saved_sucessfully'), 'success');
    } else {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_not_writeable'), 'error');
    }

    $CLICSHOPPING_EditDesign->redirect('EditModuleContent&action=directory&directory_html=' . $directory_selected . '&filename=' . $filename_selected);
  }
}

