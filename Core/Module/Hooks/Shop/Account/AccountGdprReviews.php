<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Account;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class AccountGdprReviews
{

  protected $countMyFeedback;
  protected $deleteMyFeedback;
  protected $count;

  /**
   * Retrieves the review count for the current customer and returns the query result.
   *
   * This method connects to the database to count the number of reviews associated
   * with the currently logged-in customer's ID. The count is stored as a class property,
   * and the query result is returned.
   *
   * @return array|false Returns the fetched query result as an associative array. If no results are found, returns false.
   */
  private function getCheck()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $QReviews = $CLICSHOPPING_Db->prepare('select count(reviews_id) as count
                                              from :table_reviews
                                              where customers_id = :customers_id
                                             ');
    $QReviews->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $QReviews->execute();

    $this->count = $QReviews->valueInt('count');

    return $QReviews->fetch();
  }

  /**
   * Generates and returns the HTML output for displaying GDPR-related review information.
   *
   * This method checks if the criteria for displaying the GDPR delete all reviews option are met.
   * If so, it constructs an HTML block showcasing the delete reviews option with a checkbox and
   * related information, including the count of reviews.
   *
   * @return string Returns the generated HTML output as a string. If no output is generated, returns an empty string.
   */
  public function display(): string
  {
    $output = '';

    if ($this->getCheck() === true) {
      $output .= '<div>
                      <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                          <div class="mt-1"></div>
                             ' . CLICSHOPPING::getDef('module_account_customers_gdpr_delete_all_reviews') . ' (' . CLICSHOPPING::getDef('module_account_customers_gdpr_count_customers_reviews') . ' : ' . $this->count . ')' . '
                            <label class="switch">
                              ' . HTML::checkboxField('delete_all_reviews', null, null, 'class="success"') . '
                              <span class="slider"></span>
                            </label>
                        </li>
                      </ul>
                    </div>
                  ';
    }

    return $output;
  }
}