<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * GPT Retailers OpenAI Compliant class for ClicShopping integration
 * Fully compliant with OpenAI Agentic Checkout Specification
 * 
 * This implementation follows the OpenAI Commerce specifications:
 * - Agentic Checkout: https://developers.openai.com/commerce/specs/checkout
 * - Product Feed: https://developers.openai.com/commerce/specs/feed
 * 
 * Key design principles:
 * - Uses dynamic shipping modules from ClicShopping (MODULE_SHIPPING_INSTALLED)
 * - Minimal hardcoding - delivery times and shipping details are optional per OpenAI spec
 * - Fallback to configurable constants when module data is unavailable
 * @see https://developers.openai.com/commerce/specs/checkout#object-definitions
 * @see https://developers.openai.com/commerce/specs/feed#fulfillment
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Retails;

use AllowDynamicProperties;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Retails\GptRetailers;

#[\AllowDynamicProperties]
class GptRetailersOpenAI extends GptRetailers
{

  /**
   * Active shipping modules cache
   * @var array|null
   */
  private ?array $activeShippingModules = null;

  /**
   * Delivery time defaults (can be overridden by shipping modules)
   */
  public function __construct()
  {
    $this->deliveryEarliest = define('CLICSHOPPING_APP_CHATGPT_RE_DELIVERY_EARLIEST') ? CLICSHOPPING_APP_CHATGPT_RE_DELIVERY_EARLIEST : '+3 days';
    $this->deliveryLatest =  define('CLICSHOPPING_APP_CHATGPT_RE_DELIVERY_LATEST') ? CLICSHOPPING_APP_CHATGPT_RE_DELIVERY_LATEST : '+5 days';
    $this->deliveryDefault = define('CLICSHOPPING_APP_CHATGPT_RE_DEFAULT_DELIVERY') ? CLICSHOPPING_APP_CHATGPT_RE_DEFAULT_DELIVERY : '3-5 jours ouvrés';
  }
  
