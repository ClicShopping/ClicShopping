<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;

use ClicShopping\Apps\Configuration\Weight\Classes\Shop\Weight;

/**
 * @param $default
 * @param $key
 * @return string
 */
function clic_cfg_set_weight_classes_pulldown_menu($default, $key = null)
{
  $name = (empty($key)) ? 'configuration_value' : 'configuration[' . $key . ']';

  $weight_class_array = [];

  foreach (Weight::getClasses() as $class) {
    $weight_class_array[] = array('id' => $class['id'],
      'text' => $class['title']);
  }

  return HTML::selectMenu($name, $weight_class_array, $default);
}