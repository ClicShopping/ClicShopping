<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager\Sites\ClicShoppingAdmin\Pages\Home\Actions\BannerManager;

use ClicShopping\OM\Registry;

class CopyTo extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_BannerManager = Registry::get('BannerManager');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['bID'])) {

      $QbannersCopy = $CLICSHOPPING_BannerManager->db->prepare('select banners_title,
                                                                 banners_url,
                                                                 banners_image,
                                                                 banners_group,
                                                                 banners_target,
                                                                 banners_html_text,
                                                                 expires_impressions,
                                                                 date_format(date_scheduled, "%Y-%m-%d") as date_scheduled,
                                                                 date_format(expires_date, "%Y-%m-%d") as expires_date,
                                                                 date_added,
                                                                 date_status_change,
                                                                 status,
                                                                 customers_group_id,
                                                                 languages_id,
                                                                 banners_title_admin,
                                                                 banners_theme
                                                          from :table_banners
                                                          where banners_id = :banners_id
                                                          ');
      $QbannersCopy->bindInt(':banners_id', (int)$_GET['bID']);
      $QbannersCopy->execute();

      $CLICSHOPPING_BannerManager->db->save('banners', [
          'banners_title' => $QbannersCopy->value('banners_title'),
          'banners_url' => $QbannersCopy->value('banners_url'),
          'banners_image' => $QbannersCopy->value('banners_image'),
          'banners_group' => $QbannersCopy->value('banners_group'),
          'banners_target' => $QbannersCopy->value('banners_target'),
          'banners_html_text' => $QbannersCopy->value('banners_html_text'),
          'expires_impressions' => $QbannersCopy->value('expires_impressions'),
          'date_scheduled' => (empty($QbannersCopy->value('date_scheduled')) ? 'null' : $QbannersCopy->value('date_scheduled')),
          'expires_date' => (empty($QbannersCopy->value('expires_date')) ? 'null' : $QbannersCopy->value('expires_date')),
          'date_added' => 'now()',
          'date_status_change' => (empty($QbannersCopy->value('date_status_change')) ? 'null' : $QbannersCopy->value('date_status_change')),
          'status' => 0,
          'customers_group_id' => (int)$QbannersCopy->valueInt('customers_group_id'),
          'languages_id' => (int)$QbannersCopy->valueInt('languages_id'),
          'banners_title_admin' => $QbannersCopy->value('banners_title_admin'),
          'banners_theme' => $QbannersCopy->value('banners_theme')
        ]
      );
    }


    $CLICSHOPPING_Hooks->call('CopyTo', 'BannerManager');

    $CLICSHOPPING_BannerManager->redirect('BannerManager&page=' . $page);
  }
}