<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function count;
use function is_null;

/**
 * Class ProductsListing
 *
 * This class is responsible for handling the listing of products, including retrieving column settings
 * and generating the corresponding SQL queries required for fetching the product data.
 */
class ProductsListing
{
  private mixed $db;

  /**
   * Constructor method for the class.
   * Initializes the database connection by fetching the 'Db' instance from the registry.
   *
   * @return void
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Retrieves a list of column keys based on defined configurations.
   *
   * @return array Returns an array of column keys where the corresponding configuration is not empty.
   */
  public function getColumnList(): array
  {
    $define_list = ['PRODUCT_LIST_MODEL' => CLICSHOPPING::getDef('product_list_model'),
      'PRODUCT_LIST_NAME' => CLICSHOPPING::getDef('product_list_name'),
      'PRODUCT_LIST_MANUFACTURER' => CLICSHOPPING::getDef('product_list_manufacturer'),
      'PRODUCT_LIST_PRICE' => CLICSHOPPING::getDef('product_list_price'),
      'PRODUCT_LIST_QUANTITY' => CLICSHOPPING::getDef('product_list_quantity'),
      'PRODUCT_LIST_WEIGHT' => CLICSHOPPING::getDef('product_list_weight'),
      'PRODUCT_LIST_IMAGE' => CLICSHOPPING::getDef('product_list_image'),
      'PRODUCT_LIST_DATE' => CLICSHOPPING::getDef('product_list_date'),
    ];

    asort($define_list);

    $column_list = [];

    foreach ($define_list as $key => $value) {
      if (!empty($value)) $column_list[] = $key;
    }

    return $column_list;
  }

  /**
   * Builds and returns a data query string based on various parameters including
   * customer group, manufacturer selection, category filtering, and selected
   * product attributes. The method dynamically generates SQL queries to fetch
   * product information matching specific criteria.
   *
   * The generated query integrates product details such as name, model, weight,
   * images, manufacturer information, stock quantity, and additional metadata.
   * It considers customer group restrictions, manufacturer IDs, category IDs,
   * and language-specific product descriptions.
   *
   * Handles both B2B and public-facing client scenarios, adapting query logic
   * to support filtered or general product lists. Accounts for archived products
   * and ensures compatibility with multilingual data and hierarchical product
   * categorization.
   */
  public function getData()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Category = Registry::get('Category');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');

    $column_list = $this->getColumnList();

    $search_query = 'select SQL_CALC_FOUND_ROWS ';

    for ($i = 0, $n = count($column_list); $i < $n; $i++) {
      switch ($column_list[$i]) {
        case 'PRODUCT_LIST_MODEL':
          $search_query .= ' p.products_model,';
          break;
        case 'PRODUCT_LIST_NAME':
          $search_query .= ' pd.products_name, pd.products_description as description,';
          break;
        case 'PRODUCT_LIST_MANUFACTURER':
          $search_query .= ' m.manufacturers_name,';
          break;
        case 'PRODUCT_LIST_QUANTITY':
          $search_query .= ' p.products_quantity,';
          break;
        case 'PRODUCT_LIST_IMAGE':
          $search_query .= ' p.products_image_zoom, p.products_image,';
          break;
        case 'PRODUCT_LIST_WEIGHT':
          $search_query .= ' p.products_weight,';
          break;
        case 'PRODUCT_LIST_DATE':
          $search_query .= 'p.products_date_added,';
          break;
      }
    }

    if (isset($filter_id)) {
      $filter_id = HTML::sanitize($filter_id);
    } else {
      $filter_id = null;
    }

