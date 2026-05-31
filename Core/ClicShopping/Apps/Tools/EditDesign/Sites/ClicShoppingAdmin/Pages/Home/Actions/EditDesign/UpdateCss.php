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

    // Strict whitelist: a single directory segment and a *.css filename — blocks path traversal.
    if (preg_match('/^[A-Za-z0-9_\-]+$/', $directory_selected) !== 1
      || preg_match('/^[A-Za-z0-9_.\-]+\.css$/', $filename_selected) !== 1) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_does_not_exist'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditCss&action=directory&directory_css=' . $directory_selected);
      return false;
    }

    if (CodeSecurity::isCssSafe($code) === false) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_insert_php_code'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditCss&action=directory&directory_css=' . $directory_selected . '&filename=' . $filename_selected);
      return;
    }

    $root = CLICSHOPPING::getConfig('dir_root', 'Shop');
    $lang_dir = $CLICSHOPPING_Language->get('directory');

    // The override is always written into the active theme (never into Default unless Default is active).
    // A custom theme thus receives only the files it overrides; everything else still falls back to Default.
    $themeCssRoot = $root . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/css/';
    $themeLangDir = is_dir($themeCssRoot . $lang_dir) ? $lang_dir : 'english';

    $targetDir = $themeCssRoot . $themeLangDir . '/' . $directory_selected . '/';
    $targetPath = $targetDir . $filename_selected;

    // Create the override directory if it does not exist yet (overriding a Default-only file).
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_not_writeable'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditCss&action=directory&directory_css=' . $directory_selected . '&filename=' . $filename_selected);
      return false;
    }

    // Containment check: the resolved directory must stay inside the active theme css root.
    $realThemeCssRoot = realpath($themeCssRoot);
    $realTargetDir = realpath($targetDir);

    if ($realThemeCssRoot === false || $realTargetDir === false
      || !str_starts_with($realTargetDir . DIRECTORY_SEPARATOR, $realThemeCssRoot . DIRECTORY_SEPARATOR)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_does_not_exist'), 'error');
      $CLICSHOPPING_EditDesign->redirect('EditCss&action=directory&directory_css=' . $directory_selected);
      return false;
    }

    if (FileSystem::isWritable($targetPath)) {
      $file = new \SplFileObject($targetPath, 'w');
      $file->fwrite($code);
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('success_file_saved_sucessfully'), 'success');
    } else {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_EditDesign->getDef('error_file_not_writeable'), 'error');
    }

    $CLICSHOPPING_EditDesign->redirect('EditCss&action=directory&directory_css=' . $directory_selected . '&filename=' . $filename_selected);
  }
}
