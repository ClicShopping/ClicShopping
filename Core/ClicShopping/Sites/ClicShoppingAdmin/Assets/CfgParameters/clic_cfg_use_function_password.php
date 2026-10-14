<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * @param $password
 * @return array|string|string[]|null
 */
function clic_cfg_use_function_password($password)
{
  return preg_replace("|.|", "*", $password);
}