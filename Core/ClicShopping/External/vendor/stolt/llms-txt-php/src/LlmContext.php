<?php

declare(strict_types=1);

namespace Stolt\LlmsTxt;

use RuntimeException;

final class LlmContext
{
    /**
     * @param callable(string): string|null $fetcher
     */
    public function expand(LlmsTxt $llmsTxt, bool $includeOptional = false, ?callable $fetcher = null): string
    {
        $fetcher ??= [$this, 'fetch'];

        $xml = '<project' . $this->xmlAttributes([
            'title' => $llmsTxt->getTitle(),
            'summary' => $llmsTxt->getDescription(),
        ]) . '>' . PHP_EOL;

        $details = $llmsTxt->getDetails();

        if ($details !== '') {
            $xml .= $details . PHP_EOL;
        }

        foreach ($llmsTxt->getSections() as $section) {
            if (!$includeOptional && $section->getName() === 'Optional') {
                continue;
            }

            $tag = $this->sectionTag($section->getName());
            $xml .= '<' . $tag . '>' . PHP_EOL;

            foreach ($section->getLinks() as $link) {
                $body = $this->cleanBody($fetcher($link->getUrl()));
                $xml .= '<doc' . $this->xmlAttributes([
                    'title' => $link->getUrlTitle(),
                    'desc' => $link->getUrlDetails(),
                ]) . '>' . $body . '</doc>' . PHP_EOL;
            }

            $xml .= '</' . $tag . '>' . PHP_EOL;
        }

        return $xml . '</project>' . PHP_EOL;
    }

    /**
     * @throws RuntimeException
     */
    public function fetch(string $url): string
    {
        if (\is_file($url) && \is_readable($url)) {
            $content = \file_get_contents($url);
            if ($content !== false) {
                return $content;
            }
        }

        $content = @\file_get_contents($url);
        if ($content === false) {
            throw new RuntimeException("Unable to fetch LLM context from {$url}");
        }

        return $content;
    }

    private function sectionTag(string $name): string
    {
        $slug = \strtolower(\str_replace(' ', '-', $name));

        return \preg_replace('/[^a-z0-9_-]/', '', $slug) ?: 'section';
    }

    /**
     * @param array<string, string> $attributes
     */
    private function xmlAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            if ($value === '') {
                continue;
            }
            $parts[] = $name . '="' . $this->escapeXml($value) . '"';
        }

        return $parts === [] ? '' : ' ' . \implode(' ', $parts);
    }

    private function escapeXml(string $value): string
    {
        return \htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function cleanBody(string $txt): string
    {
        $lines = \preg_split('/\R/', $txt) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            if (\preg_match('/^ $/', $line) === 1) {
                continue;
            }
            if (\preg_match('/<img[^>]*src="data:image\/[^"]*"[^>]*>/', $line) === 1) {
                continue;
            }
            $kept[] = $line;
        }

        return \implode(PHP_EOL, $kept);
    }
}
