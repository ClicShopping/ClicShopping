<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Module\Hooks\ClicShoppingAdmin\PageManager;

use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoReportOld;
use ClicShopping\Apps\Marketing\SEO\SEO as SEOApp;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

class PageTab implements HooksInterface
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

    if (!isset($_GET['bID'])) {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/PageManager/page_tab');

    if (isset($_GET['Edit']) && isset($_GET['bID']) && $_GET['bID'] != 3) {
      $bId = HTML::sanitize($_GET['bID']);

      if (isset($_POST['page_type'])) {
        $page_type = HTML::sanitize($_POST['page_type']);
      } else {
        $page_type = null;
      }

      if ($page_type == 2) {
        $link_url = HTTP::getShopUrlDomain();
      } else {
        $link_url = HTTP::getShopUrlDomain() . 'index.php?Info&Content&pagesId=' . (int)$bId;
      }

      $url_site = HTTP::getShopUrlDomain();

      $result = @file_get_contents($link_url, true);

      if ($result !== false) {
        $this->Report = new SeoReportOld($link_url, $url_site);

        $report = $this->Report->getSeoReport();
      } else {
        return false;
      }

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
$('#section_SEOReportApp_content').appendTo('#pageManagerTabs .tab-content');
$('#pageManagerTabs .nav-tabs').append('    <li class="nav-item"><a data-bs-target="#section_SEOReportApp_content" role="tab" data-bs-toggle="tab" class="nav-link">{$tab_title}</a></li>');
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
