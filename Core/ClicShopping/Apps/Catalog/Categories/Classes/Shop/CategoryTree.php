<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\RewriteUrl;
use function count;
use function is_array;

/**
 * Class CategoryTree
 * Provides a hierarchical structure for managing and interacting with categories and their subcategories.
 */
class CategoryTree
{

  /**
   * Flag to control if the total number of products in a category should be calculated
   * @access protected
   */

  protected $show_total_products = false;
  protected $data = [];
  protected $root_category_id = 0;
  protected $max_level = 0;
  protected $root_start_string = '';
  protected $root_end_string = '';
  protected $parent_start_string = '';
  protected $parent_end_string = '';
  protected $parent_group_start_string = '<ul>';
  protected $parent_group_end_string = '</ul>';
  protected $parent_group_apply_to_root = false;
  protected $child_start_string = '<li>';
  protected $child_end_string = '</li>';
  protected $breadcrumb_separator = '_';
  protected $breadcrumb_usage = true;
  protected $spacer_string = '';
  protected $spacer_multiplier = 1;
  protected $follow_cpath = false;
  protected $cpath_array = [];
  protected $cpath_start_string = '';
  protected $cpath_end_string = '';
  protected $category_product_count_start_string = '&nbsp;(';
  protected $category_product_count_end_string = ')';
  protected $rewriteUrl;
  private mixed $db;
  private mixed $lang;

