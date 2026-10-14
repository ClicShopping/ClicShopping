<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Account;

use ClicShopping\Apps\Customers\Customers\Classes\Shared\AccountDeletionRequest;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class AccountGdprDeleteAccount
{
  /**
   * Generates and returns the HTML output for displaying a GDPR account delete section.
   *
   * The output includes a title, an introductory message, and either the checkbox that asks for the
   * deletion or, while a request is pending, the field where the e-mailed number is typed back.
   *
   * @return string The generated HTML string for the GDPR account delete section.
   */
  public function display(): string
  {
    $output = '<div class="mt-1"></div>
                  <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                      <div class="alert alert-danger" role="alert">
                      <label><strong>' . CLICSHOPPING::getDef('module_account_customers_gdpr_account_delete_title') . '</strong></label><br />
                      <label><strong>' . CLICSHOPPING::getDef('module_account_customers_gdpr_account_intro_delete') . '</strong></label>
                      <blockquote>
                        ' . $this->confirmationField() . '
                      </blockquote>
                      </div>
                    </li>
                  </ul>                  
                  ';
    return $output;
  }

  /**
   * @return string The number field while a request is pending, the request checkbox otherwise
   */
  private function confirmationField(): string
  {
    if (AccountDeletionRequest::isPending((int)Registry::get('Customer')->getID())) {
      return '<label for="delete_account_code">' . CLICSHOPPING::getDef('module_account_customers_gdpr_delete_code_label') . '</label> '
        . HTML::inputField('delete_account_code', '', 'id="delete_account_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"');
    }

    return CLICSHOPPING::getDef('module_account_customers_gdpr_checkbox') . '
                        <label class="switch">
                          ' . HTML::checkboxField('delete_customers_account_checkbox', null, null, 'class="success"') . '
                          <span class="slider"></span>
                        </label>';
  }
}
