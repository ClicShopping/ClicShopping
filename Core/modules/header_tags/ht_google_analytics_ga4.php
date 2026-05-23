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

class ht_google_analytics_ga4
{
  public string $code;
  public $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->title = CLICSHOPPING::getDef('module_header_tags_google_analytics_ga4_title');
    $this->description = CLICSHOPPING::getDef('module_header_tags_google_analytics_ga4_description');

    if (defined('MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_STATUS')) {
      $this->sort_order = MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db       = Registry::get('Db');
    $CLICSHOPPING_Template = Registry::get('Template');

    if (empty(MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_MEASUREMENT_ID)) {
      return;
    }

    if (MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_JS_PLACEMENT != 'Header') {
      $this->group = 'footer_scripts';
    }

    $measurementId = HTML::output(MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_MEASUREMENT_ID);

    $header  = '<!-- Google Analytics GA4 Start -->' . "\n";

    // Load gtag.js
    $header .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $measurementId . '"></script>' . "\n";

    $header .= '<script>' . "\n";
    $header .= 'window.dataLayer = window.dataLayer || [];' . "\n";
    $header .= 'function gtag(){dataLayer.push(arguments);}' . "\n";
    $header .= "gtag('js', new Date());" . "\n";
    $header .= "gtag('config', '" . $measurementId . "');" . "\n";

    // GA4 ecommerce purchase event — only on the checkout success page
    if (
      MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_EC_TRACKING == 'True'
      && isset($_GET['Checkout'])
      && isset($_GET['Success'])
      && $CLICSHOPPING_Customer->isLoggedOn()
    ) {
      $Qorder = $CLICSHOPPING_Db->prepare('
        SELECT orders_id
        FROM :table_orders
        WHERE customers_id = :customers_id
        ORDER BY date_purchased DESC
        LIMIT 1
      ');
      $Qorder->bindInt(':customers_id', (int)$CLICSHOPPING_Customer->getID());
      $Qorder->execute();

      if ($Qorder->rowCount() == 1) {
        $orderId = (int)$Qorder->valueInt('orders_id');

        // Order totals
        $totals = [];
        $QorderTotals = $CLICSHOPPING_Db->prepare('SELECT value, 
                                                          class
                                                    FROM :table_orders_total
                                                    WHERE orders_id = :orders_id
                                                  ');
        $QorderTotals->bindInt(':orders_id', $orderId);
        $QorderTotals->execute();

        while ($row = $QorderTotals->fetch()) {
          $totals[$row['class']] = (float)$row['value'];
        }

        $revenue  = $totals['ot_total']    ?? $totals['TO']  ?? 0.0;
        $shipping = $totals['ot_shipping'] ?? $totals['SH']  ?? 0.0;
        $tax      = $totals['ot_tax']      ?? $totals['TX']  ?? 0.0;

        // Order products
        $QorderProducts = $CLICSHOPPING_Db->prepare('SELECT op.products_id,
                                                           pd.products_name,
                                                           op.final_price,
                                                           op.products_quantity
                                                    FROM :table_orders_products op,
                                                         :table_products_description pd,
                                                         :table_languages l
                                                    WHERE op.orders_id = :orders_id
                                                      AND op.products_id = pd.products_id
                                                      AND l.code = :code
                                                      AND l.languages_id = pd.language_id
                                                  ');
        $QorderProducts->bindInt(':orders_id', $orderId);
        $QorderProducts->bindValue(':code', DEFAULT_LANGUAGE);
        $QorderProducts->execute();

        $items = [];

        while ($order_products = $QorderProducts->fetch()) {
          $Qcategory = $CLICSHOPPING_Db->prepare('SELECT cd.categories_name
                                                  FROM :table_categories_description cd,
                                                       :table_products_to_categories p2c,
                                                       :table_languages l
                                                  WHERE p2c.products_id = :products_id
                                                    AND p2c.categories_id = cd.categories_id
                                                    AND l.code = :code
                                                    AND l.languages_id = cd.language_id
                                                  LIMIT 1
                                                ');
          $Qcategory->bindInt(':products_id', (int)$order_products['products_id']);
          $Qcategory->bindValue(':code', DEFAULT_LANGUAGE);
          $Qcategory->execute();

          $category = $Qcategory->fetch();

          $items[] = [
            'item_id'       => (int)$order_products['products_id'],
            'item_name'     => HTML::outputProtected($order_products['products_name']),
            'item_category' => $category ? HTML::outputProtected($category['categories_name']) : '',
            'price'         => round($this->format_raw($order_products['final_price'], DEFAULT_CURRENCY), 2),
            'quantity'      => (int)$order_products['products_quantity'],
          ];
        }

        $payload = json_encode([
          'transaction_id' => (string)$orderId,
          'affiliation'    => HTML::outputProtected(STORE_NAME),
          'value'          => round($this->format_raw($revenue,  DEFAULT_CURRENCY), 2),
          'shipping'       => round($this->format_raw($shipping, DEFAULT_CURRENCY), 2),
          'tax'            => round($this->format_raw($tax,      DEFAULT_CURRENCY), 2),
          'currency'       => DEFAULT_CURRENCY,
          'items'          => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

        $header .= "gtag('event', 'purchase', " . $payload . ");" . "\n";
      }
    }

    $header .= '</script>' . "\n";
    $header .= '<!-- Google Analytics GA4 End -->' . "\n";

    $CLICSHOPPING_Template->addBlock($header, $this->group);
  }

  public function format_raw($number, $currency_code = '', $currency_value = '')
  {
    $CLICSHOPPING_Currencies = Registry::get('Currencies');

    if (empty($currency_code) || !$CLICSHOPPING_Currencies->isSet($currency_code)) {
      $currency_code = $_SESSION['currency'];
    }

    if (empty($currency_value) || !is_numeric($currency_value)) {
      $currency_value = $CLICSHOPPING_Currencies->currencies[$currency_code]['value'];
    }

    return number_format(
      round($number * $currency_value, $CLICSHOPPING_Currencies->currencies[$currency_code]['decimal_places']),
      $CLICSHOPPING_Currencies->currencies[$currency_code]['decimal_places'],
      '.', ''
    );
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return defined('MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db       = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if ($CLICSHOPPING_Language->getId() == '1') {

      $CLICSHOPPING_Db->save('configuration', [
        'configuration_title'       => 'Souhaitez-vous activer Google Analytics GA4 ?',
        'configuration_key'         => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_STATUS',
        'configuration_value'       => 'True',
        'configuration_description' => 'Souhaitez-vous inclure la gestion des statistiques avec Google Analytics GA4 ?',
        'configuration_group_id'    => '6',
        'sort_order'                => '1',
        'set_function'              => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added'                => 'now()',
      ]);

      $CLICSHOPPING_Db->save('configuration', [
        'configuration_title'       => 'Measurement ID Google Analytics GA4',
        'configuration_key'         => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_MEASUREMENT_ID',
        'configuration_value'       => '',
        'configuration_description' => 'Veuillez insérer le Measurement ID (G-XXXXXXXXXX) fourni par Google Analytics GA4.',
        'configuration_group_id'    => '6',
        'sort_order'                => '2',
        'date_added'                => 'now()',
      ]);

      $CLICSHOPPING_Db->save('configuration', [
        'configuration_title'       => 'Souhaitez-vous activer le suivi E-Commerce ?',
        'configuration_key'         => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_EC_TRACKING',
        'configuration_value'       => 'True',
        'configuration_description' => 'Activer le suivi e-commerce GA4 (événement purchase envoyé après chaque commande).',
        'configuration_group_id'    => '6',
        'sort_order'                => '3',
        'set_function'              => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added'                => 'now()',
      ]);

      $CLICSHOPPING_Db->save('configuration', [
        'configuration_title'       => 'Emplacement du Javascript',
        'configuration_key'         => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_JS_PLACEMENT',
        'configuration_value'       => 'Header',
        'configuration_description' => 'Où souhaitez-vous placer le javascript : en entête (Header) ou en pied de page (Footer) ?',
        'configuration_group_id'    => '6',
        'sort_order'                => '4',
        'set_function'              => 'clic_cfg_set_boolean_value(array(\'Header\', \'Footer\'))',
        'date_added'                => 'now()',
      ]);

      $CLICSHOPPING_Db->save('configuration', [
        'configuration_title'       => 'Sort Order',
        'configuration_key'         => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_SORT_ORDER',
        'configuration_value'       => '90',
        'configuration_description' => 'Ordre d\'affichage. Le plus petit s\'affiche en premier.',
        'configuration_group_id'    => '6',
        'sort_order'                => '5',
        'date_added'                => 'now()',
      ]);

    } else {

      $CLICSHOPPING_Db->save('configuration', [
        'configuration_title'       => 'Enable Google Analytics GA4',
        'configuration_key'         => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_STATUS',
        'configuration_value'       => 'True',
        'configuration_description' => 'Do you want to add Google Analytics GA4 to your shop?',
        'configuration_group_id'    => '6',
        'sort_order'                => '1',
        'set_function'              => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added'                => 'now()',
      ]);

      $CLICSHOPPING_Db->save('configuration', [
        'configuration_title'       => 'Google Analytics GA4 Measurement ID',
        'configuration_key'         => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_MEASUREMENT_ID',
        'configuration_value'       => '',
        'configuration_description' => 'Enter your GA4 Measurement ID (G-XXXXXXXXXX).',
        'configuration_group_id'    => '6',
        'sort_order'                => '2',
        'date_added'                => 'now()',
      ]);

      $CLICSHOPPING_Db->save('configuration', [
        'configuration_title'       => 'E-Commerce Tracking',
        'configuration_key'         => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_EC_TRACKING',
        'configuration_value'       => 'True',
        'configuration_description' => 'Enable GA4 e-commerce tracking? (Sends a purchase event on checkout success.)',
        'configuration_group_id'    => '6',
        'sort_order'                => '3',
        'set_function'              => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added'                => 'now()',
      ]);

      $CLICSHOPPING_Db->save('configuration', [
        'configuration_title'       => 'Javascript Placement',
        'configuration_key'         => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_JS_PLACEMENT',
        'configuration_value'       => 'Header',
        'configuration_description' => 'Should the GA4 javascript be loaded in the header or footer?',
        'configuration_group_id'    => '6',
        'sort_order'                => '4',
        'set_function'              => 'clic_cfg_set_boolean_value(array(\'Header\', \'Footer\'))',
        'date_added'                => 'now()',
      ]);

      $CLICSHOPPING_Db->save('configuration', [
        'configuration_title'       => 'Sort Order',
        'configuration_key'         => 'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_SORT_ORDER',
        'configuration_value'       => '90',
        'configuration_description' => 'Sort order of display. Lowest is displayed first.',
        'configuration_group_id'    => '6',
        'sort_order'                => '5',
        'date_added'                => 'now()',
      ]);
    }
  }

  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  public function keys()
  {
    return [
      'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_STATUS',
      'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_MEASUREMENT_ID',
      'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_EC_TRACKING',
      'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_JS_PLACEMENT',
      'MODULE_HEADER_TAGS_GOOGLE_ANALYTICS_GA4_SORT_ORDER',
    ];
  }
}
