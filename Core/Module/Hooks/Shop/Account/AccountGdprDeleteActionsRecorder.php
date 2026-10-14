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

class AccountGdprDeleteActionsRecorder
{
  /**
   * Generates and returns HTML output for displaying a list group with specific elements,
   * including an action recorder checkbox with a toggle switch UI.
   *
   * @return string The generated HTML output as a string.
   */
  public function display(): string
  {
    $output = '';
    $output .= '<div>
                    <ul class="list-group list-group-flush">
                      <li class="list-group-item">
                        <div class="mt-1"></div>
                           ' . CLICSHOPPING::getDef('module_account_customers_gdpr_delete_action_recorder') . '
                          <label class="switch">
                            ' . HTML::checkboxField('action_recorder', null, null, 'class="success"') . '
                            <span class="slider"></span>
                          </label>
                      </li>
                    </ul>
                  </div>
                 ';

    return $output;
  }
}
