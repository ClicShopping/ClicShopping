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
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class UpdateGabari extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EditDesign = Registry::get('EditDesign');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $filename_selected = HTML::sanitize($_POST['filename']);
    $directory_selected = $_POST['directory_html'] ?? ''; // variable manquante, ajoutée

    $code = $_POST['code'] ?? '';

    $baseDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/files/';

    $filePath = realpath($baseDir . $filename_selected);

    // Sécuriser le chemin pour éviter directory traversal
    if ($filePath === false || strpos($filePath, realpath($baseDir)) !== 0) {
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
      $written = $file->fwrite($code);
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('success_file_saved_sucessfully'), 'success');
    } else {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_not_writeable'), 'error');
    }

    $CLICSHOPPING_EditDesign->redirect('EditGabari&action=filename&filename=' . $filename_selected);
  }
}

