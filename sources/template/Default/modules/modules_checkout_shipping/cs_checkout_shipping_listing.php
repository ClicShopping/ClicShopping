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
use ClicShopping\Sites\Shop\Shipping as Delivery;
use ClicShopping\Sites\Shop\Tax;

class cs_checkout_shipping_listing
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

    $this->title = CLICSHOPPING::getDef('module_checkout_shipping_listing_title');
    $this->description = CLICSHOPPING::getDef('module_checkout_shipping_listing_description');

    if (\defined('MODULE_CHECKOUT_SHIPPING_LISTING_STATUS')) {
      $this->sort_order = (int)MODULE_CHECKOUT_SHIPPING_LISTING_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_CHECKOUT_SHIPPING_LISTING_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($_GET['Checkout'], $_GET['Shipping'])) {
      if (!Registry::exists('Shipping')) {
        Registry::set('Shipping', new Delivery());
      }

      $CLICSHOPPING_Shipping = Registry::get('Shipping');

      $quotes = $CLICSHOPPING_Shipping->getQuote();

      if (isset($_GET['Checkout'], $_GET['Shipping'])) {
        $content_width = (int)MODULE_CHECKOUT_SHIPPING_LISTING_CONTENT_WIDTH;

        $shipping_listing = '<!-- start checkout_shipping_listing -->' . "\n";

        if ($CLICSHOPPING_Shipping->geCountShippingModules() > 0) {
          $data = '<div class="mt-1"></div>';
          $data .= '<span class="page-title moduleCheckoutShippingListingPageHeader"><h3>' . CLICSHOPPING::getDef('module_checkout_shipping_table_heading_shipping_method') . '</h3></span>';

          if (\count($quotes) > 1 && \count($quotes[0]) > 1 && \is_array($quotes)) {
            $data .= '<div>';
            $data .= '<span class="col-md-8 text-start moduleCheckoutShippingListingMethod">' . CLICSHOPPING::getDef('module_checkout_shipping_text_choose_shipping_method') . '</span>';
            $data .= '<span class="col-md-4 text-end float-end moduleCheckoutShippingListingSelect">' . CLICSHOPPING::getDef('module_checkout_shipping_title_please_select') . '</span>';
            $data .= '</div>';
            $data .= '<div class="mt-1"></div>';
          } elseif (!isset($_SESSION['free_shipping']) || $_SESSION['free_shipping'] === false) {
            $data .= '<div class="mt-1"></div>';
            $data .= '<div class="moduleCheckoutShippingListingInformation">' . CLICSHOPPING::getDef('module_checkout_shipping_text_enter_shipping_information') . '</div>';
          }

          $data .= '<div class="mt-1"></div>';
          $data .= '<table class="table table-striped table-sm table-hover">';
          $data .= '<tbody>';

          if (isset($_SESSION['free_shipping']) && $_SESSION['free_shipping'] === true) {
            $data .= '<div class="moduleCheckoutShippingListingFreeTitle">' . CLICSHOPPING::getDef('module_checkout_shipping_free_shipping_title') . '&nbsp;' . $quotes['icon'] . '</div>';
            $data .= '<div style="padding-left: 15px;">';
            $data .= CLICSHOPPING::getDef('module_checkout_shipping_free_shipping_description', ['free_shipping_amount' => $CLICSHOPPING_Currencies->format(MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)]) . HTML::hiddenField('shipping', 'free_free');
            $data .= '</div>';
          } else {
            // load the selected shipping module
            $radio_buttons = 0;

            foreach ($quotes as $n => $quote) {
              if (isset($quote['methods'])) {
                for ($j = 0, $n2 = \count($quote['methods']); $j < $n2; $j++) {
                  $data .= '<tr>' . "\n";
                  $data .= '<tr>';
                  $data .= '<td>';

                  $data .= '<strong>' . $quote['module'] . '&nbsp;</strong>';

                  if (!empty($quote['methods'][$j]['title'])) {
                    $data .= $quote['methods'][$j]['title'] . '&nbsp;</strong>';
                  }

                  if (!empty($quote['methods'][$j]['info'])) {
                    $data .= $quote['methods'][$j]['info'] . '&nbsp;';
                  }

                  if (isset($quote['icon']) && !empty($quote['icon'])) {
                    $data .= '&nbsp;' . $quote['icon'];
                  }

                  if (isset($quote['error'])) {
                    $data .= '<div class="form-text">' . $quote['error'] . '</div>';
                  }

                  $data .= '</td>';

                  if (($n >= 1) || ($n2 >= 1)) {
                    if (isset($_SESSION['shipping']['id'])) {
                      $checked = $quote['id'] . '_' . $quote['methods'][$j]['id'] === $_SESSION['shipping']['id'];
                    } else {
                      $checked = true;
                    }

                    $data .= '<td class="text-end">';

                    if (isset($quotes['error'])) {
                      $data .= '&nbsp;';
                    } else {
                      $data .= '<span class="moduleCheckoutShippingListingCurrencies">' . $CLICSHOPPING_Currencies->format(Tax::addTax($quote['methods'][$j]['cost'], $quote['tax'] ?? 0)) . '</span>&nbsp;&nbsp';
                      $data .= '<span class="moduleCheckoutShippingListingRadio">' . HTML::radioField('shipping', $quote['id'] . '_' . $quote['methods'][$j]['id'], $checked, 'required aria-required="true"') . '</span>';
                    }

                    $data .= '</td>';
                    $data .= '</tr>';
                  } else {
                    $data .= '<td class="text-end">';
                    $data .= $CLICSHOPPING_Currencies->format(Tax::addTax($quote['methods'][$j]['cost'], $quote['tax'] ?? 0)) . HTML::hiddenField('shipping', $quote['id'] . '_' . $quote['methods'][$j]['id']);
                    $data .= '</td>';
                  }

                  $data .= '</tr>';
                }
              }

              $radio_buttons++;
            }
          }

          $data .= '</tbody>';
          $data .= '</table>';
        }

        if (!empty($data)) {
          ob_start();
          require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/checkout_shipping_listing'));

          $shipping_listing .= ob_get_clean();
        }
	
        $shipping_listing .= '<!--  end checkout_shipping_listing -->' . "\n";

        $CLICSHOPPING_Template->addBlock($shipping_listing, $this->group);
      }
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_CHECKOUT_SHIPPING_LISTING_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_CHECKOUT_SHIPPING_LISTING_STATUS',
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
        'configuration_key' => 'MODULE_CHECKOUT_SHIPPING_LISTING_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_CHECKOUT_SHIPPING_LISTING_SORT_ORDER',
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
      'MODULE_CHECKOUT_SHIPPING_LISTING_STATUS',
      'MODULE_CHECKOUT_SHIPPING_LISTING_CONTENT_WIDTH',
      'MODULE_CHECKOUT_SHIPPING_LISTING_SORT_ORDER'
    );
  }
}
