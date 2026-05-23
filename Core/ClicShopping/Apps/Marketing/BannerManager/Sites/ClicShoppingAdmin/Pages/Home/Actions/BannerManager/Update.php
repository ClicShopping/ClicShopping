<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager\Sites\ClicShoppingAdmin\Pages\Home\Actions\BannerManager;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_BannerManager = Registry::get('BannerManager');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['banners_id'])) $banners_id = HTML::sanitize($_POST['banners_id']);

    $banners_title = HTML::sanitize($_POST['banners_title']);
    $banners_title_admin = HTML::sanitize($_POST['banners_title_admin']);

    $banners_url = HTML::sanitize($_POST['banners_url']);

    $new_banners_group = HTML::sanitize($_POST['new_banners_group']);
    $banners_group = (empty($new_banners_group)) ? HTML::sanitize($_POST['banners_group']) : $new_banners_group;

    $banners_target = $_POST['banners_target'];
    $banners_html_text = $_POST['banners_html_text'];

    $customers_group_id = HTML::sanitize($_POST['customers_group_id']);

    $banners_image_local = $_POST['banners_image_local'];
    $banners_image_show = $_POST['banners_image_show'];

    $expires_date = HTML::sanitize($_POST['expires_date']);
    $expires_impressions = HTML::sanitize($_POST['expires_impressions']);
    $date_scheduled = HTML::sanitize($_POST['date_scheduled']);

    $language_id = HTML::sanitize($_POST['languages_id']);
    $banners_theme = (empty($_POST['banners_theme'])) ? NULL : HTML::sanitize($_POST['banners_theme']);

    $banner_error = false;

    if (empty($banners_title)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_BannerManager->getDef('error_banner_title_required'), 'error');
      $banner_error = true;
    }

// Insertion de l'image de la banniere
    if (!empty($banners_image_local) && !\is_null($_POST['banners_image_local'])) {
      $banners_image_local = $CLICSHOPPING_Wysiwyg::getWysiwygImageAlone($banners_image_local);
    } else {
      if (!\is_null($banners_image_show)) {
        $banners_image_local = $banners_image_show;
      } else {
        $banners_image_local = '';
      }
    }

    if (isset($_POST['delete_image'])) {
      $banners_image_local = '';
    }

    if (empty($banners_group)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_BannerManager->getDef('error_banner_group_required'), 'error');
      $banner_error = true;
    }

    if ($banner_error === false) {
      $sql_data_array = [
        'banners_title' => $banners_title,
        'banners_url' => $banners_url,
        'banners_group' => $banners_group,
        'banners_target' => $banners_target,
        'languages_id' => (int)$language_id,
        'banners_html_text' => $banners_html_text,
        'expires_date' => null,
        'expires_impressions' => 0,
        'date_scheduled' => null,
        'banners_title_admin' => $banners_title_admin,
        'customers_group_id' => (int)$customers_group_id,
        'banners_theme' => $banners_theme
      ];

      $insert_image_sql_data = ['banners_image' => $banners_image_local];
      $sql_data_array = array_merge($sql_data_array, $insert_image_sql_data);

      $CLICSHOPPING_BannerManager->db->save('banners', $sql_data_array, ['banners_id' => (int)$banners_id]);

      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_BannerManager->getDef('success_banner_updated'), 'success');

      if (!empty($expires_date)) {
        $expires_date = substr($expires_date, 0, 4) . substr($expires_date, 5, 2) . substr($expires_date, 8, 2);

        $CLICSHOPPING_BannerManager->db->save('banners', [
          'expires_date' => $expires_date,
          'expires_impressions' => 'null'
        ],
          ['banners_id' => (int)$banners_id]
        );

      } elseif (!empty($expires_impressions)) {

        $CLICSHOPPING_BannerManager->db->save('banners', [
          'expires_date' => 'null',
          'expires_impressions' => $expires_impressions
        ],
          ['banners_id' => (int)$banners_id]
        );
      }

// date debut
      if (!empty($date_scheduled)) {
        $date_scheduled = substr($date_scheduled, 0, 4) . substr($date_scheduled, 5, 2) . substr($date_scheduled, 8, 2);


        $CLICSHOPPING_BannerManager->db->save('banners', [
          'status' => '0',
          'date_scheduled' => $date_scheduled
        ],
          ['banners_id' => (int)$banners_id]
        );
      }

      $CLICSHOPPING_Hooks->call('Update', 'BannerManager');

      $CLICSHOPPING_BannerManager->redirect('BannerManager&page=' . $page);
    }
  }
}