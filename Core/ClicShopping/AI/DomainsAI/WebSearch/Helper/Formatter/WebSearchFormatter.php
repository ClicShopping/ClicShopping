<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Helper\Formatter;



use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\LlmGuardrails;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters\AbstractFormatter;
use ClicShopping\AI\RegistryAI\WebSearchEngineRegistry;

/**
 * WebSearchFormatter - Formats web search query results
 * 
 * Handles formatting of external web search results including:
 * - External URLs with clickable links
 * - Price comparisons (internal vs external)
 * - Comparative tables
 * - Source attribution with web search icon
 */

class WebSearchFormatter extends AbstractFormatter
{
  /**
   * @var \ClicShopping\OM\Language Language instance for translations
   */
  private $language;
  
  /**
   * @var string Current language code
   */
  private string $languageCode;
  
  /**
   * Constructor
   * 
   * @param bool $debug Enable debug mode
   * @param bool $displaySql Display SQL queries
   */
  public function __construct(bool $debug = false, bool $displaySql = false)
  {
    parent::__construct($debug, $displaySql);
    
    // Initialize language
    $this->language = Registry::get('Language');
    $this->languageCode = $this->language->get('code');
  }
  
  /**
   * Check if this formatter can handle the given results
   * 
   * @param array $results Results to check
   * @return bool True if results are web_search type
   */
  public function canHandle(array $results): bool
  {
    $type = $results['type'] ?? '';
    return $type === 'web_search' || $type === 'web_search_results' || $type === 'web_search_response';
  }

  /**
   * Format web search results for display
   * 
   * @param array $results Web search results to format
   * @return array Formatted results with HTML content
   */
  public function format(array $results): array
  {
    // Load language definitions
    DomainConfig::loadLanguageFile('rag_web_search_formatter');
      
    $question = $results['question'] ?? $results['query'] ?? 'Unknown request';

    if ($this->debug) {
      error_log('[WebSearchFormatter] Formatting web search results\n');
      error_log('[WebSearchFormatter] Result keys: ' . implode(', ', array_keys($results)) . "\n");
      error_log('[WebSearchFormatter] Has text_response: ' . (isset($results['text_response']) ? 'YES' : 'NO') . "\n");

      if (isset($results['text_response'])) {
        $isHtml = (strpos($results['text_response'], '<') !== false);
        error_log('[WebSearchFormatter] text_response is HTML: ' . ($isHtml ? 'YES' : 'NO') . "\n");
        error_log('[WebSearchFormatter] text_response length: ' . strlen($results['text_response']) . "\n");
      }
    }

    $output = "<div class='web-search-results'>";
    $output .= "<h4>" . $this->language->getDef('text_rag_web_search_results_for') . " " . htmlspecialchars($question) . "</h4>";
    if (!empty($results['market_analysis']) && is_string($results['market_analysis'])) {
      $output .= $results['market_analysis'];
    }

    // Display mode indicator at top (if metadata available)
    if (isset($results['metadata']) && is_array($results['metadata'])) {
      $output .= $this->formatModeIndicator($results['metadata']);

      // Display user notification from Mode C fallback (e.g. scraping found 0 results)
      if (!empty($results['metadata']['user_notification'])) {
        $notification = $results['metadata']['user_notification'];
        $alertClass = ($notification['type'] ?? 'warning') === 'warning' ? 'alert-warning' : 'alert-info';
        $output .= "<div class='alert {$alertClass} mt-2' role='alert'>"
          . "⚠️ " . htmlspecialchars($notification['message'] ?? '')
          . "</div>";
      }
    }

    // Display Google Trends chart if present (Mode E)
    if (!empty($results['trends_data'])) {
      $output .= $this->formatTrendsChart($results['trends_data']);
    }

    // Display AI Overview first if present
    if (isset($results['ai_overview']) && !empty($results['ai_overview'])) {
      $output .= $this->formatAIOverview($results['ai_overview']);
    }

    // Display shopping results before organic results (if present)
    if (isset($results['shopping_results']) && is_array($results['shopping_results']) && !empty($results['shopping_results'])) {
      if ($this->debug) {
        error_log("[WebSearchFormatter::format] Calling formatShoppingResults() with " . count($results['shopping_results']) . " items");
      }


      $output .= $this->formatShoppingResults($results['shopping_results']);



    } else {
      if ($this->debug) {
        error_log("[WebSearchFormatter::format] NO shopping_results to format");
      }
    }

    // Display source attribution
    if (isset($results['source_attribution'])) {
      $output .= $this->formatSourceAttribution($results['source_attribution']);
    }

    // Display interpretation/summary
    $interpretationText = '';
    $isHtmlContent = false;
    
    if (isset($results['text_response']) && !empty($results['text_response'])) {
      $interpretationText = $results['text_response'];
      // Check if text_response contains HTML (from ResultSynthesizer)
      $isHtmlContent = (strpos($interpretationText, '<div') !== false || strpos($interpretationText, '<p>') !== false);
    } elseif (isset($results['interpretation']) && $results['interpretation'] !== 'Array') {
      $interpretationText = $results['interpretation'];
    } elseif (isset($results['response']) && !empty($results['response'])) {
      $interpretationText = $results['response'];
    }

    if (!empty($interpretationText)) {
      if ($isHtmlContent) {
        // text_response already contains formatted HTML - use as-is
        $output .= "<div class='interpretation'>" . $interpretationText . "</div>";
      } else {
        // Plain text - apply HTML encoding
        $output .= "<div class='interpretation'><strong>" . $this->language->getDef('text_rag_web_search_summary') . "</strong> " 
                . Hash::displayDecryptedDataText($interpretationText) . "</div>";
      }
    }

    // Guardrails
    $output .= "<div class='mt-2'></div>";

    $lmGuardrails = LlmGuardrails::checkGuardrails($question, Hash::displayDecryptedDataText($interpretationText));

    if (is_array($lmGuardrails)) {
      $output .= $this->formatGuardrailsMetrics($lmGuardrails);
    } else {
      $output .= "<div class='alert alert-warning'>" . htmlspecialchars($lmGuardrails) . "</div>";
    }

    $output .= "<div class='mt-2'></div>";

    // Display enhanced price comparison if available (with shopping data source indicators)
    if (isset($results['price_comparison']) && is_array($results['price_comparison'])) {
      $output .= $this->formatPriceComparisonEnhanced($results['price_comparison']);
    }

    // Display web search results with URLs
    if (isset($results['web_results']) && is_array($results['web_results']) && !empty($results['web_results'])) {
      $output .= $this->formatWebResults($results['web_results']);
    } elseif (isset($results['results']) && is_array($results['results']) && !empty($results['results'])) {
      $output .= $this->formatWebResults($results['results']);
    }

    // Display external sources/URLs
    if (isset($results['sources']) && is_array($results['sources']) && !empty($results['sources'])) {
      $output .= $this->formatExternalSources($results['sources']);
    } elseif (isset($results['urls']) && is_array($results['urls']) && !empty($results['urls'])) {
      $output .= $this->formatExternalUrls($results['urls']);
    }

    // Display comparative table if available
    if (isset($results['comparison_table']) && is_array($results['comparison_table'])) {
      $output .= $this->formatComparisonTable($results['comparison_table']);
    }

    $output .= "</div>";

    // Save audit data
    $auditExtra = [
      'web_results' => $results['web_results'] ?? [],
      'sources' => $results['sources'] ?? [],
      'price_comparison' => $results['price_comparison'] ?? [],
      'processing_chain' => $results['processing_chain'] ?? [],
      'ai_overview' => $results['ai_overview'] ?? []
    ];

    Gpt::saveData($question, $output, $auditExtra);

    // CRITICAL DEBUG: Log output status before return
    if ($this->debug) {
      error_log("[WebSearchFormatter::format] output is empty: " . (empty($output) ? 'YES' : 'NO'));
      if (!empty($output)) {
        error_log("[WebSearchFormatter::format] output length: " . strlen($output));
        error_log("[WebSearchFormatter::format] output first 100 chars: " . substr($output, 0, 100));
      }
    }

    return [
      'type' => 'formatted_results',
      'content' => $output
    ];
  }

