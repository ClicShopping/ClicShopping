<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use function count;
use function defined;
use function in_array;
use function is_array;

/**
 * Handles various operations related to product search, including filtering by year,
 * date, price, manufacturer, keywords, description, and categories. It also provides
 * methods to retrieve and validate search parameters.
 */
class Search
{
  protected $_period_min_year;
  protected $_period_max_year;
  protected string $_keywords = '';
  protected $_description;
  protected $_date_from;
  protected $_date_to;
  protected $_price_to;
  protected $_price_from;
  protected $_manufacturer;
  protected $_category;
  protected $_result;
  protected $column_list;
  private mixed $db;
  protected bool $checkManufacturer = false;
  protected bool $_recursive = false;
  protected $listing;

  /**
   * Constructor to initialize the Search class and set up the database connection.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->initializeDateRange();
  }

  /**
   * Initializes the minimum and maximum years from the product addition dates in the database.
   */
  private function initializeDateRange(): void
  {
    $Qproducts = $this->db->query('SELECT MIN(YEAR(products_date_added)) as min_year,
                                          MAX(YEAR(products_date_added)) as max_year
                                   FROM :table_products LIMIT 1');
    $Qproducts->execute();

    $this->_period_min_year = $Qproducts->valueInt('min_year');
    $this->_period_max_year = $Qproducts->valueInt('max_year');
  }

  /**
   * Retrieves the minimum year from the product addition dates.
   *
   * @return string The minimum year as a string.
   */
  public function getMinYear(): string
  {
    return (string)$this->_period_min_year;
  }

  /**
   * Retrieves the maximum year from the product addition dates.
   *
   * @return string The maximum year as a string.
   */
  public function getMaxYear(): string
  {
    return (string)$this->_period_max_year;
  }

  /**
   * Retrieves the starting date ('dfrom') from either POST or GET request.
   * - If 'dfrom' is set and non-empty in the POST request, it sanitizes and sets it.
   * - If not found in POST, checks GET for 'dfrom', sanitizes, and sets it.
   * - If 'dfrom' is not set in both, defaults to an empty string.
   *
   * @return string The sanitized starting date or an empty string if not provided.
   */
  public function getDateFrom(): string
  {
    if (isset($_POST['dfrom']) && !empty($_POST['dfrom'])) {
      $this->_date_from = HTML::sanitize($_POST['dfrom']);
    } elseif (isset($_GET['dfrom']) && !empty($_GET['dfrom'])) {
      $this->_date_from = HTML::sanitize($_GET['dfrom']);
    } else {
      $this->_date_from = '';
    }

    return $this->_date_from;
  }

  /**
   * Checks if a start date is provided for the search.
   *
   * @return bool True if a start date is set and valid, false otherwise.
   */
  public function hasDateFrom(): bool
  {
    $dfromDateTime = new DateTime($this->getDateFrom(), false);
    return $dfromDateTime->isValid();
  }

  /**
   * Retrieves and sanitizes the end date for the search from POST or GET parameters.
   *
   * @return string The sanitized end date.
   */
  public function getDateTo(): string
  {
    if (isset($_POST['dto']) && !empty($_POST['dto'])) {
      $this->_date_to = HTML::sanitize($_POST['dto']);
    } elseif (isset($_GET['dto']) && !empty($_GET['dto'])) {
      $this->_date_to = HTML::sanitize($_GET['dto']);
    } else {
      $this->_date_to = '';
    }

    return $this->_date_to;
  }

  /**
   * Checks if an end date is provided for the search.
   *
   * @return bool True if an end date is set and valid, false otherwise.
   */
  public function hasDateTo(): bool
  {
    $dtoDateTime = new DateTime($this->getDateTo(), false);
    return $dtoDateTime->isValid();
  }

  /**
   * Sets the start date for the search.
   *
   * @param string $timestamp The start date to set.
   * @return string The set start date.
   */
  public function setDateFrom(string $timestamp): string
  {
    $this->_date_from = $timestamp;
    return $this->_date_from;
  }

  /**
   * Sets the end date for the search.
   *
   * @param string $timestamp The end date to set.
   */
  public function setDateTo($timestamp): void
  {
    $this->_date_to = $timestamp;
  }

  /**
   * Retrieves and sanitizes the minimum price for the search from POST or GET parameters.
   *
   * @return string The sanitized minimum price.
   */
  public function getPriceFrom(): string
  {
    if (isset($_POST['pfrom']) && !empty($_POST['pfrom']) && is_numeric($_POST['pfrom'])) {
      $this->_price_from = HTML::sanitize((float)$_POST['pfrom']);
    } elseif (isset($_GET['pfrom']) && !empty($_GET['pfrom']) && is_numeric($_GET['pfrom'])) {
      $this->_price_from = HTML::sanitize((float)$_GET['pfrom']);
    } else {
      $this->_price_from = '';
    }

    return (string)$this->_price_from;
  }

  /***
   * Checks if a minimum price is provided for the search.
   *
   * @return bool True if a minimum price is set, false otherwise.
   */
  public function hasPriceFrom(): bool
  {
    return !empty($this->getPriceFrom());
  }

  /**
   * Retrieves and sanitizes the maximum price for the search from POST or GET parameters.
   *
   * @return string The sanitized maximum price.
   */
  public function getPriceTo(): string
  {
    if (isset($_POST['pto']) && !empty($_POST['pto']) && is_numeric($_POST['pto'])) {
      $this->_price_to = HTML::sanitize((float)$_POST['pto']);
    } elseif (isset($_GET['pto']) && !empty($_GET['pto']) && is_numeric($_GET['pto'])) {
      $this->_price_to = HTML::sanitize((float)$_GET['pto']);
    } else {
      $this->_price_to = '';
    }

    return (string)$this->_price_to;
  }

  /**
   * Checks if a maximum price is provided for the search.
   *
   * @return bool True if a maximum price is set, false otherwise.
   */
  public function hasPriceTo(): bool
  {
    return !empty($this->getPriceTo());
  }

  /**
   * Retrieves and sanitizes the keywords for the search from POST or GET parameters.
   *
   * @return string The sanitized keywords.
   */
  public function getKeywords(): string
  {
    if (isset($_POST['keywords'])) {
      $this->_keywords = HTML::sanitize($_POST['keywords']);
    } elseif (isset($_GET['keywords'])) {
      $this->_keywords = HTML::sanitize($_GET['keywords']);
    } else {
      $this->_keywords = '';
    }

    return $this->_keywords;
  }


  /**
   * Checks if keywords are provided for the search.
   *
   * @return bool True if keywords are set, false otherwise.
   */
  public function hasKeywords(): bool
  {
    return !empty($this->getKeywords());
  }

  /**
   * Sets and sanitizes the keywords for the search.
   *
   * @param string $keywords The keywords to set.
   */
  public function setKeywords(string $keywords): void
  {
    if (isset($keywords)) {
      $this->_keywords = HTML::sanitize($keywords);
    }

    $terms = explode(' ', trim($keywords));
    $terms_array = [];
    $counter = 0;

    foreach ($terms as $word) {
      $counter++;
      if ($counter > 5) {
        break;
      } elseif (!empty($word)) {
        if (!in_array($word, $terms_array, true)) {
          $terms_array[] = $word;
        }
      }
    }

    $this->_keywords = implode(' ', $terms_array);
  }

  /**
   * Retrieves and sanitizes the description search parameter from POST or GET.
   *
   * @return bool True if searching in descriptions, false otherwise.
   */
  private function getDescription(): bool
  {
    if ((isset($_POST['search_in_description']) && $_POST['search_in_description'] == 1) ||
      (isset($_GET['search_in_description']) && $_GET['search_in_description'] == 1)) {
      $this->_description = true;
    } else {
      $this->_description = false;
    }

    return $this->_description;
  }

  /**
   * Checks if the search should include product descriptions.
   *
   * @return bool True if descriptions should be included, false otherwise.
   */
  private function hasDescription(): bool
  {
    return $this->getDescription();
  }

  /**
   * Retrieves and sanitizes the category ID from POST or GET parameters.
   *
   * @return bool True if a valid category ID is found, false otherwise.
   */
  private function getCategory(): bool
  {
    if (isset($_POST['categories_id']) && !empty($_POST['categories_id']) && is_numeric($_POST['categories_id'])) {
      $this->_category = true;
    } elseif (isset($_GET['categories_id']) && !empty($_GET['categories_id']) && is_numeric($_GET['categories_id'])) {
      $this->_category = true;
    } else {
      $this->_category = false;
    }

    return $this->_category;
  }

  /**
   * Checks if a category filter is applied.
   *
   * @return bool True if a category is set, false otherwise.
   */
  private function hasCategory(): bool
  {
    return $this->getCategory();
  }

  /**
   * Determines if the search should include subcategories based on POST or GET parameters.
   *
   * @return bool True if subcategories should be included, false otherwise.
   */
  private function isRecursive(): bool
  {
    if ((isset($_POST['inc_subcat']) && $_POST['inc_subcat'] == '1') ||
      (isset($_GET['inc_subcat']) && $_GET['inc_subcat'] == '1')) {
      $this->_recursive = true;
    } else {
      $this->_recursive = false;
    }
    return $this->_recursive;
  }

  private function getCategoryID(): int|null
  {
    $category_id = null;

    if (isset($_POST['categories_id']) && !empty($_POST['categories_id'])) {
      $category_id = (int)HTML::sanitize($_POST['categories_id']);
    } elseif (isset($_GET['categories_id']) && !empty($_GET['categories_id'])) {
      $category_id = (int)HTML::sanitize($_GET['categories_id']);
    }
    return $category_id;
  }


  /**
   * Retrieves and sanitizes the manufacturer ID from POST or GET parameters.
   *
   * @return bool True if a valid manufacturer ID is found, false otherwise.
   */
  private function getManufacturer(): bool
  {
    $this->checkManufacturer = false;

    if (isset($_POST['manufacturersId']) && !empty($_POST['manufacturersId']) && is_numeric($_POST['manufacturersId'])) {
      $this->_manufacturer = HTML::sanitize($_POST['manufacturersId']);
      $this->checkManufacturer = true;
    } elseif (isset($_GET['manufacturersId']) && !empty($_GET['manufacturersId']) && is_numeric($_GET['manufacturersId'])) {
      $this->_manufacturer = HTML::sanitize($_GET['manufacturersId']);
      $this->checkManufacturer = true;
    }

    return $this->checkManufacturer;
  }

  /**
   * Checks if a manufacturer filter is applied.
   *
   * @return bool|null True if a manufacturer is set, false if not, null if not checked yet.
   */
  private function hasManufacturer(): ?bool
  {
    $this->getManufacturer(); // S'assurer que la vérification est faite
    return $this->checkManufacturer;
  }

  /**
   * Sorts and returns the list of columns available for product search results.
   *
   * @return array An array of column identifiers sorted by their defined order.
   */
  public function sortListSearch(): array
  {
    if (!defined('MODULE_PRODUCTS_SEARCH_LIST_NAME')) {
      return [];
    }

    $define_list = [
      'MODULE_PRODUCTS_SEARCH_LIST_NAME' => MODULE_PRODUCTS_SEARCH_LIST_NAME,
      'MODULE_PRODUCTS_SEARCH_LIST_MODEL' => MODULE_PRODUCTS_SEARCH_LIST_MODEL,
      'MODULE_PRODUCTS_SEARCH_LIST_MANUFACTURER' => MODULE_PRODUCTS_SEARCH_LIST_MANUFACTURER,
      'MODULE_PRODUCTS_SEARCH_LIST_PRICE' => MODULE_PRODUCTS_SEARCH_LIST_PRICE,
      'MODULE_PRODUCTS_SEARCH_LIST_QUANTITY' => MODULE_PRODUCTS_SEARCH_LIST_QUANTITY,
      'MODULE_PRODUCTS_SEARCH_LIST_WEIGHT' => MODULE_PRODUCTS_SEARCH_LIST_WEIGHT,
      'MODULE_PRODUCTS_SEARCH_LIST_DATE_ADDED' => MODULE_PRODUCTS_SEARCH_LIST_DATE_ADDED
    ];

    asort($define_list);

    $column_list = [];
    foreach ($define_list as $key => $value) {
      if ($value > 0) {
        $column_list[] = $key;
      }
    }

    return $column_list;
  }


  /**
   * Builds the base SQL query for product search.
   *
   * @return string The base SQL query string.
   */
  private function buildBaseQuery(): string
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $sql = 'SELECT SQL_CALC_FOUND_ROWS p.*, pd.*, m.*';

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $sql .= ', g.*';
    }

