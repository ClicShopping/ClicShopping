<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\ReviewsSentiment;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\ReviewSentimentGenerator;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

/**
 * ReviewsSentiment/Update — manual admin trigger for a product's sentiment analysis.
 *
 * Thin hook: the whole generation + persistence + macro RAG embedding lives in the
 * shared {@see ReviewSentimentGenerator} (identical to the cron path). This hook only
 * adds the preliminary checks, the GPT guardrail and the admin messages.
 */
class Update implements HooksInterface
{
  public mixed $app;
  protected mixed $messageStack;

  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
    $this->messageStack = Registry::get('MessageStack');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/ReviewsSentiment/review_sentiment');
  }

  /**
   * Hook entry point.
   *
   * @return bool|void  false if GPT is unavailable or the review threshold is not met.
   */
  public function execute()
  {
    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];

    CLICSHOPPING::checkAppsIsActivated($requiredConstants);

    if (!Gpt::checkGptStatus()) {
      $this->messageStack->add($this->app->getDef('text_warning_gpt_disabled'), 'warning');
      return false;
    }

    $id = HTML::sanitize($_GET['rID']);
    $user_admin = AdministratorAdmin::getUserAdmin();

    $products_id = $this->app->db->get('reviews', 'products_id', ['reviews_id' => (int)$id])->valueInt('products_id');

    $result = (new ReviewSentimentGenerator())->generateForProduct($products_id, (int)$id, $user_admin);

    if ($result === null) {
      $this->messageStack->add($this->app->getDef('text_warning_mesage', ['review_number' => ReviewSentimentGenerator::MIN_REVIEWS]), 'warning');

      return false;
    }

    $this->messageStack->add($this->app->getDef('text_succes_mesage'), 'success');
  }
}
