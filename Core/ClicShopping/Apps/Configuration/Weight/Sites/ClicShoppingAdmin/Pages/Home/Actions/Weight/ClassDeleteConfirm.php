<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Weight\Sites\ClicShoppingAdmin\Pages\Home\Actions\Weight;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ClassDeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Weight');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $weight_class_from_id = HTML::sanitize($_GET['wID']);
    $weight_class_to_id = HTML::sanitize($_GET['tID']);

    $sql_array = [
      'weight_class_from_id' => (int)$weight_class_from_id,
      'weight_class_from_id' => (int)$weight_class_to_id
    ];

    $this->app->db->delete('weight_classes_rules', $sql_array);
    $this->app->db->delete('weight_classes', ['weight_class_id' => (int)$weight_class_from_id]);

    Cache::clear('weight-classes');
    Cache::clear('weight-rules');

    $this->app->redirect('Weight&page=' . $page);
  }
}