<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\OM\Interfaces;

/**
 * ShippingInterface provides the blueprint for implementing shipping-related functionalities.
 *
 * It defines methods for obtaining shipping quotes, performing validation checks,
 * managing installation or removal of shipping modules, and retrieving configurable parameters.
 */
interface ShippingInterface
{
  public function quote();

  public function check();

  public function install();

  public function remove();

  public function keys();
}
