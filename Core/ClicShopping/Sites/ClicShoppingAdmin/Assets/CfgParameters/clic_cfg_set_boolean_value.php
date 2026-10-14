<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

////
// Alias function for Store configuration values in the Administration Tool
function clic_cfg_set_boolean_value($select_array, $default, $key = null)
{
  $string = '';

  $select_array = explode(',', substr($select_array, 6, -1));

  $name = (!empty($key) ? 'configuration[' . $key . ']' : 'configuration_value');

  for ($i = 0, $n = \count($select_array); $i < $n; $i++) {
    $value = trim($select_array[$i]);

    if (str_contains($value, '\'')) {
      $value = substr($value, 1, -1);
    } else {
      $value = (int)$value;
    }

    $select_array[$i] = $value;

    if ($value === -1) {
      $value = 'false';
    } elseif ($value === 0) {
      $value = 'optional';
    } elseif ($value === 1) {
      $value = 'true';
    }

    $string .= '<input type="radio" name="' . $name . '" value="' . $select_array[$i] . '"';

    if ($default == $select_array[$i]) {
      $string .= ' checked="checked"';
    }

    $string .= '> ' . $value . '<br />';
  }

  if (!empty($string)) {
    $string = substr($string, 0, -6);
  }

  return $string;
}