  /**
   * Format AI Overview section
   * 
   * Displays Google AI Overview with summary and sources
   * 
   * @param array $aiOverview AI Overview data
   * @return string Formatted HTML
   */
  private function formatAIOverview(array $aiOverview): string
  {
    $output = "<div class='ai-overview alert alert-primary' style='border-left: 4px solid #007bff; margin-bottom: 20px;'>";
    $output .= "<h5>🤖 " . $this->language->getDef('text_rag_ai_overview') . "</h5>";

    // Display full summary
    if (!empty($aiOverview['full_summary'])) {
      $output .= "<div class='ai-summary' style='margin: 10px 0;'>";
      $output .= nl2br(htmlspecialchars($aiOverview['full_summary']));
      $output .= "</div>";
    }

    // Display sources
    if (!empty($aiOverview['sources'])) {
      $output .= "<div class='ai-sources' style='margin-top: 10px;'>";
      $output .= "<strong>" . $this->language->getDef('text_rag_ai_sources') . "</strong>";
      $output .= "<ul>";
      foreach ($aiOverview['sources'] as $source) {
        $title = is_array($source) ? ($source['title'] ?? 'Source') : $source;
        $url = is_array($source) ? ($source['url'] ?? '') : '';

        $output .= "<li>";
        if (!empty($url)) {
          $output .= "<a href='" . htmlspecialchars($url) . "' target='_blank' rel='noopener noreferrer'>";
          $output .= htmlspecialchars($title) . " 🔗</a>";
        } else {
          $output .= htmlspecialchars($title);
        }
        $output .= "</li>";
      }
      $output .= "</ul>";
      $output .= "</div>";
    }

    $output .= "</div>";
    return $output;
  }