    $sql .= ' FROM :table_products p';

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $sql .= ' LEFT JOIN :table_products_groups g ON p.products_id = g.products_id';
    }

    $sql .= ' LEFT JOIN :table_specials s ON p.products_id = s.products_id';
    $sql .= ' LEFT JOIN :table_manufacturers m USING(manufacturers_id)';

    return $sql;
  }

  /**
   * Adds tax-related joins to the SQL query if price filtering is applied and prices are displayed with tax.
   *
   * @param string $sql The base SQL query.
   * @return string The SQL query with tax joins added if applicable.
   */
  private function addTaxJoins(string $sql): string
  {
    if (($this->hasPriceFrom() || $this->hasPriceTo()) && (DISPLAY_PRICE_WITH_TAX == 'true')) {
      $sql .= ' LEFT JOIN :table_tax_rates tr ON p.products_tax_class_id = tr.tax_class_id';
      $sql .= ' LEFT JOIN :table_zones_to_geo_zones gz ON tr.tax_zone_id = gz.geo_zone_id
                   AND (gz.zone_country_id IS NULL OR gz.zone_country_id = 0 OR gz.zone_country_id = :zone_country_id)
                   AND (gz.zone_id IS NULL OR gz.zone_id = 0 OR gz.zone_id = :zone_id)';
    }

    return $sql;
  }

  /**
   * Adds basic joins to the SQL query for product descriptions, categories, and product-to-category relationships.
   *
   * @param string $sql The base SQL query.
   * @return string The SQL query with basic joins added.
   */
  private function addBasicJoins(string $sql): string
  {
    $sql .= ', :table_products_description pd,
             :table_categories c,
             :table_products_to_categories p2c';

    return $sql;
  }

  /**
   * Builds the WHERE conditions for the SQL query based on customer group and product status.
   *
   * @return string The WHERE conditions as a string.
   */
  private function buildWhereConditions(): string
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Language = Registry::get('Language');

    $where = '';

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $where .= ' WHERE g.products_group_view = 1';
      $where .= ' AND g.customers_group_id = :customers_group_id';
    } else {
      $where .= ' WHERE p.products_view = 1';
    }

    // Conditions obligatoires
    $where .= ' AND p.products_status = 1
                AND p.products_archive = 0
                AND c.virtual_categories = 0
                AND c.status = 1
                AND p.products_id = pd.products_id
                AND p.products_id = p2c.products_id
                AND p2c.categories_id = c.categories_id
                AND pd.language_id = :language_id';

    return $where;
  }

  /**
   * Adds category and manufacturer search conditions to the SQL query based on the search criteria.
   *
   * @param string $sql The base SQL query.
   * @return string The SQL query with category and manufacturer conditions added.
   */
  private function addSearchConditions(string $sql): string
  {
    $CLICSHOPPING_CategoryTree = Registry::get('CategoryTree');

    // Condition catégorie
    if ($this->hasCategory()) {
      if ($this->isRecursive()) {
        $subcategories_array = [$this->getCategoryID()];
        $children = $CLICSHOPPING_CategoryTree->getChildren($this->getCategoryID(), $subcategories_array);
        $sql .= ' AND p2c.products_id = p.products_id
                     AND p2c.products_id = pd.products_id
                     AND p2c.categories_id IN (' . implode(',', $children) . ')
                     AND c.status = 1';
      } else {
        $sql .= ' AND p2c.products_id = p.products_id
                     AND p2c.products_id = pd.products_id
                     AND pd.language_id = :language_id_c
                     AND p2c.categories_id = :categories_id
                     AND c.status = 1';
      }
    }


    if ($this->hasManufacturer()) {
      $sql .= ' AND m.manufacturers_id = :manufacturers_id';
    }

    return $sql;
  }

  /**
   * Adds keyword search conditions to the SQL query based on the search criteria.
   *
   * @param string $sql The base SQL query.
   * @return string The SQL query with keyword conditions added.
   */
  private function addKeywordConditions(string $sql): string
  {
    if ($this->hasKeywords()) {
      $array = explode(' ', $this->_keywords);
      $counter = 0; // Ajout d'un compteur

      foreach ($array as $keyword) {
        $sql .= ' AND (';
        $sql .= ' pd.products_name LIKE :products_name_keywords_' . $counter . '
                 OR p.products_model LIKE :products_model_keywords_' . $counter . '
                 OR p.products_ean LIKE :products_ean_keywords_' . $counter . '
                 OR p.products_sku LIKE :products_sku_keywords_' . $counter . '
                 OR m.manufacturers_name LIKE :manufacturers_name_keywords_' . $counter;

        if ($this->hasDescription()) {
          $sql .= ' OR pd.products_description LIKE :products_description_keywords_' . $counter;
        }

        $sql .= ')';
        $counter++;
      }
    }

    return $sql;
  }

  /**
   * Adds date range conditions to the SQL query based on the search criteria.
   *
   * @param string $sql The base SQL query.
   * @return string The SQL query with date conditions added.
   */
  private function addDateConditions(string $sql): string
  {
    if ($this->hasDateFrom()) {
      $sql .= ' AND p.products_date_added >= :products_date_added_from';
    }

    if ($this->hasDateTo()) {
      $sql .= ' AND p.products_date_added <= :products_date_added_to';
    }

    return $sql;
  }

  /**
   * Adds price range conditions to the SQL query based on the search criteria.
   *
   * @param string $sql The base SQL query.
   * @return string The SQL query with price conditions added.
   */
  private function addPriceConditions(string $sql): string
  {
    if (\defined('DISPLAY_PRICE_WITH_TAX') && DISPLAY_PRICE_WITH_TAX == 'true') {
      if ($this->_price_from > 0) {
        $sql .= ' AND (IF(s.status, s.specials_new_products_price, p.products_price) * IF(gz.geo_zone_id IS NULL, 1, 1 + (tr.tax_rate / 100)) >= :price_from)';
      }

      if ($this->_price_to > 0) {
        $sql .= ' AND (IF(s.status, s.specials_new_products_price, p.products_price) * IF(gz.geo_zone_id IS NULL, 1, 1 + (tr.tax_rate / 100)) <= :price_to)';
      }
    } else {
      if ($this->_price_from > 0) {
        $sql .= ' AND (IF(s.status, s.specials_new_products_price, p.products_price) >= :price_from)';
      }

      if ($this->_price_to > 0) {
        $sql .= ' AND (IF(s.status, s.specials_new_products_price, p.products_price) <= :price_to)';
      }
    }

    return $sql;
  }

  /**
   * Adds ORDER BY clause to the SQL query based on sorting parameters.
   *
   * @param string $sql The base SQL query.
   * @return string The SQL query with ORDER BY clause added.
   */
  private function addOrderBy(string $sql): string
  {
    $sql .= ' GROUP BY p.products_id';

    $column_list = $this->sortListSearch();

    if ((!isset($_GET['sort'])) || (!preg_match('/^[1-8][ad]$/', $_GET['sort'])) || (substr($_GET['sort'], 0, 1) > count($column_list))) {
      if (is_array($column_list)) {
        for ($i = 0, $n = count($column_list); $i < $n; $i++) {
          if ($column_list[$i] == 'MODULE_PRODUCTS_SEARCH_LIST_DATE_ADDED') {
            $_GET['sort'] = $i + 1 . 'a';
            $sql .= ' ORDER BY p.products_sort_order DESC, pd.products_name';
            break;
          }
        }
      }
    } else {
      $sort_col = substr($_GET['sort'], 0, 1);
      $sort_order = substr($_GET['sort'], 1);

      switch ($column_list[$sort_col - 1]) {
        case 'MODULE_PRODUCTS_SEARCH_LIST_DATE_ADDED':
          $sql .= ' ORDER BY p.products_date_added ' . ($sort_order == 'd' ? 'DESC' : 'ASC');
          break;
        case 'MODULE_PRODUCTS_SEARCH_LIST_PRICE':
          $sql .= ' ORDER BY p.products_price ' . ($sort_order == 'd' ? 'DESC' : 'ASC') . ', p.products_date_added DESC';
          break;
        case 'MODULE_PRODUCTS_SEARCH_LIST_MODEL':
          $sql .= ' ORDER BY p.products_model ' . ($sort_order == 'd' ? 'DESC' : 'ASC') . ', p.products_date_added DESC';
          break;
        case 'MODULE_PRODUCTS_SEARCH_LIST_QUANTITY':
          $sql .= ' ORDER BY p.products_quantity ' . ($sort_order == 'd' ? 'DESC' : 'ASC') . ', p.products_date_added DESC';
          break;
        case 'MODULE_PRODUCTS_SEARCH_LIST_WEIGHT':
          $sql .= ' ORDER BY p.products_weight ' . ($sort_order == 'd' ? 'DESC' : 'ASC') . ', p.products_date_added DESC';
          break;
        case 'MODULE_PRODUCTS_SEARCH_LIST_NAME':
          $sql .= ' ORDER BY pd.products_name ' . ($sort_order == 'd' ? 'DESC' : 'ASC') . ', p.products_date_added DESC';
          break;
        case 'MODULE_PRODUCTS_SEARCH_LIST_MANUFACTURER':
          $sql .= ' ORDER BY m.manufacturers_name ' . ($sort_order == 'd' ? 'DESC' : 'ASC') . ', p.products_date_added DESC';
          break;
      }
    }

    return $sql;
  }

  /**
   * Binds parameters to the prepared statement based on the search criteria.
   *
   * @param \ClicShopping\OM\PDOStatement $stmt The prepared statement to bind parameters to.
   */
  private function bindParameters($stmt): void
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');

    // Paramètres de taxes
    if (($this->hasPriceFrom() || $this->hasPriceTo()) && (DISPLAY_PRICE_WITH_TAX == 'true')) {
      if ($CLICSHOPPING_Customer->isLoggedOn()) {
        $customer_country_id = $CLICSHOPPING_Customer->getCountryID();
        $customer_zone_id = $CLICSHOPPING_Customer->getZoneID();
      } else {
        $customer_country_id = (int)STORE_COUNTRY;
        $customer_zone_id = (int)STORE_ZONE;
      }

      $stmt->bindInt(':zone_country_id', $customer_country_id);
      $stmt->bindInt(':zone_id', $customer_zone_id);
    }

    // Paramètres de base
    $stmt->bindInt(':language_id', $CLICSHOPPING_Language->getId());

    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $stmt->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());
    }

    // Paramètres conditionnels
    if ($this->hasCategory() && !$this->isRecursive()) {
      $stmt->bindInt(':categories_id', $this->getCategoryID());
      $stmt->bindInt(':language_id_c', $CLICSHOPPING_Language->getId());
    }

    if ($this->hasManufacturer()) {
      $stmt->bindInt(':manufacturers_id', (int)$this->_manufacturer);
    }

    // Gestion des devises pour les prix
    $price_from = $this->_price_from;
    $price_to = $this->_price_to;

    if ($this->hasPriceFrom() && $CLICSHOPPING_Currencies->getValue($_SESSION['currency'])) {
      $price_from /= $CLICSHOPPING_Currencies->getValue($_SESSION['currency']);
    }

    if ($this->hasPriceTo() && $CLICSHOPPING_Currencies->getValue($_SESSION['currency'])) {
      $price_to /= $CLICSHOPPING_Currencies->getValue($_SESSION['currency']);
    }

    if ($price_from > 0) {
      $stmt->bindDecimal(':price_from', $price_from);
    }

    if ($price_to > 0) {
      $stmt->bindDecimal(':price_to', $price_to);
    }

    if ($this->hasDateFrom()) {
      $stmt->bindValue(':products_date_added_from', $this->getDateFrom());
    }

    if ($this->hasDateTo()) {
      $stmt->bindValue(':products_date_added_to', $this->getDateTo());
    }

    if ($this->hasKeywords()) {
      $array = explode(' ', $this->_keywords);
      $counter = 0; // Ajout d'un compteur

      foreach ($array as $keyword) {
        $stmt->bindValue(':products_name_keywords_' . $counter, '%' . $keyword . '%');
        $stmt->bindValue(':products_model_keywords_' . $counter, '%' . $keyword . '%');
        $stmt->bindValue(':products_sku_keywords_' . $counter, '%' . $keyword . '%');
        $stmt->bindValue(':products_ean_keywords_' . $counter, '%' . $keyword . '%');
        $stmt->bindValue(':manufacturers_name_keywords_' . $counter, '%' . $keyword . '%');

        if ($this->hasDescription()) {
          $stmt->bindValue(':products_description_keywords_' . $counter, '%' . $keyword . '%');
        }
        $counter++;
      }
    }
  }

  /**
   * Execute the search query and store the results.
   *
   * This method constructs the SQL query based on the search criteria,
   * prepares and executes it, and stores the results for later retrieval.
   */
  public function execute(): void
  {
    $max_display = defined('MODULE_PRODUCTS_SEARCH_MAX_DISPLAY') ? MODULE_PRODUCTS_SEARCH_MAX_DISPLAY : 20;

    // Construction de la requête par étapes
    $sql = $this->buildBaseQuery();
    $sql = $this->addTaxJoins($sql);
    $sql = $this->addBasicJoins($sql);
    $sql .= $this->buildWhereConditions();
    $sql = $this->addSearchConditions($sql);
    $sql = $this->addKeywordConditions($sql);
    $sql = $this->addDateConditions($sql);
    $sql = $this->addPriceConditions($sql);
    $sql = $this->addOrderBy($sql);
    $sql .= ' LIMIT :page_set_offset, :page_set_max_results';

    $stmt = $this->db->prepare($sql);
    $this->bindParameters($stmt);
    $stmt->setPageSet($max_display);
    $stmt->execute();

    $this->listing = $stmt;
    $this->_result = [
      'entries' => $stmt->fetchAll(),
      'total' => $stmt->getPageSetTotalRows()
    ];
  }

  /** Get the search results.
   *
   * @return array An associative array containing the search results and total count.
   */
  public function getResult()
  {
    if (!isset($this->_result)) {
      $this->execute();
    }
    return $this->_result;
  }

  /** Get the listing object for paginated results.
   *
   * @return \ClicShopping\OM\PDOStatement The listing object containing paginated results.
   */
  public function getListing()
  {
    return $this->listing;
  }


  /**
   * Get the total number of search results.
   *
   * @return int The total number of results found.
   */
  public function getNumberOfResults(): int
  {
    return $this->_result['total'] ?? 0;
  }
}