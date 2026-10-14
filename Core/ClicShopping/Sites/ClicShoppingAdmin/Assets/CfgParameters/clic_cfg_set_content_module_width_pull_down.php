<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;

/**
 * Generate a boostrap width
 *
 * @param
 * @param string $key
 * @return string
 */
function clic_cfg_set_content_module_width_pull_down($id, $key = '')
{

  $name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');

  $width = array(array('id' => '12', 'text' => '12'),
    array('id' => '11', 'text' => '11'),
    array('id' => '10', 'text' => '10'),
    array('id' => '9', 'text' => '9'),
    array('id' => '8', 'text' => '8'),
    array('id' => '7', 'text' => '7'),
    array('id' => '6', 'text' => '6'),
    array('id' => '5', 'text' => '5'),
    array('id' => '4', 'text' => '4'),
    array('id' => '3', 'text' => '3'),
    array('id' => '2', 'text' => '2'),
    array('id' => '1', 'text' => '1'),
  );

  $content_width = HTML::selectMenu($name, $width, $id);

  return $content_width;
}