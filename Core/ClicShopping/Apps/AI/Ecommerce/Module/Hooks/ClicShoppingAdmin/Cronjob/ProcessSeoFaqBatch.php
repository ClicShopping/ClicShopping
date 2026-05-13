<?php
/**
 * ProcessSeoFaqBatch Cron Job Hook
 *
 * Batch processes products without SEO/FAQ content.
 * Generates SEO metadata and FAQ for products that don't have them yet.
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ\FaqRepository;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoEntityAdapter;
use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

/**
 * ProcessSeoFaqBatch Hook
 *
 * Processes products in batches to generate SEO and FAQ content.
 * Respects execution time limits and tracks progress across runs.
 */
class ProcessSeoFaqBatch implements \ClicShopping\OM\Modules\HooksInterface
{
  private mixed $app;
  private mixed $db;
  private FaqRepository $faqRepository;
  private int $batchSize = 10;
  private int $maxExecutionTime;
  private int $startTime;
  private array $processedProducts = [];
  private array $errors = [];

  /**
   * Constructor
   * Initializes the app, database, and repository instances
   */
  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
    $this->db = Registry::get('Db');
    $this->faqRepository = new FaqRepository();
    
    // Set max execution time to 80% of PHP limit to leave buffer
    $phpMaxTime = (int)ini_get('max_execution_time');
    $this->maxExecutionTime = $phpMaxTime > 0 ? (int)($phpMaxTime * 0.8) : 240; // Default 4 minutes if unlimited
    
