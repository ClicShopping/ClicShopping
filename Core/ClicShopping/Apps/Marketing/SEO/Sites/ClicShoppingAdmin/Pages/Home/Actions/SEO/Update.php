<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Sites\ClicShoppingAdmin\Pages\Home\Actions\SEO;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_SEO = Registry::get('SEO');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Language = Registry::get('Language');

    $languages = $CLICSHOPPING_Language->getLanguages();

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $language_id = $languages[$i]['id'];

      $seo_defaut_language_title_h1 = HTML::sanitize($_POST['seo_defaut_language_title_h1_']);
      $seo_defaut_language_title = HTML::sanitize($_POST['seo_defaut_language_title_']);
      $seo_defaut_language_keywords = HTML::sanitize($_POST['seo_defaut_language_keywords_']);
      $seo_defaut_language_description = HTML::sanitize($_POST['seo_defaut_language_description_']);
      $seo_defaut_language_footer = HTML::sanitize($_POST['seo_defaut_language_footer_']);

      $seo_language_products_info_title = HTML::sanitize($_POST['seo_language_products_info_title_']);
      $seo_language_products_info_keywords = HTML::sanitize($_POST['seo_language_products_info_keywords_']);
      $seo_language_products_info_description = HTML::sanitize($_POST['seo_language_products_info_description_']);
      $seo_language_products_new_title = HTML::sanitize($_POST['seo_language_products_new_title_']);
      $seo_language_products_new_keywords = HTML::sanitize($_POST['seo_language_products_new_keywords_']);
      $seo_language_products_new_description = HTML::sanitize($_POST['seo_language_products_new_description_']);

      $seo_language_special_title = HTML::sanitize($_POST['seo_language_special_title_']);
      $seo_language_special_keywords = HTML::sanitize($_POST['seo_language_special_keywords_']);
      $seo_language_special_description = HTML::sanitize($_POST['seo_language_special_description_']);
      $seo_language_reviews_title = HTML::sanitize($_POST['seo_language_reviews_title_']);
      $seo_language_reviews_keywords = HTML::sanitize($_POST['seo_language_reviews_keywords_']);
      $seo_language_reviews_description = HTML::sanitize($_POST['seo_language_reviews_description_']);
      $seo_language_favorites_title = HTML::sanitize($_POST['seo_language_favorites_title_']);
      $seo_language_favorites_keywords = HTML::sanitize($_POST['seo_language_favorites_keywords_']);
      $seo_language_favorites_description = HTML::sanitize($_POST['seo_language_favorites_description_']);
      $seo_language_featured_title = HTML::sanitize($_POST['seo_language_featured_title_']);
      $seo_language_featured_keywords = HTML::sanitize($_POST['seo_language_featured_keywords_']);
      $seo_language_featured_description = HTML::sanitize($_POST['seo_language_featured_description_']);
      $seo_language_recommendations_title = HTML::sanitize($_POST['seo_recommendations_language_title_']);
      $seo_language_recommendations_description = HTML::sanitize($_POST['seo_recommendations_language_description_']);
      $seo_language_recommendations_keywords = HTML::sanitize($_POST['seo_recommendations_language_keywords_']);

      $sql_data_array_pages_description = [
        'seo_defaut_language_title' => $seo_defaut_language_title[$language_id],
        'seo_defaut_language_keywords' => $seo_defaut_language_keywords[$language_id],
        'seo_defaut_language_description' => $seo_defaut_language_description[$language_id],
        'seo_defaut_language_footer' => $seo_defaut_language_footer[$language_id],
        'seo_defaut_language_title_h1' => $seo_defaut_language_title_h1[$language_id],
        'seo_language_products_info_title' => $seo_language_products_info_title[$language_id],
        'seo_language_products_info_keywords' => $seo_language_products_info_keywords[$language_id],
        'seo_language_products_info_description' => $seo_language_products_info_description[$language_id],
        'seo_language_products_new_title' => $seo_language_products_new_title[$language_id],
        'seo_language_products_new_keywords' => $seo_language_products_new_keywords[$language_id],
        'seo_language_products_new_description' => $seo_language_products_new_description[$language_id],
        'seo_language_special_title' => $seo_language_special_title[$language_id],
        'seo_language_special_keywords' => $seo_language_special_keywords[$language_id],
        'seo_language_special_description' => $seo_language_special_description[$language_id],
        'seo_language_reviews_title' => $seo_language_reviews_title[$language_id],
        'seo_language_reviews_keywords' => $seo_language_reviews_keywords[$language_id],
        'seo_language_reviews_description' => $seo_language_reviews_description[$language_id],
        'seo_language_favorites_title' => $seo_language_favorites_title[$language_id],
        'seo_language_favorites_keywords' => $seo_language_favorites_keywords[$language_id],
        'seo_language_favorites_description' => $seo_language_favorites_description[$language_id],
        'seo_language_featured_title' => $seo_language_featured_title[$language_id],
        'seo_language_featured_keywords' => $seo_language_featured_keywords[$language_id],
        'seo_language_featured_description' => $seo_language_featured_description[$language_id],
        'seo_language_recommendations_title' => $seo_language_recommendations_title[$language_id],
        'seo_language_recommendations_description' => $seo_language_recommendations_description[$language_id],
        'seo_language_recommendations_keywords' => $seo_language_recommendations_keywords[$language_id]
      ];

      $update_sql = [
        'seo_id' => 1,
        'language_id' => (int)$language_id
      ];

      $CLICSHOPPING_SEO->db->save('seo', $sql_data_array_pages_description, $update_sql);

      $CLICSHOPPING_Hooks->call('SEO', 'Update');
    }

    $CLICSHOPPING_SEO->redirect('SEO&SEO');
  }
}