<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ActionsRecorder\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;
use function count;
use function is_array;
use function is_null;
use function strlen;

class ActionsRecorder
{
  protected int $category_id;
  protected int $language_id;

  /**
   * Builds and returns the category path string based on the current context or a given category ID.
   *
   * @param string $current_category_id The ID of the current category. If empty, the method will use the category path already stored in the system. Default is an empty string.
   * @return string The constructed category path string in the format 'cPath=<id_sequence>'.
   */
  public static function getPath($current_category_id = ''): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Category = Registry::get('Category');

    $cPath_array = $CLICSHOPPING_Category->getPathArray();

    if ($current_category_id == '') {
      $cPath_new = implode('_', $cPath_array);
    } else {
      if (count($cPath_array) == 0) {
        $cPath_new = $current_category_id;
      } else {
        $cPath_new = '';

        $Qlast = $CLICSHOPPING_Db->get('actions_recorder', 'parent_id', ['id' => (int)$cPath_array[(count($cPath_array) - 1)]]);

        $Qcurrent = $CLICSHOPPING_Db->get('actions_recorder', 'parent_id', ['id' => (int)$current_category_id]);

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
   * Retrieves the label of an actions recorder based on the provided ID and language ID.
   *
   * @param int $id The ID of the actions recorder.
   * @param int $language_id The ID of the language. If not provided, the current language ID will be used.
   * @return string The label associated with the actions recorder.
   */
  public static function getActionsRecorderLabel(int $id, int $language_id): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!$language_id) {
      $language_id = $CLICSHOPPING_Language->getId();
    }

    $Qcategory = Registry::get('Db')->get('actions_recorder_description', 'label', ['id' => (int)$id, 'language_id' => (int)$language_id]);

    return $Qcategory->value('label');
  }

  /**
   * Removes a category from the database, including its associated descriptions, and clears the administrator menu cache.
   *
   * @param int $id The ID of the category to remove.
   * @return void
   */
  public static function removeCategory(int $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->delete('actions_recorder', ['id' => (int)$id]);
    $CLICSHOPPING_Db->delete('actions_recorder_description', ['id' => (int)$id]);

    Cache::clear('menu-administrator');
  }

  /**
   * Builds and retrieves a hierarchical label tree structure from the database.
   *
   * @param int|string $parent_id The ID of the parent category from which the label tree starts. Default is '0'.
   * @param string $spacing A string used to visually represent depth levels in the label tree. Default is an empty string.
   * @param string $exclude The ID of a category to exclude from the label tree. Default is an empty string.
   * @param array|string $category_tree_array Array to hold the label tree structure. If not provided, it is initialized as an empty array. Default is an empty string.
   * @param bool $include_itself Whether to include the current category itself in the label tree. Default is false.
   *
   * @return array The hierarchical array representation of the label tree with category IDs and labels.
   */
  public static function getLabelTree($parent_id = '0', $spacing = '', $exclude = '', $category_tree_array = '', $include_itself = false)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_ActionsRecorder = Registry::get('ActionsRecorder');

    if (!is_array($category_tree_array)) {
      $category_tree_array = [];
    }

    if ((count($category_tree_array) < 1) && ($exclude != '0')) {
      $category_tree_array = [
        'id' => '0',
        'text' => $CLICSHOPPING_ActionsRecorder->getDef('text_top')];
    }

    if ($include_itself) {
      $Qcategory = $CLICSHOPPING_Db->get('actions_recorder_description', 'label', [
          'language_id' => (int)$CLICSHOPPING_Language->getId(),
          'id' => (int)$parent_id
        ]
      );

      $category_tree_array = [
        'id' => $parent_id,
        'text' => $Qcategory->value('label')
      ];
    }

    $Qcategories = $CLICSHOPPING_Db->get([
      'actions_recorder c',
      'actions_recorder_description cd'
    ], [
      'c.id',
      'cd.label',
      'c.parent_id'
    ], [
      'c.id' => [
        'rel' => 'cd.id'
      ],
      'cd.language_id' => (int)$CLICSHOPPING_Language->getId(),
      'c.parent_id' => (int)$parent_id
    ], [
        'c.sort_order',
        'cd.label'
      ]
    );

