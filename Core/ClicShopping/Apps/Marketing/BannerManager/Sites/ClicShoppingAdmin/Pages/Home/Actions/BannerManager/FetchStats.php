<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager\Sites\ClicShoppingAdmin\Pages\Home\Actions\BannerManager;

use ClicShopping\OM\Registry;

class FetchStats extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {

    $CLICSHOPPING_BannerManager = Registry::get('BannerManager');

    $result = [];

    if (isset($_GET['banners_id']) && is_numeric($_GET['banners_id'])) {

      $Qbanner = $CLICSHOPPING_BannerManager->db->prepare('select banners_title
                                                              from :table_banners
                                                              where banners_id = :banners_id
                                                            ');
      $Qbanner->bindInt(':banners_id', $_GET['banners_id']);
      $Qbanner->execute();

      if ($Qbanner->fetch() !== false) {
        $days_shown = $days_clicked = [];

        for ($i = 0; $i < 7; $i++) {
          $date = date('m-d', strtotime('-' . $i . ' days'));

          $days_shown[$date] = $days_clicked[$date] = 0;
        }

        $Qstats = $CLICSHOPPING_BannerManager->db->prepare('select date_format(banners_history_date, "%m-%d") as date_day,
                                                                      banners_shown,
                                                                      banners_clicked
                                                                from :table_banners_history
                                                                where banners_id = :banners_id
                                                                and banners_history_date >= date_sub(now(), interval 7 day)
                                                              ');
        $Qstats->bindInt(':banners_id', $_GET['banners_id']);
        $Qstats->execute();

        while ($Qstats->fetch()) {
          $days_shown[$Qstats->value('date_day')] = $Qstats->valueInt('banners_shown');
          $days_clicked[$Qstats->value('date_day')] = $Qstats->valueInt('banners_clicked');
        }

        $result['labels'] = array_reverse(array_keys($days_shown));
        $result['days'] = array_reverse(array_values($days_shown));
        $result['clicks'] = array_reverse(array_values($days_clicked));
        $result['title'] = $Qbanner->valueProtected('banners_title');
      }
    }

    echo json_encode($result);

    exit;
  }
}