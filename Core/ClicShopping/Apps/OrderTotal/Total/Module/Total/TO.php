<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\Total\Module\Total;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\OrderTotal\Total\Total as TotalApp;

class TO implements \ClicShopping\OM\Modules\OrderTotalInterface
{
  public string $code;
  public $title;
  public $description;
  public $enabled;
  public $group;
  public $output;
  public int|null $sort_order = 0;
  public mixed $app;
  public $signature;
  public $public_title;
  protected $api_version;

  /**
   * Initializes the Total module application by checking for its existence in the registry,
   * loading its definitions, and setting up necessary properties such as signature, version,
   * API version, code, titles, enabled status, sort order, and output.
   *
   * @return void
   */
  public function __construct()
  {

    if (!Registry::exists('Total')) {
      Registry::set('Total', new TotalApp());
    }

    $this->app = Registry::get('Total');
    $this->app->loadDefinitions('Module/Shop/TO/TO');

    $this->signature = 'Total|' . $this->app->getVersion() . '|1.0';
    $this->api_version = $this->app->getApiVersion();

    $this->code = 'TO';
    $this->title = $this->app->getDef('module_to_title');
    $this->public_title = $this->app->getDef('module_to_public_title');

    $this->enabled = \defined('CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_STATUS') && (CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_STATUS == 'True') ? true : false;

    $this->sort_order = \defined('CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_SORT_ORDER') && ((int)CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_SORT_ORDER > 0) ? (int)CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_SORT_ORDER : 0;

    $this->output = [];
  }

  /**
   * Processes the order details and formats currency information to output the total order value.
   *
   * @return void
   */
  public function process()
  {

    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Order = Registry::get('Order');

    $this->output[] = ['title' => $this->title,
      'text' => ' ' . $CLICSHOPPING_Currencies->format($CLICSHOPPING_Order->info['total'], true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']) . ' ',
      'value' => $CLICSHOPPING_Order->info['total']
    ];
  }


  /**
   * Checks if the CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_STATUS constant is defined and not an empty string.
   *
   * @return bool Returns true if the constant is defined and its value is not empty; otherwise, false.
   */
  public function check()
  {
    return \defined('CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_STATUS') && (trim(CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_STATUS) != '');
  }

  /**
   *
   * @return void
   */
  public function install()
  {
    $this->app->redirect('Configure&Install&module=TO');
  }

  /**
   * Redirects the application to the uninstall configuration page for the specified module.
   *
   * @return void
   */
  public function remove()
  {
    $this->app->redirect('Configure&Uninstall&module=TO');
  }

  /**
   *
   * @return array Returns an array of keys used for the configuration of the application.
   */
  public function keys()
  {
    return array('CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_SORT_ORDER');
  }
}