  /**
   * Format price comparison data
   * 
   * @param array $priceComparison Price comparison data
   * @return string Formatted HTML
   */
  private function formatPriceComparison(array $priceComparison): string
  {
    $output = "<div class='price-comparison alert alert-info'>";
    $output .= "<h5>💰 " . $this->language->getDef('text_rag_web_search_price_comparison') . "</h5>";

    // Internal price
    if (isset($priceComparison['internal_price'])) {
      $internalPrice = $priceComparison['internal_price'];
      $currency = $priceComparison['currency'] ?? '€';
      $output .= "<div class='internal-price'>";
      $output .= "<strong>" . $this->language->getDef('text_rag_web_search_our_price') . "</strong> " . number_format((float)$internalPrice, 2, ',', ' ') . " {$currency}";
      $output .= "</div>";
    }

    // External prices
    if (isset($priceComparison['external_prices']) && is_array($priceComparison['external_prices'])) {
      $output .= "<div class='external-prices' style='margin-top: 10px;'>";
      $output .= "<strong>" . $this->language->getDef('text_rag_web_search_competitor_prices') . "</strong>";
      $output .= "<ul>";
      
      foreach ($priceComparison['external_prices'] as $competitor) {
        $name = $competitor['name'] ?? 'Unknown';
        $price = $competitor['price'] ?? 0;
        $url = $competitor['url'] ?? '';
        $currency = $competitor['currency'] ?? '€';
        
        $output .= "<li>";
        $output .= htmlspecialchars($name) . " : " . number_format((float)$price, 2, ',', ' ') . " {$currency}";
        
        if (!empty($url)) {
          $output .= " <a href='" . htmlspecialchars($url) . "' target='_blank' rel='noopener noreferrer'>🔗 " . $this->language->getDef('text_rag_web_search_see') . "</a>";
        }
        
        // Show percentage difference if internal price exists
        if (isset($priceComparison['internal_price'])) {
          $diff = (($price - $priceComparison['internal_price']) / $priceComparison['internal_price']) * 100;
          $diffFormatted = number_format($diff, 1);
          
          if ($diff > 0) {
            $output .= " <span class='text-success'>(+{$diffFormatted}%)</span>";
          } elseif ($diff < 0) {
            $output .= " <span class='text-danger'>({$diffFormatted}%)</span>";
          }
        }
        
        $output .= "</li>";
      }
      
      $output .= "</ul>";
      $output .= "</div>";
    }

    // Recommendation
    if (isset($priceComparison['recommendation'])) {
      $output .= "<div class='recommendation' style='margin-top: 10px; padding: 8px; background-color: #f8f9fa; border-radius: 4px;'>";
      $output .= "<strong>💡 " . $this->language->getDef('text_rag_web_search_recommendation') . "</strong> " . htmlspecialchars($priceComparison['recommendation']);
      $output .= "</div>";
    }

    $output .= "</div>";

    return $output;
  }

  /**
   * Format web search results with snippets and URLs
   * 
   * @param array $webResults Web search results
   * @return string Formatted HTML
   */
  private function formatWebResults(array $webResults): string
  {
    $output = "<div class='web-results'>";
    $output .= "<h5>🌐 " . $this->language->getDef('text_rag_web_search_external_results') . "</h5>";

    foreach ($webResults as $index => $result) {
      $title = $result['title'] ?? $this->language->getDef('text_rag_web_search_result') . " " . ($index + 1);
      $snippet = $result['snippet'] ?? $result['description'] ?? '';
      $url = $result['url'] ?? $result['link'] ?? '';
      
      $output .= "<div class='web-result-item' style='margin-bottom: 15px; padding: 10px; border-left: 3px solid #17a2b8; background-color: #f8f9fa;'>";
      
      // Title with link
      if (!empty($url)) {
        $output .= "<div class='result-title'>";
        $output .= "<a href='" . htmlspecialchars($url) . "' target='_blank' rel='noopener noreferrer' style='font-weight: bold; color: #007bff;'>";
        $output .= htmlspecialchars($title);
        $output .= " 🔗</a>";
        $output .= "</div>";
      } else {
        $output .= "<div class='result-title' style='font-weight: bold;'>" . htmlspecialchars($title) . "</div>";
      }
      
      // Snippet
      if (!empty($snippet)) {
        $output .= "<div class='result-snippet' style='margin-top: 5px; color: #666;'>";
        $output .= htmlspecialchars($snippet);
        $output .= "</div>";
      }
      
      // URL display
      if (!empty($url)) {
        $output .= "<div class='result-url' style='margin-top: 5px; font-size: 0.85em; color: #28a745;'>";
        $output .= htmlspecialchars($url);
        $output .= "</div>";
      }
      
      $output .= "</div>";
    }

    $output .= "</div>";

    return $output;
  }

  /**
   * Format external sources list
   * 
   * @param array $sources External sources
   * @return string Formatted HTML
   */
  private function formatExternalSources(array $sources): string
  {
    $output = "<div class='external-sources' style='margin-top: 15px;'>";
    $output .= "<h6>📚 " . $this->language->getDef('text_rag_web_search_external_sources') . "</h6>";
    $output .= "<ul>";

    foreach ($sources as $source) {
      if (is_string($source)) {
        $output .= "<li>" . htmlspecialchars($source) . "</li>";
      } elseif (is_array($source)) {
        $name = $source['name'] ?? $source['title'] ?? 'Source';
        $url = $source['url'] ?? $source['link'] ?? '';
        
        $output .= "<li>";
        if (!empty($url)) {
          $output .= "<a href='" . htmlspecialchars($url) . "' target='_blank' rel='noopener noreferrer'>";
          $output .= htmlspecialchars($name) . " 🔗</a>";
        } else {
          $output .= htmlspecialchars($name);
        }
        $output .= "</li>";
      }
    }

    $output .= "</ul>";
    $output .= "</div>";

    return $output;
  }

  /**
   * Format external URLs list
   * 
   * @param array $urls External URLs
   * @return string Formatted HTML
   */
  private function formatExternalUrls(array $urls): string
  {
    $output = "<div class='external-urls' style='margin-top: 15px;'>";
    $output .= "<h6>🔗 " . $this->language->getDef('text_rag_web_search_external_links') . "</h6>";
    $output .= "<ul>";

    foreach ($urls as $url) {
      if (is_string($url)) {
        $output .= "<li><a href='" . htmlspecialchars($url) . "' target='_blank' rel='noopener noreferrer'>";
        $output .= htmlspecialchars($url) . " 🔗</a></li>";
      }
    }

    $output .= "</ul>";
    $output .= "</div>";

    return $output;
  }

