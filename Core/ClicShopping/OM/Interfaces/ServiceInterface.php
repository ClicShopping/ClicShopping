<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Interfaces;

/**
 * Interface ServiceInterface
 *
 * Defines the structure for a service within the application by requiring
 * the implementation of methods to start and stop the service.
 */
interface ServiceInterface
{
  /**
   * Starts the execution of the defined process or service.
   */
  public static function start();

  /**
   * Stops the execution of the defined process or service.
   */
  public static function stop();
}