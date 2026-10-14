<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;
/**
 * This class provides a service for handling output compression in a shop context.
 * It uses gzip compression if enabled and the required zlib extension is loaded,
 * and if no headers have already been sent.
 */
class OutputCompression implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Starts the process for configuring gzip compression if enabled.
   *
   * This method configures the gzip compression settings if the feature is enabled,
   * the zlib extension is loaded, and no headers are already sent. Compression level
   * and related parameters are set accordingly.
   *
   * @return bool Returns false after attempting to configure the compression settings.
   */
  public static function start(): bool
  {
// configure gzip compression if it is enabled
    if ((GZIP_COMPRESSION == 'true') && extension_loaded('zlib') && !headers_sent()) {
      if ((int)ini_get('zlib.output_compression') < 1) {
        ini_set('zlib.output_handler', '');
        ini_set('zlib.output_compression', 1);
      }
      ini_set('zlib.output_compression_level', GZIP_LEVEL);
    };

    return false;
  }

  /**
   * Stops the current operation or process.
   *
   * @return bool Returns true indicating the process was successfully stopped.
   */
  public static function stop(): bool
  {
    return true;
  }
}