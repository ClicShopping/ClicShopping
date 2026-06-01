<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Classes\Shop;

use ClicShopping\OM\Registry;
use function count;
use function is_null;

/**
 * Class representing a category with associated properties,
 * behaviors, and data management methods.
 */
class Category
{
  protected $id;
  protected string $title;
  protected string $image;
  protected $parent_id;
  protected string $description;
  protected string $category_depth;
  protected array $data = [];
  private mixed $db;
  private mixed $lang;
  protected $categoryTree;
  protected $rewriteUrl;

  /**
   * Constructor
   *
   * @param int|null $id The ID of the category to retrieve information from
   */

  public function __construct(int|null $id = null)
  {
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');
    $this->categoryTree = Registry::get('CategoryTree');

    if (!isset($id) && isset($_GET['cPath'])) {
      $cPath_array = array_unique(array_filter(explode('_', $_GET['cPath']), 'is_numeric'));

      if (!empty($cPath_array)) {
        $id = end($cPath_array);
      }
    }

    if (isset($id) && $this->categoryTree->exists($id)) {
      $this->data = $this->categoryTree->getData($id);

      $this->id = $this->data['id'];
      $this->title = $this->data['name'];
      $this->description = $this->data['description'];
      $this->image = $this->data['image'];
      $this->parent_id = $this->data['parent_id'];

      if (isset($this->data['category_depth'])) {
        $this->category_depth = $this->data['category_depth'];
      } else {
        $this->category_depth = 0;
      }
}

    if (!Registry::exists('RewriteUrl')) {
      Registry::set('RewriteUrl', new RewriteUrl());
    }

    $this->rewriteUrl = Registry::get('RewriteUrl');
  }

  /**
   * Retrieves the ID.
   *
   * @return int|null The ID value or null if not set.
   */

  public function getID(): int|null
  {
    return $this->id;
  }

  /**
   * Retrieves the description.
   *
   * @return string
   */

  public function getDescription(): string
  {
    return $this->description;
  }

  /**
   * Retrieves the title.
   * @return string
   */

  public function getTitle(): string
  {
    return $this->title;
  }

  /**
   * Checks if the object has an associated image.
   *
   * @return bool Returns true if an image exists, false otherwise.
   */
  public function hasImage(): bool
  {
    return (!empty($this->image));
  }

  /**
   * Retrieves the image path or URL.
   * @return string The image path or URL as a string.
   */

  public function getImage(): string
  {
    return $this->image;
  }

  /**
   * Checks if the current entity has a parent.
   *
   * @return bool Returns true if the entity has a parent, otherwise false.
   */

  public function hasParent(): bool
  {
    return ($this->parent_id > 0);
  }

  /**
   * Retrieves the parent ID.
   * @return int|null The ID of the parent if it exists, or null otherwise.
   */

  public function getParent(): int|null
  {
    return $this->parent_id;
  }

  /**
   * Generates the breadcrumb path for a category.
   *
   * @return mixed The breadcrumb path for the category.
   */
  public function getPath()
  {
    return $this->categoryTree->buildBreadcrumb($this->id);
  }

