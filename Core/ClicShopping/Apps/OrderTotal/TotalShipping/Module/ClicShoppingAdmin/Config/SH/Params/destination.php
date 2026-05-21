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

use ClicShopping\OM\HTML;

class destination extends \ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'national';
  public int|null $sort_order = 10;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_order_total_shipping_destination_title');
    $this->description = $this->app->getDef('cfg_order_total_shipping_destination_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $dropdown = array(array('id' => 'national', 'text' => $this->app->getDef('cfg_order_total_shipping_destination_national')),
      array('id' => 'international', 'text' => $this->app->getDef('cfg_order_total_shipping_destination_international')),
      array('id' => 'both', 'text' => $this->app->getDef('cfg_order_total_shipping_destination_both')),
    );

    $input = HTML::selectField($this->key, $dropdown, $value);

    return $input;
  }
}