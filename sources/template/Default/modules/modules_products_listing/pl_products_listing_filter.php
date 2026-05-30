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

class pl_products_listing_filter
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

    $this->title = CLICSHOPPING::getDef('module_products_listing_filter_title');
    $this->description = CLICSHOPPING::getDef('module_products_listing_filter_description');

    if (\defined('MODULE_PRODUCTS_LISTING_FILTER_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_LISTING_FILTER_SORT_ORDER') ? (int)MODULE_PRODUCTS_LISTING_FILTER_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_LISTING_FILTER_STATUS') ? (MODULE_PRODUCTS_LISTING_FILTER_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Category = Registry::get('Category');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');
    $products_listing_filter =  '';
    
    if (!empty($CLICSHOPPING_Category->getPath())) {
      if ($CLICSHOPPING_Category->getID()) {
        if ($CLICSHOPPING_Category->getDepth() == 'nested' || $CLICSHOPPING_Category->getDepth() == 'products') {
          $bootstrap_column = (int)MODULE_PRODUCTS_LISTING_FILTER_COLUMNS;
          // optional Product List Filter
          if (MODULE_PRODUCTS_LISTING_FILTER_DISPLAY_FILTER > 0) {
            if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
              if ($CLICSHOPPING_Manufacturers->getID() && !empty($CLICSHOPPING_Manufacturers->getID())) {
                $Qfilter = $CLICSHOPPING_Db->prepare('select SQL_CALC_FOUND_ROWS distinct c.categories_id as id,
                                                                                             cd.categories_name as name
                                                       from :table_products p left join :table_products_groups g on p.products_id = g.products_id,
                                                            :table_products_to_categories p2c,
                                                            :table_categories c,
                                                            :table_categories_description cd
                                                       where p.products_status = 1
                                                       and g.customers_group_id = :customers_group_id
                                                       and g.products_group_view = 1
                                                       and p.products_id = p2c.products_id
                                                       and p2c.categories_id = c.categories_id
                                                       and c.status = 1
                                                       and cd.language_id = :language_id
                                                       and (p.manufacturers_id = :manufacturers_id1 or p = :filter)
                                                       and p.products_archive = 0
                                                       order by cd.categories_name
                                                     ');
                $Qfilter->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());
                $Qfilter->bindInt(':language_id', $CLICSHOPPING_Language->getId());
                $Qfilter->bindInt(':manufacturers_id', $CLICSHOPPING_Manufacturers->getID());
                $Qfilter->bindInt(':manufacturers_id1', $_GET['filter_id']);
                $Qfilter->execute();
              } else {
                $Qfilter = $CLICSHOPPING_Db->prepare('select SQL_CALC_FOUND_ROWS distinct m.manufacturers_id as id,
                                                                                            m.manufacturers_name as name
                                                        from :table_products p left join :table_products_groups g on p.products_id = g.products_id,
                                                             :table_products_to_categories p2c,
                                                             :table_categories c,
                                                             :table_manufacturers m
                                                        where p.products_status = 1
                                                        and g.customers_group_id = :customers_group_id
                                                        and g.products_group_view = 1
                                                        and p.manufacturers_id = m.manufacturers_id
                                                        and p.products_archive = 0
                                                        and p.products_id = p2c.products_id
                                                        and p2c.categories_id = :categories_id
                                                        and c.status = 1
                                                     and m.manufacturers_status = 0
                                                        order by m.manufacturers_name
                                                       ');

                $Qfilter->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
                $Qfilter->bindInt(':categories_id', $CLICSHOPPING_Category->getID());
                $Qfilter->execute();
              }
// Clients Grand Public
            } else {
              if ($CLICSHOPPING_Manufacturers->getID() && !empty($CLICSHOPPING_Manufacturers->getID())) {
// Affichage du menu deroulant des categories sur une selection d'une marque depuis la boxe manufacturer

                $Qfilter = $CLICSHOPPING_Db->prepare('select SQL_CALC_FOUND_ROWS distinct c.categories_id as id,
                                                                                             cd.categories_name as name
                                                         from :table_products p,
                                                             :table_products_to_categories p2c,
                                                             :table_categories c,
                                                             :table_categories_description cd
                                                         where p.products_status = 1
                                                         and p.products_view = 1
                                                         and p.products_id = p2c.products_id
                                                         and p2c.categories_id = c.categories_id
                                                         and c.status = 1
                                                         and p2c.categories_id = cd.categories_id
                                                         and p.products_archive = 0
                                                         and cd.language_id = :language_id
                                                          and (p.manufacturers_id = :manufacturers_id1 or p = :filter)
                                                         order by cd.categories_name
                                                       ');

                $Qfilter->bindInt(':language_id', $CLICSHOPPING_Language->getId());
                $Qfilter->bindInt(':manufacturers_id', $CLICSHOPPING_Manufacturers->getID());
                $Qfilter->execute();

              } else {
                // Affichage du menu deroulant des Marques sur la liste des produits d'une categorie
                $Qfilter = $CLICSHOPPING_Db->prepare('select SQL_CALC_FOUND_ROWS  m.manufacturers_id as id,
                                                                                   m.manufacturers_name as name
                                                        from :table_products p,
                                                            :table_products_to_categories p2c,
                                                            :table_categories c,
                                                            :table_manufacturers m
                                                        where p.products_status = 1
                                                        and p.products_view = 1
                                                        and p.manufacturers_id = m.manufacturers_id
                                                        and p.products_archive = 0
                                                        and m.manufacturers_status = 0
                                                        and p2c.categories_id = :categories_id
                                                        and p.products_id = p2c.products_id
                                                        and c.status = 1
                                                        group by m.manufacturers_id
                                                        order by m.manufacturers_name
                                                       ');

                $Qfilter->bindInt(':categories_id', $CLICSHOPPING_Category->getID());
                $Qfilter->execute();
              }
            }

            if ($Qfilter->rowCount() > 0) {
              $products_listing_filter .= '<!-- product_listing_manufacturers start -->' . "\n";
              $products_listing_filter .= HTML::form('filter', CLICSHOPPING::link(null, '', false), 'get', null, ['session_id' => true]);
              $products_listing_filter .= '<div class="col-md-' . $bootstrap_column . '">';

              if ($CLICSHOPPING_Manufacturers->getID() && !empty($CLICSHOPPING_Manufacturers->getID())) {
                $products_listing_filter .= HTML::hiddenField('manufacturersId', $CLICSHOPPING_Manufacturers->getID());

                $options = [
                  [
                    'id' => '',
                    'text' => CLICSHOPPING::getDef('text_all_categories')
                  ]
                ];
              } else {
                $products_listing_filter .= HTML::hiddenField('cPath', $CLICSHOPPING_Category->getPath());

                $options =  [
                  [
                    'id' => '',
                    'text' => CLICSHOPPING::getDef('text_all_filter')
                  ]
                ];
              }

              $products_listing_filter .= HTML::hiddenField('sort', HTML::sanitize($_GET['sort']));

              while ($Qfilter->fetch()) {
                $options[] = [
                  'id' => $Qfilter->valueInt('id'),
                  'text' => $Qfilter->value('name')
                ];
              }

              $products_listing_filter .= HTML::selectMenu('filter_id', $options, (isset($_GET['filter_id']) ? $_GET['filter_id'] : ''), 'onchange="this.form.submit()"');
              $products_listing_filter .= '</div>';
              $products_listing_filter .= '</form>';
            }

            $products_listing_filter .= '<!-- product_listing_manufacturers end -->' . "\n";
            $CLICSHOPPING_Template->addBlock($products_listing_filter, $this->group);
          }
        }
      }
    }
  }


  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_PRODUCTS_LISTING_FILTER_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_FILTER_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the number of column that you want to display ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_FILTER_COLUMNS',
        'configuration_value' => '6',
        'configuration_description' => 'Choose a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want displayt categories / model filters ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_FILTER_DISPLAY_FILTER',
        'configuration_value' => '0',
        'configuration_description' => '<br /Please indicate a sort order<br /><br /><i>- 0 for nothing<br />- 1 the sort order</i><br />',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_FILTER_SORT_ORDER',
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
    return array('MODULE_PRODUCTS_LISTING_FILTER_STATUS',
      'MODULE_PRODUCTS_LISTING_FILTER_DISPLAY_FILTER',
      'MODULE_PRODUCTS_LISTING_FILTER_COLUMNS',
      'MODULE_PRODUCTS_LISTING_FILTER_SORT_ORDER'
    );
  }
}