  /**
   * Constructor method to initialize the category tree data structure and its dependencies.
   *
   * This method initializes the database and language objects, loads category data
   * into the tree structure from the database, and sets up category rewrite URLs.
   *
   * @return void
   */
  public function __construct()
  {
    static $category_tree_data;

    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');

    if (isset($category_tree_data)) {
      $this->data = $category_tree_data;
    } else {
      if (CLICSHOPPING::getSite() === 'Shop') {
        $Qcategories = $this->db->prepare('select c.categories_id,
                                                     c.parent_id,
                                                     c.categories_image,
                                                     cd.categories_name,
                                                     cd.categories_description
                                              from :table_categories c,
                                                   :table_categories_description cd
                                              where c.categories_id = cd.categories_id
                                              and cd.language_id = :language_id
                                              and c.status = 1
                                              order by c.parent_id,
                                                       c.sort_order,
                                                       cd.categories_name
                                              ');
      } else {
        $Qcategories = $this->db->prepare('select c.categories_id,
                                                   c.parent_id,
                                                   c.categories_image,
                                                   cd.categories_name,
                                                   cd.categories_description
                                            from :table_categories c,
                                                 :table_categories_description cd
                                            where c.categories_id = cd.categories_id
                                            and cd.language_id = :language_id
                                            order by c.parent_id,
                                                     c.sort_order,
                                                     cd.categories_name
                                            ');
      }

      $Qcategories->bindInt(':language_id', $this->lang->getId());
      $Qcategories->setCache('categories-lang' . $this->lang->getId());

      $Qcategories->execute();

      while ($Qcategories->fetch()) {
        $this->data[$Qcategories->valueInt('parent_id')][$Qcategories->valueInt('categories_id')] = [
          'name' => $Qcategories->value('categories_name'),
          'description' => $Qcategories->value('categories_description'),
          'image' => $Qcategories->value('categories_image'),
          'count' => 0
        ];
      }

      if ($this->show_total_products === true) {
        $this->calculateProductTotals();
      }

      $category_tree_data = $this->data;
    }

    if (!Registry::exists('RewriteUrl')) {
      Registry::set('RewriteUrl', new RewriteUrl());
    }

    $this->rewriteUrl = Registry::get('RewriteUrl');
  }

  /**
   * Retrieves the total count of categories from the database.
   *
   * @return int The number of categories.
   */
  public function getCountCategories(): int
  {
    $Qcategories = $this->db->prepare('select count(categories_id) as count
                                         from :table_categories
                                        ');

    $Qcategories->execute();

    return $Qcategories->valueInt('count');
  }

  /**
   * Resets the internal properties of the object to their default values.
   *
   * @return void
   */
  public function reset()
  {
    $this->root_category_id = 0;
    $this->max_level = 0;
    $this->root_start_string = '';
    $this->root_end_string = '';
    $this->parent_start_string = '';
    $this->parent_end_string = '';
    $this->parent_group_start_string = '<ul>';
    $this->parent_group_end_string = '</ul>';
    $this->child_start_string = '<li>';
    $this->child_end_string = '</li>';
    $this->breadcrumb_separator = '_';
    $this->breadcrumb_usage = true;
    $this->spacer_string = '';
    $this->spacer_multiplier = 1;
    $this->follow_cpath = false;
    $this->cpath_array = [];
    $this->cpath_start_string = '';
    $this->cpath_end_string = '';
//      $this->show_total_products = (SERVICES_CATEGORY_PATH_CALCULATE_PRODUCT_COUNT == '1') ? true : false;
    $this->category_product_count_start_string = '&nbsp;(';
    $this->category_product_count_end_string = ')';
  }

  /**
   * Constructs a hierarchical tree structure as a string based on the provided parent ID and level.
   * The method iterates through the data array to build each branch of the tree and formats it
   * using configurable placeholder strings for parent, child, and root elements.
   *
   * @param int|string $parent_id The ID of the parent element for the branch being built.
   * @param int $level The current depth level in the hierarchy, defaults to 0 for the root level.
   * @return string The constructed string representation of the tree branch.
   */
  protected function buildBranch(int|string $parent_id, int $level = 0): string
  {
    $result = ((($level === 0) && ($this->parent_group_apply_to_root === true)) || ($level > 0)) ? $this->parent_group_start_string : null;

    if (isset($this->data[$parent_id])) {
      foreach ($this->data[$parent_id] as $category_id => $category) {
        if ($this->breadcrumb_usage === true) {
          $category_link = $this->buildBreadcrumb($category_id);
        } else {
          $category_link = $category_id;
        }

        $result .= $this->child_start_string;

        if (isset($this->data[$category_id])) {
          $result .= $this->parent_start_string;
        }

        if ($level === 0) {
          $result .= $this->root_start_string;
        }

        $category_name = $this->getCategoryTreeTitle($category['name']);

        $categories_url = $this->getCategoryTreeUrl($category_link);

        if (($this->follow_cpath === true) && in_array($category_id, $this->cpath_array)) {
          $link_title = $this->cpath_start_string . $category_name . $this->cpath_end_string;
        } else {
          $link_title = $category_name;
        }

        $result .= str_repeat($this->spacer_string, $this->spacer_multiplier * $level);

        $result .= HTML::link($categories_url, $link_title);

        if ($this->show_total_products === true) {
          $result .= $this->category_product_count_start_string . $category['count'] . $this->category_product_count_end_string;
        }

        if ($level === 0) {
          $result .= $this->root_end_string;
        }

        if (isset($this->data[$category_id])) {
          $result .= $this->parent_end_string;
        }

        if (isset($this->data[$category_id]) && (($this->max_level == '0') || ($this->max_level > $level + 1))) {
          if ($this->follow_cpath === true) {
            if (in_array($category_id, $this->cpath_array)) {
              $result .= $this->buildBranch($category_id, $level + 1);
            }
} else {
            $result .= $this->buildBranch($category_id, $level + 1);
          }
}

        $result .= $this->child_end_string;
      }
}

    $result .= ((($level === 0) && ($this->parent_group_apply_to_root === true)) || ($level > 0)) ? $this->parent_group_end_string : null;

    return $result;
  }

  /**
   * Builds an array representing a hierarchical structure of categories based on a parent ID.
   *
   * @param int|string $parent_id The parent category ID for which the branch array should be built.
   * @param int $level The current depth level in the hierarchy, starting at 0 for the root node. Defaults to 0.
   * @param string|array $result The result array to construct or append to. Defaults to an empty array.
   *
   * @return array The structured array representing the category hierarchy.
   */
  public function buildBranchArray(int|string $parent_id, int $level = 0, string $result = '')
  {
    if (empty($result)) {
      $result = [];
    }

    if (isset($this->data[$parent_id])) {
      foreach ($this->data[$parent_id] as $category_id => $category) {
        if ($this->breadcrumb_usage === true) {
          $category_link = $this->buildBreadcrumb($category_id);
        } else {
          $category_link = $category_id;
        }

        $result = [
          'id' => $category_link,
          'title' => str_repeat($this->spacer_string, $this->spacer_multiplier * $level) . $category['name']
        ];

        if (isset($this->data[$category_id]) && (($this->max_level == '0') || ($this->max_level > $level + 1))) {
          if ($this->follow_cpath === true) {
            if (in_array($category_id, $this->cpath_array, true)) {
              $result = $this->buildBranchArray($category_id, $level + 1, $result);
            }
} else {
            $result = $this->buildBranchArray($category_id, $level + 1, $result);
          }
}
      }
}

    return $result;
  }

  /**
   * Builds a breadcrumb string for a given category by traversing its hierarchy.
   *
   * @param string|null $category_id The ID of the category for which the breadcrumb is built.
   *                                 Pass null to handle cases without a specific category.
   * @param int $level The depth level in the category tree hierarchy; defaults to 0 for the root level.
   * @return string The generated breadcrumb string representing the category's hierarchical trail.
   */
  public function buildBreadcrumb(?string $category_id, int $level = 0, array $visited = []): string
  {
    $breadcrumb = '';
    
    // ✅ FIX 2025-12-05: Protection ag> $categoriete recursion
    // Protection 1: Maximum depth limit (prevent stack overflow)
    $maxDepth = 50;
    if ($level > $maxDepth) {
        error_log("CategoryTree: Maximum breadcrumb depth ($maxDepth) exceeded for category $category_id");
        return $breadcrumb;
    }
    
    // Protection 2: Circular reference detection
    if (in_array($category_id, $visited, true)) {
        error_log("CategoryTree: Circular reference detected for category $category_id at level $level");
        return $breadcrumb;
    }
    
    // Mark this category as visited
    $visited[] = $category_id;

    foreach ($this->data as $parent => $categories) {
      foreach ($categories as $id => $info) {
        if ($id == $category_id) {
          if ($level < 1) {
            $breadcrumb = $id;
          } else {
            $breadcrumb = $id . $this->breadcrumb_separator . $breadcrumb;
          }

          if ($parent != $this->root_category_id) {
            // Pass visited array to prevent circular references
            $breadcrumb = $this->buildBreadcrumb($parent, $level + 1, $visited) . $breadcrumb;
          }
          
          // Protection 3: Early return once category found
          return $breadcrumb;
        }
      }
    }

    return $breadcrumb;
  }

  /**
   * Generates a hierarchical tree structure by constructing branches starting from the root category.
   * This method initiates the tree construction process and returns a string representation of the
   * entire tree.
   *
   * @return string The complete tree structure as a formatted string.
   */

  public function getTree(): string
  {
    return $this->buildBranch($this->root_category_id);
  }

  /**
   * Generates a string representation of the tree structure by invoking the getTree method.
   * This allows the object to be treated as a string, reflecting the built tree data.
   *
   * @return string The string representation of the tree structure.
   */
  public function __toString(): string
  {
    return $this->getTree();
  }

  /**
   * Retrieves and builds an array representation of a branch based on the provided parent ID.
   * The method determines whether to use the root category or the given parent ID to create the branch data.
   *
   * @param string $parent_id The ID of the parent element for which the array should be built. Defaults to an empty string, which indicates the root category ID will be used.
   * @return bool Returns true if the branch array was successfully built, otherwise false.
   */
  public function getArray(string $parent_id = ''): bool
  {
    return $this->buildBranchArray((empty($parent_id) ? $this->root_category_id : $parent_id));
  }

  /**
   * Checks if a category with the specified ID exists in the hierarchical data structure.
   * Iterates through all parent categories and their child categories to locate the ID.
   *
   * @param string $id The ID of the category to search for.
   * @return bool True if the category ID exists, false otherwise.
   */
  public function exists(string $id): bool
  {
    foreach ($this->data as $parent => $categories) {
      foreach ($categories as $category_id => $info) {
        if ($id == $category_id) {
          return true;
        }
}
    }

    return false;
  }

  /**
   * Retrieves all child category IDs recursively for a given category ID.
   * This method traverses the category hierarchy and collects the IDs
   * of all descendant categories.
   *
   * @param string $category_id The ID of the category whose children are to be fetched.
   * @param array &$array Reference to an array where child IDs will be stored.
   * @return array An array containing the IDs of all child categories for the given category.
   */
  public function getChildren(string $category_id, array &$array = []): array
  {
    foreach ($this->data as $parent => $categories) {
      if ($parent == $category_id) {
        foreach ($categories as $id => $info) {
          $array[] = $id;
          $this->getChildren($id, $array);
        }
}
    }

    return $array;
  }

  /**
   * Retrieves data associated with a specific category ID from a hierarchical data structure.
   * The method searches through the data array to find the category and returns the full data
   * or a specific key's value depending on the provided parameters.
   *
   * @param string $id The ID of the category to retrieve data for.
   * @param string|null $key An optional key to fetch a specific value from the category data. If not provided, the entire category data is returned.
   * @return array|bool Returns an associative array of category data or a specific value if a key is provided. Returns false if the category is not found.
   */

  public function getData(string $id, ?string $key = null): array|bool
  {
    foreach ($this->data as $parent => $categories) {
      foreach ($categories as $category_id => $info) {
        if ($id == $category_id) {
          $data = [
            'id' => $id,
            'name' => $info['name'],
            'description' => $info['description'],
            'parent_id' => $parent,
            'image' => $info['image'],
            'count' => $info['count']
          ];

          return (isset($key) ? $data[$key] : $data);
        }
}
    }

    return false;
  }

  /**
   * Retrieves the parent ID(s) associated with the specified identifier.
   * This method uses the internal data structure to fetch the parent ID(s)
   * corresponding to the given identifier key.
   *
   * @param string $id The identifier for which the parent ID(s) will be retrieved.
   * @return array An array containing the parent ID(s) associated with the given identifier.
   */
  public function getParentID(string $id): array
  {
    return $this->getData($id, 'parent_id');
  }

  /**
   * Calculates the total number of products for each category in a hierarchical data structure.
   * The method retrieves product counts grouped by category from the database,
   * filters the data based on the product status if specified, and updates the
   * category hierarchy to reflect the accumulated totals in parent categories.
   *
   * @param bool $filter_active Determines if only active products should be included in the calculations.
   *                            Defaults to true, filtering products by their active status.
   * @return void This method does not return a value but updates the internal category data structure
   *              with the calculated product totals.
   */
  protected function calculateProductTotals(bool $filter_active = true): void
  {
    $totals = [];

    $sql_query = 'select p2c.categories_id, 
                           count(*) as total
                    from :table_products p,
                        :table_products_to_categories p2c
                    where p2c.products_id = p.products_id';

    if ($filter_active === true) {
      $sql_query .= ' and p.products_status = :products_status';
    }

    $sql_query .= ' group by p2c.categories_id';

    if ($filter_active === true) {
      $Qtotals = $this->db->prepare($sql_query);
      $Qtotals->bindInt(':products_status', 1);
    } else {
      $Qtotals = $this->db->query($sql_query);
    }

    $Qtotals->execute();

    while ($Qtotals->fetch()) {
      $totals[$Qtotals->valueInt('categories_id')] = $Qtotals->valueInt('total');
    }

    foreach ($this->data as $parent => $categories) {
      foreach ($categories as $id => $info) {
        if (isset($totals[$id]) && ($totals[$id] > 0)) {
          $this->data[$parent][$id]['count'] = $totals[$id];

          $parent_category = $parent;

          while ($parent_category != $this->root_category_id) {
            foreach ($this->data as $parent_parent => $parent_categories) {
              foreach ($parent_categories as $parent_category_id => $parent_category_info) {
                if ($parent_category_id == $parent_category) {
                  $this->data[$parent_parent][$parent_category_id]['count'] += $this->data[$parent][$id]['count'];

                  $parent_category = $parent_parent;

                  break 2;
                }
}
            }
}
        }
}
    }
}

  /**
   * Retrieves the number of products associated with a given category ID.
   * The method iterates through the data structure to locate the category
   * and fetch its product count. If the category ID does not exist, `false`
   * is returned.
   *
   * @param mixed $id The unique identifier for the category to search for.
   * @return int|bool The number of products in the specified category, or `false` if the category ID is not found.
   */
  public function getNumberOfProducts(int $id): int|bool
  {
    foreach ($this->data as $parent => $categories) {
      foreach ($categories as $category_id => $info) {
        if ($id == $category_id) {
          return $info['count'];
        }
}
    }

    return false;
  }

  /**
   * Sets the root category ID for the instance.
   * This method assigns the provided category ID to the root category ID property.
   *
   * @param mixed $root_category_id The ID to be set as the root category.
   * @return void
   */
  public function setRootCategoryID(int $root_category_id): void
  {
    $this->root_category_id = $root_category_id;
  }

  /**
   * Sets the maximum level for a hierarchical structure.
   * This method assigns the specified level to the internal property,
   * determining the deepest level to which the hierarchy will be processed or displayed.
   *
   * @param int $max_level The maximum depth level to be assigned.
   * @return void
   */
  public function setMaximumLevel(int $max_level): void
  {
    $this->max_level = $max_level;
  }

  /**
   * Sets the string values to be used as the start and end delimiters for the root element in the tree structure.
   *
   * @param string $root_start_string The string to be used as the starting delimiter for the root element.
   * @param string $root_end_string The string to be used as the ending delimiter for the root element.
   * @return void This method does not return a value.
   */
  public function setRootString(string $root_start_string, string $root_end_string): void
  {
    $this->root_start_string = $root_start_string;
    $this->root_end_string = $root_end_string;
  }

  /**
   * Sets the start and end strings used to format parent elements in the tree structure.
   * These strings define the delimiters or markers for parent elements during tree generation.
   *
   * @param string $parent_start_string The string to mark the beginning of a parent element.
   * @param string $parent_end_string The string to mark the end of a parent element.
   * @return void
   */
  public function setParentString(string $parent_start_string, string $parent_end_string): void
  {
    $this->parent_start_string = $parent_start_string;
    $this->parent_end_string = $parent_end_string;
  }

  /**
   * Sets the start and end strings for defining parent groups in the hierarchical structure.
   * Optionally, applies the parent group strings to the root level of the hierarchy.
   *
   * @param string $parent_group_start_string The string to use at the start of a parent group.
   * @param string $parent_group_end_string The string to use at the end of a parent group.
   * @param bool $apply_to_root Indicates whether the parent group strings should be applied to the root level.
   * @return void
   */
  public function setParentGroupString(string $parent_group_start_string, string $parent_group_end_string, bool $apply_to_root = false): void
  {
    $this->parent_group_start_string = $parent_group_start_string;
    $this->parent_group_end_string = $parent_group_end_string;
    $this->parent_group_apply_to_root = $apply_to_root;
  }

  /**
   * Sets the start and end strings for child elements in the tree structure.
   * These strings are used to format the output surrounding child elements.
   *
   * @param string $child_start_string The string to prepend to each child element.
   * @param string $child_end_string The string to append to each child element.
   * @return void
   */
  public function setChildString(string $child_start_string, string $child_end_string): void
  {
    $this->child_start_string = $child_start_string;
    $this->child_end_string = $child_end_string;
  }

  /**
   * Sets the separator used in the breadcrumb navigation.
   *
   * @param string $breadcrumb_separator The string to be used as the separator in breadcrumb navigation.
   * @return void
   */
  public function setBreadcrumbSeparator(string $breadcrumb_separator): void
  {
    $this->breadcrumb_separator = $breadcrumb_separator;
  }

  /**
   * Sets the breadcrumb usage for the instance. Determines whether breadcrumb-style
   * navigation should be applied when rendering hierarchical data elements.
   *
   * @param bool $breadcrumb_usage Indicates whether breadcrumb usage should be enabled (true) or disabled (false).
   * @return void
   */
  public function setBreadcrumbUsage(bool $breadcrumb_usage): void
  {
    if ($breadcrumb_usage === true) {
      $this->breadcrumb_usage = true;
    } else {
      $this->breadcrumb_usage = false;
    }
}

  /**
   * Sets the spacer string and its multiplier for rendering hierarchical elements.
   * The spacer string is used to format the spacing between hierarchical levels,
   * and the multiplier determines the repetition of the spacer string for each level.
   *
   * @param string $spacer_string The string used for spacing between hierarchical levels.
   * @param float|int $spacer_multiplier The value specifying how many times the spacer string is repeated per level, defaults to 2.
   * @return void
   */
  public function setSpacerString(string $spacer_string, float|int $spacer_multiplier = 2): void
  {
    $this->spacer_string = $spacer_string;
    $this->spacer_multiplier = $spacer_multiplier;
  }

  /**
   * Sets the category path and updates internal properties used for category path handling.
   * Configures whether to follow a specified category path and defines custom start and end strings
   * for displaying the category path.
   *
   * @param string $cpath The category path, typically represented as a string separated by a defined delimiter.
   * @param string $cpath_start_string Optional start string for formatting the category path display. Defaults to an empty string.
   * @param string $cpath_end_string Optional end string for formatting the category path display. Defaults to an empty string.
   * @return void This method does not return a value.
   */
  public function setCategoryPath(string $cpath, string $cpath_start_string = '', string $cpath_end_string = ''): void
  {
    $this->follow_cpath = true;
    $this->cpath_array = explode($this->breadcrumb_separator, $cpath);
    $this->cpath_start_string = $cpath_start_string;
    $this->cpath_end_string = $cpath_end_string;
  }

  /**
   * Sets the follow category path functionality for the current instance.
   * This determines whether category paths should be followed or not.
   *
   * @param bool $follow_cpath Indicates whether to enable (true) or disable (false) the follow category path functionality.
   * @return void
   */
  public function setFollowCategoryPath(bool $follow_cpath): void
  {
    if ($follow_cpath === true) {
      $this->follow_cpath = true;
    } else {
      $this->follow_cpath = false;
    }
}

  /**
   * Sets the start and end string markers for the category path. These markers are used
   * to format the appearance of category paths.
   *
   * @param string $cpath_start_string The string to prepend to the category path.
   * @param string $cpath_end_string The string to append to the category path.
   * @return void
   */
  public function setCategoryPathString(string $cpath_start_string, string $cpath_end_string): void
  {
    $this->cpath_start_string = $cpath_start_string;
    $this->cpath_end_string = $cpath_end_string;
  }

  /**
   * Sets whether the total product count for each category should be displayed.
   * This method updates an internal flag based on the provided input value.
   *
   * @param int $show_category_product_count A value indicating whether to show the product count,
   *                                         where a truthy value (e.g., 1) enables it and a falsy value (e.g., 0) disables it.
   * @return void
   */
  public function setShowCategoryProductCount(int $show_category_product_count): void
  {
    if ($show_category_product_count === true) {
      $this->show_total_products = true;
    } else {
      $this->show_total_products = false;
    }
}

  /**
   * Sets the start and end strings for displaying the product count associated with a category.
   * These strings are used as formatting placeholders when rendering the product count in the category tree.
   *
   * @param string $category_product_count_start_string The string to prepend before the product count.
   * @param string $category_product_count_end_string The string to append after the product count.
   * @return void
   */
  public function setCategoryProductCountString(string $category_product_count_start_string, string $category_product_count_end_string): void
  {
    $this->category_product_count_start_string = $category_product_count_start_string;
    $this->category_product_count_end_string = $category_product_count_end_string;
  }

  /**
   * Retrieves the formatted title of a category within the category tree.
   * The method utilizes a URL rewrite utility to generate the appropriate title.
   *
   * @param string $categories_name The original name of the category to be formatted.
   * @return string The formatted title of the category.
   */
  public function getCategoryTreeTitle(string $categories_name): string
  {
    $category_name = $this->rewriteUrl->getCategoryTreeTitle($categories_name);

    return $category_name;
  }

  /**
   * Generates the complete URL for a category based on its ID.
   * It utilizes the rewriteUrl service to construct the appropriate URL.
   *
   * @param string $categories_id The unique identifier of the category.
   * @return string The generated URL for the category.
   */
  public function getCategoryTreeUrl(string $categories_id): string
  {
    $categories_url = $this->rewriteUrl->getCategoryTreeUrl($categories_id);

    return $categories_url;
  }

  /**
   * Builds and returns a hierarchical array representing the category tree for a shop.
   * The method retrieves categories from the database and formats them into a nested structure
   * suitable for displays such as dropdown menus or navigational elements.
   *
   * @param int|string $parent_id The ID of the parent category to start building the tree from, defaults to 0.
   * @param string $spacing The string used to visually indent category names for child categories.
   * @param string $exclude The ID of a category to exclude from the tree, if any.
   * @param string $category_tree_array The current category tree array being built. If not provided, an empty array will be initialized.
   * @param bool $include_itself Indicates whether to include the parent category itself in the tree, defaults to false.
   * @return array An array representing the hierarchical category tree structure.
   */
  public function getShopCategoryTree(int|string $parent_id = 0, string $spacing = '', $exclude = '', $category_tree_array = '', bool $include_itself = false): array
  {
    $this->lang = Registry::get('Language');

    if (!is_array($category_tree_array)) {
      $category_tree_array = [];
    }

    if ((count($category_tree_array) < 1) && ($exclude != '0')) {
      $category_tree_array[] = [
        'id' => '0',
        'text' => CLICSHOPPING::getDef('text_selected')
      ];
    }

    if ($include_itself) {
    $array = [
              'language_id' => (int)$this->lang->getId(),
              'categories_id' => (int)$parent_id
              ];

      $Qcategory = $this->db->get('categories_description', 'categories_name', $array);

      $category_tree_array[] = [
        'id' => $parent_id,
        'text' => $Qcategory->value('categories_name')
      ];
    }

    $Qcategories = $this->db->get([
      'categories c',
      'categories_description cd'
    ], [
      'c.categories_id',
      'cd.categories_name',
      'c.parent_id'
    ], [
      'c.categories_id' => [
        'rel' => 'cd.categories_id'
      ],
      'cd.language_id' => (int)$this->lang->getId(),
      'c.parent_id' => (int)$parent_id
    ], [
        'c.sort_order',
        'cd.categories_name'
      ]
    );

    while ($Qcategories->fetch()) {
      if ($exclude != $Qcategories->valueInt('categories_id')) {
        if ($Qcategories->valueInt('parent_id') !== 0) {
          $category_tree_array[] = [
            'id' => $Qcategories->valueInt('parent_id') . '_' . $Qcategories->valueInt('categories_id'),
            'text' => $spacing . $Qcategories->value('categories_name')
          ];
        } else {
          $category_tree_array[] = [
            'id' => $Qcategories->valueInt('categories_id'),
            'text' => $spacing . $Qcategories->value('categories_name')
          ];
        }
}

      $category_tree_array = $this->getShopCategoryTree($Qcategories->valueInt('categories_id'), $spacing . '&nbsp;&nbsp;&nbsp;', $exclude, $category_tree_array);
    }

    return $category_tree_array;
  }
}
