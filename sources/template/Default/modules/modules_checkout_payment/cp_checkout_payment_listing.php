<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Payment;

class cp_checkout_payment_listing
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

    $this->title = CLICSHOPPING::getDef('module_checkout_payment_listing_title');
    $this->description = CLICSHOPPING::getDef('module_checkout_payment_listing_description');

    if (\defined('MODULE_CHECKOUT_PAYMENT_LISTING_STATUS')) {
      $this->sort_order = (int)(\defined('MODULE_CHECKOUT_PAYMENT_LISTING_SORT_ORDER') ? MODULE_CHECKOUT_PAYMENT_LISTING_SORT_ORDER : 0);
      $this->enabled = (\defined('MODULE_CHECKOUT_PAYMENT_LISTING_STATUS') && MODULE_CHECKOUT_PAYMENT_LISTING_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($_GET['Checkout'], $_GET['Billing'])) {
      if (!Registry::exists('Payment')) {
        Registry::set('Payment', new Payment());
      }

      $CLICSHOPPING_Payment = Registry::get('Payment');

      $selection = $CLICSHOPPING_Payment->selection();

      $content_width = (int)(\defined('MODULE_CHECKOUT_PAYMENT_LISTING_CONTENT_WIDTH') ? MODULE_CHECKOUT_PAYMENT_LISTING_CONTENT_WIDTH : 12);

      $payment_process = '<!-- start cp_checkout_payment_listing -->' . "\n";

      $data = '<div class="mt-1"></div>';
      $data .= '<span class="page-title moduleCheckoutPaymentListingPageHeader"><h3>' . CLICSHOPPING::getDef('module_checkout_payment_listing_table_heading_payment_method') . '</h3></span>';
      $data .= '<div class="mt-1"></div>';

      if (\count($selection) > 1) {
        $data .= '<div>';
        $data .= '<span class="col-md-8 text-start moduleCheckoutPaymentListingMethod">' . CLICSHOPPING::getDef('module_checkout_payment_listing_text_select_payment_method') . '</span>';
        $data .= '<span class="col-md-4 text-end float-end moduleCheckoutPaymentListingSelect">' . CLICSHOPPING::getDef('module_checkout_payment_listing_title_please_select') . '</span>';
        $data .= '</div>';

      } elseif (!isset($_SESSION['free_shipping']) || $_SESSION['free_shipping'] === false) {
        $data .= '<div class="moduleCheckoutPaymentListingInformation">' . CLICSHOPPING::getDef('module_checkout_payment_listing_text_enter_payment_information') . '</div>';
      }

      $data .= '<table class="table table-striped table-sm table-hover">';
      $data .= '<tbody>';

      $radio_buttons = 0;

      for ($i = 0, $n = \count($selection); $i < $n; $i++) {
        $data .= '<tr class="table-selection">';
        $data .= '<td><strong>' . $selection[$i]['module'] . '</strong></td>';
        $data .= '<td class="ClicShoppingModulesCheckoutPaymentRadio">';

        if (\count($selection) > 1) {
          $data .= '<span class="moduleCheckoutPaymentListingRadio">' . HTML::radioField('payment', $selection[$i]['id'], (isset($_SESSION['payment']) && ($selection[$i]['id'] == $_SESSION['payment'])), 'required aria-required="true"') . '</span>';
        } else {
          $data .= HTML::radioField('payment', $selection[$i]['id'], $selection[$i]['id'], 'required aria-required="true"');
        }

        $data .= '</td>';
        $data .= '</tr>';

        if (isset($selection[$i]['error'])) {
          $data .= '<div class="form-text">' . $selection[$i]['error'] . '</div>';

        } elseif (isset($selection[$i]['fields']) && \is_array($selection[$i]['fields'])) {
          $data .= '<tr>';
          $data .= '<td colspan="2"><table border="0" cellspacing="0" cellpadding="2">';

          for ($j = 0, $n2 = \count($selection[$i]['fields']); $j < $n2; $j++) {
            $data .= '<tr>';
            $data .= '<td>' . $selection[$i]['fields'][$j]['title'] . '</td>';
            $data .= '<td>' . $selection[$i]['fields'][$j]['field'] . '</td>';
            $data .= '</tr>';
          }

          $data .= '</table></td>';
          $data .= '</tr>';
        }

        $radio_buttons++;
      }

      $data .= '</tbody>';
      $data .= '</table>';

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/checkout_payment_listing'));

      $payment_process .= ob_get_clean();

      $payment_process .= '<!--  end cp_checkout_payment_listing -->' . "\n";

      $CLICSHOPPING_Template->addBlock($payment_process, $this->group);
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_CHECKOUT_PAYMENT_LISTING_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_CHECKOUT_PAYMENT_LISTING_STATUS',
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
        'configuration_key' => 'MODULE_CHECKOUT_PAYMENT_LISTING_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_CHECKOUT_PAYMENT_LISTING_SORT_ORDER',
        'configuration_value' => '30',
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
      'MODULE_CHECKOUT_PAYMENT_LISTING_STATUS',
      'MODULE_CHECKOUT_PAYMENT_LISTING_CONTENT_WIDTH',
      'MODULE_CHECKOUT_PAYMENT_LISTING_SORT_ORDER'
    );
  }
}
