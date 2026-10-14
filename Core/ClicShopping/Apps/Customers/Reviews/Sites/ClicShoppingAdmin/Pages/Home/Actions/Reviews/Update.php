<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\ClicShoppingAdmin\Pages\Home\Actions\Reviews;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['rID'])) {
      $reviews_id = (int)HTML::sanitize($_GET['rID']);

      $reviews_text = isset($_POST['reviews_text']) ? HTML::sanitize($_POST['reviews_text']) : '';
      $reviews_status = isset($_POST['status']) ? (int)HTML::sanitize($_POST['status']) : 0;
      $languages_id = isset($_POST['languages_id']) ? (int)HTML::sanitize($_POST['languages_id']) : (int)Registry::get('Language')->getId();

      $sql_array = [
        'status' => (int)$reviews_status,
        'last_modified' => 'now()'
      ];

      $CLICSHOPPING_Reviews->db->save('reviews', $sql_array, ['reviews_id' => (int)$reviews_id]);

      $sql_array = [
        'reviews_text' => $reviews_text,
        'languages_id' => (int)$languages_id,
      ];

      $CLICSHOPPING_Reviews->db->save('reviews_description', $sql_array, ['reviews_id' => (int)$reviews_id]);

      $CLICSHOPPING_Hooks->call('Reviews', 'Update');

      $CLICSHOPPING_Reviews->redirect('Reviews&page=' . $page);
    }
  }
}