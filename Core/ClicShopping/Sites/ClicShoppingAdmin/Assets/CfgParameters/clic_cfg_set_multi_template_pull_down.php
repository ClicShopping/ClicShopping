<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * Directory list file with a drop down for products_favorites
 * @param $filename
 * @param string $key
 * @return string
 */
function clic_cfg_set_multi_template_pull_down($filename, $key = '')
{
  $module = $_GET['set'];

  $CLICSHOPPING_Db = Registry::get('Db');
  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

  $template_directory = $CLICSHOPPING_Template->getDirectoryPathModuleShopTemplateHtml($module);

  if ($contents = @scandir($template_directory)) {
    $fileTypes = ['php']; // Create an array of file types
    $found = []; // Traverse the folder, and add filename to $found array if type matches

    $name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');

    foreach ($contents as $item) {
      $fileInfo = pathinfo($item);
      if (array_key_exists('extension', $fileInfo) && \in_array($fileInfo['extension'], $fileTypes)) {
        $found[] = $item;
      }
    }

    if ($found) { // Check the $found array is not empty
      natcasesort($found); // Sort in natural, case-insensitive order, and populate menu
      $filename_array = [];

      foreach ($found as $filename) {
        $filename_array[] = [
          'id' => $filename,
          'text' => $filename
        ];
      }
    }
  }

  $QfileName = $CLICSHOPPING_Db->prepare('select configuration_value
                                            from :table_configuration
                                            where configuration_key = :configuration_key
                                           ');
  $QfileName->bindValue(':configuration_key', $key);

  $QfileName->execute();

  $filename_value = $QfileName->value('configuration_value');

  return HTML::selectMenu($name, $filename_array, $filename_value);
}
