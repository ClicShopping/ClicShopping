<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Embedding;


use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * VectorType Class
 *
 * A custom Doctrine type that handles MariaDB vector data type for storing embeddings.
 * This class manages the conversion between PHP arrays and MariaDB vector format,
 * specifically designed for handling high-dimensional vector embeddings used in
 * machine learning and similarity search operations.
 *
 * Features:
 * - Converts between PHP arrays and MariaDB vector format
 * - Supports customizable vector dimensions
 * - Handles null values appropriately
 * - Provides proper type hints for Doctrine ORM
 *
 * Usage:
 * This type should be registered with Doctrine using:
 * Type::addType('vector', VectorType::class);
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\Rag
 * @extends Type
 */

class VectorType extends Type
{
  /**
   * The name of the type
   */
  const VECTOR = 'vector';

  /**
   * Returns the SQL declaration for the vector type
   *
   * Generates the appropriate VECTOR type declaration with dimension specification
   * for MariaDB database schema.
   *
   * @param array $column Column definition array
   * @param AbstractPlatform $platform Database platform
   * @return string SQL type declaration
   */
  public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
  {
    $dimension = $column['dimension'] ?? NewVector::getEmbeddingLength();

    return "VECTOR({$dimension})";
  }

  /**
   * Converts a database value to PHP value
   *
   * Transforms the string representation of a vector from the database
   * into a PHP array of floating-point numbers.
   *
   * @param mixed $value The value to convert (string format: [x,y,z,...])
   * @param AbstractPlatform $platform Database platform
   * @return array|null Array of floats representing the vector, or null
   */
  public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
  {
    if ($value === null) {
      return null;
    }

    // Conversion de la chaîne de caractères en tableau de flottants
    if (is_string($value)) {
      // Supprimer les crochets et diviser par les virgules
      $value = trim($value, '[]');
      $values = explode(',', $value);
      return array_map('floatval', $values);
    }

    return $value;
  }

  /**
   * Converts a PHP value to database format
   *
   * Transforms a PHP array of numbers into the string format expected
   * by MariaDB's vector type.
   *
   * @param mixed $value PHP value (array of numbers)
   * @param AbstractPlatform $platform Database platform
   * @return string|null MariaDB vector format [x,y,z,...] or null
   * @throws \InvalidArgumentException If the value cannot be converted
   */
  public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
  {
    if ($value === null) {
      return null;
    }

    // Si la valeur est déjà une chaîne formatée correctement, la retourner telle quelle
    if (is_string($value) && str_starts_with($value, '[')) {
      return $value;
    }

    // Convertir le tableau en chaîne formatée pour MariaDB
    if (is_array($value)) {
      return '[' . implode(',', $value) . ']';
    }

    throw new \InvalidArgumentException("Cannot convert value to vector format");
  }

  /**
   * Returns the name of the type
   *
   * @return string Type name ('vector')
   */
  public function getName(): string
  {
    return self::VECTOR;
  }

  //*************************
  // Not used
  //*************************

  /**
   * Indicates whether the type requires SQL comment hint
   *
   * Always returns true to ensure proper type handling in Doctrine.
   *
   * @param AbstractPlatform $platform Database platform
   * @return bool Always true for vector type
   */
  public function requiresSQLCommentHint(AbstractPlatform $platform): bool
  {
    return true;
  }
}

