<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Sites\ClicShoppingAdmin\Pages\Home\Actions\Categories;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function count;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function execute()
  {
    $this->app = Registry::get('Categories');

    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

    if (isset($_GET['cID'])) {
      $categories_id = HTML::sanitize($_GET['cID']);

      if (isset($_POST['current_category_id'])) {
        $categories_id = HTML::sanitize($_POST['current_category_id']);
      }

      if (isset($_POST['sort_order'])) {
        $sort_order = HTML::sanitize($_POST['sort_order']);
      }

      if (isset($_POST['move_to_category_id'])) {
        $new_parent_id = HTML::sanitize($_POST['move_to_category_id']);
      }

      $sql_data_array = [
        'parent_id' => (int)$new_parent_id,
        'sort_order' => (int)$sort_order
      ];

      $update_sql_data = ['last_modified' => 'now()'];

      $sql_data_array = array_merge($sql_data_array, $update_sql_data);

      $this->app->db->save('categories', $sql_data_array, ['categories_id' => (int)$categories_id]);

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $categories_name_array = HTML::sanitize($_POST['categories_name']);
        $categories_description_array = $_POST['categories_description'];

        if (!empty($_POST['categories_head_title_tag'])) {
          if (is_array($_POST['categories_head_title_tag'])) {
            $categories_seo_title_array = [];
            foreach ($_POST['categories_head_title_tag'] as $key => $value) {
              // Assurez-vous que chaque valeur est une chaîne avant d'appliquer strip_tags et HTML::sanitize
              $categories_seo_title_array[$key] = HTML::sanitize(strip_tags($value));
            }
          } else {
            // Si ce n'est pas un tableau, traiter comme une chaîne unique
            $categories_seo_title_array = HTML::sanitize(strip_tags($_POST['categories_head_title_tag']));
          }
        } else {
          $categories_seo_title_array = HTML::sanitize($_POST['categories_head_title_tag']);
        }


        if (!empty($_POST['categories_head_desc_tag'])) {
          if (is_array($_POST['categories_head_desc_tag'])) {
            $categories_seo_description_array = [];
            foreach ($_POST['categories_head_desc_tag'] as $key => $value) {
              // Assurez-vous que chaque valeur est une chaîne avant d'appliquer strip_tags et HTML::sanitize
              $categories_seo_description_array[$key] = HTML::sanitize(strip_tags($value));
            }
          } else {
            // Si ce n'est pas un tableau, traiter comme une chaîne unique
            $categories_seo_description_array = HTML::sanitize(strip_tags($_POST['categories_head_desc_tag']));
          }
        } else {
          $categories_seo_description_array = HTML::sanitize($_POST['categories_head_desc_tag']);
        }


        if (!empty($_POST['categories_head_keywords_tag'])) {
          if (is_array($_POST['categories_head_keywords_tag'])) {
            $categories_seo_keyword_array = [];
            foreach ($_POST['categories_head_keywords_tag'] as $key => $value) {
              // Assurez-vous que chaque valeur est une chaîne avant d'appliquer strip_tags et HTML::sanitize
              $categories_seo_keyword_array[$key] = HTML::sanitize(strip_tags($value));
            }
          } else {
            // Si ce n'est pas un tableau, traiter comme une chaîne unique
            $categories_seo_keyword_array = HTML::sanitize(strip_tags($_POST['categories_head_keywords_tag']));
          }
        } else {
          $categories_seo_keyword_array = HTML::sanitize($_POST['categories_head_keywords_tag']);
        }

        $categories_seo_url_array = HTML::sanitize($_POST['categories_seo_url']);

        $sql_data_array = [
          'categories_name' => $categories_name_array[$language_id],
          'categories_description' => $categories_description_array[$language_id],
          'categories_seo_url' => $categories_seo_url_array[$language_id],
          'categories_head_title_tag' => $categories_seo_title_array[$language_id],
          'categories_head_desc_tag' => $categories_seo_description_array[$language_id],
          'categories_head_keywords_tag' => $categories_seo_keyword_array[$language_id]
        ];

        $update_sql_data = [
          'categories_id' => (int)$categories_id,
          'language_id' => (int)$language_id
        ];

        $this->app->db->save('categories_description', $sql_data_array, $update_sql_data);
      }

      if (isset($_POST['delete_image'])) {
        $sql_data_array = ['categories_image' => ''];
        $this->app->db->save('categories', $sql_data_array, ['categories_id' => (int)$categories_id]);
      } elseif (isset($_POST['categories_image']) && !\is_null($_POST['categories_image']) && ($_POST['categories_image'] != 'none') && !empty($_POST['categories_image'])) {
        $categories_image = $_POST['categories_image'];

        if (!empty($categories_image) && !\is_null($categories_image)) {
          $categories_image = $CLICSHOPPING_Wysiwyg::getWysiwygImageAlone($categories_image);
        }

        $sql_data_array = ['categories_image' => $categories_image];
        $this->app->db->save('categories', $sql_data_array, ['categories_id' => (int)$categories_id]);
      }

      Cache::clear('categories');
      Cache::clear('products-also_purchased');
      Cache::clear('products_related');
      Cache::clear('products_cross_sell');
      Cache::clear('upcoming');

      $CLICSHOPPING_Hooks->call('Categories', 'Update');

      $this->app->redirect('Categories&cPath=' . $new_parent_id . '&cID=' . $categories_id);
    } else {
      $this->app->redirect('Categories');
    }
  }
}