    if (!is_null($CLICSHOPPING_Manufacturers->getID())) {
      $manufacturers_id = HTML::sanitize($CLICSHOPPING_Manufacturers->getID());
    } else {
      $manufacturers_id = false;
    }

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      if (isset($manufacturers_id) && is_numeric($manufacturers_id) && !empty($manufacturers_id)) {
        if (isset($filter_id) && !is_null($filter_id)) {
// Affichage des produits en mode B2B sur la selection d'une marque depuis la boxe manufacturer avec filtrage de la categorie
          $search_query .= ' p.products_id,
                             p.products_sort_order
                             from :table_products p left join :table_specials s on p.products_id = s.products_id
                                                    left join :table_products_groups g on p.products_id = g.products_id,
                                  :table_products_description pd,
                                  :table_manufacturers m,
                                  :table_products_to_categories p2c,
                                  :table_categories c
                             where p.products_status = 1
                             and g.customers_group_id = :customers_group_id
                             and g.products_group_view = 1
                             and p.manufacturers_id = m.manufacturers_id
                             and m.manufacturers_id = :manufacturers_id
                             and p.products_id = p2c.products_id
                             and pd.products_id = p2c.products_id
                             and p.products_archive = 0
                             and pd.language_id = :language_id
                             and p2c.categories_id = :categories_id
                             and c.status = 1
                             and m.manufacturers_status = 0
                            ';
        } else {
// Affichage des produits en mode B2B sur la selection d'une marque depuis la boxe manufacturer
          $search_query .= ' p.products_id,
                             p.products_sort_order
                             from :table_products p left join :table_specials s on p.products_id = s.products_id
                                                    left join :table_products_groups g on p.products_id = g.products_id,
                                  :table_products_description pd,
                                  :table_manufacturers m,
                                  :table_products_to_categories p2c,
                                  :table_categories c
                             where p.products_status = 1
                             and g.customers_group_id = :customers_group_id
                             and g.products_group_view = 1
                             and pd.products_id = p.products_id
                             and p.products_archive = 0
                             and pd.language_id = :language_id
                             and p.manufacturers_id = m.manufacturers_id
                             and m.manufacturers_id = :manufacturers_id
                             and m.manufacturers_status = 0
                             and p.products_id = p2c.products_id
                             and p2c.categories_id = c.categories_id
                             and c.status = 1
                           ';
        }
      } else {
        if (isset($filter_id) && !is_null($filter_id)) {
// Affichage general en mode B2B de la liste des produits d'une categorie avec un filtrage des Marques

          $search_query .= ' p.products_id,
                             p.products_sort_order
                            from :table_products p left join :table_specials s on p.products_id = s.products_id
                                                   left join :table_products_groups g on p.products_id = g.products_id,
                                 :table_products_description pd,
                                 :table_manufacturers m,
                                 :table_products_to_categories p2c,
                                 :table_categories c
                            where p.products_status = 1
                            and g.customers_group_id = :customers_group_id
                            and g.products_group_view = 1
                            and p.manufacturers_id = m.manufacturers_id
                            and m.manufacturers_id = :manufacturers_id
                            and p.products_id = p2c.products_id
                            and pd.products_id = p2c.products_id
                            and p.products_archive = 0
                            and pd.language_id = :language_id
                            and p2c.categories_id = :categories_id
                            and c.status = 1
                            and m.manufacturers_status = 0
                           ';
        } else {
// Affichage general en mode B2B de la liste des produits d'une categorie avec toutes les Marques

          $search_query .= ' p.products_id,
                             p.products_sort_order
                            from :table_products_description pd,
                                 :table_products p left join :table_manufacturers  m on p.manufacturers_id = m.manufacturers_id
                                                   left join :table_specials s on p.products_id = s.products_id
                                                   left join :table_products_groups g on p.products_id = g.products_id,
                                 :table_products_to_categories p2c,
                                 :table_categories c
                            where p.products_status = 1
                            and g.customers_group_id = :customers_group_id
                            and g.products_group_view = 1
                            and p.products_id = p2c.products_id
                            and pd.products_id = p2c.products_id
                            and p.products_archive = 0
                            and pd.language_id = :language_id
                            and p2c.categories_id = :categories_id
                            and c.status = 1
                           ';
        }
      }

// ***************************
// Clients Grand Public
// ***************************
    } else {
      if (isset($manufacturers_id) && is_numeric($manufacturers_id) && !empty($manufacturers_id)) {
        if (isset($filter_id) && !is_null($filter_id)) {

// Affichage des produits sur la selection d'une marque depuis la boxe manufacturer avec filtrage de la categorie
          $search_query .= ' p.products_id,
                             p.products_sort_order
                            from :table_products p left join :table_specials s on p.products_id = s.products_id,
                                 :table_products_description pd,
                                 :table_manufacturers m,
                                 :table_products_to_categories p2c,
                                 :table_categories c
                            where p.products_status = 1
                            and p.products_view = 1
                            and p.manufacturers_id = m.manufacturers_id
                            and m.manufacturers_id = :manufacturers_id
                            and p.products_id = p2c.products_id
                            and pd.products_id = p2c.products_id
                            and p.products_archive = 0
                            and pd.language_id = :language_id
                            and p2c.categories_id = :categories_id
                            and m.manufacturers_status = 0
                            and c.status = 1
                           ';
        } else {

// Affichage des produits sur la selection d'une marque depuis la boxe manufacturer
          $search_query .= ' p.products_id,
                             p.products_sort_order
                            from :table_products p left join :table_specials s on p.products_id = s.products_id,
                                 :table_products_description pd,
                                 :table_manufacturers m,
                                 :table_products_to_categories p2c,
                                 :table_categories c
                            where p.products_status = 1
                            and p.products_view = 1
                            and pd.products_id = p.products_id
                            and p.products_archive = 0
                            and pd.language_id = :language_id
                            and p.manufacturers_id = m.manufacturers_id
                            and m.manufacturers_id = :manufacturers_id
                            and m.manufacturers_status = 0
                            and p.products_id = p2c.products_id
                            and p2c.categories_id = c.categories_id
                            and c.status = 1
                           ';
        }
      } else {
        if (isset($filter_id) && !is_null($filter_id)) {
// Affichage general de la liste des produits d'une categorie avec un filtrage des Marques

          $search_query .= ' p.products_id,
                             p.products_sort_order
                            from :table_products p left join :table_specials s on p.products_id = s.products_id,
                                 :table_products_description pd,
                                 :table_manufacturers m,
                                 :table_products_to_categories p2c,
                                 :table_categories c
                            where p.products_status = 1
                            and p.products_view = 1
                            and p.manufacturers_id = m.manufacturers_id
                            and m.manufacturers_id = :manufacturers_id
                            and p.products_id = p2c.products_id
                            and pd.products_id = p2c.products_id
                            and p.products_archive = 0
                            and pd.language_id = :language_id
                            and p2c.categories_id = :categories_id
                            and m.manufacturers_status = 0
                            and c.status = 1
                           ';
        } else {
// Affichage general de la liste des produits d'une categorie avec tout les Marques

          $search_query .= ' p.products_id,
                               p.products_sort_order
                            from :table_products_description pd,
                                 :table_products p left join :table_manufacturers  m on p.manufacturers_id = m.manufacturers_id
                                                   left join :table_specials s on p.products_id = s.products_id,
                                 :table_products_to_categories p2c,
                                 :table_categories c
                            where p.products_status = 1
                            and p.products_view = 1
                            and p.products_id = p2c.products_id
                            and pd.products_id = p2c.products_id
                            and p.products_archive = 0
                            and pd.language_id = :language_id
                            and p2c.categories_id = :categories_id
                            and c.status = 1
                           ';
        }
      }
    }
