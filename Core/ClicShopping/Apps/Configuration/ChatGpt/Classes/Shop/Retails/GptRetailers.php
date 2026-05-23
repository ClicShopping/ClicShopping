<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Retails;

use AllowDynamicProperties;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\OM\SimpleLogger;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Retails\GptOrderManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Retails\GptCustomerManager;
use ClicShopping\Apps\Catalog\Manufacturers\Classes\ClicShoppingAdmin\ManufacturerAdmin;

use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

/**
 * GptRetailers class.
 *
 * This class acts as the main interface between the ClicShopping e-commerce platform
 * and the OpenAI Retailers API. It manages product catalog synchronization,
 * checkout session creation/management, delegated payment handling (Stripe),
 * webhook communication with the ACP agent, and order creation.
 */
#[AllowDynamicProperties]
class GptRetailers
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
   * @var GptCustomerManager Manages customer account creation and retrieval.
   */
  protected GptCustomerManager $customerManager;

  /**
   * @var string Directory path for storing session files.
   */
  protected string $dirSession;

  /**
   * GptRetailers constructor.
   * Initializes dependencies, checks application status, configures Stripe,
   * and sets up the session directory.
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
      // Use a custom logger instance if the global one doesn't exist
      $this->logger = new SimpleLogger('MCP_ClicShopping');
    } else {
      $this->logger = Registry::get('SimpleLogger');
    }

    $this->orderManager = new GptOrderManager();
    $this->customerManager = new GptCustomerManager();

    // Perform initial checks
    $this->checkStatus();
    $this->checkAppStripe();

    // Setup session directory
    $this->dirSession = CLICSHOPPING::BASE_DIR . 'Work/Sessions/Shop/ACP';

    if (!is_dir( $this->dirSession)){
      mkdir( $this->dirSession, 0777, true);
    }

    // Configure Stripe API key dynamically (live or test)
    Stripe::setApiKey($this->AppStripeKey());
  }

  // ---
  ## Configuration and Status Checks

  /**
   * Checks if the ChatGpt Retailer application status is enabled and configured.
   *
   * @return bool True if the application is enabled and the OpenAI API key is set, false otherwise.
   */
  private function checkStatus() :bool
  {
    $result = true;

    if(\defined('CLICSHOPPING_APP_CHATGPT_RE_STATUS') && CLICSHOPPING_APP_CHATGPT_RE_STATUS == 'False') {
      $result = false;
    }

    if(\defined('CLICSHOPPING_APP_CHATGPT_RE_API_KEY_OPENAI_RETAIL') && empty(CLICSHOPPING_APP_CHATGPT_RE_API_KEY_OPENAI_RETAIL)) {
      $result = false;
    }

    return $result;
  }

  /**
   * Checks if the Stripe application is installed, activated, and properly configured.
   *
   * @return bool True if Stripe is ready to be used, false otherwise.
   */
  private function checkAppStripe() :bool
  {
    $result = true;

    if(\defined('CLICSHOPPING_APP_STRIPE_ST_STATUS') && CLICSHOPPING_APP_STRIPE_ST_STATUS == 'False') {
      $result = false;
    }

    if (\defined('CLICSHOPPING_APP_STRIPE_ST_KEY_WEBHOOK_ENDPOINT') && empty(CLICSHOPPING_APP_STRIPE_ST_KEY_WEBHOOK_ENDPOINT)) {
      // Note: CLICSHOPPING_APP_STRIPE_ST_KEY_WEBHOOK_ENDPOINT seems to be used as the secret here, which may be incorrect based on typical Stripe integration.
      $result = false;
    }

    return $result;
  }

  /**
   * Retrieves the Webhook Secret for OpenAI Retailers.
   * Note: The current implementation uses CLICSHOPPING_APP_CHATGPT_RE_STATUS as the secret, which may be a placeholder or configuration error.
   *
   * @return string The webhook secret, or an empty string if not defined.
   */
  private function webHookSecretOpenAI() :string
  {
    $webhookSecret = '';

    if (\defined('CLICSHOPPING_APP_CHATGPT_RE_STATUS') && !empty(CLICSHOPPING_APP_CHATGPT_RE_STATUS)) {
      $webhookSecret = CLICSHOPPING_APP_CHATGPT_RE_STATUS;
    }

    return $webhookSecret;
  }

  /**
   * Provides the URL for the OpenAI Retailers Agent webhook.
   *
   * @return string The default OpenAI Agent webhook URL.
   */
  private function webHookUrlOpenAI() :string
  {
    $webhookUrl = 'https://agent.openai.com/webhook';

    return $webhookUrl;
  }

  /**
   * Retrieves the appropriate Stripe private key (live or test) based on the application's production status.
   *
   * @return string The Stripe private API key.
   */
  public function AppStripeKey() :string
  {
    $private_key  = '';

    if (\defined('CLICSHOPPING_APP_STRIPE_ST_SERVER_PROD')) {
      if (CLICSHOPPING_APP_STRIPE_ST_SERVER_PROD == 'True' && \defined('CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY')) {
        $private_key = CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY;
      } elseif (\defined('CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY_TEST')) {
        $private_key = CLICSHOPPING_APP_STRIPE_ST_PRIVATE_KEY_TEST;
      }
}

    return $private_key;
  }

  // ---
  ## Product Catalog Synchronization

  /**
   * Retrieves all available, in-stock, and visible products to generate the catalog data required for the ACP (Agent Controlled Purchase) agent.
   *
   * The data includes product details, pricing, stock, images, categories, shipping information, and promotional metadata.
   *
   * @return array A list of products formatted for the OpenAI ACP specification.
   */
  public function getProducts(): array
  {
    $language_id = $this->lang->getId();
    $catalog = [];

    $Qproducts = $this->db->prepare('SELECT p.products_id,
                                             p.products_model,
                                             pd.products_name,
                                             pd.products_description,
                                             p.products_price,
                                             p.products_quantity,
                                             p.products_image_small,
                                             p.products_image_medium,
                                             p.products_image_zoom,
                                             p.products_weight,
                                             p.products_dimension_depth,
                                             p.products_dimension_width,
                                             p.products_dimension_height,
                                             p.products_min_qty_order,
                                             p.products_price_kilo,
                                             pd.products_description_summary,
                                             pd.products_head_title_tag,
                                             pd.products_head_desc_tag,
                                             pd.products_head_keywords_tag,
                                             pd.products_shipping_delay,
                                             p.products_sku,
                                             p.products_ean,
                                             p.parent_id,
                                             GROUP_CONCAT(cd.categories_name) AS categories,
                                             pc.categories_id
                                      FROM :table_products p
                                      LEFT JOIN :table_products_description pd ON p.products_id = pd.products_id
                                      LEFT JOIN :table_products_to_categories pc ON p.products_id = pc.products_id
                                      LEFT JOIN :table_categories_description cd ON pc.categories_id = cd.categories_id
                                      WHERE p.products_status = 1
                                        AND p.products_id = pd.products_id
                                        AND pd.language_id = :language_id
                                        AND cd.language_id = :language_id
                                        AND p.products_view = 1
                                        AND p.products_archive = 0
                                        AND p.products_quantity > 0
                                      GROUP BY p.products_id');

    $Qproducts->bindInt(':language_id', $language_id);
    $Qproducts->execute();

    while ($p = $Qproducts->fetch()) {
      $productId = (int)$p['products_id'];
      $brandName = ManufacturerAdmin::getManufacturerName($productId);
      $favorites = $this->getFavorites($productId);
      $featured = $this->getFeatured($productId);
      $special = $this->getSpecial($productId);

      $catalog[] = [
        'id' => (string)$productId,
        'title' => $p['products_name'],
        'description' => $p['products_description'],
        'currency' => 'EUR', // Hardcoded currency
        'price' => (float)$p['products_price'],
        'images' => [
          $p['products_image_small'],
          $p['products_image_medium'],
          $p['products_image_zoom']
        ],
        'in_stock' => ((int)$p['products_quantity'] > 0),
        'available_quantity' => (int)$p['products_quantity'],
        'categories' => $p['categories'] !== null ? explode(',', $p['categories']) : [],
        'shipping' => [
          'weight_kg' => (float)$p['products_weight'],
          'dimensions_cm' => [
            'length' => (float)$p['products_dimension_depth'], // Depth mapped as Length
            'width' => (float)$p['products_dimension_width'],
            'height' => (float)$p['products_dimension_height']
          ],
          'methods' => ['standard', 'express'] // Simplified shipping methods
        ],
        'metadata' => [
          'brand' => $brandName,
          'sku' => $p['products_sku'],
          'ean' => $p['products_ean'],
          'model' => $p['products_model'],
          'min_qty_order' => $p['products_min_qty_order'],
          'price_kilo' => $p['products_price_kilo'],
          'description_summary' => $p['products_description_summary'],
          'head_title_tag' => $p['products_head_title_tag'],
          'head_desc_tag' => $p['products_head_desc_tag'],
          'head_keywords_tag' => $p['products_head_keywords_tag'],
          'shipping_delay' => $p['products_shipping_delay'],
          'parent_id' => $p['parent_id'],
          'categories_id' => $p['categories_id'],
          'featured' => [
            'active' => !empty($featured),
            'start_date' => $featured['scheduled_date'] ?? null,
            'end_date' => $featured['expires_date'] ?? null
          ],
          'favorite' => [
            'active' => !empty($favorites),
            'start_date' => $favorites['scheduled_date'] ?? null,
            'end_date' => $favorites['expires_date'] ?? null
          ],
          'promotion' => [
            'active' => !empty($special),
            'specials_new_products_price' => $special['specials_new_products_price'] ?? null,
            'flash_discount' => $special['flash_discount'] ?? null,
            'start_date' => $special['scheduled_date'] ?? null,
            'end_date' => $special['expires_date'] ?? null
          ]
        ]
      ];
    }

    return $catalog;
  }

  /**
   * Retrieves promotion data for products marked as "favorites".
   *
   * @param int $id The ID of the product.
   * @return array An array containing scheduled and expiration dates, or an empty array if not a favorite.
   */
  public function getFavorites(int $id): array
  {
    $QFavorites = $this->db->prepare('select products_favorites_id,
                                                   scheduled_date,
                                                   expires_date
                                              from :table_products_favorites
                                              where status = 1
                                              and products_id = :products_id
                                           ');

    $QFavorites->bindInt(':products_id', $id);
    $QFavorites->execute();
    $favorites_array = [];

    if (!empty($QFavorites->valueInt('products_favorites_id'))) {
      $favorites_array = [
        'scheduled_date' => $QFavorites->value('scheduled_date'),
        'expires_date' => $QFavorites->value('expires_date')
      ];

    }

    return $favorites_array;
  }

  /**
   * Retrieves promotion data for "featured" products.
   *
   * @param int $id The ID of the product.
   * @return array An array containing scheduled and expiration dates, or an empty array if not featured.
   */
  private function getFeatured(int $id): array
  {
    $QFavorites = $this->db->prepare('select products_featured_id,
                                                   scheduled_date,
                                                   expires_date
                                              from :table_products_featured
                                              where status = 1
                                              and products_id = :products_id
                                           ');

    $QFavorites->bindInt(':products_id',$id);
    $QFavorites->execute();
    $favorites_array = [];

    if (!empty($QFavorites->valueInt('products_featured_id'))) {
      $favorites_array = [
        'scheduled_date' => $QFavorites->value('scheduled_date'),
        'expires_date' => $QFavorites->value('expires_date')
      ];

    }

    return $favorites_array;
  }

  /**
   * Retrieves data for "special" (sale) products.
   *
   * @param int $id The ID of the product.
   * @return array An array containing pricing and scheduling details, or an empty array if not on special.
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

    $Qspecials->bindInt(':products_id',$id);
    $Qspecials->execute();
    $specials_array = [];

    if (!empty($Qspecials->valueInt('specials_id'))) {
      $specials_array = [
        'scheduled_date' => $Qspecials->value('scheduled_date'),
        'expires_date' => $Qspecials->value('expires_date'),
        'specials_new_products_price' => $Qspecials->value('specials_new_products_price'),
        'flash_discount' => $Qspecials->value('flash_discount')
      ];

    }

    return $specials_array;
  }

  /**
   * Builds an array of product items, including detailed information, from a simple list of product IDs and quantities.
   *
   * This is used to hydrate the checkout session items from minimal input.
   *
   * @param array $productIdsQuantities An associative array where keys are product IDs and values are quantities.
   * @return array A list of product items formatted for the ACP checkout session.
   */
  public function buildItemsFromIds(array $productIdsQuantities): array
  {
    $items = [];
    $language_id = $this->lang->getId();

    foreach ($productIdsQuantities as $pid => $qty) {
      $Qproducts = $this->db->prepare('SELECT p.products_id, 
                                             p.products_model,
                                             pd.products_name, 
                                             pd.products_description, 
                                             p.products_price,
                                             p.products_quantity, 
                                             p.products_image_small,
                                             p.products_image_medium,
                                             p.products_image_zoom,
                                             p.products_weight,
                                             p.products_dimension_depth, 
                                             p.products_dimension_width, 
                                             p.products_dimension_height,
                                             p.products_min_qty_order,
                                             p.products_price_kilo,
                                             pd.products_description_summary,
                                             pd.products_head_title_tag,
                                             pd.products_head_desc_tag,
                                             pd.products_head_keywords_tag,
                                             pd.products_shipping_delay,
                                             p.products_sku,
                                             p.products_ean, 
                                             p.parent_id,
                                             GROUP_CONCAT(cd.categories_name) AS categories,
                                             pc.categories_id
                                      FROM :table_products p
                                      LEFT JOIN :table_products_description pd ON p.products_id = pd.products_id
                                      LEFT JOIN :table_products_to_categories pc ON p.products_id = pc.products_id
                                      LEFT JOIN :table_categories_description cd ON pc.categories_id = cd.categories_id
                                      WHERE p.products_status = 1
                                        AND p.products_id = pd.products_id
                                        AND pd.language_id = :language_id
                                        AND cd.language_id = :language_id
                                        AND p.products_view = 1
                                        AND p.products_archive = 0
                                        AND p.products_quantity > 0
                                        AND p.products_id = :product_id
                                      GROUP BY p.products_id
                                  '
      );

      $Qproducts->bindInt(':language_id', $language_id);
      $Qproducts->bindInt(':product_id', (int)$pid);
      $Qproducts->execute();

      while ($p = $Qproducts->fetch()) {
        $productId = (int)$p['products_id'];
        $brand_name = ManufacturerAdmin::getManufacturerName($productId);
        $favorites = $this->getFavorites($productId);
        $featured = $this->getFeatured($productId);
        $special =  $this->getSpecial($productId);
        $items[] = [
          'id' => (string)$productId,
          'title' => $p['products_name'],
          'description' => $p['products_description'],
          'currency' => 'EUR', // Hardcoded currency
          'price' => (float)$p['products_price'],
          'quantity' => (int)$qty,
          'unit_price' => (float)$p['products_price'],
          'images' => [
            $p['products_image_small'],
            $p['products_image_medium'],
            $p['products_image_zoom']
          ],
          'in_stock' => ((int)$p['products_quantity'] > 0),
          'available_quantity' => (int)$p['products_quantity'],
          'categories' => $p['categories'] !== null ? explode(',', $p['categories']) : [],
          'shipping' => [
            'weight_kg' => (float)$p['products_weight'],
            'dimensions_cm' => [
              'length' => (float)$p['products_dimension_depth'],
              'width' => (float)$p['products_dimension_width'],
              'height' => (float)$p['products_dimension_height']
            ],
            'methods' => ['standard', 'express']
          ],

          'metadata' => [
            'brand' => $brand_name,
            'sku' => $p['products_sku'],
            'ean' => $p['products_ean'],
            'model' => $p['products_model'],
            'min_qty_order' => $p['products_min_qty_order'],
            'price_kilo' => $p['products_price_kilo'],
            'description_summary' => $p['products_description_summary'],
            'head_title_tag' => $p['products_head_title_tag'],
            'head_desc_tag' => $p['products_head_desc_tag'],
            'head_keywords_tag' => $p['products_head_keywords_tag'],
            'shipping_delay' => $p['products_shipping_delay'],
            'parent_id' => $p['parent_id'],
            'categories_id' => $p['categories_id'],

            'featured' =>[
              'active' => !empty($featured),
              'start_date' => $featured['scheduled_date'] ?? null,
              'end_date' => $featured['expires_date'] ?? null
            ],

            'favorite' =>[
              'active' => !empty($favorites),
              'start_date' => $favorites['scheduled_date'] ?? null,
              'end_date' => $favorites['expires_date'] ?? null
            ],

            'promotion' => [
              'active' => !empty($special),
              'specials_new_products_price' => $special['specials_new_products_price'] ?? null,
              'flash_discount' => $special['flash_discount'] ?? null,
              'start_date' => $special['scheduled_date'] ?? null,
              'end_date' => $special['expires_date'] ?? null
            ]
          ]
        ];
      }
}

    return $items;
  }

  // ---
  ## Checkout Session Management

  /**
   * Creates a new ACP checkout session based on the provided input.
   *
   * It calculates totals, saves the session data to a JSON file, and sends an `order.created` webhook event.
   *
   * @param array $input Associative array containing 'items', 'shipping_address', 'billing_address', and 'metadata'.
   * @return array The newly created checkout session data.
   */
  public function createSession(array $input): array
  {
    $sessionId = uniqid('cs_');
    $items = $input['items'] ?? [];
    $shipping = $input['shipping_address'] ?? [];
    $billing = $input['billing_address'] ?? [];

    $subtotal = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));
    // Hardcoded simple tax calculation (8%)
    $tax = round($subtotal * 0.08, 2);
    $shipping_cost = $shipping['cost'] ?? 0;
    $total = $subtotal + $tax + $shipping_cost;

    $session = [
      "id" => $sessionId,
      "status" => "open",
      "currency" => "EUR",
      "items" => $items,
      "subtotal" => $subtotal,
      "tax" => $tax,
      "shipping_cost" => $shipping_cost,
      "total" => $total,
      "shipping_address" => $shipping,
      "billing_address" => $billing,
      "payment" => ["status" => "pending","methods" => ["card","paypal","apple_pay"]],
      "metadata" => $input['metadata'] ?? []
    ];

    file_put_contents( $this->dirSession . '/' . $sessionId . '.json', json_encode(["checkout_session"=>$session], JSON_UNESCAPED_SLASHES));

    $this->sendWebhookEvent('order.created', $session);

    return $session;
  }

  /**
   * Updates an existing ACP checkout session with new data.
   *
   * It recalculates totals based on updated items, saves the changes, and sends an `order.updated` webhook event.
   *
   * @param string $sessionId The ID of the session to update.
   * @param array $input The data fields to merge (items, addresses, metadata).
   * @return array|null The updated checkout session data, or null if the session file doesn't exist.
   */
  public function updateSession(string $sessionId, array $input): ?array
  {
    $file =  $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return null;
    }

    $session = json_decode(file_get_contents($file), true)['checkout_session'];

    $array_data = [
      'items',
      'shipping_address',
      'billing_address',
      'metadata'
    ];

    // Merge input data into session
    foreach ($array_data as $f) {
      if (isset($input[$f])) $session[$f] = $input[$f];
    }

    // Recalculate totals
    $subtotal = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $session['items']));
    $tax = round($subtotal * 0.08, 2);
    $shipping_cost = $session['shipping_address']['cost'] ?? 0;
    $session['subtotal'] = $subtotal;
    $session['tax'] = $tax;
    $session['shipping_cost'] = $shipping_cost;
    $session['total'] = $subtotal + $tax + $shipping_cost;

    file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
    $this->sendWebhookEvent('order.updated', $session);

    return $session;
  }

  /**
   * Completes a checkout session by initiating a Stripe Payment Intent.
   *
   * This is used for a direct payment flow. It updates the session status to 'completed'
   * and stores Stripe-specific payment data.
   *
   * @param string $sessionId The ID of the session to complete.
   * @param array $input Input data, mainly containing the chosen payment method type.
   * @return array|null The updated session data, or null if the session file doesn't exist.
   */
  public function completeSessionWithStripe(string $sessionId, array $input): ?array
  {
    $file =  $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return null;
    }

    $session = json_decode(file_get_contents($file), true)['checkout_session'];
    $totalCents = intval($session['total'] * 100);

    // Create Stripe PaymentIntent
    $paymentIntent = PaymentIntent::create([
      'amount' => $totalCents,
      'currency' => strtolower($session['currency']),
      'payment_method_types' => ['card'],
      'metadata' => [
        'checkout_session_id' => $sessionId,
        'internal_reference' => $session['metadata']['internal_reference'] ?? ''
      ]
    ]);

    $session['status'] = 'completed';

    $session['payment'] = [
      'status' => 'pending',
      'stripe_payment_intent_id' => $paymentIntent->id,
      'client_secret' => $paymentIntent->client_secret,
      'method' => $input['payment_method']['type'] ?? 'card'
    ];

    // Update addresses and metadata if provided in the completion input
    if (isset($input['billing_address'])) $session['billing_address'] = $input['billing_address'];
    if (isset($input['shipping_address'])) $session['shipping_address'] = $input['shipping_address'];
    if (isset($input['metadata'])) $session['metadata'] = $input['metadata'];

    file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
    $this->sendWebhookEvent('order.completed', $session);

    return $session;
  }

  /**
   * Completes a checkout session using an ACP Delegated Payment payload (e.g., a payment token).
   *
   * This flow signals that payment details have been captured and tokenized by a third-party
   * (like Stripe in a delegated flow) and are now available for merchant capture.
   *
   * @param string $sessionId The ID of the session to complete.
   * @param array $input Input data, including the `delegated_payment` payload.
   * @return array|null The updated session data, or null if the session is not found or no delegated payment payload is present.
   */
  public function completeSessionWithDelegatedPayment(string $sessionId, array $input): ?array
  {
    $file =  $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return null;
    }

    $session = json_decode(file_get_contents($file), true)['checkout_session'];

    $delegated = $input['delegated_payment'] ?? null;
    if (!$delegated) {
      return null;
    }

    // Set session status to 'completed' (checkout phase finished)
    $session['status'] = 'completed';
    $session['payment'] = [
      'status' => 'pending', // Payment is pending merchant capture/confirmation
      'method' => 'delegated',
      'delegated' => [
        'psp' => $delegated['psp'] ?? 'stripe',
        'token' => $delegated['token'] ?? null,
        'max_amount' => $delegated['max_amount'] ?? null,
        'currency' => $delegated['currency'] ?? ($session['currency'] ?? 'EUR'),
        'expires_at' => $delegated['expires_at'] ?? null
      ]
    ];

    if (isset($input['billing_address'])) $session['billing_address'] = $input['billing_address'];
    if (isset($input['shipping_address'])) $session['shipping_address'] = $input['shipping_address'];
    if (isset($input['metadata'])) $session['metadata'] = $input['metadata'];

    file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
    $this->sendWebhookEvent('order.completed', $session);

    return $session;
  }

  // ---
  ## Webhook and Session Retrieval

  /**
   * Sends a webhook event containing session data to the ACP agent URL.
   *
   * The payload is signed with a SHA256 HMAC signature using the OpenAI webhook secret.
   *
   * @param string $event The name of the event (e.g., 'order.created', 'order.completed').
   * @param array $session The current checkout session data.
   * @return void
   */
  public function sendWebhookEvent(string $event, array $session): void
  {
    $payload = [
      'event' => $event,
      'checkout_session' => $session,
      'timestamp' => gmdate("Y-m-d\TH:i:s\Z")
    ];

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $signature = hash_hmac('sha256', $jsonPayload, $this->webHookSecretOpenAI());

    HTTP::getResponse([
      'url' => $this->webHookUrlOpenAI(),
      'method' => 'POST',
      'headers' => [
        'Content-Type: application/json',
        'X-Signature: ' . $signature
      ],
      'body' => $jsonPayload,
      'timeout' => 30,
      'ssl_verify' => true,
      'user_agent' => 'ClicShopping-MCP-Client/1.0'
    ]);
  }

  /**
   * Lists all stored checkout sessions.
   *
   * @param bool $fullData Whether to return the full session array or just a list of session IDs.
   * @return array A list of sessions or session IDs.
   */
  public function listSessions(bool $fullData = true): array
  {
    $sessions = [];

    if (!is_dir($this->dirSession)) {
      return $sessions;
    }

    $files = glob($this->dirSession . '/*.json');

    foreach ($files as $file) {
      $sessionId = basename($file, '.json');

      if ($fullData) {
        $sessionData = json_decode(file_get_contents($file), true);
        if ($sessionData && isset($sessionData['checkout_session'])) {
          $sessions[] = $sessionData['checkout_session'];
        }
} else {
        $sessions[] = $sessionId;
      }
}

    return $sessions;
  }

  /**
   * Retrieves a specific checkout session by its ID.
   *
   * @param string $sessionId The ID of the session.
   * @return array|null The session data array, or null if the session file is not found.
   */
  public function getSessionById(string $sessionId): ?array
  {
    $file = $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return null;
    }

    $sessionData = json_decode(file_get_contents($file), true);

    if ($sessionData && isset($sessionData['checkout_session'])) {
      return $sessionData['checkout_session'];
    }

    return null;
  }

  /**
   * Deletes a checkout session file.
   *
   * @param string $sessionId The ID of the session to delete.
   * @return bool True on successful deletion, false if the file was not found.
   */
  public function deleteSession(string $sessionId): bool
  {
    $file = $this->dirSession . '/' . $sessionId . '.json';

    if (!file_exists($file)) {
      return false;
    }

    return unlink($file);
  }

  /**
   * Handles incoming Stripe webhook events.
   *
   * This method verifies the webhook signature and processes events, specifically
   * `payment_intent.succeeded`, to update the local session status and notify the ACP agent.
   *
   * @return void
   */
  public function handleStripeWebhook(): void
  {
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    // Note: The endpoint secret ('whsec_xxx') must be dynamically retrieved from the database in a production environment.
    $endpoint_secret = 'whsec_xxx';

    try {
      $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    } catch (\Exception $e) {
      http_response_code(400);
      exit('Invalid payload');
    }

    if ($event->type === 'payment_intent.succeeded') {
      $pi = $event->data->object;
      $sessionId = $pi->metadata->checkout_session_id ?? null;

      if (!$sessionId) {
        http_response_code(404); exit('No session ID');
      }

      $file =  $this->dirSession . '/' . $sessionId . '.json';

      if (!file_exists($file)) {
        http_response_code(404);
        exit('Session not found');
      }

      $session = json_decode(file_get_contents($file), true)['checkout_session'];
      $session['payment']['status'] = 'succeeded';
      $session['payment']['transaction_id'] = $pi->id;

      file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
      $this->sendWebhookEvent('order.paid', $session);
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
  }

  // ---
  ## Order Creation

  /**
   * Helper function to either create a new customer account or retrieve an existing one.
   *
   * @param array $customerData Customer data, typically derived from the session's address info.
   * @return array Result with 'status', 'message', and 'customer_id'.
   */
  private function createOrGetCustomer(array $customerData): array
  {
    // Check if the customer already exists by email
    if ($this->customerManager->emailExists($customerData['email_address'] ?? '')) {
      // If customer exists, we should ideally retrieve their ID, but current GptCustomerManager lacks getCustomerByEmail
      // For simplicity in this demo, we assume the customer needs to be created, or we'd need a way to link to an existing one.
      // Since the prompt shows a simple order creation flow, we'll try to create it and rely on the GptCustomerManager's check.
      $Qcustomer = $this->db->prepare('SELECT customers_id
                                      FROM :table_customers 
                                      WHERE customers_email_address = :customers_email_address
                                     ');
      $Qcustomer->bindValue(':customers_email_address', $customerData['email_address']);
      $Qcustomer->execute();

      if ($Qcustomer->fetch()) {
        $customerId = $Qcustomer->valueInt('customers_id');
        return [
          'status' => 'success',
          'message' => 'Customer account already exists and retrieved.',
          'customer_id' => $customerId
        ];
      }
}

    // If customer doesn't exist, create a new one
    $customerResult = $this->customerManager->createCustomerAccount($customerData);

    if ($customerResult['status'] === 'success') {
      return $customerResult;
    } else {
      // Fallback if creation fails (e.g., missing address fields)
      return [
        'status' => 'error',
        'message' => 'Failed to create customer account: ' . ($customerResult['message'] ?? 'Unknown error.'),
        'customer_id' => null
      ];
    }
}

  /**
   * Creates a final e-commerce order from a completed checkout session.
   *
   * This is the crucial step after payment/delegated payment is finalized. It handles
   * customer creation and passes control to the GptOrderManager for database persistence.
   *
   * @param string $sessionId The ID of the completed checkout session.
   * @param array $customerData Full customer details from the session (optional, can be derived from session).
   * @param array $paymentData Payment details (optional, can be derived from session).
   * @return array Result containing 'status', 'message', and the created 'order_id'.
   */
  public function createOrderFromSession(string $sessionId, array $customerData = [], array $paymentData = []): array
  {
    try {
      // Load session data
      $session = $this->getSessionById($sessionId);

      if (!$session) {
        return [
          'status' => 'error',
          'message' => 'Session not found',
          'order_id' => null
        ];
      }

      // Check if session is valid for order creation (e.g., completed or paid)
      if ($session['status'] !== 'completed' && $session['status'] !== 'order_created') {
        return [
          'status' => 'error',
          'message' => 'Session status is ' . $session['status'] . '. Order creation requires a "completed" or "paid" status.',
          'order_id' => null
        ];
      }

      // 1. Prepare customer data (prefer explicit data, fall back to session addresses)
      if (empty($customerData) && !empty($session['billing_address'])) {
        // Simplified conversion from session address to customerData structure
        $customerData = [
          'email_address' => $session['metadata']['customer_email'] ?? $session['billing_address']['email'] ?? 'unknown@example.com',
          'firstname' => $session['billing_address']['first_name'] ?? 'GPT',
          'lastname' => $session['billing_address']['last_name'] ?? 'Customer',
          'street_address' => $session['billing_address']['street_address'] ?? 'N/A',
          'city' => $session['billing_address']['city'] ?? 'N/A',
          'postcode' => $session['billing_address']['postcode'] ?? 'N/A',
          'country' => $session['billing_address']['country'] ?? 'FR', // ISO 2-letter code
          'telephone' => $session['billing_address']['telephone'] ?? 'N/A',
          'cellular_phone' => $session['billing_address']['cellular_phone'] ?? '',
          'company' => $session['billing_address']['company'] ?? '',
          'state' => $session['billing_address']['state'] ?? '',
        ];
      }

      // 2. Create or retrieve customer account
      $customerResult = $this->createOrGetCustomer($customerData);

      if ($customerResult['status'] !== 'success') {
        return $customerResult;
      }

      // Update customer data with the created customer ID
      $customerData['id'] = $customerResult['customer_id'];

      // 3. Create order using the order manager
      // GptOrderManager::createOrderFromSession handles the detailed logic
      $result = $this->orderManager->createOrderFromSession($session, $customerData, $paymentData);

      if ($result['status'] === 'success') {
        // 4. Update session with final order ID and status
        $session['order_id'] = $result['order_id'];
        $session['customer_id'] = $customerResult['customer_id'];
        $session['status'] = 'order_created';

        $file = $this->dirSession . '/' . $sessionId . '.json';
        file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));

        // 5. Send final webhook event
        $this->sendWebhookEvent('order.created', $session);
      }

      return $result;

    } catch (\Exception $e) {
      $this->logger->error('Failed to create order from session', [
        'event' => 'gpt_order_creation_error',
        'session_id' => $sessionId,
        'error' => $e->getMessage()
      ]);

      return [
        'status' => 'error',
        'message' => $e->getMessage(),
        'order_id' => null
      ];
    }
}
}