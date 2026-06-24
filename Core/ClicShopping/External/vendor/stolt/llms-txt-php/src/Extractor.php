<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt;

use DOMDocument;
use DOMXPath;
use Exception;
use RuntimeException;

final class Extractor
{
    /**
     * Extracts the contents of all <script type="text/llms.txt"> blocks from a given HTML file.
     *
     * @param string $filePath Path to the HTML file.
     * @param bool $parsed Whether the llms.txt should be parsed or not.
     * @throws RuntimeException|Exception If a file isn't found or unreadable.
     * @return string[] Extracted contents.
     */
    public function extractFromFile(string $filePath, bool $parsed = false): array
    {
        if (!\is_readable($filePath)) {
            throw new RuntimeException("HTML file {$filePath} not found or not readable");
        }

        $html = \file_get_contents($filePath);

        if ($html === false) {
            throw new RuntimeException("Failed to read HTML file {$filePath}");
        }

        return $this->extractFromHtml($html, $parsed);
    }

    /**
     * Extracts the contents of all <script type="text/llms.txt"> blocks from raw HTML.
     *
     * @param string $html Raw HTML string.
     * @param bool $parsed Whether the llms.txt should be parsed or not.
     * @throws Exception If the llmxt.txt parsing fails.
     * @return array Extracted contents.
     */
    public function extractFromHtml(string $html, bool $parsed = false): array
    {
        $dom = new DOMDocument();
        // Suppress warnings from malformed HTML
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//script[@type="text/llms.txt"]');

        $contents = [];
        foreach ($nodes as $node) {
            $content = \trim($node->textContent);
            if ($parsed) {
                $content = (new LlmsTxt)->parse($node->textContent);
            }
            $contents[] = $content;
        }

        return $contents;
    }
}
