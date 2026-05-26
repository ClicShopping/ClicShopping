<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\UCP;

use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\Common\GptOrderManager;
use ClicShopping\Apps\Catalog\Manufacturers\Classes\ClicShoppingAdmin\ManufacturerAdmin;
use ClicShopping\Apps\Catalog\Products\Classes\Shop\ProductsCommon;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt;
use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\UCP\Sub\PaymentProcessor;
use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\SimpleLogger;
use ClicShopping\Sites\Shop\Address;

/**
 * GptRetailersUCP class.
 *
 * This class acts as the entry point for UCP (Universal Commerce Protocol)
 * business logic in the Shop context.
 */
class GptRetailersUCP
{
  /**
   * @var ChatGpt The main ChatGpt application object.
   */
  protected ChatGpt $app;

  /**
   * @var object The language object from the Registry.
   */
  protected object $lang;

  /**
   * @var object The database connection object from the Registry.
   */
  protected object $db;

  /**
   * @var SimpleLogger The simple logger instance for logging events and errors.
   */
  protected SimpleLogger $logger;

  /**
   * @var GptOrderManager Manages the creation and handling of shop orders.
   */
  protected GptOrderManager $orderManager;

  /**
   * @var GptSessionManager Manages UCP checkout sessions.
   */
  protected GptSessionManager $sessionManager;
  protected PaymentProcessor $paymentProcessor;

