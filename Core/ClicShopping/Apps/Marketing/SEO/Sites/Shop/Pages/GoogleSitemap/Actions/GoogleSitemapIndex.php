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

class GoogleSitemapIndex extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $use_site_template = false;

  public function execute()
  {
    $this->page->setUseSiteTemplate(false); //don't display Header / Footer

    if (!\defined('MODE_VENTE_PRIVEE') || MODE_VENTE_PRIVEE == 'false') {
      $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?>\n" . '<sitemapindex xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" />');

      $sitemaps = [
        'Sitemap&GoogleSitemapCategories',
        'Sitemap&GoogleSitemapProducts',
        'Sitemap&GoogleSitemapSpecials',
        'Sitemap&GoogleSitemapFavorites',
        'Sitemap&GoogleSitemapManufacturers',
        'Sitemap&GoogleSitemapBlogCategories',
        'Sitemap&GoogleSitemapBlogContent',
        'Sitemap&GoogleSitemapPageManager',
        'Sitemap&GoogleSitemapFeatured',
      ];

      $lastmod = date('Y-m-d');

      foreach ($sitemaps as $sitemapRoute) {
        $location = htmlspecialchars(CLICSHOPPING::link(null, $sitemapRoute), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $sitemap = $xml->addChild('sitemap');
        $sitemap->addChild('loc', $location);
        $sitemap->addChild('lastmod', $lastmod);
      }

      header('Content-type: text/xml');
      echo $xml->asXML();
      exit;
    }
  }
}