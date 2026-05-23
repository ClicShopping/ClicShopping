<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Interfaces;

/**
 * Interface PagesInterface
 *
 * Provides an interface to manage page files within the ClicShopping framework.
 */
interface PagesInterface
{
  /**
   * Retrieves a file.
   *
   * This method is used to obtain a file based on internal logic or criteria.
   *
   * @return mixed The file or its representation, depending on the implementation.
   */
  public function getFile();

  /**
   *
   * @param mixed $file The file to be set.
   */
  public function setFile($file);
}
