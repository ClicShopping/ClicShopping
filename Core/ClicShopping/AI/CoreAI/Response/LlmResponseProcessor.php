<?php
/**
 * LlmResponseProcessor
 *
 * Post-processes LLM responses to ensure they are properly structured
 * and contain the necessary metadata for the ResultFormatter.
 *
 * Refactored: Direct processing of LLM responses in structured JSON format
 * to eliminate dependency on fragile regular expressions.
 *
 * @package ClicShopping\AI\CoreAI\Response
 * @version 2.0 - Refactored to use JSON-based processing
 */

namespace ClicShopping\AI\CoreAI\Response;


use ClicShopping\AI\Security\SecurityLogger;


class LlmResponseProcessor
{
    private SecurityLogger $securityLogger;
    private bool $debug;

    // Required JSON fields for validation
    private const REQUIRED_FIELDS = [
        'response_type',
        'interpretation',
        'entity_type'
    ];

    public function __construct()
    {
        $this->securityLogger = new SecurityLogger();
        $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';
    }

    /**
     * Post-processes an LLM response expecting to receive a structured JSON string.
     *
     * @param mixed $response Raw JSON string from LLM
     * @param string $originalQuery Original question (maintained for context)
     * @param string $intentType Detected intent type (maintained for context/validation)
     * @return array Structured response (PHP array)
     */
    public function processResponse($response, string $originalQuery, string $intentType): array
    {
        if ($this->debug) {
            $this->securityLogger->logSecurityEvent(
                "LlmResponseProcessor: Attempting to decode JSON response for intent '$intentType'",
                'info'
            );
        }

        // --- 1. Decode JSON to PHP array ---
        $decodedResponse = $this->decodeJsonResponse($response);

        // If decoding fails, or if response is already an unstructured array (rare fallback case)
        if (!is_array($decodedResponse)) {
            $errorResponse = $this->handleDecodingFailure($response, $originalQuery, $intentType);
            return $errorResponse;
        }

        // --- 2. Validate presence of required fields ---
        $validatedResponse = $this->validateAndNormalizeResponse($decodedResponse, $originalQuery, $intentType);

        if ($this->debug) {
            $responseType = $validatedResponse['response_type'] ?? 'unknown';
            $entityType = $validatedResponse['entity_type'] ?? 'unknown';
            $this->securityLogger->logSecurityEvent(
                "LlmResponseProcessor: Successfully processed JSON response (Type: $responseType, Entity: $entityType)",
                'info'
            );
        }

        // The method returns the structure directly provided and validated by the LLM
        return $validatedResponse;
    }

    /**
     * Attempts to decode the JSON response.
     *
     * @param mixed $response Response from LLM
     * @return array|null Decoded array or null if decoding fails
     */
    private function decodeJsonResponse($response): ?array
    {
        // If it's already an array, return it (test cases or internal chaining)
        if (is_array($response)) {
            return $response;
        }

        // Ensure it's a string
        $jsonString = is_string($response) ? trim($response) : (string) $response;

        // Try to remove Markdown code blocks around JSON
        if (str_starts_with($jsonString, '```json') && str_ends_with($jsonString, '```')) {
            $jsonString = substr($jsonString, 7, -3); // Remove "```json" and "```"
            $jsonString = trim($jsonString);
        } elseif (str_starts_with($jsonString, '```') && str_ends_with($jsonString, '```')) {
            $jsonString = substr($jsonString, 3, -3); // Remove "```" and "```"
            $jsonString = trim($jsonString);
        }

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($this->debug) {
                $this->securityLogger->logSecurityEvent(
                    "JSON Decoding Failed: " . json_last_error_msg(),
                    'warning'
                );
            }
            return null;
        }

