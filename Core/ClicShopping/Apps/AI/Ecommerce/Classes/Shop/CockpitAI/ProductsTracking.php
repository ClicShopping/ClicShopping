<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\CockpitAI;

  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;
  use ClicShopping\Sites\Shop\BotDetector;

  class ProductsTracking
  {
      /**
     * ProductsTracking constructor.
     */
    public function __construct()
    {
      if (!static::checkStatusProductTracking()) {
        return;
      }
    }

    /**
     * Check if the tracking system is activated
     * @return bool
     */
    private static function checkStatusProductTracking(): bool
    {
      $requiredConstants = [
        'CLICSHOPPING_APP_ECOMMERCE_CAI_STATUS',
        'CLICSHOPPING_APP_ECOMMERCE_CAI_PRODUCT_TRACKING',
        'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      ];

      CLICSHOPPING::checkAppsIsActivated($requiredConstants);

      if (!Gpt::checkGptStatus()) {
        return false;
      }

      return true;
    }

    /**
     * Record a single product impression
     * @param int $products_id
     * @param string $module_code
     * @param string $module_position
     * @param int $sort_order
     * @param int $language_id
     * @param string|array|null $metadata
     */
    public static function insertProductTracking(
      int $products_id,
      string $module_code,
      string $module_position,
      int $sort_order,
      int $language_id,
      string|array|null $metadata = null,
      ?float $weight = null
    ): void
    {
      $CLICSHOPPING_Customer = Registry::get('Customer');
      $CLICSHOPPING_Db = Registry::get('Db');

      if (!self::checkStatusProductTracking()) {
        return;
      }

      // 1. Anti-Spam Check (Cool-down 15 min)
      // Avoids artificial inflation of scores by page refreshing
      if (self::isRecentImpression($products_id, $module_code, $module_position)) {
        return;
      }

      // If a bot is detected, stop execution immediately to avoid skewed analytics
      $botDetector = new BotDetector();

      if ($botDetector->isBot()) {
        return;
      }

      $session_id = session_id() ?: 'no-session';
       $binary_hash = pack("H*", hash('sha256', $session_id));

      $customer_id = $CLICSHOPPING_Customer->isLoggedOn() ? (int)$CLICSHOPPING_Customer->getID() : null;

      $customer_group_id = (int)$CLICSHOPPING_Customer->getCustomersGroupID();

      /* // GDPR Compliance - Placeholder for customer consent check
      if (defined('CONFIG_TRACKING_ALLOW_CUSTOMER_ID') && CONFIG_TRACKING_ALLOW_CUSTOMER_ID == 'True') {
           // Logic for authorized tracking
      }
      */

      // Merge weight into metadata for the IA Cockpit
      $final_metadata = is_array($metadata) ? $metadata : [];

      if (!is_null($weight)) {
        $final_metadata['w'] = $weight;
      }

      $json_metadata = !empty($final_metadata) ? json_encode(array_filter($final_metadata, fn($v) => $v !== null)) : null;

      $insert_sql_array = [
        'products_id' => $products_id,
        'language_id' => $language_id,
        'page_code' =>  self::pageCode(['page_code' => null]),
        'displayed_at' => 'now()',
        'session_hash' => $binary_hash,
        'customer_id' => $customer_id,
        'customer_group_id' => $customer_group_id,
        'module_code' => $module_code,
        'module_position' => $module_position,
        'module_sort_order' => $sort_order,
        'metadata' => $json_metadata,
        'weight' => $weight
      ];

      $CLICSHOPPING_Db->save('products_cockpit_ai_tracking_impressions', $insert_sql_array);

      // Maintain Circular Buffer (FIFO)
      self::applyCircularQuota($module_code);
    }

    /**
     * Check if the product was already tracked in this session recently
     * Prevents F5/Refresh from skewing IA data
     */
    private static function isRecentImpression(int $products_id, string $module_code, string $module_position): bool
    {
      $CLICSHOPPING_Db = Registry::get('Db');
      $session_id = session_id() ?: 'no-session';
      $binary_hash = pack("H*", hash('sha256', $session_id));

      $Qcheck = $CLICSHOPPING_Db->prepare('SELECT id 
                                           FROM :table_products_cockpit_ai_tracking_impressions 
                                           WHERE products_id = :products_id 
                                           AND module_code = :module_code 
                                           AND session_hash = :hash 
                                           AND module_position = :module_position
                                           AND displayed_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                                           LIMIT 1');
      $Qcheck->bindInt(':products_id', $products_id);
      $Qcheck->bindValue(':module_position', $module_position);
      $Qcheck->bindValue(':module_code', $module_code);
      $Qcheck->bindValue(':hash', $binary_hash);

      $Qcheck->execute();

      return ($Qcheck->fetch() !== false);
    }

    /**
     * Dynamic determination of the page code with multiple fallbacks
     * @param array $data
     * @return string
     */
    private static function pageCode(array $data = []): string
    {
      // 1. Priority: Manually forced value from the module call
      if (!empty($data['page_code'])) {
        return (string)$data['page_code'];
      }

      // 2. URL Analysis ($_GET): Highly reliable as it's available early in the lifecycle
      if (isset($_GET) && !empty($_GET)) {
        $keys = array_keys($_GET);
        $first_key = $keys[0];

        // Sanitize key to avoid technical indexes like index_php
        if (!empty($first_key) && $first_key !== 'index_php' && !str_contains($first_key, '/')) {
          return (string)basename($first_key);
        }
      }

      // 3. Registry Attempt: If the Site object is already initialized
      try {
        if (Registry::exists('Site')) {
          $site = Registry::get('Site');
          if (method_exists($site, 'getPage') && $site->getPage() !== null) {
            $page_code = $site->getPage()->getCode();
            if (!empty($page_code)) {
              return (string)$page_code;
            }
          }
        }
      } catch (\Exception $e) {
        // Optional: error_log('Tracking PageCode Registry fail: ' . $e->getMessage());
      }

      // 4. Final Fallback: Ensures a value exists for MySQL NOT NULL constraint
      return 'index';
    }

    /**
     * Maintain a fixed number of rows per module (FIFO logic)
     * Ensures the IA Cockpit works with fresh data and prevents DB bloating
     * @param string $module_code
     */
    private static function applyCircularQuota(string $module_code): void
    {
      $db = Registry::get('Db');
      $quota = 1000;

      // compter les lignes
      $Qcount = $db->prepare('SELECT COUNT(*) as total 
                            FROM :table_products_cockpit_ai_tracking_impressions 
                            WHERE module_code = :module_code
                            ');
      $Qcount->bindValue(':module_code', $module_code);
      $Qcount->execute();

      $row = $Qcount->fetch();
      $total = (int)$row['total'];

      if ($total <= $quota) {
        return;
      }

      // suppression déterministe des plus anciennes entrées
      $limit = $total - $quota;

      $Qdel = $db->prepare('DELETE FROM :table_products_cockpit_ai_tracking_impressions 
                             WHERE module_code = :module_code 
                             ORDER BY id ASC 
                             LIMIT :limit
                             ');

      $Qdel->bindValue(':module_code', $module_code);
      $Qdel->bindInt(':limit', $limit);
      $Qdel->execute();
    }

    /**
     * Insert multiple rows (batch processing compatible with save())
     * @param array $rows
     */
    public static function insertMultiple(array $rows): void
    {
      if (!self::checkStatusProductTracking()) {
        return;
      }

      $CLICSHOPPING_Customer = Registry::get('Customer');
      $CLICSHOPPING_Db = Registry::get('Db');

      $botDetector = new BotDetector();
      if ($botDetector->isBot()) {
        return;
      }

      $session_id = session_id() ?: 'no-session';
      $binary_hash = pack("H*", hash('sha256', $session_id));

      $customer_id = $CLICSHOPPING_Customer->isLoggedOn() ? (int)$CLICSHOPPING_Customer->getID() : null;

      $customer_group_id = (int)$CLICSHOPPING_Customer->getCustomersGroupID();

      foreach ($rows as $row) {
        if (!isset(
          $row['products_id'],
          $row['module_code'],
          $row['module_position'],
          $row['module_sort_order'],
          $row['language_id']
        )) {
          continue;
        }

        if (self::isRecentImpression(
          (int)$row['products_id'],
          (string)$row['module_code'],
          (string)$row['module_position']
        )) {
          continue;
        }

        $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
        $weight = isset($row['weight']) ? (float)$row['weight'] : null;

        if (!is_null($weight)) {
          $metadata['w'] = $weight;
        }

        $insert_sql_array = [
          'products_id' => (int)$row['products_id'],
          'language_id' => (int)$row['language_id'],
          'page_code' => self::pageCode(['page_code' => $row['page_code'] ?? null]),
          'displayed_at' => 'now()',
          'session_hash' => $binary_hash,
          'customer_id' => $customer_id,
          'customer_group_id' => $customer_group_id,
          'module_code' => (string)$row['module_code'],
          'module_position' => (string)$row['module_position'],
          'module_sort_order' => (int)$row['module_sort_order'],
          'metadata' => !empty($metadata) ? json_encode($metadata) : null,
          'weight' => $weight
        ];

        $CLICSHOPPING_Db->save(':table_products_cockpit_ai_tracking_impressions', $insert_sql_array);
      }
    }

    /**
     * Analytics - Retrieve product impression count
     * @param int $productId
     * @param int $days
     * @return int
     */
    public static function getProductImpressions(int $productId, int $days = 30): int
    {
      $CLICSHOPPING_Db = Registry::get('Db');

      $Q = $CLICSHOPPING_Db->prepare('SELECT COUNT(*) AS total
                                     FROM :table_products_cockpit_ai_tracking_impressions
                                     WHERE products_id = :products_id
                                     AND displayed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                                     ');
      $Q->bindInt(':products_id', $productId);
      $Q->bindInt(':days', $days);
      $Q->execute();

      return (int)$Q->valueInt('total');
    }

    /**
     * Analytics - Stats grouped by page and module
     * @param int $days
     * @return array
     */
    public static function getStatsByPageModule(int $days = 30): array
    {
      $CLICSHOPPING_Db = Registry::get('Db');

      $Q = $CLICSHOPPING_Db->prepare('SELECT page_code, 
                                             module_code, 
                                             COUNT(*) AS impressions
                                      FROM :table_products_cockpit_ai_tracking_impressions
                                      WHERE displayed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                                      GROUP BY page_code, module_code
                                      ORDER BY impressions DESC
                                      ');
      $Q->bindInt(':days', $days);
      $Q->execute();

      return $Q->fetchAll();
    }

    /**
     * Analytics - Top performing products
     * @param int $limit
     * @param int $days
     * @return array
     */
    public static function getTopProducts(int $limit = 20, int $days = 30): array
    {
      $CLICSHOPPING_Db = Registry::get('Db');

      $Q = $CLICSHOPPING_Db->prepare('SELECT products_id, COUNT(*) AS impressions
                                       FROM :table_products_cockpit_ai_tracking_impressions
                                       WHERE displayed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                                       GROUP BY products_id
                                       ORDER BY impressions DESC
                                       LIMIT :limit
                                       ');
      $Q->bindInt(':days', $days);
      $Q->bindInt(':limit', $limit);
      $Q->execute();

      return $Q->fetchAll();
    }

    /**
     * Analytics - Average display position
     * @param int $productId
     * @param int $days
     * @return float
     */
    public static function getAveragePosition(int $productId, int $days = 30): float
    {
      $CLICSHOPPING_Db = Registry::get('Db');

      $Q = $CLICSHOPPING_Db->prepare('SELECT AVG(module_sort_order) AS avg_position
                                       FROM :table_products_cockpit_ai_tracking_impressions
                                       WHERE products_id = :products_id
                                       AND displayed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                             ');
      $Q->bindInt(':products_id', $productId);
      $Q->bindInt(':days', $days);
      $Q->execute();

      return (float)$Q->value('avg_position');
    }
  }