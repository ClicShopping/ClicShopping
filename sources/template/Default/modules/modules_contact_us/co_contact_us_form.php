<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class co_contact_us_form
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('modules_contact_us_form_title');
    $this->description = CLICSHOPPING::getDef('modules_contact_us_form_description');

    if (\defined('MODULES_CONTACT_US_FORM_STATUS')) {
      $this->sort_order = \defined('MODULES_CONTACT_US_FORM_SORT_ORDER') ? (int)MODULES_CONTACT_US_FORM_SORT_ORDER : 0;
      $this->enabled = \defined('MODULES_CONTACT_US_FORM_STATUS') ? (MODULES_CONTACT_US_FORM_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_GET['Info'], $_GET['Contact']) && !isset($_GET['Success'])) {
      $content_width = \defined('MODULE_CONTACT_US_FORM_CONTENT_WIDTH') ? (int)MODULE_CONTACT_US_FORM_CONTENT_WIDTH : 12;
      $min_caracters_to_write = \defined('MODULE_CONTACT_US_FORM_CONTENT_CARACTER') ? (int)MODULE_CONTACT_US_FORM_CONTENT_CARACTER : 90;

      $message_alert = CLICSHOPPING::getDef('entry_text_alert', ['textalert' => $min_caracters_to_write]);

      $form = HTML::form('contact', CLICSHOPPING::link(null, 'Info&Contact&Process'), 'post', 'onsubmit="var text = document.getElementById(\'messageAlert\').value; if(text.length < ' . $min_caracters_to_write . ') { alert(\'' . $message_alert . '\'); return false; } return true;"', ['tokenize' => true, 'action' => 'process']);

      if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
        $order_id = HTML::sanitize($_GET['order_id']);
      } else {
        $order_id = 0;
      }

      $contact_us_form = '<!--  contact_us_form start -->' . "\n";
      $contact_us_form .= '<div class="col-md-' . $content_width . '" id="ContactUs">';
      $contact_us_form .= $form;

      if ($order_id == 0 && !$CLICSHOPPING_Customer->isLoggedOn()) {
        $contact_us_form .= '<div class="mt-1"></div>';
        $contact_us_form .= '<div class="col-md-12">' . CLICSHOPPING::getDef('entry_note_no_registered') . '</div>';
        $contact_us_form .= '<div class="mt-1"></div>';
      }

      if ($CLICSHOPPING_Customer->isLoggedOn()) {
        $contact_us_form .= '
            <div class="row" id="NameCustomer">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="AllName" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('entry_name') . '</label>
                  <div class="col-sm-6 col-md-4">
                      ' . $CLICSHOPPING_Customer->getLastName() . '  ' . $CLICSHOPPING_Customer->getFirstName() . HTML::hiddenField('name', $CLICSHOPPING_Customer->getLastName() . ' ' . $CLICSHOPPING_Customer->getFirstName()) . '
                  </div>
                </div>
              </div>
            </div>
          ';

      } else {
        $contact_us_form .= '
            <div class="row" id="nameCustomerAnonymous">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="InputName" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('entry_name') . '</label>
                  <div class="col-sm-6 col-md-4">
                      ' . HTML::inputField('name', null, 'required aria-required="true" id="InputName" autocomplete="name" aria-describedby="' . CLICSHOPPING::getDef('entry_name') . '" placeholder="' . CLICSHOPPING::getDef('entry_name') . '"') . '
                  </div>
                </div>
              </div>
            </div>
          ';
      }
      $contact_us_form .= '<div class="mt-1"></div>';

      if ($CLICSHOPPING_Customer->isLoggedOn()) {
        $contact_us_form .= '
            <div class="row" id="emailCustomer">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="dob" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('entry_email') . '</label>
                  <div class="col-sm-6 col-md-4">
                      ' . $CLICSHOPPING_Customer->getEmailAddress() . HTML::hiddenField('email', $CLICSHOPPING_Customer->getEmailAddress()) . '
                  </div>
                </div>
              </div>
            </div>
          ';
      } else {
        $contact_us_form .= '
            <div class="row" id="emailCustomerAnonymous">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="email" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('entry_email') . '</label>
                  <div class="col-sm-6 col-md-4">
                      ' . HTML::inputField('email', null, 'rel="txtTooltipEmailAddress" autocomplete="email" title="' . CLICSHOPPING::getDef('entry_email_dgrp') . '" data-bs-toggle="tooltip" data-placement="right" required aria-required="true" id="InputEmail" aria-describedby="' . CLICSHOPPING::getDef('entry_email') . '" placeholder="' . CLICSHOPPING::getDef('entry_email') . '"', 'email') . '
                  </div>
                </div>
              </div>
            </div>
          ';
      }

      $contact_us_form .= '<div class="mt-1"></div>';

      if ($CLICSHOPPING_Customer->isLoggedOn()) {

        $contact_us_form .= '
              <div class="row" id="customerPhone">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="inputTelephone" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('entry_customers_phone') . '</label>
                    <div class="col-sm-6 col-md-4">
                      ' . $CLICSHOPPING_Customer->getTelephone() . HTML::hiddenField('customers_telephone', $CLICSHOPPING_Customer->getTelephone()) . '
                    </div>
                  </div>
                </div>
              </div>
            ';

      } else {

        $contact_us_form .= '
              <div class="row" id="customerAnonymousPhone">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="inputTelephone" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('entry_customers_phone') . '</label>
                    <div class="col-sm-6 col-md-4">
                      ' . HTML::inputField('customers_telephone', null, 'rel="txtTooltipPhone" autocomplete="tel" title="' . CLICSHOPPING::getDef('entry_customers_phone_dgrp') . '" data-bs-toggle="tooltip" data-placement="right" required aria-required="true" id="InputTelephone" aria-describedby="' . CLICSHOPPING::getDef('entry_customers_phone') . '" placeholder="' . CLICSHOPPING::getDef('entry_customers_phone') . '"', 'phone') . '
                    </div>
                  </div>
                </div>
              </div>
            ';
      }


