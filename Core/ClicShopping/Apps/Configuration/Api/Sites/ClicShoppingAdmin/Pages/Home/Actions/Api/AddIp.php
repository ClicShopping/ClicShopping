<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Api\Sites\ClicShoppingAdmin\Pages\Home\Actions\Api;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class AddIp extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Api');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['AddIp'])) {
      $api_id = HTML::sanitize($_GET['cID']);
      $ip = HTML::sanitize($_POST['ip']);
      $comment = HTML::sanitize($_POST['comment']);

      $sql_data_array = [
        'api_id' => $api_id,
        'ip' => $ip,
        'comment' => $comment,
      ];

      $this->app->db->save('api_ip', $sql_data_array);
    }

    $this->app->redirect('Edit&cID=' . (int)$_GET['cID'] . '&page=' . $page . '&#tab2');
  }
}