// ####### END B2B #######
    $search_query .= ' group by p.products_id ';

    if ((!isset($_GET['sort'])) || (!preg_match('/^[1-8][ad]$/', $_GET['sort'])) || (substr($_GET['sort'], 0, 1) > count($column_list))) {
      for ($i = 0, $n = count($column_list); $i < $n; $i++) {
        if ($column_list[$i] == 'PRODUCT_LIST_NAME') {
          $_GET['sort'] = $i + 1 . 'a';

          $search_query .= ' order by';
          $search_query .= ' p.products_sort_order, ';
          $search_query .= ' pd.products_name';

          break;
        }
      }
    } else {
      $sort_col = substr($_GET['sort'], 0, 1);
      $sort_order = substr($_GET['sort'], 1);

      switch ($column_list[$sort_col - 1]) {

        case 'PRODUCT_LIST_MODEL':
          $search_query .= ' order by p.products_model ' . ($sort_order == 'd' ? 'desc' : '') . ', pd.products_name';
          break;
        case 'PRODUCT_LIST_NAME':
          $search_query .= ' order by pd.products_name ' . ($sort_order == 'd' ? 'desc' : '');
          break;
        case 'PRODUCT_LIST_MANUFACTURER':
          $search_query .= ' order by m.manufacturers_name ' . ($sort_order == 'd' ? 'desc' : '') . ', pd.products_name';
          break;
        case 'PRODUCT_LIST_QUANTITY':
          $search_query .= ' order by p.products_quantity ' . ($sort_order == 'd' ? 'desc' : '') . ', pd.products_name';
          break;
        case 'PRODUCT_LIST_IMAGE':
          $search_query .= ' order by pd.products_name';
          break;
        case 'PRODUCT_LIST_WEIGHT':
          $search_query .= ' order by p.products_weight ' . ($sort_order == 'd' ? 'desc' : '') . ', pd.products_name';
          break;
        case 'PRODUCT_LIST_PRICE':
          $search_query .= ' order by p.products_price ' . ($sort_order == 'd' ? 'desc' : '') . ', pd.products_name';
          break;
        case 'PRODUCT_LIST_DATE':
          $search_query .= ' order by p.products_date_added ' . ($sort_order == 'd' ? 'desc' : '') . ', pd.products_name';
          break;
      }
    }

    $search_query .= ' limit :page_set_offset,
                               :page_set_max_results
                       ';

    $Qlisting = $this->db->prepare($search_query);

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      if (isset($manufacturers_id) && is_numeric($manufacturers_id) && !empty($manufacturers_id)) {
        if (isset($filter_id) && !is_null($filter_id)) {
// Affichage des produits en mode B2B sur la selection d'une marque depuis la boxe manufacturer avec filtrage de la categorie
          $Qlisting->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
          $Qlisting->bindInt(':manufacturers_id', $manufacturers_id);
          $Qlisting->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
          $Qlisting->bindInt(':categories_id', $filter_id);
        } else {
// Affichage des produits en mode B2B sur la selection d'une marque depuis la boxe manufacturer
          $Qlisting->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
          $Qlisting->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
          $Qlisting->bindInt(':manufacturers_id', $manufacturers_id);
        }
      } else {
        if (isset($filter_id) && !is_null($filter_id)) {
// Affichage general en mode B2B de la liste des produits d'une categorie avec un filtrage des Marques
          $Qlisting->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
          $Qlisting->bindInt(':manufacturers_id', $filter_id);
          $Qlisting->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
          $Qlisting->bindInt(':categories_id', $CLICSHOPPING_Category->getPath());
        } elseif (!empty($CLICSHOPPING_Category->getPath())) {
          $Qlisting->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
          $Qlisting->bindInt(':language_id', $CLICSHOPPING_Language->getId());
          $Qlisting->bindInt(':categories_id', $CLICSHOPPING_Category->getPath());
        } elseif (isset($manufacturers_id) && is_numeric($manufacturers_id) && !empty($manufacturers_id)) {
          $Qlisting->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
          $Qlisting->bindInt(':manufacturers_id', $manufacturers_id);
          $Qlisting->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        } else {
          $Qlisting->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
          $Qlisting->bindInt(':language_id', $CLICSHOPPING_Language->getId());
          $Qlisting->bindInt(':categories_id', $CLICSHOPPING_Category->getPath());
        }
      }

