<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\SH\Params;

class sort_order extends \ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '500';
  public bool $app_configured = false;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_order_total_shipping_sort_order_title');
    $this->description = $this->app->getDef('cfg_order_total_shipping_sort_order_description');
  }
}
