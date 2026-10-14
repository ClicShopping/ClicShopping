<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sites\Shop\Pages\RSS\Actions;

use ClicShopping\Apps\Communication\PageManager\Classes\Shop\RSS as RSSApp;
use ClicShopping\OM\Registry;

class RSS extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_RSS = new RSSApp();
    Registry::set('RSS', $CLICSHOPPING_RSS);

    $CLICSHOPPING_RSS = Registry::get('RSS');

    if (!function_exists('getallheaders')) {
      function getallheaders()
      {
        $headers = [];

        foreach ($_SERVER as $h => $v) {
          if (preg_match('#HTTP_(.+)#', $h, $hp)) {
            $headers[$hp[1]] = $v;
          }
        }
        return $headers;
      }
    }

    header('Content-Type: application/rss+xml; charset=UTF-8');
    header('Last-Modified: ' . gmdate("D, d M Y G:i:s", strtotime($CLICSHOPPING_RSS->productDateAdded())) . ' GMT');

    $CLICSHOPPING_RSS->getMaxListing(20);

    echo $CLICSHOPPING_RSS->displayFeed();

    exit;
  }
}
