<?php
/**
 * MCP (Multi-Channel Products) API endpoint for retrieving product data.
 *
 * This class provides a set of methods to interact with the product catalog,
 * including listing, searching, and getting detailed information for products.
 * It also includes advanced functionality for natural language processing (NLP)
 * to handle chat-based product queries.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\EndPoint;


use ClicShopping\AI\Infrastructure\Cache\TranslationCache;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\MCPConnector;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;
use ClicShopping\Apps\Tools\MCP\MCP;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Products
{
  /** @var mixed The MCP application instance. */
  protected mixed $app;
  /** @var mixed The message handler for sending API responses. */
  protected mixed $message;
  /** @var mixed The database connection object. */
  protected mixed $db;
  /** @var mixed The language object. */
  protected mixed $lang;
  /** @var mixed The translation cache for optimizing GPT requests. */
  protected mixed $translationCache;
  /** @var array The rules for NLP intent analysis. */
  protected array $rules = [];
  private mixed $mcpConnector;
  
  /**
   * Class constructor. Initializes and registers necessary components.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('MCP')) {
      Registry::set('MCP', new MCP());
    }

    $this->app = Registry::get('MCP');

    Registry::set('Message', new Message());
    $this->message = Registry::get('Message');

    Registry::set('MCPConnector', new MCPConnector());
    $this->mcpConnector = Registry::get('MCPConnector');

    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');

    Registry::set('TranslationCache', new TranslationCache());
    $this->translationCache = Registry::get('TranslationCache');

    // Initialize the NLP rules for search intent analysis
    $this->initializeRules();
  }

  /**
   * Defines the regex rules for NLP intent and entity detection.
   *
   * @return void
   */
  protected function initializeRules(): void
  {
    $this->rules = [
      // Main intentions
      [
        'regex' => '/\b(search|find|show|list|lookup|browse|explore)\b/',
        'type' => 'product_search',
        'confidence' => 0.8
      ],
      // Price ranges
      [
        'regex' => '/\b(between)\s*(\d+(?:[\.,]\d{1,2})?)\s*(?:and|-)\s*(\d+(?:[\.,]\d{1,2})?)\s*(€|\$|usd|eur|gbp)?\b/i',
        'type' => 'price_range',
        'confidence' => 0.9,
        'extract' => function ($matches) {
          return [
            'price_min' => (float)str_replace(',', '.', $matches[2]),
            'price_max' => (float)str_replace(',', '.', $matches[3])
          ];
        }
      ],
      [
        'regex' => '/\b(under|less than|cheaper than)\s*(\d+(?:[\.,]\d{1,2})?)\s*(€|\$|usd|eur|gbp)?\b/i',
        'type' => 'price_max',
        'confidence' => 0.85,
        'extract' => function ($matches) {
          return ['price_max' => (float)str_replace(',', '.', $matches[2])];
        }
      ],
      [
        'regex' => '/\b(over|above|more than|higher than)\s*(\d+(?:[\.,]\d{1,2})?)\s*(€|\$|usd|eur|gbp)?\b/i',
        'type' => 'price_min',
        'confidence' => 0.85,
        'extract' => function ($matches) {
          return ['price_min' => (float)str_replace(',', '.', $matches[2])];
        }
      ],
      // Product attributes
      [
        'regex' => '/\b(color|colour|red|blue|green|yellow|black|white|size|small|medium|large|brand|model|material)\b/i',
        'type' => 'attribute_filter',
        'confidence' => 0.75,
        'extract' => function ($matches) {
          return ['attribute' => $matches[0]];
        }
      ],
      // References
      [
        'regex' => '/\b(REF|SKU|EAN|UPC|ISBN|GTIN|barcode)[-\s]?(\d+[-\w]*)\b/i',
        'type' => 'product_reference',
        'confidence' => 0.9,
        'extract' => function ($matches) {
          return ['reference' => $matches[2]];
        }
      ],
      // Stock and availability
      [
        'regex' => '/\b(stock|availability|available|out of stock|quantity|in stock|only (\d+) left)\b/i',
        'type' => 'stock_filter',
        'confidence' => 0.8,
        'extract' => function ($matches) {
          return isset($matches[2]) ? ['quantity_min' => (int)$matches[2]] : [];
        }
      ],
      // Promotions and offers
      [
        'regex' => '/\b(discount|promo|sale|flash sale|deal)\b/i',
        'type' => 'promotion_filter',
        'confidence' => 0.7
      ]
    ];
  }


  /**
   * Retrieves a list of products based on various filters.
   *
   * @return void Sends a JSON success response with product data and pagination.
   */
  // Fichier : Core/ClicShopping/Apps/Tools/MCP/Classes/Shop/EndPoint/Products.php (Méthode getProductsList)

  public function getProductsList(): void
  {
    // 1. Initialisation sécurisée des paramètres (Corrige les PHP Warnings)
    $limit = (int)HTML::sanitize($_GET['limit'] ?? $_POST['limit'] ?? 10);
    $offset = (int)HTML::sanitize($_GET['offset'] ?? $_POST['offset'] ?? 0);
    $category_id = HTML::sanitize($_GET['category_id'] ?? $_POST['category_id'] ?? null);
    $status = HTML::sanitize($_GET['status'] ?? $_POST['status'] ?? 1);

    $sql = 'SELECT SQL_CALC_FOUND_ROWS p.products_id,
                                       pd.products_name,
                                       pd.products_description,
                                       p.products_model,
                                       p.products_price,
                                       p.products_quantity,
                                       p.products_status,
                                       p.products_image,
                                       p.products_date_added,
                                       p.products_last_modified,
                                       p.products_weight,
                                       p.products_tax_class_id
                                FROM :table_products p
                                LEFT JOIN :table_products_description pd ON p.products_id = pd.products_id
                                WHERE pd.language_id = :lang_id
                                AND p.products_status = :status
                                AND p.products_quantity > 0
                                AND p.products_archive = 0
                                AND p.products_view = 1
                                AND p.orders_view = 1';

    $params = [
      ':lang_id' => $this->lang->getId()
    ];

    if (!empty($category_id)) {
      // Utilisation d'un JOIN pour la liaison Catégorie
      $sql .= ' AND p.products_id IN (SELECT products_id 
                                      FROM :table_products_to_categories 
                                      WHERE categories_id = :category_id)';
      $params[':category_id'] = (int)$category_id;
    }

    $status = (int)$status;

    if ($status === 0 || $status === 1) { // Accepte 0 ou 1
      $sql .= ' AND p.products_status = :status';
      $params[':status'] = $status; // Ajoute le paramètre de statut
    }

    $sql .= ' ORDER BY pd.products_name ASC LIMIT :limit OFFSET :offset';

    $Qproducts = $this->db->prepare($sql);

    foreach ($params as $key => $value) {
      if (is_int($value)) {
        $Qproducts->bindInt($key, $value);
      } else {
        $Qproducts->bindValue($key, $value);
      }
    }

    $Qproducts->bindInt(':limit', $limit);
    $Qproducts->bindInt(':offset', $offset);

    $Qproducts->execute();

    $productArray = [];
    while ($Qproducts->fetch()) {
      $productArray[] = [
        'id' => (int)$Qproducts->valueInt('products_id'),
        'name' => $Qproducts->value('products_name'),
        'description' => strip_tags($Qproducts->value('products_description')),
        'model' => $Qproducts->value('products_model'),
        'price' => (float)$Qproducts->valueDecimal('products_price'),
        'quantity' => (int)$Qproducts->valueInt('products_quantity'),
        'status' => (int)$Qproducts->valueInt('products_status'),
        'image' => $Qproducts->value('products_image'),
        'weight' => (float)$Qproducts->valueDecimal('products_weight'),
        'date_added' => $Qproducts->value('products_date_added'),
      ];
    }

    $Qtotal = $this->db->query('SELECT FOUND_ROWS()');

    $totalRow = $Qtotal->fetch();
    $totalProducts = (int)($totalRow[0] ?? 0);

    $hasMore = ($offset + $limit) < $totalProducts;

    $this->message->sendSuccess([
      'products' => $productArray,
      'pagination' => [
        'limit' => $limit,
        'offset' => $offset,
        'count' => count($productArray),
        'total' => $totalProducts,
        'has_more' => $hasMore
      ]
    ]);
  }

  /**
   * Retrieves detailed information for a single product.
   *
   * @return void Sends a JSON response with the product details or an error if not found.
   */
  public function getProductDetail(): void
  {
    $productId = filter_var(HTML::sanitize($_GET['id']) ?? HTML::sanitize($_POST['id']) ?? 0, FILTER_VALIDATE_INT);

    if ($productId === false || $productId <= 0) {
      $this->message->sendError('A valid product ID is required.', 400);
      return;
    }

    $Qproduct = $this->db->prepare("SELECT p.products_id,
                                        pd.products_name,
                                        pd.products_description,
                                        p.products_model,
                                        p.products_price,
                                        p.products_quantity,
                                        p.products_status,
                                        p.products_image,
                                        p.products_weight,
                                        p.products_tax_class_id,
                                        p.products_date_added,
                                        p.products_last_modified
                                 FROM :table_products p
                                 LEFT JOIN :table_products_description pd ON p.products_id = pd.products_id
                                 WHERE p.products_id = :product_id
                                 AND pd.language_id = :language_id
                                 AND p.products_status = 1
                                 AND p.products_quantity > 0
                                 AND p.products_archive = 0
                                 AND p.products_view = 1
                                 AND p.orders_view = 1
                                ");

    $Qproduct->bindInt(':product_id', $productId);
    $Qproduct->bindInt(':language_id', (int)$this->lang->getId());
    $Qproduct->execute();

    if ($Qproduct->rowCount() === 0) {
      $this->message->sendError('Product not found or is unavailable.', 404);
      return;
    }

    $product = [
      'id' => (int)$Qproduct->valueInt('products_id'),
      'name' => $Qproduct->value('products_name'),
      'description' => strip_tags($Qproduct->value('products_description')),
      'model' => $Qproduct->value('products_model'),
      'price' => (float)$Qproduct->valueDecimal('products_price'),
      'quantity' => (int)$Qproduct->valueInt('products_quantity'),
      'status' => (int)$Qproduct->valueInt('products_status'),
      'image' => $Qproduct->value('products_image'),
      'weight' => (float)$Qproduct->valueDecimal('products_weight'),
      'tax_class_id' => (int)$Qproduct->valueInt('products_tax_class_id'),
      'date_added' => $Qproduct->value('products_date_added'),
      'last_modified' => $Qproduct->value('products_last_modified')
    ];

    // Get categories for this product
    $Qcategories = $this->db->prepare("SELECT c.categories_id,
                                           cd.categories_name
                                     FROM :table_products_to_categories ptc
                                     LEFT JOIN :table_categories c ON ptc.categories_id = c.categories_id
                                     LEFT JOIN :table_categories_description cd ON c.categories_id = cd.categories_id
                                     WHERE ptc.products_id = :product_id
                                     AND cd.language_id = :language_id
                                     AND c.virtual_categories = 0
                                     AND c.status = 1
                                    ");

    $Qcategories->bindInt(':product_id', $productId);
    $Qcategories->bindInt(':language_id', (int)$this->lang->getId());
    $Qcategories->execute();

    $categories = [];
    while ($Qcategories->fetch()) {
      $categories[] = [
        'id' => (int)$Qcategories->valueInt('categories_id'),
        'name' => $Qcategories->value('categories_name')
      ];
    }

    $product['categories'] = $categories;

    $this->message->sendSuccess(['product' => $product]);
  }

  /**
   * Retrieves a list of categories.
   *
   * @return void Sends a JSON success response with category data.
   */
  public function getCategories(): void
  {
    $parentId = filter_var(HTML::sanitize($_GET['parent_id']) ?? HTML::sanitize($_POST['parent_id']) ?? null, FILTER_VALIDATE_INT);

    $sql = "SELECT c.categories_id,
                   cd.categories_name,
                   c.parent_id,
                   c.sort_order,
                   c.status,
                   c.categories_image
            FROM :table_categories c
            LEFT JOIN :table_categories_description cd ON c.categories_id = cd.categories_id
            WHERE cd.language_id = :language_id
            AND c.status = 1
            AND c.virtual_categories = 0
            ";

    $params = [':language_id' => (int)$this->lang->getId()];

    if ($parentId !== false) {
      $sql .= " AND c.parent_id = :parent_id";
      $params[':parent_id'] = $parentId;
    }

    $sql .= " ORDER BY c.sort_order, cd.categories_name";

    $Qcategories = $this->db->prepare($sql);

    foreach ($params as $key => $value) {
      $Qcategories->bindValue($key, $value);
    }
    $Qcategories->execute();

    $categories = [];
    while ($Qcategories->fetch()) {
      $categories[] = [
        'id' => (int)$Qcategories->valueInt('categories_id'),
        'name' => $Qcategories->value('categories_name'),
        'parent_id' => (int)$Qcategories->valueInt('parent_id'),
        'sort_order' => (int)$Qcategories->valueInt('sort_order'),
        'status' => (int)$Qcategories->valueInt('categories_status'),
        'image' => $Qcategories->value('categories_image')
      ];
    }

    $this->message->sendSuccess(['categories' => $categories]);
  }

  /**
   * Handles a GET request for a product search.
   * This method serves as an entry point for simplified, direct search queries.
   *
   * @return void Sends a JSON success response with search results or an error.
   */
  public function handleSearchQuery(): void
  {
    $query = HTML::sanitize($_GET['query']) ?? HTML::sanitize($_GET['q']) ?? HTML::sanitize($_POST['query']) ?? HTML::sanitize($_POST['q']) ?? '';

    try {
      if (empty($query)) {
        $this->message->sendError('Search query is required.', 400);
        return;
      }

      // Analyze the user's intent from the query string
      $intent = $this->analyzeProductIntent($query);

      // Execute the product search based on the intent
      $results = $this->executeProductSearch($intent, []);

      // Generate the final response based on the search results
      $response = $this->generateProductResponse($results, $intent);

      $this->message->sendSuccess([
        'response' => $response,
        'type' => 'product_info',
        'confidence' => $intent['confidence'],
        'products' => $results['products'],
        'metadata' => [
          'search_time' => microtime(true),
          'result_count' => count($results['products']),
          'user_mode' => 'client',
          'intent' => $intent['type']
        ]
      ]);

    } catch (\Exception $e) {
      error_log('GET search error: ' . $e->getMessage());
      $this->message->sendError('GET search error: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Analyzes a user's natural language message to determine search intent,
   * entities (like categories or keywords), and filters (like price range).
   * It uses GPT for translation and regex rules for intent detection.
   *
   * @param string $message The natural language message from the user.
   * @return array A structured array representing the detected intent.
   */
  public function analyzeProductIntent(string $message): array
  {
    $languageId = $this->lang->getId();

    // 1. Check translation cache first
    $translated = $this->translationCache->getCachedTranslation($message, $languageId);

    // 2. If not in cache, call GPT and then cache the result
    if (is_null($translated)) {
      $translated = strtolower(Gpt::getGptResponse(
        'Translate the following message into English. Return only the translated text, with no explanations, formatting, or extra content: ' . $message
      ));

      if (!empty($translated)) {
        $this->translationCache->cacheTranslation($message, $translated, $languageId);
      }
    }

    $intent = [
      'type' => 'general_search',
      'confidence' => 0.5,
      'entities' => [],
      'filters' => []
    ];

    $totalWeight = 0;
    $matchWeight = 0;

    foreach ($this->rules as $rule) {
      if (preg_match($rule['regex'], $translated, $matches)) {
        $intent['type'] = $rule['type'];
        $matchWeight += $rule['confidence'];
        $totalWeight += 1;

        if (isset($rule['extract']) && is_callable($rule['extract'])) {
          $intent['filters'] = array_merge($intent['filters'], $rule['extract']($matches));
        }
      }
    }

    // Category detection
    $Qcategories = $this->db->prepare('SELECT cd.categories_name
                                            FROM :table_categories c,
                                                 :table_categories_description cd
                                            WHERE c.categories_id = cd.categories_id
                                            AND cd.language_id = :language_id
                                            AND status = 1
                                        ');
    $Qcategories->bindInt(':language_id', (int)$this->lang->getId());
    $Qcategories->execute();

    $categories_array = $Qcategories->fetchAll();
    foreach ($categories_array as $cat) {
      $catLower = strtolower($cat['categories_name'] ?? '');
      if (strpos($translated, $catLower) !== false) {
        $intent['entities']['category'] = $catLower;
        $matchWeight += 0.8;
        $totalWeight += 1;
        break;
      }
    }

    // Keyword extraction
    $keywords = [];
    // 1) Quoted phrases
    if (preg_match_all('/"([^"]{2,})"/', $translated, $matchesQuoted)) {
      foreach ($matchesQuoted[1] as $phrase) {
        $phrase = trim($phrase);
        if ($phrase !== '') {
          $keywords[] = $phrase;
        }
      }
    }

    // 2) Significant words (length > 2) excluding common stopwords
    $stopwords = [
      'the', 'and', 'for', 'with', 'your', 'our', 'you', 'are', 'not', 'can', 'how', 'what', 'why', 'which', 'have', 'has', 'had', 'from', 'this', 'that', 'these', 'those', 'a', 'an', 'to', 'of', 'in', 'on', 'by', 'at', 'it', 'is', 'as', 'or', 'be', 'do', 'does', 'did', 'me', 'my', 'we', 'us', 'they', 'them', 'their', 'there', 'here', 'about', 'please', 'show', 'find', 'search', 'look', 'list', 'between', 'under', 'over', 'more', 'than', 'less', 'cheaper', 'above', 'below'
    ];

    $tokens = preg_split('/[^a-z0-9]+/i', $translated, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($tokens as $tok) {
      $tokLower = strtolower($tok);
      if (strlen($tokLower) > 2 && !in_array($tokLower, $stopwords, true)) {
        $keywords[] = $tokLower;
      }
    }

    // Keep unique and limited keywords
    $keywords = array_values(array_unique($keywords));
    if (!empty($keywords)) {
      $intent['entities']['keywords'] = array_slice($keywords, 0, 5);
      $matchWeight += 0.8;
      $totalWeight += 1;
    }

    // Final confidence score calculation
    if ($totalWeight > 0) {
      $intent['confidence'] = min(1.0, $matchWeight / $totalWeight);
    }

    return $intent;
  }

  /**
   * Executes a product search based on a structured intent.
   *
   * @param array $intent The structured intent derived from user input.
   * @param mixed $context Additional context for the search.
   * @return array The search results, total count, and applied filters.
   */
  public function executeProductSearch(array $intent, mixed $context): array
  {
    try {
      $sql = "SELECT DISTINCT  p.products_id,
                                pd.products_name,
                                pd.products_description,
                                p.products_model,
                                p.products_price,
                                p.products_quantity,
                                p.products_status,
                                p.products_image,
                                p.products_weight,
                                p.products_tax_class_id,
                                p.products_date_added,
                                p.products_last_modified,
                                p.products_ean,
                                p.products_sku,
                                p.products_mpn,
                                p.products_isbn,
                                p.products_upc,
                                p.products_jan,
                                c.categories_id,
                                cd.categories_name AS category_name
                FROM :table_products p
                INNER JOIN :table_products_to_categories p2c ON p.products_id = p2c.products_id
                INNER JOIN :table_categories c ON p2c.categories_id = c.categories_id
                INNER JOIN :table_categories_description cd ON c.categories_id = cd.categories_id
                INNER JOIN :table_products_description pd ON p.products_id = pd.products_id
                WHERE p.products_status = 1
                  AND p.products_quantity > 0
                  AND p.products_archive = 0
                  AND p.products_view = 1
                  AND p.orders_view = 1
                  AND c.virtual_categories = 0
                  AND c.status = 1
                  AND cd.language_id = :language_id
                  AND pd.language_id = :language_id
        ";

      $params = [':language_id' => (int)$this->lang->getId()];

      // Filter by category
      if (!empty($intent['entities']['category'])) {
        $sql .= " AND cd.categories_name LIKE :cat_name";
        $params[':cat_name'] = '%' . $intent['entities']['category'] . '%';
      }

      // Price filtering
      if (!empty($intent['filters']['price_min'])) {
        $sql .= " AND p.products_price >= :price_min";
        $params[':price_min'] = (float)$intent['filters']['price_min'];
      }
      if (!empty($intent['filters']['price_max'])) {
        $sql .= " AND p.products_price <= :price_max";
        $params[':price_max'] = (float)$intent['filters']['price_max'];
      }

      // Multi-reference filtering (SKU, EAN, UPC, MPN, ISBN)
      if (!empty($intent['filters']['reference'])) {
        $sql .= " AND (p.products_model = :ref
                       OR p.products_sku = :ref
                       OR p.products_ean = :ref
                       OR p.products_upc = :ref
                       OR p.products_mpn = :ref
                       OR p.products_isbn = :ref
                       OR p.products_jan = :ref)
                ";
        $params[':ref'] = $intent['filters']['reference'];
      }

      // Quantity filtering
      if (!empty($intent['filters']['quantity_min'])) {
        $sql .= " AND p.products_quantity >= :qty_min";
        $params[':qty_min'] = (int)$intent['filters']['quantity_min'];
      }

      // Keyword filtering (name / description / model)
      if (!empty($intent['entities']['keywords']) && is_array($intent['entities']['keywords'])) {
        $kIndex = 0;
        $sql .= " AND (";
        $conditions = [];

        foreach ($intent['entities']['keywords'] as $kw) {
          $like = '%' . $kw . '%';
          $nameKey = ':kw_name_' . $kIndex;
          $descKey = ':kw_desc_' . $kIndex;
          $modelKey = ':kw_model_' . $kIndex;

          $conditions[] = " (pd.products_name LIKE $nameKey
                             OR pd.products_description LIKE $descKey
                             OR p.products_model LIKE $modelKey)";

          $params[$nameKey] = $like;
          $params[$descKey] = $like;
          $params[$modelKey] = $like;
          $kIndex++;
        }
        $sql .= implode(' OR ', $conditions) . ")";
      }

      $sql .= " GROUP BY p.products_id ORDER BY pd.products_name DESC LIMIT 20";

      $stmt = $this->db->prepare($sql);

      foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
      }

      $stmt->execute();
      $products = $stmt->fetchAll();

      return [
        'products' => $products,
        'total' => count($products),
        'filters_applied' => $intent['filters']
      ];

    } catch (\Exception $e) {
      error_log('Product search error: ' . $e->getMessage());
      return ['products' => [], 'total' => 0, 'error' => $e->getMessage()];
    }
  }

  /**
   * Generates a formatted text response for a user based on product search results.
   * This is a private helper method used by the chat handler.
   *
   * @param array $results The search results containing product data.
   * @param array $intent The detected user intent and filters.
   * @return string The formatted response message.
   */
  private function generateProductResponse(array $results, array $intent): string
  {
    if (empty($results['products'])) {
      return "🔍 No products found for your search.";
    }

    $response = "🛍️ **I found " . count($results['products']) . " product(s):**\n\n";

    // Display applied filters and detected intent
    if (!empty($intent['filters'])) {
      $response .= "**Applied Filters:** ";
      $filters = [];
      foreach ($intent['filters'] as $key => $value) {
        $filters[] = "$key: $value";
      }
      $response .= implode(", ", $filters) . "\n";
    }
    if (!empty($intent['type'])) {
      $response .= "**Detected Intent:** " . $intent['type'] . "\n\n";
    }

    foreach ($results['products'] as $i => $p) {
      $response .= "**" . ($i + 1) . ". " . ($p['products_name'] ?? '') . "**\n";
      $response .= "💰 Price: " . ($p['products_price'] ?? 'N/A') . "€\n";
      $response .= "📦 Stock: " . ($p['products_quantity'] ?? 'N/A') . " units\n";

      $ean = $p['products_ean'] ?? '';
      $sku = $p['products_sku'] ?? '';
      $mpn = $p['products_mpn'] ?? '';
      $isbn = $p['products_isbn'] ?? '';
      $upc = $p['products_upc'] ?? '';
      $jan = $p['products_jan'] ?? '';
      $weight = $p['products_weight'] ?? '';

      if ($ean || $sku || $mpn || $isbn || $upc || $jan) {
        $response .= "📑 References: ";
        $refs = [];
        if ($ean) $refs[] = "EAN: $ean";
        if ($sku) $refs[] = "SKU: $sku";
        if ($mpn) $refs[] = "MPN: $mpn";
        if ($isbn) $refs[] = "ISBN: $isbn";
        if ($upc) $refs[] = "UPC: $upc";
        if ($jan) $refs[] = "JAN: $jan";
        $response .= implode(", ", $refs) . "\n";
      }

      if ($weight) {
        $response .= "⚖️ Weight: $weight kg\n";
      }

      $response .= !empty($p['category_name']) ? "🏷️ Category: " . $p['category_name'] . "\n" : "";
      $response .= "\n";
    }

    return $response;
  }

  /**
   * Performs a simple product search based on a single query string.
   * This method is intended for basic keyword-based searches.
   *
   * @return void Sends a JSON success response with search results.
   */
  public function searchProducts(): void
  {
    $query = HTML::sanitize($_GET['q']) ?? HTML::sanitize($_POST['q']) ?? '';

    if (empty($query) || strlen($query) < 2) {
      $this->message->sendError('Search query must be at least 2 characters long.', 400);
      return;
    }

    $limit = filter_var(HTML::sanitize($_GET['limit']) ?? HTML::sanitize($_POST['limit']) ?? 20, FILTER_VALIDATE_INT, ['options' => ['default' => 20, 'min_range' => 1, 'max_range' => 100]]);
    $offset = filter_var(HTML::sanitize($_GET['offset']) ?? HTML::sanitize($_POST['offset']) ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);

    $Qsearch = $this->db->prepare("SELECT p.products_id,
                                          pd.products_name,
                                          pd.products_description,
                                          p.products_model,
                                          p.products_price,
                                          p.products_quantity,
                                          p.products_status,
                                          p.products_image,
                                          p.products_weight
                                   FROM :table_products p
                                   LEFT JOIN :table_products_description pd ON p.products_id = pd.products_id
                                   WHERE pd.language_id = :language_id
                                   AND p.products_status = 1
                                   AND (pd.products_name LIKE :query
                                        OR pd.products_description LIKE :query
                                        OR p.products_model LIKE :query)
                                   AND p.products_quantity > 0
                                   AND p.products_archive = 0
                                   AND p.products_view = 1
                                   AND p.orders_view = 1
                                   ORDER BY pd.products_name
                                   LIMIT :limit OFFSET :offset");

    $searchQuery = '%' . $query . '%';

    $Qsearch->bindInt(':language_id', (int)$this->lang->getId());
    $Qsearch->bindValue(':query', $searchQuery);
    $Qsearch->bindInt(':limit', $limit);
    $Qsearch->bindInt(':offset', $offset);
    $Qsearch->execute();

    $products = [];
    while ($Qsearch->fetch()) {
      $products[] = [
        'id' => (int)$Qsearch->valueInt('products_id'),
        'name' => $Qsearch->value('products_name'),
        'description' => strip_tags($Qsearch->value('products_description')),
        'model' => $Qsearch->value('products_model'),
        'price' => (float)$Qsearch->valueDecimal('products_price'),
        'quantity' => (int)$Qsearch->valueInt('products_quantity'),
        'image' => $Qsearch->value('products_image'),
        'weight' => (float)$Qsearch->valueDecimal('products_weight')
      ];
    }

    $this->message->sendSuccess([
      'products' => $products,
      'query' => $query,
      'pagination' => [
        'limit' => $limit,
        'offset' => $offset,
        'count' => count($products)
      ]
    ]);
  }

  /**
   * Retrieves key statistics about the product catalog.
   *
   * @return void Sends a JSON success response with product statistics.
   */
  public function getProductStats(): void
  {
    // Total products
    $Qtotal = $this->db->prepare("SELECT COUNT(*) as total FROM :table_products WHERE products_status = 1");
    $Qtotal->execute();
    $totalProducts = $Qtotal->valueInt('total');

    // Products in stock
    $QinStock = $this->db->prepare("SELECT COUNT(*) as in_stock FROM :table_products WHERE products_status = 1 AND products_quantity > 0");
    $QinStock->execute();
    $inStock = $QinStock->valueInt('in_stock');

    // Average price
    $Qavg = $this->db->prepare("SELECT AVG(products_price) as avg_price FROM :table_products WHERE products_status = 1 AND products_price > 0");
    $Qavg->execute();
    $avgPrice = $Qavg->valueDecimal('avg_price');

    // Price range
    $Qrange = $this->db->prepare("SELECT MIN(products_price) as min_price, MAX(products_price) as max_price FROM :table_products WHERE products_status = 1 AND products_price > 0");
    $Qrange->execute();
    $minPrice = $Qrange->valueDecimal('min_price');
    $maxPrice = $Qrange->valueDecimal('max_price');

    $stats = [
      'total_products' => $totalProducts,
      'in_stock_products' => $inStock,
      'average_price' => (float)number_format($avgPrice, 2, '.', ''),
      'min_price' => (float)number_format($minPrice, 2, '.', ''),
      'max_price' => (float)number_format($maxPrice, 2, '.', '')
    ];

    $this->message->sendSuccess(['stats' => $stats]);
  }

  /**
   * Retrieves product recommendations based on various criteria.
   *
   * @return void Sends a JSON success response with recommended products.
   */
  public function getProductRecommendations(): void
  {
    $limit = filter_var(HTML::sanitize($_GET['limit']) ?? HTML::sanitize($_POST['limit']) ?? 10, FILTER_VALIDATE_INT, ['options' => ['default' => 10, 'min_range' => 1, 'max_range' => 50]]);
    $categoryId = filter_var(HTML::sanitize($_GET['category_id']) ?? HTML::sanitize($_POST['category_id']) ?? null, FILTER_VALIDATE_INT);
    $priceMax = filter_var(HTML::sanitize($_GET['price_max']) ?? HTML::sanitize($_POST['price_max']) ?? null, FILTER_VALIDATE_FLOAT);

    $sql = "SELECT p.products_id,
                   pd.products_name,
                   pd.products_description,
                   p.products_model,
                   p.products_price,
                   p.products_quantity,
                   p.products_status,
                   p.products_image,
                   p.products_weight,
                   p.products_date_added,
                   c.categories_id,
                   cd.categories_name AS category_name
            FROM :table_products p
            LEFT JOIN :table_products_description pd ON p.products_id = pd.products_id
            LEFT JOIN :table_products_to_categories p2c ON p.products_id = p2c.products_id
            LEFT JOIN :table_categories c ON p2c.categories_id = c.categories_id
            LEFT JOIN :table_categories_description cd ON c.categories_id = cd.categories_id
            WHERE pd.language_id = :language_id
            AND p.products_status = 1
            AND p.products_quantity > 0
            AND p.products_archive = 0
            AND p.products_view = 1
            AND p.orders_view = 1
            AND c.virtual_categories = 0
            AND c.status = 1
            AND cd.language_id = :language_id";

    $params = [':language_id' => (int)$this->lang->getId()];

    if ($categoryId !== false) {
      $sql .= " AND c.categories_id = :category_id";
      $params[':category_id'] = $categoryId;
    }

    if ($priceMax !== false) {
      $sql .= " AND p.products_price <= :price_max";
      $params[':price_max'] = $priceMax;
    }

    // Order by most recent and most popular (based on date added and price)
    $sql .= " ORDER BY p.products_date_added DESC, p.products_price ASC LIMIT :limit";

    $Qrecommendations = $this->db->prepare($sql);
    foreach ($params as $key => $value) {
      $Qrecommendations->bindValue($key, $value);
    }
    $Qrecommendations->bindInt(':limit', $limit);
    $Qrecommendations->execute();

    $recommendations = [];
    while ($Qrecommendations->fetch()) {
      $recommendations[] = [
        'id' => (int)$Qrecommendations->valueInt('products_id'),
        'name' => $Qrecommendations->value('products_name'),
        'description' => strip_tags($Qrecommendations->value('products_description')),
        'model' => $Qrecommendations->value('products_model'),
        'price' => (float)$Qrecommendations->valueDecimal('products_price'),
        'quantity' => (int)$Qrecommendations->valueInt('products_quantity'),
        'image' => $Qrecommendations->value('products_image'),
        'weight' => (float)$Qrecommendations->valueDecimal('products_weight'),
        'category_id' => (int)$Qrecommendations->valueInt('categories_id'),
        'category_name' => $Qrecommendations->value('category_name'),
        'date_added' => $Qrecommendations->value('products_date_added')
      ];
    }

    $this->message->sendSuccess([
      'recommendations' => $recommendations,
      'criteria' => [
        'limit' => $limit,
        'category_id' => $categoryId,
        'price_max' => $priceMax
      ],
      'count' => count($recommendations)
    ]);
  }


}