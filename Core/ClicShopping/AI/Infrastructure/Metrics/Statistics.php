<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * Statistics - token usage statistics read from the rag_statistics table.
 *
 * Provides the monthly token total consumed by the ChatGpt admin dashboard. The write path lives
 * in StatisticsTracker; this class is read-only.
 * The legacy gpt_usage-based methods were removed with the gpt/gpt_usage tables (catalogue LLM §U).
 */
class Statistics {

  /**
   * Retrieves the total number of tokens (promptTokens, completionTokens, totalTokens) used in the last month.
   *
   * @return array An associative array containing promptTokens, completionTokens, totalTokens, and date_added.
   */
  public static function getTotalTokenByMonth(): array
  {
    
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    
    $sql = "SELECT sum(tokens_prompt) as promptTokens,
                   sum(tokens_completion) as completionTokens,
                   sum(tokens_total) as totalTokens,
                   max(date_added) as date_added
            FROM {$prefix}rag_statistics
            WHERE date_added >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";

    $result = DoctrineOrm::selectOne($sql);

    return $result ?: [];
  }
}