// ***************************
// Clients Grand Public
// ***************************
    } else {
      if (isset($manufacturers_id) && !empty($filter_id)) {

        if (isset($filter_id) && !is_null($filter_id)) {
// Affichage des produits sur la selection d'une marque depuis la boxe manufacturer avec filtrage de la categorie
          $Qlisting->bindInt(':manufacturers_id', $manufacturers_id);
          $Qlisting->bindInt(':language_id', $CLICSHOPPING_Language->getId());
          $Qlisting->bindInt(':categories_id', $filter_id);
        } else {
// Affichage des produits sur la selection d'une marque depuis la boxe manufacturer
          $Qlisting->bindInt(':manufacturers_id', $manufacturers_id);
          $Qlisting->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        }
      } else {
        if (isset($filter_id) && !is_null($filter_id)) {
// Affichage general de la liste des produits d'une categorie avec un filtrage des Marques
          $Qlisting->bindInt(':manufacturers_id', $filter_id);
          $Qlisting->bindInt(':language_id', $CLICSHOPPING_Language->getId());
          $Qlisting->bindInt(':categories_id', $CLICSHOPPING_Category->getPath());
        } elseif (!empty($CLICSHOPPING_Category->getPath())) {
// Affichage general de la liste des produits d'une categorie avec tout les Marques
          $Qlisting->bindInt(':language_id', $CLICSHOPPING_Language->getId());
          $Qlisting->bindInt(':categories_id', $CLICSHOPPING_Category->getPath());
        } elseif (isset($manufacturers_id) && is_numeric($manufacturers_id) && !empty($manufacturers_id)) {
          $Qlisting->bindInt(':manufacturers_id', $manufacturers_id);
          $Qlisting->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        } else {
          $Qlisting->bindInt(':language_id', $CLICSHOPPING_Language->getId());
          $Qlisting->bindInt(':categories_id', $CLICSHOPPING_Category->getPath());
        }
      }
    }

    $Qlisting->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS);
    $Qlisting->execute();

    return $Qlisting;
  }

  /**
   * Retrieves the total number of rows in the current page set.
   *
   * @return int The total number of rows.
   */
  public function getTotalRow(): int
  {
    $listingTotalRow = $this->getData()->getPageSetTotalRows();

    return $listingTotalRow;
  }

  /**
   * Retrieves the label for the current page set.
   *
   * @return string The label representing the current set of pages.
   */
  public function getPageSetLabel(): string
  {
    $result = $this->getData()->getPageSetLabel(CLICSHOPPING::getDef('text_display_number_of_items'));

    return $result;
  }


  /**
   * Retrieves the page set links by processing the current GET parameters
   * and generating the corresponding links for paging.
   *
   * @return mixed Returns the page set links based on the current parameters.
   */
  public function getPageSetLinks(): mixed
  {
    $result = $this->getData()->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y')), 'Shop');

    return $result;
  }
}