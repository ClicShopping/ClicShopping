<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

/**
 * LlmCallCounter
 *
 * Process-wide counter of real LLM round-trips, reset and read per request. It exists
 * because no single log records the exact number of LLM calls a request makes:
 * rag_statistics writes one row per interaction, and ~15 call sites invoke
 * $chat->generateText() directly, bypassing the Gpt façade. Every LLphant chat object is
 * wrapped by {@see CountingChat} at construction, so a single increment point captures
 * both the façade path and the raw path.
 *
 * Static by design: the chat objects are created deep in the provider layer and read back
 * by the orchestrator / eval harness with no shared instance to thread through. The count
 * is per PHP process (one web request = one process under PHP-FPM); call reset() at the
 * start of a request to scope it.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt
 */
final class LlmCallCounter
{
  /**
   * Callers that only relay a prompt on behalf of someone else: the stack walk steps over
   * them so the call is filed under the class that actually wanted it.
   */
  private const PASS_THROUGH = ['TranslationHandler', 'ParallelLLMExecutor', 'LLMServiceWrapper'];

  /**
   * Role of each LLM call site, keyed by `Class::method` then by `Class`.
   * reasoning = deciding what to do; generation = producing the artefact or the answer;
   * validation / correction / translation / normalization / retrieval = the rest of the work;
   * plumbing = anything that hits the model without either thinking or producing.
   */
  private const ROLE_BY_SOURCE = [
    // Deciding: intent, plan, decomposition, resolution of what the question refers to.
    'ReasoningAgent' => 'reasoning',
    'ReasoningStrategies' => 'reasoning',
    'AnalysisPlanner::plan' => 'reasoning',
    'HybridQueryPlanner::analyze' => 'reasoning',
    'HybridQueryDecomposer' => 'reasoning',
    'QuerySplitter::splitHybridQuery' => 'reasoning',
    'ComplexQueryHandler::decomposeComplexQuery' => 'reasoning',
    'UnifiedMetadataExtractor::extract' => 'reasoning',
    'IntentRouter::detectIntentViaLLM' => 'reasoning',
    'AmbiguousQueryDetector::detectAmbiguity' => 'reasoning',
    'AmbiguousQueryDetector::clarifyQueryForInterpretation' => 'reasoning',
    // Reached through the relay: it is the commissioner of the parallel clarification calls.
    'AmbiguousQueryDetector::generateMultipleInterpretations' => 'reasoning',
    'ReferenceResolver' => 'reasoning',
    'EntityMatcher' => 'reasoning',
    'ClassificationEngine::computeSemantics' => 'reasoning',
    'ReputationAnalyzer::analyzeReputation' => 'reasoning',
    'WeightingLlmClient::callLLM' => 'reasoning',
    'AnalyticsAgent::processAnalyticsQuery' => 'reasoning',

    // Producing: SQL, the answer text, an editorial artefact.
    'AnalyticsAgent::generateSqlQueries' => 'generation',
    'AnalyticsAgent::processAnalyticsQuery{closure}' => 'generation',
    'ResultInterpreter::interpretResults' => 'generation',
    'EmptyResultFormatter' => 'generation',
    'MultiDBRAGManager::answerQuestion' => 'generation',
    'LLMFallbackHandler::queryLLM' => 'generation',
    'SemanticAgent::createTaxonomy' => 'generation',
    'LlmAnalysisGenerator::generate' => 'generation',
    'CockpitAIOrchestrator::executeAnalysis' => 'generation',
    'MarketAnalysisEnhancer' => 'generation',
    'ReviewSentimentGenerator::generate' => 'generation',
    'SeoAgenticPipeline' => 'generation',
    'Insert::execute' => 'generation',
    'AIProcess::generateInsights' => 'generation',
    'SaveEntry' => 'generation',

    // Checking a produced artefact against something else.
    'LlmResponseEvaluator::callEvaluationModel' => 'validation',
    'HallucinationDetector::detectOutOfContext' => 'validation',
    'SemanticSecurityAnalyzer::callLlmAnalysis' => 'validation',
    'GamingDetector::detectGaming' => 'validation',
    'ReviewSentimentFidelityChecker::check' => 'validation',
    'AmbiguousQueryDetector::critiqueAnalysis' => 'validation',

    // Repairing a failed artefact.
    'ErrorAnalyzer::analyzErrorWithLLM' => 'correction',
    'CorrectionStrategyManager::correctWithLLMReasoning' => 'correction',
    'ColumnErrorStrategy::correctOrderByError' => 'correction',
    'DiagnosticManager::explainLastError' => 'correction',

    // Moving the same content between languages.
    'SemanticAgent::translateToLanguage' => 'translation',
    'SentimentAnalysisTranslator::translate' => 'translation',
    'TranslationServiceWrapper' => 'translation',

    // Rewriting the question into the pipeline's working form (English).
    'SemanticAgent::translateToEnglish' => 'normalization',
    'EnglishQueryNormalizer' => 'normalization',

    // Choosing what context to feed the next step.
    'DocumentReranker::transformDocuments' => 'retrieval',
  ];

