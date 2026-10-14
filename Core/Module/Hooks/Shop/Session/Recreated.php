<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\OM\Module\Hooks\Shop\Session;

use ClicShopping\OM\Hash;

class Recreated
{
  /**
   * Resets the session token with a newly generated value.
   *
   * @param mixed $parameters Additional parameters for the execution. Not used in the current implementation.
   * @return void
   */
  public function execute($parameters)
  {
// reset session token
    $_SESSION['sessiontoken'] = md5(Hash::getRandomInt() . Hash::getRandomInt() . Hash::getRandomInt() . Hash::getRandomInt());
  }
}
