<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\CoreAI\Embedding;




class VectorStatistics
{
public function __construct()
{
}

  /**
   * Calculates the mean (average) value of the given array of numbers.
   *
   * @param array $values The array of numerical values to calculate the mean from.
   * @return float The calculated mean value of the array.
   * @throws DivisionByZeroError If the array is empty, causing a division by zero.
   */
  private function calculateMean(array $values)
  {
    return array_sum($values) / count($values);
  }


  /**
   * Calculates the variance of a given array of numeric values.
   *
   * @param array $values An array of numeric values for which to calculate the variance.
   * @return float The calculated variance of the provided values.
   * @throws \InvalidArgumentException If the input array is empty.
   */
  private function calculateVariance(array $values): float
  {
    $mean = $this->calculateMean($values);
    $sum_of_squared_diff = 0;

    foreach ($values as $value) {
      $sum_of_squared_diff += pow($value - $mean, 2);
    }

    if (empty($values)) {
      throw new \InvalidArgumentException('The array should not be empty.');
    }

    return $sum_of_squared_diff / count($values);
  }


  /**
   * Calculates the standard deviation of the given array of values.
   *
   * @param array $values The array of numerical values to calculate the standard deviation for.
   * @return float The calculated standard deviation of the values.
   * @throws \InvalidArgumentException If the provided array is empty.
   */
  public function calculateStandardDeviation(array $values): float
  {
    $variance = $this->calculateVariance($values);

    if (empty($values)) {
      throw new \InvalidArgumentException('The array should not be empty.');
    }

    return sqrt($variance);
  }


  /**
   * Calculates the cosine similarity between two vectors.
   *
   * @param array $vec1 An array representing the first vector.
   * @param array $vec2 An array representing the second vector. Must have the same length as $vec1.
   * @return float The cosine similarity value, which ranges from -1 to 1. Returns 0.0 if either vector has zero magnitude.
   * @throws InvalidArgumentException If the input vectors do not have the same length.
   */
  public static function cosineSimilarity(array $vec1, array $vec2) :float
  {
    if (count($vec1) !== count($vec2)) {
      throw new InvalidArgumentException('Vectors must have the same length.');
    }

    $dot_product = 0;
    $magnitude_vec1 = 0;
    $magnitude_vec2 = 0;

    foreach ($vec1 as $i => $value) {
      $dot_product += $value * $vec2[$i];
      $magnitude_vec1 += $value * $value;
      $magnitude_vec2 += $vec2[$i] * $vec2[$i];
    }

    if ($magnitude_vec1 == 0 || $magnitude_vec2 == 0) {
      return 0.0; // Return 0 for vectors with no magnitude
    }

    return $dot_product / (sqrt($magnitude_vec1) * sqrt($magnitude_vec2));
  }
}