  private static int $count = 0;

  /** @var array<string, int> round-trips per role, same scope as $count */
  private static array $byRole = [];

  /**
   * @var array<string, int> round-trips per CALL SITE (`Class::method`), same scope as $count.
   * A role cannot answer "did this branch run?" once the site is mapped into a shared role.
   */
  private static array $bySite = [];

  /**
   * @var array<string, array{prompt:int,completion:int,reasoning:int}> tokens per role, same
   * scope as $count. BENCH-3 (2a): an appel is not a unit of work, a completion token is.
   */
  private static array $tokensByRole = [];

  /** @var array<string, array{prompt:int,completion:int,reasoning:int}> tokens per CALL SITE */
  private static array $tokensBySite = [];

  /**
   * @var array<string, int> round-trips whose provider reported NO usage, per call site. A
   * silent provider must be a NAMED zero: a plain 0 would read as a free call.
   */
  private static array $unmeasured = [];

  /**
   * @var resource|null|false Capture sink of PROMPT-1: `false` = not looked up yet, `null` = off.
   * Opened only when CLICSHOPPING_LLM_CAPTURE names a file — a diagnostic, never a production log.
   */
  private static mixed $capture = false;

  /**
   * Append one captured round-trip line to the sink, filed under the same site as its
   * increment(). No-op unless CLICSHOPPING_LLM_CAPTURE names a writable file.
   */
  private static function captureLine(string $kind, mixed $payload): void
  {
    if (self::$capture === false) {
      $path = (string)getenv('CLICSHOPPING_LLM_CAPTURE');
      self::$capture = $path === '' ? null : (fopen($path, 'ab') ?: null);
    }

    if (self::$capture === null) {
      return;
    }

    fwrite(self::$capture, json_encode(
      ['kind' => $kind, 'site' => self::deriveSite(), 'payload' => $payload],
      JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    ) . "\n");
  }

  /**
   * File the exact payload emitted to the model, when capture is on. This is what answers
   * "what is in the prompt, and how much of it is the same from one call to the next".
   */
  public static function capturePrompt(mixed $payload): void
  {
    self::captureLine('prompt', $payload);
  }

  /**
   * Increment the counter by one LLM round-trip. Called by {@see CountingChat} on its six
   * generation methods, and by call sites that reach a provider without a chat object.
   *
   * @param string|null $role Explicit role; derived from the call stack when null.
   */
  public static function increment(?string $role = null): void
  {
    self::$count++;
    $site = self::deriveSite();
    self::$bySite[$site] = (self::$bySite[$site] ?? 0) + 1;
    $role ??= self::roleOf($site);
    self::$byRole[$role] = (self::$byRole[$role] ?? 0) + 1;
  }

  /**
   * File the token usage of the round-trip just made under the SAME site and role as its
   * increment() — the site is re-derived from the stack, so ordering never matters (the raw
   * HTTP path records its usage only once the batch has settled).
   *
   * @param mixed $usage Provider response, its `usage` member, or a decoded JSON body.
   *                     Anything unreadable counts as a NAMED unmeasured call, never a zero.
   */
  public static function recordTokens(mixed $usage): void
  {
    $site = self::deriveSite();
    self::captureLine('usage', self::unwrapUsage($usage));
    $tokens = self::normalizeUsage($usage);

    if ($tokens === null) {
      self::$unmeasured[$site] = (self::$unmeasured[$site] ?? 0) + 1;
      return;
    }

    $role = self::roleOf($site);

    foreach ($tokens as $kind => $n) {
      self::$tokensByRole[$role][$kind] = (self::$tokensByRole[$role][$kind] ?? 0) + $n;
      self::$tokensBySite[$site][$kind] = (self::$tokensBySite[$site][$kind] ?? 0) + $n;
    }
  }

  /**
   * Tokens per role since the last reset. `completion` is the unit that answers "does this
   * call carry more work?"; `prompt` measures the input, `reasoning` the hidden thinking when
   * the provider exposes it.
   *
   * @return array<string, array{prompt:int,completion:int,reasoning:int}>
   */
  public static function tokensByRole(): array
  {
    return self::$tokensByRole;
  }

  /**
   * Tokens per call site since the last reset, same shape as {@see self::tokensByRole()}.
   *
   * @return array<string, array{prompt:int,completion:int,reasoning:int}>
   */
  public static function tokensBySite(): array
  {
    return self::$tokensBySite;
  }

