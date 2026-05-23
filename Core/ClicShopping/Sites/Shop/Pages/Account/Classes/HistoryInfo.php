<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Classes;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class HistoryInfo
{
  /**
   * Retrieves specific order history information based on the provided order ID.
   *
   * This method queries the database to fetch the customer's ID related to a specific order.
   * It checks if the order status is associated with a public flag and matches the current language ID.
   *
   * @return array|false Returns an associative array containing the customer ID if found, or false if no record matches.
   */
  public static function getHistoryInfoCheck()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->prepare('select o.customers_id
                                           from :table_orders o,
                                                :table_orders_status s
                                           where o.orders_id = :orders_id
                                           and o.orders_status = s.orders_status_id
                                           and s.language_id = :language_id
                                           and s.public_flag = 1
                                          ');
    $Qcheck->bindInt(':orders_id', (int)$_GET['order_id']);
    $Qcheck->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());

    $Qcheck->execute();

    $check = $Qcheck->fetch();

    return $check;
  }

  /**
   * Retrieves the total count of records in the orders status history table
   * corresponding to a specific order ID.
   *
   * @return int The number of records found for the given order ID.
   */
  public static function getHistoryInfoCount(): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcount = $CLICSHOPPING_Db->prepare('select count(orders_status_id) as count
                                           from :table_orders_status_history
                                           where orders_id = :orders_id
                                          ');
    $Qcount->bindInt(':orders_id', $_GET['order_id']);

    $Qcount->execute();

    $count = $Qcount->valueInt('count');

    return $count;
  }

  /**
   * Retrieves the display history information support flag based on specific conditions.
   *
   * This method queries the database to check if there is a row in the `orders_status` table
   * where the `support_orders_flag` is set to 0 and the `orders_status_id` is 5.
   *
   * @return int|null Returns the value of the `support_orders_flag` if found, otherwise null.
   */
  public static function getDisplayHistoryInfoSupport()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qsupport = $CLICSHOPPING_Db->prepare('select support_orders_flag
                                              from :table_orders_status
                                              where support_orders_flag = 0
                                              and orders_status_id = 5
                                             ');

    $Qsupport->execute();

    $support = $Qsupport->valueInt('support_orders_flag');

    return $support;
  }

  /**
   * Retrieves the order history information including status name, date added, comments,
   * tracking number, and support ID, for a specific order based on the given order ID.
   * The information is filtered by language and public flag.
   *
   * @return array An array of order history information matching the specified criteria.
   */
  public static function getHistoryInfoDisplay(): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qstatuse = $CLICSHOPPING_Db->prepare('select distinct os.orders_status_name,
                                                              osh.date_added,
                                                              osh.comments,
                                                              osh.orders_tracking_number,
                                                              osh.orders_status_support_id
                                            from :table_orders_status os,
                                                 :table_orders_status_history osh
                                            where osh.orders_id = :orders_id
                                            and osh.orders_status_id = os.orders_status_id
                                            and os.language_id = :language_id
                                            and os.public_flag = 1
                                            order by osh.date_added
                                            ');
    $Qstatuse->bindInt(':orders_id', $_GET['order_id']);
    $Qstatuse->bindInt(':language_id', $CLICSHOPPING_Language->getId());

    $Qstatuse->execute();

    $statuse = $Qstatuse->fetchAll();

    return $statuse;
  }

  /**
   * Retrieves information about downloadable files related to a specific order.
   *
   * This method performs a query to fetch distinct product details that are linked
   * to a given order and meet specific conditions, such as the order's status and
   * the product's availability within active categories.
   *
   * @return array|bool Returns an associative array containing the details of the product
   *                    and its downloadable file if found, or false if no record is found.
   */
  public static function getHistoryInfoDownloadFiles()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QdonwloadProductsFiles = $CLICSHOPPING_Db->prepare('select distinct p.products_id,
                                                                            p.products_download_filename,
                                                                            op.products_id,
                                                                            op.orders_id,
                                                                            o.orders_id
                                                             from :table_products p,
                                                                  :table_orders_products op,
                                                                  :table_orders o,
                                                                  :table_orders_status os,
                                                                  :table_products_to_categories p2c,
                                                                  :table_categories c
                                                             where p.products_id = op.products_id
                                                             and o.orders_id = op.orders_id
                                                             and op.orders_id = :orders_id
                                                             and o.orders_status = 3
                                                             and p.products_id = p2c.products_id
                                                             and p2c.categories_id = c.categories_id
                                                             and c.status = 1
                                                            ');
    $QdonwloadProductsFiles->bindInt(':orders_id', $_GET['order_id']);

    $QdonwloadProductsFiles->execute();

    $download = $QdonwloadProductsFiles->fetch();

    return $download;
  }

  /**
   * Retrieves the name of the order status support related to a specific customer support history entry.
   *
   * @param int $orders_status_support_id The ID of the order status support to fetch information for.
   * @return array|bool Returns the fetched support information as an associative array or false if no record is found.
   */
  public static function getHistoryInfoSupportCustomer(int $orders_status_support_id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QCustomerSupport = $CLICSHOPPING_Db->prepare('select oss.orders_status_support_name
                                                     from :table_orders_status_history osh,
                                                          :table_orders_status_support oss
                                                     where osh.orders_status_support_id = :orders_status_support_id
                                                     and osh.orders_status_support_id = oss.orders_status_support_id
                                                     and oss.language_id = :language_id
                                                     order by osh.date_added desc
                                                     limit 1
                                                    ');

    $QCustomerSupport->bindInt(':orders_status_support_id', $orders_status_support_id);
    $QCustomerSupport->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $QCustomerSupport->execute();

    $support = $QCustomerSupport->fetch();

    return $support;
  }


  /**
   * Generates and retrieves a tracking link based on order status and tracking details.
   *
   * @return string The constructed tracking link or a default tracking URL.
   */
  public static function getTrackingLink(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QordersTracking = $CLICSHOPPING_Db->prepare('select orders_status_tracking_id,
                                                           orders_status_tracking_link
                                                    from :table_orders_status_tracking
                                                    where  language_id = :language_id
                                                  ');

    $QordersTracking->bindInt(':language_id', $CLICSHOPPING_Language->getId());

    $QordersTracking->execute();
    $orders_tracking = $QordersTracking->fetch();


    if (empty($QordersTracking->value('orders_status_tracking_link')) || (empty($QordersTracking->value('orders_tracking_number')))) {
      $tracking_url = ' (<a href="http://www.track-trace.com/" target="_blank">http://www.track-trace.com/</a>)';
    } else {
      $tracking_url = ' (<a href="' . $QordersTracking->value('orders_status_tracking_link') . $QordersTracking->value('orders_tracking_number') . '" target="_blank" rel="noreferrer">' . $orders_tracking->value('orders_status_tracking_link') . $QordersTracking->value('orders_tracking_number') . '</a>)';
    }
    return $tracking_url;
  }

  /**
   * Retrieves download files for products purchased associated with a specific order.
   *
   * This method queries the database to fetch details about downloadable products
   * from a given order or the latest order placed by the customer. The method processes
   * requests based on the provided 'order' and 'id' parameters in the GET request.
   *
   * The query returns details such as the product name, filename of the downloadable file,
   * download count, maximum download days, and the purchase date.
   *
   * @return \PDOStatement|null The query result containing the downloadable product details,
   *                             or null if no query is executed.
   */
  public static function getDownloadFilesPurchased()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (isset($_GET['order'])) {
      $orders_id = HTML::sanitize($_GET['order']);

      $Qdownload = $CLICSHOPPING_Db->prepare('select date_format(o.date_purchased, "%Y-%m-%d") as date_purchased_day,
                                                       opd.download_maxdays,
                                                       opd.download_count,
                                                       opd.download_maxdays,
                                                       opd.orders_products_filename,
                                                       opd.orders_products_download_id,
                                                       op.products_name
                                                from :table_orders o,
                                                     :table_orders_products op,
                                                     :table_orders_products_download opd,
                                                     :table_orders_status os
                                                where o.orders_id = :orders_id
                                                and o.customers_id = :customers_id
                                                and o.orders_id = op.orders_id
                                                and op.orders_products_id = opd.orders_products_id
                                                and opd.orders_products_download_id = :orders_products_download_id
                                                and opd.orders_products_filename != ""
                                                and o.orders_status = os.orders_status_id
                                                and os.downloads_flag = "1"
                                                and os.language_id = :language_id
                                              ');
      $Qdownload->bindInt(':orders_id', $orders_id);
      $Qdownload->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qdownload->bindInt(':orders_products_download_id', $_GET['id']);
      $Qdownload->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qdownload->execute();
    } else {

      $Qorders = $CLICSHOPPING_Db->get('orders', 'orders_id', ['customers_id' => $CLICSHOPPING_Customer->getID()], 'orders_id desc', 1);
      $last_order = $Qorders->valueInt('orders_id');

      $Qdownload = $CLICSHOPPING_Db->prepare('select date_format(o.date_purchased, "%Y-%m-%d") as date_purchased_day,
                                                       opd.download_maxdays,
                                                       opd.download_count,
                                                       opd.download_maxdays,
                                                       opd.orders_products_filename,
                                                       opd.orders_products_download_id,
                                                       op.products_name
                                                from :table_orders o,
                                                     :table_orders_products op,
                                                     :table_orders_products_download opd,
                                                     :table_orders_status os
                                                where o.orders_id = :orders_id
                                                and o.customers_id = :customers_id
                                                and o.orders_id = op.orders_id
                                                and op.orders_products_id = opd.orders_products_id
                                                and opd.orders_products_filename != ""
                                                and o.orders_status = os.orders_status_id
                                                and os.downloads_flag = 1
                                                and os.language_id = :language_id
                                              ');

      $Qdownload->bindInt(':orders_id', $last_order);
      $Qdownload->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qdownload->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qdownload->execute();
    }

    return $Qdownload;
  }
}