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
use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\CockpitAI\ProductsTracking;

class bm_specials
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;
  public $pages;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_boxes_specials_title');
    $this->description = CLICSHOPPING::getDef('module_boxes_specials_description');

    if (\defined('MODULE_BOXES_SPECIALS_STATUS')) {
      $this->sort_order = defined('MODULE_BOXES_SPECIALS_SORT_ORDER') ? (int)MODULE_BOXES_SPECIALS_SORT_ORDER : 0;
      $this->enabled = defined('MODULE_BOXES_SPECIALS_STATUS') && MODULE_BOXES_SPECIALS_STATUS == 'True';
      $this->pages = defined('MODULE_BOXES_SPECIALS_DISPLAY_PAGES') ? MODULE_BOXES_SPECIALS_DISPLAY_PAGES : '';

      $this->group = (defined('MODULE_BOXES_SPECIALS_CONTENT_PLACEMENT') && MODULE_BOXES_SPECIALS_CONTENT_PLACEMENT == 'Left Column') ? 'boxes_column_left' : 'boxes_column_right';
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Service = Registry::get('Service');
    $CLICSHOPPING_Banner = Registry::get('Banner');
    $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');
    $CLICSHOPPING_Language = Registry::get('Language');

    // normalisation position module (left/right)
    $module_position = ($this->group === 'boxes_column_left') ? 'left' : 'right';

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $Qproducts = $CLICSHOPPING_Db->prepare('select p.products_id
                                                    from :table_specials s,
                                                          :table_products p left join :table_products_groups g on p.products_id = g.products_id,
                                                          :table_products_to_categories p2c,
                                                          :table_categories c
                                                    where (p.products_status = 1
                                                            and g.price_group_view = 1
                                                           )
                                                        or (p.products_status = 1
                                                           and g.price_group_view <> 1
                                                           )
                                                    and g.products_group_view = 1
                                                    and s.status = 1
                                                    and p.products_id = s.products_id
                                                    and p.products_archive = 0
                                                    and g.customers_group_id = :customers_group_id
                                                    and p.products_id <> :products_id
                                                    and p.products_id = p2c.products_id
                                                    and p2c.categories_id = c.categories_id
                                                    and c.status = 1
                                                    and (s.customers_group_id = :customers_group_id or s.customers_group_id = 99)
                                                    order by rand(),
                                                             s.specials_date_added desc
                                                    limit :limit
                                                  ');

      $Qproducts->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
      $Qproducts->bindInt(':products_id', $CLICSHOPPING_ProductsCommon->getID());
      $Qproducts->bindInt(':limit', defined('MODULE_BOXES_SPECIALS_MAX_DISPLAY_LIMIT') ? (int)MODULE_BOXES_SPECIALS_MAX_DISPLAY_LIMIT : 0);
      $Qproducts->execute();

    } else {

      $Qproducts = $CLICSHOPPING_Db->prepare('select p.products_id
                                                  from :table_specials s,
                                                       :table_products p,
                                                       :table_products_to_categories p2c,
                                                       :table_categories c
                                                  where p.products_status = 1
                                                  and s.products_id = p.products_id
                                                  and s.status = 1
                                                  and p.products_view = 1
                                                  and (s.customers_group_id = 0 or s.customers_group_id = 99)
                                                  and p.products_archive = 0
                                                  and p.products_id <> :products_id
                                                  and p.products_id = p2c.products_id
                                                  and p2c.categories_id = c.categories_id
                                                  and c.status = 1
                                                  order by rand(),
                                                           s.specials_date_added desc
                                                  limit :limit
                                                ');

      $Qproducts->bindInt(':products_id', $CLICSHOPPING_ProductsCommon->getID());
      $Qproducts->bindInt(':limit', defined('MODULE_BOXES_SPECIALS_MAX_DISPLAY_LIMIT') ? (int)MODULE_BOXES_SPECIALS_MAX_DISPLAY_LIMIT : 0);
      $Qproducts->execute();
    }

    $col = 0;

    if ($Qproducts->rowCount() > 0) {
      $specials_banner = '';

      if ($CLICSHOPPING_Service->isStarted('Banner')) {
        if ($banner = $CLICSHOPPING_Banner->bannerExists('dynamic', defined('MODULE_BOXES_SPECIALS_BANNER_GROUP') ? MODULE_BOXES_SPECIALS_BANNER_GROUP : '')) {
          $specials_banner = $CLICSHOPPING_Banner->displayBanner('static', $banner) . '<br /><br />';
        }
      }

      $data = '<!-- Boxe specials start-->' . "\n";
      $data .= '<section class="boxe_specials" id="boxe_specials">';
      $data .= '<div class="mt-1"></div>';
      $data .= '<div class="boxeBannerContentsSpecials">' . $specials_banner . '</div>';
      $data .= '<div class="card boxeContainerSpecials">';
      $data .= '<div class="card-header boxeHeadingSpecials"><span class="card-title boxeTitleSpecials">' . HTML::link(CLICSHOPPING::link(null, 'Products&Specials'), CLICSHOPPING::getDef('module_boxes_specials_box_title')) . '</span></div>';
      $data .= '<div class="card-block text-center boxeContentArroundSpecials">';
      $data .= ' <div class="mt-1"></div>';

      while ($Qproducts->fetch()) {
        $products_id = $Qproducts->valueInt('products_id');
        //trackingProduct
        ProductsTracking::insertProductTracking($products_id,  $this->code, $module_position, $this->sort_order, $CLICSHOPPING_Language->getId(), null, 0.15);

        // **************************
        //    product name
        // **************************
        $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($products_id);

        $products_name = $CLICSHOPPING_ProductsCommon->getProductsName($products_id);
        $products_name_image = $CLICSHOPPING_ProductsFunctionTemplate->getProductsNameUrl($products_id);
        // *************************
        //       Flash discount
        // **************************
        $products_flash_discount = '';
        if ($CLICSHOPPING_ProductsCommon->getProductsFlashDiscount($products_id) != '') {
          $products_flash_discount = CLICSHOPPING::getDef('text_flash_discount') . '<br/>' . $CLICSHOPPING_ProductsCommon->getProductsFlashDiscount($products_id);
        }
        // *************************
        // display the differents prices before button
        // **************************
        $product_price = $CLICSHOPPING_ProductsCommon->getCustomersPrice($products_id);

        // **************************
        // See the button more view details
        // **************************
        if (defined('MODULE_BOXES_SPECIAL_DETAIL_BUTTON') && MODULE_BOXES_SPECIAL_DETAIL_BUTTON == 'True') {
          $button_small_view_details = HTML::button(CLICSHOPPING::getDef('button_detail'), null, $products_name_url, 'info', null, 'sm');
        } else {
          $button_small_view_details = '';
        }

        $products_image = HTML::link($products_name_url, HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $CLICSHOPPING_ProductsCommon->getProductsImage($products_id), HTML::outputProtected($products_name), defined('SMALL_IMAGE_WIDTH') ? (int)SMALL_IMAGE_WIDTH : 0, defined('SMALL_IMAGE_HEIGHT') ? (int)SMALL_IMAGE_HEIGHT : 0));

        // **************************
        //Ticker Image
        // **************************
        if ($CLICSHOPPING_ProductsCommon->getProductsTickerSpecials($products_id) == 'True' && defined('MODULE_BOXES_SPECIALS_TICKER') && MODULE_BOXES_SPECIALS_TICKER == 'True') {
          $products_image .= HTML::link($products_name_url, HTML::tickerImage(CLICSHOPPING::getDef('text_ticker_specials'), 'ModulesBoxeBootstrapTickerSpecial', $CLICSHOPPING_ProductsCommon->getProductsTickerSpecials($products_id)));
        } elseif ($CLICSHOPPING_ProductsCommon->getProductsTickerFavorites($products_id) == 'True' && defined('MODULE_BOXES_SPECIALS_TICKER') &&  MODULE_BOXES_SPECIALS_TICKER == 'True') {
          $products_image .= HTML::link($products_name_url, HTML::tickerImage(CLICSHOPPING::getDef('text_ticker_favorite'), 'ModulesBoxeBootstrapTickerFavorite', $CLICSHOPPING_ProductsCommon->getProductsTickerFavorites($products_id)));
        } elseif ($CLICSHOPPING_ProductsCommon->getProductsTickerFeatured($products_id) == 'True' && defined('MODULE_BOXES_SPECIALS_TICKER') && MODULE_BOXES_SPECIALS_TICKER == 'True') {
          $products_image .= HTML::link($products_name_url, HTML::tickerImage(CLICSHOPPING::getDef('text_ticker_featured'), 'ModulesBoxeBootstrapTickerFeatured', $CLICSHOPPING_ProductsCommon->getProductsTickerFeatured($products_id)));
        } elseif ($CLICSHOPPING_ProductsCommon->getProductsTickerProductsNew($products_id) == 'True' && defined('MODULE_BOXES_SPECIALS_TICKER') && MODULE_BOXES_SPECIALS_TICKER == 'True') {
          $products_image .= HTML::link($products_name_url, HTML::tickerImage(CLICSHOPPING::getDef('text_ticker_products_new'), 'ModulesBoxeBootstrapTickerNew', $CLICSHOPPING_ProductsCommon->getProductsTickerProductsNew($products_id)));
        }

        if (defined('MODULE_BOXES_SPECIALS_POURCENTAGE_TICKER') && MODULE_BOXES_SPECIALS_POURCENTAGE_TICKER == 'True' && !\is_null($CLICSHOPPING_ProductsCommon->getProductsTickerSpecialsPourcentage($products_id))) {
          $ticker = HTML::link($products_name_url, HTML::tickerImage($CLICSHOPPING_ProductsCommon->getProductsTickerSpecialsPourcentage($products_id), 'ModulesBoxeBootstrapTickerSpecialPourcentage', true));
        } else {
          $ticker = '';
        }

        ob_start();
        require($CLICSHOPPING_Template->getTemplateModules('/modules_boxes/content/specials'));
        $data .= ob_get_clean();

        $col++;
        if ($col > 0) {
          $col = 0;
        }
      } //end while

      $data .= '</div>';
      $data .= '<div class="card-footer boxeBottomContentsSpecials"></div>';
      $data .= '</div>' . "\n";
      $data .= '</section>' . "\n";

      $data .= '<!-- Boxe Specials end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($data, $this->group);
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_BOXES_SPECIALS_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_BOXES_SPECIALS_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please choose where the boxe must be displayed',
        'configuration_key' => 'MODULE_BOXES_SPECIALS_CONTENT_PLACEMENT',
        'configuration_value' => 'Right Column',
        'configuration_description' => 'Choose where the boxe must be displayed',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'Left Column\', \'Right Column\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the banner group for the image',
        'configuration_key' => 'MODULE_BOXES_SPECIALS_BANNER_GROUP',
        'configuration_value' => SITE_THEMA . '_boxe_specials',
        'configuration_description' => 'Indicate the banner group<br /><br /><strong>Note :</strong><br /><i>The group must be created or selected whtn you create a banner in Marketing / banner</i>',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display details button ?',
        'configuration_key' => 'MODULE_BOXES_SPECIAL_DETAIL_BUTTON',
        'configuration_value' => 'False',
        'configuration_description' => 'display details button ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'How many products Do you want to display ?',
        'configuration_key' => 'MODULE_BOXES_SPECIALS_MAX_DISPLAY_LIMIT',
        'configuration_value' => '1',
        'configuration_description' => 'Display products',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display a message News / Specials / Favorites / Featured ?',
        'configuration_key' => 'MODULE_BOXES_SPECIALS_TICKER',
        'configuration_value' => 'False',
        'configuration_description' => 'Display a message News / Specials / Favorites / Featured',
        'configuration_group_id' => '6',
        'sort_order' => '9',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the discount pourcentage (specials) ?',
        'configuration_key' => 'MODULE_BOXES_SPECIALS_POURCENTAGE_TICKER',
        'configuration_value' => 'False',
        'configuration_description' => 'Display the discount pourcentage (specials)',
        'configuration_group_id' => '6',
        'sort_order' => '9',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_BOXES_SPECIALS_SORT_ORDER',
        'configuration_value' => '120',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate the page where the module is displayed',
        'configuration_key' => 'MODULE_BOXES_SPECIALS_DISPLAY_PAGES',
        'configuration_value' => 'all',
        'configuration_description' => 'Select the pages where the boxe must be present.',
        'configuration_group_id' => '6',
        'sort_order' => '6',
        'set_function' => 'clic_cfg_set_select_pages_list',
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
    return array('MODULE_BOXES_SPECIALS_STATUS',
      'MODULE_BOXES_SPECIALS_CONTENT_PLACEMENT',
      'MODULE_BOXES_SPECIALS_BANNER_GROUP',
      'MODULE_BOXES_SPECIAL_DETAIL_BUTTON',
      'MODULE_BOXES_SPECIALS_MAX_DISPLAY_LIMIT',
      'MODULE_BOXES_SPECIALS_TICKER',
      'MODULE_BOXES_SPECIALS_POURCENTAGE_TICKER',
      'MODULE_BOXES_SPECIALS_SORT_ORDER',
      'MODULE_BOXES_SPECIALS_DISPLAY_PAGES');
  }
}