  /**
   * Format comparison table
   * 
   * @param array $comparisonTable Comparison table data
   * @return string Formatted HTML
   */
  private function formatComparisonTable(array $comparisonTable): string
  {
    if (empty($comparisonTable)) {
      return '';
    }

    $output = "<div class='comparison-table' style='margin-top: 15px;'>";
    $output .= "<h5>📊 " . $this->language->getDef('text_rag_web_search_comparison_table') . "</h5>";
    
    // Use inherited method from AbstractFormatter
    $output .= $this->generateTable($comparisonTable, 'table table-bordered table-striped\n');
    
    $output .= "</div>";

    return $output;
  }

  /**
   * Format guardrails metrics
   * 
   * @param array $guardrails Guardrails data
   * @return string Formatted HTML
   */
  private function formatGuardrailsMetrics(array $guardrails): string
  {
    $output = "<div class='guardrails-metrics'>";
    // Add guardrails display logic here
    // This can be expanded based on the actual guardrails structure
    $output .= "</div>";
    return $output;
  }

  /**
   * Format mode indicator badges
   * 
   * Displays badges showing which search modes were used (Mode A, B, C)
   * with execution time for each mode when available.
   * 
   * @param array $metadata Result metadata containing engines_used and execution times
   * @return string Formatted HTML with mode badges
   */
  public function formatModeIndicator(array $metadata): string
  {
    if (empty($metadata['engines_used']) && empty($metadata['mode_type'])) {
      return '';
    }

    $output = "<div class='mode-indicator alert alert-light' style='border: 1px solid #dee2e6; margin-bottom: 20px; padding: 15px;'>";
    
    // Determine which modes were used
    $modesUsed = [];
    $isHybrid = false;
    
    if (isset($metadata['engines_used']) && is_array($metadata['engines_used'])) {
      $modesUsed = $metadata['engines_used'];
      $isHybrid = count($modesUsed) > 1;
    } elseif (isset($metadata['mode_type'])) {
      $modesUsed = [$metadata['mode_type']];
      $isHybrid = str_contains($metadata['mode_type'], 'hybrid');
    }

    // Display hybrid label if applicable
    if ($isHybrid) {
      $output .= "<strong>" . $this->language->getDef('text_rag_hybrid_search') . "</strong><br>";
    }

    // Display mode badges
    $badges = [];
    $registry = WebSearchEngineRegistry::getInstance();

    foreach ($modesUsed as $mode) {
      $badge = '';

      if (str_contains($mode, 'mode_a') || str_contains($mode, 'ai_overview')) {
        $badge = "🤖 " . $this->language->getDef('text_rag_mode_ai_overview');
      } elseif (str_contains($mode, 'mode_b') || str_contains($mode, 'google_shopping')) {
        $badge = "🛒 " . $this->language->getDef('text_rag_mode_shopping');
      } elseif (str_contains($mode, 'mode_c') || str_contains($mode, 'rag')) {
        $badge = "🔍 " . $this->language->getDef('text_rag_mode_rag_scraping');
      } elseif (str_contains($mode, 'mode_e') || str_contains($mode, 'google_trends')) {
        $badge = "📈 " . $this->language->getDef('text_rag_mode_google_trends');
      } else {
        // Domain-registered mode — get its label from the provider
        $provider = $registry->getProvider($mode);
        if ($provider !== null) {
          $badge = "🛒 " . $provider->getDisplayName();
        }
      }

      if (!empty($badge)) {
        $badges[] = "<span class='badge badge-primary' style='margin-right: 10px; padding: 8px 12px; font-size: 0.9em;'>{$badge}</span>";
      }
    }

    $output .= implode(' ', $badges);

    // Display execution times if available
    if (isset($metadata['engines']) && is_array($metadata['engines'])) {
      $totalTime = 0;
      $output .= "<div style='margin-top: 10px; font-size: 0.85em; color: #666;'>";
      
      foreach ($metadata['engines'] as $engine) {
        if (isset($engine['execution_time'])) {
          $totalTime += $engine['execution_time'];
        }
      }
      
      if ($totalTime > 0) {
        $output .= "⏱️ " . $this->language->getDef('text_rag_execution_time') . ": " . number_format($totalTime, 2) . "s";
      }
      
      $output .= "</div>";
    }

    $output .= "</div>";
    
    return $output;
  }

