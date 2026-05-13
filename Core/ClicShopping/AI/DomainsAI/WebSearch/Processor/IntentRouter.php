<?php
/**
 * IntentRouter.php
 *
 * Intent detection and routing component for the unified websearch engine.
 * Analyzes user queries using LLM-based intent detection with pattern-based fallback.
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Processor
 * @since 2026-05-05
 *
 * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 8.1, 8.2, 8.5, 18.1, 18.2, 18.3, 18.4, 18.5, 20.1, 20.2, 20.3, 20.4, 20.5
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Processor;

use ClicShopping\AI\Security\LlmGuardrails;
use ClicShopping\AI\DomainsAI\WebSearch\Logger\WebSearchLogger;
use ClicShopping\AI\DomainsAI\WebSearch\Patterns\IntentDetectionPatterns;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

/**
 * IntentRouter Class
 *
 * Implements LLM-based intent detection with pattern-based fallback for the three-mode websearch architecture.
 * Applies AI security guardrails before LLM analysis and delegates mode selection to ModeSelector.
 *
 * Routing Flow:
 * 1. Apply AI security guardrails (prompt injection, obfuscation, threat scoring, rate limiting)
 * 2. LLM-based intent detection via LLPhant
 * 3. Parse and validate LLM JSON response
 * 4. Fallback to pattern-based detection if LLM fails
 * 5. Delegate mode selection to ModeSelector
 * 6. Log routing decisions
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Processor
 */
class IntentRouter
{
  /**
   * @var WebSearchLogger Logger instance for audit trail
   */
  private WebSearchLogger $logger;

  /**
   * @var ModeSelector Mode selector instance
   */
  private ModeSelector $modeSelector;

  /**
   * @var bool Debug mode flag
   */
  private bool $debug;

  /**
   * @var object Language instance for getDef()
   */
  private object $language;

  /**
   * @var float Threat score threshold for guardrails
   */
  private const THREAT_SCORE_THRESHOLD = 0.8;

  /**
   * @var int LLM max tokens for intent detection
   */
  private const LLM_MAX_TOKENS = 500;

  /**
   * @var float LLM temperature for intent detection
   */
  private const LLM_TEMPERATURE = 0.3;

  /**
   * Constructor
   *
   * @param WebSearchLogger|null $logger Optional logger instance
   * @param ModeSelector|null $modeSelector Optional mode selector instance
   */
  public function __construct(?WebSearchLogger $logger = null, ?ModeSelector $modeSelector = null)
  {
    $this->logger = $logger ?? new WebSearchLogger();
    $this->modeSelector = $modeSelector ?? new ModeSelector($this->logger);
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER == 'True';

    // Load language file for intent router prompts (once in constructor)
    // File: ClicShoppingAdmin/Core/languages/{language}/ecommerce/rag_intent_router.txt
    DomainConfig::loadLanguageFile('rag_intent_router');

    // Get Language instance (once in constructor)
    $this->language = Registry::get('Language');
  }

