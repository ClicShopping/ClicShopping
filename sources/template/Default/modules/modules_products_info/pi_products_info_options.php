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

class pi_products_info_options
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

    $this->title = CLICSHOPPING::getDef('module_products_info_options');
    $this->description = CLICSHOPPING::getDef('module_products_info_options_description');

    if (\defined('MODULE_PRODUCTS_INFO_OPTIONS_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_INFO_OPTIONS_SORT_ORDER') ? (int)MODULE_PRODUCTS_INFO_OPTIONS_SORT_ORDER : 0;
      $this->enabled = (MODULE_PRODUCTS_INFO_OPTIONS_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Tax = Registry::get('Tax');
    $CLICSHOPPING_Category = Registry::get('Category');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    if ($CLICSHOPPING_ProductsCommon->getID() && isset($_GET['Products'])) {

      $content_width = \defined('MODULE_PRODUCTS_INFO_OPTIONS_CONTENT_WIDTH') ? (int)MODULE_PRODUCTS_INFO_OPTIONS_CONTENT_WIDTH : 12;
      $text_position = \defined('MODULE_PRODUCTS_INFO_OPTIONS_POSITION') ? MODULE_PRODUCTS_INFO_OPTIONS_POSITION : 'float-none';

      $CLICSHOPPING_Customer = Registry::get('Customer');
      $CLICSHOPPING_Db = Registry::get('Db');
      $CLICSHOPPING_Template = Registry::get('Template');
      $CLICSHOPPING_Currencies = Registry::get('Currencies');
      $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
      $CLICSHOPPING_Language = Registry::get('Language');
      $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');

      if ($CLICSHOPPING_ProductsAttributes->getCountProductsAttributes() > 0) {
        if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          $QproductsOptionsName = $CLICSHOPPING_Db->prepare('select distinct popt.products_options_id,
                                                                                popt.products_options_name,
                                                                                popt.products_options_type
                                                                from :table_products_options popt,
                                                                     :table_products_attributes patrib
                                                                where patrib.products_id= :products_id
                                                                and patrib.options_id = popt.products_options_id
                                                                and popt.language_id = :language_id
                                                                and (patrib.customers_group_id = :customers_group_id or patrib.customers_group_id = 99)
                                                                and patrib.status = 1
                                                                order by popt.products_options_sort_order,
                                                                         popt.products_options_name
                                                               ');
          $QproductsOptionsName->bindInt(':products_id', (int)$CLICSHOPPING_ProductsCommon->getID());
          $QproductsOptionsName->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
          $QproductsOptionsName->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());

          $QproductsOptionsName->execute();
        } else {
          $QproductsOptionsName = $CLICSHOPPING_Db->prepare('select distinct popt.products_options_id,
                                                                                popt.products_options_name,
                                                                                popt.products_options_type
                                                                from :table_products_options popt,
                                                                     :table_products_attributes patrib
                                                                where patrib.products_id = :products_id
                                                                and patrib.options_id = popt.products_options_id
                                                                and popt.language_id = :language_id
                                                                and (patrib.customers_group_id = 0 or patrib.customers_group_id = 99)
                                                                and patrib.status = 1
                                                                order by popt.products_options_sort_order,
                                                                         popt.products_options_name
                                                               ');
          $QproductsOptionsName->bindInt(':products_id', (int)$CLICSHOPPING_ProductsCommon->getID());
          $QproductsOptionsName->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());

          $QproductsOptionsName->execute();
        }

        $products_options_content_display = '<!-- Start products_options -->' . "\n";
//*****************************
// Strong relations with pi_products_info price.php Don't delete
//*****************************
        if (\defined('MODULE_PRODUCTS_INFO_PRICE_SORT_ORDER')) {
          if (MODULE_PRODUCTS_INFO_PRICE_SORT_ORDER > MODULE_PRODUCTS_INFO_OPTIONS_SORT_ORDER) {
            if (isset($_GET['cPath'])) {
              $products_options_content_display .= HTML::form('cart_add', CLICSHOPPING::link(null, 'Cart&Add&cPath=' . $CLICSHOPPING_Category->getPath(), ' SSL'), 'post', '', ['tokenize' => true]);
            } else {
              $products_options_content_display .= HTML::form('cart_add', CLICSHOPPING::link(null, 'Cart&Add', ' SSL'), 'post', '', ['tokenize' => true]);
            }
          }
        }

        $products_options_content_display .= '<div class="contentText ' . MODULE_PRODUCTS_INFO_OPTIONS_POSITION . ';">';
        $products_options_content_display .= '<div class="mt-1"></div>';
        $products_options_content_display .= '<div class="ModuleProductsInfoPositionOption">';
        $products_options_content_display .= '<span class="ModuleProductsInfoOptionsText"><h3>' . CLICSHOPPING::getDef('text_product_options') . '</h3></span>';

        // Flag: load the external swatch JS only when a color_picker option is present on this page
        $has_color_swatch = false;

        while ($QproductsOptionsName->fetch()) {
          $products_options_array = [];

          $QproductsOptions = $CLICSHOPPING_ProductsAttributes->getProductsAttributesInfo(
            $CLICSHOPPING_ProductsCommon->getID(), $QproductsOptionsName->valueInt('products_options_id'), $CLICSHOPPING_Language->getId());
//
// select
//
          if ($QproductsOptionsName->value('products_options_type') == 'select') {
            while ($QproductsOptions->fetch() !== false) {
              $products_options_array[] = [
                'id'   => $QproductsOptions->valueInt('products_options_values_id'),
                'text' => $QproductsOptions->value('products_options_values_name')
              ];

              if ($QproductsOptions->valueDecimal('options_values_price') != '0') {
                $option_price_display = ' (' . $QproductsOptions->value('price_prefix') . $CLICSHOPPING_Currencies->displayPrice($QproductsOptions->valueDecimal('options_values_price'), $CLICSHOPPING_Tax->getTaxRate($CLICSHOPPING_ProductsCommon->getProductsTaxClassId())) . ') ';

                if (PRICES_LOGGED_IN == 'False') {
                  $option_price_display_d = $option_price_display;
                }

                if ((PRICES_LOGGED_IN == 'True') && (!$CLICSHOPPING_Customer->isLoggedOn())) {
                  $option_price_display_d = '';
                } else {
                  $option_price_display_d = $option_price_display;
                }

                $products_options_array[\count($products_options_array) - 1]['text'] .= $option_price_display_d;
              }
            }

            if (isset($CLICSHOPPING_ShoppingCart->contents[(int)$CLICSHOPPING_ProductsCommon->getID()]['attributes'][$QproductsOptionsName->valueInt('products_options_id')])) {
              $selected_attribute = $CLICSHOPPING_ShoppingCart->contents[(int)$CLICSHOPPING_ProductsCommon->getID()]['attributes'][$QproductsOptionsName->valueInt('products_options_id')];
            } else {
              $selected_attribute = false;
            }

            $products_options_content_display .= '<div>';
            $products_options_content_display .= '<label class="ModuleProductsInfoOptionsName">' . $QproductsOptionsName->value('products_options_name') . ' : </label>';
            $products_options_content_display .= '<span class="ModuleProductsInfoOptionsPullDownMenu">' . HTML::selectMenu('id[' . $QproductsOptionsName->valueInt('products_options_id') . ']', $products_options_array, $selected_attribute, 'class="ModuleProductsInfoOptionsPullDownMenuOptionsInside" required aria-required="true"') . '</span>';
            $products_options_content_display .= '</div>';
            $products_options_content_display .= '<div class="mt-1"></div>';

            //
            // checkbox
            //
          } elseif ($QproductsOptionsName->value('products_options_type') == 'checkbox') {
            while ($QproductsOptions->fetch() !== false) {
              $products_options_array[] = [
                'id'    => $QproductsOptions->valueInt('products_options_values_id'),
                'text'  => $QproductsOptions->value('products_options_values_name'),
                'image' => $QproductsOptions->value('products_attributes_image')
              ];

              if ($QproductsOptions->valueDecimal('options_values_price') != '0') {
                $option_price_display = ' (' . $QproductsOptions->value('price_prefix') . $CLICSHOPPING_Currencies->displayPrice($QproductsOptions->valueDecimal('options_values_price'), $CLICSHOPPING_Tax->getTaxRate($CLICSHOPPING_ProductsCommon->getProductsTaxClassId())) . ') ';

                if (PRICES_LOGGED_IN == 'False') {
                  $option_price_display_d = $option_price_display;
                }

                if ((PRICES_LOGGED_IN == 'True') && (!$CLICSHOPPING_Customer->isLoggedOn())) {
                  $option_price_display_d = '';
                } else {
                  $option_price_display_d = $option_price_display;
                }

                $products_options_array[\count($products_options_array) - 1]['text'] .= $option_price_display_d;
              }
            }

            $selected_attributes = $CLICSHOPPING_ShoppingCart->contents[(int)$CLICSHOPPING_ProductsCommon->getID()]['attributes'][$QproductsOptionsName->valueInt('products_options_id')] ?? [];

            $products_options_content_display .= '<label class="ModuleProductsInfoOptionsName">' . $QproductsOptionsName->value('products_options_name') . ': </label>';

            foreach ($products_options_array as $value) {
              if (!\is_null($value['image'])) {
                if (is_file(CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDirectoryTemplateImages() . $value['image'])) {
                  $products_attributes_image = HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $value['image'], $value['text']) . '   ';
                } else {
                  $products_attributes_image = '';
                }
              } else {
                $products_attributes_image = '';
              }

              $checked = \is_array($selected_attributes) && \in_array($value['id'], $selected_attributes) ? ' checked' : '';
              $chk_id = 'chk_' . $QproductsOptionsName->valueInt('products_options_id') . '_' . $value['id'];

              $products_options_content_display .= '<div class="col-md-12">';
              $products_options_content_display .= '<span class="ModuleProductsInfoOptionsPullDownMenu">';
              $products_options_content_display .= '<div class="custom-control custom-checkbox">';
              $products_options_content_display .= '<input type="checkbox" name="id[' . $QproductsOptionsName->valueInt('products_options_id') . '][]" value="' . $value['id'] . '" class="custom-control-input" id="' . $chk_id . '"' . $checked . '>';
              $products_options_content_display .= '<label class="custom-control-label" for="' . $chk_id . '">' . $products_attributes_image . $value['text'] . '</label>';
              $products_options_content_display .= '</div>';
              $products_options_content_display .= '</span>';
              $products_options_content_display .= '</div>';
            }

            //
            // text
            //
          } elseif ($QproductsOptionsName->value('products_options_type') == 'text') {
            $selected_attribute = $CLICSHOPPING_ShoppingCart->contents[(int)$CLICSHOPPING_ProductsCommon->getID()]['attributes'][$QproductsOptionsName->valueInt('products_options_id')] ?? '';

            $products_options_content_display .= '<div>';
            $products_options_content_display .= '<label class="ModuleProductsInfoOptionsName">' . $QproductsOptionsName->value('products_options_name') . ': </label>';
            $products_options_content_display .= '<input type="text" name="id[' . $QproductsOptionsName->valueInt('products_options_id') . ']" value="' . HTML::outputProtected((string)$selected_attribute) . '" class="form-control ModuleProductsInfoOptionsText" required aria-required="true">';
            $products_options_content_display .= '</div>';

            //
            // textarea
            //
          } elseif ($QproductsOptionsName->value('products_options_type') == 'textarea') {
            $selected_attribute = $CLICSHOPPING_ShoppingCart->contents[(int)$CLICSHOPPING_ProductsCommon->getID()]['attributes'][$QproductsOptionsName->valueInt('products_options_id')] ?? '';

            $products_options_content_display .= '<div>';
            $products_options_content_display .= '<label class="ModuleProductsInfoOptionsName">' . $QproductsOptionsName->value('products_options_name') . ': </label>';
            $products_options_content_display .= '<textarea name="id[' . $QproductsOptionsName->valueInt('products_options_id') . ']" class="form-control ModuleProductsInfoOptionsTextarea" rows="3" required aria-required="true">' . HTML::outputProtected((string)$selected_attribute) . '</textarea>';
            $products_options_content_display .= '</div>';

            //
            // color_picker — délégué à HTML::colorSwatchField() + JS externe
            //
          } elseif ($QproductsOptionsName->value('products_options_type') == 'color_picker') {
            while ($QproductsOptions->fetch() !== false) {
              $swatch_price = '';
              if ($QproductsOptions->valueDecimal('options_values_price') != '0') {
                $option_price_display = ' (' . $QproductsOptions->value('price_prefix') . $CLICSHOPPING_Currencies->displayPrice($QproductsOptions->valueDecimal('options_values_price'), $CLICSHOPPING_Tax->getTaxRate($CLICSHOPPING_ProductsCommon->getProductsTaxClassId())) . ')';

                if (PRICES_LOGGED_IN == 'False') {
                  $swatch_price = $option_price_display;
                }

                if ((PRICES_LOGGED_IN == 'True') && (!$CLICSHOPPING_Customer->isLoggedOn())) {
                  $swatch_price = '';
                } else {
                  $swatch_price = $option_price_display;
                }
              }

              // On sépare le nom (pour le panier) et l'hexa (pour le visuel)
              $human_name = $QproductsOptionsName->value('products_options_name');
              $color_hex = $QproductsOptions->value('products_options_values_name');

              $products_options_array[] = [
                'id'    => $QproductsOptions->valueInt('products_options_values_id'),
                'text'  => $human_name, // Sera affiché dans le panier
                'color' => $color_hex,  // SERA UTILISÉ PAR LA FONCTION colorSwatchField
                'price' => $swatch_price,
              ];
            }

            if (isset($CLICSHOPPING_ShoppingCart->contents[(int)$CLICSHOPPING_ProductsCommon->getID()]['attributes'][$QproductsOptionsName->valueInt('products_options_id')])) {
              $selected_attribute = $CLICSHOPPING_ShoppingCart->contents[(int)$CLICSHOPPING_ProductsCommon->getID()]['attributes'][$QproductsOptionsName->valueInt('products_options_id')];
            } else {
              $selected_attribute = false;
            }
            $option_label = $QproductsOptionsName->value('products_options_name');
            $group_id     = 'color_group_' . $QproductsOptionsName->valueInt('products_options_id');

            $products_options_content_display .= '<div class="mb-2">';
            $products_options_content_display .= '<label class="ModuleProductsInfoOptionsName">' . HTML::outputProtected($option_label) . ': </label>';
            $products_options_content_display .= HTML::colorSwatchField(
              'id[' . $QproductsOptionsName->valueInt('products_options_id') . ']',
              $products_options_array,
              $selected_attribute,
              $group_id,
              $option_label
            );
	    
            $products_options_content_display .= '</div>';

            $has_color_swatch = true;

            //
            // radio (type par défaut, avec image optionnelle)
            // "required" uniquement sur le premier radio du groupe.
            //
          } else {
            while ($QproductsOptions->fetch() !== false) {
              $products_options_array[] = [
                'id'    => $QproductsOptions->valueInt('products_options_values_id'),
                'text'  => $QproductsOptions->value('products_options_values_name'),
                'image' => $QproductsOptions->value('products_attributes_image')
              ];

              if ($QproductsOptions->valueDecimal('options_values_price') != '0') {
                $option_price_display = ' (' . $QproductsOptions->value('price_prefix') . $CLICSHOPPING_Currencies->displayPrice($QproductsOptions->valueDecimal('options_values_price'), $CLICSHOPPING_Tax->getTaxRate($CLICSHOPPING_ProductsCommon->getProductsTaxClassId())) . ') ';

                if (PRICES_LOGGED_IN == 'False') {
                  $option_price_display_d = $option_price_display;
                }
                if ((PRICES_LOGGED_IN == 'True') && (!$CLICSHOPPING_Customer->isLoggedOn())) {
                  $option_price_display_d = '';
                } else {
                  $option_price_display_d = $option_price_display;
                }

                $products_options_array[\count($products_options_array) - 1]['text'] .= $option_price_display_d;
              }
            }

            if (isset($CLICSHOPPING_ShoppingCart->contents[(int)$CLICSHOPPING_ProductsCommon->getID()]['attributes'][$QproductsOptionsName->valueInt('products_options_id')])) {
              $selected_attribute = $CLICSHOPPING_ShoppingCart->contents[(int)$CLICSHOPPING_ProductsCommon->getID()]['attributes'][$QproductsOptionsName->valueInt('products_options_id')];
            } else {
              $selected_attribute = false;
            }

            $products_options_content_display .= '<label class="ModuleProductsInfoOptionsName">' . $QproductsOptionsName->value('products_options_name') . ': </label>';

            // "required" on the first radio only — the browser treats all same-name radios
            // as one group for constraint validation, so one "required" is enough and avoids
            // false validation failures on visually hidden inputs.
            $first_radio = true;

            foreach ($products_options_array as $value) {
              if (!\is_null($value['image'])) {
                if (is_file(CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDirectoryTemplateImages() . $value['image'])) {
                  $products_attributes_image = HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $value['image'], $value['text']) . '   ';
                } else {
                  $products_attributes_image = '';
                }
              } else {
                $products_attributes_image = '';
              }

              $radio_id   = 'option_' . $QproductsOptionsName->valueInt('products_options_id') . '_' . (int)$value['id'];
              $is_checked = ($selected_attribute !== false && (string)$selected_attribute === (string)$value['id']);

              $required_attr = $first_radio ? ' required aria-required="true"' : '';
              $checked_attr  = $is_checked ? ' checked="checked"' : '';

              $products_options_content_display .= '<div class="col-md-12">';
              $products_options_content_display .= '<span class="ModuleProductsInfoOptionsPullDownMenu">';
              $products_options_content_display .= '<div class="custom-control custom-radio">';
              $products_options_content_display .= '<input type="radio"'
                . ' name="id[' . $QproductsOptionsName->valueInt('products_options_id') . ']"'
                . ' value="' . (int)$value['id'] . '"'
                . ' id="' . $radio_id . '"'
                . ' class="custom-control-input"'
                . $required_attr
                . $checked_attr
                . '>';
              $products_options_content_display .= '<label class="custom-control-label" for="' . $radio_id . '">'
                . $products_attributes_image
                . HTML::outputProtected($value['text'])
                . '</label>';
              $products_options_content_display .= '</div>';
              $products_options_content_display .= '</span>';
              $products_options_content_display .= '</div>';

              $first_radio = false;
            }
          }

          $products_options_content_display .= '<div class="mt-1"></div>';
        }// end while

        $products_options_content_display .= '</div>';
        $products_options_content_display .= '</div>' . "\n";

      // Strong relations with pi_products_info_price.php Don't delete
        if (\defined('MODULE_PRODUCTS_INFO_PRICE_SORT_ORDER') && MODULE_PRODUCTS_INFO_PRICE_SORT_ORDER !== '') {
          $module_products_info_price_sort_order = (int)MODULE_PRODUCTS_INFO_PRICE_SORT_ORDER;
        } else {
          $module_products_info_price_sort_order = -1;
        }

        if ((int)MODULE_PRODUCTS_INFO_OPTIONS_SORT_ORDER > $module_products_info_price_sort_order) {
          $products_options_content_display .= '</form>' . "\n";
        }

        // Load the external JS only when this page actually has a color_picker option
        if ($has_color_swatch) {
          $footer = '<!-- Color Picker -->' . "\n";
          $footer .= '<script defer src="'. CLICSHOPPING::link($CLICSHOPPING_Template->getTemplateDefaultJavaScript('clicshopping/products_info_options_color_swatch.js')) . '"></script>' . "\n";
          $footer .= '<!-- Color Picker end -->' . "\n";

          $CLICSHOPPING_Template->addBlock($footer, 'footer_scripts');
        }

        $products_options_content_display .= '<!-- end products_options -->' . "\n";

        $CLICSHOPPING_Template->addBlock($products_options_content_display, $this->group);
      } // end total
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_PRODUCTS_INFO_OPTIONS_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_OPTIONS_STATUS',
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
        'configuration_key' => 'MODULE_PRODUCTS_INFO_OPTIONS_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Where Do you want to display the module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_OPTIONS_POSITION',
        'configuration_value' => 'float-none',
        'configuration_description' => 'Affiche l\'option du produit à gauche ou à droite<br><br><i>(Valeur Left = Gauche <br>Valeur Right = Droite <br>Valeur None = Aucun)</i>',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_OPTIONS_SORT_ORDER',
        'configuration_value' => '90',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '3',
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
      'MODULE_PRODUCTS_INFO_OPTIONS_STATUS',
      'MODULE_PRODUCTS_INFO_OPTIONS_CONTENT_WIDTH',
      'MODULE_PRODUCTS_INFO_OPTIONS_POSITION',
      'MODULE_PRODUCTS_INFO_OPTIONS_SORT_ORDER'
    );
  }
}
