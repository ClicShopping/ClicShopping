<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\Newsletters;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $Qnewsletter = $CLICSHOPPING_Db->prepare('select customers_newsletter
                                                from :table_customers
                                                where customers_id = :customers_id
                                              ');
    $Qnewsletter->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $Qnewsletter->execute();

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $newsletter_general = isset($_POST['newsletter_general']) && ($_POST['newsletter_general'] == 1) ? 1 : 0;

      if ($newsletter_general !== $Qnewsletter->valueInt('customers_newsletter')) {
        $newsletter_general = ($Qnewsletter->valueInt('customers_newsletter') === 1) ? 0 : 1;

        $CLICSHOPPING_Db->save('customers', ['customers_newsletter' => $newsletter_general],
          ['customers_id' => (int)$CLICSHOPPING_Customer->getID()]
        );

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_newsletter_updated'), 'success', 'newsletter');
      }
    }

    $CLICSHOPPING_Hooks->call('Newsletters', 'Process');

    CLICSHOPPING::redirect(null, 'Account&Newsletters');
  }
}