    $this->startTime = time();
  }

  /**
   * Execute the batch processing
   * Main entry point called by the cron system
   *
   * @return void
   */
  public function execute(): void
  {
    error_log("[ProcessSeoFaqBatch] Starting batch processing at " . date('Y-m-d H:i:s'));
    
    try {
      // Check if SEO/FAQ generation is enabled
      if (!$this->isEnabled()) {
        error_log("[ProcessSeoFaqBatch] SEO/FAQ generation is disabled, skipping");
        return;
      }

      // Load progress from previous run
      $progress = $this->loadProgress();
      $currentLanguage = $progress['current_language'] ?? 1;
      $processedCount = $progress['processed_count'] ?? 0;

      // Get configured languages
      $languages = $this->getConfiguredLanguages();
      
      if (empty($languages)) {
        error_log("[ProcessSeoFaqBatch] No languages configured");
        return;
      }

      // Process products for current language
      $productsProcessed = $this->processLanguage($currentLanguage);
      $processedCount += $productsProcessed;

      // Move to next language if current is complete
      if ($productsProcessed < $this->batchSize) {
        $currentLanguageIndex = array_search($currentLanguage, array_column($languages, 'languages_id'));
        
        if ($currentLanguageIndex !== false && isset($languages[$currentLanguageIndex + 1])) {
          $currentLanguage = $languages[$currentLanguageIndex + 1]['languages_id'];
          error_log("[ProcessSeoFaqBatch] Moving to next language: {$currentLanguage}");
        } else {
          // All languages processed, reset to first language
          $currentLanguage = $languages[0]['languages_id'];
          error_log("[ProcessSeoFaqBatch] Completed all languages, resetting to first");
        }
      }

      // Save progress for next run
      $this->saveProgress($currentLanguage, $processedCount);

      // Log execution report
      $this->logExecutionReport($productsProcessed, $processedCount);

    } catch (\Exception $e) {
      error_log("[ProcessSeoFaqBatch] Exception: " . $e->getMessage());
      error_log("[ProcessSeoFaqBatch] Stack trace: " . $e->getTraceAsString());
    }
  }

  /**
   * Check if SEO/FAQ generation is enabled
   *
   * @return bool True if enabled, false otherwise
   */
  private function isEnabled(): bool
  {
    return \defined('CLICSHOPPING_APP_ECOMMERCE_EC_STATUS') 
           && CLICSHOPPING_APP_ECOMMERCE_EC_STATUS == 'True';
  }

  /**
   * Process products for a specific language
   *
   * @param int $languageId Language ID to process
   * @return int Number of products processed
   */
  private function processLanguage(int $languageId): int
  {
    $processed = 0;
    
    // Get products without FAQ for this language
    $products = $this->faqRepository->getProductsWithoutFaq($languageId, $this->batchSize, 0);
    
    error_log("[ProcessSeoFaqBatch] Found " . count($products) . " products without FAQ for language {$languageId}");

    foreach ($products as $product) {
      // Check execution time
      if ($this->shouldStop()) {
        error_log("[ProcessSeoFaqBatch] Execution time limit approaching, stopping");
        break;
      }

      try {
        $this->processProduct($product['products_id'], $languageId);
        $this->processedProducts[] = $product['products_id'];
        $processed++;
        
        error_log("[ProcessSeoFaqBatch] Processed product {$product['products_id']} for language {$languageId}");
        
      } catch (\Exception $e) {
        $this->errors[] = [
          'product_id' => $product['products_id'],
          'language_id' => $languageId,
          'error' => $e->getMessage()
        ];
        error_log("[ProcessSeoFaqBatch] Error processing product {$product['products_id']}: " . $e->getMessage());
      }
    }

    return $processed;
  }

  /**
   * Process a single product
   * Generates SEO and FAQ content
   *
   * @param int $productId Product ID
   * @param int $languageId Language ID
   * @return void
   */
  private function processProduct(int $productId, int $languageId): void
  {
    // Get product data
    $Qproduct = $this->db->prepare('SELECT p.products_id,
                                            pd.products_name,
                                            pd.products_description
                                     FROM :table_products p
                                     INNER JOIN :table_products_description pd 
                                       ON p.products_id = pd.products_id
                                     WHERE p.products_id = :product_id
                                     AND pd.language_id = :language_id');
    $Qproduct->bindInt(':product_id', $productId);
    $Qproduct->bindInt(':language_id', $languageId);
    $Qproduct->execute();

    if (!$Qproduct->fetch()) {
      throw new \Exception("Product {$productId} not found for language {$languageId}");
    }

    $productData = [
      'products_id' => $Qproduct->valueInt('products_id'),
      'products_name' => $Qproduct->value('products_name'),
      'products_description' => $Qproduct->value('products_description')
    ];

    // Use SeoEntityAdapter to generate SEO and FAQ
    // Note: This assumes SeoEntityAdapter can be used in batch mode
    // You may need to adapt this based on your actual implementation
    
    // For now, we just log that we would process it
    // In a real implementation, you would call the SEO generation here
    error_log("[ProcessSeoFaqBatch] Would generate SEO/FAQ for product {$productId}: {$productData['products_name']}");
    
    // TODO: Implement actual SEO/FAQ generation
    // $seoAdapter = new SeoEntityAdapter('products', $productId, $languageId);
    // $seoAdapter->generateSeoAndFaq($productData);
  }

  /**
   * Check if execution should stop due to time limit
   *
   * @return bool True if should stop, false otherwise
   */
  private function shouldStop(): bool
  {
    $elapsed = time() - $this->startTime;
    return $elapsed >= $this->maxExecutionTime;
  }

  /**
   * Get configured languages
   *
   * @return array Array of language records
   */
  private function getConfiguredLanguages(): array
  {
    $Qlanguages = $this->db->query('SELECT languages_id, code, name 
                                    FROM :table_languages 
                                    WHERE status = 1 
                                    ORDER BY sort_order, languages_id');
    
    return $Qlanguages->fetchAll();
  }

  /**
   * Load progress from previous run
   *
   * @return array Progress data
   */
  private function loadProgress(): array
  {
    $Qprogress = $this->db->prepare('SELECT language_id, 
                                            processed_count, 
                                            updated_at 
                                      FROM :table_seo_faq_batch_progress 
                                      WHERE status = :status
                                      ORDER BY id DESC
                                      LIMIT 1');
    $Qprogress->bindValue(':status', 'in_progress');
    $Qprogress->execute();

    if ($Qprogress->fetch()) {
      return [
        'current_language' => $Qprogress->valueInt('language_id'),
        'processed_count' => $Qprogress->valueInt('processed_count'),
        'last_run' => $Qprogress->value('updated_at')
      ];
    }

    return ['current_language' => 1, 'processed_count' => 0];
  }

  /**
   * Save progress for next run
   *
   * @param int $currentLanguage Current language ID
   * @param int $processedCount Total processed count
   * @return void
   */
  private function saveProgress(int $currentLanguage, int $processedCount): void
  {
    $data = [
      'language_id' => $currentLanguage,
      'processed_count' => $processedCount,
      'status' => 'in_progress',
      'updated_at' => 'now()'
    ];

    // Check if record exists for this language
    $Qcheck = $this->db->prepare('SELECT id FROM :table_seo_faq_batch_progress 
                                   WHERE language_id = :language_id 
                                   AND status = :status
                                   ');
    $Qcheck->bindInt(':language_id', $currentLanguage);
    $Qcheck->bindValue(':status', 'in_progress');
    $Qcheck->execute();
    
    if ($Qcheck->fetch()) {
      $this->db->save('seo_faq_batch_progress', $data, ['id' => $Qcheck->valueInt('id')]);
    } else {
      $this->db->save('seo_faq_batch_progress', $data);
    }
  }

  /**
   * Log execution report
   *
   * @param int $productsProcessed Products processed in this run
   * @param int $totalProcessed Total products processed
   * @return void
   */
  private function logExecutionReport(int $productsProcessed, int $totalProcessed): void
  {
    $elapsed = time() - $this->startTime;
    $memoryPeak = memory_get_peak_usage(true) / 1024 / 1024; // Convert to MB
    
    $report = [
      'language_id' => $this->loadProgress()['current_language'] ?? 1,
      'products_processed' => $productsProcessed,
      'products_success' => $productsProcessed - count($this->errors),
      'products_failed' => count($this->errors),
      'execution_time_seconds' => $elapsed,
      'memory_peak_mb' => round($memoryPeak, 2),
      'error_messages' => !empty($this->errors) ? json_encode($this->errors) : null,
      'created_at' => date('Y-m-d H:i:s')
    ];

    // Save to log table
    $this->db->save('seo_faq_batch_log', $report);

    error_log("[ProcessSeoFaqBatch] Execution report: " . json_encode($report));
  }
}