  /**
   * Route query to appropriate search mode(s)
   *
   * Implements requirements 7.1-7.5, 8.1-8.5, 18.1-18.5, 20.1-20.5.
   * Applies guardrails → LLM analysis → fallback to patterns on failure.
   *
   * @param string $query User query
   * @param array $options Additional options (reserved for future use)
   * @return RoutingDecision Routing decision
   * @throws \Exception If guardrails reject the query
   */
  public function route(string $query, array $options = []): RoutingDecision
  {
    if ($this->debug) {
      error_log("IntentRouter::route() - Query: {$query}");
    }

    // Step 1: Apply AI security guardrails (Requirement 20.1, 20.2, 20.3, 20.4, 20.5)
    $this->applyGuardrails($query);

    // Step 2: LLM-based intent detection (Requirement 7.1, 7.2, 7.3, 7.4)
    $intent = $this->detectIntentViaLLM($query);

    // Step 3: Fallback to pattern-based detection if LLM failed (Requirement 7.5, 8.1, 8.2)
    if ($intent === null) {
      $intent = $this->detectIntentViaPatterns($query);
    }

    // Step 4: Map location to parameters (Requirement 9.1.1, 9.1.2, 9.1.3, 9.1.4, 9.1.5)
    $locationParams = $this->modeSelector->mapLocationToParams($intent['location'] ?? null);

    // Step 5: Delegate mode selection to ModeSelector (Requirement 9.1, 9.2, 9.3, 9.4, 9.5, 9.6)
    $selectedModes = $this->modeSelector->selectModes($intent, $options);

    // Retrieve any user notifications generated during mode selection (e.g. site not in DB)
    $userNotifications = $this->modeSelector->getUserNotifications();

    // Step 6: Create routing decision
    $metadata = [
      'confidence' => $intent['confidence'] ?? 0.0,
      'query_length' => strlen($query),
      'timestamp' => date('Y-m-d H:i:s')
    ];

    // Attach the first notification (if any) so it survives through WebSearchExecutor → formatter
    if (!empty($userNotifications)) {
      $metadata['user_notification'] = $userNotifications[0];
    }

    $routingDecision = new RoutingDecision(
      $intent,
      $selectedModes,
      $locationParams,
      $intent['detection_method'] ?? 'unknown',
      $metadata
    );

    // Step 7: Log routing decision (Requirement 21.2)
    $this->logRoutingDecision($query, $routingDecision);

    if ($this->debug) {
      error_log("IntentRouter::route() - Routing decision: " . json_encode($routingDecision->toArray()));
    }

    return $routingDecision;
  }

  /**
   * Apply AI security guardrails to query
   *
   * Implements requirement 20.1, 20.2, 20.3, 20.4, 20.5:
   * - Prompt injection detection
   * - Obfuscation detection
   * - Threat scoring
   * - Rate limiting (900s window, 20 requests max)
   *
   * @param string $query User query
   * @throws \Exception If guardrails reject the query
   */
  private function applyGuardrails(string $query): void
  {
    try {
      // Apply LlmGuardrails for security validation
      // Note: LlmGuardrails::checkGuardrails() expects both question and result
      // For intent detection, we validate the query as both input and output
      $guardrailResult = LlmGuardrails::checkGuardrails($query, $query);

      if ($this->debug) {
        error_log("IntentRouter::applyGuardrails() - Guardrail result: " . json_encode($guardrailResult));
      }

      // Check if guardrails rejected the query
      if (is_array($guardrailResult) && isset($guardrailResult['action'])) {
        if ($guardrailResult['action'] === 'reject' || $guardrailResult['action'] === 'block') {
          $reason = $guardrailResult['reason'] ?? 'Security threat detected';
          $threatScore = $guardrailResult['threat_score'] ?? 1.0;

          $this->logger->logError("Guardrails rejected query", [
            'query' => substr($query, 0, 100),
            'reason' => $reason,
            'threat_score' => $threatScore,
            'action' => $guardrailResult['action']
          ]);

          throw new \Exception("Query rejected for security reasons: {$reason}");
        }
      }

      // Additional threat score validation
      if (is_array($guardrailResult) && isset($guardrailResult['threat_score'])) {
        if ($guardrailResult['threat_score'] >= self::THREAT_SCORE_THRESHOLD) {
          $this->logger->logWarning("High threat score detected", [
            'query' => substr($query, 0, 100),
            'threat_score' => $guardrailResult['threat_score']
          ]);

          throw new \Exception("Query rejected: threat score too high");
        }
      }

    } catch (\Exception $e) {
      // Log security event
      $this->logger->logError("Guardrails error: " . $e->getMessage(), [
        'query' => substr($query, 0, 100),
        'exception' => $e->getMessage()
      ]);

      // Re-throw security exceptions
      throw $e;
    }
  }

