<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
/**
 * @param $string
 * @return bool|mixed|string
 */
function clic_cfg_use_get_boolean_value($string)
{
  switch ($string) {
    case -1:
    case '-1':
      return false;

    case 0:
    case '0':
      return 'optional';

    case 1:
    case '1':
      return true;

    default:
      return $string;
  }
}