<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Modules;

/**
 * Interface AdminDashboardInterface
 *
 * Defines the structure for an admin dashboard module, including methods
 * for rendering output, installation, configuration, and operational checks.
 */
interface AdminDashboardInterface
{
  public function getOutput();

  public function install();

  public function keys();

  public function isEnabled();

  public function check();

  public function remove();
}