  /**
   * Detect intent using LLM via LLPhant
   *
   * Implements requirement 7.1, 7.2, 7.3, 7.4, 18.1, 18.2, 18.3, 18.4, 18.5:
   * - Use LLPhant abstraction for multi-provider support via Gpt class
   * - Structured prompt requesting JSON output
   * - Parse and validate JSON response
   * - Provider fallback on failure (handled by Gpt class)
   *
   * @param string $query User query
   * @return array|null Intent structure or null if LLM failed
   */
  private function detectIntentViaLLM(string $query): ?array
  {
    try {
      // Build structured prompt for intent detection
      $prompt = $this->buildIntentDetectionPrompt($query);

      if ($this->debug) {
        error_log("IntentRouter::detectIntentViaLLM() - Prompt: {$prompt}");
      }

      // Call LLM via Gpt class (uses LLPhant abstraction with automatic fallback)
      // Requirement 18.1: Support multiple LLM providers (OpenAI, Anthropic, Mistral, Ollama, LM Studio)
      // Requirement 18.4: Provider fallback when primary provider fails (handled by Gpt class)
      $llmResponse = Gpt::getGptResponse(
        $prompt,
        self::LLM_MAX_TOKENS,
        self::LLM_TEMPERATURE,
        null, // Use configured default engine
        1
      );

      if ($llmResponse === false || empty($llmResponse)) {
        $this->logger->logWarning("LLM intent detection failed: empty response", [
          'query' => substr($query, 0, 100)
        ]);

        return null;
      }

      if ($this->debug) {
        error_log("IntentRouter::detectIntentViaLLM() - LLM response: {$llmResponse}");
      }

      // Parse and validate JSON response (Requirement 7.4)
      $intent = $this->parseLLMResponse($llmResponse);

      if ($intent === null) {
        $this->logger->logWarning("LLM intent detection failed: invalid JSON", [
          'query' => substr($query, 0, 100),
          'response' => substr($llmResponse, 0, 200)
        ]);

        return null;
      }

      // Add detection method metadata
      $intent['detection_method'] = 'llm';
      $intent['confidence'] = $intent['confidence'] ?? 0.9; // LLM has high confidence

      // Log successful LLM detection (Requirement 18.5)
      $this->logger->logInfo("LLM intent detection successful", [
        'query' => substr($query, 0, 100),
        'intent_type' => $intent['intent'] ?? 'unknown',
        'confidence' => $intent['confidence']
      ]);

      return $intent;

    } catch (\Exception $e) {
      $this->logger->logError("LLM intent detection error: " . $e->getMessage(), [
        'query' => substr($query, 0, 100),
        'exception' => $e->getMessage()
      ]);

      return null;
    }
  }

  /**
   * Build structured prompt for LLM intent detection
   *
   * Implements requirement 7.3: Structured prompt template requesting JSON output.
   * Uses multi-domain language definitions via getDef() for internationalization.
   *
   * @param string $query User query
   * @return string Structured prompt
   */
  private function buildIntentDetectionPrompt(string $query): string
  {
    // Get prompt template from language file with query placeholder replacement
    // The getDef() method automatically replaces {{query}} with the provided value
    $prompt = $this->language->getDef('text_intent_detection_prompt', ['query' => $query]);

    if ($this->debug) {
      error_log("IntentRouter::buildIntentDetectionPrompt() - Using language file prompt template");
    }

    return $prompt;
  }

