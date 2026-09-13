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
   * Calculates the inverse of the normal cumulative distribution function (CDF).
   *
   * @param float $p The probability at which to evaluate the inverse normal CDF. Must be in the range (0, 1).
   * @param float $mean The mean (μ) of the normal distribution.
   * @param float $stddev The standard deviation (σ) of the normal distribution. Must be positive.
   * @return float The value x such that the cumulative distribution function equals $p.
   */
  private static function norMinv($p, $mean, $stddev): float
  {
    // Acklam's rational approximation of the standard-normal quantile (rel. error < 1.15e-9).
    // Returns mean + stddev * z; the previous coefficients were the forward-CDF polynomial and
    // produced a wrong z (0.33 instead of 1.96 at p=0.025), silently under-sizing safety stock.
    $p = min(1 - 1e-16, max(1e-16, (float)$p));

    $a = [-3.969683028665376e+01, 2.209460984245205e+02, -2.759285104469687e+02,
          1.383577518672690e+02, -3.066479806614716e+01, 2.506628277459239e+00];
    $b = [-5.447609879822406e+01, 1.615858368580409e+02, -1.556989798598866e+02,
          6.680131188771972e+01, -1.328068155288572e+01];
    $c = [-7.784894002430293e-03, -3.223964580411365e-01, -2.400758277161838e+00,
          -2.549732539343734e+00, 4.374664141464968e+00, 2.938163982698783e+00];
    $d = [7.784695709041462e-03, 3.224671290700398e-01, 2.445134137142996e+00,
          3.754408661907416e+00];

    $p_low = 0.02425;
    $p_high = 1 - $p_low;

    if ($p < $p_low) {
      $q = sqrt(-2 * log($p));
      $z = ((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5])
         / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
    } elseif ($p <= $p_high) {
      $q = $p - 0.5;
      $r = $q * $q;
      $z = ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q
         / ((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1);
    } else {
      $q = sqrt(-2 * log(1 - $p));
      $z = -((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5])
         / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
    }

    return $mean + $stddev * $z;
  }

  /**
   * Lead time in days. SAFETY_STOCK_TIME is the only source: repeating its value in a fallback
   * literal is how a consumer drifts out of sync with the back-office.
   *
   * @return int The configured lead time, in days
   */
  public static function configuredLeadTimeDays(): int
  {
    return (int)SAFETY_STOCK_TIME;
  }

  /**
   * Alert threshold in force for a product: its own when set, else the shop-wide
   * STOCK_REORDER_LEVEL. A zero on the product means UNSET, never a threshold of zero.
   *
   * @param float $productAlert The products_quantity_alert column of the product
   * @return float The threshold that actually applies
   */
  public static function effectiveAlertStock(float $productAlert): float
  {
    return $productAlert > 0 ? $productAlert : (float)STOCK_REORDER_LEVEL;
  }

  /**
   * Predictive safety stock for a product, from its recent daily demand series.
   *
   * @param int|string|null $products_id Product ID
   * @return float Safety stock (Z * sigma_daily * sqrt(lead time)); 0.0 when it cannot be computed
   */
  public static function getSafetyStockByProducts(int|string|null $products_id = null): float
  {
    if (!isset($products_id)) {
      return 0.0;
    }

    $series = self::getDailyDemandSeriesByProducts($products_id);

    if ($series === []) {
      return 0.0;
    }

    return round(self::calculateSafetyStockFromDailyDemand($series, self::configuredLeadTimeDays()), 2);
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
   * @param array $values
   * @return array
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
   * @param array $dailyDemand
   * @param int $horizonDays
   * @return array
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
   * @param array $dailyDemand
   * @param int $leadTimeDays
   * @param float $serviceLevel
   * @return float
   *  Calculate safety stock using daily demand and lead time.
   */
  public static function calculateSafetyStockFromDailyDemand(array $dailyDemand, int $leadTimeDays, float $serviceLevel = 0.95): float
  {
    $leadTimeDays = max(1, $leadTimeDays);
    $stats = self::calculateDemandStats($dailyDemand);
    $zScore = self::getZScoreForServiceLevel($serviceLevel);

    return $zScore * $stats['stddev'] * sqrt($leadTimeDays);
  }


  /**
   * @param float $currentStock
   * @param float $meanDemand
   * @param float $stdDevDemand
   * @return float
   *  Calculate probability of stock-out over a horizon.
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
   * @param float $currentStock
   * @param float $expectedDemand
   * @param float $safetyStock
   * @return float
   *  Calculate reorder quantity based on expected demand and safety stock.
   */
  public static function calculateReorderQuantity(float $currentStock, float $expectedDemand, float $safetyStock): float
  {
    $target = $expectedDemand + $safetyStock;
    return max(0.0, $target - $currentStock);
  }

  /**
   * @param float $serviceLevel
   * @return float
   *  Convert service level to Z-score.
   */
  private static function getZScoreForServiceLevel(float $serviceLevel): float
  {
    $serviceLevel = min(0.999, max(0.50, $serviceLevel));
    return abs(self::norMinv((1 - $serviceLevel) / 2, 0, 1));
  }

  /**
   * @param float $x
   * @param float $mean
   * @param float $stdDev
   * @return float
   *  Normal CDF approximation.
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
   * @param float $x
   * @return float
   *  Error function approximation (Abramowitz-Stegun 7.1.26).
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
