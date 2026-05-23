<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Classes\Shop;

use ClicShopping\OM\Registry;
use function count;

class FavoritesClass
{
  /**
   * Updates the status of a specific favorite product in the database.
   *
   * @param int $products_favorites_id The ID of the favorite product to update.
   * @param int $status The new status to set for the favorite product. Accepted values: 1 (active), 0 (inactive).
   * @return bool|int Returns true on successful update, false on failure, or -1 if an invalid status is provided.
   */
  private static function setFavoritesStatus(int $products_favorites_id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == '1') {
      return $CLICSHOPPING_Db->save('products_favorites', ['status' => 1,
        'date_status_change' => 'now()',
        'scheduled_date' => 'null'
      ],
        ['products_favorites_id' => (int)$products_favorites_id]
      );

    } elseif ($status == '0') {
      return $CLICSHOPPING_Db->save('products_favorites', ['status' => 0,
        'date_status_change' => 'now()',
        'scheduled_date' => 'null'
      ],
        ['products_favorites_id' => (int)$products_favorites_id]
      );
    } else {
      return -1;
    }
  }

  /**
   * Activates scheduled favorite products by updating their status when their scheduled date is due.
   *
   * @return void
   */
  public static function scheduledFavorites(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QFavorites = $CLICSHOPPING_Db->query('select products_favorites_id
                                              from :table_products_favorites
                                              where scheduled_date is not null
                                              and scheduled_date <= now()
                                              and status <> 1
                                             ');

    $QFavorites->execute();


    if ($QFavorites->fetch() !== false) {
      do {
        static::setFavoritesStatus($QFavorites->valueInt('products_favorites_id'), 1);
      } while ($QFavorites->fetch());
    }
  }
  /**
   * Expires favorite products by checking if their expiration date has passed
   * and updating their status accordingly.
   *
   * @return void
   */
// Auto expire products on products favorites
  public static function expireFavorites(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QFavorites = $CLICSHOPPING_Db->query('select products_favorites_id
                                              from :table_products_favorites
                                              where status = 1
                                              and expires_date is not null
                                              and now() >= expires_date
                                            ');

    $QFavorites->execute();

    if ($QFavorites->fetch() !== false) {
      do {
        static::setFavoritesStatus($QFavorites->valueInt('products_favorites_id'), 0);
      } while ($QFavorites->fetch());
    }
  }

  /**
   * Retrieves a list of column identifiers where the associated configuration value is greater than zero.
   * The columns are sorted based on their defined configuration ordering.
   *
   * @return array An array of column identifiers that meet the specified criteria.
   */
  public static function getCountColumnList(): array
  {
// create column list
    $define_list = [
      'MODULE_PRODUCTS_FAVORITES_LIST_DATE_ADDED' => MODULE_PRODUCTS_FAVORITES_LIST_DATE_ADDED,
      'MODULE_PRODUCTS_FAVORITES_LIST_PRICE' => MODULE_PRODUCTS_FAVORITES_LIST_PRICE,
      'MODULE_PRODUCTS_FAVORITES_LIST_MODEL' => MODULE_PRODUCTS_FAVORITES_LIST_MODEL,
      'MODULE_PRODUCTS_FAVORITES_LIST_WEIGHT' => MODULE_PRODUCTS_FAVORITES_LIST_WEIGHT,
      'MODULE_PRODUCTS_FAVORITES_LIST_QUANTITY' => MODULE_PRODUCTS_FAVORITES_LIST_QUANTITY
    ];

    asort($define_list);

    $column_list = [];

    foreach ($define_list as $key => $value) {
      if ($value > 0) $column_list[] = $key;
    }

    return $column_list;
  }

  /**
   * Generates an SQL query string for retrieving a listing of products based on configured conditions
   * such as customer group, products' attributes, and sorting order.
   *
   * @return string SQL query string for fetching products listing.
   */
  private static function Listing()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $Qlisting = 'select SQL_CALC_FOUND_ROWS ';

    $count_column = static::getCountColumnList();

    for ($i = 0, $n = count($count_column); $i < $n; $i++) {
      switch ($count_column[$i]) {
        case 'MODULE_PRODUCTS_FAVORITES_LIST_DATE_ADDED':
          $Qlisting .= ' p.products_date_added, ';
          break;
        case 'MODULE_PRODUCTS_FAVORITES_LIST_PRICE':
          $Qlisting .= ' p.products_price, ';
          break;
        case 'MODULE_PRODUCTS_FAVORITES_LIST_MODEL':
          $Qlisting .= ' p.products_model, ';
          break;
        case 'MODULE_PRODUCTS_FAVORITES_LIST_WEIGHT':
          $Qlisting .= ' p.products_weight, ';
          break;
        case 'MODULE_PRODUCTS_FAVORITES_LIST_QUANTITY':
          $Qlisting .= ' p.products_quantity, ';
          break;
      }
    }

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {

      $Qlisting .= ' p.products_id,
                       p.products_quantity as in_stock
                    from :table_products p  left join :table_products_groups g on p.products_id = g.products_id,
                         :table_products_favorites ph,
                         :table_products_to_categories p2c,
                         :table_categories c
                    where p.products_status = 1
                            and g.price_group_view = 1    
                            and ph.status = 1
                            and p.products_id = ph.products_id
                            and g.customers_group_id = :customers_group_id
                            and g.products_group_view = 1
                            and p.products_archive = 0
                            and ph.products_id = p.products_id
                            and (ph.customers_group_id = :customers_group_id or ph.customers_group_id = 99)
                            and p.products_id = p2c.products_id
                            and p2c.categories_id = c.categories_id
                            and c.virtual_categories = 0
                            and c.status = 1                          
                   ';
    } else {
      $Qlisting .= ' p.products_id,
                       p.products_quantity as in_stock
                    from :table_products p,
                         :table_products_favorites ph,
                         :table_products_to_categories p2c,
                         :table_categories c
                    where p.products_status = 1
                    and p.products_id = ph.products_id
                    and ph.status = 1
                    and p.products_view = 1
                    and p.products_archive = 0
                    and (ph.customers_group_id = 0 or ph.customers_group_id = 99)
                    and p.products_id = p2c.products_id
                    and p2c.categories_id = c.categories_id
                    and c.virtual_categories = 0
                    and c.status = 1
                   ';
    }

    if ((!isset($_GET['sort'])) || (!preg_match('/^[1-8][ad]$/', $_GET['sort'])) || (substr($_GET['sort'], 0, 1) > count($count_column))) {
      for ($i = 0, $n = count($count_column); $i < $n; $i++) {
        if ($count_column[$i] == 'MODULE_PRODUCTS_FAVORITES_LIST_DATE_ADDED') {
          $_GET['sort'] = $i + 1 . 'a';
          $Qlisting .= ' order by p.products_date_added DESC ';
          break;
        }
      }
    } else {
      $sort_col = substr($_GET['sort'], 0, 1);
      $sort_order = substr($_GET['sort'], 1);

      switch ($count_column[$sort_col - 1]) {
        case 'MODULE_PRODUCTS_FAVORITES_LIST_DATE_ADDED':
          $Qlisting .= ' order by p.products_date_added ' . ($sort_order == 'd' ? 'desc' : ' ');
          break;
        case 'MODULE_PRODUCTS_FAVORITES_LIST_PRICE':
          $Qlisting .= ' order by p.products_price ' . ($sort_order == 'd' ? 'desc' : '') . ', p.products_date_added DESC ';
          break;
        case 'MODULE_PRODUCTS_FAVORITES_LIST_MODEL':
          $Qlisting .= ' order by p.products_model ' . ($sort_order == 'd' ? 'desc' : '') . ', p.products_date_added DESC ';
          break;
        case 'MODULE_PRODUCTS_FAVORITES_LIST_QUANTITY':
          $Qlisting .= ' order by p.products_quantity ' . ($sort_order == 'd' ? 'desc' : '') . ', p.products_date_added DESC ';
          break;
        case 'MODULE_PRODUCTS_FAVORITES_LIST_WEIGHT':
          $Qlisting .= ' order by p.products_weight ' . ($sort_order == 'd' ? 'desc' : '') . ', p.products_date_added DESC ';
          break;
      }
    }

    $Qlisting .= ' limit :page_set_offset,
                           :page_set_max_results
                       ';

    return $Qlisting;
  }

  /**
   * Retrieves a listing from the database, optionally binding the customer group ID
   * if the current customer belongs to a specific group.
   *
   * @return mixed The prepared database query result for the listing.
   */
  public static function getListing(): mixed
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qlisting = static::Listing();

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $QlistingFavorites = $CLICSHOPPING_Db->prepare($Qlisting);
      $QlistingFavorites->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
    } else {
      $QlistingFavorites = $CLICSHOPPING_Db->prepare($Qlisting);
    }

    return $QlistingFavorites;
  }
}