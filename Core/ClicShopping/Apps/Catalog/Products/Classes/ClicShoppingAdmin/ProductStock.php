<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use function is_null;
/**
 * Class ProductStock
 *
 * This class provides functionalities related to calculating product stock levels,
 * including safety stock calculations based on historical demand and other inventory factors.
 */
class ProductStock
{
  /**
   * Normalize a numeric series by casting to float and filtering invalid values.
   */
  private static function normalizeSeries(array $values): array
  {
    $normalized = [];

    foreach ($values as $value) {
      if (is_numeric($value)) {
        $normalized[] = (float)$value;
      }
    }

    return $normalized;
  }

  /**
   * Calculates the safety stock based on historical demand, lead time, desired service level,
   * and standard deviation factor.
   *
   * @param array $historicalDemand An array of historical demand values used to calculate the mean and standard deviation.
   * @param int $leadTime The lead time in relevant time units (e.g., days, weeks) for replenishment.
   * @param float $serviceLevel Optional parameter specifying the desired service level as a probability (default is 0.95).
   * @param float $standardDeviationFactor Optional parameter representing the Z-score or factor for standard deviation calculation (default is 1.65).
   * @return float|int The calculated safety stock value, rounded to meet specified parameters.
   */
  private static function calculateSafetyStock(array $historicalDemand, int $leadTime, float $serviceLevel = 0.95, float $standardDeviationFactor = 1.65): float|int
  {
    $historicalDemand = self::normalizeSeries($historicalDemand);

    if (empty($historicalDemand)) {
      return 0;
    }

    // Calculate the mean (average) of historical demand
    $meanDemand = array_sum($historicalDemand) / count($historicalDemand);

    // Calculate the standard deviation of historical demand
    $standardDeviation = 0;
    foreach ($historicalDemand as $demand) {
      $standardDeviation += pow($demand - $meanDemand, 2);
    }
    $standardDeviation = sqrt($standardDeviation / count($historicalDemand));

    // Calculate the safety stock using the formula: Safety Stock = (Z-score * Standard Deviation * sqrt(Lead Time)) + Mean Demand
    $zScore = abs(static::norMinv((1 - $serviceLevel) / 2, 0, 1));
    $safetyStock = ($zScore * $standardDeviation * sqrt($leadTime)) + $meanDemand;

    return $safetyStock;
  }

  /**
   * Calculates the inverse of the normal cumulative distribution function (CDF).
   *
   * @param float $p The probability at which to evaluate the inverse normal CDF. Must be in the range (0, 1).
   * @param float $mean The mean (μ) of the normal distribution.
   * @param float $stddev The standard deviation (σ) of the normal distribution. Must be positive.
   * @return float|int The value x such that the cumulative distribution function equals $p. The result is a float or an integer based on the computation.
   */
  private static function norMinv($p, $mean, $stddev): float|int
  {
    $b1 = 0.319381530;
    $b2 = -0.356563782;
    $b3 = 1.781477937;
    $b4 = -1.821255978;
    $b5 = 1.330274429;
    $p_low = 0.02425;
    $p_high = 1 - $p_low;

    if ($p < $p_low) {
      $q = sqrt(-2 * log($p));
      return (((((($b5 * $q) + $b4) * $q) + $b3) * $q + $b2) * $q + $b1) / $p_high;
    } elseif ($p <= $p_high) {
      $q = $p - 0.5;
      $r = $q * $q;
      return (((((($b5 * $r) + $b4) * $r) + $b3) * $r + $b2) * $q + $b1) * $stddev + $mean;
    } else {
      $q = sqrt(-2 * log(1 - $p));
      return -(((((($b5 * $q) + $b4) * $q) + $b3) * $q + $b2) * $q + $b1) / $p_high;
    }
  }

  /**
   * Retrieves the historical customer demand for a specified product and calculates the safety stock based on the lead time.
   *
   * @param int|string|null $products_id The ID of the product for which historical demand is to be calculated. Can be null.
   * @param int|null $leadTime The lead time for calculating safety stock. Defaults to a predefined constant if null.
   *
   * @return float|false Returns the calculated safety stock as a float, or false if the product ID is not set or if there is an error during calculation.
   */
  public static function getHistoricalCustomerDemandByProducts(int|string|null $products_id = null, ?int $leadTime = null): float|false
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (is_null($leadTime)) {
      $leadTime = (int)SAFETY_STOCK_TIME;
    }

