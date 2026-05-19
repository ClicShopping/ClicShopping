<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Marketing\SEO\Sites\Shop\Pages\GoogleSitemap\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class GoogleSitemapFeatured extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $use_site_template = false;
  protected $rewriteUrl;

  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $this->rewriteUrl = Registry::get('RewriteUrl');

    if (MODE_VENTE_PRIVEE == 'false') {
      $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' ?>\n" . '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" />');

      $products_array = [];

      $QproductsFeatured = $CLICSHOPPING_Db->prepare('select products_id,
                                                          coalesce(NULLIF(products_featured_last_modified, :products_featured_last_modified),
                                                                   products_featured_date_added) as last_modified
                                                          from :table_products_featured
                                                          where status = 1
                                                          and customers_group_id = 0
                                                          order by last_modified desc
                                                         ');

      $QproductsFeatured->bindValue(':products_featured_last_modified', null);
      $QproductsFeatured->execute();

      while ($QproductsFeatured->fetch()) {
        $location = htmlspecialchars(mb_convert_encoding($this->rewriteUrl->getProductNameUrl($QproductsFeatured->valueInt('products_id')), 'UTF-8', 'ISO-8859-1'), ENT_QUOTES | ENT_HTML5);

        $products_array[$QproductsFeatured->valueInt('products_id')]['loc'] = $location;
        $products_array[$QproductsFeatured->valueInt('products_id')]['lastmod'] = $QproductsFeatured->value('last_modified');
        $products_array[$QproductsFeatured->valueInt('products_id')]['changefreq'] = 'weekly';
        $products_array[$QproductsFeatured->valueInt('products_id')]['priority'] = '0.5';
      }

      foreach ($products_array as $k => $v) {
        $url = $xml->addChild('url');
        $url->addChild('loc', $v['loc']);
        $url->addChild('lastmod', date("Y-m-d", strtotime($v['lastmod'])));
        $url->addChild('changefreq', 'weekly');
        $url->addChild('priority', '0.5');
      }

      header('Content-type: text/xml');
      echo $xml->asXML();
      exit;
    }
  }
}