// ----------------------
// Support
// ----------------------

      if ($CLICSHOPPING_Customer->isLoggedOn()) {
        $contact_us_form .= '
              <div class="row" id="customerId">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="entry_customers_id" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('entry_customers_id') . '</label>
                    <div class="col-sm-6 col-md-4">
                       ' . HTML::hiddenField('customer_id', $CLICSHOPPING_Customer->getID()) . (int)$CLICSHOPPING_Customer->getID() . '
                    </div>
                  </div>
                </div>
              </div>
            ';
// customer registered with order number
        if ($order_id == 0) {
          $contact_us_form .= '<div id="noteRegistrednoOrder">';
          $contact_us_form .= '<label for="entry_note_registred" class="col-md-12">' . CLICSHOPPING::getDef('entry_note_registered') . '</label>';
          $contact_us_form .= '</div>';

        } else {
// customer registered with no order number
          $contact_us_form .= '
              <div class="row" id="orderId">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="gender" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('entry_order') . '</label>
                    <div class="col-sm-6 col-md-4">
                      ' . $order_id . ' ' . HTML::hiddenField('order_id', (int)$order_id) . '
                    </div>
                  </div>
                </div>
              </div>
            ';

          $contact_us_form .= '
              <div class="row" id="NoteRegistred">
                <div class="col-md-12">
                  <div class="form-group row">
                    <div class="col-md-12 alert alert-warning">
                      <h6>' . CLICSHOPPING::getDef('entry_note_registered') . '</h6>
                    </div>
                  </div>
                </div>
              </div>
            ';
        }
      }

      if (!empty(CONTACT_DEPARTMENT_LIST)) {
        if (is_array($_POST['send_to_array'])) {
          $sanitized_array = array_map(fn($item) => HTML::sanitize($item), $_POST['send_to_array']);
          $contact = HTML::selectMenu('send_to', $sanitized_array, null, null, false, 'inputContacUsPullDownMenu');
        } else {
          $contact = '';
        }

        $contact_us_form .= '
            <div class="row" id="companyDepartement">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="CompanyDepartment" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('send_department_company') . '</label>
                  <div class="col-sm-6 col-md-4">
                    ' . $contact . '
                  </div>
                </div>
              </div>
            </div>
          ';
      }
// ----------------------
// Subject
// -------
      $contact_us_form .= '
            <div class="row" id="subject">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="Inputsubject" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('entry_customers_subject') . '</label>
                  <div class="col-sm-6 col-md-4">
                      ' . HTML::inputField('email_subject', null, 'required aria-required="true" id="Inputsubject" aria-describedby="' . CLICSHOPPING::getDef('entry_customers_subject') . '" placeholder="' . CLICSHOPPING::getDef('entry_customers_subject') . '"') . '
                  </div>
                </div>
              </div>
            </div>
          ';

// ----------------------
// Enquiry
// ----------------------
      $contact_us_form .= '<div class="mt-1"></div>';
      $contact_us_form .= '
           <div class="row" id="enquiry">
             <div class="col-md-12">
                <div class="form-group row">
                  <label for="inputMessage" class="col-sm-6 col-md-4 col-form-label">' . CLICSHOPPING::getDef('entry_enquiry') . '</label>
                </div>
             </div>
             <div class="col-md-10">
                ' . HTML::textAreaField('enquiry', null, 50, 15, 'class="form-control inputContacUsFormTextArea" required aria-required="true" id="messageAlert" placeholder="' . CLICSHOPPING::getDef('entry_enquiry') . '"') . '
             </div>
           </div>
            ';
// ----------------------
// Hooks
// ----------------------

      $contact_us_form .= '</div>' . "\n";

      $contact_us_form .= $CLICSHOPPING_Hooks->output('Contact', 'DisplayRowContent', null, 'display');

      $contact_us_form .= '<!-- contact_us_form end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($contact_us_form, $this->group);
    } // end
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_CONTACT_US_FORM_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_CONTACT_US_FORM_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the module',
        'configuration_key' => 'MODULE_CONTACT_US_FORM_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate a minimum number of characters to insert in the message before the customer can send it',
        'configuration_key' => 'MODULE_CONTACT_US_FORM_CONTENT_CARACTER',
        'configuration_value' => '90',
        'configuration_description' => 'Messages less than this number will not be sent. A warning pop up will be displayed to the client indicating the minimum number of characters',
        'configuration_group_id' => '6',
        'sort_order' => '10',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULES_CONTACT_US_FORM_SORT_ORDER',
        'configuration_value' => '100',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '20',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  public function keys()
  {
    return array('MODULES_CONTACT_US_FORM_STATUS',
      'MODULE_CONTACT_US_FORM_CONTENT_WIDTH',
      'MODULE_CONTACT_US_FORM_CONTENT_CARACTER',
      'MODULES_CONTACT_US_FORM_SORT_ORDER'
    );
  }
}
