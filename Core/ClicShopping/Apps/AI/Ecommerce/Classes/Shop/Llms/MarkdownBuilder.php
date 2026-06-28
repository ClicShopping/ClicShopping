<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  declare(strict_types=1);

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\Llms;

  use ClicShopping\OM\CLICSHOPPING;

  /**
   * Utility class responsible for generating Markdown-compatible
   * catalog entries used in llms.txt exports.
   *
   * It centralizes Markdown escaping, URL generation and text
   * normalization to ensure consistent output across categories
   * and products.
   */
  final class MarkdownBuilder
  {
    /**
     * Maximum length allowed for product descriptions.
     *
     * Short descriptions improve readability and keep generated
     * llms.txt files lightweight for crawlers and AI agents.
     */
    private const int DESCRIPTION_LENGTH = 180;

    /**
     * Build a Markdown entry for a category.
     *
     * Format:
     * - [Category Name](url) - Description
     */
    public function category(array $category): string
    {
      $url = CLICSHOPPING::link('index.php','cPath=' . $category['id']);
      $name = $this->escape($category['name']);
      $description = $this->escape(trim($category['description']));

      $line = "- [{$name}]({$url})";

      // Append description only when meaningful content exists.
      if ($description !== '') {
        $line .= " - {$description}";
      }

      return $line;
    }

    /**
     * Build a Markdown entry for a product.
     *
     * Product descriptions are automatically truncated to avoid
     * oversized entries in generated files.
     *
     * Format:
     * - [Product Name](url) - Description
     */
    public function product(array $product): string
    {
      $url = CLICSHOPPING::link('index.php','Products&Description&products_id=' . $product['id']);
      $name = $this->escape( $product['name']);
      $description = $this->truncate($product['description']);
      return "- [{$name}]({$url}) - {$description}";
    }

    /**
     * Build a Markdown entry for a pages manager.
     *
     * Pages manager descriptions are automatically truncated to avoid
     * oversized entries in generated files.
     *
     * Format:
     * - [Page Name](url) - Description
     */
    public function page(array $page): string
    {
      $url = CLICSHOPPING::link('index.php','Info&Content&pagesId=' . $page['id']);
      $name = $this->escape($page['name']);
      $description = $this->truncate($page['description']);

      return "- [{$name}]({$url}) - {$description}";
    }

    /**
     * Convert content into Markdown-safe text.
     *
     * HTML entities are decoded before escaping characters
     * that could interfere with Markdown link syntax.
     */
    public function escape(string $text): string
    {
      $text = html_entity_decode(
        $text,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
      );

      return str_replace(
        ['[', ']', '(', ')'],
        ['\[', '\]', '\(', '\)'],
        trim($text)
      );
    }

    /**
     * Normalize and shorten long descriptions.
     *
     * Multiple spaces and line breaks are collapsed into a
     * single space before truncation. Escaping is applied after
     * normalization to ensure Markdown validity.
     */
    private function truncate(string $text): string
    {
      $text = trim(
        preg_replace('/\s+/u', ' ', $text) ?? ''
      );

      return mb_strimwidth(
        $this->escape($text),
        0,
        self::DESCRIPTION_LENGTH,
        '...',
        'UTF-8'
      );
    }
  }