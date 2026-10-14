<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');

spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('Shop');

if (isset($_POST['reviewId'], $_POST['product_id'])) {
  $CLICSHOPPING_Db = Registry::get('Db');

  $reviews_id = is_numeric($_POST['reviewId']) ? (int)HTML::sanitize($_POST['reviewId']) : 0;
  $products_id = HTML::sanitize($_POST['product_id']);
  $vote = HTML::sanitize($_POST['vote']);
  $customer_id = HTML::sanitize($_POST['customer_id']);

  if ($reviews_id === 0) {
    $array = [
      'products_id' => (int)$products_id,
      'reviews_id' => 0,
      'vote' => (int)$vote,
      'customer_id' => (int)$customer_id,
      'sentiment' => (int)$vote
    ];
  } else {
    $array = [
      'products_id' => (int)$products_id,
      'reviews_id' => (int)$reviews_id,
      'vote' => (int)$vote,
      'customer_id' => (int)$customer_id,
      'sentiment' => 0
    ];
  }

  $CLICSHOPPING_Db->save('reviews_vote', $array);

  // Récupérer les nouveaux compteurs après l'insertion
  $QyesCount = $CLICSHOPPING_Db->prepare('SELECT COUNT(*) as count FROM :table_reviews_vote WHERE reviews_id = :reviews_id AND vote = 1');
  $QyesCount->bindInt(':reviews_id', $reviews_id);
  $QyesCount->execute();
  $yesCount = $QyesCount->valueInt('count');

  $QnoCount = $CLICSHOPPING_Db->prepare('SELECT COUNT(*) as count FROM :table_reviews_vote WHERE reviews_id = :reviews_id AND vote = 0');
  $QnoCount->bindInt(':reviews_id', $reviews_id);
  $QnoCount->execute();
  $noCount = $QnoCount->valueInt('count');

  // Retourner la réponse JSON
  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'yesCount' => $yesCount,
    'noCount' => $noCount,
    'message' => 'Vote enregistré avec succès'
  ]);
  exit;
} else {
  // Erreur si paramètres manquants
  header('Content-Type: application/json');
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => 'Paramètres manquants'
  ]);
  exit;
}