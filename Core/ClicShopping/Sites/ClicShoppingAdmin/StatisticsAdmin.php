<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
/**
 * Calculates the total revenue from all years.
 *
 * The method retrieves the total sales amount by summing up the product prices multiplied
 * by the quantities for all orders matching the specified status across all years.
 *
 * @return int|mixed Total revenue or 0 if no data is found.
 */
class StatisticsAdmin
{
  /**
   * Calculates the total revenue for all orders in the current year, considering only orders
   * with a specific status.
   *
   * @return float The total calculated revenue for all eligible orders in the current year.
   */
  public static function statsTotalCaAllYear()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaTotal = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                from :table_orders o,
                                                     :table_orders_products op
                                                where o.orders_id = op.orders_id
                                                and o.orders_status = :orders_status
                                                and  (YEAR(o.date_purchased))
                                              ');
    $QcaTotal->bindInt(':orders_status', 3);
    $QcaTotal->execute();
    $ca_total = $QcaTotal->fetch();

    $ca_total = $ca_total['psum'];

    if (($ca_total == '') || ($ca_total == 0)) $ca_total = 0;
    return $ca_total;
  }


// CA annee en cours
// stat0
  /**
   * Retrieves the total sum of products' final prices multiplied by their quantities
   * for all orders with a specific status in the current year.
   *
   * @return float The total calculated value for the current year. Returns 0 if no matching data is found.
   */
  public static function statCurrentYear()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaYear = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                              from :table_orders o,
                                                   :table_orders_products op
                                              where o.orders_id = op.orders_id
                                              and o.orders_status = :orders_status
                                              and  ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                             ');
    $QcaYear->bindInt(':orders_status', 3);
    $QcaYear->execute();
    $ca_year = $QcaYear->fetch();

    $ca_year = $ca_year['psum'];
    if (($ca_year == '') || ($ca_year == 0)) $ca_year = 0;
    return $ca_year;
  }


// CA annee n-1
// stat1
  /**
   * Calculates the total sales from orders placed last year.
   *
   * @return float The sum of final prices multiplied by their quantities for orders with a specific status, placed in the last calendar year. Returns 0 if no sales are found.
   */
  public static function statLastYear()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaYear1 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                from :table_orders o,
                                                     :table_orders_products op
                                                where o.orders_id = op.orders_id
                                                and o.orders_status = :orders_status
                                                and ((YEAR(o.date_purchased)) >= (YEAR(CURRENT_DATE))-1)
                                                and ((YEAR(o.date_purchased)) < (YEAR(CURRENT_DATE)))
                                              ');
    $QcaYear1->bindInt(':orders_status', 3);
    $QcaYear1->execute();
    $ca_year1 = $QcaYear1->fetch();

    $ca_year1 = $ca_year1['psum'];
    if (($ca_year1 == '') || ($ca_year1 == 0)) $ca_year1 = 0;
    return $ca_year1;
  }

// Ca annee n-2
// stat2
  /**
   * Retrieves the total sales amount from the previous year (N-2) based on the provided orders status.
   *
   * The method calculates the sum of final prices multiplied by quantities of products
   * in orders made during the year (N-2), where N is the current year.
   * It returns 0 if no data is found or if the calculated total is empty.
   *
   * @return float The total sales amount for the year N-2.
   */
  public static function statYearN2()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaYear2 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                from :table_orders o,
                                                     :table_orders_products op
                                                where o.orders_id = op.orders_id
                                                and o.orders_status = :orders_status
                                                and ((YEAR(o.date_purchased)) >= (YEAR(CURRENT_DATE))-2)
                                                and ((YEAR(o.date_purchased)) < (YEAR(CURRENT_DATE))-1)
                                              ');
    $QcaYear2->bindInt(':orders_status', 3);
    $QcaYear2->execute();
    $ca_year2 = $QcaYear2->fetch();

    $ca_year2 = $ca_year2['psum'];
    if (($ca_year2 == '') || ($ca_year2 == 0)) $ca_year2 = 0;
    return $ca_year2;
  }