  /**
   * GptRetailersUCP constructor.
   * Initializes dependencies and checks application status.
   */
  public function __construct()
  {
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGpt());
    }

    $this->app = Registry::get('ChatGpt');
    $this->lang = Registry::get('Language');
    $this->db = Registry::get('Db');

    if (!Registry::exists('SimpleLogger')) {
      $this->logger = new SimpleLogger('UCP_ClicShopping');
    } else {
      $this->logger = Registry::get('SimpleLogger');
    }

    $this->orderManager = new GptOrderManager();
    $this->sessionManager = new GptSessionManager();
    $this->paymentProcessor = new PaymentProcessor();

    $this->checkStatus();
  }

  /**
   * Checks if the UCP application status is enabled and configured.
   *
   * @return bool True if the application is enabled and the shared key is set, false otherwise.
   */
  private function checkStatus() :bool
  {
    $result = true;

    if (\defined('CLICSHOPPING_APP_ECOMMERCE_UCP_STATUS') && CLICSHOPPING_APP_ECOMMERCE_UCP_STATUS == 'False') {
      $result = false;
    }

    if (\defined('CLICSHOPPING_APP_ECOMMERCE_UCP_API_KEY_GOOGLE_RETAIL') && empty(CLICSHOPPING_APP_ECOMMERCE_UCP_API_KEY_GOOGLE_RETAIL)) {
      $result = false;
    }

    return $result;
  }

  /**
   * Validate basic UCP input for session creation/update.
   *
   * @param array $input
   * @param bool $partial
   * @return array
   */
  public function validateUcpInput(array $input, bool $partial = false): array
  {
    $messages = [];

    if (!$partial || array_key_exists('items', $input)) {
      $items = $input['items'] ?? [];
      $messages = array_merge($messages, $this->validateItemsInput($items, '$.items'));
    }

    if (!$partial || array_key_exists('fulfillment_address', $input)) {
      $address = $input['fulfillment_address'] ?? [];
      if (!empty($address)) {
        $messages = array_merge($messages, $this->validateAddress($address, '$.fulfillment_address'));
      } elseif (!$partial) {
        $messages[] = $this->buildFieldError('missing', '$.fulfillment_address', 'Fulfillment address is required.');
      }
    }

    if (!$partial || array_key_exists('consumer', $input)) {
      $consumer = $input['consumer'] ?? [];
      $messages = array_merge($messages, $this->validateConsumer($consumer, '$.consumer'));
    }

    return $messages;
  }

  /**
   * Validate items input payload.
   *
   * @param array $items
   * @param string $path
   * @return array
   */
  private function validateItemsInput(array $items, string $path): array
  {
    $messages = [];
    $index = 0;

    foreach ($items as $item) {
      $itemPath = $path . '[' . $index . ']';
      if (empty($item['id'])) {
        $messages[] = $this->buildFieldError('missing', $itemPath . '.id', 'Item id is required.');
      }
      if (!isset($item['quantity'])) {
        $messages[] = $this->buildFieldError('missing', $itemPath . '.quantity', 'Item quantity is required.');
      } elseif (!is_numeric($item['quantity']) || (int)$item['quantity'] <= 0) {
        $messages[] = $this->buildFieldError('invalid', $itemPath . '.quantity', 'Item quantity must be a positive integer.');
      }
      $index++;
    }

    return $messages;
  }

  /**
   * Build standardized field error entry.
   *
   * @param string $code
   * @param string $field
   * @param string $message
   * @return array
   */
  private function buildFieldError(string $code, string $field, string $message): array
  {
    return [
      'code' => $code,
      'field' => $field,
      'message' => $message
    ];
  }

  /**
   * Validate address fields.
   *
   * @param array $address
   * @param string $path
   * @return array
   */
  private function validateAddress(array $address, string $path): array
  {
    $messages = [];
    $required = ['name','line_one','city','postal_code','country'];

    foreach ($required as $field) {
      if (empty($address[$field])) {
        $messages[] = $this->buildFieldError('missing', $path . '.' . $field, 'Address field is required.');
      }
    }

    if (!empty($address['country']) && !preg_match('/^[A-Z]{2,3}$/', (string)$address['country'])) {
      $messages[] = $this->buildFieldError('invalid', $path . '.country', 'Country must be ISO 3166-1 alpha-2 or alpha-3.');
    }

    if (!empty($address['phone_number']) && !preg_match('/^\\+[1-9]\\d{1,14}$/', (string)$address['phone_number'])) {
      $messages[] = $this->buildFieldError('invalid', $path . '.phone_number', 'Phone number must follow E.164.');
    }

    return $messages;
  }

  /**
   * Validate consumer fields (email, phone).
   *
   * @param array $consumer
   * @param string $path
   * @return array
   */
  private function validateConsumer(array $consumer, string $path): array
  {
    $messages = [];

    if (!empty($consumer['email']) && !filter_var($consumer['email'], FILTER_VALIDATE_EMAIL)) {
      $messages[] = $this->buildFieldError('invalid', $path . '.email', 'Email format is invalid.');
    }

    if (!empty($consumer['phone_number']) && !preg_match('/^\\+[1-9]\\d{1,14}$/', (string)$consumer['phone_number'])) {
      $messages[] = $this->buildFieldError('invalid', $path . '.phone_number', 'Phone number must follow E.164.');
    }

    return $messages;
  }

  /**
   * Returns the UCP product feed.
   *
   * @param array $filters
   * @return array
   */
  public function getProducts(array $filters = []): array
  {
    $cacheKey = $this->buildProductCacheKey($filters);
    $cache = new Cache($cacheKey, 'UCP');


    $cacheTtlMinutes = 5;
    if ($cache->exists($cacheTtlMinutes)) {
      $cached = $cache->get();
      if (is_array($cached)) {
        return $cached;
      }
    }

    if (!Registry::exists('ProductsCommon')) {
      Registry::set('ProductsCommon', new ProductsCommon());
    }

    $productsCommon = Registry::get('ProductsCommon');
    $result = $this->fetchProductsUsingClasses($filters);

    $products = [];
    foreach ($result['rows'] as $row) {
      $products[] = $this->formatProductForUCP($row, $productsCommon);
    }

    $payload = [
      'products' => $products,
      'pagination' => $result['pagination']
    ];

    $cache->save($payload, ['ttl_minutes' => $cacheTtlMinutes]);

    return $payload;
  }

  /**
   * Build cache key for product feed.
   *
   * @param array $filters
   * @return string
   */
  private function buildProductCacheKey(array $filters): string
  {
    $keyData = [
      'page' => $filters['page'] ?? 1,
      'limit' => $filters['limit'] ?? 100,
      'category' => $filters['category'] ?? '',
      'min_price' => $filters['min_price'] ?? '',
      'max_price' => $filters['max_price'] ?? '',
      'in_stock' => $filters['in_stock'] ?? ''
    ];

    return 'products_' . md5(json_encode($keyData));
  }

  /**
   * Retrieve product rows using ProductsCommon as the primary accessor.
   *
   * @param array $filters
   * @return array
   */
  private function fetchProductsUsingClasses(array $filters): array
  {
    $languageId = $this->lang->getId();
    $page = (int)($filters['page'] ?? 1);
    $limit = (int)($filters['limit'] ?? 100);
    $page = $page < 1 ? 1 : $page;
    $limit = $limit < 1 ? 100 : $limit;
    $offset = ($page - 1) * $limit;

    $where = [
      'p.products_status = 1',
      'p.products_view = 1',
      'p.products_archive = 0'
    ];
    $params = [
      ':language_id' => $languageId
    ];

    if (!empty($filters['in_stock'])) {
      $where[] = 'p.products_quantity > 0';
    }

    if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
      $where[] = 'p.products_price >= :min_price';
      $params[':min_price'] = (float)$filters['min_price'];
    }

    if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
      $where[] = 'p.products_price <= :max_price';
      $params[':max_price'] = (float)$filters['max_price'];
    }

    if (!empty($filters['category'])) {
      if (is_numeric($filters['category'])) {
        $where[] = 'pc.categories_id = :category_id';
        $params[':category_id'] = (int)$filters['category'];
      } else {
        $where[] = 'cd.categories_name like :category_name';
        $params[':category_name'] = '%' . $filters['category'] . '%';
      }
    }

    $whereSql = implode(' and ', $where);

    $countSql = 'select count(distinct p.products_id) as total
                   from :table_products p
                   left join :table_products_to_categories pc on p.products_id = pc.products_id
                   left join :table_categories c on pc.categories_id = c.categories_id
                   left join :table_categories_description cd on pc.categories_id = cd.categories_id and cd.language_id = :language_id
                  where ' . $whereSql . ' and (c.status = 1 or c.status is null)';

    $Qcount = $this->db->prepare($countSql);
    foreach ($params as $key => $value) {
      if (is_int($value)) {
        $Qcount->bindInt($key, $value);
      } else {
        $Qcount->bindValue($key, $value);
      }
    }
    $Qcount->execute();
    $total = (int)$Qcount->valueInt('total');

    $sql = 'select p.products_id,
                   p.products_quantity,
                   p.products_weight,
                   p.products_dimension_depth,
                   p.products_dimension_width,
                   p.products_dimension_height,
                   p.products_sku,
                   p.products_model,
                   p.parent_id,
                   p.products_image_small,
                   p.products_image_medium,
                   p.products_image_zoom,
                   group_concat(distinct cd.categories_name) as categories,
                   group_concat(distinct pc.categories_id) as categories_id
              from :table_products p
              left join :table_products_to_categories pc on p.products_id = pc.products_id
              left join :table_categories c on pc.categories_id = c.categories_id
              left join :table_categories_description cd on pc.categories_id = cd.categories_id and cd.language_id = :language_id
             where ' . $whereSql . ' and (c.status = 1 or c.status is null)
             group by p.products_id
             order by p.products_id desc
             limit :limit offset :offset';

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

    $rows = [];
    while ($row = $Qproducts->fetch()) {
      $rows[] = $row;
    }

    return [
      'rows' => $rows,
      'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'has_more' => ($page * $limit) < $total
      ]
    ];
  }

  /**
   * Build UCP-formatted product payload from raw product row.
   *
   * @param array $product
   * @param ProductsCommon $productsCommon
   * @return array
   */
  private function formatProductForUCP(array $product, ProductsCommon $productsCommon): array
  {
    $productId = (int)($product['products_id'] ?? 0);
    $currencyCode = \defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'EUR';
    $brandName = ManufacturerAdmin::getManufacturerName($productId);
    $special = $this->getSpecial($productId);

    $imageSmall = $product['products_image_small'] ?? '';
    $imageMedium = $product['products_image_medium'] ?? '';
    $imageZoom = $product['products_image_zoom'] ?? '';
    $images = array_filter([
      $this->buildAbsoluteUrl($imageSmall),
      $this->buildAbsoluteUrl($imageMedium),
      $this->buildAbsoluteUrl($imageZoom)
    ]);

    $categories = [];
    if (!empty($product['categories'])) {
      $categories = array_map('trim', explode(',', $product['categories']));
    }

    $variants = $this->getProductVariants($productId);

    return [
      'id' => (string)$productId,
      'title' => $productsCommon->getProductsName($productId),
      'description' => $productsCommon->getProductsDescription($productId),
      'currency' => strtolower($currencyCode),
      'price' => (float)$productsCommon->getDisplayPriceGroupWithoutCurrencies($productId),
      'images' => array_values($images),
      'in_stock' => ((int)($product['products_quantity'] ?? 0) > 0),
      'available_quantity' => (int)($product['products_quantity'] ?? 0),
      'categories' => $categories,
      'tags' => [],
      'shipping' => [
        'weight_kg' => (float)($product['products_weight'] ?? 0),
        'dimensions_cm' => [
          'length' => (float)($product['products_dimension_depth'] ?? 0),
          'width' => (float)($product['products_dimension_width'] ?? 0),
          'height' => (float)($product['products_dimension_height'] ?? 0)
        ],
        'methods' => ['standard', 'express']
      ],
      'metadata' => [
        'brand' => $brandName ?: (\defined('STORE_NAME') ? STORE_NAME : 'ClicShopping'),
        'sku' => $product['products_sku'] ?? $productsCommon->getProductsSKU($productId),
        'model' => $product['products_model'] ?? null,
        'promotion' => [
          'active' => !empty($special),
          'discount_price' => $special['specials_new_products_price'] ?? null
        ]
      ],
      'variants' => $variants
    ];
  }

  /**
   * Retrieves data for "special" (sale) products.
   *
   * @param int $id
   * @return array
   */
  private function getSpecial(int $id): array
  {
    $Qspecials = $this->db->prepare('select specials_id,
                                             scheduled_date,
                                             expires_date,
                                             specials_new_products_price,
                                             flash_discount
                                        from :table_specials
                                       where status = 1
                                         and products_id = :products_id
                                     ');
    $Qspecials->bindInt(':products_id', $id);
    $Qspecials->execute();

    if (!empty($Qspecials->valueInt('specials_id'))) {
      return [
        'scheduled_date' => $Qspecials->value('scheduled_date'),
        'expires_date' => $Qspecials->value('expires_date'),
        'specials_new_products_price' => $Qspecials->value('specials_new_products_price'),
        'flash_discount' => $Qspecials->value('flash_discount')
      ];
    }

    return [];
  }

  /**
   * Build absolute URL for shop assets and pages.
   *
   * @param string $path
   * @return string
   */
  private function buildAbsoluteUrl(string $path): string
  {
    if ($path === '') {
      return '';
    }

    if (preg_match('#^https?://#i', $path)) {
      return $path;
    }

    $baseUrl = $this->getBaseShopUrl();
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
  }

  /**
   * Returns the base shop URL.
   *
   * @return string
   */
  private function getBaseShopUrl(): string
  {
    return CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop');
  }

  /**
   * Returns variants for a product when available.
   *
   * @param int $productId
   * @return array
   */
  private function getProductVariants(int $productId): array
  {
    $variants = [];
    $languageId = $this->lang->getId();

    $Qvariants = $this->db->prepare('select products_id,
                                            products_sku,
                                            products_model,
                                            products_quantity
                                       from :table_products
                                      where parent_id = :parent_id
                                        and products_status = 1');
    $Qvariants->bindInt(':parent_id', $productId);
    $Qvariants->execute();

    while ($variant = $Qvariants->fetch()) {
      $variantId = (int)$variant['products_id'];
      $attributes = [];

      $Qattributes = $this->db->prepare('select po.products_options_name,
                                                pov.products_options_values_name
                                           from :table_products_attributes pa
                                           join :table_products_options po on pa.options_id = po.products_options_id
                                           join :table_products_options_values pov on pa.options_values_id = pov.products_options_values_id
                                          where pa.products_id = :products_id
                                            and po.language_id = :language_id
                                            and pov.language_id = :language_id');
      $Qattributes->bindInt(':products_id', $variantId);
      $Qattributes->bindInt(':language_id', $languageId);
      $Qattributes->execute();

      while ($attr = $Qattributes->fetch()) {
        $attributes[] = [
          'name' => $attr['products_options_name'],
          'value' => $attr['products_options_values_name']
        ];
      }

      $variants[] = [
        'id' => (string)$variantId,
        'sku' => $variant['products_sku'] ?? null,
        'model' => $variant['products_model'] ?? null,
        'available_quantity' => (int)($variant['products_quantity'] ?? 0),
        'attributes' => $attributes
      ];
    }

    return $variants;
  }

  /**
   * Create a new UCP checkout session.
   *
   * @param array $input
   * @return array
   */
  public function createSession(array $input): array
  {
    if (!Registry::exists('ProductsCommon')) {
      Registry::set('ProductsCommon', new ProductsCommon());
    }

    $productsCommon = Registry::get('ProductsCommon');
    $taxRate = 0.08;
    $currencyCode = \defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'EUR';

    $items = $input['items'] ?? [];
    $consumer = $input['consumer'] ?? [];
    $fulfillmentAddress = $input['fulfillment_address'] ?? [];
    $consent = $input['consent'] ?? ($consumer['consent'] ?? null);

    $normalizedAddress = !empty($fulfillmentAddress) ? $this->normalizeAddress($fulfillmentAddress) : [];

    $hydratedItems = [];
    foreach ($items as $item) {
      if (empty($item['id'])) {
        continue;
      }
      $productId = (int)$item['id'];
      $price = (float)$productsCommon->getDisplayPriceGroupWithoutCurrencies($productId);
      $title = $productsCommon->getProductsName($productId);
      $model = $productsCommon->getProductsModel($productId);
      $hydratedItems[] = [
        'id' => (string)$productId,
        'title' => $title,
        'metadata' => [
          'model' => $model
        ],
        'quantity' => (int)($item['quantity'] ?? 1),
        'price' => $price,
        'unit_price' => $price
      ];
    }

    $lineItems = $this->buildLineItems($hydratedItems, $taxRate);
    $shippingSubtotal = 0;
    $shippingTax = (int)round($shippingSubtotal * $taxRate);
    $fulfillmentOptions = $this->buildFulfillmentOptions($shippingSubtotal, $shippingTax, $normalizedAddress);

    $session = [
      'id' => '',
      'status' => 'not_ready_for_payment',
      'currency' => strtolower($currencyCode),
      'line_items' => $lineItems,
      'items' => $hydratedItems,
      'consumer' => $consumer,
      'fulfillment_address' => $normalizedAddress,
      'fulfillment_options' => $fulfillmentOptions,
      'fulfillment_option_id' => $fulfillmentOptions[0]['id'] ?? null,
      'totals' => $this->buildTotals($lineItems, $shippingSubtotal, $shippingTax),
      'messages' => [],
      'links' => [
        'checkout_url' => $this->getBaseShopUrl(),
        'terms_url' => $this->getBaseShopUrl() . 'index.php?Info&Content&pagesId=4'
      ],
      'payment_provider' => [
        'provider' => 'stripe',
        'supported_payment_methods' => ['card']
      ],
      'consent' => $consent
    ];

    if (!empty($consumer['email'])) {
      $customerId = $this->getCustomerIdByEmail($consumer['email']);
      if ($customerId !== null) {
        $session['customer_id'] = $customerId;
        $session['saved_addresses'] = $this->getSavedAddresses($customerId);
      }
    }

    $session['status'] = $this->determineSessionStatus($session);
    $sessionId = $this->sessionManager->create($session);
    $session['id'] = $sessionId;
    $this->sessionManager->update($sessionId, $session);

    return $session;
  }

  /**
   * Normalize address input into UCP format.
   *
   * @param array $address
   * @return array
   */
  private function normalizeAddress(array $address): array
  {
    $normalized = [
      'name' => $address['name'] ?? ($address['full_name'] ?? ''),
      'line_one' => $address['line_one'] ?? ($address['street_address'] ?? ''),
      'line_two' => $address['line_two'] ?? ($address['suburb'] ?? ''),
      'city' => $address['city'] ?? '',
      'state' => $address['state'] ?? ($address['province'] ?? ''),
      'postal_code' => $address['postal_code'] ?? ($address['postcode'] ?? ''),
      'country' => $address['country'] ?? ($address['country_code'] ?? ''),
      'phone_number' => $address['phone_number'] ?? ($address['phone'] ?? '')
    ];

    if (!empty($normalized['country'])) {
      $normalized['country'] = strtoupper($normalized['country']);
    }

    return $normalized;
  }

  /**
   * Build UCP line items from input items.
   *
   * @param array $items
   * @param float $taxRate
   * @return array
   */
  private function buildLineItems(array $items, float $taxRate): array
  {
    $lineItems = [];

    foreach ($items as $item) {
      $quantity = (int)($item['quantity'] ?? 1);
      $unitPrice = (float)($item['unit_price'] ?? $item['price'] ?? 0);
      $baseAmount = $this->toMinorUnits($unitPrice * $quantity);
      $discount = 0;
      $subtotal = $baseAmount - $discount;
      $tax = (int)round($subtotal * $taxRate);
      $total = $subtotal + $tax;

      $lineItems[] = [
        'id' => uniqid('li_'),
        'item' => [
          'id' => (string)($item['id'] ?? ''),
          'quantity' => $quantity
        ],
        'base_amount' => $baseAmount,
        'discount' => $discount,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total
      ];
    }

    return $lineItems;
  }

  /**
   * Convert major currency amount to minor units.
   *
   * @param float $amount
   * @return int
   */
  private function toMinorUnits(float $amount): int
  {
    return (int)round($amount * 100);
  }

  /**
   * Build fulfillment options.
   *
   * @param int $shippingSubtotal
   * @param int $shippingTax
   * @return array
   */
  private function buildFulfillmentOptions(int $shippingSubtotal, int $shippingTax, array $deliveryAddress = []): array
  {
    $earliest = \defined('CLICSHOPPING_APP_ECOMMERCE_UCP_DELIVERY_EARLIEST') ? CLICSHOPPING_APP_ECOMMERCE_UCP_DELIVERY_EARLIEST : '+3 days';
    $latest = \defined('CLICSHOPPING_APP_ECOMMERCE_UCP_DELIVERY_LATEST') ? CLICSHOPPING_APP_ECOMMERCE_UCP_DELIVERY_LATEST : '+5 days';
    $defaultDelivery = \defined('CLICSHOPPING_APP_ECOMMERCE_UCP_DEFAULT_DELIVERY') ? CLICSHOPPING_APP_ECOMMERCE_UCP_DEFAULT_DELIVERY : '3-5 business days';
    $options = [];

    if (\defined('MODULE_SHIPPING_INSTALLED') && !empty(MODULE_SHIPPING_INSTALLED)) {
      try {
        if (!Registry::exists('Shipping')) {
          Registry::set('Shipping', new \ClicShopping\Sites\Shop\Shipping());
        }

        $shipping = Registry::get('Shipping');
        $quotes = $shipping->getQuote();

        foreach ($quotes as $quote) {
          $methods = $quote['methods'] ?? [];
          foreach ($methods as $method) {
            $cost = (float)($method['cost'] ?? 0);
            $subtotal = $this->toMinorUnits($cost);
            $taxRate = (float)($quote['tax'] ?? 0);
            $tax = $taxRate > 0 ? (int)round($subtotal * ($taxRate / 100)) : 0;
            $total = $subtotal + $tax;
            $moduleId = $quote['id'] ?? 'shipping';
            $methodId = $method['id'] ?? 'standard';

            $options[] = [
              'type' => 'shipping',
              'id' => str_replace('\\', '_', $moduleId . '_' . $methodId),
              'title' => $method['title'] ?? ($quote['module'] ?? 'Shipping'),
              'subtitle' => $defaultDelivery,
              'carrier' => $quote['module'] ?? 'Shipping',
              'earliest_delivery_time' => $this->toRfc3339($earliest),
              'latest_delivery_time' => $this->toRfc3339($latest),
              'subtotal' => $subtotal,
              'tax' => $tax,
              'total' => $total
            ];
          }
        }
      } catch (\Throwable $e) {
        $this->logger->error('UCP fulfillment options error', ['error' => $e->getMessage()]);
      }
    }

    if (empty($options)) {
      $total = $shippingSubtotal + $shippingTax;
      $options[] = [
        'type' => 'shipping',
        'id' => 'shipping_standard',
        'title' => 'Standard',
        'subtitle' => $defaultDelivery,
        'carrier' => 'Standard',
        'earliest_delivery_time' => $this->toRfc3339($earliest),
        'latest_delivery_time' => $this->toRfc3339($latest),
        'subtotal' => $shippingSubtotal,
        'tax' => $shippingTax,
        'total' => $total
      ];
    }

    return $options;
  }

  /**
   * Returns RFC3339 date from relative time string.
   *
   * @param string $time
   * @return string
   */
  private function toRfc3339(string $time): string
  {
    return gmdate('c', strtotime($time));
  }

  /**
   * Build totals array for UCP checkout session.
   *
   * @param array $lineItems
   * @param int $fulfillmentSubtotal
   * @param int $fulfillmentTax
   * @return array
   */
  private function buildTotals(array $lineItems, int $fulfillmentSubtotal, int $fulfillmentTax): array
  {
    $itemsBaseAmount = 0;
    $itemsDiscount = 0;
    $itemsTax = 0;

    foreach ($lineItems as $lineItem) {
      $itemsBaseAmount += (int)($lineItem['base_amount'] ?? 0);
      $itemsDiscount += (int)($lineItem['discount'] ?? 0);
      $itemsTax += (int)($lineItem['tax'] ?? 0);
    }

    $subtotal = $itemsBaseAmount - $itemsDiscount;
    $discount = 0;
    $tax = $itemsTax + $fulfillmentTax;
    $fee = 0;
    $total = $itemsBaseAmount - $itemsDiscount - $discount + $fulfillmentSubtotal + $tax + $fee;

    $totals = [];

    $totals[] = [
      'type' => 'items_base_amount',
      'display_text' => 'Item(s) total',
      'amount' => $itemsBaseAmount
    ];

    if ($itemsDiscount > 0) {
      $totals[] = [
        'type' => 'items_discount',
        'display_text' => 'Items discount',
        'amount' => $itemsDiscount
      ];
    }

    $totals[] = [
      'type' => 'subtotal',
      'display_text' => 'Subtotal',
      'amount' => $subtotal
    ];

    if ($discount > 0) {
      $totals[] = [
        'type' => 'discount',
        'display_text' => 'Discount',
        'amount' => $discount
      ];
    }

    $totals[] = [
      'type' => 'fulfillment',
      'display_text' => 'Fulfillment',
      'amount' => $fulfillmentSubtotal
    ];

    $totals[] = [
      'type' => 'tax',
      'display_text' => 'Tax',
      'amount' => $tax
    ];

    if ($fee > 0) {
      $totals[] = [
        'type' => 'fee',
        'display_text' => 'Fees',
        'amount' => $fee
      ];
    }

    $totals[] = [
      'type' => 'total',
      'display_text' => 'Total',
      'amount' => $total
    ];

    return $totals;
  }

  /**
   * Lookup customer id by email.
   *
   * @param string $email
   * @return int|null
   */
  private function getCustomerIdByEmail(string $email): ?int
  {
    $Qcustomer = $this->db->prepare('select customers_id from :table_customers where customers_email_address = :email');
    $Qcustomer->bindValue(':email', $email);
    $Qcustomer->execute();

    if ($Qcustomer->fetch()) {
      return $Qcustomer->valueInt('customers_id');
    }

    return null;
  }

  /**
   * Retrieve saved addresses for a customer in UCP format.
   *
   * @param int $customerId
   * @return array
   */
  private function getSavedAddresses(int $customerId): array
  {
    $addresses = [];

    $Qaddress = $this->db->prepare('select entry_firstname,
                                           entry_lastname,
                                           entry_company,
                                           entry_street_address,
                                           entry_suburb,
                                           entry_city,
                                           entry_state,
                                           entry_postcode,
                                           entry_country_id,
                                           entry_telephone
                                      from :table_address_book
                                     where customers_id = :customers_id');
    $Qaddress->bindInt(':customers_id', $customerId);
    $Qaddress->execute();

    while ($Qaddress->fetch()) {
      $country = Address::getCountries((int)$Qaddress->valueInt('entry_country_id'), true);
      $countryCode = $country['countries_iso_code_2'] ?? '';
      $name = trim($Qaddress->value('entry_firstname') . ' ' . $Qaddress->value('entry_lastname'));

      $addresses[] = [
        'name' => $name,
        'line_one' => $Qaddress->value('entry_street_address'),
        'line_two' => $Qaddress->value('entry_suburb'),
        'city' => $Qaddress->value('entry_city'),
        'state' => $Qaddress->value('entry_state'),
        'postal_code' => $Qaddress->value('entry_postcode'),
        'country' => $countryCode,
        'phone_number' => $Qaddress->value('entry_telephone')
      ];
    }

    return $addresses;
  }

  /**
   * Determine session status based on required fields.
   *
   * @param array $session
   * @return string
   */
  private function determineSessionStatus(array $session): string
  {
    if (empty($session['items']) || empty($session['fulfillment_address'])) {
      return 'not_ready_for_payment';
    }

    return 'ready_for_payment';
  }

  /**
   * Update an existing UCP checkout session.
   *
   * @param string $sessionId
   * @param array $input
   * @return array|null
   */
  public function updateSession(string $sessionId, array $input): ?array
  {
    $session = $this->sessionManager->get($sessionId);
    if ($session === null) {
      return null;
    }

    if (isset($input['items'])) {
      $session['items'] = $input['items'];
    }

    if (isset($input['consumer'])) {
      $session['consumer'] = $input['consumer'];
    }

    if (isset($input['fulfillment_address']) && is_array($input['fulfillment_address'])) {
      $session['fulfillment_address'] = $this->normalizeAddress($input['fulfillment_address']);
    }

    if (isset($input['fulfillment_option_id'])) {
      $session['fulfillment_option_id'] = $input['fulfillment_option_id'];
    }

    $taxRate = 0.08;
    $lineItems = $this->buildLineItems($session['items'] ?? [], $taxRate);
    $shippingSubtotal = 0;
    $shippingTax = (int)round($shippingSubtotal * $taxRate);
    $session['line_items'] = $lineItems;
    $session['fulfillment_options'] = $this->buildFulfillmentOptions($shippingSubtotal, $shippingTax, $session['fulfillment_address'] ?? []);
    $session['totals'] = $this->buildTotals($lineItems, $shippingSubtotal, $shippingTax);
    $session['status'] = $this->determineSessionStatus($session);

    $this->sessionManager->update($sessionId, $session);

    return $session;
  }

  /**
   * Complete a checkout session with payment data.
   *
   * @param string $sessionId
   * @param array $input
   * @return array|null
   */
  public function completeSession(string $sessionId, array $input): ?array
  {
    $session = $this->sessionManager->get($sessionId);
    if ($session === null) {
      return null;
    }

    if (($session['status'] ?? '') !== 'ready_for_payment') {
      return [
        'error' => [
          'code' => 'INVALID_STATUS',
          'message' => 'Session not ready for payment'
        ]
      ];
    }

    $paymentData = $input['payment_data'] ?? [];
    $errors = $this->paymentProcessor->validatePaymentData($paymentData);
    if (!empty($errors)) {
      return [
        'error' => [
          'code' => 'VALIDATION_ERROR',
          'message' => 'Payment data invalid',
          'details' => $errors
        ]
      ];
    }

    $result = $this->paymentProcessor->processPayment($session, $paymentData);
    if (($result['status'] ?? '') !== 'succeeded') {
      return [
        'error' => [
          'code' => 'PAYMENT_FAILED',
          'message' => 'Payment failed'
        ]
      ];
    }

    $session['status'] = 'completed';
    $session['payment'] = [
      'status' => 'succeeded',
      'provider' => $result['provider'] ?? ($paymentData['provider'] ?? 'stripe'),
      'transaction_id' => $result['transaction_id'] ?? null
    ];
    $session['payment_data'] = $paymentData;

    $this->sessionManager->update($sessionId, $session);

    $orderResult = $this->createOrderFromSession($sessionId, [], $paymentData);

    $order = null;
    if (($orderResult['status'] ?? '') === 'success' && !empty($orderResult['order_id'])) {
      $orderId = (string)$orderResult['order_id'];
      $order = [
        'id' => $orderId,
        'checkout_session_id' => $sessionId,
        'permalink_url' => CLICSHOPPING::link('Shop/index.php', 'Account&HistoryInfo&order_id=' . $orderId)
      ];
    }

    return [
      'checkout_session' => $session,
      'order' => $order
    ];
  }

  /**
   * Create an order from a stored UCP session.
   *
   * @param string $sessionId
   * @param array $customerData
   * @param array $paymentData
   * @return array
   */
  public function createOrderFromSession(string $sessionId, array $customerData = [], array $paymentData = []): array
  {
    $session = $this->sessionManager->get($sessionId);
    if ($session === null) {
      return [
        'status' => 'error',
        'message' => 'Session not found',
        'order_id' => null
      ];
    }

    if (empty($customerData)) {
      $customerData = $this->buildCustomerDataFromSession($session);
    }

    return $this->orderManager->createOrderFromSession($session, $customerData, $paymentData);
  }

  /**
   * Build minimal customer data from session for order creation.
   *
   * @param array $session
   * @return array
   */
  private function buildCustomerDataFromSession(array $session): array
  {
    $consumer = $session['consumer'] ?? [];
    $address = $session['fulfillment_address'] ?? [];
    $name = trim((string)($consumer['name'] ?? $address['name'] ?? ''));
    $parts = preg_split('/\\s+/', $name, 2);
    $firstname = $parts[0] ?? 'GPT';
    $lastname = $parts[1] ?? 'Customer';

    return [
      'firstname' => $firstname,
      'lastname' => $lastname,
      'email_address' => $consumer['email'] ?? 'gpt@example.com',
      'telephone' => $consumer['phone_number'] ?? ($address['phone_number'] ?? ''),
      'street_address' => $address['line_one'] ?? '',
      'suburb' => $address['line_two'] ?? '',
      'city' => $address['city'] ?? '',
      'postcode' => $address['postal_code'] ?? '',
      'state' => $address['state'] ?? '',
      'country' => $address['country'] ?? 'FR'
    ];
  }

  /**
   * Handle payment webhook payload.
   *
   * @param array $payload
   * @return array
   */
  public function handleWebhook(array $payload): array
  {
    $event = $this->paymentProcessor->handleWebhook($payload);
    return ['received' => true, 'event' => $event['type'] ?? null];
  }

  /**
   * Returns the store country ISO-3166-1 alpha-2 code.
   *
   * @return string
   */
  private function getStoreCountryIso(): string
  {
    if (\defined('STORE_COUNTRY')) {
      $country = Address::getCountries((int)STORE_COUNTRY, true);
      if (!empty($country['countries_iso_code_2'])) {
        return $country['countries_iso_code_2'];
      }
    }

    return 'US';
  }
}
