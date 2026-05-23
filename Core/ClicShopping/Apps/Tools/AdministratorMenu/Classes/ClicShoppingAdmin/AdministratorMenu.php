<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use function call_user_func;
use function count;
use function is_array;
use function strlen;

class AdministratorMenu
{
  protected int $language_id;
  private static mixed $data;

  public function __construct()
  {
    static $_category_tree_data;

    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');


    if (isset($_category_tree_data)) {
      static::$data = $_category_tree_data;
    } else {
      $Qcategories = $this->db->prepare('select am.id,
                                               am.parent_id,
                                               amd.label
                                        from :table_administrator_menu am,
                                             :table_administrator_menu_description amd
                                        where am.id = amd.id
                                        and amd.language_id = :language_id
                                        order by am.parent_id,
                                                 am.sort_order,
                                                 amd.label
                                        ');

      $Qcategories->bindInt(':language_id', $this->lang->getId());

      $Qcategories->execute();

      while ($Qcategories->fetch()) {
        static::$data[$Qcategories->valueInt('parent_id')][$Qcategories->valueInt('id')] = [
          'name' => $Qcategories->value('label'),
          'count' => 0
        ];
      }
    }
  }

  /*
  * Parse and secure the cPath parameter values
  * @int, $cPath, value of cPath
  * return @ string array $tmp_array
  */
  /**
   * Parses and sanitizes a category path string into an array of unique integers while ensuring integrity.
   *
   * @param string $cPath The category path string with category IDs separated by underscores.
   * @return array An array of unique category IDs as integers.
   */
  private static function getParseCategoryPath(string $cPath): array
  {
// make sure the category IDs are integers
    $cPath_array = array_map(function ($string) {
      return (int)$string;
    }, explode('_', $cPath));

// make sure no duplicate category IDs exist which could lock the server in a loop
    $tmp_array = [];
    $n = count($cPath_array);

    for ($i = 0; $i < $n; $i++) {
      if (!in_array($cPath_array[$i], $tmp_array, true)) {
        $tmp_array[] = $cPath_array[$i];
      }
    }

    return $tmp_array;
  }


  /**
   * Retrieves the parsed category path as an array.
   *
   * @param int|null $id The specific index of the category path array to be returned, or null to return the entire array.
   * @return array The category path array or a specific element of it if an index is provided.
   */
  public static function getPathArray(int|null $id = null): array
  {
    if ((isset($_POST['cPath']) && !empty($_POST['cPath'])) || (isset($_GET['cPath']) && !empty($_GET['cPath']))) {
      if (isset($_POST['cPath'])) {
        $cPath = HTML::sanitize($_POST['cPath']);
      } else {
        $cPath = HTML::sanitize($_GET['cPath']);
      }

      $cPath_array = static::getParseCategoryPath($cPath);
    } else {
      $cPath_array = [];
    }

    if (isset($id)) {
      return $cPath_array[$id];
    }

    return $cPath_array;
  }

  /**
   * Constructs a category path string based on the current or specified category ID.
   *
   * @param string $current_category_id The identifier of the current category to build the path for. Default is an empty string.
   * @return string The generated category path string in the format 'cPath=...'.
   */
  public static function getPath(string $current_category_id = ''): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $cPath_array = self::getPathArray();

    if ($current_category_id == '') {
      $cPath_new = implode('_', $cPath_array);
    } else {
      if (count($cPath_array) == 0) {
        $cPath_new = $current_category_id;
      } else {
        $cPath_new = '';

        $Qlast = $CLICSHOPPING_Db->get('administrator_menu', 'parent_id', ['id' => (int)$cPath_array[(count($cPath_array) - 1)]]);

        $Qcurrent = $CLICSHOPPING_Db->get('administrator_menu', 'parent_id', ['id' => (int)$current_category_id]);

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
   * Retrieves the label associated with an administrator menu item.
   *
   * @param string|null $id The ID of the administrator menu item. Can be null.
   * @param int $language_id The ID of the language for which the label should be retrieved.
   * @return string The label of the administrator menu item for the specified language.
   */
  public static function getAdministratorMenuLabel(?string $id, int $language_id): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();
    $Qcategory = Registry::get('Db')->get('administrator_menu_description', 'label', ['id' => (int)$id, 'language_id' => (int)$language_id]);

    return $Qcategory->value('label');
  }

  /**
   * Removes a category and its related descriptions from the administrator menu.
   *
   * @param int $id The unique identifier of the category to be removed.
   * @return void
   */
  public static function removeCategory(int $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->delete('administrator_menu', ['id' => (int)$id]);
    $CLICSHOPPING_Db->delete('administrator_menu_description', ['id' => (int)$id]);

    Cache::clear('menu-administrator');
  }

  /**
   * Generates a hierarchical tree of labels from the "administrator_menu" structure.
   *
   * @param int|string $parent_id The ID of the parent category to start building the tree from. Defaults to '0'.
   * @param string $spacing A string used to represent the depth of the tree during output, typically spaces. Defaults to an empty string.
   * @param array|string $exclude A category ID or array of IDs to exclude from the tree. Defaults to an empty string.
   * @param array|string $category_tree_array An existing array to accumulate the hierarchical tree, or an empty string to start a new tree. Defaults to an empty string.
   * @param bool $include_itself If true, includes the parent category itself at the start of the tree. Defaults to false.
   * @return array The hierarchical tree of labels as an array, each element containing 'id' (the category ID) and 'text' (the category label).
   */
  public static function getLabelTree(int|string $parent_id = '0', string $spacing = '', array|string $exclude = '', array|string $category_tree_array = '', bool $include_itself = false): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_AdministratorMenu = Registry::get('AdministratorMenu');

    if (!is_array($category_tree_array)) {
      $category_tree_array = [];
    }

    if ((count($category_tree_array) < 1) && ($exclude != '0')) {
      $category_tree_array[] = [
        'id' => '0',
        'text' => $CLICSHOPPING_AdministratorMenu->getDef('text_top')
      ];
    }

    if ($include_itself) {
      $array = [
        'language_id' => (int)$CLICSHOPPING_Language->getId(),
        'id' => (int)$parent_id,
        'status' => 1
      ];

      $Qcategory = $CLICSHOPPING_Db->get('administrator_menu_description', 'label', $array);

      $category_tree_array[] = [
        'id' => $parent_id,
        'text' => $Qcategory->value('label')
      ];
    }


    $Qcategories = $CLICSHOPPING_Db->get([
      'administrator_menu c',
      'administrator_menu_description cd'
    ], [
      'c.id',
      'cd.label',
      'c.parent_id'
    ], [
      'c.id' => [
      'rel' => 'cd.id'
      ],
      'cd.language_id' => (int)$CLICSHOPPING_Language->getId(),
      'c.parent_id' => (int)$parent_id,
      'status' => 1
    ], [
        'c.sort_order',
        'cd.label'
      ]
    );

    while ($Qcategories->fetch()) {
      if ($exclude != $Qcategories->valueInt('id'))
        $category_tree_array[] = [
          'id' => $Qcategories->valueInt('id'),
          'text' => $spacing . $Qcategories->value('label')
        ];

      $category_tree_array = static::getLabelTree($Qcategories->valueInt('id'), $spacing . '&nbsp;&nbsp;&nbsp;', $exclude, $category_tree_array);
    }

    return $category_tree_array;
  }

  /**
   * Generates a string representation of administrator menu path IDs based on a given menu ID.
   *
   * @param int $id The ID of the administrator menu for which the path IDs are to be generated.
   * @return string A string containing the concatenated path IDs, separated by underscores and formatted with line breaks.
   */
  public static function getGeneratedAdministratorMenuPathIds(int $id)
  {
    $CLICSHOPPING_AdministratorMenu = Registry::get('AdministratorMenu');

    $calculated_category_path_string = '';
    $calculated_category_path = static::getGenerateCategoryPath($id);

    for ($i = 0, $n = count($calculated_category_path); $i < $n; $i++) {
      for ($j = 0, $k = count($calculated_category_path[$i]); $j < $k; $j++) {
        $calculated_category_path_string .= $calculated_category_path[$i][$j]['id'] . '_';
      }
      $calculated_category_path_string = substr($calculated_category_path_string, 0, -1) . '<br />';
    }

    $calculated_category_path_string = substr($calculated_category_path_string, 0, -6);

    if (strlen($calculated_category_path_string) < 1) $calculated_category_path_string = $CLICSHOPPING_AdministratorMenu->getDef('text_top');

    return $calculated_category_path_string;
  }

  /**
   * Generates a hierarchical path of categories starting from a given category ID.
   * This method builds an array that represents the hierarchy based on parent-child relationships.
   *
   * @param int $id The ID of the category to start generating the path.
   * @param array|string $categories_array The array to store the category hierarchy. Defaults to an empty array.
   *                                        If passed as a string, it is converted to an empty array.
   * @param int $index The index to be used for organizing the hierarchy in the resulting array. Defaults to 0.
   *
   * @return array An array containing the hierarchical category path information. Each entry includes
   *               the category ID and its corresponding label.
   */
  public static function getGenerateCategoryPath(int $id, $categories_array = '', int $index = 0): array
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!is_array($categories_array)) {
      $categories_array = [];
    }