// CA annee n-3
// stat3
  /**
   * Retrieves the total sales amount for the year that is three years ago.
   * Calculates the sum of the product of final prices and product quantities for orders
   * with a specific status, placed within the year that is three years prior to the current year.
   *
   * @return float The total sales amount for the specified year. Returns 0 if no sales are found.
   */
  public static function statYearN3()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaYear3 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                              from :table_orders o,
                                                   :table_orders_products op
                                              where o.orders_id = op.orders_id
                                              and o.orders_status = :orders_status
                                              and ((YEAR(o.date_purchased)) >= (YEAR(CURRENT_DATE))-3)
                                              and  ((YEAR(o.date_purchased)) < (YEAR(CURRENT_DATE))-2)
                                             ');
    $QcaYear3->bindInt(':orders_status', 3);
    $QcaYear3->execute();
    $ca_year3 = $QcaYear3->fetch();

    $ca_year3 = $ca_year3['psum'];
    if (($ca_year3 == '') || ($ca_year3 == 0)) $ca_year3 = 0;
    return $ca_year3;
  }

//
// Statististique sur nbr de client
//

//annee
//  stat_customers_annee
  /**
   * Calculates the total number of unique customers for the current year based on completed orders.
   *
   * The method queries the database to count the number of customers who have placed an order
   * with a specified order status during the current calendar year.
   *
   * @return int The count of unique customers for the current year. Returns 0 if no customers are found.
   */
  public static function statCustomerCurrentYear()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QCustomersTotalYear = $CLICSHOPPING_Db->prepare('select count(customers_id) as customers
                                                        from :table_orders o,
                                                             :table_orders_products op
                                                        where o.orders_id = op.orders_id
                                                        and o.orders_status = :orders_status
                                                        and  ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                                      ');
    $QCustomersTotalYear->bindInt(':orders_status', 3);
    $QCustomersTotalYear->execute();
    $customers_total_year = $QCustomersTotalYear->fetch();

    $customers_total_year = $customers_total_year['customers'];

    return $customers_total_year;
  }

//annee n-1
// stat_customers_annee1
  /**
   * Retrieves the total number of unique customers from orders completed last year.
   *
   * This method queries the orders and orders_products tables to count the total number
   * of unique customers whose orders were completed in the previous calendar year.
   * It considers only orders with a specific status, which is provided as a parameter.
   *
   * @return int The count of unique customers from the previous year. Returns 0 if no customers are found.
   */
  public static function statCustomerLastYear()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QCustomersTotalYear1 = $CLICSHOPPING_Db->prepare('select count(customers_id) as customers
                                                          from :table_orders o,
                                                               :table_orders_products op
                                                          where o.orders_id = op.orders_id
                                                          and o.orders_status = :orders_status
                                                          and  ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)-1))
                                                        ');
    $QCustomersTotalYear1->bindInt(':orders_status', 3);
    $QCustomersTotalYear1->execute();
    $customers_total_year1 = $QCustomersTotalYear1->fetch();

    $customers_total_year1 = $customers_total_year1['customers'];

    return $customers_total_year1;
  }

// anneee n-2
// stat_customers_annee2
  /**
   * Retrieves the total number of customers for orders placed in the year N-2.
   *
   * This method calculates the total count of unique customers who made purchases
   * in the year two years prior to the current year, filtering by a specific order status.
   *
   * @return int The total count of customers for orders placed in year N-2. Returns 0 if no customers are found.
   */
  public static function statCustomerYearN2()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QCustomersTotalYear2 = $CLICSHOPPING_Db->prepare('select count(customers_id) as customers
                                                          from :table_orders o,
                                                               :table_orders_products op
                                                          where o.orders_id = op.orders_id
                                                          and o.orders_status = :orders_status
                                                          and  ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)-2))
                                                         ');
    $QCustomersTotalYear2->bindInt(':orders_status', 3);
    $QCustomersTotalYear2->execute();
    $customers_total_year2 = $QCustomersTotalYear2->fetch();

    $customers_total_year2 = $customers_total_year2['customers'];

    return $customers_total_year2;
  }