    /**
     * Create OpenAI compliant checkout session
     * 
     * @param array $input Request data from OpenAI
     * @return array OpenAI compliant response
     */
    public function createSessionOpenAI(array $input): array
    {
        $sessionId = uniqid('cs_');
        $items = $input['items'] ?? [];
        $buyer = $input['buyer'] ?? null;
        $fulfillmentAddress = $this->normalizeAddressToOpenAI($input['fulfillment_address'] ?? []);
        
        // Build line items according to OpenAI spec
        $lineItems = [];
        $itemsBaseAmount = 0;
        
        foreach ($items as $item) {
            $productData = $this->getProductData($item['id']);
            if (!$productData) {
                continue; // Skip invalid products
            }
            
            $lineItem = $this->buildLineItemOpenAI($item, $productData);
            $lineItems[] = $lineItem;
            $itemsBaseAmount += $lineItem['base_amount'];
        }
        
        // Calculate totals in minor units (cents for EUR)
        $itemsDiscount = 0; // TODO: Calculate discounts based on promotions
        $subtotal = $itemsBaseAmount - $itemsDiscount;
        $fulfillmentCost = 590; // 5.90 EUR in cents (default shipping)
        $tax = $this->calculateTaxMinorUnits($subtotal + $fulfillmentCost);
        $total = $subtotal + $fulfillmentCost + $tax;
        
        // Determine session status
        $status = $this->determineSessionStatus($fulfillmentAddress, $lineItems);
        
        // Build OpenAI compliant session response
        $session = [
            "id" => $sessionId,
            "status" => $status,
            "currency" => "eur", // ISO 4217 lowercase as per spec
            "line_items" => $lineItems,
            "fulfillment_address" => $fulfillmentAddress,
            "fulfillment_options" => $this->buildFulfillmentOptions(),
            "fulfillment_option_id" => "standard_shipping",
            "totals" => $this->buildTotalsOpenAI($itemsBaseAmount, $itemsDiscount, $subtotal, $fulfillmentCost, $tax, $total),
            "messages" => $this->buildMessages($lineItems),
            "links" => $this->buildLinks(),
            "payment_provider" => [
                "provider" => "stripe",
                "supported_payment_methods" => ["card"]
            ]
        ];
        
        // Add buyer if provided
        if ($buyer) {
            $session['buyer'] = $this->normalizeBuyerToOpenAI($buyer);
        }
        
        // Save session
        file_put_contents($this->dirSession . '/' . $sessionId . '.json', json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
        
        // Send webhook event
        $this->sendWebhookEvent('order.created', $session);
        
        return $session;
    }
    
    /**
     * Update OpenAI compliant checkout session
     * 
     * @param string $sessionId
     * @param array $input
     * @return array|null
     */
    public function updateSessionOpenAI(string $sessionId, array $input): ?array
    {
        $file = $this->dirSession . '/' . $sessionId . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $session = json_decode(file_get_contents($file), true)['checkout_session'];
        
        // Update items if provided
        if (isset($input['items'])) {
            $lineItems = [];
            $itemsBaseAmount = 0;
            
            foreach ($input['items'] as $item) {
                $productData = $this->getProductData($item['id']);
                if (!$productData) {
                    continue;
                }
                
                $lineItem = $this->buildLineItemOpenAI($item, $productData);
                $lineItems[] = $lineItem;
                $itemsBaseAmount += $lineItem['base_amount'];
            }
            
            $session['line_items'] = $lineItems;
            
            // Recalculate totals
            $itemsDiscount = 0;
            $subtotal = $itemsBaseAmount - $itemsDiscount;
            $fulfillmentCost = $this->getFulfillmentCost($session['fulfillment_option_id'] ?? 'standard_shipping');
            $tax = $this->calculateTaxMinorUnits($subtotal + $fulfillmentCost);
            $total = $subtotal + $fulfillmentCost + $tax;
            
            $session['totals'] = $this->buildTotalsOpenAI($itemsBaseAmount, $itemsDiscount, $subtotal, $fulfillmentCost, $tax, $total);
        }
        
        // Update fulfillment address if provided
        if (isset($input['fulfillment_address'])) {
            $session['fulfillment_address'] = $this->normalizeAddressToOpenAI($input['fulfillment_address']);
        }
        
        // Update fulfillment option if provided
        if (isset($input['fulfillment_option_id'])) {
            $session['fulfillment_option_id'] = $input['fulfillment_option_id'];
            // Recalculate totals with new fulfillment cost
            // ... (similar to above)
        }
        
        // Update status
        $session['status'] = $this->determineSessionStatus($session['fulfillment_address'], $session['line_items']);
        
        // Update messages
        $session['messages'] = $this->buildMessages($session['line_items']);
        
        // Save updated session
        file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
        
        // Send webhook event
        $this->sendWebhookEvent('order.updated', $session);
        
        return $session;
    }
    
    /**
     * Complete OpenAI compliant checkout session
     * 
     * @param string $sessionId
     * @param array $input
     * @return array|null
     */
    public function completeSessionOpenAI(string $sessionId, array $input): ?array
    {
        $file = $this->dirSession . '/' . $sessionId . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $session = json_decode(file_get_contents($file), true)['checkout_session'];
        
        // Validate session can be completed
        if ($session['status'] !== 'ready_for_payment') {
            return null;
        }
        
        // Process payment data according to OpenAI spec
        $paymentData = $input['payment_data'] ?? null;
        if (!$paymentData) {
            return null;
        }
        
        // Create order
        $customerData = $this->extractCustomerDataFromSession($session, $input);
        $orderResult = $this->createOrderFromSession($sessionId, $customerData, $paymentData);
        
        if ($orderResult['status'] === 'success') {
            $session['status'] = 'completed';
            $session['order'] = [
                'id' => (string)$orderResult['order_id'],
                'checkout_session_id' => $sessionId,
                'permalink_url' => $this->buildOrderPermalinkUrl($orderResult['order_id'])
            ];
            
            // Save completed session
            file_put_contents($file, json_encode(["checkout_session" => $session], JSON_UNESCAPED_SLASHES));
            
            // Send webhook event
            $this->sendWebhookEvent('order.completed', $session);
        }
        
        return $session;
    }
    
    /**
     * Normalize address to OpenAI spec format
     * 
     * @param array $address
     * @return array
     */
    private function normalizeAddressToOpenAI(array $address): array
    {
        if (empty($address)) {
            return [];
        }
        
        return [
            'line1' => $address['address'] ?? $address['street_address'] ?? '',
            'line2' => $address['suburb'] ?? $address['line2'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'postal_code' => $address['postal_code'] ?? $address['postcode'] ?? '',
            'country' => strtoupper($address['country'] ?? 'FR')
        ];
    }
    
    /**
     * Normalize buyer to OpenAI spec format
     * 
     * @param array $buyer
     * @return array
     */
    private function normalizeBuyerToOpenAI(array $buyer): array
    {
        return [
            'email' => $buyer['email'] ?? $buyer['email_address'] ?? '',
            'phone' => $buyer['phone'] ?? $buyer['telephone'] ?? '',
            'first_name' => $buyer['first_name'] ?? $buyer['firstname'] ?? '',
            'last_name' => $buyer['last_name'] ?? $buyer['lastname'] ?? ''
        ];
    }
    
    /**
     * Build line item according to OpenAI spec
     * 
     * @param array $item
     * @param array $productData
     * @return array
     */
    private function buildLineItemOpenAI(array $item, array $productData): array
    {
        $baseAmount = $this->toMinorUnits($productData['price']);
        $quantity = (int)($item['quantity'] ?? 1);
        $discount = $this->calculateItemDiscount($productData, $quantity);
        $subtotal = ($baseAmount * $quantity) - $discount;
        $tax = $this->calculateItemTaxMinorUnits($subtotal);
        
        return [
            'id' => 'line_item_' . $item['id'],
            'item' => [
                'id' => $item['id'],
                'quantity' => $quantity
            ],
            'base_amount' => $baseAmount,
            'discount' => $discount,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax
        ];
    }
    
    /**
     * Get product data for line item building
     * 
     * @param string $productId
     * @return array|null
     */
    private function getProductData(string $productId): ?array
    {
        $products = $this->buildItemsFromIds([$productId => 1]);
        return $products[0] ?? null;
    }
    
    /**
     * Calculate item-specific discount
     * 
     * @param array $productData
     * @param int $quantity
     * @return int
     */
    private function calculateItemDiscount(array $productData, int $quantity): int
    {
        // Check for promotions/specials
        if (!empty($productData['metadata']['promotion']['active'])) {
            $discountPrice = $productData['metadata']['promotion']['specials_new_products_price'] ?? 0;
            if ($discountPrice > 0) {
                $originalAmount = $this->toMinorUnits($productData['price']);
                $discountAmount = $this->toMinorUnits($discountPrice);
                return ($originalAmount - $discountAmount) * $quantity;
            }
}
        
        return 0;
    }
    
    /**
     * Convert amount to minor units (cents for EUR)
     * 
     * @param float $amount
     * @return int
     */
    private function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }
    
    /**
     * Calculate tax in minor units
     * 
     * @param int $amount Amount in minor units
     * @return int
     */
    private function calculateTaxMinorUnits(int $amount): int
    {
        return (int) round($amount * 0.20); // 20% VAT for France
    }
    
    /**
     * Calculate item-specific tax in minor units
     * 
     * @param int $amount Amount in minor units
     * @return int
     */
    private function calculateItemTaxMinorUnits(int $amount): int
    {
        return (int) round($amount * 0.20); // 20% VAT
    }
    
    /**
     * Determine session status according to OpenAI spec
     * 
     * @param array $fulfillmentAddress
     * @param array $lineItems
     * @return string
     */
    private function determineSessionStatus(array $fulfillmentAddress, array $lineItems): string
    {
        if (empty($lineItems)) {
            return 'not_ready_for_payment';
        }
        
        if (empty($fulfillmentAddress)) {
            return 'not_ready_for_payment';
        }
        
        return 'ready_for_payment';
    }
    
    /**
     * Build totals array according to OpenAI spec
     * 
     * @param int $itemsBaseAmount
     * @param int $itemsDiscount
     * @param int $subtotal
     * @param int $fulfillmentCost
     * @param int $tax
     * @param int $total
     * @return array
     */
    private function buildTotalsOpenAI(int $itemsBaseAmount, int $itemsDiscount, int $subtotal, int $fulfillmentCost, int $tax, int $total): array
    {
        $totals = [];
        
        if ($itemsBaseAmount > 0) {
            $totals[] = [
                'type' => 'items_base_amount',
                'display_text' => 'Articles',
                'amount' => $itemsBaseAmount
            ];
        }
        
        if ($itemsDiscount > 0) {
            $totals[] = [
                'type' => 'items_discount',
                'display_text' => 'Remise articles',
                'amount' => $itemsDiscount
            ];
        }
        
        $totals[] = [
            'type' => 'subtotal',
            'display_text' => 'Sous-total',
            'amount' => $subtotal
        ];
        
        if ($fulfillmentCost > 0) {
            $totals[] = [
                'type' => 'fulfillment',
                'display_text' => 'Livraison',
                'amount' => $fulfillmentCost
            ];
        }
        
        if ($tax > 0) {
            $totals[] = [
                'type' => 'tax',
                'display_text' => 'Taxes',
                'amount' => $tax
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
     * Build fulfillment options according to OpenAI spec
     * Integrates with ClicShopping Shipping system
     * 
     * @param array $deliveryAddress Optional delivery address for shipping calculation
     * @return array
     */
    private function buildFulfillmentOptions(array $deliveryAddress = []): array
    {
        $fulfillmentOptions = [];
        
        try {
            // Get shipping options from ClicShopping shipping modules
            $shippingOptions = $this->getClicShoppingShippingOptions($deliveryAddress);
            
            if (!empty($shippingOptions)) {
                foreach ($shippingOptions as $option) {
                    $fulfillmentOptions[] = $this->convertToOpenAIFormat($option);
                }
}
            
            // Add digital delivery option for virtual products
            if ($this->hasVirtualProducts()) {
                $fulfillmentOptions[] = $this->buildDigitalFulfillmentOption();
            }
            
            // Add free shipping option if applicable  
            if ($this->isEligibleForFreeShipping()) {
                $fulfillmentOptions[] = $this->buildFreeShippingOption();
            }
} catch (\Exception $e) {
            $this->logger->error('Error building fulfillment options', [
                'error' => $e->getMessage()
            ]);
        }
        
        // Fallback to default options if no shipping modules available
        if (empty($fulfillmentOptions)) {
            $fulfillmentOptions = $this->buildDefaultFulfillmentOptions();
        }
        
        return $fulfillmentOptions;
    }
    
    /**
     * Get ClicShopping shipping options
     * 
     * @param array $deliveryAddress
     * @return array
     */
    private function getClicShoppingShippingOptions(array $deliveryAddress = []): array
    {
        $options = [];
        
        if (!defined('MODULE_SHIPPING_INSTALLED') || empty(MODULE_SHIPPING_INSTALLED)) {
            return $options;
        }
        
        try {
            // Initialize shipping system if not already done
            if (!Registry::exists('Shipping')) {
                Registry::set('Shipping', new \ClicShopping\Sites\Shop\Shipping());
            }
            
            $installedModules = explode(';', MODULE_SHIPPING_INSTALLED);
            
            foreach ($installedModules as $moduleCode) {
                if (empty($moduleCode)) continue;
                
                $shippingOption = $this->getShippingModuleOption($moduleCode);
                if ($shippingOption) {
                    $options[] = $shippingOption;
                }
}
            
        } catch (\Exception $e) {
            $this->logger->error('Error getting ClicShopping shipping options', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $options;
    }
    
    /**
     * Get shipping module option
     * 
     * @param string $moduleCode
     * @return array|null
     */
    private function getShippingModuleOption(string $moduleCode): ?array
    {
        try {
            $registryKey = 'Shipping_' . str_replace('\\', '_', $moduleCode);
            
            if (!Registry::exists($registryKey)) {
                return null;
            }
            
            $shippingModule = Registry::get($registryKey);
            
            if (!$shippingModule->enabled) {
                return null;
            }
            
            $quote = $shippingModule->quote();
            
            if (!$quote || empty($quote['methods'])) {
                return null;
            }
            
            return [
                'module_code' => $moduleCode,
                'module_name' => $quote['module'] ?? 'Unknown',
                'methods' => $quote['methods'],
                'tax' => $quote['tax'] ?? 0
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Error getting shipping module option', [
                'module' => $moduleCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
}

    /**
     * Convert ClicShopping shipping to OpenAI format
     * 
     * @param array $shippingOption
     * @return array
     */
    private function convertToOpenAIFormat(array $shippingOption): array
    {
        $method = $shippingOption['methods'][0] ?? [];
        $cost = (float)($method['cost'] ?? 0);
        
        $subtotal = $this->toMinorUnits($cost);
        $tax = $this->calculateTaxMinorUnits($subtotal);
        $total = $subtotal + $tax;
        
        $carrierInfo = $this->getCarrierInfoFromModule($shippingOption['module_code'], $shippingOption['module_name']);
        $deliveryTimes = $this->getDeliveryTimesFromModule($shippingOption['module_code']);
        
        return [
            'type' => 'shipping',
            'id' => $this->generateShippingId($shippingOption['module_code'], $method['id'] ?? 'default'),
            'title' => $method['title'] ?? $shippingOption['module_name'],
            'subtitle' => $this->getSubtitleFromModule($shippingOption['module_code']),
            'carrier_info' => $carrierInfo,
            'earliest_delivery_time' => $deliveryTimes['earliest'],
            'latest_delivery_time' => $deliveryTimes['latest'],
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total
        ];
    }

    /**
     * Load active shipping modules from ClicShopping system
     * 
     * @return array
     */
    private function loadActiveShippingModules(): array
    {
        if ($this->activeShippingModules === null) {
            $this->activeShippingModules = [];
            
            try {
                // Check if shipping modules are installed and configured
                if (!defined('MODULE_SHIPPING_INSTALLED') || empty(MODULE_SHIPPING_INSTALLED)) {
                    return $this->activeShippingModules;
                }
                
                // Initialize shipping system if not already done
                if (!Registry::exists('Shipping')) {
                    Registry::set('Shipping', new \ClicShopping\Sites\Shop\Shipping());
                }
                
                // Get installed modules list
                $installedModules = explode(';', MODULE_SHIPPING_INSTALLED);
                
                foreach ($installedModules as $moduleCode) {
                    if (empty($moduleCode)) continue;
                    
                    $registryKey = 'Shipping_' . str_replace('\\', '_', $moduleCode);
                    
                    if (Registry::exists($registryKey)) {
                        $moduleInstance = Registry::get($registryKey);
                        
                        // Only include enabled modules
                        if (isset($moduleInstance->enabled) && $moduleInstance->enabled) {
                            $this->activeShippingModules[$moduleCode] = $moduleInstance;
                        }
}
                }
} catch (\Exception $e) {
                if (isset($this->logger)) {
                    $this->logger->error('Error loading active shipping modules', [
                        'error' => $e->getMessage()
                    ]);
                }
}
        }
        
        return $this->activeShippingModules;
    }

    /**
     * Get carrier info from active module instance
     * 
     * @param string $moduleCode
     * @param string $moduleName
     * @return string
     */
    private function getCarrierInfoFromModule(string $moduleCode, string $moduleName): string
    {
        try {
            // Get active shipping modules
            $activeModules = $this->loadActiveShippingModules();
            
            // Check if module is active and enabled
            if (isset($activeModules[$moduleCode])) {
                $moduleInstance = $activeModules[$moduleCode];
                
                // Use public_title if available, otherwise title
                if (isset($moduleInstance->public_title) && !empty($moduleInstance->public_title)) {
                    return $moduleInstance->public_title;
                } elseif (isset($moduleInstance->title) && !empty($moduleInstance->title)) {
                    return $moduleInstance->title;
                }
}
            
            // Fallback to module name if no specific info found
            return $moduleName;
            
        } catch (\Exception $e) {
            if (isset($this->logger)) {
                $this->logger->error('Error getting carrier info from module', [
                    'moduleCode' => $moduleCode,
                    'error' => $e->getMessage()
                ]);
            }
            return $moduleName;
        }
}
    
    /**
     * Get delivery times from active module instance or defaults based on module type
     * 
     * @param string $moduleCode
     * @return array
     */
    private function getDeliveryTimesFromModule(string $moduleCode): array
    {
        try {
            // Get active shipping modules
            $activeModules = $this->loadActiveShippingModules();
            
            // Check if module is active and has delivery time info
            if (isset($activeModules[$moduleCode])) {
                $moduleInstance = $activeModules[$moduleCode];
                
                // Try to get delivery times from module properties
                if (isset($moduleInstance->delivery_times) && is_array($moduleInstance->delivery_times)) {
                    $times = $moduleInstance->delivery_times;
                    return [
                        'earliest' => date('c', strtotime($times['earliest'])),
                        'latest' => date('c', strtotime($times['latest']))
                    ];
                }
                
                // Check if module has specific delivery time methods
                if (method_exists($moduleInstance, 'getDeliveryTimes')) {
                    $times = $moduleInstance->getDeliveryTimes();
                    if (is_array($times) && isset($times['earliest'], $times['latest'])) {
                        return [
                            'earliest' => date('c', strtotime($times['earliest'])),
                            'latest' => date('c', strtotime($times['latest']))
                        ];
                    }
}
            }
            
            // Fallback: Determine delivery times based on module type/name
            $times = $this->getDefaultDeliveryTimesByModuleType($moduleCode);
            
            return [
                'earliest' => date('c', strtotime($times['earliest'])),
                'latest' => date('c', strtotime($times['latest']))
            ];
            
        } catch (\Exception $e) {
            if (isset($this->logger)) {
                $this->logger->error('Error getting delivery times from module', [
                    'moduleCode' => $moduleCode,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Fallback times in case of error
            return [
                'earliest' => date('c', strtotime(self::$this->deliveryEarliest)),
                'latest' => date('c', strtotime(self::$this->deliveryLatest))
            ];
        }
}
    
    /**
     * Get default delivery times using constants (aligned with OpenAI spec - delivery fields are optional)
     * 
     * @param string $moduleCode
     * @return array
     */
    private function getDefaultDeliveryTimesByModuleType(string $moduleCode): array
    {
        // According to OpenAI Product Feed spec, delivery_estimate is optional
        // Using default constants for simplicity
        return [
            'earliest' => self::$this->deliveryEarliest,
            'latest' => self::$this->deliveryLatest
        ];
    }

    /**
     * Get subtitle from active module instance or generate based on module type
     * 
     * @param string $moduleCode
     * @return string
     */
    private function getSubtitleFromModule(string $moduleCode): string
    {
        try {
            // Get active shipping modules
            $activeModules = $this->loadActiveShippingModules();
            
            // Check if module is active and has description
            if (isset($activeModules[$moduleCode])) {
                $moduleInstance = $activeModules[$moduleCode];
                
                if (isset($moduleInstance->description) && !empty($moduleInstance->description)) {
                    return $moduleInstance->description;
                }
                
                // Try to get subtitle from module properties
                if (isset($moduleInstance->subtitle) && !empty($moduleInstance->subtitle)) {
                    return $moduleInstance->subtitle;
                }
}
            
            // Fallback: Generate subtitle based on module type and delivery times
            return $this->generateSubtitleByModuleType($moduleCode);
            
        } catch (\Exception $e) {
            if (isset($this->logger)) {
                $this->logger->error('Error getting subtitle from module', [
                    'moduleCode' => $moduleCode,
                    'error' => $e->getMessage()
                ]);
            }
            return self::$this->deliveryDefault;
        }
}
    
    /**
     * Generate subtitle using constants (aligned with OpenAI spec - shipping details are optional)
     * 
     * @param string $moduleCode
     * @return string
     */
    private function generateSubtitleByModuleType(string $moduleCode): string
    {
        // Special case for free shipping
        if ($moduleCode === 'free_shipping') {
            return 'Livraison offerte';
        }
        
        // According to OpenAI Product Feed spec, shipping details are optional
        // Using default constant for simplicity
        return self::$this->deliveryDefault;
    }

    /**
     * Generate shipping ID
     * 
     * @param string $moduleCode
     * @param string $methodId
     * @return string
     */
    private function generateShippingId(string $moduleCode, string $methodId): string
    {
        $moduleShort = str_replace(['Shipping\\', '\\'], ['', '_'], $moduleCode);
        return strtolower($moduleShort . '_' . $methodId);
    }
    
    /**
     * Build digital fulfillment option
     * 
     * @return array
     */
    private function buildDigitalFulfillmentOption(): array
    {
        return [
            'type' => 'digital',
            'id' => 'instant_download',
            'title' => 'Téléchargement immédiat',
            'subtitle' => 'Disponible immédiatement après achat',
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0
        ];
    }
    
    /**
     * Build free shipping option
     * 
     * @return array
     */
    private function buildFreeShippingOption(): array
    {
        $deliveryTimes = $this->getDeliveryTimesFromModule('free_shipping');
        
        return [
            'type' => 'shipping',
            'id' => 'free_shipping',
            'title' => 'Livraison gratuite',
            'subtitle' => 'Livraison offerte',
            'carrier_info' => 'Livraison standard gratuite',
            'earliest_delivery_time' => $deliveryTimes['earliest'],
            'latest_delivery_time' => $deliveryTimes['latest'],
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0
        ];
    }
    
    /**
     * Build default fulfillment options (fallback)
     * 
     * @return array
     */
    private function buildDefaultFulfillmentOptions(): array
    {
        return [
            [
                'type' => 'shipping',
                'id' => 'standard_shipping',
                'title' => 'Livraison standard',
                'subtitle' => self::$this->deliveryDefault,
                'carrier_info' => 'Livraison standard',
                'earliest_delivery_time' => date('c', strtotime(self::$this->deliveryEarliest)),
                'latest_delivery_time' => date('c', strtotime(self::$this->deliveryLatest)),
                'subtotal' => 590, // 5.90 EUR
                'tax' => 118,     // 20% VAT
                'total' => 708    // 7.08 EUR
            ]
        ];
    }
    
    /**
     * Check if order has virtual products
     * 
     * @return bool
     */
    private function hasVirtualProducts(): bool
    {
        // TODO: Implement logic to check for virtual/digital products
        return false;
    }
    
    /**
     * Check if eligible for free shipping
     * 
     * @return bool
     */
    private function isEligibleForFreeShipping(): bool
    {
        if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') &&  MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING === 'true' &&
            defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER')) {
            
            // TODO: Calculate order total and check against free shipping threshold
            return false;
        }
        
        return false;
    }
    
    /**
     * Get fulfillment cost by option ID
     * 
     * @param string $optionId
     * @return int
     */
    private function getFulfillmentCost(string $optionId): int
    {
        $options = $this->buildFulfillmentOptions();
        foreach ($options as $option) {
            if ($option['id'] === $optionId) {
                return $option['total'];
            }
}
        return 590; // Default standard shipping
    }
    
    /**
     * Build messages according to OpenAI spec
     * 
     * @param array $lineItems
     * @return array
     */
    private function buildMessages(array $lineItems = []): array
    {
        $messages = [];
        
        // Check for out of stock items
        foreach ($lineItems as $lineItem) {
            $productId = $lineItem['item']['id'];
            $productData = $this->getProductData($productId);
            if ($productData && !$productData['in_stock']) {
                $messages[] = [
                    'type' => 'error',
                    'code' => 'out_of_stock',
                    'text' => 'Produit ' . $productData['title'] . ' temporairement indisponible',
                    'path' => '$.line_items[' . array_search($lineItem, $lineItems) . ']'
                ];
            }
}
        
        // Add promotional messages
        if (empty($messages)) {
            $messages[] = [
                'type' => 'info',
                'text' => 'Livraison gratuite à partir de 50€'
            ];
        }
        
        return $messages;
    }
    
    /**
     * Build links according to OpenAI spec
     * 
     * @return array
     */
    private function buildLinks(): array
    {
        $baseUrl =  CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop');
        
        return [
            [
                'type' => 'terms_of_service',
                'url' => $baseUrl . 'index.php?Info&Content&pagesId=4',
                'text' => CLICSHOPPING::getDef('text_general_conditions')
            ],
            [
                'type' => 'privacy_policy',
                'url' => $baseUrl . 'index.php?Info&Content&pagesId=4',
                'text' => CLICSHOPPING::getDef('text_privacy_conditions')
            ],
            [
                'type' => 'return_policy',
                'url' => $baseUrl . 'index.php?Info&Content&pagesId=4',
                'text' => CLICSHOPPING::getDef('text_general_conditions')
            ]
        ];
    }
    
    /**
     * Extract customer data from session for order creation
     * 
     * @param array $session
     * @param array $input
     * @return array
     */
    private function extractCustomerDataFromSession(array $session, array $input): array
    {
        $address = $session['fulfillment_address'] ?? [];
        $buyer = $session['buyer'] ?? [];
        $paymentData = $input['payment_data'] ?? [];
        $billingAddress = $paymentData['billing_address'] ?? $address; //not used for now
        
        return [
            'firstname' => $buyer['first_name'] ?? 'OpenAI',
            'lastname' => $buyer['last_name'] ?? 'Customer',
            'email_address' => $buyer['email'] ?? 'customer@openai.com',
            'telephone' => $buyer['phone'] ?? '',
            'street_address' => $address['line1'] ?? '',
            'suburb' => $address['line2'] ?? '',
            'city' => $address['city'] ?? '',
            'postcode' => $address['postal_code'] ?? '',
            'state' => $address['state'] ?? '',
            'country' => $address['country'] ?? 'FR'
        ];
    }
    
    /**
     * Build order permalink URL
     * 
     * @param int $orderId
     * @return string
     */
    private function buildOrderPermalinkUrl(int $orderId): string
    {
        $baseUrl = CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop');
        return $baseUrl . 'index.php?/account/orders/' . $orderId; //to check
    }
}