  /**
   * Round-trips per call site whose provider reported no usage. Non-empty means the token
   * figures under-count by that many calls — read it before reading the totals.
   *
   * @return array<string, int>
   */
  public static function unmeasuredCalls(): array
  {
    return self::$unmeasured;
  }

  /**
   * Current number of LLM round-trips counted since the last reset.
   */
  public static function count(): int
  {
    return self::$count;
  }

  /**
   * Round-trips per role since the last reset, e.g. ['reasoning' => 4, 'generation' => 2].
   *
   * @return array<string, int>
   */
  public static function breakdown(): array
  {
    return self::$byRole;
  }

  /**
   * Round-trips per call site (`Class::method`) since the last reset. This is what answers
   * "did that branch run at all?" — a role cannot, once several sites share it.
   *
   * @return array<string, int>
   */
  public static function sites(): array
  {
    return self::$bySite;
  }

  /**
   * Reset the counter to zero. Call once at the start of a request to scope the count to it.
   */
  public static function reset(): void
  {
    self::$count = 0;
    self::$byRole = [];
    self::$bySite = [];
    self::$tokensByRole = [];
    self::$tokensBySite = [];
    self::$unmeasured = [];
  }

  /**
   * Role of a call site: exact `Class::method` first, then the class, then a named unknown.
   */
  private static function roleOf(string $site): string
  {
    $class = explode('::', $site)[0];

    return self::ROLE_BY_SOURCE[$site] ?? self::ROLE_BY_SOURCE[$class] ?? 'unknown:' . $site;
  }

  /**
   * Peel a provider response down to its `usage` member, whichever shape it arrived in.
   */
  private static function unwrapUsage(mixed $usage): mixed
  {
    if (\is_object($usage) && isset($usage->usage)) {
      return $usage->usage;
    }

    if (\is_array($usage) && isset($usage['usage'])) {
      return $usage['usage'];
    }

    return $usage;
  }

  /**
   * Read a provider usage payload into prompt/completion/reasoning counts. Accepts the
   * LLphant response object, its `usage` member, or a decoded raw-HTTP JSON body.
   * ⛔ Never falls back to an estimate: characters/4 would measure the estimator, not the
   * model. Unreadable input returns null so the call is filed as unmeasured.
   *
   * @return array{prompt:int,completion:int,reasoning:int}|null
   */
  private static function normalizeUsage(mixed $usage): ?array
  {
    $usage = self::unwrapUsage($usage);

    if (\is_object($usage)) {
      $usage = [
        'prompt_tokens' => $usage->promptTokens ?? null,
        'completion_tokens' => $usage->completionTokens ?? null,
        'reasoning_tokens' => $usage->completionTokensDetails->reasoningTokens ?? null,
      ];
    }

    if (!\is_array($usage)) {
      return null;
    }

    // Ollama names the same two figures prompt_eval_count / eval_count on the raw HTTP path.
    $prompt = $usage['prompt_tokens'] ?? $usage['promptTokens'] ?? $usage['prompt_eval_count'] ?? null;
    $completion = $usage['completion_tokens'] ?? $usage['completionTokens'] ?? $usage['eval_count'] ?? null;

    if ($prompt === null && $completion === null) {
      return null;
    }

    return [
      'prompt' => (int)$prompt,
      'completion' => (int)$completion,
      'reasoning' => (int)($usage['reasoning_tokens']
        ?? $usage['completion_tokens_details']['reasoning_tokens'] ?? 0),
    ];
  }

  /**
   * Call site of the current call, read off the stack: the first frame outside the ChatGpt
   * provider layer that is not a relay. Depth is unbounded on purpose — the façade path is
   * several frames deep and a truncated stack would file real callers as unknown.
   */
  private static function deriveSite(): string
  {
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
      $class = $frame['class'] ?? '';

      if ($class === '' || str_contains($class, '\\ChatGpt\\Classes\\')) {
        continue;
      }

      $short = basename(str_replace('\\', '/', $class));

      if (\in_array($short, self::PASS_THROUGH, true)) {
        continue;
      }

      return $short . '::' . self::normalizeFunction((string)($frame['function'] ?? ''));
    }

    return 'no-caller';
  }

  /**
   * A closure frame is reported as `{closure:Class::method():LINE}`: the line number would make
   * the site key move on every edit above it. Keep the enclosing method, marked as a closure so
   * it keeps its own role — the closure rarely does the same work as its host method.
   */
  private static function normalizeFunction(string $function): string
  {
    if (preg_match('/^\{closure:.*::(\w+)\(\):\d+\}$/', $function, $m) === 1) {
      return $m[1] . '{closure}';
    }

    return $function;
  }
}
