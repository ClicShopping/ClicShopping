<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\DefineLanguage\Sites\ClicShoppingAdmin\Pages\Home\Actions\DefineLanguage;

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Save extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('DefineLanguage');
  }

  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_GET['search']) && !empty($_GET['search'])) {
      $search = HTML::sanitize($_GET['search']);
    } else {
      $search = '';
    }

    $languages = $CLICSHOPPING_Language->getLanguages();

    if (isset($_GET['ContentGroup'])) {
      $content_group = HTML::sanitize($_GET['ContentGroup']);
    } else {
      $content_group = '';
    }

    if (isset($_POST['definition_value'])) {
      $definition_values = $_POST['definition_value'];
    } else {
      $definition_values = '';
    }

    if (isset($_POST['new_definition_key']) && !empty($_POST['new_definition_key']) && isset($_POST['new_definition_value'])) {
      $new_definition_key = HTML::sanitize($_POST['new_definition_key']);
    } else {
      $new_definition_key = '';
    }

    if (isset($search)) {
// delete
      if (isset($_POST['delete']) && \is_array($_POST['delete'])) {
        foreach ($_POST['delete'] as $key => $value) {
          $sql_array = [
            'definition_key' => HTML::sanitize($key),
            'content_group' => $content_group
          ];

          $this->app->db->delete(':table_languages_definitions', $sql_array);
        }
      }

// update only
      if (\is_array($definition_values)) {
        foreach ($definition_values as $definition_key => $language_definition) {
          if (\is_array($language_definition)) {
            foreach ($language_definition as $language_id => $definition_value) {
              $sql_data_array = [
                'content_group' => $content_group,
                'definition_key' => $definition_key,
                'languages_id' => $language_id,
                'definition_value' => $definition_value
              ];

              $where_array = [
                'content_group' => $content_group,
                'definition_key' => $definition_key,
                'languages_id' => $language_id
              ];

              $Qdefinitions = $this->app->db->get(':table_languages_definitions', ['count(*) as total'], $where_array);

              if ($Qdefinitions->valueInt('total') == 0) {
                $this->app->db->save(':table_languages_definitions', $sql_data_array);
              } else {
                $this->app->db->save(':table_languages_definitions', $sql_data_array, $where_array);
              }
            }
          }
        }
      }

      // add new_definition_key
      if (isset($new_definition_key) && \is_array($_POST['new_definition_value'])) {
        foreach ($_POST['new_definition_value'] as $key => $value) {
          $sql_data_array = [
            'content_group' => $content_group,
            'definition_key' => $new_definition_key,
            'languages_id' => $key,
            'definition_value' => $value
          ];

          $where_array = [
            'content_group' => $content_group,
            'definition_key' => $new_definition_key,
            'languages_id' => $key
          ];

          $Qdefinitions = $this->app->db->get(':table_languages_definitions', ['count(*) as total'], $where_array);

          if ($Qdefinitions->valueInt('total') == 0) {
            if (!empty($new_definition_key)) {
              $this->app->db->save(':table_languages_definitions', $sql_data_array);
            }
          } else {
            $CLICSHOPPING_MessageStack->add($this->app->getDef('ms_error_db_save', ['definition_key' => $new_definition_key]), 'error');
          }
        }
      }
    } else {
// reset all
      $new_definition_key_error = false;

      if (isset($new_definition_key) && \is_array($_POST['new_definition_value'])) {
        foreach ($_POST['new_definition_value'] as $key => $value) {
          if (!isset($definition_values[$new_definition_key][$key])) {
            $new_definition_values[$new_definition_key][$key] = $value;
          } else {
            $CLICSHOPPING_MessageStack->add($this->app->getDef('ms_error_db_save', ['definition_key' => $new_definition_key]), 'error');
            $new_definition_key_error = true;
          }
        }
      }

      if (!$new_definition_key_error && isset($new_definition_values)) {
        $definition_values = array_merge($definition_values, $new_definition_values);
      }

      $where_array = ['content_group' => $content_group];

      $this->app->db->delete(':table_languages_definitions', $where_array);

      if (\is_array($definition_values)) {
        foreach ($definition_values as $definition_key => $language_definition) {
          foreach ($language_definition as $language_id => $definition_value) {
            $sql_data_array = [
              'content_group' => $content_group,
              'definition_key' => $definition_key,
              'languages_id' => $language_id,
              'definition_value' => $definition_value
            ];

            $this->app->db->save(':table_languages_definitions', $sql_data_array);
          }
        }
      }
    }

// save to files
    $groups = explode('-', $content_group);
    $path_to_file = '/';

    for (($groups[0] == 'Apps' ? $i = 3 : $i = 1), $n = \count($groups) - 1; $i < $n; $i++) {
      $path_to_file .= $groups[$i] . '/';
    }

//      $file_name = $groups[\count($groups) - 1] . '.txt';

    $path_name = str_replace('-', '/', substr($content_group, ($groups[0] != 'Apps' ? \strlen($groups[0]) : \strlen($groups[0] . '-' . $groups[1] . '-' . $groups[2])))) . '.txt';

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $language_dir = CLICSHOPPING::getConfig('dir_root', ($groups[0] == 'Apps' ? 'Shop' : $groups[0])) . ($groups[0] == 'Apps' ? 'Core/ClicShopping/Apps/' . $groups[1] . '/' . $groups[2] . '/' : 'Core/') . 'languages/' . $languages[$i]['directory'];

      if (!is_file($language_dir . $path_name)) {
        if (!is_dir($language_dir . $path_to_file)) {
          if (@!mkdir($concurrentDirectory = $language_dir . $path_to_file, 0777, true) && !is_dir($concurrentDirectory)) {
            $CLICSHOPPING_MessageStack->add($this->app->getDef('ms_error_create', ['pathname' => $language_dir . $path_to_file]), 'error');
          }
        }
      } else {
        if (FileSystem::isWritable($language_dir . $path_name) === true) {
          unlink($language_dir . $path_name);
        } else {
          $CLICSHOPPING_MessageStack->add($this->app->getDef('error_file_not_writeable', ['pathname' => $language_dir . $path_name]), 'warning');
        }
      }

      $Qdefinitions = $this->app->db->prepare('select definition_key,
                                                         definition_value
                                                 from :table_languages_definitions
                                                 where content_group = :content_group
                                                 and languages_id = :languages_id
                                                 order by definition_key
                                                ');

      $Qdefinitions->bindValue(':content_group', $content_group);
      $Qdefinitions->bindInt(':languages_id', $languages[$i]['id']);
      $Qdefinitions->execute();

      if ($Qdefinitions->fetch() !== false) {
        do {
          $data = $Qdefinitions->value('definition_key') . ' = ' . $Qdefinitions->value('definition_value');

          if (is_file($language_dir . $path_name) && FileSystem::isWritable($language_dir . $path_name)) {
            file_put_contents($language_dir . $path_name, $data . PHP_EOL, FILE_APPEND | LOCK_EX);
          } else {
            // $CLICSHOPPING_MessageStack->add($this->app->getDef('error_file_not_writeable', ['pathname' => $language_dir . $path_name]), 'warning');
          }
        } while ($Qdefinitions->fetch());
      }
    }

    Cache::clear('languages-defs-' . $content_group . '-lang');

    $this->app->redirect('ContentGroup=' . $content_group . (isset($search) ? '&search=' . $search : ''));
  }
}