<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Interfaces;

/**
 * Interface IsInterface
 *
 * This interface defines a method for executing a specific functionality.
 * Implementing classes are required to provide the logic for the `execute` method.
 */
interface IsInterface
{
  /**
   * Executes a specific operation based on the provided value.
   *
   * @param mixed $value The input value required for execution.
   * @return bool Returns true if the operation was successful, otherwise false.
   */
  public static function execute(mixed $value): bool;
}
