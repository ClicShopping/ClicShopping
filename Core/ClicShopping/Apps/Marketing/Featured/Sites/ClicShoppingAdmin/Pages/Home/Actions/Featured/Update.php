<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Sites\ClicShoppingAdmin\Pages\Home\Actions\Featured;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {

    $CLICSHOPPING_Featured = Registry::get('Featured');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    $products_featured_id = HTML::sanitize($_POST['products_featured_id']);

    if (!empty($_POST['expdate'])) {
      $expdate = HTML::sanitize($_POST['expdate']);
    } else {
      $expdate = null;
    }

    if (!empty($_POST['expdate'])) {
      $schdate = HTML::sanitize($_POST['schdate']);
    } else {
      $schdate = null;
    }

    $Qupdate = $CLICSHOPPING_Featured->db->prepare('update :table_products_featured
                                                      set products_featured_last_modified = now(),
                                                          expires_date = :expires_date,
                                                          scheduled_date = :scheduled_date
                                                      where products_featured_id = :products_featured_id
                                                    ');
    $Qupdate->bindValue(':expires_date', $expdate);
    $Qupdate->bindValue(':scheduled_date', $schdate);
    $Qupdate->bindInt(':products_featured_id', $products_featured_id);

    $Qupdate->execute();

    $CLICSHOPPING_Hooks->call('Featured', 'Update');

    $CLICSHOPPING_Featured->redirect('Featured', 'page=' . $page . '&sID=' . $products_featured_id);
  }
}