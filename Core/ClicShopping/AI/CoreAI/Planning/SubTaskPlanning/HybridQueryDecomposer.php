<?php
/**
 * HybridQueryDecomposer - Pure LLM-based hybrid query decomposition
 * 
 * Decomposes hybrid queries into separate sub-queries using LLM (no pattern matching).
 * Supports multi-domain deployments with domain-aware prompts.
 * 
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * 
 * @created 2026-02-09
 * @see .kiro/specs/hybrid-query-decomposition/requirements.md
 * @see .kiro/specs/hybrid-query-decomposition/design.md
 */

namespace ClicShopping\AI\CoreAI\Planning\SubTaskPlanning;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Infrastructure\Cache\DecompositionCache;
use ClicShopping\AI\Infrastructure\Monitoring\DecompositionPerformanceMonitor;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ResponseProcessor;
use ClicShopping\OM\Registry;

/**
 * HybridQueryDecomposer
 * 
 * Pure LLM-based decomposition of hybrid queries into sub-queries.
 * Uses domain-aware prompts for multi-domain support.
 */
class HybridQueryDecomposer
{
    private bool $debug;
    private ?SecurityLogger $securityLogger;
    private mixed $chat;
    private DecompositionCache $cache;
    private DecompositionPerformanceMonitor $performanceMonitor;
    private bool $decompositionEnabled;
    private ?string $llmProvider;
    
    /**
     * Constructor
     * 
     * @param bool $debug Enable debug logging
     * @param SecurityLogger|null $securityLogger Logger instance
     */
    public function __construct(bool $debug = false, ?SecurityLogger $securityLogger = null)
    {
        // Load configuration (Requirement 12.1, 12.2, 12.3, 12.4)
        $this->loadConfiguration();
        
        // Override debug if explicitly provided
        if ($debug) {
            $this->debug = $debug;
        }
        
        $this->securityLogger = $securityLogger;
        
        // Initialize cache (Requirement 7.3)
        $this->cache = new DecompositionCache(3600, $debug); // 1 hour TTL
        
        // Initialize performance monitor (Requirement 7.1, 7.4)
        $this->performanceMonitor = new DecompositionPerformanceMonitor($debug, $securityLogger, 500);
        
        // Initialize chat for LLM decomposition
        $this->initializeChat();
        
        if ($this->debug) {
            $this->logDebug("HybridQueryDecomposer initialized with caching and performance monitoring");
        }
    }
    
    /**
     * Load configuration from TechnicalConfig
     * 
     * Requirements: 12.1, 12.2, 12.3, 12.4
     * 
     * Loads configuration settings for hybrid query decomposition:
     * - HYBRID_DECOMPOSITION_STATUS: Enable/disable decomposition
     * - HYBRID_DECOMPOSITION_LLM_PROVIDER: LLM provider (uses default if null)
     * - HYBRID_DECOMPOSITION_DEBUG: Debug mode (uses default RAG debug if null)
     * 
     * @return void
     */
    private function loadConfiguration(): void
    {
        // Default: enabled (True)
        $statusConfig = \defined('CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_STATUS') ? CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_STATUS : 'True';
        $this->decompositionEnabled = ($statusConfig === 'True' || $statusConfig === true);

        // Default: null (uses default LLM provider from CLICSHOPPING_APP_CHATGPT_CH_MODEL)
        $this->llmProvider = \defined('CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_LLM_PROVIDER') ? CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_LLM_PROVIDER : null;
        
        // Load debug mode (Requirement 12.3)
        // Default: null (uses default RAG debug from CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER)
        $debugConfig = \defined('CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_DEBUG') ? CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_DEBUG : null;
        
        // If debug config is null, use default RAG debug setting
        if ($debugConfig === null) {
            $defaultDebug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') ? CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER : 'False';
            $this->debug = ($defaultDebug === 'True' || $defaultDebug === true);
        } else {
            $this->debug = ($debugConfig === 'True' || $debugConfig === true);
        }
    }
    