//annee n-3
// stat_customers_annee3
  /**
   * Calculates the total number of customers who placed orders three years ago, based on the current year.
   *
   * This method retrieves the count of customers who made purchases during the year
   * that is three years prior to the current year. The count is limited to orders
   * with a specific order status.
   *
   * @return int The total number of customers for the specified year. Returns 0 if no customers are found.
   */
  public static function statCustomerYearN3()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QCustomersTotalYear3 = $CLICSHOPPING_Db->prepare('select count(customers_id) as customers
                                                          from :table_orders o,
                                                               :table_orders_products op
                                                          where o.orders_id = op.orders_id
                                                          and o.orders_status = :orders_status
                                                          and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)-3))
                                                        ');
    $QCustomersTotalYear3->bindInt(':orders_status', 3);
    $QCustomersTotalYear3->execute();
    $customers_total_year3 = $QCustomersTotalYear3->fetch();

    $customers_total_year3 = $customers_total_year3['customers'];

    return $customers_total_year3;
  }


//
// Statistiques par mois
//

// Ca mois janvier
// stat1_month
  /**
   * Calculates the total revenue for the month of January by summing the final prices of products
   * multiplied by their quantities in completed orders.
   *
   * @return float The total revenue for January. Returns 0 if no data is available.
   */
  public static function statMonthJanuary()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth1 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                  from :table_orders o,
                                                       :table_orders_products op
                                                  where o.orders_id = op.orders_id
                                                  and o.orders_status = :orders_status
                                                  and  ((MONTH(o.date_purchased))) = 1
                                                  and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                                ');
    $QcaMonth1->bindInt(':orders_status', 3);
    $QcaMonth1->execute();
    $month_janvier = $QcaMonth1->fetch();

    $month_janvier = $month_janvier['psum'];

    if (($month_janvier == '') || ($month_janvier == 0)) $month_janvier = 0;
    return $month_janvier;
  }

// Ca mois fevrier
// stat2_month
  /**
   * Calculates and returns the total revenue generated in the month of February
   * for orders with a specific status in the current year. The revenue is computed
   * by summing the product of final price and quantity for all qualifying orders.
   *
   * @return float The total revenue for February. If no revenue is found, returns 0.
   */
  public static function statMonthFebruary()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth2 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                from :table_orders o,
                                                     :table_orders_products op
                                                where o.orders_id = op.orders_id
                                                and o.orders_status = :orders_status
                                                and  ((MONTH(o.date_purchased))) = 2
                                                and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                               ');
    $QcaMonth2->bindInt(':orders_status', 3);
    $QcaMonth2->execute();
    $month_fevrier = $QcaMonth2->fetch();

    $month_fevrier = $month_fevrier['psum'];
    if (($month_fevrier == '') || ($month_fevrier == 0)) $month_fevrier = 0;
    return $month_fevrier;
  }

// Ca mois mars

  /**
   * Calculates the total sum of all products sold in March of the current year,
   * considering their final price and quantity, for orders with a specific status.
   *
   * @return float The total sales amount for March, or 0 if no sales occurred.
   */
  public static function statMonthMarch()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth3 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                from :table_orders o,
                                                     :table_orders_products op
                                                where o.orders_id = op.orders_id
                                                and o.orders_status = :orders_status
                                                and  ((MONTH(o.date_purchased))) = 3
                                                and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                              ');
    $QcaMonth3->bindInt(':orders_status', 3);
    $QcaMonth3->execute();
    $month_mars = $QcaMonth3->fetch();

    $month_mars = $month_mars['psum'];
    if (($month_mars == '') || ($month_mars == 0)) $month_mars = 0;
    return $month_mars;
  }

