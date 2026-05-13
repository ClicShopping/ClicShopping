<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Tools\SecurityCheck\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Tools\SecurityCheck\SecurityCheck;
use ClicShopping\OM\Registry;

class Home extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    $CLICSHOPPING_SecurityCheck = new SecurityCheck();
    Registry::set('SecurityCheck', $CLICSHOPPING_SecurityCheck);

    $this->app = Registry::get('SecurityCheck');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }

}
