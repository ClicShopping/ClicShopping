<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\WEB\Params;

class sort_order extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '100';
  public int|null $sort_order = 300;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_web_sort_order_title');
    $this->description = $this->app->getDef('cfg_ecommerce_web_sort_order_description');
  }
}
