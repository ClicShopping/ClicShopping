<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Classes\Shop;

use ClicShopping\OM\Registry;
use function count;

class FeaturedClass
{
  /**
   * Updates the featured status of a product in the database.
   *
   * @param int $products_featured_id The ID of the product to update the featured status for.
   * @param int $status The new status to set (1 for active, 0 for inactive).
   * @return mixed Returns the result of the save operation, or -1 if the status is invalid.
   */
  private static function setFeaturedStatus(int $products_featured_id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == '1') {
      return $CLICSHOPPING_Db->save('products_featured', [
        'status' => 1,
        'date_status_change' => 'now()',
        'scheduled_date' => 'null'
      ],
        ['products_featured_id' => (int)$products_featured_id]
      );

    } elseif ($status == '0') {

      return $CLICSHOPPING_Db->save('products_featured', [
        'status' => 0,
        'date_status_change' => 'now()',
        'scheduled_date' => 'null'
      ],
        ['products_featured_id' => (int)$products_featured_id]
      );
    } else {
      return -1;
    }
  }

  /**
   * Updates the status of featured products based on their scheduled date.
   *
   * This method queries the database for products in the featured products table
   * that have a scheduled date that is not null, have already reached or passed
   * their scheduled date, and are not currently active. For each eligible product,
   * the method sets the product's status to active.
   *
   * @return void
   */
  public static function scheduledFeatured(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QFeatured = $CLICSHOPPING_Db->query('select products_featured_id
                                            from :table_products_featured
                                            where scheduled_date is not null
                                            and scheduled_date <= now()
                                            and status <> 1
                                           ');

    $QFeatured->execute();
    $QFeatured->execute();

    if ($QFeatured->fetch() !== false) {
      do {
        static::setFeaturedStatus($QFeatured->valueInt('products_featured_id'), 1);
      } while ($QFeatured->fetch());
    }
  }

  /**
   * Expires featured products whose expiration date has passed and updates their status to inactive.
   *
   * @return void
   */
  public static function expireFeatured(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QFeatured = $CLICSHOPPING_Db->query('select products_featured_id
                                            from :table_products_featured
                                            where status = 1
                                            and expires_date is not null
                                            and now() >= expires_date
                                          ');

    $QFeatured->execute();

    if ($QFeatured->fetch() !== false) {
      do {
        static::setFeaturedStatus($QFeatured->valueInt('products_featured_id'), 0);
      } while ($QFeatured->fetch());
    }
  }

  /**
   * Retrieves a list of column keys based on the defined list of module settings.
   * The columns are determined by specific module configuration values being greater than zero.
   *
   * @return array An array of column keys that are enabled based on the module settings.
   */
  public static function getCountColumnList(): array
  {
// create column list
    $define_list = [
      'MODULE_PRODUCTS_FEATURED_LIST_DATE_ADDED' => MODULE_PRODUCTS_FEATURED_LIST_DATE_ADDED,
      'MODULE_PRODUCTS_FEATURED_LIST_PRICE' => MODULE_PRODUCTS_FEATURED_LIST_PRICE,
      'MODULE_PRODUCTS_FEATURED_LIST_MODEL' => MODULE_PRODUCTS_FEATURED_LIST_MODEL,
      'MODULE_PRODUCTS_FEATURED_LIST_WEIGHT' => MODULE_PRODUCTS_FEATURED_LIST_WEIGHT,
      'MODULE_PRODUCTS_FEATURED_LIST_QUANTITY' => MODULE_PRODUCTS_FEATURED_LIST_QUANTITY
    ];

    asort($define_list);

    $column_list = [];

    foreach ($define_list as $key => $value) {
      if ($value > 0) $column_list[] = $key;
    }

    return $column_list;
  }

  /**
   * Constructs and returns a database query string for generating a paginated listing of featured products
   * based on the customer's group, product attributes, and sort criteria.
   *
   * The method dynamically builds the query by using available product attributes and sorting options.
   * The query considers various conditions such as product status, customer group permission,
   * and category status.
   *
   * @return string The constructed SQL query string for fetching the featured product listing.
   */
  private static function Listing()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $Qlisting = 'select distinct SQL_CALC_FOUND_ROWS ';

    $count_column = static::getCountColumnList();

    for ($i = 0, $n = count($count_column); $i < $n; $i++) {
      switch ($count_column[$i]) {
        case 'MODULE_PRODUCTS_FEATURED_LIST_DATE_ADDED':
          $Qlisting .= ' p.products_date_added, ';
          break;
        case 'MODULE_PRODUCTS_FEATURED_LIST_PRICE':
          $Qlisting .= ' p.products_price, ';
          break;
        case 'MODULE_PRODUCTS_FEATURED_LIST_MODEL':
          $Qlisting .= ' p.products_model, ';
          break;
        case 'MODULE_PRODUCTS_FEATURED_LIST_WEIGHT':
          $Qlisting .= ' p.products_weight, ';
          break;
        case 'MODULE_PRODUCTS_FEATURED_LIST_QUANTITY':
          $Qlisting .= ' p.products_quantity, ';
          break;
      }
    }

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {

      $Qlisting .= ' p.products_id,
                       p.products_quantity
                    from :table_products p left join :table_products_groups g on p.products_id = g.products_id,
                         :table_products_featured pf,
                         :table_products_to_categories p2c,
                         :table_categories c
                    where p.products_status = 1
                    and g.price_group_view = 1                 
                    and pf.status = 1
                    and p.products_id = pf.products_id
                    and g.customers_group_id = :customers_group_id
                    and g.products_group_view = 1
                    and p.products_archive = 0
                    and pf.products_id = p.products_id
                    and (pf.customers_group_id = :customers_group_id or pf.customers_group_id = 99)
                    and p.products_id = p2c.products_id
                    and p2c.categories_id = c.categories_id
                    and c.virtual_categories = 0
                    and c.status = 1
                   ';
    } else {
      $Qlisting .= ' p.products_id,
                       p.products_quantity
                    from :table_products p,
                         :table_products_featured pf,
                         :table_products_to_categories p2c,
                         :table_categories c
                    where p.products_status = 1
                    and p.products_id = pf.products_id
                    and pf.status = 1
                    and p.products_view = 1
                    and p.products_archive = 0
                    and (pf.customers_group_id = 0 or pf.customers_group_id = 99)
                    and p.products_id = p2c.products_id
                    and p2c.categories_id = c.categories_id
                    and c.virtual_categories = 0
                    and c.status = 1
                   ';
    }

    if ((!isset($_GET['sort'])) || (!preg_match('/^[1-8][ad]$/', $_GET['sort'])) || (substr($_GET['sort'], 0, 1) > count($count_column))) {
      for ($i = 0, $n = count($count_column); $i < $n; $i++) {
        if ($count_column[$i] == 'MODULE_PRODUCTS_FEATURED_LIST_DATE_ADDED') {
          $_GET['sort'] = $i + 1 . 'a';
          $Qlisting .= ' order by p.products_date_added DESC ';
          break;
        }
      }
    } else {

      $sort_col = substr($_GET['sort'], 0, 1);
      $sort_order = substr($_GET['sort'], 1);

      switch ($count_column[$sort_col - 1]) {
        case 'MODULE_PRODUCTS_FEATURED_LIST_DATE_ADDED':
          $Qlisting .= ' order by p.products_date_added ' . ($sort_order == 'd' ? 'desc' : ' ');
          break;
        case 'MODULE_PRODUCTS_FEATURED_LIST_PRICE':
          $Qlisting .= ' order by p.products_price ' . ($sort_order == 'd' ? 'desc' : '') . ', p.products_date_added DESC ';
          break;
        case 'MODULE_PRODUCTS_FEATURED_LIST_MODEL':
          $Qlisting .= ' order by p.products_model ' . ($sort_order == 'd' ? 'desc' : '') . ', p.products_date_added DESC ';
          break;
        case 'MODULE_PRODUCTS_FEATURED_LIST_QUANTITY':
          $Qlisting .= ' order by p.products_quantity ' . ($sort_order == 'd' ? 'desc' : '') . ', p.products_date_added DESC ';
          break;
        case 'MODULE_PRODUCTS_FEATURED_LIST_WEIGHT':
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
   * Retrieves the listing data based on customer group ID and prepares the query.
   *
   * @return mixed Returns the prepared query object containing the listing data.
   */
  public static function getListing(): mixed
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qlisting = static::Listing();

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $QlistingFeatured = $CLICSHOPPING_Db->prepare($Qlisting);
      $QlistingFeatured->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
    } else {
      $QlistingFeatured = $CLICSHOPPING_Db->prepare($Qlisting);
    }

    return $QlistingFeatured;
  }
}