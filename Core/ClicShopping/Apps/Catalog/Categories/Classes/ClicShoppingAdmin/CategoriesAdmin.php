<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use function call_user_func;
use function count;
use function is_array;
use function is_null;
use function strlen;
/**
 * Class CategoriesAdmin
 *
 * This class provides functionality for managing and retrieving category-related data in the admin panel for ClicShopping.
 */
class CategoriesAdmin
{
  private mixed $lang;
  private mixed $template;
  private mixed $db;

  /**
   * Constructor method to initialize required dependencies.
   *
   * @return void
   */
  public function __construct()
  {

    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');
    $this->template = Registry::get('TemplateAdmin');
  }

  /**
   * Retrieves categories based on search keywords or parent category ID.
   *
   * @param string|null $keywords Optional search keywords to filter categories by name.
   * @return object Query result object containing the matched categories.
   */
  public function getSearch(?string $keywords = null)
  {
    $current_category_id = 0;

    if (isset($_POST['cPath'])) {
      $current_category_id = HTML::sanitize($_POST['cPath']);
    } elseif (isset($_GET['cPath'])) {
      $current_category_id = HTML::sanitize($_GET['cPath']);
    }

    if (!is_null($keywords)) {
      $search = HTML::sanitize($keywords);

      $Qcategories = $this->db->prepare('select SQL_CALC_FOUND_ROWS c.categories_id,
                                                                     cd.categories_name,
                                                                     c.categories_image,
                                                                     c.parent_id,
                                                                     c.sort_order,
                                                                     c.date_added,
                                                                     c.last_modified ,
                                                                     c.status,
                                                                     c.virtual_categories
                                          from :table_categories c,
                                               :table_categories_description cd
                                          where c.categories_id = cd.categories_id
                                          and cd.language_id = :language_id
                                          and cd.categories_name like :search
                                          order by c.sort_order,
                                                   cd.categories_name
                                          limit :page_set_offset, :page_set_max_results
                                          ');
      $Qcategories->bindInt(':language_id', $this->lang->getId());
      $Qcategories->bindValue(':search', '%' . $search . '%');
      $Qcategories->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qcategories->execute();
    } else {
      $Qcategories = $this->db->prepare('select SQL_CALC_FOUND_ROWS c.categories_id,
                                                                     cd.categories_name,
                                                                     c.categories_image,
                                                                     c.parent_id,
                                                                     c.sort_order,
                                                                     c.date_added,
                                                                     c.last_modified,
                                                                     c.status,
                                                                     c.virtual_categories
                                        from :table_categories c,
                                             :table_categories_description cd
                                        where c.parent_id = :parent_id
                                        and c.categories_id = cd.categories_id
                                        and cd.language_id = :language_id
                                        order by c.sort_order,
                                                  cd.categories_name
                                        limit :page_set_offset, :page_set_max_results
                                        ');

      $Qcategories->bindInt(':parent_id', $current_category_id);
      $Qcategories->bindInt(':language_id', $this->lang->getId());
      $Qcategories->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qcategories->execute();
    }

    return $Qcategories;
  }

  /**
   * Retrieve the category path array.
   *
   * @param int|null $id Optional index to retrieve a specific path segment from the path array.
   * @return array Returns the parsed category path as an array. If $id is provided, returns the specific segment of the array at the given index.
   */
  public function getPathArray( int|null $id = null): array
  {
    $CLICSHOPPING_CategoryCommon = Registry::get('CategoryCommon');

// calculate category path
    if ((isset($_POST['cPath']) && !empty($_POST['cPath'])) || (isset($_GET['cPath']) && !empty($_GET['cPath']))) {
      if (isset($_POST['cPath'])) {
        $cPath = HTML::sanitize($_POST['cPath']);
      } else {
        $cPath = HTML::sanitize($_GET['cPath']);
      }

      $cPath_array = $CLICSHOPPING_CategoryCommon->getParseCategoryPath($cPath);
    } else {
      $cPath_array = [];
    }

    if (isset($id)) {
      return $cPath_array[$id];
    }

    return $cPath_array;
  }

  /**
   * Retrieve the name of a category based on its ID and language ID.
   *
   * @param int $category_id The ID of the category to retrieve the name for.
   * @param int $language_id The ID of the language for which the category name is required.
   * @return string The name of the category in the specified language.
   */
  public function getCategoryName(int $category_id, int $language_id = 0): string
  {
    if ($language_id == 0) $language_id = $this->lang->getId();

    $array = [
      'categories_id' => (int)$category_id,
      'language_id' => (int)$language_id
    ];

    $Qcategory = Registry::get('Db')->get('categories_description', 'categories_name', $array);

    return $Qcategory->value('categories_name');
  }

  /**
   * Retrieve the description of a category based on the given category and language IDs.
   *
   * @param int $category_id The ID of the category whose description is to be retrieved.
   * @param int $language_id The ID of the language for the description; defaults to the current language if not provided.
   * @return string The description of the category.
   */
  public function getCategoryDescription(int $category_id, int $language_id): string
  {

    if (!$language_id) $language_id = $this->lang->getId();

    $Qcategory = $this->db->prepare('select categories_description
                                        from :table_categories_description
                                        where categories_id = :categories_id
                                        and language_id = :language_id
                                      ');

    $Qcategory->bindInt(':categories_id', $category_id);
    $Qcategory->bindInt(':language_id', $language_id);

    $Qcategory->execute();

    return $Qcategory->value('categories_description');
  }

  /**
   * Retrieves the total count of child categories within a specific category, including subcategories recursively.
   *
   * @param int $categories_id The ID of the parent category whose child categories will be counted.
   * @return int The total number of child categories located within the specified category.
   */
// pb avec static function

  public function getChildsInCategoryCount(int $categories_id): int
  {
    $categories_count = 0;

    $Qcategories = Registry::get('Db')->get('categories', 'categories_id', ['parent_id' => (int)$categories_id]);

    while ($Qcategories->fetch() !== false) {
      $categories_count++;

      $categories_count += call_user_func(__METHOD__, $Qcategories->valueInt('categories_id'));
    }

    return $categories_count;
  }


  /**
   * Retrieves the total count of products in a specified category, optionally including deactivated products.
   *
   * This method calculates the total number of products in the given category and its subcategories.
   * It can include or exclude deactivated products based on the provided parameter.
   *
   * @param int $categories_id The ID of the category for which the product count is being calculated.
   * @param bool $include_deactivated Whether to include deactivated products (products with a status other than '1') in the count.
   *                                   Defaults to false.
   * @return int The total count of products in the category and its subcategories.
   */
  public function getCatalogInCategoryCount(int $categories_id, bool $include_deactivated = false): int
  {
    if ($include_deactivated) {
      $Qproducts = $this->db->get([
        'products p',
        'products_to_categories p2c'
      ], [
        'count(*) as total'
      ], [
          'p.products_id' => [
            'rel' => 'p2c.products_id'
          ],
          'p2c.categories_id' => (int)$categories_id
        ]
      );
    } else {
      $Qproducts = $this->db->get([
        'products p',
        'products_to_categories p2c'
      ], [
        'count(*) as total'
      ], [
          'p.products_id' => [
            'rel' => 'p2c.products_id'
          ],
          'p.products_status' => '1',
          'p2c.categories_id' => (int)$categories_id
        ]
      );
    }

    $products_count = $Qproducts->valueInt('total');

    $Qchildren = $this->db->get('categories', 'categories_id', ['parent_id' => (int)$categories_id]);


    while ($Qchildren->fetch() !== false) {
      $products_count += call_user_func(__METHOD__, $Qchildren->valueInt('categories_id'), $include_deactivated);
    }

    return $products_count;
  }

  /**
   * Generates a string representation of category path IDs.
   *
   * @param int|string $id The ID of the category or entity to generate the path for.
   * @param string $from The type of entity for which the path is being generated (default is 'category').
   *                      It specifies the starting point or context for generating the path.
   *
   * @return string A string containing the generated category path IDs, structured and concatenated
   *                based on the specified entity context.
   */
  public function getGeneratedCategoryPathIds($id, string $from = 'category')
  {
    $calculated_category_path_string = '';
    $calculated_category_path = $this->getGenerateCategoryPath($id, $from);
    for ($i = 0, $n = count($calculated_category_path); $i < $n; $i++) {
      for ($j = 0, $k = count($calculated_category_path[$i]); $j < $k; $j++) {
        $calculated_category_path_string .= $calculated_category_path[$i][$j]['id'] . '_';
      }
      $calculated_category_path_string = substr($calculated_category_path_string, 0, -1) . '<br />';
    }

    $calculated_category_path_string = substr($calculated_category_path_string, 0, -6);

    if (strlen($calculated_category_path_string) < 1) $calculated_category_path_string = $this->db->getDef('text_top');

    return $calculated_category_path_string;
  }

  /**
   * Removes a category from the database, including its image, descriptions, and associations with products.
   * It also clears related cache entries. If the category image is not used by other entities, it is deleted from the file system.
   *
   * @param int $category_id The unique identifier of the category to be removed.
   *
   * @return void
   */
  public function removeCategory(int $category_id)
  {
    $QcategoriesImage = $this->db->prepare('select categories_image
                                               from :table_categories
                                               where categories_id = :categories_id
                                             ');
    $QcategoriesImage->bindInt(':categories_id', (int)$category_id);

    $QcategoriesImage->execute();

    $QduplicateImage = $this->db->prepare('select count(*) as total
                                             from :table_categories
                                             where categories_image = :categories_image
                                             ');
    $QduplicateImage->bindValue(':categories_image', $QcategoriesImage->value('categories_image'));

    $QduplicateImage->execute();

    $QduplicateImageBanners = $this->db->prepare('select count(*) as total
                                                    from :table_banners
                                                    where banners_image = :banners_image
                                                   ');
    $QduplicateImageBanners->bindValue(':banners_image', $QcategoriesImage->value('categories_image'));

    $QduplicateImageBanners->execute();

//*******************************
// Manufacturer
//*******************************
    $QduplicateImageManufacturers = $this->db->prepare('select count(*) as total
                                                        from :table_manufacturers
                                                        where manufacturers_image = :manufacturers_image
                                                       ');
    $QduplicateImageManufacturers->bindValue(':manufacturers_image', $QcategoriesImage->value('categories_image'));

    $QduplicateImageManufacturers->execute();

//*******************************
// Supplier
//*******************************
    $QduplicateImageSuppliers = $this->db->prepare('select count(*) as total
                                                    from :table_suppliers
                                                    where suppliers_image = :suppliers_image
                                                   ');
    $QduplicateImageSuppliers->bindValue(':suppliers_image', $QcategoriesImage->value('categories_image'));

    $QduplicateImageSuppliers->execute();

//*******************************
// Products
//*******************************
    $QduplicateProductsImage = $this->db->prepare('select count(*) as total
                                                    from :table_products
                                                    where products_image = :products_image
                                                   ');
    $QduplicateProductsImage->bindValue(':products_image', $QcategoriesImage->value('categories_image'));

    $QduplicateProductsImage->execute();

    if (($QduplicateImage->valueInt('total') < 2) &&
      ($QduplicateImageBanners->valueInt('total') == 0) &&
      ($QduplicateImageManufacturers->valueInt('total') == 0) &&
      ($QduplicateImageSuppliers->valueInt('total') == 0) &&
      ($QduplicateProductsImage->valueInt('total') == 0)) {
// delete categorie image
      if (file_exists($this->template->getDirectoryPathTemplateShopImages() . $QcategoriesImage->value('categories_image'))) {
        unlink($this->template->getDirectoryPathTemplateShopImages() . $QcategoriesImage->value('categories_image'));
      }
    }

    $this->db->delete('categories', ['categories_id' => (int)$category_id]);
    $this->db->delete('categories_description', ['categories_id' => (int)$category_id]);
    $this->db->delete('products_to_categories', ['categories_id' => (int)$category_id]);

    Cache::clear('categories');
    Cache::clear('products-also_purchased');
    Cache::clear('products_related');
    Cache::clear('products_cross_sell');
    Cache::clear('upcoming');
  }

  /**
   * Constructs and returns the category path as a string parameter based on the current category
   * ID and the existing category path array.
   *
   * @param string $current_category_id The current category ID to append to the path. If empty,
   *                                    the path will be generated from the existing category path array.
   * @return string The constructed category path in the format 'cPath=x_x_x', where x represents
   *                the category IDs.
   */
  public function getCategoriesPath($current_category_id = '')
  {
    $cPath_array = $this->getPathArray();

    if ($current_category_id == '') {
      $cPath_new = implode('_', $cPath_array);
    } else {
      if (count($cPath_array) == 0) {
        $cPath_new = $current_category_id;
      } else {
        $cPath_new = '';

        $Qlast = $this->db->get('categories', 'parent_id', ['categories_id' => (int)$cPath_array[(count($cPath_array) - 1)]]);

        $Qcurrent = $this->db->get('categories', 'parent_id', ['categories_id' => (int)$current_category_id]);

        if ($Qlast->valueInt('parent_id') === $Qcurrent->valueInt('parent_id')) {
          for ($i = 0, $n = count($cPath_array) - 1; $i < $n; $i++) {
            $cPath_new .= '_' . $cPath_array[$i];
          }
        } else {
          for ($i = 0, $n = count($cPath_array); $i < $n; $i++) {
            $cPath_new .= '_' . $cPath_array[$i];
          }
        }

        $cPath_new .= '_' . $current_category_id;

        if (substr($cPath_new, 0, 1) == '_') {
          $cPath_new = substr($cPath_new, 1);
        }
      }
    }

    return 'cPath=' . $cPath_new;
  }

  /**
   * Recursively constructs and returns a hierarchical category tree structure as an array.
   *
   * @param int|string $parent_id The parent category ID from which to start building the tree. Defaults to '0'.
   * @param string $spacing The spacing string used to denote the level of nesting for category names.
   * @param int|string $exclude The ID of a category to exclude from the tree. Defaults to an empty string.
   * @param array|string $category_tree_array The tree array being built. If not provided, it will initialize an empty array.
   * @param bool $include_itself Whether to include the parent ID as a top-level node in the tree. Defaults to false.
   * @return array An array representing the category tree, with each element containing 'id' and 'text' keys.
   */
  public function getCategoryTree($parent_id = '0', string $spacing = '', $exclude = '', $category_tree_array = '', bool $include_itself = false)
  {
    if (!is_array($category_tree_array)) {
      $category_tree_array = [];
    }
    if ((count($category_tree_array) < 1) && ($exclude != '0')) {
      $category_tree_array[] = ['id' => '0', 'text' => CLICSHOPPING::getDef('text_top')];
    }

    if ($include_itself) {
      $sql_array = [
        'language_id' => $this->lang->getId(),
        'categories_id' => (int)$parent_id
      ];

      $Qcategory = $this->db->get('categories_description', 'categories_name', $sql_array);

      $category_tree_array[] = [
        'id' => $parent_id,
        'text' => $Qcategory->value('categories_name')
      ];
    }

    $Qcategories = $this->db->get(['categories c',
      'categories_description cd'
    ], [
      'c.categories_id',
      'cd.categories_name',
      'c.parent_id'
    ], [
      'c.categories_id' => [
        'rel' => 'cd.categories_id'
      ],
      'cd.language_id' => $this->lang->getId(),
      'c.parent_id' => (int)$parent_id
    ], [
        'c.sort_order',
        'cd.categories_name'
      ]
    );


    while ($Qcategories->fetch()) {
      if ($exclude != $Qcategories->valueInt('categories_id')) {
        $category_tree_array[] = [
          'id' => $Qcategories->valueInt('categories_id'),
          'text' => $spacing . $Qcategories->value('categories_name')
        ];
      }

      $category_tree_array = $this->getCategoryTree($Qcategories->valueInt('categories_id'), $spacing . '&nbsp;&nbsp;&nbsp;', $exclude, $category_tree_array);
    }

    return $category_tree_array;
  }

  /**
   * Generates and returns a formatted category path string based on the current category
   * ID and the existing category path structure.
   *
   * @param string $current_category_id The category ID to append to the path. If not provided,
   *                                    the path will be constructed from the existing category path array.
   * @return string The resulting category path in the format 'cPath=x_x_x', where each x is a category ID.
   */
  public function getPath(string $current_category_id = ''): string
  {
    $cPath_array = $this->getPathArray();

    if (empty($current_category_id)) {
      $cPath_new = implode('_', $cPath_array);
    } else {
      if (count($cPath_array) == 0) {
        $cPath_new = $current_category_id;
      } else {
        $cPath_new = '';

        $Qlast = $this->db->get('categories', 'parent_id', ['categories_id' => (int)$cPath_array[(count($cPath_array) - 1)]]);

        $Qcurrent = $this->db->get('categories', 'parent_id', ['categories_id' => (int)$current_category_id]);

        if ($Qlast->valueInt('parent_id') === $Qcurrent->valueInt('parent_id')) {
          for ($i = 0, $n = count($cPath_array) - 1; $i < $n; $i++) {
            $cPath_new .= '_' . $cPath_array[$i];
          }
        } else {
          for ($i = 0, $n = count($cPath_array); $i < $n; $i++) {
            $cPath_new .= '_' . $cPath_array[$i];
          }
        }

        $cPath_new .= '_' . $current_category_id;

        if (substr($cPath_new, 0, 1) == '_') {
          $cPath_new = substr($cPath_new, 1);
        }
      }
    }

    return 'cPath=' . $cPath_new;
  }

  /**
   * Generates a multi-dimensional array containing category paths or product-to-category
   * relationships and their hierarchical structure.
   *
   * @param int|string $id The ID of the category or product to generate the path for.
   * @param string $from Specifies the context: 'category' to generate category paths or 'product'
   *                     to generate product-to-category mappings.
   * @param array|null $categories_array An optional array to hold and build the generated category
   *                                      structure. Defaults to an empty array.
   * @param int $index The index used for structuring the category array hierarchy.
   * @return array A multi-dimensional array representing the generated category paths or
   *               product-to-category relationships and their parent-child hierarchy.
   */
  public function getGenerateCategoryPath(int $id, string $from = 'category', $categories_array = '', int $index = 0): array
  {
    if (!is_array($categories_array)) {
      $categories_array = [];
    }

    if ($from == 'product') {
      $Qcategories = $this->db->get('products_to_categories', 'categories_id', ['products_id' => (int)$id]);

      while ($Qcategories->fetch()) {
        if ($Qcategories->valueInt('categories_id') === 0) {
          $categories_array[$index][] = [
            'id' => '0',
            'text' => CLICSHOPPING::getDef('text_top')
          ];
        } else {
          $Qcategory = $this->db->get([
            'categories c',
            'categories_description cd'
          ], [
            'cd.categories_name',
            'c.parent_id'
          ], [
              'c.categories_id' => [
                'val' => $Qcategories->valueInt('categories_id'),
                'rel' => 'cd.categories_id'
              ],
              'cd.language_id' => (int)$this->lang->getId()
            ]
          );

          $categories_array[$index][] = [
            'id' => $Qcategories->valueInt('categories_id'),
            'text' => $Qcategory->value('categories_name')
          ];

          /*
                      if ($Qcategory->valueInt('parent_id') > 0) {
                        $categories_array = call_user_func(__FUNCTION__, $Qcategory->valueInt('parent_id'), 'category', $categories_array, $index);
                      }
          */
          $categories_array[$index] = array_reverse($categories_array[$index]);
        }

        $index++;
      }
    } elseif ($from == 'category') {
      $Qcategory = $this->db->get([
        'categories c',
        'categories_description cd'
      ], [
        'cd.categories_name',
        'c.parent_id'
      ], [
          'c.categories_id' => [
            'val' => (int)$id,
            'rel' => 'cd.categories_id'
          ],
          'cd.language_id' => (int)$this->lang->getId()
        ]
      );

      $categories_array[$index][] = [
        'id' => (int)$id,
        'text' => $Qcategory->value('categories_name')
      ];

      if ($Qcategory->valueInt('parent_id') > 0) {
        $categories_array = call_user_func(__FUNCTION__, $Qcategory->valueInt('parent_id'), 'category', $categories_array, $index);
      }
    }

    return $categories_array;
  }

  /**
   * Generates and returns a string representation of a category path based on the given ID
   * and type, formatted with separators and line breaks.
   *
   * @param int $id The ID of the category or item for which the category path is generated.
   * @param string $from Specifies the type of the category path generation, defaulting to 'category'.
   * @return string The generated category path as a formatted string, with delimiters and structure.
   */
  public function getOutputGeneratedCategoryPath(int $id, string $from = 'category')
  {
    $calculated_category_path_string = '';

    $calculated_category_path = $this->getGenerateCategoryPath($id, $from);

    for ($i = 0, $n = count($calculated_category_path); $i < $n; $i++) {
      for ($j = 0, $k = count($calculated_category_path[$i]); $j < $k; $j++) {
        $calculated_category_path_string .= $calculated_category_path[$i][$j]['text'] . '&nbsp;&gt;&nbsp;';
      }

      $calculated_category_path_string = substr($calculated_category_path_string, 0, -16) . '<br />';
    }

    $calculated_category_path_string = substr($calculated_category_path_string, 0, -6);

    if (strlen($calculated_category_path_string) < 1) {
      $calculated_category_path_string = CLICSHOPPING::getDef('text_top');
    }

    return $calculated_category_path_string;
  }
}