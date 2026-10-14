<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Module\Hooks\ClicShoppingAdmin\Categories;

use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoReportOld;
use ClicShopping\Apps\Marketing\SEO\SEO as SEOApp;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

class PageTabContent implements HooksInterface
{
  public mixed $app;
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

    $this->template = Registry::get('TemplateAdmin');
  }

  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_SEO_SE_STATUS') || CLICSHOPPING_APP_SEO_SE_STATUS == 'False') {
      return false;
    }

    if (!isset($_GET['cID'])) {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Categories/page_tab_content');

    if (isset($_GET['Edit'])) {

      $link_url = HTTP::getShopUrlDomain() . 'index.php?cPath=' . (int)$_GET['cID'];
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
<!-- Start Report SEO APP     -->
<!-- ######################## -->
<div class="tab-pane" id="section_SEOReportApp_content">
  <div class="mainTitle">
    <span class="col-md-12">{$title}</span>
  </div>
  <div class="mt-1"></div>
  {$content}
</div>

<script>
$('#section_SEOReportApp_content').appendTo('#categoriesTabs .tab-content');
$('#categoriesTabs .nav-tabs').append('    <li class="nav-item"><a data-bs-target="#section_SEOReportApp_content" role="tab" data-bs-toggle="tab" class="nav-link">{$tab_title}</a></li>');
</script>
<!-- ######################## -->
<!--  End Report SEO APP      -->
<!-- ######################## -->
EOD;

        return $output;
      }
    }
  }
}
