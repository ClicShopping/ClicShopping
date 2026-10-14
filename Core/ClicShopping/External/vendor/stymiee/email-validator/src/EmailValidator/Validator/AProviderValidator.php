<?php

declare(strict_types=1);

namespace EmailValidator\Validator;

/**
 * Abstract base class for validators that check against provider lists
 *
 * This abstract class provides functionality for validators that need to check
 * email addresses against lists of providers (e.g., disposable or free email providers).
 * It handles fetching and parsing provider lists from various sources and formats.
 */
abstract class AProviderValidator extends AValidator
{
    /**
     * Array of provider list sources and their formats
     *
     * @var array<array{format: string, url: string}>
     */
    protected static array $providers = [];

    /**
     * Gets and merges provider lists from various sources
     *
     * Fetches public lists of provider domains and merges them together into one array.
     * If a custom list is provided, it is merged into the new list.
     *
     * @param bool $checkLocalOnly If true, only use the provided list and skip external sources
     * @param array<string> $list Custom list of provider domains to merge with external lists
     * @return array<string> Merged and deduplicated list of provider domains
     */
    public function getList(bool $checkLocalOnly = false, array $list = []): array
    {
        $providers = [];
        if (!$checkLocalOnly) {
            foreach (static::$providers as $provider) {
                if (filter_var($provider['url'], FILTER_VALIDATE_URL)) {
                    $content = @file_get_contents($provider['url']);
                    if ($content) {
                        $providers[] = $this->getExternalList($content, $provider['format']);
                    }
                }
            }
        }
        return array_values(array_filter(array_unique(array_merge($list, ...$providers)), 'is_string'));
    }

    /**
     * Parses a provider list based on its format
     *
     * Supports JSON and plain text formats for provider lists.
     *
     * @param string $content The content of the provider list
     * @param string $type The format of the list ('json' or 'txt')
     * @return array<string> Parsed list of provider domains
     */
    protected function getExternalList(string $content, string $type): array
    {
        if (empty($content)) {
            return [];
        }

        switch ($type) {
            case 'json':
                $providers = json_decode($content, true);
                if (!is_array($providers)) {
                    return [];
                }
                break;
            case 'txt':
            default:
                $providers = array_filter(explode("\n", str_replace("\r\n", "\n", $content)), 'strlen');
                break;
        }
        return array_values(array_filter($providers, 'is_string'));
    }
}
