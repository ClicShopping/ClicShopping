<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\OM\Interfaces;

/**
 * Interface AdminMenuInterface
 *
 * Defines the contract for an admin menu module within the ClicShopping application.
 * Classes implementing this interface must provide functionality to execute specific operations
 * related to the administration menu.
 * Not used a this moment
 */

interface AdminMenuInterface
{
  public function execute();

  public function isEnabled();

  public function check();

  public function install();

  public function remove();

  public function keys();
}
