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

class GoogleSitemapManufacturers extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $use_site_template = false;
  protected $rewriteUrl;

  public function execute()
  {
    $this->rewriteUrl = Registry::get('RewriteUrl');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!\defined('MODE_VENTE_PRIVEE') || MODE_VENTE_PRIVEE == 'false') {
      $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' ?>\n" . '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" />');

      $manufacturer_array = [];

      $Qmanufacturers = $CLICSHOPPING_Db->prepare('select manufacturers_id,
                                                     coalesce(NULLIF(last_modified, :last_modified),
                                                                     date_added) as last_modified
                                                      from :table_manufacturers
                                                      where manufacturers_status = 0
                                                      order by last_modified DESC
                                                      ');

      $Qmanufacturers->bindValue(':last_modified', null);
      $Qmanufacturers->execute();

      while ($Qmanufacturers->fetch()) {
        $location = htmlspecialchars($this->rewriteUrl->getManufacturerUrl($Qmanufacturers->valueInt('manufacturers_id')), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $manufacturer_array[$Qmanufacturers->valueInt('manufacturers_id')]['loc'] = $location;
        $manufacturer_array[$Qmanufacturers->valueInt('manufacturers_id')]['lastmod'] = $Qmanufacturers->value('last_modified');
        $manufacturer_array[$Qmanufacturers->valueInt('manufacturers_id')]['changefreq'] = 'weekly';
        $manufacturer_array[$Qmanufacturers->valueInt('manufacturers_id')]['priority'] = '0.5';
      }

      foreach ($manufacturer_array as $k => $v) {
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
