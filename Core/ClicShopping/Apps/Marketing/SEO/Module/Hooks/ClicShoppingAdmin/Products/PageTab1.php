<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductsStatusAdmin;
use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoReportOld;
use ClicShopping\Apps\Marketing\SEO\SEO as SEOApp;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class PageTab implements HooksInterface
{
  public mixed $app;
  protected $SEOAdmin;
  protected $products;
  private mixed $lang;
  private mixed $db;
  private mixed $template;

  public function __construct()
  {
    if (!Registry::exists('SEO')) {
      Registry::set('SEO', new SEOApp());
    }

    $this->app = Registry::get('SEO');
    $this->lang = Registry::get('Language');
    $this->db = Registry::get('Db');
    $this->products = Registry::get('Products');
    $this->template = Registry::get('TemplateAdmin');
  }

  public function display()
  {
    $CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');

    if (!\defined('CLICSHOPPING_APP_SEO_SE_STATUS') || CLICSHOPPING_APP_SEO_SE_STATUS == 'False') {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/page_tab');

    if (!isset($_GET['pID']) || ProductsStatusAdmin::checkProductStatus($_GET['pID']) === false) {
      return false;
    }

    if (isset($_GET['Edit'])) {
      if (isset($_GET['pID'])) {
        $link_url = HTTP::getShopUrlDomain() . 'index.php?Products&Description&products_id=' . (int)$_GET['pID'];
        $url_site = HTTP::getShopUrlDomain();

        $this->Report = new SeoReportOld($link_url, $url_site);
        $report = $this->Report->getSeoReport();
        $content = '<!-- SEO Page report -->';

        if (isset($report)) {
          $content .= $report;

          $tab_title = $this->app->getDef('tab_seo_report');
          $title = $this->app->getDef('tab_seo_report');

          $output = <<<EOD
<!-- ######################## -->
<!-- Start Report SEO APP  -->
<!-- ######################## -->
<div class="tab-pane" id="section_SEOReportApp_content">
  <div class="mainTitle">
    <span class="col-md-10">{$title}</span>
  </div>
  {$content}
</div>

<script>
$('#section_SEOReportApp_content').appendTo('#productsTabs .tab-content');
$('#productsTabs .nav-tabs').append('    <li class="nav-item"><a data-bs-target="#section_SEOReportApp_content" role="tab" data-bs-toggle="tab" class="nav-link">{$tab_title}</a></li>');
</script>
<!-- ######################## -->
<!--  End eport APP   -->
<!-- ######################## -->
EOD;

          return $output;
        }
      }
    }
  }
}