  /**
   * Format shopping results as product cards
   * 
   * Displays shopping results in a responsive grid layout with product cards
   * showing thumbnail, title, price, old price (strikethrough), merchant, and link.
   * 
   * @param array $shoppingResults Shopping results array
   * @return string Formatted HTML with product cards
   */
  public function formatShoppingResults(array $shoppingResults): string
  {
    if (empty($shoppingResults)) {
      if ($this->debug) {
        error_log("[WebSearchFormatter::formatShoppingResults] Shopping results array is EMPTY");
      }
      return '';
    }

    if ($this->debug) {
      error_log("[WebSearchFormatter::formatShoppingResults] Formatting " . count($shoppingResults) . " shopping results");
      // CRITICAL DEBUG: Log first item structure
      if (!empty($shoppingResults[0])) {
        error_log("[WebSearchFormatter::formatShoppingResults] First item keys: " . implode(', ', array_keys($shoppingResults[0])));
        error_log("[WebSearchFormatter::formatShoppingResults] First item data: " . json_encode($shoppingResults[0]));
      }
    }

    $count = count($shoppingResults);

    // Detect source(s) from data_source field
    $dataSources = array_unique(array_filter(array_column($shoppingResults, 'data_source')));
    $sourceLabel = '';
    if (!empty($dataSources)) {
      $registry = WebSearchEngineRegistry::getInstance();
      $labels = [];

      foreach ($dataSources as $source) {
        $provider = $registry->findProviderByEngineName((string) $source);
        if ($provider !== null) {
          $labels[] = $provider->getDisplayName();
          continue;
        }

        // Last-resort fallback: title-case the raw source name
        $labels[] = ucfirst(str_replace('_', ' ', (string) $source));
      }

      if (!empty($labels)) {
        $sourceLabel = ' — ' . implode(' & ', $labels);
      }
    }

    $output = "<div class='shopping-results' style='margin: 20px 0;'>";
    $output .= '<h5 class="text-primary">🛒 ' . $this->language->getDef('text_rag_shopping_results') . ' (' . $count . ' ' . $this->language->getDef('text_rag_shopping_results_count') . ')' . $sourceLabel . '</h5>';
    
    $hasRagResults = false;
    foreach ($shoppingResults as $r) {
      if (($r['data_source'] ?? '') === 'rag_websearch') {
        $hasRagResults = true;
        break;
      }
    }
    if ($hasRagResults) {
      $output .= "<div class='alert alert-warning' style='background:#fff3cd; border:1px solid #ffeeba; "
        . "color:#856404; border-radius:6px; padding:8px 12px; margin:8px 0; font-size:0.85em;'>"
        . "ℹ️ " . htmlspecialchars($this->language->getDef('text_rag_site_search_notice'))
        . "</div>";
    }

    // Responsive grid layout
    $output .= "<div class='row' style='display: flex; flex-wrap: wrap; margin: 0 -10px;'>";
    
    foreach ($shoppingResults as $result) {
      $title = $result['title'] ?? '';
      $price = $result['price'] ?? '';
      $extractedPrice = $result['extracted_price'] ?? null;
      $oldPrice = $result['old_price'] ?? '';
      $extractedOldPrice = $result['extracted_old_price'] ?? null;
      $source = $result['source'] ?? '';
      $productLink = $result['link'] ?? $result['product_link'] ?? ''; // Try 'link' first, then 'product_link'
      $productLink = '';
      foreach (['link', 'product_link'] as $linkKey) {
        if (!empty($result[$linkKey]) && is_string($result[$linkKey])) {
          $productLink = $result[$linkKey];
          break;
        }
      }
      if ($productLink === '' && !empty($title)) {
        $productLink = 'https://www.google.com/search?tbm=shop&q=' . urlencode($title);
      }

      $thumbnail = $result['thumbnail'] ?? '';
      $rating = $result['rating'] ?? null; // Amazon rating
      $reviews = $result['reviews'] ?? null; // Amazon reviews count
      
      // CRITICAL DEBUG: Log what we extracted
      if ($this->debug) {
        error_log("[WebSearchFormatter::formatShoppingResults] Item: title={$title}, price={$price}, link={$productLink}, source={$source}, thumbnail=" . (!empty($thumbnail) ? 'YES' : 'NO') . ", rating={$rating}, reviews={$reviews}");
      }
      
      // Product card (responsive: col-md-4 = 3 columns desktop, col-sm-6 = 2 columns tablet, col-12 = 1 column mobile)
      $output .= "<div class='col-12 col-sm-6 col-md-4' style='padding: 10px;'>";
      $output .= "<div class='product-card' style='border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; height: 100%; display: flex; flex-direction: column; background-color: #fff;'>";
      
      // Thumbnail
      if (!empty($thumbnail)) {
        $output .= "<div class='product-thumbnail' style='text-align: center; margin-bottom: 10px;'>";
        $output .= "<img src='" . htmlspecialchars($thumbnail) . "' alt='" . htmlspecialchars($title) . "' style='max-width: 100%; max-height: 150px; object-fit: contain;' />";
        $output .= "</div>";
      }
      
      // Title
      $output .= "<div class='product-title' style='font-weight: bold; margin-bottom: 10px; flex-grow: 1;'>";
      $output .= htmlspecialchars($title);
      $output .= "</div>";
      
      // Rating and Reviews (Amazon specific)
      if ($rating !== null || $reviews !== null) {
        $output .= "<div class='product-rating' style='margin-bottom: 10px; font-size: 0.9em;'>";
        
        if ($rating !== null) {
          // Display stars
          $fullStars = floor($rating);
          $halfStar = ($rating - $fullStars) >= 0.5;
          $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
          
          $output .= "<span style='color: #ffa500;'>"; // Orange color for stars
          for ($i = 0; $i < $fullStars; $i++) {
            $output .= "★";
          }
          if ($halfStar) {
            $output .= "⯨"; // Half star
          }
          for ($i = 0; $i < $emptyStars; $i++) {
            $output .= "☆";
          }
          $output .= "</span> ";
          $output .= "<span style='color: #666;'>" . number_format($rating, 1) . "</span>";
        }
        
        if ($reviews !== null) {
          $output .= " <span style='color: #999;'>(" . number_format($reviews) . " avis)</span>";
        }
        
        $output .= "</div>";
      }
      
      // Price section
      $output .= "<div class='product-price' style='margin-bottom: 10px;'>";
      
      // Current price (prominent)
      if (!empty($price)) {
        $output .= "<div style='font-size: 1.3em; font-weight: bold; color: #28a745;'>";
        $output .= htmlspecialchars($price);
        $output .= "</div>";
      }
      
      // Old price (strikethrough)
      if (!empty($oldPrice) && $oldPrice !== $price) {
        $output .= "<div style='font-size: 0.9em; color: #999; text-decoration: line-through;'>";
        $output .= htmlspecialchars($oldPrice);
        $output .= "</div>";
      }
      
      $output .= "</div>";
      
      // Merchant/Source
      if (!empty($source)) {
        $output .= "<div class='product-source' style='font-size: 0.85em; color: #666; margin-bottom: 10px;'>";
        $output .= "📦 " . htmlspecialchars($source);
        $output .= "</div>";
      }
      
      // View Product link
      if (!empty($productLink)) {
        $output .= "<div class='product-link'>";
        $output .= "<a href='" . htmlspecialchars($productLink) . "' target='_blank' rel='noopener noreferrer' class='btn btn-sm btn-primary' style='width: 100%; text-align: center;'>";
        $output .= $this->language->getDef('text_rag_view_product') . " 🔗";
        $output .= "</a>";
        $output .= "</div>";
      }
      
      $output .= "</div>"; // .product-card
      $output .= "</div>"; // .col
    }
    
    $output .= "</div>"; // .row
    $output .= "</div>"; // .shopping-results
    
    return $output;
  }

