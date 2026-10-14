<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce\Sub;

use ClicShopping\Apps\Tools\MCP\Classes\Shop\EndPoint\Products as ProductsEndPoint;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;

/**
 * Products sub-handler for the AnthropicEcommerce MCP endpoint.
 *
 * Delegates all product logic to the shared EndPoint\Products class,
 * which already handles DB queries, NLP search intent, categories,
 * recommendations and stats.
 *
 * Supported actions:
 *   - products        : paginated product listing
 *   - product         : single product detail by ?id=
 *   - search          : NLP keyword search via ?query= or ?q=
 *   - categories      : full category tree
 *   - recommendations : featured / recent products
 *   - stats           : catalogue statistics
 */
class Products
{
  private Message          $message;
  private ProductsEndPoint $endpoint;

  public function __construct(mixed $db, Message $message)
  {
    $this->message  = $message;
    $this->endpoint = new ProductsEndPoint();
  }

  // =========================================================================
  // Dispatcher
  // =========================================================================

  public function dispatch(string $action): void
  {
    match ($action) {
      'products'        => $this->endpoint->getProductsList(),
      'product'         => $this->endpoint->getProductDetail(),
      'search'          => $this->endpoint->handleSearchQuery(),
      'categories'      => $this->endpoint->getCategories(),
      'recommendations' => $this->endpoint->getProductRecommendations(),
      'stats'           => $this->endpoint->getProductStats(),
      default           => $this->message->sendError('Unknown product action: ' . $action, 400),
    };
  }
}
