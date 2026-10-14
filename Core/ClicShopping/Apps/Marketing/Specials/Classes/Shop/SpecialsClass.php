<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Classes\Shop;

use ClicShopping\OM\Registry;

class SpecialsClass
{
  /**
   * Updates the status of a special in the specials table based on the provided status value.
   *
   * @param int $specials_id The ID of the special to be updated.
   * @param int $status The new status to set for the special (1 for active, 0 for inactive).
   * @return int Returns the number of affected rows on successful update or -1 if the provided status is invalid.
   */
  private static function setSpecialsStatus(int $specials_id, int $status): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == '1') {

      return $CLICSHOPPING_Db->save('specials', ['status' => 1,
        'date_status_change' => 'now()',
        'scheduled_date' => 'null'
      ],
        ['specials_id' => (int)$specials_id]
      );

    } elseif ($status == '0') {

      return $CLICSHOPPING_Db->save('specials', ['status' => 0,
        'date_status_change' => 'now()',
        'scheduled_date' => 'null',
        'flash_discount' => 0
      ],
        ['specials_id' => (int)$specials_id]
      );
    } else {
      return -1;
    }
  }

  /**
   * Activates scheduled specials by changing their status to active if the scheduled date has passed
   * and the status is not already set to active.
   *
   * @return void
   */
  public static function scheduledSpecials(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qspecials = $CLICSHOPPING_Db->query('select specials_id
                                            from :table_specials
                                            where scheduled_date is not null
                                            and scheduled_date <= now()
                                            and status <> 1
                                           ');

    $Qspecials->execute();

    if ($Qspecials->fetch() !== false) {
      do {
        static::setSpecialsStatus($Qspecials->valueInt('specials_id'), 1);
      } while ($Qspecials->fetch());
    }
  }

  /**
   * Expires all active special offers that have passed their expiration date.
   *
   * @return void
   */
  public static function expireSpecials(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qspecials = $CLICSHOPPING_Db->query('select specials_id
                                            from :table_specials
                                            where status = 1
                                            and expires_date is not null
                                            and now() >= expires_date
                                          ');

    $Qspecials->execute();

    if ($Qspecials->fetch() !== false) {
      do {
        static::setSpecialsStatus($Qspecials->valueInt('specials_id'), 0);
      } while ($Qspecials->fetch());
    }
  }

  /**
   * Retrieves a list of column identifiers based on the defined list and their respective statuses.
   * The method creates a sorted list of columns and filters them by their status being greater than zero.
   *
   * @return array Returns an array of column identifiers where the status is greater than zero.
   */
  public static function getCountColumnList(): array
  {
// create column list
    $define_list = [
      'MODULE_PRODUCTS_SPECIAL_LIST_DATE_ADDED' => MODULE_PRODUCTS_SPECIAL_LIST_DATE_ADDED,
      'MODULE_PRODUCTS_SPECIAL_LIST_PRICE' => MODULE_PRODUCTS_SPECIAL_LIST_PRICE,
      'MODULE_PRODUCTS_SPECIAL_LIST_MODEL' => MODULE_PRODUCTS_SPECIAL_LIST_MODEL,
      'MODULE_PRODUCTS_SPECIAL_LIST_WEIGHT' => MODULE_PRODUCTS_SPECIAL_LIST_WEIGHT,
      'MODULE_PRODUCTS_SPECIAL_LIST_QUANTITY' => MODULE_PRODUCTS_SPECIAL_LIST_QUANTITY,
    ];

    asort($define_list);

    $column_list = [];

    foreach ($define_list as $key => $value) {
      if ($value > 0) $column_list[] = $key;
    }

    return $column_list;
  }

  /**
   * Constructs and returns an SQL query string for listing special products.
   *
   * This method dynamically builds a query string based on the current customer group,
   * product attributes, and sorting preferences provided through the application.
   *
   * @return string The constructed SQL query string for special product listings.
   */
  private static function Listing()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $Qlisting = 'select SQL_CALC_FOUND_ROWS ';

    $count_column = static::getCountColumnList();

    for ($i = 0, $n = \count($count_column); $i < $n; $i++) {
      switch ($count_column[$i]) {
        case 'MODULE_PRODUCTS_SPECIAL_LIST_DATE_ADDED':
          $Qlisting .= ' p.products_date_added, ';
          break;
        case 'MODULE_PRODUCTS_SPECIAL_LIST_PRICE':
          $Qlisting .= ' s.specials_new_products_price, ';
          break;
        case 'MODULE_PRODUCTS_SPECIAL_LIST_MODEL':
          $Qlisting .= ' p.products_model, ';
          break;
        case 'MODULE_PRODUCTS_SPECIAL_LIST_WEIGHT':
          $Qlisting .= ' p.products_weight, ';
          break;
        case 'MODULE_PRODUCTS_SPECIAL_LIST_QUANTITY':
          $Qlisting .= ' p.products_quantity, ';
          break;
      }
    }

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $Qlisting .= '  p.products_id,
                        p.products_quantity
                   from :table_products p join :table_products_groups g on p.products_id = g.products_id,
                        :table_specials s,
                        :table_products_to_categories p2c,
                        :table_categories c
                   where p.products_status = 1
                   and g.price_group_view = 1
                   and g.customers_group_id = :customers_group_id
                   and g.products_group_view = 1
                   and s.products_id = p.products_id
                   and s.status = 1
                   and (s.customers_group_id = :customers_group_id or s.customers_group_id = 99)
                   and p.products_archive = 0
                   and p.products_id = p2c.products_id
                   and p2c.categories_id = c.categories_id
                   and c.status = 1
                   ';
    } else {
      $Qlisting .= '   p.products_id,
                         p.products_quantity
                       from  :table_specials s,
                             :table_products p,
                              :table_products_to_categories p2c,
                              :table_categories c
                       where s.products_id = p.products_id
                       and p.products_status = 1
                       and s.status = 1
                       and p.products_view = 1
                       and (s.customers_group_id = 0 or s.customers_group_id = 99)
                       and p.products_archive = 0
                       and p.products_id = p2c.products_id
                       and p2c.categories_id = c.categories_id
                       and c.status = 1
                    ';
    }

    if ((!isset($_GET['sort'])) || (!preg_match('/^[1-8][ad]$/', $_GET['sort'])) || (substr($_GET['sort'], 0, 1) > \count($count_column))) {
      for ($i = 0, $n = \count($count_column); $i < $n; $i++) {
        if ($count_column[$i] == 'MODULE_PRODUCTS_SPECIAL_LIST_DATE_ADDED') {
          $_GET['sort'] = $i + 1 . 'a';
          $Qlisting .= ' order by p.products_date_added DESC ';
          break;
        }
      }
    } else {

      $sort_col = substr($_GET['sort'], 0, 1);
      $sort_order = substr($_GET['sort'], 1);

      switch ($count_column[$sort_col - 1]) {
        case 'MODULE_PRODUCTS_SPECIAL_LIST_DATE_ADDED':
          $Qlisting .= ' order by s.specials_date_added ' . ($sort_order == 'd' ? 'desc' : ' ');
          break;
        case 'MODULE_PRODUCTS_SPECIAL_LIST_PRICE':
          $Qlisting .= ' order by s.specials_new_products_price ' . ($sort_order == 'd' ? 'desc' : '') . ', s.specials_date_added DESC ';
          break;
        case 'MODULE_PRODUCTS_SPECIAL_LIST_MODEL':
          $Qlisting .= ' order by p.products_model ' . ($sort_order == 'd' ? 'desc' : '') . ', s.specials_date_added DESC ';
          break;
        case 'MODULE_PRODUCTS_SPECIAL_LIST_QUANTITY':
          $Qlisting .= ' order by p.products_quantity ' . ($sort_order == 'd' ? 'desc' : '') . ', s.specials_date_added DESC ';
          break;
        case 'MODULE_PRODUCTS_SPECIAL_LIST_WEIGHT':
          $Qlisting .= ' order by p.products_weight ' . ($sort_order == 'd' ? 'desc' : '') . ', s.specials_date_added DESC ';
          break;
      }
    }

    $Qlisting .= ' limit :page_set_offset,
                           :page_set_max_results
                  ';
    return $Qlisting;
  }

  /**
   * Retrieves the prepared database query for a listing, applying specific customer group conditions if applicable.
   *
   * @return mixed Returns the prepared query object for the listing.
   */
  public static function getListing(): mixed
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qlisting = static::Listing();

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $QlistingSpecials = $CLICSHOPPING_Db->prepare($Qlisting);
      $QlistingSpecials->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
    } else {
      $QlistingSpecials = $CLICSHOPPING_Db->prepare($Qlisting);
    }

    return $QlistingSpecials;
  }
}
