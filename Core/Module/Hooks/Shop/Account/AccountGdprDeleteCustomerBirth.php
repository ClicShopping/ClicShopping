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

class AccountGdprDeleteCustomerBirth
{
  /**
   * Renders and returns the HTML output for displaying a list group item with a checkbox.
   *
   * @return string The generated HTML output.
   */
  public function display(): string
  {
    $output = '<div>
                    <ul class="list-group list-group-flush">
                      <li class="list-group-item">
                        <div class="mt-1"></div>
                           ' . CLICSHOPPING::getDef('module_account_customers_gdpr_customer_bidth') . '
                          <label class="switch">
                            ' . HTML::checkboxField('delete_customers_birth', null, null, 'class="success"') . '
                            <span class="slider"></span>
                          </label>
                      </li>
                    </ul>
                  </div>
                 ';

    return $output;
  }
}