// Ca mois avril

  /**
   * Calculates the total sales revenue for the month of April in the current year.
   *
   * This method fetches and sums up the final prices of all sold products
   * for completed orders within the month of April of the current year,
   * based on status and date conditions in the database.
   *
   * @return float|int The total sales revenue for April. Returns 0 if no sales are recorded.
   */
  public static function statMonthApril()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth4 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                          from :table_orders o,
                                               :table_orders_products op
                                          where o.orders_id = op.orders_id
                                          and o.orders_status = :orders_status
                                          and  ((MONTH(o.date_purchased))) = 4
                                          and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                         ');
    $QcaMonth4->bindInt(':orders_status', 3);
    $QcaMonth4->execute();
    $month_avril = $QcaMonth4->fetch();

    $month_avril = $month_avril['psum'];
    if (($month_avril == '') || ($month_avril == 0)) $month_avril = 0;
    return $month_avril;
  }

// Ca mois mai

  /**
   * Retrieves the total revenue generated in the month of May for the current year,
   * calculated based on the sum of the final prices of ordered products multiplied
   * by their respective quantities. Considers only orders with a specific status.
   *
   * @return float The calculated total revenue for the month of May. Returns 0 if no revenue is generated.
   */
  public static function statMonthMay()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth5 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                from :table_orders o,
                                                     :table_orders_products op
                                                where o.orders_id = op.orders_id
                                                and o.orders_status = :orders_status
                                                and  ((MONTH(o.date_purchased))) = 5
                                                and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                               ');
    $QcaMonth5->bindInt(':orders_status', 3);
    $QcaMonth5->execute();
    $month_mai = $QcaMonth5->fetch();

    $month_mai = $month_mai['psum'];
    if (($month_mai == '') || ($month_mai == 0)) $month_mai = 0;
    return $month_mai;
  }

// Ca mois juin

  /**
   * Calculates the total revenue for all orders with a specific status made in the month of June of the current year.
   *
   * The final price of each product in an order is multiplied by its quantity,
   * and the results are summed to provide the total revenue for the specified month.
   *
   * @return float The total revenue for orders with the specified status in June of the current year.
   *               Returns 0 if no applicable orders are found or the result is empty.
   */
  public static function statMonthJune()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth6 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                  from :table_orders o,
                                                       :table_orders_products op
                                                  where o.orders_id = op.orders_id
                                                  and o.orders_status = :orders_status
                                                  and  ((MONTH(o.date_purchased))) = 6
                                                  and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                                 ');
    $QcaMonth6->bindInt(':orders_status', 3);
    $QcaMonth6->execute();
    $month_juin = $QcaMonth6->fetch();

    $month_juin = $month_juin['psum'];
    if (($month_juin == '') || ($month_juin == 0)) $month_juin = 0;
    return $month_juin;
  }

// Ca mois juillet

  /**
   * Calculates the total sales revenue for the month of July in the current year.
   *
   * Queries the database to sum up the total revenue generated by orders
   * in the month of July for the current year, where the orders have a specific
   * status.
   *
   * @return float The total sales revenue for July. Returns 0 if there are no sales.
   */
  public static function statMonthJuly()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth7 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                  from :table_orders o,
                                                       :table_orders_products op
                                                  where o.orders_id = op.orders_id
                                                  and o.orders_status = :orders_status
                                                  and  ((MONTH(o.date_purchased))) = 7
                                                  and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                                 ');
    $QcaMonth7->bindInt(':orders_status', 3);
    $QcaMonth7->execute();
    $month_juillet = $QcaMonth7->fetch();

    $month_juillet = $month_juillet['psum'];
    if (($month_juillet == '') || ($month_juillet == 0)) $month_juillet = 0;
    return $month_juillet;
  }

// Ca mois Aout

  /**
   * Calculates and returns the total sales for the month of August in the current year.
   * This method retrieves the sum of final prices multiplied by product quantities
   * for all orders in August where the order status matches the specified value.
   *
   * @return float The total sales for August. Returns 0 if no sales are found.
   */
  public static function statMonthAugust()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth8 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                from :table_orders o,
                                                     :table_orders_products op
                                                where o.orders_id = op.orders_id
                                                and o.orders_status = :orders_status
                                                and  ((MONTH(o.date_purchased))) = 8
                                                and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                               ');
    $QcaMonth8->bindInt(':orders_status', 3);
    $QcaMonth8->execute();
    $month_aout = $QcaMonth8->fetch();

    $month_aout = $month_aout['psum'];
    if (($month_aout == '') || ($month_aout == 0)) $month_aout = 0;
    return $month_aout;
  }

