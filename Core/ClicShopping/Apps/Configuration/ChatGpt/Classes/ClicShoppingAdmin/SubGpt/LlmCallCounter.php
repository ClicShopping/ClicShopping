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
    $class = explode('::', $site)[0];
    $role ??= self::ROLE_BY_SOURCE[$site] ?? self::ROLE_BY_SOURCE[$class] ?? 'unknown:' . $site;
    self::$byRole[$role] = (self::$byRole[$role] ?? 0) + 1;
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
