<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;

/**
 * Email password input
 *
 * @param string $password
 * @return string  $password, the password
 */

function clic_cfg_set_input_password($password)
{
  $encrypted_password = Hash::displayDecryptedDataText($password);
  return HTML::passwordField('configuration_value', $encrypted_password);
}
