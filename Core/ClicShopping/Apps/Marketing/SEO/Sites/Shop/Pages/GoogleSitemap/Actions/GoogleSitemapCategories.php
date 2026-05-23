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

class GoogleSitemapCategories extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $use_site_template = false;
  protected $rewriteUrl;

  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Db = Registry::get('Db');
    $this->rewriteUrl = Registry::get('RewriteUrl');

    if (!\defined('MODE_VENTE_PRIVEE') || MODE_VENTE_PRIVEE == 'false') {
    $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' ?>\n" . '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" />');

    $category_array = [];

    $Qcategorie = $CLICSHOPPING_Db->prepare('select c.categories_id,
                                                      cd.categories_name,
                                              coalesce(NULLIF(last_modified, :last_modified),
                                                              date_added) as last_modified
                                              from :table_categories c,
                                              :table_categories_description cd
                                              where virtual_categories = 0
                                              and c.categories_id = cd.categories_id
                                              and c.status = 1
                                              and cd.language_id = :language_id
                                              group by categories_id
                                              order by last_modified DESC
                                              ');

    $Qcategorie->bindValue(':last_modified', null);
    $Qcategorie->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $Qcategorie->execute();

    while ($Qcategorie->fetch()) {

      $this->rewriteUrl->getCategoryTreeTitle($Qcategorie->value('categories_name'));
      $location = htmlspecialchars($this->rewriteUrl->getCategoryTreeUrl($Qcategorie->valueInt('categories_id')), ENT_QUOTES | ENT_XML1, 'UTF-8');

      $category_array[$Qcategorie->valueInt('categories_id')]['loc'] = $location;
      $category_array[$Qcategorie->valueInt('categories_id')]['lastmod'] = $Qcategorie->value('last_modified');
      $category_array[$Qcategorie->valueInt('categories_id')]['changefreq'] = 'weekly';
      $category_array[$Qcategorie->valueInt('categories_id')]['priority'] = '0.5';
    }

    foreach ($category_array as $k => $v) {
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
