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

class bm_order_history
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
    $this->title = CLICSHOPPING::getDef('module_boxes_order_history_title');
    $this->description = CLICSHOPPING::getDef('module_boxes_order_history_description');

    if (\defined('MODULE_BOXES_ORDER_HISTORY_STATUS')) {
      $this->sort_order = \defined('MODULE_BOXES_ORDER_HISTORY_SORT_ORDER') ? (int)MODULE_BOXES_ORDER_HISTORY_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_BOXES_ORDER_HISTORY_STATUS') && MODULE_BOXES_ORDER_HISTORY_STATUS == 'True');
      $this->pages = \defined('MODULE_BOXES_ORDER_HISTORY_DISPLAY_PAGES') ? MODULE_BOXES_ORDER_HISTORY_DISPLAY_PAGES : '';
      $this->group = ((\defined('MODULE_BOXES_ORDER_HISTORY_CONTENT_PLACEMENT') && MODULE_BOXES_ORDER_HISTORY_CONTENT_PLACEMENT == 'Left Column') ? 'boxes_column_left' : 'boxes_column_right');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Service = Registry::get('Service');
    $CLICSHOPPING_Banner = Registry::get('Banner');
    $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');

    if ($CLICSHOPPING_Customer->isLoggedOn()) {
// retreive the last x products purchased

      $Qorders = $CLICSHOPPING_Db->prepare('select distinct op.products_id,
                                                              o.orders_id,
                                                              o.date_purchased
                                               from :table_orders o,
                                                    :table_orders_products op,
                                                    :table_products p,
                                                    :table_products_to_categories p2c,
                                                    :table_categories c
                                                where o.customers_id = :customers_id
                                                and o.orders_id = op.orders_id
                                                and op.products_id = p.products_id
                                                and p.products_status = 1
                                                and p.products_id = p2c.products_id
                                                and p2c.categories_id = c.categories_id
                                                and c.status = 1
                                                group by products_id,
                                                         o.date_purchased
                                                order by o.date_purchased desc
                                                limit :limit
                                           ');
      $Qorders->bindInt(':limit', \defined('MODULE_BOXES_ORDER_HISTORY_MAX_DISPLAY_PRODUCTS') ? (int)MODULE_BOXES_ORDER_HISTORY_MAX_DISPLAY_PRODUCTS : 5);
      $Qorders->bindInt(':customers_id', (int)$CLICSHOPPING_Customer->getID());
      $Qorders->execute();

      if ($Qorders->fetch() !== false) {
        $product_ids = [];

        do {
          $product_ids[] = $Qorders->valueInt('products_id');
        } while ($Qorders->fetch());

        $customer_orders_string = null;

        $Qproducts = $CLICSHOPPING_Db->prepare('select products_id,
                                                         products_name
                                                  from :table_products_description
                                                  where products_id in (' . implode(', ', $product_ids) . ')
                                                  and language_id = :language_id
                                                  order by products_name
                                                 ');
        $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        $Qproducts->execute();

        while ($Qproducts->fetch()) {
          //$products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($Qproducts->valueInt('products_id'));
          $products_name_url = CLICSHOPPING::link(null, 'Account&HistoryInfo');
          $customer_orders_string .= '<li class="boxeContentsHistory">' . HTML::link($products_name_url, $Qproducts->value('products_name')) . '</li>';
        }

        $order_history_banner = '';

        if ($CLICSHOPPING_Service->isStarted('Banner')) {
          if ($banner = $CLICSHOPPING_Banner->bannerExists('dynamic', \defined('MODULE_BOXES_ORDER_HISTORY_BANNER_GROUP') ? MODULE_BOXES_ORDER_HISTORY_BANNER_GROUP : '')) {
            $order_history_banner = $CLICSHOPPING_Banner->displayBanner('static', $banner) . '<br /><br />';
          }
        }

        $data = '<!-- boxe OrderHistory  start-->' . "\n";

        ob_start();
        require($CLICSHOPPING_Template->getTemplateModules('/modules_boxes/content/order_history'));

        $data .= ob_get_clean();

        $data .= '<!-- Boxe Order history end -->' . "\n";

        $CLICSHOPPING_Template->addBlock($data, $this->group);
      }
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_BOXES_ORDER_HISTORY_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_BOXES_ORDER_HISTORY_STATUS',
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
        'configuration_key' => 'MODULE_BOXES_ORDER_HISTORY_CONTENT_PLACEMENT',
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
        'configuration_key' => 'MODULE_BOXES_ORDER_HISTORY_BANNER_GROUP',
        'configuration_value' => SITE_THEMA . '_boxe_history',
        'configuration_description' => 'Indicate the banner group<br /><br /><strong>Note :</strong><br /><i>The group must be created or selected whtn you create a banner in Marketing / banner</i>',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Veuillez indiquer le nombre de commandes effectuée par le client à afficher',
        'configuration_key' => 'MODULE_BOXES_ORDER_HISTORY_MAX_DISPLAY_PRODUCTS',
        'configuration_value' => '5',
        'configuration_description' => 'Veuillez indiquer le nombre de commandes effectuée par le client à afficher',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_BOXES_ORDER_HISTORY_SORT_ORDER',
        'configuration_value' => '120',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the page where the module is displayed',
        'configuration_key' => 'MODULE_BOXES_ORDER_HISTORY_DISPLAY_PAGES',
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
    return array('MODULE_BOXES_ORDER_HISTORY_STATUS',
      'MODULE_BOXES_ORDER_HISTORY_CONTENT_PLACEMENT',
      'MODULE_BOXES_ORDER_HISTORY_BANNER_GROUP',
      'MODULE_BOXES_ORDER_HISTORY_MAX_DISPLAY_PRODUCTS',
      'MODULE_BOXES_ORDER_HISTORY_SORT_ORDER',
      'MODULE_BOXES_ORDER_HISTORY_DISPLAY_PAGES');
  }
}
