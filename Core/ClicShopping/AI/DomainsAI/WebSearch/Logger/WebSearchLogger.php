<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Logger;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * WebSearchLogger Class
 *
 * Manages web search query logging and results storage.
 * Separates web_search data from internal RAG data (gpt table).
 */

class WebSearchLogger
{
    private SecurityLogger $logger;
    private bool $debug;

    public function __construct()
    {
        $this->logger = new SecurityLogger();
        $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
            && \CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    }

    /**
     * Info message — only forwarded when debug mode is on.
     */
    public function logInfo(string $message, array $context = []): void
    {
        try {
            if ($this->debug) {
                $contextStr = !empty($context) ? ' | Context: ' . \json_encode($context) : '';
                $this->logger->logSecurityEvent($message . $contextStr, 'info');
            }
        } catch (\Exception $e) {
            // Silently fail to avoid blocking execution
            \error_log('WebSearchLogger::logInfo error: ' . $e->getMessage());
        }
    }

    /**
     * Warning message — always forwarded.
     */
    public function logWarning(string $message, array $context = []): void
    {
        try {
            $contextStr = !empty($context) ? ' | Context: ' . \json_encode($context) : '';
            $this->logger->logSecurityEvent($message . $contextStr, 'warning');
        } catch (\Exception $e) {
            \error_log('WebSearchLogger::logWarning error: ' . $e->getMessage());
        }
    }

    /**
     * Error message — always forwarded.
     */
    public function logError(string $message, array $context = []): void
    {
        try {
            $contextStr = !empty($context) ? ' | Context: ' . \json_encode($context) : '';
            $this->logger->logSecurityEvent($message . $contextStr, 'error');
        } catch (\Exception $e) {
            \error_log('WebSearchLogger::logError error: ' . $e->getMessage());
        }
    }
}