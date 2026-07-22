<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\InterfacesAI;

/**
 * QueryIntentDetectorInterface
 *
 * Domain-agnostic contract for a FALLBACK, pattern-based intent verdict used
 * only when the LLM intent signal is absent. Lets a domain App own its own
 * intent keywords/patterns (which are domain-specific by nature) without Core
 * ever hard-coding a domain concept.
 *
 * Architecture (Pure LLM Mode, AGENTS.md):
 *   - PRIMARY : the LLM emits the intent (e.g. via rag_intent_router).
 *   - FALLBACK: registered detectors are consulted when the LLM signal is
 *               missing. Core owns no detector; verdicts only come from
 *               registered domain Apps.
 *
 * Registered through
 * {@see \ClicShopping\AI\RegistryAI\WebSearchEngineRegistry::registerIntentDetector()}
 * and consulted from the planning layer in registration order; the first
 * non-null verdict wins.
 *
 * Implementations MUST:
 *   - be side-effect-free and deterministic for a given query
 *   - return an intent identifier string (e.g. 'comparative_lookup') or null
 *   - never throw — return null on any failure
 *
 * @package ClicShopping\AI\InterfacesAI
 * @since 2026-07-20
 */
interface QueryIntentDetectorInterface
{
    /**
     * Detect an intent for the given query using pattern matching.
     *
     * @param string $query The query to analyse (any language)
     * @return string|null The detected intent identifier, or null if none matched
     */
    public function detectIntent(string $query): ?string;

    /**
     * Stable identifier for this detector (used in logs and diagnostics).
     *
     * @return string Detector identifier (e.g. 'ecommerce-price-comparison')
     */
    public function getDetectorId(): string;
}
