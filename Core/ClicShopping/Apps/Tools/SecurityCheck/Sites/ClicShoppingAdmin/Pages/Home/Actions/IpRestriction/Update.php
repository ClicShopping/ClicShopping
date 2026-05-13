<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Tools\SecurityCheck\Sites\ClicShoppingAdmin\Pages\Home\Actions\IpRestriction;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('SecurityCheck');
  }

  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['cID'])) {
      $id = HTML::sanitize($_GET['cID']);

      if (isset($_POST['ip_comment'])) {
        $ip_comment = HTML::sanitize($_POST['ip_comment']);
      } else {
        $ip_comment = '';
      }

      $sql_data_array = ['ip_comment' => $ip_comment];

      $ip_restriction = HTML::sanitize($_POST['ip_restriction']);

      $update_sql_data = ['ip_restriction' => $ip_restriction];

      $sql_data_array = array_merge($sql_data_array, $update_sql_data);

      $this->app->db->save('ip_restriction', $sql_data_array, ['id' => (int)$id]);

      $CLICSHOPPING_Hooks->call('IpRestriction', 'Update');
    }

    $this->app->redirect('IpRestriction&page=' . $page);
  }
}