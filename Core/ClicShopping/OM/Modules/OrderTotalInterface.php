<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Modules;

/**
 * Interface OrderTotalInterface
 *
 * Represents the contract for managing and processing order total modules.
 */
interface OrderTotalInterface
{
  public function process();

  public function check();

  public function install();

  public function remove();

  public function keys();
}