    /**
     * Initialize chat instance for LLM calls
     * 
     * Requirements: 12.2
     * Uses configured LLM provider or defaults to CLICSHOPPING_APP_CHATGPT_CH_MODEL
     */
    private function initializeChat(): void
    {
        // Use configured LLM provider or default (Requirement 12.2)
        $model = $this->llmProvider ?? (\defined('CLICSHOPPING_APP_CHATGPT_CH_MODEL') ? CLICSHOPPING_APP_CHATGPT_CH_MODEL : 'gpt-5-mini');
        
        if ($this->debug) {
            $this->logDebug("Initializing chat with model: {$model}");
        }
        
        try {
            $this->chat = ResponseProcessor::getGptResponse('', null, null, $model);
            
            if ($this->chat === false || $this->chat === null) {
                $this->chat = null;
                if ($this->debug) {
                    $this->logDebug("Chat initialization failed - using fallback mode");
                }
            }
        } catch (\Exception $e) {
            $this->chat = null;
            if ($this->debug) {
                $this->logDebug("Chat initialization error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Log debug message
     *
     * @param string $message Debug message
     */
    private function logDebug(string $message): void
    {
        if ($this->securityLogger) {
            $this->securityLogger->logSecurityEvent($message, 'info');
        }

        if ($this->debug) {
            error_log("[HybridQueryDecomposer] " . $message);
        }
    }
    
    /**
     * Decompose hybrid query into sub-queries using LLM
     *
     * Requirements: 2.1, 2.2, 2.5, 11.1, 11.6, 7.3, 12.1
     *
     * @param string $query Original user query
     * @param array $intent Intent with sub_types array
     * @param array $context Conversation context
     * @return array Array of sub-queries with types
     */
    public function decompose(string $query, array $intent, array $context = []): array
    {
        $queryForDecomposition = $context['translated_query'] ?? $query;

        // Check if decomposition is enabled (Requirement 12.1)
        if (!$this->decompositionEnabled) {
            if ($this->debug) {
                $this->logDebug("Decomposition disabled in configuration - falling back to single step");
            }
            return $this->fallbackToSingleStep($queryForDecomposition, $intent, $context);
        }

        // Start performance tracking (Requirement 7.1, 7.4)
        $operationId = $this->performanceMonitor->startDecomposition($query, $intent);

        if ($this->debug) {
            $this->logDebug("Decomposing hybrid query: " . substr($query, 0, 100));
            $this->logDebug("Sub-types requested: " . json_encode($intent['sub_types'] ?? []));
        }

        // Check cache before calling LLM (Requirement 7.3)
        $cachedResult = $this->cache->getCachedDecomposition($query, $intent);
        if ($cachedResult !== null) {
            if ($this->debug) {
                $this->logDebug("Using cached decomposition result");
            }

            // End performance tracking with cache hit
            $this->performanceMonitor->endDecomposition(
                $operationId,
                $cachedResult,
                true, // cache hit
                true  // success
            );

            return $cachedResult;
        }

        // Retrieve active domain from DomainConfig (Requirement 11.1, 11.6)
        $domain = DomainConfig::getActivities();

        if ($this->debug) {
            $this->logDebug("Active domain: " . ($domain ?: 'generic'));
        }

        // Check if chat is available
        if ($this->chat === null) {
            if ($this->debug) {
                $this->logDebug("Chat not available - falling back to single step");
            }

            $fallbackResult = $this->fallbackToSingleStep($queryForDecomposition, $intent, $context);

            // End performance tracking with error
            $this->performanceMonitor->endDecomposition(
                $operationId,
                $fallbackResult,
                false, // cache miss
                false, // failure
                "Chat not available"
            );

            return $fallbackResult;
        }

        try {
            // Get sub_types from intent
            $subTypes = $intent['sub_types'] ?? [];

            if (empty($subTypes)) {
                if ($this->debug) {
                    $this->logDebug("No sub_types provided - falling back to single step");
                }

                $fallbackResult = $this->fallbackToSingleStep($queryForDecomposition, $intent, $context);

                // End performance tracking with error
                $this->performanceMonitor->endDecomposition(
                    $operationId,
                    $fallbackResult,
                    false, // cache miss
                    false, // failure
                    "No sub_types provided"
                );

                return $fallbackResult;
            }

            // Build domain-aware LLM prompt (Requirement 2.2, 2.6, 11.1)
            $prompt = $this->buildDecompositionPrompt($queryForDecomposition, $subTypes, $domain);

            if ($this->debug) {
                $this->logDebug("Calling LLM for decomposition");
            }

            // Call LLM to decompose query (Requirement 2.2)
            $response = $this->chat->generateText($prompt);

            if ($this->debug) {
                $this->logDebug("LLM response received: " . substr($response, 0, 200));
            }

            // Parse JSON response
            $subQueries = $this->parseJsonResponse($response);

            if ($subQueries === null) {
                if ($this->debug) {
                    $this->logDebug("Failed to parse JSON response - falling back");
                }

                $fallbackResult = $this->fallbackToSingleStep($queryForDecomposition, $intent, $context);

                // End performance tracking with error
                $this->performanceMonitor->endDecomposition(
                    $operationId,
                    $fallbackResult,
                    false, // cache miss
                    false, // failure
                    "Failed to parse JSON response"
                );

                return $fallbackResult;
            }

            // Validate response (Requirement 2.3)
            if (!$this->validateSubQueries($subQueries, $subTypes)) {
                if ($this->debug) {
                    $this->logDebug("Validation failed - falling back");
                }

                $fallbackResult = $this->fallbackToSingleStep($queryForDecomposition, $intent, $context);

                // End performance tracking with error
                $this->performanceMonitor->endDecomposition(
                    $operationId,
                    $fallbackResult,
                    false, // cache miss
                    false, // failure
                    "Validation failed"
                );

                return $fallbackResult;
            }

            if ($this->debug) {
                $this->logDebug("Successfully decomposed into " . \count($subQueries) . " sub-queries");
            }

            // Store decomposition results in cache (Requirement 7.3)
            $this->cache->cacheDecomposition($query, $intent, $subQueries);

            // End performance tracking with success (Requirement 7.1, 7.4)
            $this->performanceMonitor->endDecomposition(
                $operationId,
                $subQueries,
                false, // cache miss
                true   // success
            );

            // Return sub-queries array (Requirement 2.5)
            return $subQueries;

        } catch (\Exception $e) {
            // Fallback on error (Requirement 2.4, 6.1, 6.2)
            if ($this->debug) {
                $this->logDebug("Decomposition error: " . $e->getMessage());
            }

            $fallbackResult = $this->fallbackToSingleStep($queryForDecomposition, $intent, $context);

            // End performance tracking with error
            $this->performanceMonitor->endDecomposition(
                $operationId,
                $fallbackResult,
                false, // cache miss
                false, // failure
                $e->getMessage()
            );

            return $fallbackResult;
        }
    }
    
    /**
     * Fallback when decomposition fails
     *
     * Requirements: 2.4, 6.1, 6.2
     *
     * @param string $query Original query
     * @param array $intent Original intent
     * @return array Single sub-query array
     */
    private function fallbackToSingleStep(string $query, array $intent, array $context = []): array
    {
        // Log fallback reason with full context (Requirement 6.1)
        $this->logDebug("FALLBACK: Decomposition failed, processing as single step");
        $this->logDebug("Original query: " . $query);
        $this->logDebug("Intent type: " . ($intent['type'] ?? 'unknown'));

        if ($this->securityLogger) {
            $this->securityLogger->logSecurityEvent(
                "HybridQueryDecomposer fallback triggered",
                'warning',
                [
                    'query' => $query,
                    'intent_type' => $intent['type'] ?? 'unknown',
                    'sub_types' => $intent['sub_types'] ?? []
                ]
            );
        }

        // If we have multiple requested sub_types, create a multi-step fallback
        $requestedTypes = $intent['sub_types'] ?? [];
        if (is_array($requestedTypes) && count($requestedTypes) > 1) {
            return $this->fallbackToMultiStep($query, $requestedTypes);
        }

        // Create single sub-query from original query (Requirement 2.4)
        $fallbackType = $requestedTypes[0] ?? 'analytics';

        return [
            [
                'type' => $fallbackType,
                'text' => $query,
                'is_fallback' => true
            ]
        ];
    }

    /**
     * Fallback for hybrid queries when LLM decomposition is unavailable.
     * Tries to split by connectors and map parts to requested types.
     *
     * @param string $query Original query
     * @param array $requestedTypes Requested sub_types (e.g., ['semantic','analytics'])
     * @return array Sub-queries array
     */
    private function fallbackToMultiStep(string $query, array $requestedTypes): array
    {
        $parts = $this->splitQueryByConnectors($query);
        $subQueries = [];

        // If we can split into at least 2 parts and we have exactly 2 types
        if (count($parts) >= 2 && count($requestedTypes) === 2) {
            $left = trim($parts[0]);
            $right = trim($parts[1]);

            $leftType = $this->inferSubTypeFromText($left, $requestedTypes);
            $rightType = $this->inferSubTypeFromText($right, $requestedTypes);

            // If both inferred but same, clear to force ordered mapping
            if ($leftType !== null && $rightType !== null && $leftType === $rightType) {
                $leftType = null;
                $rightType = null;
            }

            // If only one side inferred, assign the other type to the other side
            if ($leftType !== null && $rightType === null) {
                $rightType = $this->getOtherType($leftType, $requestedTypes);
            } elseif ($rightType !== null && $leftType === null) {
                $leftType = $this->getOtherType($rightType, $requestedTypes);
            }

            // If still not inferred, map by requested order
            if ($leftType === null || $rightType === null) {
                $leftType = $requestedTypes[0];
                $rightType = $requestedTypes[1];
            }

            $subQueries[] = [
                'type' => $leftType,
                'text' => $left !== '' ? $left : $query,
                'is_fallback' => true
            ];
            $subQueries[] = [
                'type' => $rightType,
                'text' => $right !== '' ? $right : $query,
                'is_fallback' => true
            ];

            return $subQueries;
        }

        // Default: duplicate query for each requested type
        foreach ($requestedTypes as $type) {
            $subQueries[] = [
                'type' => $type,
                'text' => $query,
                'is_fallback' => true
            ];
        }

        return $subQueries;
    }

    /**
     * Split query by common connectors (French/English).
     *
     * @param string $query
     * @return array
     */
    private function splitQueryByConnectors(string $query): array
    {
        $connectors = [
            ' and ',
            ' then ',
            ' & ',
            ' + ',
            ';',
            ',',
        ];

        $lower = strtolower($query);
        foreach ($connectors as $connector) {
            $pos = strpos($lower, $connector);
            if ($pos !== false) {
                $left = trim(substr($query, 0, $pos));
                $right = trim(substr($query, $pos + strlen($connector)));
                if ($left !== '' && $right !== '') {
                    return [$left, $right];
                }
            }
        }

        return [$query];
    }
    
    /**
     * Infer sub-query type from text using lightweight keyword heuristics.
     *
     * @param string $text
     * @param array $requestedTypes
     * @return string|null
     */
    private function inferSubTypeFromText(string $text, array $requestedTypes): ?string
    {
        $lower = strtolower($text);

        $analyticsKeywords = [
            'price', 'total', 'count', 'sum', 'avg',
            'revenue', 'stock', 'quantity',
            'number', 'numbers'
        ];

        $domainEntityKeywords = $this->getDomainEntityKeywords();

        if (!empty($domainEntityKeywords)) {
            $analyticsKeywords = array_merge($analyticsKeywords, $domainEntityKeywords);
        }

        $semanticKeywords = [
            'article', 'terms', 'policy',
            'shipping', 'return', 'privacy', 'description'
        ];

        $domainSemanticKeywords = $this->getDomainSemanticKeywords();

        if (!empty($domainSemanticKeywords)) {
            $semanticKeywords = array_merge($semanticKeywords, $domainSemanticKeywords);
        }

        $analyticsMatch = $this->hasKeyword($lower, $analyticsKeywords);
        $semanticMatch = $this->hasKeyword($lower, $semanticKeywords);

        if ($analyticsMatch && in_array('analytics', $requestedTypes, true,)) {
            return 'analytics';
        }
        if ($semanticMatch && in_array('semantic', $requestedTypes, true,)) {
            return 'semantic';
        }

        return null;
    }
    
    /**
     * Get domain-specific entity keywords dynamically (if available).
     *
     * @return array
     */
    private function getDomainEntityKeywords(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = [];

        try {
            if (!class_exists('ClicShopping\\AI\\Config\\DomainConfig')) {
                return $cache;
            }

            $domain = DomainConfig::getActivities();
            if (empty($domain)) {
                return $cache;
            }

            $domainCandidates = [
                $domain,
                ucfirst(strtolower($domain))
            ];

            foreach ($domainCandidates as $domainCandidate) {
                $entityConfigClass = 'ClicShopping\\Apps\\AI\\' . $domainCandidate . '\\Classes\\ClicShoppingAdmin\\EntityConfig';
                if (class_exists($entityConfigClass) && method_exists($entityConfigClass, 'getEntityTypes')) {
                    $entityTypes = $entityConfigClass::getEntityTypes();
                    $cache = $this->normalizeEntityKeywords($entityTypes);
                    break;
                }
            }
        } catch (\Exception $e) {
            // Keep fallback behavior; do not block query decomposition.
            $cache = [];
        }

        return $cache;
    }

    /**
     * Normalize entity type names into keywords (singular/plural + underscore variants).
     *
     * @param array $entityTypes
     * @return array
     */
    private function normalizeEntityKeywords(array $entityTypes): array
    {
        $keywords = [];

        foreach ($entityTypes as $entityType) {
            $entityType = strtolower((string)$entityType);
            if ($entityType === '') {
                continue;
            }

            $keywords[] = $entityType;
            $keywords[] = str_replace('_', ' ', $entityType);

            if (substr($entityType, -3) === 'ies') {
                $keywords[] = substr($entityType, 0, -3) . 'y';
            } elseif (substr($entityType, -1) === 's') {
                $keywords[] = substr($entityType, 0, -1);
            } else {
                $keywords[] = $entityType . 's';
            }
        }

        return array_values(array_unique(array_filter($keywords)));
    }
    
    /**
     * Get domain-specific semantic keywords dynamically (if available).
     *
     * @return array
     */
    private function getDomainSemanticKeywords(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = [];

        try {
            if (!class_exists('ClicShopping\\AI\\Config\\DomainConfig')) {
                return $cache;
            }

            $domain = \ClicShopping\AI\Config\DomainConfig::getActivities();
            if (empty($domain)) {
                return $cache;
            }

            $domainCandidates = [
                $domain,
                ucfirst(strtolower($domain))
            ];

            foreach ($domainCandidates as $domainCandidate) {
                $analyticsConfigClass = 'ClicShopping\\Apps\\AI\\' . $domainCandidate . '\\Classes\\ClicShoppingAdmin\\AnalyticsConfig';
                if (class_exists($analyticsConfigClass) && method_exists($analyticsConfigClass, 'getNonDatabaseWords')) {
                    $cache = $analyticsConfigClass::getNonDatabaseWords();
                    break;
                }
            }
        } catch (\Exception $e) {
            // Keep fallback behavior; do not block query decomposition.
            $cache = [];
        }

        return $cache;
    }

    /**
     * Check if any keyword exists in text.
     *
     * @param string $text
     * @param array $keywords
     * @return bool
     */
    private function hasKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return the other type from requestedTypes when there are two.
     *
     * @param string $type
     * @param array $requestedTypes
     * @return string|null
     */
    private function getOtherType(string $type, array $requestedTypes): ?string
    {
        if (count($requestedTypes) !== 2) {
            return null;
        }

        return $requestedTypes[0] === $type ? $requestedTypes[1] : $requestedTypes[0];
    }

    /**
     * Build LLM prompt for decomposition (domain-aware)
     *
     * @param string $query Original query
     * @param array $subTypes Sub-types from intent (e.g., ['analytics', 'semantic'])
     * @param string $domain Active domain from DomainConfig
     * @return string LLM prompt
     */
    private function buildDecompositionPrompt(string $query, array $subTypes, string $domain): string
    {
        // Get language instance
        $language = Registry::get('Language');

        // Load language definitions for SQL correction prompts using DomainConfig
        DomainConfig::loadLanguageFile('rag_hybrid_query_decomposer');

        $domainContext = $this->getDomainContext();
        $domainExamples = $this->getDomainExamples();

        $subTypeLines = [];
        foreach ($subTypes as $type) {
            $subTypeLines[] = "- {$type}: " . $this->getTypeDescription($type);
        }

        $template = $language->getDef('text_rag_hybrid_decomposer_prompt_template', [
            'domain_name' => $domainContext['name'],
            'domain_terminology' => $domainContext['terminology'],
            'domain_data_types' => $domainContext['data_types'],
            'domain_examples' => $domainExamples,
            'sub_types_list' => implode("\n", $subTypeLines),
            'sub_types_csv' => implode(', ', $subTypes),
            'sub_type_count' => (string)count($subTypes),
            'query' => $query,
        ]);

        if (empty($template) || $template === 'text_rag_hybrid_decomposer_prompt_template') {
            // Fallback to a minimal prompt if the language file is missing
            return "You are a query decomposition expert for a {$domainContext['name']} system.\n\n"
                . "DOMAIN CONTEXT:\n"
                . "- Domain: {$domainContext['name']}\n"
                . "- Terminology: {$domainContext['terminology']}\n"
                . "- Data Types: {$domainContext['data_types']}\n\n"
                . "REQUESTED SUB-QUERY TYPES:\n"
                . implode("\n", $subTypeLines) . "\n\n"
                . "EXAMPLES FOR {$domainContext['name']} DOMAIN:\n"
                . $domainExamples . "\n\n"
                . "QUERY TO DECOMPOSE:\n"
                . "\"{$query}\"\n\n"
                . "INSTRUCTIONS:\n"
                . "1. Split the query into exactly " . count($subTypes) . " sub-queries\n"
                . "2. Each sub-query must have a 'type' and 'text' field\n"
                . "3. Types must match the requested types: " . implode(', ', $subTypes) . "\n"
                . "4. Preserve the original meaning and context\n"
                . "5. Use {$domainContext['name']} terminology\n"
                . "6. If the query is multi-intent joined by 'and', separate the metric/COUNT intent into the analytics sub-query and the policy/article intent into the semantic sub-query\n"
                . "7. Keep sub-queries concise and focused on their intent\n\n"
                . "OUTPUT FORMAT (JSON):\n"
                . "[\n"
                . "  {\"type\": \"analytics\", \"text\": \"sub-query text\"},\n"
                . "  {\"type\": \"semantic\", \"text\": \"sub-query text\"}\n"
                . "]\n\n"
                . "Return ONLY the JSON array, no additional text.";
        }

        return $template;
    }

    /**
     * Get domain-specific context
     *
     * Retrieves domain information from language definitions
     *
     * @param string $domain Domain identifier
     * @return array Domain context
     */
    private function getDomainContext(): array
    {
        $language = Registry::get('Language');

        $domainName = $language->getDef('text_rag_hybrid_decomposer_domain_name');
        $terminology = $language->getDef('text_rag_hybrid_decomposer_terminology');
        $dataTypes = $language->getDef('text_rag_hybrid_decomposer_data_types');

        return [
            'name' => !empty($domainName) ? $domainName : 'Business',
            'terminology' => !empty($terminology) ? $terminology : 'data, records, entities, metrics, reports',
            'data_types' => !empty($dataTypes) ? $dataTypes : 'records, metrics, reports, entities',
        ];
    }

    /**
     * Get domain-specific examples
     *
     * Examples are tailored to each domain's terminology and use cases
     *
     * @param string $domain Domain identifier
     * @return string Examples text
     */
    private function getDomainExamples(): string
    {
        $language = Registry::get('Language');
        $examples = $language->getDef('text_rag_hybrid_decomposer_examples');

        if (!empty($examples)) {
            return $examples;
        }

        return "Example (Generic):\n"
            . "Query: \"total records and details for item X\"\n"
            . "Sub-queries: [{\"type\": \"analytics\", \"text\": \"total records\"}, {\"type\": \"semantic\", \"text\": \"details for item X\"}]\n\n"
            . "Example 2 (Generic):\n"
            . "Query: \"count of entities and description of entity Y\"\n"
            . "Sub-queries: [{\"type\": \"analytics\", \"text\": \"count of entities\"}, {\"type\": \"semantic\", \"text\": \"description of entity Y\"}]";
    }

    /**
     * Get description for sub-query type
     *
     * @param string $type Sub-query type
     * @return string Type description
     */
    private function getTypeDescription(string $type): string
    {
        $descriptions = [
            'analytics' => 'Quantitative query requiring database aggregation (COUNT, SUM, AVG, etc.)',
            'semantic' => 'Qualitative query requiring semantic search or document retrieval',
            'web_search' => 'Query requiring external web search',
        ];

        return $descriptions[$type] ?? 'Query of type ' . $type;
    }

    /**
     * Parse JSON response from LLM
     *
     * @param string $response LLM response
     * @return array|null Parsed sub-queries or null on error
     */
    private function parseJsonResponse(string $response): ?array
    {
        // Try to extract JSON from response
        $response = trim($response);

        // Remove markdown code blocks if present
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);

        // Try to decode JSON
        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($this->debug) {
                $this->logDebug("JSON decode error: " . json_last_error_msg());
            }
            return null;
        }

        return $decoded;
    }
    
    /**
     * Validate sub-queries returned by LLM
     *
     * @param array $subQueries Sub-queries from LLM
     * @param array $requestedTypes Requested sub-types
     * @return bool True if valid
     */
    private function validateSubQueries(array $subQueries, array $requestedTypes): bool
    {
        // Check if array
        if (!is_array($subQueries)) {
            if ($this->debug) {
                $this->logDebug("Validation failed: not an array");
            }
            return false;
        }

        // Check count matches requested types
        if (count($subQueries) !== count($requestedTypes)) {
            if ($this->debug) {
                $this->logDebug("Validation failed: count mismatch (expected " . count($requestedTypes) . ", got " . count($subQueries) . ")");
            }
            return false;
        }

        // Check each sub-query has 'type' and 'text' fields
        foreach ($subQueries as $index => $subQuery) {
            if (!isset($subQuery['type']) || !isset($subQuery['text'])) {
                if ($this->debug) {
                    $this->logDebug("Validation failed: sub-query {$index} missing 'type' or 'text' field");
                }
                return false;
            }

            // Verify type matches requested types
            if (!in_array($subQuery['type'], $requestedTypes, true)) {
                if ($this->debug) {
                    $this->logDebug("Validation failed: sub-query {$index} type '{$subQuery['type']}' not in requested types");
                }
                return false;
            }

            // Ensure no empty query texts
            if (empty(trim($subQuery['text']))) {
                if ($this->debug) {
                    $this->logDebug("Validation failed: sub-query {$index} has empty text");
                }
                return false;
            }
        }

        if ($this->debug) {
            $this->logDebug("Validation passed");
        }

        return true;
    }
    
    /**
     * Get performance statistics
     *
     * @param int $days Number of days to analyze (default: 7)
     * @return array Performance statistics
     */
    public function getPerformanceStats(int $days = 7): array
    {
        return $this->performanceMonitor->getPerformanceStats($days);
    }
    
    /**
     * Check if decomposition is enabled
     *
     * @return bool True if decomposition is enabled
     */
    public function isDecompositionEnabled(): bool
    {
        return $this->decompositionEnabled;
    }
    
    /**
     * Get configured LLM provider
     *
     * @return string|null LLM provider or null for default
     */
    public function getLlmProvider(): ?string
    {
        return $this->llmProvider;
    }
    
    /**
     * Get debug mode status
     *
     * @return bool True if debug mode is enabled
     */
    public function isDebugEnabled(): bool
    {
        return $this->debug;
    }
}
