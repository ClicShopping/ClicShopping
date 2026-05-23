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
use ClicShopping\Sites\Shop\Tax;

class cc_checkout_confirmation_products_listing
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

    $this->title = CLICSHOPPING::getDef('module_checkout_confirmation_products_listing_title');
    $this->description = CLICSHOPPING::getDef('module_checkout_confirmation_products_listing_description');

    if (\defined('MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_STATUS')) {
      $this->sort_order = (int)MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_SORT_ORDER;
      $this->enabled = (MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    if (isset($_GET['Checkout'], $_GET['Confirmation']) && $CLICSHOPPING_Customer->isLoggedOn()) {
      $content_width = (int)MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_CONTENT_WIDTH;

      $confirmation = '  <!-- cc_checkout_confirmation_products_listing start -->' . "\n";

      $confirmation .= '<div class="col-md-' . $content_width . '">';
      $confirmation .= '<div class="mt-1"></div>';
      $confirmation .= '<div class="page-title moduleCheckoutConfirmationProductsListingPageHeader"><h3>' . CLICSHOPPING::getDef('module_checkout_confirmation_products_listing_information') . '</h3></div>';
      $confirmation .= '<div class="mt-1"></div>';
      $confirmation .= '<div class="mt-1"></div>';
      $confirmation .= '<div>';

      if (\count($CLICSHOPPING_Order->info['tax_groups']) > 1) {
        $confirmation .= '<div class="card moduleCheckoutConfirmationProductsListingCard">';
        $confirmation .= '<div class="card-header moduleCheckoutConfirmationProductsListingHeader"><strong>' . CLICSHOPPING::getDef('module_checkout_confirmation_products_listing_heading_products') . '</strong>';
        $confirmation .= HTML::link(CLICSHOPPING::link(null, 'Cart'), '<span class="orderEdit">(' . CLICSHOPPING::getDef('module_checkout_confirmation_products_listing_text_edit') . ')</span>');
        $confirmation .= '</div>';
        $confirmation .= '<div class="hr"></div>';
        $confirmation .= '<div class="card-block moduleCheckoutConfirmationProductsListingCardBlock">';
        $confirmation .= '<div class="mt-1"></div>';
        $confirmation .= '<table width="100%">';
        $confirmation .= '<tr>';
        $confirmation .= '<td class="text-end"><strong>' . CLICSHOPPING::getDef('module_checkout_confirmation_products_listing_heading_tax') . '</strong></td>';
        $confirmation .= '<td class="text-end"><strong>' . CLICSHOPPING::getDef('module_checkout_confirmation_products_listing_heading_total') . '</strong></td>';
        $confirmation .= '</tr>';
        $confirmation .= '</table>';
        $confirmation .= '</div>';

      } else {
        $confirmation .= '<div class="card moduleCheckoutConfirmationProductsListingCard">';
        $confirmation .= '<div class="card-header moduleCheckoutConfirmationProductsListingHeader"><strong>' . CLICSHOPPING::getDef('module_checkout_confirmation_products_listing_heading_products') . '</strong>';
        $confirmation .= HTML::link(CLICSHOPPING::link(null, 'Cart'), '<span class="orderEdit">(' . CLICSHOPPING::getDef('module_checkout_confirmation_products_listing_text_edit') . ')</span>');
        $confirmation .= '</div>';
      }

      $confirmation .= '<div class="card-block moduleCheckoutConfirmationProductsListingCardBlock">';
      $confirmation .= '<div class="mt-1"></div>';
      $confirmation .= '<table width="100%">';

      for ($i = 0, $n = \count($CLICSHOPPING_Order->products); $i < $n; $i++) {
        $data = '<tr>' . "\n";
        $data .= '<td class="text-end" valign="top" width="30">' . $CLICSHOPPING_Order->products[$i]['qty'] . '&nbsp;x</td>' . "\n";
        $data .= '<td valign="top">' . $CLICSHOPPING_Order->products[$i]['name'] . ' (' . $CLICSHOPPING_Currencies->displayPrice($CLICSHOPPING_Order->products[$i]['final_price'], $CLICSHOPPING_Order->products[$i]['tax'], 1) . ')';

        if (STOCK_CHECK == 'True') {
          $data .= $CLICSHOPPING_ProductsCommon->getCheckStock($CLICSHOPPING_Order->products[$i]['id'], $CLICSHOPPING_Order->products[$i]['qty']);
        }

        if ((isset($CLICSHOPPING_Order->products[$i]['attributes'])) && (\count($CLICSHOPPING_Order->products[$i]['attributes']) > 0)) {
          for ($j = 0, $n2 = \count($CLICSHOPPING_Order->products[$i]['attributes']); $j < $n2; $j++) {
            $reference = '';

            if (!empty($CLICSHOPPING_Order->products[$i]['attributes'][$j]['reference'])) {
              $reference = $CLICSHOPPING_Order->products[$i]['attributes'][$j]['reference'] . ' / ';
            }

            if (!\is_null($CLICSHOPPING_Order->products[$i]['attributes'][$j]['products_attributes_image'])) {
              if (is_file(CLICSHOPPING::getConfig('Shop') . $CLICSHOPPING_Template->getDirectoryTemplateImages() . $CLICSHOPPING_Order->products[$i]['attributes'][$j]['products_attributes_image'])) {
                $products_attributes_image = HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $CLICSHOPPING_Order->products[$i]['attributes'][$j]['products_attributes_image'], $CLICSHOPPING_Order->products[$i]['attributes'][$j]['option'] . '   ', 30, 30);
              } else {
                $products_attributes_image = '     ';
              }
            } else {
              $products_attributes_image = '     ';
            }

            $data .= '<br /><small>&nbsp;<i> - ' . $products_attributes_image . $reference . $CLICSHOPPING_Order->products[$i]['attributes'][$j]['option'] . ': ' . $CLICSHOPPING_Order->products[$i]['attributes'][$j]['price'] . '</i></small>';
          }
        }

        $data .= '</td>' . "\n";

        if (\count($CLICSHOPPING_Order->info['tax_groups']) > 1) {
          $data .= '<td class="text-end" vertical-align="top">' . Tax::displayTaxRateValue($CLICSHOPPING_Order->products[$i]['tax']) . '</td>' . "\n";
        }

        $data .= '<td  class="text-end" vertical-align="top">';
        $data .= $CLICSHOPPING_Currencies->displayPrice($CLICSHOPPING_Order->products[$i]['final_price'], $CLICSHOPPING_Order->products[$i]['tax'], $CLICSHOPPING_Order->products[$i]['qty']);
        $data .= '</td>' . "\n";
        $data .= '</tr>' . "\n";

        $confirmation .= $data;
      }

      $confirmation .= '</table>';
      $confirmation .= '</div>';
      $confirmation .= '</div>';
      $confirmation .= '</div>';
      $confirmation .= '</div>';

      $confirmation .= '<!--  cc_checkout_confirmation_products_listing end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($confirmation, $this->group);
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_STATUS',
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
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_SORT_ORDER',
        'configuration_value' => '50',
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
      'MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_STATUS',
      'MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_CONTENT_WIDTH',
      'MODULE_CHECKOUT_CONFIRMATION_PRODUCTS_LISTING_SORT_ORDER'
    );
  }
}