<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  declare(strict_types=1);

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\Llms;

  use ClicShopping\OM\Registry;
  use ClicShopping\OM\CLICSHOPPING;
  use Stolt\LlmsTxt\LlmsTxt as LlmsTxtBuilder;
  use Stolt\LlmsTxt\Section;
  use Stolt\LlmsTxt\Section\Link;
  use Exception;
  use ClicShopping\Apps\Catalog\Categories\Classes\Shop\CategoryTree;
  use ClicShopping\Apps\Communication\PageManager\Classes\Shop\PageManagerShop;

  final class Llms
  {
    // Dependencies are injectable to simplify testing and future extensions.
    protected mixed $rewriteUrl;
    protected mixed $productsCommon;
    protected mixed $categories;
    protected mixed $pageManagerShop;

    private CategoryProvider $categoryProvider;
    private ProductProvider $productProvider;
    private PageManagerProvider $pageProvider;

    public function __construct(
      ?CategoryProvider $categoryProvider = null,
      ?ProductProvider $productProvider  = null,
      ?PageManagerProvider $pageProvider  = null
    ) {
      // Fallback to default providers when no dependency is injected.
      // This keeps backward compatibility while allowing unit testing.
      $this->categoryProvider = $categoryProvider ?? new CategoryProvider();
      $this->productProvider = $productProvider ?? new ProductProvider();
      $this->pageProvider = $pageProvider ?? new PageManagerProvider();
    }

    /**
     * Generate the lightweight llms.txt file.
     *
     * Intended for AI crawlers and LLM agents that only need
     * the most relevant categories and products.
     */
    public function generate(): string
    {
      try {
        $languageId = (int)Registry::get('Language')->getId();

        return $this->build(
          $this->categoryProvider->getCategories($languageId),
          $this->productProvider->getPopularProducts($languageId),'Popular Products',
          $this->pageProvider->getPages($languageId), 'General Condition & Confidential policies',
          false);
      } catch (Exception $e) {
        // Return a visible error instead of silently generating an empty file.
        return 'Error generating llms.txt: ' . $e->getMessage();
      }
    }

    /**
     * Generate the complete catalog version.
     *
     * Same product set as the light file but with FULL (untruncated)
     * descriptions — intended for agents requiring the complete product text.
     */
    public function generateFull(): string
    {
      try {
        $languageId = (int)Registry::get('Language')->getId();

        return $this->build(
          $this->categoryProvider->getCategories($languageId),
          $this->productProvider->getPopularProducts($languageId),'Popular Products (Full Catalog)',
          $this->pageProvider->getPages($languageId), 'General Condition & Confidential policies',
          true);
      } catch (Exception $e) {
        return 'Error generating llms-full.txt: ' . $e->getMessage();
      }
    }

    /**
     * Central builder shared by llms.txt and llms-full.txt.
     *
     * Converts catalog data into the standardized llms.txt format.
     */
    private function build(
      array $categories,
      array $products,
      string $productsLabel,
      array $pagesManager,
      string $pagesManagerLabel,
      bool $full = false,
    ): string {
      $catSection = $this->buildCategorySection($categories);
      $prodSection = $this->buildProductSection($products, $productsLabel, $full);
      $pageSection = $this->buildPageManagerSection($pagesManager, $pagesManagerLabel, $full);
      $agenticSection = $this->buildAgenticCommerceSection();

      return (new LlmsTxtBuilder())
        ->title(STORE_NAME)
        ->description('Powered by ClicShopping AI')
        ->details(
          'Type: E-commerce Store' . "\n\n" .
          'Machine-readable product catalog generated from live database.'
        )
        ->addSection($catSection)
        ->addSection($prodSection)
        ->addSection($pageSection)
        ->addSection($agenticSection)
        ->toString();
    }

    /**
     * Build the "Agentic Commerce" section.
     *
     * Advertises the machine-readable, read-only UCP product-catalog endpoint
     * (Universal Commerce Protocol) and the in-page WebMCP tools, so AI agents
     * can discover how to query the live catalog programmatically. The
     * transactional endpoints (checkout) are intentionally NOT advertised here:
     * they require a Bearer API key and are not part of the public surface.
     */
    private function buildAgenticCommerceSection(): Section
    {
      $section = (new Section())->name('Agentic Commerce (UCP & WebMCP)');

      $catalogLink = (new Link())
        ->urlTitle('Product Catalog API (UCP, read-only JSON)')
        ->url($this->agentUrl('Google&UCP&retailers/products'))
        ->urlDetails(
          'GET endpoint returning the live product catalog as JSON. '
          . 'Optional query filters: page, limit, category, min_price, max_price, in_stock. '
          . 'Universal Commerce Protocol (UCP) — read-only; checkout endpoints require a Bearer API key.'
        );

      $section->addLink($catalogLink);

      $webMcpLink = (new Link())
        ->urlTitle('WebMCP in-page tools')
        ->url($this->agentUrl(null))
        ->urlDetails(
          'Store pages expose WebMCP tool annotations (e.g. mcp-tool="searchProducts") '
          . 'so in-browser AI agents can call page actions directly.'
        );

      $section->addLink($webMcpLink);

      return $section;
    }

    /**
     * Build an absolute store URL fit for a plain-text llms.txt: CLICSHOPPING::link()
     * HTML-encodes '&' as '&amp;' for HTML contexts, which is wrong here, so the
     * entity is decoded back to a raw '&'.
     */
    private function agentUrl(?string $parameters): string
    {
      return html_entity_decode(
        CLICSHOPPING::link(null, $parameters, false, false),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
      );
    }

    /**
     * Build the category section of the llms file.
     *
     * Category descriptions are sanitized before insertion
     * to avoid malformed Markdown output.
     */
    private function buildCategorySection(array $categories): Section
    {
      $section = (new Section())->name('Categories');

      $this->rewriteUrl = Registry::get('RewriteUrl');

      // Ensure a CategoryTree instance exists before usage.
      if (!Registry::exists('CategoryTree')) {
        Registry::set('CategoryTree', new CategoryTree());
      }

      $this->categories = Registry::get('CategoryTree');

      foreach ($categories as $category) {
        $name = $this->categories->getCategoryTreeTitle($category['name']);
        $url = $this->categories->getCategoryTreeUrl($category['id']);

        // Remove HTML and escape Markdown-sensitive characters.
        $desc = $this->cleanText($category['description'] ?? '');

        $link = (new Link())
          ->urlTitle($name)
          ->url($url);

        if ($desc !== '') {
          $link->urlDetails($desc);
        }

        $section->addLink($link);
      }

      return $section;
    }

    /**
     * Build the products section.
     *
     * Light mode truncates descriptions to keep llms.txt compact; full mode
     * (llms-full.txt) keeps the complete product description.
     */
    private function buildProductSection(array $products, string $label, bool $full = false): Section
    {
      $this->rewriteUrl = Registry::get('RewriteUrl');
      $this->productsCommon = Registry::get('ProductsCommon');

      $section = (new Section())->name($label);

      foreach ($products as $product) {
        $url = $this->rewriteUrl->getProductNameUrl($product['id']);
        $name = $this->productsCommon->getProductsName($product['id']);

        // Light: shorten to keep entries compact. Full: keep the whole description.
        $desc = $this->cleanText(
          $this->productsCommon->getProductsDescription($product['id'])
        );

        if (!$full) {
          $desc = $this->truncate($desc);
        }

        $link = (new Link())
          ->urlTitle($name)
          ->url($url);

        if ($desc !== '') {
          $link->urlDetails($desc);
        }

        $section->addLink($link);
      }

      return $section;
    }

    /**
     * Build the Pages Manager section.
     *
     * Pages Manager descriptions are truncated to keep llms.txt
     * compact and crawler-friendly.
     */
    private function buildPageManagerSection(array $pagesManager, string $label, bool $full = false): Section
    {
      $this->rewriteUrl = Registry::get('RewriteUrl');

      if (!Registry::exists('PageManagerShop')) {
        Registry::set('PageManagerShop',new PageManagerShop());
      }

      $this->pageManagerShop = Registry::get('PageManagerShop');

      $section = (new Section())->name($label);

      foreach ($pagesManager as $page) {
        $url = $this->rewriteUrl->getPageManagerContentUrl($page['id']);
        $name = $page['name'];

        // Light: shorten. Full: keep the complete page text.
        $desc = $this->cleanText($page['description']);

        if (!$full) {
          $desc = $this->truncate($desc);
        }

        $link = (new Link())
          ->urlTitle($name)
          ->url($url);

        if ($desc !== '') {
          $link->urlDetails($desc);
        }

        $section->addLink($link);
      }

      return $section;
    }


    /**
     * Convert HTML content into plain text safe for Markdown output.
     *
     * Escapes characters that could be interpreted as Markdown links.
     */
    private function cleanText(string $text): string
    {
      $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $text = strip_tags($text);
      $text = trim($text);

      return str_replace(
        ['[', ']', '(', ')'],
        ['\[', '\]', '\(', '\)'],
        $text
      );
    }

    /**
     * Reduce text length while preserving UTF-8 characters.
     *
     * Prevents excessively long descriptions in generated files.
     */
    private function truncate(string $text, int $maxLength = 180): string
    {
      $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

      return mb_strimwidth(
        $text,
        0,
        $maxLength,
        '…',
        'UTF-8'
      );
    }
  }