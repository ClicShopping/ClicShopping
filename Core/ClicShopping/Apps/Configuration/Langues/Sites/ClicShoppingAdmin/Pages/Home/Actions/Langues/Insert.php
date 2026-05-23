<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Langues\Sites\ClicShoppingAdmin\Pages\Home\Actions\Langues;

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\ClicShoppingAdmin\ModuleDownload;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  private mixed $lang;

  public function __construct()
  {
    $this->app = Registry::get('Langues');
    $this->lang = Registry::get('Language');
  }

  public function execute()
  {

    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $name = HTML::sanitize($_POST['name']);
    $code = HTML::sanitize(substr($_POST['code'], 0, 2));
    $image = HTML::sanitize($_POST['image']);
    $locale = HTML::sanitize($_POST['locale']);

    if (empty($_POST['directory_create'])) {
      $directory = HTML::sanitize($_POST['directory']);
    } else {
      $directory = HTML::sanitize($_POST['directory_create']);
    }

    $sort_order = (int)HTML::sanitize($_POST['sort_order']);

    if (isset($_POST['create_language']) && ($_POST['create_language'] == 'on') && !empty($_POST['directory_create'])) {

      $QlngDefaultDirectory = $this->app->db->prepare('select directory
                                                         from :table_languages
                                                         where code = :code
                                                        ');

      $QlngDefaultDirectory->bindValue(':code', DEFAULT_LANGUAGE);
      $QlngDefaultDirectory->execute();

      $lng_default_directory = $QlngDefaultDirectory->value('directory');

// ---------------------------------------------
// -- Copy the new language in admin directory
// ---------------------------------------------
      $source = CLICSHOPPING::getConfig('dir_root') . $CLICSHOPPING_Template->getLanguageDirectory() . '/' . $lng_default_directory;
      $dest = CLICSHOPPING::getConfig('dir_root') . $CLICSHOPPING_Template->getLanguageDirectory() . '/' . $directory;

      if (is_dir($source)) {
        ModuleDownload::smartCopy($source, $dest);
      }

// ---------------------------------------------
// copy the files in the language admin directory
// ---------------------------------------------
      $source_admin = CLICSHOPPING::getConfig('dir_root') . $CLICSHOPPING_Template->getLanguageDirectory() . '/' . $lng_default_directory . '.txt';
      $dest_admin = CLICSHOPPING::getConfig('dir_root') . $CLICSHOPPING_Template->getLanguageDirectory() . '/' . $directory . '.txt';

      if (is_file($source_admin)) {
        copy($source_admin, $dest_admin);
        chmod($dest_admin, 0644);
      }

// ---------------------------------------------
// -- Copy the new language in template catalog directory : original language
// ---------------------------------------------

      $source = $CLICSHOPPING_Template->getPathLanguageShopDirectory() . '/' . $lng_default_directory;
      $dest = $CLICSHOPPING_Template->getPathLanguageShopDirectory() . '/' . $directory;

      if (is_dir($source)) {
        ModuleDownload::smartCopy($source, $dest);
      }

// copy the files in the template language catalogue directory
      $source_catalogue = $CLICSHOPPING_Template->getPathLanguageShopDirectory() . '/' . $lng_default_directory . '.txt';
      $dest_catalogue = $CLICSHOPPING_Template->getPathLanguageShopDirectory() . '/' . $directory . '.txt';

      if (is_file($source_catalogue)) {
        copy($source_catalogue, $dest_catalogue);
        chmod($dest_catalogue, 0644);
      }

// ---------------------------------------------
// -- Copy the new language in template catalog directory for the add on module
// ---------------------------------------------
      /*
                $source = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/languages/' . $lng_default_directory;
                $dest = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/languages/' .  $directory;

                if (is_dir($source)) {
                  ModuleDownload::smartCopy($source, $dest);
                }

                $source_catalogue = CLICSHOPPING::getConfig('dir_root', 'Shop') .$CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/languages/' . $lng_default_directory.'.txt';
                $dest_catalogue = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/languages/' . $directory.'.txt';

                if (is_file($source_catalogue)) {
                  copy($source_catalogue, $dest_catalogue);
                  chmod($dest_catalogue, 0644);
                }
      */

// ---------------------------------------------------------
// -- Copy the new language in the template design directory
// ---------------------------------------------------------
      $source = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/css/' . $lng_default_directory;
      $dest = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/css/' . $directory;

      if (is_dir($source)) {
        ModuleDownload::smartCopy($source, $dest);
      }
    } // end checkbox

// ---------------------------------------------------------
// -- insert datas
// ---------------------------------------------------------
    $insert_array = [
      'name' => $name,
      'code' => $code,
      'image' => $image,
      'directory' => $directory,
      'sort_order' => (int)$sort_order,
      'status' => 0,
      'locale' => $locale
    ];

    $this->app->db->save('languages', $insert_array);

    $insert_id = $this->app->db->lastInsertId();

// create additional default configuration
    if (isset($_POST['default'])) {
      $this->app->db->save('configuration', ['configuration_value' => $code], ['configuration_key' => 'DEFAULT_LANGUAGE']);
    }

// create additional products_options records
    $Qoptions = $this->app->db->get('products_options', '*', ['language_id' => $this->lang->getId()]);

    while ($Qoptions->fetch()) {
      $cols = $Qoptions->toArray();

      $cols['language_id'] = $insert_id;

      $this->app->db->save('products_options', $cols);
    }

// create additional products_options_values records
    $Qvalues = $this->app->db->get('products_options_values', '*', ['language_id' => (int)$this->lang->getId()]);

    while ($Qvalues->fetch()) {
      $cols = $Qvalues->toArray();

      $cols['language_id'] = $insert_id;

      $this->app->db->save('products_options_values', $cols);
    }

    $CLICSHOPPING_Hooks->call('Langues', 'Insert');

    Cache::clear('languages-system-shop');
    Cache::clear('languages-system-admin');

    $this->app->redirect('Langues&page=' . $page);
  }
}