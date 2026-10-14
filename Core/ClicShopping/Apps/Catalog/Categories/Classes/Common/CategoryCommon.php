<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Classes\Common;

use function count;
use function in_array;

/**
 * Class CategoryCommon
 *
 * Provides utility methods for handling category-related operations.
 */
class CategoryCommon
{
  /**
   * Parses a category path string into an array of unique integer category IDs.
   *
   * @param string $cPath The category path represented as a string with category IDs separated by underscores.
   * @return array An array of unique integer category IDs extracted from the input category path.
   */
  public function getParseCategoryPath(string $cPath): array
  {
// make sure the category IDs are integers
    $cPath_array = array_map(function ($string) {
      return (int)$string;
    }, explode('_', $cPath));

// make sure no duplicate category IDs exist which could lock the server in a loop
    $tmp_array = [];
    $n = count($cPath_array);

    for ($i = 0; $i < $n; $i++) {
      if (!in_array($cPath_array[$i], $tmp_array)) {
        $tmp_array[] = $cPath_array[$i];
      }
    }

    return $tmp_array;
  }
}
