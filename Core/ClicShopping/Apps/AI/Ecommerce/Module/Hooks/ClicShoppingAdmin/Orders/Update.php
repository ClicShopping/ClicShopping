<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Orders;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\Shared\OrderEmbeddingBuilder;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

class Update implements HooksInterface
{
  public mixed $app;
  public mixed $lang;
  public mixed $semantics;
  private OrderEmbeddingBuilder $builder;
  
  /**
   * Class constructor.
   *
   * Initializes the ChatGptApp instance in the Registry if it doesn't already exist,
   * and loads the necessary definitions for the application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
    $this->lang = Registry::get('Language');

    if (!Registry::exists('Semantics')) {
      Registry::set('Semantics', new SemanticAgent());
    }
    $this->semantics = Registry::get('Semantics');
    $this->builder = new OrderEmbeddingBuilder();

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Orders/rag');
  }


  /**
   * Executes the embedding process for order updates.
   *
   * This method checks the GPT status and whether the embedding feature is enabled.
   * If the conditions are met, it retrieves order details, products, attributes,
   * status history, totals, and returns. It then builds the embedding data and
   * saves it to the database.
   *
   * @return bool Returns false if conditions are not met, otherwise true.
   */
  public function execute()
  {
    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];

    if (!CLICSHOPPING::checkAppsIsActivated($requiredConstants)) {
      return false;
    }

    if (!Gpt::checkGptStatus()) {
      return false;
    }

    $embedding_enabled = \defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True' && \defined( 'CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True';

    if (!isset($_GET['Update'], $_GET['Orders'], $_GET['oID'])) {
      return false;
    }

    if ($embedding_enabled) {
      $order_id = HTML::sanitize($_GET['oID']);

      $result = $this->builder->regenerate((int)$order_id);

      if (!$result['success']) {
        error_log("Orders: Failed to save embeddings - " . $result['error']);
      } else {
        error_log("Orders: Successfully saved {$result['chunks_saved']} chunk(s) for order {$order_id}");
      }
    }
  }
}