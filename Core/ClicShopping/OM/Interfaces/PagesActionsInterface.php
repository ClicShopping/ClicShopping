<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Interfaces;

/**
 * Interface PagesActionsInterface
 *
 * Defines the structure for pages action classes within the ClicShopping framework.
 * Classes implementing this interface must provide methods for executing actions
 * and determining if the action is executed via Remote Procedure Call (RPC).
 */
interface PagesActionsInterface
{
  /**
   *
   */
  public function execute();

  /**
   * Determines if the current instance represents an RPC (Remote Procedure Call).
   *
   * This method checks if the instance or its associated logic adheres to
   * the structure or behavior of an RPC. It is typically used to differentiate
   * between various communication or operational modes within the application.
   *
   * @return bool Returns true if the instance is an RPC, false otherwise.
   */
  public function isRPC();
}
