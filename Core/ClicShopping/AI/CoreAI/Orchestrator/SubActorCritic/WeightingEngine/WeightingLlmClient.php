<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * WeightingLlmClient - Shared LLM transport for the adaptive weighting subsystem
 *
 * Centralises the low-level plumbing used by every LLM-backed step of the weighting
 * engine (adaptive weights, bounds determination, anomaly detection): a single retry
 * loop with exponential backoff, the Gpt facade call, lenient JSON repair, and the
 * matching retry log lines. Parsing/validation stays with each caller because the
 * expected JSON shape is step-specific.
 *
 * All LLM access goes through the {@see Gpt} facade (LLPhant abstraction) — no direct
 * provider call. This client performs no database access.
 */
class WeightingLlmClient
{
    private int $maxRetries;
    private bool $debug;
    private string $errorLogPath;

    /**
     * Constructor
     *
     * @param int $maxRetries Number of retries on transport failure (exponential backoff)
     * @param bool $debug Gates verbose error_log (retry traces); resolved by the engine from
     *                    CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER plus any config override
     * @param string $errorLogPath Destination file for debug log lines
     */
    public function __construct(int $maxRetries, bool $debug, string $errorLogPath)
    {
        $this->maxRetries = $maxRetries;
        $this->debug = $debug;
        $this->errorLogPath = $errorLogPath;
    }

    /**
     * Call LLM service with retry logic and exponential backoff
     *
     * Attempts to call the LLM service with exponential backoff retry.
     * Retries up to maxRetries times on failure with delays: 1s, 2s.
     *
     * @param string $prompt Structured prompt for LLM
     * @return string LLM response (JSON)
     * @throws \RuntimeException If all retries fail
     */
    public function callLLMWithRetry(string $prompt): string
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $response = $this->callLLM($prompt);

                if ($attempt > 0) {
                    $this->logRetrySuccess($attempt);
                }

                return $response;

            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;

                // Log retry attempt
                $this->logRetryAttempt($attempt, $this->maxRetries, $e->getMessage());

                if ($attempt <= $this->maxRetries) {
                    // Exponential backoff: 1s, 2s
                    $waitTime = pow(2, $attempt - 1);
                    sleep($waitTime);
                }
            }
        }

        // All retries failed
        throw new \RuntimeException(
            "LLM call failed after {$this->maxRetries} retries: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }

    /**
     * Call LLM via Gpt facade
     *
     * @param string $prompt Prompt to send
     * @return string LLM response
     * @throws \RuntimeException If LLM call fails
     */
    private function callLLM(string $prompt): string
    {
        $fullPrompt = $prompt . "\n\nIMPORTANT: You MUST respond with valid JSON only. Do not include any text before or after the JSON object.";

        $chat = Gpt::getChatForModel();
        $response = $chat->generateText($fullPrompt);

        if (empty($response)) {
            throw new \RuntimeException('Empty response from LLM');
        }

        return $response;
    }

    /**
     * Lenient, model-agnostic repair of common LLM JSON defects.
     *
     * Fixes the blemishes that recur across models regardless of prompt — trailing commas before a
     * closing brace/bracket and stray ASCII control characters — so a single malformed body does not
     * force a regeneration or fallback. This is mechanical JSON cleanup, not model-output pattern
     * matching, and the result is still validated by the caller.
     *
     * @param string $json Candidate JSON string
     * @return string Repaired JSON string
     */
    public function repairJson(string $json): string
    {
        // Drop trailing commas: {"a":1,} or [1,2,] -> valid JSON
        $json = preg_replace('/,\s*([}\]])/', '$1', $json);
        // Replace ASCII control characters (keep the \t \n \r that JSON permits) with a space
        $json = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $json);

        return $json;
    }

    /**
     * Log retry attempt
     *
     * Requirements: 19.4
     *
     * @param int $attempt Current attempt number
     * @param int $maxRetries Maximum retries allowed
     * @param string $error Error message that triggered the retry
     * @return void
     */
    public function logRetryAttempt(int $attempt, int $maxRetries, string $error): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] [WARNING] RETRY - Attempt %d/%d | Error: %s\n",
            $timestamp,
            $attempt,
            $maxRetries,
            $error
        );

        $this->debugLog($message);
    }

    /**
     * Log successful retry
     *
     * Requirements: 19.4
     *
     * @param int $attempt Attempt number that succeeded
     * @return void
     */
    private function logRetrySuccess(int $attempt): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] [INFO] RETRY SUCCESS - Succeeded on attempt %d\n",
            $timestamp,
            $attempt
        );

        $this->debugLog($message);
    }

    /**
     * Write a debug line to the error log when debug mode is enabled.
     *
     * @param string $message Pre-formatted log line
     * @return void
     */
    private function debugLog(string $message): void
    {
        if (!$this->debug) {
            return;
        }

        error_log($message, 3, $this->errorLogPath);
    }
}
