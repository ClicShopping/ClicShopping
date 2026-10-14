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

class UpdateListing extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EditDesign = Registry::get('EditDesign');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $directory_selected = HTML::sanitize($_POST['directory_html'] ?? '');
    $filename_selected = HTML::sanitize($_POST['filename'] ?? '');
    $code = $_POST['code'] ?? '';

    // Strict whitelist: a single directory segment and a *.php/*.css filename — blocks path traversal.
    if (preg_match('/^[A-Za-z0-9_\-]+$/', $directory_selected) !== 1
      || preg_match('/^[A-Za-z0-9_.\-]+\.(php|css)$/', $filename_selected) !== 1) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_does_not_exist'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditListing&action=directory&directory_html=' . $directory_selected);
      return false;
    }

    if (pathinfo($filename_selected, PATHINFO_EXTENSION) === 'css') {
      if (CodeSecurity::isCssSafe($code) === false) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_insert_php_code'), 'error');
        $CLICSHOPPING_EditDesign->redirect('EditListing&action=directory&directory_html=' . $directory_selected . '&filename=' . $filename_selected);
        return false;
      }
    } elseif (CodeSecurity::isPhpSafe($code) === false) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_insert_php_code'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditListing&action=directory&directory_html=' . $directory_selected . '&filename=' . $filename_selected);
      return false;
    }

    // The override is always written into the active theme module template_html/ directory.
    $modulesRoot = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/modules/';
    $targetDir = $modulesRoot . $directory_selected . '/template_html/';
    $targetPath = $targetDir . $filename_selected;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_not_writeable'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditListing&action=directory&directory_html=' . $directory_selected . '&filename=' . $filename_selected);
      return false;
    }

    // Containment check: the resolved directory must stay inside the active theme modules/ root.
    $realModulesRoot = realpath($modulesRoot);
    $realTargetDir = realpath($targetDir);

    if ($realModulesRoot === false || $realTargetDir === false
      || !str_starts_with($realTargetDir . DIRECTORY_SEPARATOR, $realModulesRoot . DIRECTORY_SEPARATOR)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_does_not_exist'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditListing&action=directory&directory_html=' . $directory_selected);
      return false;
    }

    if (FileSystem::isWritable($targetPath)) {
      $file = new \SplFileObject($targetPath, 'w');
      $file->fwrite($code);
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('success_file_saved_sucessfully'), 'success');
    } else {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_not_writeable'), 'error');
    }

    $CLICSHOPPING_EditDesign->redirect('EditListing&action=directory&directory_html=' . $directory_selected . '&filename=' . $filename_selected);
  }
}
