<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\SEO\Classes\Shop\SeoShop;

// start the timer for the page parse time log
define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', __DIR__ . '/Core/ClicShopping/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();

CLICSHOPPING::loadSite('Shop');

$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

if (!Registry::exists('SeoShop')) {
  Registry::set('SeoShop', new SeoShop());
}

$CLICSHOPPING_seoShop = Registry::get('SeoShop');

$title = HTML::removeFileAccents($CLICSHOPPING_seoShop->getSeoIndexTitle());
$description = HTML::removeFileAccents($CLICSHOPPING_seoShop->getSeoIndexDescription());

$siteName = HTML::removeFileAccents(STORE_NAME);

if (empty($title)) {
  $store_name = HTML::removeFileAccents(STORE_NAME);
  $shortName = substr($store_name, 0, 30);
} else {
  $shortName = substr($title, 0, 30);
}

if (empty($description)) {
  $description = HTML::removeFileAccents(STORE_NAME);
}

$scope = CLICSHOPPING::getConfig('http_path', 'Shop');
$start_url = HTTP::getShopUrlDomain();

$image_192 = HTTP::getShopUrlDomain() . 'sources/images/logos/manifest/logo_192.png';
$image_512 = HTTP::getShopUrlDomain() . 'sources/images/logos/manifest/logo_512.png';

$code_langue = $CLICSHOPPING_Language->getCode();

$manifest = [
  "dir" => "ltr",
  "lang" => $code_langue,
  "name" => $siteName,
  "short_name" => $shortName,
  "description" => $description,
  "scope" => $scope,
  "display" => "standalone",
  "start_url" => $start_url,
  "theme_color" => "#317EFB",
  "orientation" => "any",
  "background_color" => "#fff",
  "related_applications" => [],
  "prefer_related_applications" => false,
  "screenshots" => [],
  "icons" => [
    [
      "src" => $image_192,
      "sizes" => "192x192",
      "type" => "image/png",
      "purpose" => "maskable"
    ],
    [
      "src" => $image_512,
      "sizes" => "512x512",
      "type" => "image/png",
      "purpose" => "maskable"
    ]
  ]
];


$json_manifest = json_encode($manifest);

echo $json_manifest;
