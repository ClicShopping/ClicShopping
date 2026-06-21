<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Module\Hooks\ClicShoppingAdmin\Customers;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;

class DeleteCustomers implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Reviews application.
   *
   * Checks if the 'Reviews' key exists in the Registry; if not, it initializes
   * a new ReviewsApp instance and stores it in the Registry. Then, it retrieves
   * the 'Reviews' application instance from the Registry and assigns it to the
   * class property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Reviews')) {
      Registry::set('Reviews', new ReviewsApp());
    }

    $this->app = Registry::get('Reviews');
  }

  /**
   * Deletes a customer and handles associated reviews based on the provided settings.
   *
   * @param int $id The unique identifier of the customer to be deleted.
   * @return void
   */
  private function deleteCustomer(int $id): void
  {
    if (isset($_POST['delete_reviews']) && ($_POST['delete_reviews'] == 'on')) {
      $Qreviews = $this->app->db->get('reviews', 'reviews_id', ['customers_id' => $id]);

      while ($Qreviews->fetch()) {
        $this->app->db->delete('reviews_description', ['reviews_id' => (int)$Qreviews->valueInt('reviews_id')]);
      }

      $this->app->db->delete('reviews', ['customers_id' => $id]);
    } else {
      $this->app->db->save('reviews', ['customers_id' => 'null'], ['customers_id' => $id]);
    }
  }

  /**
   * Executes the delete operation for customers.
   *
   * If the 'DeleteAll' parameter is set in the GET request, this method deletes
   * all selected customers specified in the POST data. If no customers are
   * selected, it deletes the customer with the ID provided in the POST data.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['DeleteAll'])) {
      if (isset($_POST['selected']) && \is_array($_POST['selected']) && !empty($_POST['selected'])) {
        foreach ($_POST['selected'] as $id) {
          $this->deleteCustomer($id);
        }
      } else {
        $id = HTML::sanitize($_POST['id']);
        $this->deleteCustomer($id);
      }
    }
  }
}