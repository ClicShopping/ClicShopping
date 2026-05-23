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

class GoogleSitemapBlogContent extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  protected $use_site_template = false;

  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!\defined('MODE_VENTE_PRIVEE') || MODE_VENTE_PRIVEE == 'false') {

      $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' ?>\n" . '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" />');

      $products_array = [];

      $Qproducts = $CLICSHOPPING_Db->prepare('select blog_content_id,
                                                coalesce(NULLIF(blog_content_last_modified, :last_modified),
                                                                blog_content_date_added) as last_modified
                                                from :table_blog_content
                                                where blog_content_status = 1
                                                and customers_group_id = 0
                                                order by last_modified desc
                                              ');

      $Qproducts->bindValue(':last_modified', null);
      $Qproducts->execute();

      while ($Qproducts->fetch()) {
        $location = htmlspecialchars(CLICSHOPPING::link(null, 'Blog&Content&blogContentId=' . $Qproducts->valueInt('blog_content_id')), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $products_array[$Qproducts->valueInt('blog_content_id')]['loc'] = $location;
        $products_array[$Qproducts->valueInt('blog_content_id')]['lastmod'] = $Qproducts->value('last_modified');
        $products_array[$Qproducts->valueInt('blog_content_id')]['changefreq'] = 'weekly';
        $products_array[$Qproducts->valueInt('blog_content_id')]['priority'] = '0.5';
      }

      foreach ($products_array as $k => $v) {
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