    $Qcategory = $CLICSHOPPING_Db->get([
      'administrator_menu c',
      'administrator_menu_description cd'
    ], [
      'cd.label',
      'c.parent_id'
    ], [
        'c.id' => [
          'val' => (int)$id,
          'rel' => 'cd.id'
        ],
        'cd.language_id' => (int)$CLICSHOPPING_Language->getId()
      ]
    );

    $categories_array[$index][] = [
      'id' => (int)$id,
      'text' => $Qcategory->value('label')
    ];

    if ((!\is_null($Qcategory->valueInt('parent_id'))) && ($Qcategory->valueInt('parent_id') != '0')) {
      $categories_array = static::getGenerateCategoryPath($Qcategory->valueInt('parent_id'), $categories_array, $index);
    }

    return $categories_array;
  }

  /**
   * Removes an administrator menu category and its associated image if it is not used elsewhere,
   * and also deletes its descriptions.
   *
   * @param int $id The ID of the administrator menu category to be removed.
   *
   * @return void This method does not return a value.
   */
  public static function getRemoveAdministratorMenuCategory(int $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $QImage = $CLICSHOPPING_Db->prepare('select image
                                            from :table_administrator_menu
                                            where id = :id
                                           ');
    $QImage->bindInt(':id', (int)$id);
    $QImage->execute();

// Controle si l'image est utilise sur une autre categorie
    $QduplicateImage = $CLICSHOPPING_Db->prepare('select count(*) as total
                                                    from :table_administrator_menu
                                                    where image = :image
                                                   ');
    $QduplicateImage->bindValue(':image', $QImage->value('image'));
    $QduplicateImage->execute();

// Controle si l'image est utilise sur une autre categorie
    $QduplicateImageCategories = $CLICSHOPPING_Db->prepare('select count(*) as total
                                                              from :table_administrator_menu
                                                              where image = :image
                                                             ');
    $QduplicateImageCategories->bindValue(':image', $QImage->value('image'));
    $QduplicateImageCategories->execute();

    if (($QduplicateImage->valueInt('total') < 2) && ($QduplicateImageCategories->valueInt('total') == 0)) {
// delete categorie image
      if (is_file($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages() . $QImage->value('image'))) {
        unlink($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages() . $QImage->value('image'));
      }
    }

    $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                              from :table_administrator_menu
                                              where id = :id
                                            ');
    $Qdelete->bindInt(':id', (int)$id);
    $Qdelete->execute();

    $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                      from :table_administrator_menu_description
                                      where id = :id
                                    ');
    $Qdelete->bindInt(':id', (int)$id);
    $Qdelete->execute();
  }

  /**
   * Builds a hierarchical tree structure for the administrator menu categories starting from a specified parent ID.
   * The method generates an array representing the hierarchy with optional inclusion of a specific category itself.
   *
   * @param string|int $parent_id The ID of the parent category to start building the tree from. Defaults to '0'.
   * @param string $spacing A string used for indentation in the tree structure for better visualization. Defaults to an empty string.
   * @param string|int $exclude The ID of a category to exclude from the tree. Defaults to an empty string.
   * @param array|string $category_tree_array The array to store the resulting tree structure. Defaults to an empty array.
   *                                          If passed as a string, it is converted to an empty array.
   * @param bool $include_itself Whether to include the parent category itself in the tree structure. Defaults to false.
   *
   * @return array An array representing the hierarchical tree of categories. Each entry contains the category ID and its label.
   */
  public static function getAdministratorMenuCategoryTree($parent_id = '0', string $spacing = '', $exclude = '', $category_tree_array = '', bool $include_itself = false): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_AdministratorMenu = Registry::get('AdministratorMenu');

    if (!is_array($category_tree_array)) {
      $category_tree_array = [];
    }

    if ((count($category_tree_array) < 1) && ($exclude != '0')) {
      $category_tree_array[] = ['id' => '0', 'text' => $CLICSHOPPING_AdministratorMenu->getDef('text_top')];
    }

    if ($include_itself) {
      $Qcategory = $CLICSHOPPING_Db->prepare('select label
                                                from :table_administrator_menu_description
                                                where language_id = :language_id
                                                and id = :parent_id
                                               ');

      $Qcategory->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
      $Qcategory->bindInt(':parent_id', (int)$parent_id);
      $Qcategory->execute();

      $category_tree_array[] = ['id' => $parent_id, 'text' => $Qcategory->value('label')];
    }

    $Qcategory = $CLICSHOPPING_Db->prepare('select c.id,
                                                       cd.label,
                                                       c.parent_id
                                                from :table_administrator_menu c,
                                                     :table_administrator_menu_description cd
                                                where c.id = cd.id
                                                and cd.language_id = :language_id
                                                and c.parent_id = :parent_id
                                                order by c.sort_order,
                                                         cd.label
                                              ');

    $Qcategory->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $Qcategory->bindInt(':parent_id', (int)$parent_id);
    $Qcategory->execute();

    while ($Qcategory->fetch()) {
      if ($exclude != $Qcategory->valueInt('id')) {
        $category_tree_array[] = [
          'id' => $Qcategory->valueInt('id'),
          'text' => $spacing . $Qcategory->value('label')
        ];
      }

      $category_tree_array = static::getAdministratorMenuCategoryTree($Qcategory->valueInt('id'), $spacing . '&nbsp;&nbsp;&nbsp;', $exclude, $category_tree_array);
    }

    return $category_tree_array;
  }

  /**
   * Calculates the total number of child menu items for a given menu item ID,
   * including all nested child menu items recursively.
   *
   * @param int $id The ID of the parent menu item to count child menu items for.
   *
   * @return int The total count of child menu items including all nested levels.
   */
  public static function getChildsInMenuCount(int $id): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $categories_count = 0;

    $Qcategories = $CLICSHOPPING_Db->prepare('select id
                                                from :table_administrator_menu
                                                where parent_id = :parent_id
                                                ');

    $Qcategories->bindInt(':parent_id', $id);
    $Qcategories->execute();

    while ($Qcategories->fetch() !== false) {
      $categories_count++;

      $categories_count += call_user_func(__METHOD__, $Qcategories->valueInt('id'));
    }

    return $categories_count;
  }

  /*
   * @return array
   */
  /**
   * Retrieves the administrator header menu based on access level and language settings.
   * This method queries the database to fetch menu items with appropriate access permissions and
   * organizes them based on parent-child relationships.
   *
   * @return array An array of menu items with details such as ID, link, parent ID, access level,
   *               sort order, image, B2B menu flag, label, and administrator access permissions.
   */
  public static function getHeaderMenu(): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (isset($_SESSION['admin']['access'])) {
      if ($_SESSION['admin']['access'] == 1) {
        $access_level = 0;
      } elseif ($_SESSION['admin']['access'] == 2) {
        $access_level = 2;
      } elseif ($_SESSION['admin']['access'] == 3) {
        $access_level = 2;
      } else {
        $access_level = 0;
      }
    } else {
      $access_level = 0;
    }

    if ($access_level == 0) {
      $Qmenus = $CLICSHOPPING_Db->prepare('select am.id,
                                                    am.link,
                                                    am.parent_id,
                                                    am.access,
                                                    am.sort_order,
                                                    am.image,
                                                    am.b2b_menu,
                                                    amd.label,
                                                    ad.access
                                              from :table_administrator_menu am  left join :table_administrators ad on ad.access =  am.access,
                                                  :table_administrator_menu_description amd
                                              where am.id = amd.id
                                              and amd.language_id = :language_id
                                              and am.status = 1
                                              order by am.parent_id,
                                                       am.sort_order
                                            ');
      $Qmenus->bindInt(':language_id', $CLICSHOPPING_Language->getId());

    } elseif ($access_level == 2) {
      $Qmenus = $CLICSHOPPING_Db->prepare('select am.id,
                                                  am.link,
                                                  am.parent_id,
                                                  am.access,
                                                  am.sort_order,
                                                  am.image,
                                                  am.b2b_menu,
                                                  amd.label,
                                                  ad.access
                                            from :table_administrator_menu am  left join :table_administrators ad on ad.access =  am.access,
                                                :table_administrator_menu_description amd
                                            where am.id = amd.id
                                            and am.status = 1
                                            and amd.language_id = :language_id
                                            and (am.access = 0 or am.access > 1)
                                            order by am.parent_id,
                                                     am.sort_order
                                          ');

      $Qmenus->bindInt(':language_id', $CLICSHOPPING_Language->getId());

    } elseif ($access_level == 3) {
      $Qmenus = $CLICSHOPPING_Db->prepare('select am.id,
                                                  am.link,
                                                  am.parent_id,
                                                  am.access,
                                                  am.sort_order,
                                                  am.image,
                                                  am.b2b_menu,
                                                  amd.label,
                                                  ad.access
                                            from :table_administrator_menu am  left join :table_administrators ad on ad.access =  am.access,
                                                :table_administrator_menu_description amd
                                            where am.id = amd.id
                                            and amd.language_id = :language_id
                                            and am.status = 1
                                            and (am.access = 0 and am.access > 2)
                                            order by am.parent_id,
                                                     am.sort_order
                                          ');

      $Qmenus->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    }

    $Qmenus->setCache('menu-administrator');
    $Qmenus->execute();

    return $Qmenus->fetchAll();
  }

  /**
   * Retrieves all child category IDs recursively for a given category ID.
   * This method collects the IDs of all direct and indirect child categories
   * and stores them in the provided array.
   *
   * @param string $category_id The ID of the category to retrieve child IDs for.
   * @param array &$array A reference to an array where all child category IDs will be stored.
   *                       Defaults to an empty array.
   *
   * @return array An array containing all child category IDs, including both direct and indirect children.
   */
  public static function getChildren(string $category_id, array &$array = []): array
  {
    foreach (static::$data as $parent => $categories) {
      if ($parent == $category_id) {
        foreach ($categories as $id => $info) {
          $array[] = $id;
          self::getChildren($id, $array);
        }
      }
    }

    return $array;
  }
}