  /**
   * Parse LLM JSON response and validate intent structure
   *
   * Implements requirement 7.4: Parse and validate JSON response.
   *
   * @param string $llmResponse LLM response text
   * @return array|null Validated intent structure or null if invalid
   */
  private function parseLLMResponse(string $llmResponse): ?array
  {
    // Extract JSON from response (LLM might include extra text)
    $jsonMatch = null;
    if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $llmResponse, $jsonMatch)) {
      $jsonString = $jsonMatch[0];
    } else {
      $jsonString = $llmResponse;
    }

    // Decode JSON
    $intent = json_decode($jsonString, true);

    if ($intent === null || !is_array($intent)) {
      return null;
    }

    // Validate required fields
    if (!isset($intent['intent']) || !in_array($intent['intent'], ['price_comparison', 'product_discovery', 'market_research', 'trend_analysis'])) {
      return null;
    }

    // Ensure all fields exist with defaults
    $validatedIntent = [
      'product' => $intent['product'] ?? null,
      'intent' => $intent['intent'],
      'location' => $intent['location'] ?? null,
      'target_site' => $intent['target_site'] ?? null,
      'mode_hint' => $intent['mode_hint'] ?? null,
      'confidence' => $intent['confidence'] ?? 0.9
    ];

    // Validate mode_hint if present
    if ($validatedIntent['mode_hint'] !== null) {
      $validModeHints = ['mode_a', 'mode_b', 'mode_c', 'hybrid'];
      if (!in_array($validatedIntent['mode_hint'], $validModeHints)) {
        $validatedIntent['mode_hint'] = null;
      }
    }

    // Normalize target_site to lowercase
    if ($validatedIntent['target_site'] !== null) {
      $validatedIntent['target_site'] = strtolower(trim($validatedIntent['target_site']));
    }

    // CRITICAL FIX: Bugfix for french-price-comparison-mode-selection-fix
    // The LLM sometimes returns a non-null mode_hint for price_comparison intent
    // despite language file rules saying it should return null. This causes
    // ModeSelector::selectModes() to take the mode_hint path instead of calling
    // selectPriceComparisonModes(), which means UserInputRequiredResponse is never
    // created and the user never sees the 3-option prompt in web/chat mode.
    // Force mode_hint to null for intents whose mode is determined by ModeSelector
    $intentsRequiringNullHint = ['price_comparison', 'trend_analysis'];
    if (in_array($validatedIntent['intent'], $intentsRequiringNullHint, true) && $validatedIntent['mode_hint'] !== null) {
      $originalModeHint = $validatedIntent['mode_hint'];

      if ($this->debug) {
        error_log(sprintf(
          'IntentRouter::parseLLMResponse() - LLM returned mode_hint for %s, forcing to null (was: %s)',
          $validatedIntent['intent'],
          $originalModeHint
        ));
      }

      $this->logger->logWarning('LLM returned mode_hint for ' . $validatedIntent['intent'] . ' intent, forced to null', [
        'original_mode_hint' => $originalModeHint,
        'intent' => $validatedIntent['intent'],
      ]);

      $validatedIntent['mode_hint'] = null;
    }

    return $validatedIntent;
  }

  /**
   * Detect intent using pattern-based fallback
   *
   * Implements requirement 7.5, 8.1, 8.2, 8.5:
   * - Fallback to pattern matching when LLM fails
   * - Use IntentDetectionPatterns for regex-based detection
   * - Log fallback routing decisions
   *
   * @deprecated Pattern-based logic superseded by Pure LLM Mode. FALLBACK ONLY.
   * @param string $query User query
   * @return array Intent structure
   */
  private function detectIntentViaPatterns(string $query): array
  {
    if ($this->debug) {
      error_log("IntentRouter::detectIntentViaPatterns() - Falling back to pattern-based detection");
    }

    // Use IntentDetectionPatterns for fallback (Requirement 8.1, 8.2)
    $intent = IntentDetectionPatterns::detectIntent($query);

    // Log fallback routing decision (Requirement 8.5)
    $this->logger->logWarning("Fallback to pattern-based intent detection", [
      'query' => substr($query, 0, 100),
      'detected_intent' => $intent['intent'] ?? 'unknown',
      'confidence' => $intent['confidence'] ?? 0.0
    ]);

    return $intent;
  }

  /**
   * Log routing decision for monitoring and debugging
   *
   * Implements requirement 21.2: Log all intent detection operations.
   *
   * @param string $query User query
   * @param RoutingDecision $routing Routing decision
   */
  private function logRoutingDecision(string $query, RoutingDecision $routing): void
  {
    $this->logger->logInfo("Intent routing decision", [
      'query' => substr($query, 0, 100),
      'detected_intent' => $routing->getIntentType(),
      'routing_method' => $routing->getRoutingMethod(),
      'confidence_score' => $routing->getMetadata()['confidence'] ?? 0.0,
      'selected_modes' => $routing->getSelectedModes(),
      'is_hybrid_mode' => $routing->isHybridMode(),
      'location' => $routing->getLocation(),
      'target_site' => $routing->getTargetSite(),
      'location_params' => $routing->getLocationParams()
    ]);
  }

  /**
   * Get mode selector instance
   *
   * Useful for testing and debugging.
   *
   * @return ModeSelector Mode selector instance
   */
  public function getModeSelector(): ModeSelector
  {
    return $this->modeSelector;
  }

  /**
   * Get logger instance
   *
   * Useful for testing and debugging.
   *
   * @return WebSearchLogger Logger instance
   */
  public function getLogger(): WebSearchLogger
  {
    return $this->logger;
  }
}