        return $decoded;
    }

    /**
     * Handles cases where the response is not valid JSON (e.g., simple text response).
     * Creates a 'semantic_results' response by default.
     *
     * @param mixed $response Original response
     * @param string $originalQuery Original query
     * @param string $intentType Intent type
     * @return array Fallback response structure
     */
    private function handleDecodingFailure($response, string $originalQuery, string $intentType): array
    {
        $responseText = is_string($response) ? $response : (string) $response;

        if ($this->debug) {
            $this->securityLogger->logSecurityEvent(
                "LlmResponseProcessor: Failed to decode JSON. Falling back to simple text response.",
                'error'
            );
        }

        // Minimalist fallback
        return [
            'response_type' => 'semantic_results', // Default type
            'question' => $originalQuery,
            'interpretation' => 'LLM response format error. Raw response: ' . substr($responseText, 0, 200) . '...',
            'entity_id' => 0,
            'entity_type' => 'general',
            'data_results' => [],
            // Add raw response to logger for debugging
            'raw_llm_output' => $responseText,
        ];
    }

    /**
     * Validates the JSON response structure and adds default fields.
     *
     * @param array $response Decoded response
     * @param string $originalQuery Original query
     * @param string $intentType Intent type
     * @return array Normalized response
     */
    private function validateAndNormalizeResponse(array $response, string $originalQuery, string $intentType): array
    {
        $normalized = $response;

        // Ensure key fields are present, otherwise use default values
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($normalized[$field])) {
                // Log alert because LLM didn't respect the schema
                if ($this->debug) {
                    $this->securityLogger->logSecurityEvent(
                        "JSON structure error: Missing required field '$field'. Using default value.",
                        'warning'
                    );
                }
                // Use default values
                $normalized[$field] = match ($field) {
                    'response_type' => 'semantic_results',
                    'interpretation' => $originalQuery, // Fallback with the question
                    'entity_type' => 'general',
                    default => '',
                };
            }
        }

        // Add/Ensure presence of additional fields required by the processor
        $normalized['question'] = $originalQuery;
        $normalized['entity_id'] = $normalized['entity_id'] ?? 0;
        $normalized['data_results'] = $normalized['data_results'] ?? [];
        $normalized['source_attribution'] = $normalized['source_attribution'] ?? [];

        // Rename 'results' to 'data_results' if 'results' exists (for internal compatibility if needed)
        if (isset($normalized['results'])) {
            if (!isset($normalized['data_results']) || empty($normalized['data_results'])) {
                $normalized['data_results'] = $normalized['results'];
            }
            unset($normalized['results']);
        }

        // Isolate SQL query in a dedicated key if interpretation contained it
        // This step is kept as a safety net, but should ideally be filled by the LLM
        if ($normalized['response_type'] === 'analytics_response' && !isset($normalized['sql_query'])) {
            $sql = $this->extractSqlQueryFromText($normalized['interpretation'] ?? '');
            if (!empty($sql)) {
                $normalized['sql_query'] = $sql;
            }
        }

        return $normalized;
    }

    // --- Elimination of unreliable regex extraction methods (Kept only as safety net) ---

    /**
     * Extracts an SQL query if accidentally included in the interpretation text.
     * This function is a safety net. The LLM should use the 'sql_query' key.
     *
     * @param string $responseText Response text
     * @return string Extracted SQL query or empty string
     */
    private function extractSqlQueryFromText(string $responseText): string
    {
        // Robust pattern for SQL SELECT (if not in dedicated JSON key)
        if (preg_match('/SELECT.*?FROM.*?(?:WHERE.*?)?(?:ORDER BY.*?)?(?:LIMIT.*?)?/is', $responseText, $matches)) {
            return trim($matches[0]);
        }

        return '';
    }

    // --- Old methods (removed/replaced according to requirements) ---
    /*
     * The following methods have been removed as they relied on fragile regex patterns:
     * - private function restructureResponse($response, string $originalQuery, string $intentType): array
     * - private function extractEntityId(string $responseText): int
     * - private function determineEntityType(string $responseText, string $originalQuery): string
     * - private function extractStructuredData(string $responseText): array
     * - private function determineResponseType(string $intentType, string $originalQuery): string
     * - private function extractSqlQuery(string $responseText): string (replaced by extractSqlQueryFromText)
     *
     * These methods have been replaced by JSON-based processing where the LLM provides
     * structured data directly, eliminating the need for regex extraction.
     *
     * The LLM is now expected to return responses in the following JSON format:
     * {
     *   "response_type": "analytics_response | semantic_results | web_search_result",
     *   "entity_id": 123,
     *   "entity_type": "product | customer | order | general",
     *   "interpretation": "Natural language response text for the user.",
     *   "source_attribution": [...], // For RAG/Web Search
     *   "data_results": [
     *     {
     *       "products_id": 10,
     *       "products_model": "REF-ABC",
     *       "products_quantity": 50,
     *       "price": 19.99
     *     }
     *   ],
     *   "sql_query": "SELECT ...", // For analytics queries
     *   "tool_used": "bi_tool | rag_tool | web_search_tool" // Optional
     * }
     */
}