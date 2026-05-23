<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Modules;

/**
 * Interface HeaderTagsInterface
 *
 * Defines a contract for managing header tags in a system.
 * Provides methods for output generation, installation, configuration keys retrieval,
 * status checking, and removal functionalities.
 */
interface HeaderTagsInterface
{
  public function getOutput();

  public function install();

  public function keys();

  public function isEnabled();

  public function check();

  public function remove();
}
