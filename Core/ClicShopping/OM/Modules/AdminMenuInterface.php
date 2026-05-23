<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\OM\Modules;

/**
 * Interface AdminMenuInterface
 *
 * Defines the contract for an admin menu module within the ClicShopping application.
 * Classes implementing this interface must provide functionality to execute specific operations
 * related to the administration menu.
 */

interface AdminMenuInterface
{
  public static function execute();
}
