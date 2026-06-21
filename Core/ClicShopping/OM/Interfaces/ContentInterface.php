<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\OM\Interfaces;

interface ContentInterface
{
  public function execute();

  public function isEnabled();

  public function check();

  public function install();

  public function remove();

  public function keys();
}
