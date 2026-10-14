<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\OM\Registry;

/**
 * DataManager
 *
 * Computes AI interaction analytics from the rag_interactions log.
 * Extracted from Gpt.php as part of code refactoring (Task 9).
 *
 * Responsibilities:
 * - Calculate the AI error rate (request_type='error')
 */
class DataManager
{
  /**
   * Calculates the AI error rate from the rag_interactions log.
   *
   * Reads the modern rag_interactions table (written by StatisticsManager::persistInteraction),
   * where every AI interaction is tagged with a request_type; failures carry request_type='error'.
   * The rate is errors / total interactions. Replaces the legacy string-scan over the retired
   * gpt table.
   *
   * @return bool|float Error rate as a percentage (0..100), or false when there is no data.
   */
  public static function getErrorRateGpt(): bool|float
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $result = false;

    $Qtotal = $CLICSHOPPING_Db->prepare('select count(*) as total
                                           from :table_rag_interactions
                                          ');
    $Qtotal->execute();

    $total = $Qtotal->valueInt('total');

    if ($total > 0) {
      $Qerrors = $CLICSHOPPING_Db->prepare('select count(*) as errors
                                              from :table_rag_interactions
                                              where request_type = :request_type
                                             ');
      $Qerrors->bindValue(':request_type', 'error');
      $Qerrors->execute();

      $errors = $Qerrors->valueInt('errors');

      $result = round(($errors / $total) * 100, 2);
    }

    return $result;
  }
}
