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

use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('SecurityCheck');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $Qselect = $this->app->db->prepare('select ip_restriction
                                              from :table_ip_restriction
                                              where id = :id
                                            ');
        $Qselect->bindInt(':id', $id);
        $Qselect->execute();

        $delete_ip = $Qselect->value('ip_restriction');

        $Qdelete = $this->app->db->prepare('delete
                                              from :table_ip_restriction
                                              where id = :id
                                            ');
        $Qdelete->bindInt(':id', $id);
        $Qdelete->execute();

        $Qdelete = $this->app->db->prepare('delete
                                              from :table_ip_restriction_stats
                                              where ip_remote = :ip_remote
                                            ');
        $Qdelete->bindInt(':ip_remote', $delete_ip);
        $Qdelete->execute();
      }
    }

    $this->app->redirect('IpRestriction&page=' . $page);
  }
}