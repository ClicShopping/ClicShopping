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
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class UpdateCss extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EditDesign = Registry::get('EditDesign');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');
    $CLICSHOPPING_Language = Registry::get('Language');

    $directory_selected = HTML::sanitize($_POST['directory_css'] ?? '');
    $filename_selected = HTML::sanitize($_POST['filename'] ?? '');
    $code = $_POST['code'] ?? '';

    $lang_dir = $CLICSHOPPING_Language->get('directory');
    $basePathLang = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . "/css/{$lang_dir}/{$directory_selected}/";
    $basePathFallback = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . "/css/english/{$directory_selected}/";

    $filePath = realpath($basePathLang . $filename_selected);
    if ($filePath === false || strpos($filePath, realpath($basePathLang)) !== 0 || !is_file($filePath)) {
      $filePath = realpath($basePathFallback . $filename_selected);
      if ($filePath === false || strpos($filePath, realpath($basePathFallback)) !== 0 || !is_file($filePath)) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_does_not_exist'), 'error');
        $CLICSHOPPING_EditDesign->redirect('EditCss&action=directory&directory_css=' . $directory_selected);
        return false;
      }
    }

    $extension = pathinfo($filename_selected, PATHINFO_EXTENSION);

    if ($extension === 'css') {
      if (CodeSecurity::isCssSafe($code) === false) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_insert_php_code'), 'error');
        $CLICSHOPPING_EditDesign->redirect('EditCss&action=directory&directory_css=' . $directory_selected . '&filename=' . $filename_selected);
        return;
      }
    } else {
      if (CodeSecurity::isPhpSafe($code) === false) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_insert_php_code'), 'error');
        $CLICSHOPPING_EditDesign->redirect('EditCss&action=directory&directory_css=' . $directory_selected . '&filename=' . $filename_selected);
        return;
      }
    }

    if (FileSystem::isWritable($filePath)) {
      $file = new \SplFileObject($filePath, "w");
      $file->fwrite($code);
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('success_file_saved_sucessfully'), 'success');
    } else {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_not_writeable'), 'error');
    }

    $CLICSHOPPING_EditDesign->redirect('EditCss&action=directory&directory_css=' . $directory_selected . '&filename=' . $filename_selected);
  }
}

