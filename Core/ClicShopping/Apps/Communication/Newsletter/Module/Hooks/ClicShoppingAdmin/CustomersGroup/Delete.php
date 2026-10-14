<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Module\Hooks\ClicShoppingAdmin\CustomersGroup;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Communication\Newsletter\Newsletter as NewsletterApp;

class Delete implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for the class.
   *
   * Ensures the 'Newsletter' instance exists in the Registry. If not, it creates and sets a new instance of NewsletterApp in the Registry.
   * Initializes the $app property with the 'Newsletter' instance from the Registry.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Newsletter')) {
      Registry::set('Newsletter', new NewsletterApp());
    }

    $this->app = Registry::get('Newsletter');
  }

  /**
   * Deletes all newsletter entries associated with the specified customers group ID.
   *
   * @param int $group_id The ID of the customers group whose associated newsletter entries should be deleted.
   * @return void
   */
  private function delete(int $group_id): void
  {
    $QnewsletteCustomersId = $this->app->db->prepare('select count(customers_group_id) as count
                                                         from :table_newsletters
                                                         where customers_group_id = :customers_group_id
                                                       ');
    $QnewsletteCustomersId->bindInt(':customers_group_id', (int)$group_id);
    $QnewsletteCustomersId->execute();

    if ($QnewsletteCustomersId->valueInt('count') > 0) {
      $Qdelete = $this->app->db->prepare('delete
                                            from :table_newsletters
                                            where customers_group_id = :customers_group_id
                                          ');
      $Qdelete->bindInt(':customers_group_id', (int)$group_id);
      $Qdelete->execute();
    }
  }

  /**
   * Executes the main function of the method.
   * Checks if the 'Delete' parameter is set in the GET request,
   * sanitizes the provided 'cID' value, and calls the delete method with the sanitized ID.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Delete'])) {
      $id = HTML::sanitize($_GET['cID']);
      $this->delete($id);
    }
  }
}