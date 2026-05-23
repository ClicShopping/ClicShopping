<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Categories;

  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoEmbedding;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoReport;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoSerpReportRepository;
  use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\Registry;

  /**
   * Class CategoriesSerp
   * Hook to display SEO analysis and AI reports within the Category administration page.
   * Logic flow:
   * - If no history exists: Displays initial report + "Run Analysis" button.
   * - If history exists: Displays current report + score delta + AI suggestions + history table.
   */
  class CategoriesSerp implements \ClicShopping\OM\Modules\HooksInterface
  {
    public mixed $app;
    private mixed $lang;
    private mixed $db;
    private mixed $template;
    private string $db_stable = 'categories_seo_embedding';

    /**
     * CategoriesSerp constructor.
     * Initializes dependencies and loads translation definitions.
     */
    public function __construct()
    {
      if (!Registry::exists('Ecommerce')) {
        Registry::set('Ecommerce', new EcommerceApp());
      }

      $this->app      = Registry::get('Ecommerce');
      $this->lang     = Registry::get('Language');
      $this->db       = Registry::get('Db');
      $this->template = Registry::get('TemplateAdmin');

      $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Categories/page_tab_content');
    }

    /**
     * Main entry point for the hook.
     * Checks requirements and renders the SEO tab content.
     * * @return string|false HTML content of the tab or false if requirements are not met.
     */
    public function display(): string|false
    {
      $requiredConstants = ['CLICSHOPPING_APP_ECOMMERCE_EC_STATUS'];

      foreach ($requiredConstants as $const) {
        if (!\defined($const) || \constant($const) !== 'True') {
          return false;
        }
      }

      if (!isset($_GET['cID'], $_GET['Edit'])) {
        return false;
      }

      $categoryId = (int) $_GET['cID'];
      $languageId = (int)$this->lang->getId();

      if (isset($_GET['language_id'])) {
        $requestedLanguage = (int)$_GET['language_id'];
        if ($requestedLanguage > 0) {
          $languageId = $requestedLanguage;
        }
      }

      $linkUrl    = HTTP::getShopUrlDomain() . 'index.php?cPath=' . $categoryId;
      $baseUrl = HTTP::getShopUrlDomain();

      // -- Manual Action: Run/Re-run analysis --
      $actionResult = null;
      if (isset($_POST['seo_run_analysis']) && (int) ($_POST['seo_category_id'] ?? 0) === $categoryId) {
        try {
          $repository = $this->getRepository();
          $postedLanguage = (int)($_POST['language_id'] ?? 0);
          if ($postedLanguage > 0) {
            $languageId = $postedLanguage;
          }
          $actionResult = $repository->process(
            entityId:    $categoryId,
            languageId: $languageId,
            url: $linkUrl,
            baseUrl: $baseUrl,
            pageType:    'category',
            triggeredBy: 'manual'
          );
        } catch (\Throwable $e) {
          $actionResult = ['success' => false, 'error' => $e->getMessage()];
        }
      }

      // -- Load embedding history --
      try {
        $repository = $this->getRepository();
        $latest     = $repository->getLatestReport($categoryId, $languageId);
        $history    = $repository->getHistory($categoryId, $languageId, limit: 5);
      } catch (\Throwable $e) {
        $latest = null;
        $history = [];
      }

      // -- Load latest agentic audit (advanced AI) --
      try {
        $serpRepo = new SeoSerpReportRepository();
        $agenticLatest = $serpRepo->getLatestReport('category', $categoryId, $languageId);
      } catch (\Throwable $e) {
        $agenticLatest = null;
      }

      // -- Live SEO Report (crawl current page) --
      $seoReport = new SeoReport($linkUrl, $baseUrl);
      $seoData    = $seoReport->getSeoData(false, 'category');

      if ($seoData['isAlive']) {
        if (method_exists($seoReport, 'getHTMLReport')) {
          $reportHtml = $seoReport->getHTMLReport($seoData);
        } elseif (method_exists($seoReport, 'getSeoReport')) {
          $reportHtml = $seoReport->getSeoReport();
        } else {
          $reportHtml = '';
        }
      } else {
        $reportHtml = '';
      }

      // -- UI Assembly --
      $title = $this->app->getDef('tab_seo_report');
      $content = $this->buildTabContent(
        categoryId:    $categoryId,
        latest: $latest,
        history: $history,
        seoData: $seoData,
        reportHtml: $reportHtml,
        actionResult: $actionResult,
        agenticLatest: $agenticLatest,
        languageId: $languageId
      );

      return $this->wrapInTab($title, $content);
    }

    // ============================================================
    // UI BUILDERS
    // ============================================================

    /**
     * Returns an instance of the SEO embedding repository.
     * * @return SeoEmbedding
     */
    private function getRepository(): SeoEmbedding
    {
      return new SeoEmbedding($this->db_stable);
    }

    /**
     * Builds the complete tab content based on context.
     * * @param int $categoryId
     * @param array|null $latest Latest stored report
     * @param array $history Previous reports history
     * @param array $seoData Live crawled data
     * @param string $reportHtml HTML representation of the live report
     * @param array|null $actionResult Result of a manual trigger
     * @param array|null $agenticLatest Latest AI agent report
     * @param int $languageId
     * @return string
     */
    private function buildTabContent(
      int $categoryId,
      ?array $latest,
      array $history,
      array $seoData,
      string $reportHtml,
      ?array $actionResult,
      ?array $agenticLatest,
      int $languageId
    ): string {
      $out = '';

      $out .= $this->renderLanguageSelector($languageId);

      if ($actionResult !== null) {
        $out .= $this->renderActionBanner($actionResult);
      }

      // Initial Mode: No history yet
      if ($latest === null) {
        $out .= $this->renderInitialMode($categoryId, $seoData, $reportHtml, $languageId);
        return $out;
      }

      // Optimization Mode: History available
      $out .= $this->renderOptimizationMode($categoryId, $latest, $history, $seoData, $reportHtml, $agenticLatest, $languageId);

      return $out;
    }

    private function renderLanguageSelector(int $languageId): string
    {
      try {
        $languages = $this->lang->getAll();
      } catch (\Throwable) {
        return '';
      }

      if (empty($languages)) {
        return '';
      }

      $options = '';
      foreach ($languages as $lang) {
        $id = (int)($lang['id'] ?? 0);
        $name = (string)($lang['name'] ?? $id);
        $selected = $id === $languageId ? ' selected' : '';
        $options .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars($name) . '</option>';
      }

      $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
      $separator = str_contains($currentUrl, '?') ? '&' : '?';

      $out  = '<div class="mb-3">';
      $out .= '<label class="form-label small text-muted">' . $this->app->getDef('text_seo_language_select') . '</label>';
      $out .= '<select class="form-select form-select-sm" id="seo-language-selector">' . $options . '</select>';
      $out .= '</div>';
      $out .= '<script>
        (function(){
          var selector = document.getElementById("seo-language-selector");
          if (!selector) return;
          selector.addEventListener("change", function(){
            var url = "' . addslashes($currentUrl) . '";
            var newUrl = url;
            if (url.indexOf("language_id=") !== -1) {
              newUrl = url.replace(/language_id=\d+/, "language_id=" + this.value);
            } else {
              newUrl = url + "' . $separator . 'language_id=" + this.value;
            }
            window.location.href = newUrl;
          });
        })();
      </script>';

      return $out;
    }

    /**
     * Renders a success/error banner after an AJAX action.
     * * @param array $actionResult
     * @return string
     */
    private function renderActionBanner(array $actionResult): string
    {
      if (!($actionResult['success'] ?? false)) {
        $error = htmlspecialchars($actionResult['error'] ?? 'Unknown error.');
        return '<div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i>' . $error . '</div>';
      }

      $mode = $actionResult['mode'] ?? '';
      $score = $actionResult['seo_score'] ?? $actionResult['seo_score_now'] ?? $actionResult['seo_score_after'] ?? '—';
      $message = htmlspecialchars($actionResult['message'] ?? '');

      $icon = $mode === 'initial' ? 'bi-check-circle-fill' : 'bi-check2-all';
      $type = ($actionResult['improved'] ?? true) ? 'success' : 'warning';

      return '<div class="alert alert-' . $type . '">'
        . '<i class="bi ' . $icon . ' me-1"></i>'
        . $message
        . ' — Score : <strong>' . $score . '/100</strong>'
        . '</div>';
    }

    /**
     * Renders the UI for categories with no previous analysis.
     * * @param int $categoryId
     * @param array $seoData
     * @param string $reportHtml
     * @param int $languageId
     * @return string
     */
    private function renderInitialMode(int $categoryId, array $seoData, string $reportHtml, int $languageId): string
    {
      $score      = $seoData['seo_score'] ?? 0;
      $scoreColor = $this->scoreColor($score);

      $out  = '<div class="alert alert-info d-flex align-items-center gap-2 mb-3">';
      $out .= '<i class="bi bi-info-circle-fill fs-5"></i>';
      $out .= '<div>';
      $out .= '<strong>' . $this->app->getDef('text_seo_no_history_title') . '</strong><br />';
      $out .= $this->app->getDef('text_seo_no_history_info');
      $out .= '</div>';
      $out .= '</div>';

      $out .= $this->renderScoreBadge($score, $scoreColor, label: $this->app->getDef('text_seo_current_score_not_archived'));

      // T3.5 — Schema.org badge
      $out .= $this->renderSchemaBadge($seoData, 'category');

      // T4.1 + T4.4 — Thin content & JS render warnings
      $out .= $this->renderCategoryContentWarnings($seoData);

      $runUrl = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/SEO/save_category_report.php';

      $out .= $this->renderActionButton(
        $categoryId,
        label: $this->app->getDef('text_seo_run_initial'),
        url: $runUrl,
        postName: 'seo_run_analysis',
        buttonClass: 'btn-primary',
        languageId: $languageId
      );
      $out .= $this->renderReportsButton($categoryId);

      if (!empty($reportHtml)) {
        $out .= '<div class="mt-3">' . $reportHtml . '</div>';
      }

      return $out;
    }

    /**
     * Determines the Bootstrap color class based on the SEO score.
     * * @param int $score
     * @return string
     */
    private function scoreColor(int $score): string
    {
      if ($score >= 70) return 'success';
      if ($score >= 40) return 'warning';
      return 'danger';
    }

    /**
     * Renders a badge containing the SEO score.
     * * @param int $score
     * @param string $color Bootstrap color class
     * @param string $label Optional label
     * @return string
     */
    private function renderScoreBadge(int $score, string $color, string $label = ''): string
    {
      $out = '<div class="mb-3 d-flex align-items-center gap-2">';
      if ($label) {
        $out .= '<span class="text-muted small">' . htmlspecialchars($label) . '</span>';
      }
      $out .= '<span class="badge bg-' . $color . ' fs-6" id="seo-live-score-badge" data-score="' . $score . '">' . $score . '/100</span>';
      $out .= '</div>';

      return $out;
    }

    /**
     * T3.5 — Renders a schema.org status badge (category variant).
     *
     * Green  = BreadcrumbList (or other JSON-LD) detected and JSON is valid.
     * Yellow = JSON-LD detected but contains JSON errors.
     * Red    = No JSON-LD found; links to Google Rich Results Test.
     *
     * @param array  $seoData    Report array from SeoReport::getSeoData()
     * @param string $entityType 'product' | 'category'
     */
    private function renderSchemaBadge(array $seoData, string $entityType): string
    {
      $schema   = $seoData['schema_org'] ?? [];
      $detected = (bool)($schema['detected'] ?? false);
      $types    = $schema['types']    ?? [];
      $valid    = (bool)($schema['valid'] ?? false);

      $out = '<div class="mb-3 d-flex align-items-center gap-2 flex-wrap">';

      if ($detected) {
        $typeLabel  = !empty($types) ? implode(', ', $types) : 'JSON-LD';
        $validBadge = $valid
          ? '<span class="badge bg-success ms-1"><i class="bi bi-check-lg me-1"></i>valid JSON</span>'
          : '<span class="badge bg-warning text-dark ms-1"><i class="bi bi-exclamation-triangle me-1"></i>JSON error</span>';

        $out .= '<span class="badge bg-success">';
        $out .= '<i class="bi bi-diagram-3-fill me-1"></i>';
        $out .= 'Schema.org detected: ' . htmlspecialchars($typeLabel);
        $out .= '</span>';
        $out .= $validBadge;
      } else {
        $richResultsUrl = 'https://search.google.com/test/rich-results?url=' . urlencode($seoData['url'] ?? '');
        $expectedType   = match ($entityType) {
          'category' => 'BreadcrumbList + ItemList',
          default    => 'Product',
        };

        $out .= '<span class="badge bg-danger">';
        $out .= '<i class="bi bi-diagram-3 me-1"></i>';
        $out .= 'Schema.org ' . htmlspecialchars($expectedType) . ' missing';
        $out .= '</span>';
        $out .= ' <a href="' . htmlspecialchars($richResultsUrl) . '" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm py-0">';
        $out .= '<i class="bi bi-box-arrow-up-right me-1"></i>Rich Results Test</a>';
      }

      $out .= '</div>';

      return $out;
    }

    /**
     * Renders a button that triggers an AJAX SEO analysis.
     * * @param int $categoryId
    /**
     * T4.1 + T4.4 — Renders contextual warning banners for category pages.
     *
     * TWO independent signals from SeoReport::getSeoData():
     *
     * js_render_suspected (T4.4) — H1 absent AND wordcount_body < 50.
     *   Means the crawler got an empty shell; JavaScript likely renders the
     *   real content client-side. SEO score is underestimated. Shown first
     *   because it explains *why* thin_content may appear.
     *
     * thin_content (T4.1) — wordcount_body < 150.
     *   Category page has insufficient crawlable text. Hurts rankings.
     *   Running the agentic optimization will generate and inject a
     *   body description into categories_description.categories_description.
     */
    private function renderCategoryContentWarnings(array $seoData): string
    {
      $out         = '';
      $wordcount   = (int)($seoData['wordcount_body']      ?? 0);
      $thinContent = (bool)($seoData['thin_content']        ?? false);
      $jsRender    = (bool)($seoData['js_render_suspected'] ?? false);

      // T4.4 — JS render warning (highest priority — explains thin_content if both true)
      if ($jsRender) {
        $out .= '<div class="alert alert-secondary d-flex align-items-start gap-2 mb-2">';
        $out .= '<i class="bi bi-code-slash fs-5 mt-1 text-secondary flex-shrink-0"></i>';
        $out .= '<div>';
        $out .= '<strong>' . htmlspecialchars($this->app->getDef('text_seo_js_render_title')) . '</strong><br />';
        $out .= '<span class="small text-muted">' . htmlspecialchars($this->app->getDef('text_seo_js_render_info')) . '</span>';
        $out .= '</div>';
        $out .= '</div>';
      }

      // T4.1 — Thin content warning
      if ($thinContent) {
        $message = sprintf(
          $this->app->getDef('text_seo_thin_content_info'),
          $wordcount
        );

        $out .= '<div class="alert alert-warning d-flex align-items-start gap-2 mb-2">';
        $out .= '<i class="bi bi-file-earmark-x fs-5 mt-1 flex-shrink-0"></i>';
        $out .= '<div>';
        $out .= '<strong>' . htmlspecialchars($this->app->getDef('text_seo_thin_content_title')) . '</strong><br />';
        $out .= '<span class="small">' . htmlspecialchars($message) . '</span>';
        $out .= '</div>';
        $out .= '</div>';
      }

      return $out;
    }

    /**
     * Renders a button that triggers an AJAX SEO action.
     * On success the page reloads via window.location.href (preserving the ClicShopping URL intact)
     * so the history table refreshes and the SEO tab reopens automatically.
     * On error a Bootstrap modal shows the message without reloading.
     */
    private function renderActionButton(
      int    $categoryId,
      string $label,
      string $url,
      string $postName,
      string $buttonClass = 'btn-primary',
      int    $languageId  = 0
    ): string {
      $loadingText  = $this->app->getDef('text_seo_loading')              ?: 'Loading…';
      $ajaxInvalid  = $this->app->getDef('text_seo_ajax_invalid_response') ?: 'Invalid response.';
      $unknownError = $this->app->getDef('text_seo_unknown_error')         ?: 'Unknown error.';
      $ajaxFailed   = $this->app->getDef('text_seo_ajax_failed')           ?: 'Request failed';

      // Unique suffix per button so multiple buttons on the same page don't conflict
      $uid = 'seo_' . substr(md5($postName . $categoryId), 0, 8);

      $out  = '<button type="button"';
      $out .= ' id="btn_' . $uid . '"';
      $out .= ' class="btn ' . $buttonClass . ' btn-sm me-2 mb-3"';
      $out .= ' data-url="'       . htmlspecialchars($url)      . '"';
      $out .= ' data-post-name="' . htmlspecialchars($postName) . '"';
      $out .= ' data-category-id="'. $categoryId . '"';
      $out .= ' data-language-id="'. (int)$languageId . '">';
      $out .= '<i class="bi bi-play-circle me-1"></i>' . htmlspecialchars($label);
      $out .= '</button>';

      // Error modal (only injected once per page via a guard check)
      $out .= '
<div class="modal fade" id="seoErrorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>SEO</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="seoErrorModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . ($this->app->getDef('text_seo_modal_close') ?: 'Close') . '</button>
      </div>
    </div>
  </div>
</div>';

      $out .= '<script>
(function () {
  var btn = document.getElementById(' . json_encode('btn_' . $uid) . ');
  if (!btn) return;

  btn.addEventListener("click", function () {
    if (btn.disabled) return;

    var formURL    = btn.getAttribute("data-url");
    var postName   = btn.getAttribute("data-post-name");
    var categoryId = btn.getAttribute("data-category-id");
    var languageId = btn.getAttribute("data-language-id");

    var postData = {};
    postData[postName]          = "1";
    postData["seo_category_id"] = categoryId;
    postData["language_id"]     = languageId;

    var originalHtml = btn.innerHTML;
    btn.disabled  = true;
    btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-1" role="status"></span>\' + ' . json_encode($loadingText) . ';

    // Progress indicator for long-running optimization
    var progressInterval;
    var dots = 0;
    var startTime = Date.now();
    
    progressInterval = setInterval(function() {
      dots = (dots + 1) % 4;
      var dotStr = ".".repeat(dots);
      var elapsed = Math.floor((Date.now() - startTime) / 1000);
      btn.innerHTML = \'<span class="spinner-border spinner-border-sm me-1" role="status"></span>\' 
                    + ' . json_encode($loadingText) . ' + dotStr + " (" + elapsed + "s)";
    }, 1000);

    function showSeoError(msg) {
      if (progressInterval) clearInterval(progressInterval);
      var modalEl = document.getElementById("seoErrorModal");
      document.getElementById("seoErrorModalBody").innerHTML = "<p>" + msg + "</p>";
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
    
    // -----------------------------
    // SNIPPET 2 — AJAX success logic
    // -----------------------------
    
    $.ajax({
      url: formURL,
      type: "POST",
      data: postData,
      dataType: "json",
      timeout: 300000  // 5 minutes timeout for long-running SEO optimization
    }).done(function (payload) {
    
      if (progressInterval) clearInterval(progressInterval);
      btn.disabled  = false;
      btn.innerHTML = originalHtml;
    
      if (!payload || typeof payload !== "object") {
        showSeoError("Invalid response");
        return;
      }
    
      // Check success with multiple type checks
      var isSuccess = (payload.success === true || payload.success === 1 || payload.success === "true" || payload.success === "1");
    
      if (isSuccess) {
    
        // Show success message
        btn.innerHTML = \'<i class="bi bi-check-circle me-1"></i>Success! Reloading...\';
        btn.classList.remove("btn-success");
        btn.classList.add("btn-success", "opacity-75");
    
        // Reload page after short delay to show success message
        setTimeout(function() {
          var base = window.location.href.replace(/#.*$/, "");
    
          if (languageId && base.indexOf("language_id=") !== -1) {
            base = base.replace(/language_id=\\d+/, "language_id=" + languageId);
          } else if (languageId && base.indexOf("language_id=") === -1) {
            base = base + (base.indexOf("?") !== -1 ? "&" : "?") + "language_id=" + languageId;
          }
    
          window.location.replace(base + "#section_SEOReportApp_content");
        }, 800);
    
      } else {
    
        var msg = payload.error || payload.message || "Unknown error";
        showSeoError(msg);
    
      }
    
    }).fail(function (xhr) {
    
      if (progressInterval) clearInterval(progressInterval);
      btn.disabled  = false;
      btn.innerHTML = originalHtml;
    
      var errorMsg = "Request failed (HTTP " + xhr.status + ")";
      if (xhr.status === 0) {
        errorMsg = "Request timeout or network error. The optimization may still be running on the server.";
      } else if (xhr.status === 504 || xhr.status === 502) {
        errorMsg = "Gateway timeout. The optimization is taking longer than expected. Please refresh the page in a moment to see if changes were applied.";
      }
      showSeoError(errorMsg);
    
    });
  });

  function showSeoError(msg) {
    var modalEl = document.getElementById("seoErrorModal");
    document.getElementById("seoErrorModalBody").innerHTML = "<p>" + msg + "</p>";
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
})();
</script>';

      return $out;
    }

    /**
     * Renders a link to the global SEO reports page for categories.
     * * @param int $categoryId
     * @return string
     */
    private function renderReportsButton(int $categoryId): string
    {
    $out = '';
    /*
      $link = CLICSHOPPING::link(null, 'A&Marketing\SEO&Reports&scope=categories&entity_id=' . (int)$categoryId);

      $out  = '<a class="btn btn-outline-secondary btn-sm mb-3" href="' . htmlspecialchars($link) . '">';
      $out .= '<i class="bi bi-bar-chart-line me-1"></i>' . htmlspecialchars($this->app->getDef('text_seo_view_reports'));
      $out .= '</a>';
     */
      return $out;
    }

    // ============================================================
    // HELPERS
    // ============================================================
    /**
     * Renders the optimization UI with comparative scores and AI suggestions.
     * @param int $categoryId
     * @param array $latest
     * @param array $history
     * @param array $seoData
     * @param string $reportHtml
     * @param array|null $agenticLatest
     * @param int $languageId
     * @return string
     */
    private function renderOptimizationMode(
      int $categoryId,
      array $latest,
      array $history,
      array $seoData,
      string $reportHtml,
      ?array $agenticLatest,
      int $languageId
    ): string {
      $prevMeta = json_decode($latest['metadata'] ?? '{}', true);
      $scorePrev = (int)($prevMeta['seo_score_before'] ?? 0);
      $scoreNow = (int)($seoData['seo_score'] ?? 0);
      $delta = $scoreNow - $scorePrev;
      $deltaColor = $delta > 0 ? 'success' : ($delta === 0 ? 'secondary' : 'danger');
      $deltaIcon = $delta > 0 ? 'bi-arrow-up-circle-fill' : ($delta === 0 ? 'bi-dash-circle' : 'bi-arrow-down-circle-fill');
      $suggestions = $prevMeta['suggestions'] ?? [];
      $auditResult = $prevMeta['audit_result'] ?? [];

      $out = '';

      // -- Score Delta Banner --
      $out .= '<div class="row g-2 mb-3">';

      $out .= '<div class="col-md-4"><div class="card border-secondary h-100"><div class="card-body text-center">';
      $out .= '<div class="text-muted small mb-1">' . $this->app->getDef('text_seo_archived_score') . '</div>';
      $out .= '<span class="badge bg-' . $this->scoreColor($scorePrev) . ' fs-5">' . $scorePrev . '/100</span>';
      $out .= '<div class="text-muted small mt-1">' . $this->formatDate($latest['date_modified']) . '</div>';
      $out .= '</div></div></div>';

      $out .= '<div class="col-md-4"><div class="card border-' . $deltaColor . ' h-100"><div class="card-body text-center">';
      $out .= '<div class="text-muted small mb-1">' . $this->app->getDef('text_seo_evolution') . '</div>';
      $out .= '<i class="bi ' . $deltaIcon . ' text-' . $deltaColor . ' fs-4"></i>';
      $out .= '<div class="fs-5 fw-bold text-' . $deltaColor . '">' . ($delta >= 0 ? '+' : '') . $delta . ' pts</div>';
      $out .= '</div></div></div>';

      $out .= '<div class="col-md-4"><div class="card border-' . $this->scoreColor($scoreNow) . ' h-100"><div class="card-body text-center">';
      $out .= '<div class="text-muted small mb-1">' . $this->app->getDef('text_seo_current_score_live') . '</div>';
      $out .= '<span class="badge bg-' . $this->scoreColor($scoreNow) . ' fs-5">' . $scoreNow . '/100</span>';
      $out .= '<div class="text-muted small mt-1">' . $this->app->getDef('text_seo_now') . '</div>';
      $out .= '</div></div></div>';

      $out .= '</div>';

      // T3.5 — Schema.org badge
      $out .= $this->renderSchemaBadge($seoData, 'category');

      // T4.1 + T4.4 — Thin content & JS render warnings
      $out .= $this->renderCategoryContentWarnings($seoData);

      // -- AI Audit Summary --
      if (!empty($auditResult['summary'])) {
        $auditIcon  = ($auditResult['improved'] ?? false) ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning';
        $out .= '<div class="alert alert-light border d-flex align-items-start gap-2 mb-3">';
        $out .= '<i class="bi ' . $auditIcon . ' fs-5 mt-1"></i>';
        $out .= '<div><strong>' . $this->app->getDef('text_seo_ai_audit') . '</strong><br />' . htmlspecialchars($auditResult['summary']) . '</div>';
        $out .= '</div>';
      }

      // -- Suggestions --
      if (!empty($suggestions)) {
        $out .= '<div class="card mb-3"><div class="card-header d-flex align-items-center gap-2">';
        $out .= '<i class="bi bi-lightbulb-fill text-warning"></i><strong>' . $this->app->getDef('text_seo_suggestions') . '</strong></div>';
        $out .= '<ul class="list-group list-group-flush">';

        $iconMap = [
          'title' => 'bi-type-h1', 'description' => 'bi-card-text', 'performance' => 'bi-speedometer2',
        ];

        foreach ($suggestions as $key => $value) {
          $icon = $iconMap[$key] ?? 'bi-arrow-right-circle';
          $out .= '<li class="list-group-item d-flex align-items-start gap-2">';
          $out .= '<i class="bi ' . $icon . ' text-primary mt-1"></i>';
          $out .= '<div><strong>' . strtoupper($key) . '</strong><br /><span class="text-muted">' . htmlspecialchars($value) . '</span></div></li>';
        }
        $out .= '</ul></div>';
      }

      // -- Action Buttons --
      $optUrl = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/SEO/optimize_category_seo.php';
      $out .= $this->renderActionButton($categoryId, $this->app->getDef('text_seo_run_optimize'), $optUrl, 'seo_run_optimize', 'btn-success', $languageId);
     // $out .= $this->renderReportsButton($categoryId);

      // -- Agentic Audit --
      if (!empty($agenticLatest)) {
        $out .= '<div class="alert alert-light border d-flex align-items-start gap-2 mb-3"><i class="bi bi-robot fs-5 mt-1"></i>';
        $out .= '<div><strong>' . $this->app->getDef('text_seo_agentic_audit') . '</strong><br />' . $this->app->getDef('text_seo_status_label') . ': <span class="badge bg-secondary">' . htmlspecialchars($agenticLatest['status']) . '</span> ';
        $out .= $this->app->getDef('text_seo_score_label') . ': <strong>' . (int)$agenticLatest['seo_score_before'] . ' -> ' . (int)$agenticLatest['seo_score_after'] . '</strong><br />';
        $out .= htmlspecialchars($agenticLatest['summary'] ?? '') . '</div></div>';
      }

      if (!empty($history)) {
        $out .= $this->renderHistory($history, $languageId);
      }

      return $out;
    }

    /**
     * Formats a date string into a localized format.
     * * @param string $dateString
     * @return string
     */
    private function formatDate(string $dateString): string
    {
      try {
        $dt = new \DateTimeImmutable($dateString);
        return $dt->format('d/m/Y H:i');
      } catch (\Throwable) {
        return $dateString;
      }
    }

    /**
     * Renders the history table of previous SEO actions.
     * * @param array $history
     * @return string
     */
    private function renderHistory(array $history, int $languageId = 0): string
    {
      $langName = (string)$languageId;
      try {
        foreach ($this->lang->getAll() as $l) {
          if ((int)($l['id'] ?? 0) === $languageId) {
            $langName = strtoupper((string)($l['code'] ?? $l['name'] ?? $languageId));
            break;
          }
        }
      } catch (\Throwable) {}

      $out  = '<div class="card mb-3"><div class="card-header d-flex align-items-center gap-2">';
      $out .= '<i class="bi bi-clock-history text-secondary"></i><strong>' . $this->app->getDef('text_seo_history') . '</strong></div>';
      $out .= '<div class="table-responsive"><table class="table table-sm table-hover mb-0">';
      $out .= '<thead class="table-light"><tr>'
        . '<th>' . $this->app->getDef('text_seo_table_date')       . '</th>'
        . '<th>' . $this->app->getDef('text_seo_table_type')       . '</th>'
        . '<th>' . $this->app->getDef('text_seo_table_source')     . '</th>'
        . '<th>' . ($this->app->getDef('text_seo_table_language') ?: 'Language') . '</th>'
        . '<th>' . $this->app->getDef('text_seo_table_score_prev') . '</th>'
        . '<th>' . $this->app->getDef('text_seo_table_score_new')  . '</th>'
        . '<th>' . $this->app->getDef('text_seo_table_status')     . '</th>'
        . '<th>' . ($this->app->getDef('text_seo_table_action') ?: 'Action') . '</th>'
        . '</tr></thead><tbody>';

      foreach ($history as $row) {
        $meta     = json_decode($row['metadata'] ?? '{}', true);
        $metaJson = htmlspecialchars(json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);

        $out .= '<tr>'
          . '<td class="text-muted small">' . $this->formatDate($row['date_modified']) . '</td>'
          . '<td>' . $this->typeLabel($row['type'] ?? '') . '</td>'
          . '<td class="text-muted small">' . htmlspecialchars($row['sourcename'] ?? '') . '</td>'
          . '<td><span class="badge bg-secondary">' . htmlspecialchars($langName) . '</span></td>'
          . '<td><span class="badge bg-' . $this->scoreColor((int)($meta['seo_score_before'] ?? 0)) . '">' . ($meta['seo_score_before'] ?? '—') . '</span></td>'
          . '<td><span class="badge bg-' . $this->scoreColor((int)($meta['seo_score_after']  ?? 0)) . '">' . ($meta['seo_score_after']  ?? '—') . '</span></td>'
          . '<td>' . $this->statusBadge($meta['status'] ?? '—') . '</td>'
          . '<td><button type="button" class="btn btn-outline-secondary btn-sm seo-view-btn" data-meta="' . $metaJson . '">'
          . '<i class="bi bi-eye me-1"></i>' . ($this->app->getDef('text_seo_view') ?: 'View') . '</button></td>'
          . '</tr>';
      }
      $out .= '</tbody></table></div></div>';

      // Modal XXL — detail view for each history row
      $out .= '
<div class="modal fade" id="seoHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>' . ($this->app->getDef('text_seo_history_detail') ?: 'Analysis detail') . '</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="seoHistoryModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . ($this->app->getDef('text_seo_modal_close') ?: 'Close') . '</button>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  document.addEventListener("click", function (e) {
    var btn = e.target.closest(".seo-view-btn");
    if (!btn) return;
    e.preventDefault();
    var meta = {};
    try { meta = JSON.parse(btn.getAttribute("data-meta") || "{}"); } catch (ex) {}
    document.getElementById("seoHistoryModalBody").innerHTML = buildSeoDetail(meta);
    bootstrap.Modal.getOrCreateInstance(document.getElementById("seoHistoryModal")).show();
  });

  function buildSeoDetail(m) {
    var h = "";
    var sb = m.seo_score_before != null ? m.seo_score_before : null;
    var sa = m.seo_score_after  != null ? m.seo_score_after  : null;
    if (sb !== null || sa !== null) {
      h += "<div class=\"d-flex gap-3 mb-3 flex-wrap\">";
      if (sb !== null) h += "<div class=\"card border-secondary text-center px-4 py-2\"><div class=\"text-muted small\">Score avant</div><span class=\"badge fs-5 bg-" + sc(sb) + "\">" + sb + "/100</span></div>";
      if (sa !== null) h += "<div class=\"card border-secondary text-center px-4 py-2\"><div class=\"text-muted small\">Score après</div><span class=\"badge fs-5 bg-" + sc(sa) + "\">" + sa + "/100</span></div>";
      h += "</div>";
    }
    var audit = m.audit_result || null;
    if (audit && audit.summary) {
      var ico = audit.improved ? "bi-check-circle-fill text-success" : "bi-exclamation-triangle-fill text-warning";
      h += "<div class=\"alert alert-light border d-flex align-items-start gap-2 mb-3\"><i class=\"bi " + ico + " fs-5 mt-1\"></i><div><strong>Audit</strong><br>" + esc(audit.summary) + "</div></div>";
    }
    var sugg = m.suggestions || null;
    if (sugg && typeof sugg === "object" && Object.keys(sugg).length) {
      h += "<div class=\"card mb-3\"><div class=\"card-header\"><i class=\"bi bi-lightbulb-fill text-warning me-1\"></i><strong>Suggestions</strong></div><ul class=\"list-group list-group-flush\">";
      Object.entries(sugg).forEach(function (kv) {
        h += "<li class=\"list-group-item\"><strong>" + esc(String(kv[0]).toUpperCase()) + "</strong><br><span class=\"text-muted\">" + esc(String(kv[1])) + "</span></li>";
      });
      h += "</ul></div>";
    }
    var raw = m.report_raw || null;
    if (raw && typeof raw === "object") {
      h += "<div class=\"mb-3\"><button class=\"btn btn-outline-secondary btn-sm\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#seoRawReport\"><i class=\"bi bi-code-slash me-1\"></i>Raw report</button>"
        + "<div class=\"collapse mt-2\" id=\"seoRawReport\"><pre class=\"bg-light p-3 rounded small\" style=\"max-height:400px;overflow:auto\">" + esc(JSON.stringify(raw, null, 2)) + "</pre></div></div>";
    }
    return h || "<p class=\"text-muted\">No detail available.</p>";
  }
  function sc(s) { return s >= 70 ? "success" : (s >= 40 ? "warning" : "danger"); }
  function esc(s) { return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"); }
})();
</script>';

      return $out;
    }

    /**
     * Returns a badge for the report type.
     * * @param string $type
     * @return string
     */
    private function typeLabel(string $type): string
    {
      return match ($type) {
        'initial_report'   => '<span class="badge bg-info text-dark">' . $this->app->getDef('text_seo_type_initial') . '</span>',
        'optimized_report' => '<span class="badge bg-primary">' . $this->app->getDef('text_seo_type_optimized') . '</span>',
        default            => '<span class="badge bg-light text-dark">' . htmlspecialchars($type) . '</span>',
      };
    }

    /**
     * Returns a badge for the report status.
     * * @param string $status
     * @return string
     */
    private function statusBadge(string $status): string
    {
      return match ($status) {
        'applied'   => '<span class="badge bg-success">'           . $this->app->getDef('text_seo_status_applied')   . '</span>',
        'completed' => '<span class="badge bg-primary">'           . $this->app->getDef('text_seo_status_completed') . '</span>',
        'pending'   => '<span class="badge bg-warning text-dark">' . $this->app->getDef('text_seo_status_pending')   . '</span>',
        'initial'   => '<span class="badge bg-info text-dark">'    . $this->app->getDef('text_seo_status_initial')   . '</span>',
        default     => '<span class="badge bg-light text-dark">'   . htmlspecialchars($status)                       . '</span>',
      };
    }

    /**
     * Wraps the content into the ClicShopping tab system structure.
     * * @param string $title
     * @param string $content
     * @return string
     */
    private function wrapInTab(string $title, string $content): string
    {
      return <<<EOD
<div class="tab-pane" id="section_SEOReportApp_content">
  <div class="mainTitle"><span class="col-md-12">{$title}</span></div>
  <div class="mt-1 p-3">{$content}</div>
</div>
<script>
$('#section_SEOReportApp_content').appendTo('#categoriesTabs .tab-content');
$('#categoriesTabs .nav-tabs').append('<li class="nav-item"><a data-bs-target="#section_SEOReportApp_content" role="tab" data-bs-toggle="tab" class="nav-link">{$title}</a></li>');
</script>
EOD;
    }
  }
