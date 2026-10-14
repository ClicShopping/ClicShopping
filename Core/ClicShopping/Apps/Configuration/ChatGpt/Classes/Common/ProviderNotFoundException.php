<?php
/**
 * Provider Not Found Exception
 *
 * Exception thrown when a requested LLM provider is not found in the registry.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\Common
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

/**
 * Class ProviderNotFoundException
 *
 * Exception thrown when attempting to retrieve a provider that doesn't exist.
 * This helps distinguish provider lookup failures from other runtime errors.
 */
class ProviderNotFoundException extends \RuntimeException
{
  /**
   * Constructor
   *
   * @param string $message Exception message
   * @param int $code Exception code (default: 0)
   * @param \Throwable|null $previous Previous exception for chaining
   */
  public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}