  /**
   * Retrieves the path of categories for the current or specified category.
   * Constructs a cPath parameter based on the current category ID and the categories path array.
   *
   * @param string $current_category_id The ID of the current category. If empty, uses the existing path array.
   * @return string The constructed cPath string representing the categories path.
   */
  public function getPathCategories($current_category_id = '')
  {
    $cPath_array = $this->getPathArray();

    if (empty($current_category_id)) {
      $cPath_new = $this->getPathArray($cPath_array);
    } else {
      if (count($cPath_array) == 0) {
        $cPath_new = $current_category_id;
      } else {
        $cPath_new = '';

        $insert_sql = [
          'categories_id' => (int)$cPath_array[(count($cPath_array) - 1)],
          'status' => 1
        ];

        $Qlast = $this->db->get('categories', 'parent_id', $insert_sql);

        $insert_sql = [
          'categories_id' => (int)$current_category_id,
          'status' => 1
        ];

        $Qcurrent = $this->db->get('categories', 'parent_id', $insert_sql);

        if ($Qlast->valueInt('parent_id') === $Qcurrent->valueInt('parent_id')) {
          for ($i = 0, $n = count($cPath_array) - 1; $i < $n; $i++) {
            $cPath_new .= '_' . $cPath_array[$i];
          }
} else {
          for ($i = 0, $n = count($cPath_array); $i < $n; $i++) {
            $cPath_new .= $cPath_array[$i];
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
   * Retrieves an array of path components or a specific component by index.
   *
   * @param int|null $id The index of the specific component to retrieve (optional).
   * @return array|string The path array if no index is provided or the specific component at the given index.
   */
  public function getPathArray(?int $id = null)
  {
    $cPath_array = explode('_', $this->getPath());

    if (isset($id)) {
      return $cPath_array[$id];
    }

    return $cPath_array;
  }

  /**
   * Retrieves data associated with the given keyword.
   * @param string $keyword The key used to fetch the corresponding data.
   * @return mixed The data associated with the provided keyword.
   */

  public function getData($keyword)
  {
    return $this->data[$keyword] ?? null;
  }

  /**
   * Determines the depth of a category.
   * It can be 'top', 'nested', or 'products', depending on its relation:
   *  - 'top' when no category path is set.
   *  - 'nested' when the category has subcategories.
   *  - 'products' when the category contains products or is empty.
   *
   * @return string The category depth ('top', 'nested', or 'products').
   */

  public function getDepth()
  {
    $this->category_depth = 'top';

    if (isset($_GET['cPath']) && !is_null($_GET['cPath'])) {
      $Qcheck = $this->db->prepare('select products_id
                                      from :table_products_to_categories
                                      where categories_id = :categories_id
                                      limit 1
                                     ');
      $Qcheck->bindInt(':categories_id', $this->id);
      $Qcheck->execute();

      if ($Qcheck->fetch() === false) {
        $this->category_depth = 'products'; // display products
      } else {
        $Qcheck = $this->db->prepare('select categories_id
                                         from :table_categories
                                         where parent_id = :parent_id
                                         and status = 1
                                        ');
        $Qcheck->bindInt(':parent_id', $this->id);
        $Qcheck->execute();

        if ($Qcheck->fetch() !== false) {
          $this->category_depth = 'nested'; // navigate through the categories
        } else {
          $this->category_depth = 'products'; // category has no products, but display the 'no products' message
        }
}
    }

    return $this->category_depth;
  }

  /**
   * Retrieves the count of nested categories for a specific category.
   *
   * This method calculates the total number of categories that are referenced
   * by products and have the specified category ID, with a parent ID of 0
   * and are active (status = 1).
   *
   * @return int Returns the total number of nested categories as an integer.
   */

  public function getCountCategoriesNested()
  {
    $Qcategories = $this->db->prepare('select count(*) as total
                                         from :table_categories c,
                                              :table_products_to_categories cd
                                         where c.parent_id = 0
                                         and c.categories_id = cd.categories_id
                                         and cd.categories_id = :categories_id
                                         and status = 1
                                       ');
    $Qcategories->bindInt(':categories_id', $this->id);
    $Qcategories->execute();

    $total = $Qcategories->valueInt('total');

    return $total;
  }

  /**
   * Checks if a category has subcategories.
   *
   * @param int $category_id The ID of the category to check for subcategories.
   * @return bool Returns true if the category has subcategories, false otherwise.
   */

  public function getHasSubCategories($category_id)
  {
    $Qcheck = $this->db->prepare('select categories_id
                                    from :table_categories
                                    where parent_id = :parent_id
                                    and status = 1
                                    limit 1
                                  ');
    $Qcheck->bindInt(':parent_id', $category_id);
    $Qcheck->execute();

    return ($Qcheck->fetch() !== false);
  }

  /**
   * Retrieves subcategories recursively for a given parent category ID.
   *
   * @param array &$subcategories_array Reference to an array that will be populated with subcategory IDs.
   * @param int $parent_id The ID of the parent category. Defaults to 0.
   * @return void
   */
  public function getSubcategories(&$subcategories_array, $parent_id = 0)
  {
    $Qsub = $this->db->prepare('select categories_id
                                  from :table_categories
                                  where parent_id = :parent_id
                                  and status = 1
                                  ');
    $Qsub->bindInt(':parent_id', $parent_id);
    $Qsub->execute();

    while ($Qsub->fetch()) {
      $subcategories_array[count($subcategories_array)] = $Qsub->valueInt('categories_id');

      if ($Qsub->valueInt('categories_id') != $parent_id) {
        $this->getSubcategories($subcategories_array, $Qsub->valueInt('categories_id'));
      }
}
  }

  /**
   * Retrieves a hierarchical list of categories based on a parent ID and language.
   *
   * @param array|null $categories_array An array to store the retrieved categories, or null to initialize a new array.
   * @param int|null $parent_id The ID of the parent category. Default is 0.
   * @param string $indent A string used to indent child categories. Default is an empty string.
   * @return array|null An array of categories or null if no categories are found.
   */

  public function getCategories(?array $categories_array = null,  int|null $parent_id = 0, string $indent = ''): ?array
  {
    $Qcategories = $this->db->prepare('select c.categories_id,
                                                cd.categories_name
                                        from :table_categories c,
                                             :table_categories_description cd
                                        where c.parent_id = :parent_id
                                        and c.categories_id = cd.categories_id
                                        and cd.language_id = :language_id
                                        and c.virtual_categories = 0
                                        and c.status = 1
                                        order by sort_order,
                                                 cd.categories_name
                                       ');
    $Qcategories->bindInt(':parent_id', $parent_id);
    $Qcategories->bindInt(':language_id', $this->lang->getId());
    $Qcategories->execute();

    while ($Qcategories->fetch()) {
      $array = [
        'id' => $Qcategories->valueInt('categories_id'),
        'text' => $indent . $Qcategories->value('categories_name')
      ];

      if ($Qcategories->valueInt('categories_id') !== $parent_id) {
        $categories_array[] = $this->getCategories($array, $Qcategories->valueInt('categories_id'), $indent . '&nbsp;&nbsp;');
      }
}

    return $categories_array;
  }

  /**
   * Retrieves the parent categories of a specified category ID and stores them in the provided categories array.
   *
   * @param array &$categories An array to store the parent category IDs.
   * @param int $categories_id The ID of the category for which parent categories should be retrieved.
   * @return bool Returns true if the top-level parent (with parent_id = 0) is reached.
   */
  public function getParentCategories(&$categories, $categories_id)
  {

    $Qparent = $this->db->prepare('select parent_id
                                    from :table_categories
                                    where categories_id = :categories_id
                                    and status = 1
                                    ');

    $Qparent->bindInt(':categories_id', $categories_id);
    $Qparent->execute();

    while ($Qparent->fetch()) {
      if ($Qparent->valueInt('parent_id') == 0) {
        return true;
      }

      $categories[count($categories)] = $Qparent->valueInt('parent_id');

      if ($Qparent->valueInt('parent_id') != $categories_id) {
        $this->getParentCategories($categories, $Qparent->valueInt('parent_id'));
      }
}
  }

  /**
   * Retrieves the product path for a given product ID by determining its category hierarchy.
   *
   * @param int $products_id The ID of the product for which to retrieve the path.
   * @return string The product path constructed from its category hierarchy.
   */
  public function getProductPath($products_id)
  {
    $cPath = '';

    $Qcategory = $this->db->prepare('select p2c.categories_id
                                      from :table_products p,
                                           :table_products_to_categories p2c
                                      where p.products_id = :products_id
                                      and p.products_status = 1
                                      and p.products_id = p2c.products_id
                                      and p.products_archive = 0
                                      limit 1
                                      ');
    $Qcategory->bindInt(':products_id', $products_id);

    $Qcategory->execute();

    if ($Qcategory->fetch() !== false) {
      $categories = [];
      $this->getParentCategories($categories, $Qcategory->valueInt('categories_id'));

      $categories = array_reverse($categories);

      $cPath = implode('_', $categories);

      if (!is_null($cPath)) {
        $cPath .= '_';
      }

      $cPath .= $Qcategory->valueInt('categories_id');
    }

    return $cPath;
  }

  /**
   * Retrieves the image URL of a category based on its ID.
   *
   * @param int|string $categories_id The ID of the category whose image URL is to be retrieved.
   * @return string The URL of the category image.
   */
  public function getCategoryImageUrl($categories_id)
  {
    $category = $this->getPathCategories($categories_id);

    $categories_url = $this->rewriteUrl->getCategoryImageUrl($category);

    return $categories_url;
  }

  /**
   * Retrieves the title of a category based on its name.
   *
   * @param string $categories_name The name of the category to retrieve the title for.
   *
   * @return string The title of the category.
   */
  public function getCategoryTitle($categories_name)
  {
    $category_name = $this->rewriteUrl->getCategoryTreeTitle($categories_name);

    return $category_name;
  }
}