  /**
   * Format Google Trends data as a Chart.js line chart (Mode E)
   *
   * Renders an interest-over-time line chart using the timeline data returned by
   * GoogleTrendsEngine. Each point is a {date, value} pair (0–100 relative scale).
   * Chart.js must already be loaded on the page (via HeaderOutputChart hook).
   *
   * @param array $trendsData Trends data from GoogleTrendsEngine::search()
   * @return string Formatted HTML with canvas + inline Chart.js initialisation script
   */
  public function formatTrendsChart(array $trendsData): string
  {
    if (empty($trendsData['timeline'])) {
      return '';
    }

    $keyword    = $trendsData['keyword']    ?? '';
    $dateRange  = $trendsData['date_range'] ?? 'today 12-m';
    $timeline   = $trendsData['timeline'];
    $pointCount = count($timeline);

    $values = array_map(fn($p) => (int)($p['value'] ?? 0), $timeline);
    $labels = array_map(fn($p) => $p['date'] ?? '', $timeline);

    // Dimensions
    $svgW   = 800; $svgH = 300;
    $padL   = 45;  $padR = 20; $padT = 20; $padB = 55;
    $chartW = $svgW - $padL - $padR;
    $chartH = $svgH - $padT - $padB;
    $n      = count($values);

    // Points (x,y)
    $pts = [];
    for ($i = 0; $i < $n; $i++) {
      $x = $padL + ($n > 1 ? ($i / ($n - 1)) * $chartW : $chartW / 2);
      $y = $padT + $chartH - ($values[$i] / 100) * $chartH;
      $pts[] = [round($x, 1), round($y, 1)];
    }

    // Polyline string
    $polyStr = implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $pts));

    // Area fill (ferme vers le bas)
    $areaStr = $padL . ',' . ($padT + $chartH) . ' '
      . $polyStr . ' '
      . ($padL + $chartW) . ',' . ($padT + $chartH);

    // SVG
    $svg  = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$svgW} {$svgH}' style='width:100%;height:300px;display:block;'>";

    // Dégradé
    $svg .= "<defs><linearGradient id='tg' x1='0' y1='0' x2='0' y2='1'>";
    $svg .= "<stop offset='0%' stop-color='#36a2eb' stop-opacity='0.3'/>";
    $svg .= "<stop offset='100%' stop-color='#36a2eb' stop-opacity='0'/>";
    $svg .= "</linearGradient></defs>";

    // Fond
    $svg .= "<rect width='{$svgW}' height='{$svgH}' fill='white' rx='6'/>";

    // Grille + labels Y
    foreach ([0, 25, 50, 75, 100] as $tick) {
      $y = $padT + $chartH - ($tick / 100) * $chartH;
      $svg .= "<line x1='{$padL}' y1='{$y}' x2='" . ($padL + $chartW) . "' y2='{$y}' stroke='#eeeeee' stroke-width='1'/>";
      $svg .= "<text x='" . ($padL - 6) . "' y='{$y}' text-anchor='end' dominant-baseline='middle' font-size='11' fill='#999'>{$tick}</text>";
    }

    // Axe X
    $baseY = $padT + $chartH;
    $svg .= "<line x1='{$padL}' y1='{$baseY}' x2='" . ($padL + $chartW) . "' y2='{$baseY}' stroke='#cccccc' stroke-width='1'/>";

    // Aire
    $svg .= "<polygon points='{$areaStr}' fill='url(#tg)'/>";

    // Ligne
    $svg .= "<polyline points='{$polyStr}' fill='none' stroke='#36a2eb' stroke-width='2.5' stroke-linejoin='round' stroke-linecap='round'/>";

    // Labels X (~8 répartis)
    $step = max(1, (int)round($n / 8));
    for ($i = 0; $i < $n; $i += $step) {
      $x     = $pts[$i][0];
      $yLbl  = $baseY + 14;
      // Raccourcir le label : "Sep 7 – 13, 2025" → "Sep 7"
      $short = preg_replace('/\s*[\-–].*/', '', $labels[$i]);
      $short = htmlspecialchars(substr($short, 0, 8));
      $svg .= "<text x='{$x}' y='{$yLbl}' text-anchor='end' font-size='10' fill='#999' transform='rotate(-35 {$x} {$yLbl})'>{$short}</text>";
    }

    // Marqueur + label sur le pic
    $maxVal = max($values);
    $maxIdx = array_search($maxVal, $values);
    $px = $pts[$maxIdx][0];
    $py = $pts[$maxIdx][1];
    $svg .= "<circle cx='{$px}' cy='{$py}' r='5' fill='#36a2eb' stroke='white' stroke-width='2'/>";
    $labelY = max($padT + 16, $py - 10);
    $svg .= "<text x='{$px}' y='{$labelY}' text-anchor='middle' font-size='12' fill='#36a2eb' font-weight='bold'>{$maxVal}</text>";

    // Points sur la courbe (légers)
    foreach ($pts as $p) {
      $svg .= "<circle cx='{$p[0]}' cy='{$p[1]}' r='2' fill='#36a2eb' opacity='0.6'/>";
    }

    $svg .= "</svg>";

    $output  = "<div class='trends-chart-container' style='margin:20px 0; border:1px solid #e9ecef; border-radius:8px; padding:15px; background:#fff;'>";
    $titleLabel = $this->language->getDef('text_rag_trends_title');
    if (empty($titleLabel)) {
        $titleLabel = 'Interest over time';
    }
    $output .= "<h5 style='margin:0 0 4px 0; color:#212529; font-size:1.05em;'>📈 "
        . htmlspecialchars($titleLabel) . " : "
        . "<span style='color:#36a2eb;'>" . htmlspecialchars($keyword) . "</span></h5>";

    $disclaimer = $this->language->getDef('text_rag_trends_disclaimer');
    if (empty($disclaimer)) {
        $disclaimer = 'Google Trends shows the relative search interest for a keyword over time (0 = no data, 100 = peak). It is a proxy for popularity, NOT the actual product price. For real price comparisons across competitors, use Google Shopping / Amazon results above.';
    }
    $output .= "<div class='alert alert-info' style='font-size:0.85em; color:#0c5460; background:#d1ecf1; border:1px solid #bee5eb; border-radius:4px; padding:8px 12px; margin:8px 0;'>"
        . "ℹ️ " . htmlspecialchars($disclaimer)
        . "</div>";

    $output .= "<p style='font-size:0.85em; color:#666; margin-bottom:12px;'>{$pointCount} points — " . htmlspecialchars($dateRange) . "</p>";
    $output .= $svg;
    $output .= "</div>";

    return $output;
  }

  /**
   * Enhanced price comparison with data source badges and thumbnails
   * 
   * Displays price comparison with data source indicators (Shopping Data vs Web Extraction),
   * thumbnail images, clickable view buttons, and best/worst price highlighting.
   * 
   * NOTE: Hybrid approach displays both SQL request results (internal prices) and 
   * websearch results (external prices) for comprehensive analysis.
   * 
   * @param array $priceComparison Price comparison data (can be array of products or single product)
   * @return string Formatted HTML with enhanced price comparison
   */
  public function formatPriceComparisonEnhanced(array $priceComparison): string
  {
    if (empty($priceComparison)) {
      return '';
    }

    $output = "<div class='price-comparison-enhanced alert alert-info' style='margin: 20px 0;'>";
    $output .= "<h5>💰 " . $this->language->getDef('text_rag_web_search_price_comparison') . "</h5>";

    // Handle both single product and array of products
    $products = [];
    if (isset($priceComparison['internal_price']) || isset($priceComparison['external_prices'])) {
      // Single product format
      $products = [$priceComparison];
    } else {
      // Array of products
      $products = $priceComparison;
    }

    foreach ($products as $product) {
      $internalPrice = $product['internal_price'] ?? null;
      $externalPrices = $product['external_prices'] ?? [];
      $currency = $product['currency'] ?? '€';
      $productTitle = $product['product_title'] ?? '';
      
      // Collect all prices for best/worst detection
      $allPrices = [];
      if ($internalPrice !== null) {
        $allPrices[] = (float)$internalPrice;
      }
      foreach ($externalPrices as $ext) {
        if (isset($ext['price'])) {
          $allPrices[] = (float)$ext['price'];
        }
      }
      
      $bestPrice = !empty($allPrices) ? min($allPrices) : null;
      $worstPrice = !empty($allPrices) ? max($allPrices) : null;

      // Product title if available
      if (!empty($productTitle)) {
        $output .= "<div style='font-weight: bold; margin-top: 15px; margin-bottom: 10px;'>";
        $output .= htmlspecialchars($productTitle);
        $output .= "</div>";
      }

      // Internal price (from SQL/database)
      if ($internalPrice !== null) {
        $isBest = ($bestPrice !== null && (float)$internalPrice === $bestPrice);
        $isWorst = ($worstPrice !== null && (float)$internalPrice === $worstPrice && $bestPrice !== $worstPrice);
        
        $output .= "<div class='price-item' style='padding: 10px; margin-bottom: 8px; border-left: 3px solid #007bff; background-color: #f8f9fa;'>";
        $output .= "<div style='display: flex; justify-content: space-between; align-items: center;'>";
        
        // Price and source
        $output .= "<div>";
        $output .= "<strong>" . $this->language->getDef('text_rag_web_search_our_price') . "</strong> ";
        $output .= number_format((float)$internalPrice, 2, ',', ' ') . " {$currency}";
        
        // Best/Worst badge
        if ($isBest) {
          $output .= " <span class='badge badge-success' style='margin-left: 10px;'>✅ " . $this->language->getDef('text_rag_best_price') . "</span>";
        } elseif ($isWorst) {
          $output .= " <span class='badge badge-danger' style='margin-left: 10px;'>❌ " . $this->language->getDef('text_rag_worst_price') . "</span>";
        }
        
        $output .= "</div>";
        $output .= "</div>";
        $output .= "</div>";
      }

      // External prices (from web search)
      if (!empty($externalPrices)) {
        $shoppingDataCount = 0;
        $webExtractionCount = 0;
        
        foreach ($externalPrices as $competitor) {
          $name = $competitor['name'] ?? 'Unknown';
          $price = $competitor['price'] ?? 0;
          $url = $competitor['url'] ?? '';
          $thumbnail = $competitor['thumbnail'] ?? '';
          $dataSource = $competitor['data_source'] ?? 'web_extraction'; // 'shopping_data' or 'web_extraction'
          $compCurrency = $competitor['currency'] ?? $currency;
          
          // Count data sources
          if ($dataSource === 'shopping_data') {
            $shoppingDataCount++;
          } else {
            $webExtractionCount++;
          }
          
          $isBest = ($bestPrice !== null && (float)$price === $bestPrice);
          $isWorst = ($worstPrice !== null && (float)$price === $worstPrice && $bestPrice !== $worstPrice);
          
          $output .= "<div class='price-item' style='padding: 10px; margin-bottom: 8px; border-left: 3px solid #17a2b8; background-color: #f8f9fa; display: flex; align-items: center;'>";
          
          // Thumbnail if available
          if (!empty($thumbnail)) {
            $output .= "<div style='margin-right: 15px;'>";
            $output .= "<img src='" . htmlspecialchars($thumbnail) . "' alt='" . htmlspecialchars($name) . "' style='width: 60px; height: 60px; object-fit: contain;' />";
            $output .= "</div>";
          }
          
          // Price details
          $output .= "<div style='flex-grow: 1;'>";
          
          // Competitor name and price
          $output .= "<div style='margin-bottom: 5px;'>";
          $output .= "<strong>" . htmlspecialchars($name) . "</strong> : ";
          $output .= number_format((float)$price, 2, ',', ' ') . " {$compCurrency}";
          
          // Best/Worst badge
          if ($isBest) {
            $output .= " <span class='badge badge-success' style='margin-left: 10px;'>✅ " . $this->language->getDef('text_rag_best_price') . "</span>";
          } elseif ($isWorst) {
            $output .= " <span class='badge badge-danger' style='margin-left: 10px;'>❌ " . $this->language->getDef('text_rag_worst_price') . "</span>";
          }
          
          $output .= "</div>";
          
          // Data source badge
          $output .= "<div style='font-size: 0.85em;'>";
          if ($dataSource === 'shopping_data') {
            $output .= "<span class='badge badge-info'>🛒 " . $this->language->getDef('text_rag_data_source_shopping') . "</span>";
          } else {
            $domainProvider = WebSearchEngineRegistry::getInstance()
              ->findProviderByEngineName((string) $dataSource);
            if ($domainProvider !== null) {
              $output .= "<span class='badge badge-info'>🛒 " . htmlspecialchars($domainProvider->getDisplayName()) . "</span>";
            } else {
              $output .= "<span class='badge badge-secondary'>🌐 " . $this->language->getDef('text_rag_data_source_web') . "</span>";
            }
          }
          $output .= "</div>";
          
          // Percentage difference if internal price exists
          if ($internalPrice !== null) {
            $diff = (((float)$price - (float)$internalPrice) / (float)$internalPrice) * 100;
            $diffFormatted = number_format($diff, 1);
            
            $output .= "<div style='font-size: 0.85em; margin-top: 5px;'>";
            if ($diff > 0) {
              $output .= "<span class='text-success'>(+{$diffFormatted}%)</span>";
            } elseif ($diff < 0) {
              $output .= "<span class='text-danger'>({$diffFormatted}%)</span>";
            }
            $output .= "</div>";
          }
          
          $output .= "</div>";
          
          // View button
          if (!empty($url)) {
            $output .= "<div style='margin-left: 15px;'>";
            $output .= "<a href='" . htmlspecialchars($url) . "' target='_blank' rel='noopener noreferrer' class='btn btn-sm btn-outline-primary'>";
            $output .= $this->language->getDef('text_rag_web_search_see') . " 🔗";
            $output .= "</a>";
            $output .= "</div>";
          }
          
          $output .= "</div>"; // .price-item
        }
        
        // Recommendation text mentioning data sources
        if ($shoppingDataCount > 0 || $webExtractionCount > 0) {
          $output .= "<div class='recommendation' style='margin-top: 15px; padding: 10px; background-color: #e7f3ff; border-radius: 4px; font-size: 0.9em;'>";
          $output .= "<strong>💡 " . $this->language->getDef('text_rag_web_search_recommendation') . "</strong> ";
          
          $parts = [];
          if ($shoppingDataCount > 0) {
            $parts[] = $shoppingDataCount . " " . $this->language->getDef('text_rag_data_source_shopping');
          }
          if ($webExtractionCount > 0) {
            $parts[] = $webExtractionCount . " " . $this->language->getDef('text_rag_data_source_web');
          }
          
          $output .= implode(', ', $parts);
          $output .= "</div>";
        }
      }

      // Custom recommendation if provided
      if (isset($product['recommendation'])) {
        $output .= "<div class='recommendation' style='margin-top: 10px; padding: 8px; background-color: #f8f9fa; border-radius: 4px;'>";
        $output .= "<strong>💡 " . $this->language->getDef('text_rag_web_search_recommendation') . "</strong> " . htmlspecialchars($product['recommendation']);
        $output .= "</div>";
      }
    }

    $output .= "</div>"; // .price-comparison-enhanced

    return $output;
  }
}
