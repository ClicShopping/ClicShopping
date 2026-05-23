<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Domains;

use ClicShopping\OM\Interfaces;

/**
 * This abstract class implements the PagesActionsInterface and serves as a base
 * class for handling page-specific actions in the application.
 */
abstract class PagesActionsAbstract implements Interfaces\PagesActionsInterface
{
  protected $page;
  protected $file;
  protected bool $is_rpc = false;

  /**
   * Constructor method for initializing the page object.
   *
   * @param \ClicShopping\OM\Interfaces\PagesInterface $page An instance of PagesInterface representing the page to be initialized.
   * @return void
   */
  public function __construct(Interfaces\PagesInterface $page)
  {
    $this->page = $page;

    if (isset($this->file)) {
      $this->page->setFile($this->file);
    }
  }

  /**
   * Checks if the current request is an RPC (Remote Procedure Call) request.
   *
   * @return bool Returns true if the request is an RPC request, otherwise false.
   */
  public function isRPC()
  {
    return ($this->is_rpc === true);
  }
}
