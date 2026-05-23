<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Payment;

class cc_checkout_confirmation_payment_information
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

    $this->title = CLICSHOPPING::getDef('module_checkout_confirmation_payment_information_title');
    $this->description = CLICSHOPPING::getDef('module_checkout_confirmation_payment_information_description');

    if (\defined('MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_STATUS')) {
      $this->sort_order = (int)MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_SORT_ORDER;
      $this->enabled = (MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_GET['Checkout'], $_GET['Confirmation']) && $CLICSHOPPING_Customer->isLoggedOn()) {
      if (!Registry::exists('Payment')) {
        Registry::set('Payment', new Payment());
      }

      $CLICSHOPPING_Payment = Registry::get('Payment');

      $content_width = (int)MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_CONTENT_WIDTH;

      $data = '';

      if (\is_array($CLICSHOPPING_Payment->modules)) {
        $confirmation = $CLICSHOPPING_Payment->confirmation();

        if ($confirmation) {
          $display = '  <!-- checkout_confirmation_payment_information -->' . "\n";
          $display .= '<div class="clearfix"></div>' . "\n";

          if (isset($confirmation['content'])) {
            $display .= '<div class="col-sm-12">';
            $display .= '<div class="checkoutPaymentInput">' . $confirmation['content'] . '</div>';
            $display .= ' </div>';
          } else {
            if (isset($confirmation['title'])) {
              $data .= '<div class="col-sm-12">';
              $data .= '  <div class="">';
              $data .= $confirmation['title'];
              $data .= '  </div>';
              $data .= '</div>';
            }

            if (isset($confirmation['fields'])) {
              $display = '';
              $display .= '<div class="col-md-' . $content_width . '">';
              $display .= '<div class="col-md-12">';
              $display .= '<div class="mt-1"></div>';
              $display .= '<div class="card moduleCheckoutConfirmationPaymentInformationCard">';
              $display .= '<div class="card-header moduleCheckoutConfirmationPaymentInformationCardHeader"><strong>' . CLICSHOPPING::getDef('module_checkout_confirmation_payment_information_heading_payment_information') . '</strong></div>';
              $display .= '<div class="card-block moduleCheckoutConfirmationPaymentInformationCardBlock">';
              $display .= '<div class="mt-1"></div>';

              for ($i = 0, $n = \count($confirmation['fields']); $i < $n; $i++) {

                $field = '<span class="col-md-3">' . $confirmation['fields'][$i]['title'] . '</span>';
                $field .= '<span class="col-md-3">' . $confirmation['fields'][$i]['field'] . '</span>';

                $display .= '<div class="col-md-12">';
                $display .= $data;
                $display .= $field;
                $display .= '</div>';
              }

              $display .= '</div>';
              $display .= '</div>';
              $display .= '</div>';
              $display .= '</div>';
            } else {
              ob_start();
              require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/checkout_confirmation_payment_information'));

              $display .= ob_get_clean();
            }
          }

          $display .= '<!--  checkout_confirmation_payment_information -->' . "\n";

          $CLICSHOPPING_Template->addBlock($display, $this->group);
        }
      }
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_STATUS',
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
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );


    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_SORT_ORDER',
        'configuration_value' => '85',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
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
    return array(
      'MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_STATUS',
      'MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_CONTENT_WIDTH',
      'MODULE_CHECKOUT_CONFIRMATION_PAYMENT_INFORMATION_SORT_ORDER'
    );
  }
}