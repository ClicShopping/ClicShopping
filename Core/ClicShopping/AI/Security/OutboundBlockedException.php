<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Security;

/**
 * Raised when the deployment policy forbids reaching an outbound destination.
 *
 * Distinct from a network failure on purpose: "the operator forbade it" and "the provider did not
 * answer" call for opposite reactions, and a single false collapses them.
 *
 * @package ClicShopping\AI\Security
 * @since 4.33.0
 */
class OutboundBlockedException extends \RuntimeException
{
  /**
   * @param string $host The destination that was refused
   * @param string $mode The policy mode in force
   * @param string $purpose What the call was for (llm, embeddings, websearch…)
   */
  public function __construct(
    public private(set) string $host,
    public private(set) string $mode,
    public private(set) string $purpose
  ) {
    parent::__construct(
      sprintf('Outbound call to "%s" for "%s" blocked by deployment policy "%s".', $host, $purpose, $mode)
    );
  }
}
