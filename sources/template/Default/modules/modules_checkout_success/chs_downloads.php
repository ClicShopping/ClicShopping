<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Registry;

class chs_downloads
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_checkout_success_downloads_title');
    $this->description = CLICSHOPPING::getDef('module_checkout_success_downloads_description');

    if (\defined('MODULE_CHECKOUT_SUCCESS_DOWNLOADS_STATUS')) {
      $this->sort_order = \defined('MODULE_CHECKOUT_SUCCESS_DOWNLOADS_SORT_ORDER') ? (int)MODULE_CHECKOUT_SUCCESS_DOWNLOADS_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_CHECKOUT_SUCCESS_DOWNLOADS_STATUS') && MODULE_CHECKOUT_SUCCESS_DOWNLOADS_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_GET['Checkout'], $_GET['Success'])) {
      if (\defined('DOWNLOAD_ENABLED') && DOWNLOAD_ENABLED == 'true') {
        $download = '<!-- Product download start -->' . "\n";

// Get last order id for checkout_success
        $Qorders = $CLICSHOPPING_Db->get('orders', 'orders_id', ['customers_id' => $CLICSHOPPING_Customer->getID()], 'orders_id desc', 1);
        $last_order = $Qorders->valueInt('orders_id');

// Now get all downloadable products in that order
        $Qdownloads = $CLICSHOPPING_Db->prepare('select date_format(o.date_purchased, "%Y-%m-%d") as date_purchased_day,
                                                          opd.download_maxdays,
                                                          op.products_name,
                                                          opd.orders_products_download_id,
                                                          opd.orders_products_filename,
                                                          opd.download_count,
                                                          opd.download_maxdays
                                                    from :table_orders o,
                                                         :table_orders_products op,
                                                         :table_orders_products_download opd,
                                                         :table_orders_status os
                                                    where o.orders_id = :orders_id
                                                    and o.customers_id = :customers_id
                                                    and o.orders_id = op.orders_id
                                                    and op.orders_products_id = opd.orders_products_id
                                                    and opd.orders_products_filename <> ""
                                                    and o.orders_status = os.orders_status_id
                                                    and os.downloads_flag = 1
                                                    and os.language_id = :language_id
                                                  ');

        $Qdownloads->bindInt(':orders_id', $last_order);
        $Qdownloads->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $Qdownloads->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        $Qdownloads->execute();

        $download .= '<div class="col-md-12">
                          <div class="card">
                            <div class="card-header"><h3>' . CLICSHOPPING::getDef('heading_download') . '</h3></div>
                               <div class="card-text">
                                  <table class="table table-sm table-striped table-hover">
                        ';

        if ($Qdownloads->fetch() !== false) {
          do {
// MySQL 3.22 does not have INTERVAL
            list($dt_year, $dt_month, $dt_day) = explode('-', $Qdownloads->value('date_purchased_day'));
            $download_timestamp = mktime(23, 59, 59, $dt_month, $dt_day + $Qdownloads->valueInt('download_maxdays'), $dt_year);
            $download_expiry = date('Y-m-d H:i:s', $download_timestamp);

            $download .= '<tr>' . "\n";

// The link will appear only if:
// - Download remaining count is > 0, AND
// - The file is present in the DOWNLOAD directory, AND EITHER
// - No expiry date is enforced (maxdays == 0), OR
// - The expiry date is not reached
            if (($Qdownloads->valueInt('download_count') > 0) && (is_file($CLICSHOPPING_Template->getPathDownloadShopDirectory('Private') . $Qdownloads->value('orders_products_filename'))) && (($Qdownloads->valueInt('download_maxdays') == 0) || ($download_timestamp > time()))) {
              $download .= '<td><a href="' . CLICSHOPPING::link(null, 'Products&Download&order=' . $last_order . '&id=' . $Qdownloads->valueInt('orders_products_download_id')) . '"><strong>' . $Qdownloads->value('products_name') . '</a></strong></td>' . "\n";
            } else {
              $download .= '<td>' . $Qdownloads->value('products_name') . '</td>' . "\n";
            }

            $download .= '<td>' . CLICSHOPPING::getDef('table_heading_download_date') . ' ' . DateTime::toLong($download_expiry) . '</td>' . "\n" .
              '<td class="text-end">' . $Qdownloads->valueInt('download_count') . ' ' . CLICSHOPPING::getDef('table_heading_download_count') . '</td>' . "\n" .
              '</tr>' . "\n";
          } while ($Qdownloads->fetch());
        }

        $download .= '     </table>
                           </div>
                         </div>
                      </div>
                     <div class="mt-1"></div>
                    ';

        $download .= '<!-- Product download end -->' . "\n";

        $CLICSHOPPING_Template->addBlock($download, $this->group);
      }
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_CHECKOUT_SUCCESS_DOWNLOADS_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Enable Product Downloads Module',
        'configuration_key' => 'MODULE_CHECKOUT_SUCCESS_DOWNLOADS_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Should ordered product download links be shown on the checkout success page ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_CHECKOUT_SUCCESS_DOWNLOADS_SORT_ORDER',
        'configuration_value' => '3',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  public function keys()
  {
    return array('MODULE_CHECKOUT_SUCCESS_DOWNLOADS_STATUS',
      'MODULE_CHECKOUT_SUCCESS_DOWNLOADS_SORT_ORDER'
    );
  }
}