    if (isset($products_id) && !is_null($products_id)) {
      $QhistoricalDemand = $CLICSHOPPING_Db->get('orders_products', ['products_id', 'products_quantity'], ['products_id' => (int)$products_id]);

      $historicalDemand = $QhistoricalDemand->toArray();

      if (is_array($historicalDemand)) {
        $series = [];
        foreach ($historicalDemand as $row) {
          if (is_array($row) && isset($row['products_quantity'])) {
            $series[] = $row['products_quantity'];
          } elseif (is_numeric($row)) {
            $series[] = $row;
          }
        }

        $safetyStock = self::calculateSafetyStock($series, $leadTime);

        return round($safetyStock, 2);
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  /**
   * Get daily demand series for a product over a lookback window.
   *
   * @param int|string|null $products_id Product ID
   * @param int|null $daysBack Number of days to look back (default 90)
   * @return array Array of daily quantities (including zeros for missing days)
   */
  public static function getDailyDemandSeriesByProducts(int|string|null $products_id = null, ?int $daysBack = 90): array
  {
    if (!isset($products_id) || is_null($products_id)) {
      return [];
    }

    $CLICSHOPPING_Db = Registry::get('Db');

    $daysBack = is_null($daysBack) ? 90 : max(1, (int)$daysBack);
    $dateFrom = date('Y-m-d 00:00:00', time() - ($daysBack * 86400));

    $Qdaily = $CLICSHOPPING_Db->prepare('select DATE(o.date_purchased) as order_day,
                                               SUM(op.products_quantity) as daily_qty
                                        from :table_orders_products op,
                                             :table_orders o
                                        where op.orders_id = o.orders_id
                                          and op.products_id = :products_id
                                          and o.date_purchased >= :date_from
                                        group by order_day
                                        order by order_day asc
                                       ');
    $Qdaily->bindInt(':products_id', (int)$products_id);
    $Qdaily->bindValue(':date_from', $dateFrom);
    $Qdaily->execute();

    $dailyTotals = [];
    while ($Qdaily->fetch()) {
      $dailyTotals[$Qdaily->value('order_day')] = (float)$Qdaily->value('daily_qty');
    }

    $series = [];
    $startDay = new \DateTimeImmutable(date('Y-m-d', strtotime($dateFrom)));
    for ($i = 0; $i < $daysBack; $i++) {
      $day = $startDay->modify('+' . $i . ' days')->format('Y-m-d');
      $series[] = $dailyTotals[$day] ?? 0.0;
    }

    return $series;
  }

  /**
   * Calculate mean and standard deviation for a numeric series.
   */
  public static function calculateDemandStats(array $values): array
  {
    $values = self::normalizeSeries($values);

    if (empty($values)) {
      return [
        'count' => 0,
        'mean' => 0.0,
        'stddev' => 0.0,
      ];
    }

    $count = count($values);
    $mean = array_sum($values) / $count;
    $variance = 0.0;
    foreach ($values as $value) {
      $variance += pow($value - $mean, 2);
    }
    $variance = $variance / $count;

    return [
      'count' => $count,
      'mean' => $mean,
      'stddev' => sqrt($variance),
    ];
  }

  /**
   * Forecast total demand over a horizon based on daily demand series.
   */
  public static function calculateDemandForecast(array $dailyDemand, int $horizonDays): array
  {
    $horizonDays = max(1, $horizonDays);
    $stats = self::calculateDemandStats($dailyDemand);

    $meanDaily = $stats['mean'];
    $stdDaily = $stats['stddev'];

    return [
      'mean_daily' => $meanDaily,
      'stddev_daily' => $stdDaily,
      'mean_total' => $meanDaily * $horizonDays,
      'stddev_total' => $stdDaily * sqrt($horizonDays),
      'count_days' => $stats['count'],
    ];
  }

  /**
   * Calculate safety stock using daily demand and lead time.
   */
  public static function calculateSafetyStockFromDailyDemand(array $dailyDemand, int $leadTimeDays, float $serviceLevel = 0.95): float
  {
    $leadTimeDays = max(1, $leadTimeDays);
    $stats = self::calculateDemandStats($dailyDemand);
    $zScore = self::getZScoreForServiceLevel($serviceLevel);

    return $zScore * $stats['stddev'] * sqrt($leadTimeDays);
  }

  /**
   * Calculate probability of stock-out over a horizon.
   */
  public static function calculateStockoutProbability(float $currentStock, float $meanDemand, float $stdDevDemand): float
  {
    if ($stdDevDemand <= 0.0) {
      return ($currentStock < $meanDemand) ? 1.0 : 0.0;
    }

    $cdf = self::normalCdf($currentStock, $meanDemand, $stdDevDemand);
    $probability = 1.0 - $cdf;

    return min(1.0, max(0.0, $probability));
  }

  /**
   * Calculate reorder quantity based on expected demand and safety stock.
   */
  public static function calculateReorderQuantity(float $currentStock, float $expectedDemand, float $safetyStock): float
  {
    $target = $expectedDemand + $safetyStock;
    return max(0.0, $target - $currentStock);
  }

  /**
   * Convert service level to Z-score.
   */
  private static function getZScoreForServiceLevel(float $serviceLevel): float
  {
    $serviceLevel = min(0.999, max(0.50, $serviceLevel));
    return abs(self::norMinv((1 - $serviceLevel) / 2, 0, 1));
  }

  /**
   * Normal CDF approximation.
   */
  private static function normalCdf(float $x, float $mean, float $stdDev): float
  {
    if ($stdDev <= 0.0) {
      return ($x < $mean) ? 0.0 : 1.0;
    }

    $z = ($x - $mean) / ($stdDev * sqrt(2));
    return 0.5 * (1 + self::erfApprox($z));
  }

  /**
   * Error function approximation (Abramowitz-Stegun 7.1.26).
   */
  private static function erfApprox(float $x): float
  {
    $sign = ($x < 0) ? -1 : 1;
    $x = abs($x);

    $p = 0.3275911;
    $a1 = 0.254829592;
    $a2 = -0.284496736;
    $a3 = 1.421413741;
    $a4 = -1.453152027;
    $a5 = 1.061405429;

    $t = 1.0 / (1.0 + $p * $x);
    $y = 1.0 - (((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t) * exp(-$x * $x);

    return $sign * $y;
  }
}