// Ca mois Septembre

  /**
   * Calculates and returns the total sum of product sales for the month of September
   * in the current year, filtered by a specific order status.
   *
   * @return float Total sales amount for September. Returns 0 if no sales data is found.
   */
  public static function statMonthSeptember()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth9 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                  from :table_orders o,
                                                       :table_orders_products op
                                                  where o.orders_id = op.orders_id
                                                  and o.orders_status = :orders_status
                                                  and  ((MONTH(o.date_purchased))) = 9
                                                  and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                                 ');
    $QcaMonth9->bindInt(':orders_status', 3);
    $QcaMonth9->execute();
    $month_septembre = $QcaMonth9->fetch();

    $month_septembre = $month_septembre['psum'];
    if (($month_septembre == '') || ($month_septembre == 0)) $month_septembre = 0;
    return $month_septembre;
  }

// Ca mois Octobre

  /**
   * Calculates the total sales revenue for the month of October in the current year,
   * based on the final price and quantity of products in completed orders.
   *
   * @return float The total sales revenue for October. Returns 0 if no sales are recorded.
   */
  public static function statMonthOctober()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth10 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                  from :table_orders o,
                                                       :table_orders_products op
                                                  where o.orders_id = op.orders_id
                                                  and o.orders_status = :orders_status
                                                  and  ((MONTH(o.date_purchased))) = 10
                                                  and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                                 ');
    $QcaMonth10->bindInt(':orders_status', 3);
    $QcaMonth10->execute();
    $month_octobre = $QcaMonth10->fetch();

    $month_octobre = $month_octobre['psum'];
    if (($month_octobre == '') || ($month_octobre == 0)) $month_octobre = 0;
    return $month_octobre;
  }

// Ca mois Novembre

  /**
   * Retrieves the total revenue for the month of November of the current year.
   *
   * This method fetches the sum of the final price multiplied by the product's quantity
   * from the database for all orders placed in November with a specific order status.
   *
   * @return float The total revenue for November. Returns 0 if no revenue is found or calculated.
   */
  public static function statMonthNovember()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth11 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                  from :table_orders o,
                                                       :table_orders_products op
                                                  where o.orders_id = op.orders_id
                                                  and o.orders_status = :orders_status
                                                  and  ((MONTH(o.date_purchased))) = 11
                                                  and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                                 ');
    $QcaMonth11->bindInt(':orders_status', 3);
    $QcaMonth11->execute();
    $month_novembre = $QcaMonth11->fetch();

    $month_novembre = $month_novembre['psum'];
    if (($month_novembre == '') || ($month_novembre == 0)) $month_novembre = 0;
    return $month_novembre;
  }

// Ca mois Decembre

  /**
   * Calculates and returns the total revenue generated from orders in the month of December
   * for the current year, based on the final price and quantity of the products.
   *
   * @return float The total revenue for December. Returns 0 if no revenue data exists.
   */
  public static function statMonthDecember()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcaMonth12 = $CLICSHOPPING_Db->prepare('select sum(op.final_price * op.products_quantity) as psum
                                                  from :table_orders o,
                                                       :table_orders_products op
                                                  where o.orders_id = op.orders_id
                                                  and o.orders_status = :orders_status
                                                  and  ((MONTH(o.date_purchased))) = 12
                                                  and ((YEAR(o.date_purchased)) = (YEAR(CURRENT_DATE)))
                                                 ');
    $QcaMonth12->bindInt(':orders_status', 3);
    $QcaMonth12->execute();
    $month_decembre = $QcaMonth12->fetch();

    $month_decembre = $month_decembre['psum'];
    if (($month_decembre == '') || ($month_decembre == 0)) $month_decembre = 0;
    return ($month_decembre);
  }
}

