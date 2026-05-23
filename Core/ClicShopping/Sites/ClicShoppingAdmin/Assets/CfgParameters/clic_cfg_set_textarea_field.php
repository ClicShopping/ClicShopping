<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;

/**
 * @param $text
 * @param $key
 * @return string
 */
function clic_cfg_set_textarea_field($text, $key = null)
{
  $name = (!empty($key) ? 'configuration[' . $key . ']' : 'configuration_value');

  return HTML::textAreaField($name, $text, 35, 5);
}