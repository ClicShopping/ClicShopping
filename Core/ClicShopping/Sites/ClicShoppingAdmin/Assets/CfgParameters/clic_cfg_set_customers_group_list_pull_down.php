<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


use ClicShopping\OM\HTML;

use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;

/**
 * @param $customers_group_id
 * @return string
 */
function clic_cfg_set_customers_group_list_pull_down($customers_group_id)
{
  return HTML::selectMenu('configuration_value', GroupsB2BAdmin::getCustomersGroup(), (int)$customers_group_id);
}