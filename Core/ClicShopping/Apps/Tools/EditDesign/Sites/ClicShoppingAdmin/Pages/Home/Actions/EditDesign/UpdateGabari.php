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

    $filename_selected = HTML::sanitize($_POST['filename'] ?? '');
    $code = $_POST['code'] ?? '';

    // Strict whitelist: a single *.php/*.css filename — blocks path traversal.
    if (preg_match('/^[A-Za-z0-9_.\-]+\.(php|css)$/', $filename_selected) !== 1) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_does_not_exist'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditGabari&action=filename');
      return false;
    }

    if (pathinfo($filename_selected, PATHINFO_EXTENSION) === 'css') {
      if (CodeSecurity::isCssSafe($code) === false) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_insert_php_code'), 'error');
        $CLICSHOPPING_EditDesign->redirect('EditGabari&action=filename&filename=' . $filename_selected);
        return false;
      }
    } elseif (CodeSecurity::isPhpSafe($code) === false) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_insert_php_code'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditGabari&action=filename&filename=' . $filename_selected);
      return false;
    }

    // The override is always written into the active theme files/ directory.
    $targetDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/files/';
    $targetPath = $targetDir . $filename_selected;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_not_writeable'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditGabari&action=filename&filename=' . $filename_selected);
      return false;
    }

    // Containment check: the resolved directory must stay inside the active theme files/ root.
    $realTargetDir = realpath($targetDir);

    if ($realTargetDir === false
      || !str_starts_with($realTargetDir . DIRECTORY_SEPARATOR, realpath(CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory()) . DIRECTORY_SEPARATOR)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_does_not_exist'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditGabari&action=filename');
      return false;
    }

    if (FileSystem::isWritable($targetPath)) {
      $file = new \SplFileObject($targetPath, 'w');
      $file->fwrite($code);
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('success_file_saved_sucessfully'), 'success');
    } else {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_not_writeable'), 'error');
    }

    $CLICSHOPPING_EditDesign->redirect('EditGabari&action=filename&filename=' . $filename_selected);
  }
}
