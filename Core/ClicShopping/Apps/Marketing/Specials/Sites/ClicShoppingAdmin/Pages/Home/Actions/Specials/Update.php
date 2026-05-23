<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Sites\ClicShoppingAdmin\Pages\Home\Actions\Specials;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {

    $CLICSHOPPING_Specials = Registry::get('Specials');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    $status = 1;

    if (isset($_POST['specials_id'])) $specials_id = HTML::sanitize($_POST['specials_id']);
    if (isset($_POST['products_price'])) $products_price = HTML::sanitize($_POST['products_price']);
    if (isset($_POST['specials_price'])) $specials_price = HTML::sanitize($_POST['specials_price']);

    if (!empty($_POST['expdate'])) {
      $expdate = HTML::sanitize($_POST['expdate']);
    } else {
      $expdate = null;
    }

    if (!empty($_POST['schdate'])) {
      $schdate = HTML::sanitize($_POST['schdate']);

      $date1 = new \DateTimeImmutable(date('Y-m-d'));
      $date2 = new \DateTimeImmutable($schdate);

      if ($date1 < $date2) {
        $status = 0;
      }
    } else {
      $schdate = null;
    }

    if (isset($_POST['flash_discount']) && HTML::sanitize($_POST['flash_discount']) == 1) {
      $flash_discount = 1;
    } else {
      $flash_discount = 0;
    }

    if (substr($specials_price, -1) == '%') {
      $specials_price = str_replace('%', '', $specials_price);
      $specials_price = ($products_price - (($specials_price / 100) * $products_price));
    }

    $Qupdate = $CLICSHOPPING_Specials->db->prepare('update :table_specials
                                                      set specials_new_products_price = :specials_new_products_price,
                                                          specials_last_modified = now(),
                                                          expires_date = :expires_date,
                                                          scheduled_date = :scheduled_date,
                                                          flash_discount = :flash_discount,
                                                          status = :status
                                                      where specials_id = :specials_id
                                                    ');
    $Qupdate->bindDecimal(':specials_new_products_price', $specials_price);
    $Qupdate->bindValue(':expires_date', $expdate);
    $Qupdate->bindValue(':scheduled_date', $schdate);
    $Qupdate->bindInt(':flash_discount', $flash_discount);
    $Qupdate->bindInt(':status', $status);
    $Qupdate->bindInt(':specials_id', $specials_id);

    $Qupdate->execute();

    $CLICSHOPPING_Hooks->call('Specials', 'Update');

    $CLICSHOPPING_Specials->redirect('Specials&page=', $page . '&sID=' . $specials_id);
  }
}