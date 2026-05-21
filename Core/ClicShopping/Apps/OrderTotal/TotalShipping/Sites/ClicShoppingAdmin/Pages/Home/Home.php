<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\OrderTotal\TotalShipping\TotalShipping;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_TotalShipping = new TotalShipping();
    Registry::set('TotalShipping', $CLICSHOPPING_TotalShipping);

    $this->app = $CLICSHOPPING_TotalShipping;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
