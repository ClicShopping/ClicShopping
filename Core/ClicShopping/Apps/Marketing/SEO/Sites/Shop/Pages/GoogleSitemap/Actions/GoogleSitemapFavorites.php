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

class GoogleSitemapFavorites extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $use_site_template = false;
  protected $rewriteUrl;

  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $this->rewriteUrl = Registry::get('RewriteUrl');

    if (!\defined('MODE_VENTE_PRIVEE') || MODE_VENTE_PRIVEE == 'false') {

      $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' ?>\n" . '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" />');

      $products_array = [];

      $Qproducts = $CLICSHOPPING_Db->prepare('select products_id,
                                                coalesce(NULLIF(products_favorites_last_modified, :products_favorites_last_modified),
                                                               products_favorites_date_added) as last_modified
                                                from :table_products_favorites
                                                where status = 1
                                                and customers_group_id = 0
                                                order by last_modified DESC
                                                ');

      $Qproducts->bindValue(':products_favorites_last_modified', null);
      $Qproducts->execute();

      while ($Qproducts->fetch()) {
        $location = htmlspecialchars($this->rewriteUrl->getProductNameUrl($Qproducts->valueInt('products_id')), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $products_array[$Qproducts->valueInt('products_id')]['loc'] = $location;
        $products_array[$Qproducts->valueInt('products_id')]['lastmod'] = $Qproducts->value('last_modified');
        $products_array[$Qproducts->valueInt('products_id')]['changefreq'] = 'weekly';
        $products_array[$Qproducts->valueInt('products_id')]['priority'] = '0.5';
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
