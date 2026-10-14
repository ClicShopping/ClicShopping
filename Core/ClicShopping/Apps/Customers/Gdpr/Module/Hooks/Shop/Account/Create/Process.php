<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Module\Hooks\Shop\Account\Create;

use ClicShopping\Apps\Customers\Gdpr\Gdpr as GdprApp;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

/**
 * Fires on customer account creation (call('Create', 'Process')). Anchors the GDPR
 * retention clock by stamping customers_info_date_of_last_logon = now(), so an account
 * that is created but never logged into is still covered by the retention purge cron
 * (a NULL last-logon would otherwise never match the cutoff and never be cleaned up).
 */
class Process implements HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Gdpr')) {
      Registry::set('Gdpr', new GdprApp());
    }

    $this->app = Registry::get('Gdpr');
  }

  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_STATUS') || CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_STATUS == 'False') {
      return false;
    }

    $customer_id = (int)Registry::get('Customer')->getID();

    if ($customer_id > 0) {
      $this->app->db->save(
        'customers_info',
        ['customers_info_date_of_last_logon' => 'now()'],
        ['customers_info_id' => $customer_id]
      );
    }
  }
}
