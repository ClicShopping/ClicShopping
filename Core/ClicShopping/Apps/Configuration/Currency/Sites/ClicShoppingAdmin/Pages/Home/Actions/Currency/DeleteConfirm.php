<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Currency\Sites\ClicShoppingAdmin\Pages\Home\Actions\Currency;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Currency');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    $currencies_id = (int)HTML::sanitize($_GET['cID']);

    $Qcurrency = $this->app->db->get('currencies', 'code', ['currencies_id' => $currencies_id]);

    // Fail-closed : la devise par défaut n'est pas supprimable, y compris par appel direct de
    // l'action. Le bouton du formulaire est déjà masqué, ce qui ne protégeait que l'affichage.
    if ($Qcurrency->value('code') == DEFAULT_CURRENCY) {
      Registry::get('MessageStack')->add($this->app->getDef('error_remove_default_currency'), 'error');

      $this->app->redirect('Currency&page=' . $page);

      return;
    }

    $this->app->db->delete('currencies', ['currencies_id' => $currencies_id]);

    Cache::clear('currencies');

    $this->app->redirect('Currency&&page=' . $page);
  }
}