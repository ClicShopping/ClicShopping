<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Module\Hooks\ClicShoppingAdmin\StatsDashboard;

use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

class PageTabContent implements HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Orders')) {
      Registry::set('Orders', new OrdersApp());
    }

    $this->app = Registry::get('Orders');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/StatsDashboard/page_tab_content');
  }

  /**
   * Amount excl. tax, excl. shipping, AFTER order discounts (TO - TX - SH): the base the AI uses.
   */
  private const NET_AMOUNT = '(select sum(case when ot.class = \'TO\' then ot.value else -ot.value end)
                               from :table_orders_total ot
                               where ot.orders_id = o.orders_id
                               and ot.class in (\'TO\', \'TX\', \'SH\'))';

  /**
   * Delivered orders, all time: net amount, order and customer counts.
   * A refunded order leaves the delivered status, so this total is already net of refunds.
   *
   * @return array{amount: float, orders: int, customers: int}
   */
  private function deliveredStats(): array
  {
    $Qdelivered = $this->app->db->prepare('select sum(' . self::NET_AMOUNT . ') as amount,
                                                  count(o.orders_id) as orders,
                                                  count(distinct o.customers_id) as customers
                                           from :table_orders o
                                           where o.orders_status = 3
                                          ');
    $Qdelivered->execute();

    return [
      'amount' => (float)$Qdelivered->valueDecimal('amount'),
      'orders' => $Qdelivered->valueInt('orders'),
      'customers' => $Qdelivered->valueInt('customers'),
    ];
  }

  /**
   * Refunded orders (revenue_sign = -1), all time: net amount.
   *
   * @return float
   */
  private function refundedAmount(): float
  {
    $Qrefunded = $this->app->db->prepare('select sum(' . self::NET_AMOUNT . ') as amount
                                          from :table_orders o
                                          where o.orders_status in (select orders_status_id
                                                                    from :table_orders_status
                                                                    where revenue_sign = -1)
                                         ');
    $Qrefunded->execute();

    return (float)$Qrefunded->valueDecimal('amount');
  }

  /**
   * @param string $label
   * @param float $value
   * @return string
   */
  private function row(string $label, float $value): string
  {
    return '
        <div class="row">
          <div class="col-md-11 mainTable">
            <div class="form-group row">
              <label for="' . $label . '" class="col-9 col-form-label"><a href="' . $this->app->link('Orders') . '">' . $label . '</a></label>
              <div class="col-md-3">
                ' . number_format($value, 2, '.', '') . '
              </div>
            </div>
          </div>
        </div>
       ';
  }

  /**
   * @return false|string
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_ORDERS_OD_STATUS') || CLICSHOPPING_APP_ORDERS_OD_STATUS == 'False') {
      return false;
    }

    $delivered = $this->deliveredStats();

    if ($delivered['amount'] <= 0) {
      return false;
    }

    $content = $this->row($this->app->getDef('box_entry_basket'), round($delivered['amount'] / $delivered['customers'], 2));
    $content .= $this->row($this->app->getDef('box_entry_order_total_delivery'), round($delivered['amount'] / $delivered['orders'], 2));

    $refunded = $this->refundedAmount();

    if ($refunded > 0) {
      $content .= $this->row($this->app->getDef('box_entry_refunded'), round($refunded, 2));
    }

    return <<<EOD
  <!-- ######################## -->
  <!--  Start orders     -->
  <!-- ######################## -->
             {$content}
  <!-- ######################## -->
  <!--  Start orders      -->
  <!-- ######################## -->
EOD;
  }
}
