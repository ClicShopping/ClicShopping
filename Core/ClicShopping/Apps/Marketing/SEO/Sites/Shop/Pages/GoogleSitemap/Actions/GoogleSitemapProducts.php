<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Sites\Shop\Pages\GoogleSitemap\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class GoogleSitemapProducts extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $use_site_template = false;
  protected $rewriteUrl;

  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $this->rewriteUrl = Registry::get('RewriteUrl');

    if (!\defined('MODE_VENTE_PRIVEE') || MODE_VENTE_PRIVEE == 'false') {
      $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' ?>\n" . '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" />');

      $product_array = [];

      $Qproducts = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                        coalesce(NULLIF(p.products_last_modified, :products_last_modified),
                                                                        p.products_date_added) as last_modified
                                                from :table_products p,
                                                     :table_products_to_categories p2c,
                                                     :table_categories c
                                                where p.products_status = 1
                                                and p.products_view = 1                                                                                         
                                                and p.products_id = p2c.products_id
                                                and p2c.categories_id = c.categories_id
                                                and c.status = 1    
                                                order by last_modified desc
                                                ');

      $Qproducts->bindValue(':products_last_modified', null);
      $Qproducts->execute();

      while ($Qproducts->fetch()) {
        $location = htmlspecialchars($this->rewriteUrl->getProductNameUrl($Qproducts->valueInt('products_id')), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $product_array[$Qproducts->valueInt('products_id')]['loc'] = $location;
        $product_array[$Qproducts->valueInt('products_id')]['lastmod'] = $Qproducts->value('last_modified');
        $product_array[$Qproducts->valueInt('products_id')]['changefreq'] = 'weekly';
        $product_array[$Qproducts->valueInt('products_id')]['priority'] = '0.5';
      }

      foreach ($product_array as $k => $v) {
        $url = $xml->addChild('url');
        $url->addChild('loc', $v['loc']);
        $url->addChild('lastmod', date("Y-m-d", strtotime($v['lastmod']) ?: time()));
        $url->addChild('changefreq', 'weekly');
        $url->addChild('priority', '0.5');
      }

      header('Content-type: text/xml');
      echo $xml->asXML();
      exit;
    }
  }
}