    while ($Qcategories->fetch()) {
      if ($exclude != $Qcategories->valueInt('id')) {
        $category_tree_array = [
          'id' => $Qcategories->valueInt('id'),
          'text' => $spacing . $Qcategories->value('label')];
      }

      $category_tree_array = static::getLabelTree($Qcategories->valueInt('id'), $spacing . '&nbsp;&nbsp;&nbsp;', $exclude, $category_tree_array);
    }

    return $category_tree_array;
  }

  /**
   * Generates a path string of category IDs related to the specified actions recorder ID.
   *
   * @param int $id The actions recorder ID for which the category path string is generated.
   * @return string A string representing the generated category path of IDs.
   */
  public static function getGeneratedActionsRecorderPathIds(int $id)
  {
    $CLICSHOPPING_ActionsRecorder = Registry::get('ActionsRecorder');

    $calculated_category_path_string = '';
    $calculated_category_path = static::getGenerateCategoryPath($id);

    for ($i = 0, $n = count($calculated_category_path); $i < $n; $i++) {
      for ($j = 0, $k = count($calculated_category_path[$i]); $j < $k; $j++) {
        $calculated_category_path_string .= $calculated_category_path[$i][$j]['id'] . '_';
      }
      $calculated_category_path_string = substr($calculated_category_path_string, 0, -1) . '<br />';
    }

    $calculated_category_path_string = substr($calculated_category_path_string, 0, -6);

    if (strlen($calculated_category_path_string) < 1) {
      $calculated_category_path_string = $CLICSHOPPING_ActionsRecorder->getDef('text_top');
    }

    return $calculated_category_path_string;
  }

  /**
   * Generates a hierarchical path for a given category ID.
   *
   * @param int $id The ID of the category for which the path is generated.
   * @param array|string $categories_array An optional array to store categories or an empty string. Defaults to an empty array if not provided.
   * @param int $index The level or index in the categories array. Defaults to 0.
   * @return array Returns an array representing the category path with hierarchical structure.
   */
  public static function getGenerateCategoryPath(int $id, $categories_array = '', $index = 0): array
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!is_array($categories_array)) {
      $categories_array = [];
    }

    $categories_array[$index][] = [
      'id' => (int)$id,
      'text' => $Qcategory->value('label')
    ];

    $Qcategory = $CLICSHOPPING_Db->get([
      'actions_recorder c',
      'actions_recorder_description cd'
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

    return $categories_array;
  }

  /**
   * Removes a category record and its related image from the actions recorder table
   * and description table in the database. If the associated image is not used
   * by any other categories, it also deletes the image file from the file system.
   *
   * @param int $id The ID of the category to be removed from the actions recorder.
   *
   * @return void
   */
  public static function getRemoveActionsRecorderCategory(int $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $QImage = $CLICSHOPPING_Db->prepare('select image
                                          from :table_actions_recorder
                                          where id = :id
                                         ');
    $QImage->bindInt(':id', $id);
    $QImage->execute();

// Controle si l'image est utilise sur une autre categorie
    $QduplicateImage = $CLICSHOPPING_Db->prepare('select count(*) as total
                                                    from :table_actions_recorder
                                                    where image = :image
                                                   ');
    $QduplicateImage->bindValue(':image', $QImage->value('image'));
    $QduplicateImage->execute();

// Controle si l'image est utilise sur une autre categorie
    $QduplicateImageCategories = $CLICSHOPPING_Db->prepare('select count(*) as total
                                                              from :table_actions_recorder
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
                                            from :table_actions_recorder
                                            where id = :id
                                          ');
    $Qdelete->bindInt(':id', $id);
    $Qdelete->execute();

    $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                            from :table_actions_recorder_description
                                            where id = :id
                                          ');
    $Qdelete->bindInt(':id', $id);
    $Qdelete->execute();
  }

  /**
   * Retrieves a hierarchical tree structure of action recorder categories.
   * This allows for generating a nested list of categories, optionally including
   * the specified parent category itself and excluding a specific category.
   *
   * @param string|int $parent_id The ID of the parent category to start building the tree from. Defaults to '0'.
   * @param string $spacing A string used for spacing the labels to represent hierarchy. Defaults to an empty string.
   * @param string $exclude The ID of a category to exclude from the tree. Defaults to an empty string.
   * @param array $category_tree_array The existing array structure of the category tree. Defaults to an empty array.
   * @param bool $include_itself Indicates whether to include the specified parent category itself in the tree. Defaults to false.
   *
   * @return array An array representing the hierarchical tree structure of action recorder categories.
   */
  public static function getActionsRecorderCategoryTree($parent_id = '0', $spacing = '', $exclude = '', $category_tree_array = '', bool $include_itself = false)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_ActionsRecorder = Registry::get('ActionsRecorder');

    if (!is_array($category_tree_array)) {
      $category_tree_array = [];
    }

    if ((count($category_tree_array) < 1) && ($exclude != '0')) {
      $category_tree_array[] = [
        'id' => '0',
        'text' => $CLICSHOPPING_ActionsRecorder->getDef('text_top')
      ];
    }

    if ($include_itself) {
      $Qcategory = $CLICSHOPPING_Db->prepare('select label
                                                from :table_actions_recorder_description
                                                where language_id = :language_id
                                                and id = :parent_id
                                               ');

      $Qcategory->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
      $Qcategory->bindInt(':parent_id', (int)$parent_id);
      $Qcategory->execute();

      $category_tree_array[] = [
        'id' => $parent_id,
        'text' => $Qcategory->value('label')
      ];
    }

    $Qcategory = $CLICSHOPPING_Db->prepare('select c.id,
                                                       cd.label,
                                                       c.parent_id
                                                from :table_actions_recorder c,
                                                     :table_actions_recorder_description cd
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

      $category_tree_array = static::getActionsRecorderCategoryTree($Qcategory->valueInt('id'), $spacing . '&nbsp;&nbsp;&nbsp;', $exclude, $category_tree_array);
    }

    return $category_tree_array;
  }

  /**
   * Retrieves the count of all child categories associated with a given parent category ID.
   * The method performs a recursive search to count all descendants within the menu structure.
   *
   * @param int $id The ID of the parent category for which child categories will be counted.
   *
   * @return int The total number of child categories, including all nested child categories.
   */
  public static function getChildsInMenuCount(int $id): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $categories_count = 0;

    $Qcategories = $CLICSHOPPING_Db->prepare('select id
                                                from :table_actions_recorder
                                                where parent_id = :parent_id
                                                ');

    $Qcategories->bindInt(':parent_id', $id);
    $Qcategories->execute();

    while ($Qcategories->fetch() !== false) {
      $categories_count++;

      $categories_count += \call_user_func(__METHOD__, $Qcategories->valueInt('id'));
    }

    return $categories_count;
  }

  /**
   * Retrieves and instantiates a class based on the provided file name, if the class exists.
   * It also sets the instantiated class as a global variable with the class name as the key.
   *
   * @param string $file The file name from which the class name will be derived.
   *
   * @return object|null The instance of the class if it exists, or null if the class does not exist.
   */
  public function getClass(string $file)
  {
    $class = substr($file, 0, strrpos($file, '.'));

    if (class_exists($class) && !is_null($class)) {
      $GLOBALS[$class] = new $class;

      return $GLOBALS[$class];
    }
  }

  /**
   * Retrieves a module class instance from the global scope.
   *
   * @param string $module The name of the module whose class instance is to be retrieved.
   *
   * @return mixed The module class instance if it exists in the global scope.
   */
  public function getClassModule($module)
  {
    return $GLOBALS[$module];
  }
}
