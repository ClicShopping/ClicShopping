<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Products;

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;

  /**
   * CockpitAI Hook for Product Sheet Integration
   *
   * This hook integrates the CockpitAI strategic analysis module into the product edit page.
   * It provides a dedicated tab for triggering and viewing product analysis.
   *
   * Hook Behavior:
   * - When pID is present: Display analysis interface with trigger button and results
   * - When pID is absent: Display configuration options (future implementation)
   *
   * Integration Pattern:
   * - Follows the same pattern as SeoChatGpt hook
   * - Uses JavaScript to append tab to product edit page
   * - AJAX calls to orchestrator for analysis execution
   *
   * @package ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Products
   */
  class CockpitAI implements \ClicShopping\OM\Modules\HooksInterface
  {
    public mixed $app;

    /**
     * Constructor - Initialize the Ecommerce application
     *
     * @return void
     */
    public function __construct()
    {
      if (!Registry::exists('Ecommerce')) {
        Registry::set('Ecommerce', new EcommerceApp());
      }

      $this->app = Registry::get('Ecommerce');
    }

    /**
     * Display the CockpitAI tab in the product edit page
     *
     * This method handles two modes:
     * 1. Product Edit Mode (pID present): Display analysis interface
     * 2. Configuration Mode (pID absent): Display module options
     *
     * @return bool|string Returns false if module is disabled, otherwise returns HTML content
     */
    public function display()
    {
      $requiredConstants = [
        'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
        'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
        'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
      ];

      foreach ($requiredConstants as $const) {
        if (!\defined($const) || \constant($const) !== 'True') {
          return false;
        }
      }

      $CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');

      // Load language definitions
      $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/cockpit_ai');

      if (isset($_GET['pID'])) {
        // Product Edit Mode - Display analysis interface
        $productId = HTML::sanitize($_GET['pID']);
        $productName = $CLICSHOPPING_ProductsAdmin->getProductsName($productId);

        $tab_title = $this->app->getDef('tab_cockpit_ai');
        $title = $this->app->getDef('tab_title');

        $ajaxUrl     = CLICSHOPPING::link('ClicShoppingAdmin/ajax/CockpitAI/analyze_product.php');
        $ajaxLoadUrl = CLICSHOPPING::link('ClicShoppingAdmin/ajax/CockpitAI/load_last_analysis.php');

        // Get language ID from URL parameter or session
        // In admin, the language_id parameter in URL indicates the product's language
        $languageId = Registry::get('Language')->getId();

        // Try to load last analysis from database
        $lastAnalysis = null;
        $hasAnalysis = false;

        try {
          $db = Registry::get('Db');

          $query = $db->prepare('SELECT metadata, 
                                        date_modified 
                                 FROM :table_products_cockpit_ai_embedding  
                                 WHERE entity_id = :entity_id 
                                 AND language_id = :language_id 
                                 ORDER BY date_modified DESC 
                                 LIMIT 1');

          $query->bindInt(':entity_id', (int)$productId);
          $query->bindInt(':language_id', $languageId);
          $query->execute();

          $result = $query->fetch();

          if ($result && !empty($result['metadata'])) {
            $lastAnalysis = json_decode($result['metadata'], true);
            if ($lastAnalysis) {
              $hasAnalysis = true;
            }
          }
        } catch (\Exception $e) {
          if (defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG')
              && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True') {
            error_log('CockpitAI Hook: Failed to load last analysis: ' . $e->getMessage());
          }
        }


        // Include custom CSS and JS
        $cssUrl = CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/CockpitAI/cockpit_ai.css');
        $jsUrl = CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/CockpitAI/cockpit_ai.js');

        // Determine initial display state
        $showAnalyzeButton = !$hasAnalysis;
        $showResults = $hasAnalysis;

        $content = '
        <div class="mt-3">
          <!-- Analysis Trigger Section -->
          <div class="row">
            <div class="col-md-12">
              <div class="card">
                <div class="card-header bg-primary text-white">
                  <h5 class="mb-0"><i class="bi bi-graph-up"></i> ' . $this->app->getDef('label_analysis') . '</h5>
                </div>
                <div class="card-body">
                  <div id="CockpitAI-analysis-container" style="display: ' . ($showAnalyzeButton ? 'block' : 'none') . ';">
                    <div class="text-center py-4">
                      <p class="lead">' . $this->app->getDef('text_no_analysis') . '</p>
                      <button type="button" class="btn btn-primary btn-lg" id="CockpitAI-analyze-btn" data-product-id="' . $productId . '">
                        <i class="bi bi-graph-up"></i> ' . $this->app->getDef('button_analyze') . '
                      </button>
                    </div>
                  </div>
                  
                  <div id="CockpitAI-loading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 lead">' . $this->app->getDef('text_analysis_in_progress') . '</p>
                    <p class="text-muted"><small>Pipeline: Data Collection → Scoring → SEO → RAG → LLM → Rules → Persistence</small></p>
                  </div>
                  
                  <div id="CockpitAI-error" class="alert alert-danger" style="display: none;">
                    <i class="bi bi-exclamation-triangle"></i> <span id="CockpitAI-error-message"></span>
                  </div>
                  
                  <div id="CockpitAI-results" style="display: ' . ($showResults ? 'block' : 'none') . ';">';

        // If we have analysis data, render it directly in PHP
        if ($hasAnalysis && $lastAnalysis) {
          $content .= $this->renderAnalysisResults($lastAnalysis);
          // NOTE: chart init for PHP-rendered results is handled by the main
          // $(document).ready() block below via _caiPhpRendered=true flag.
        }

        $content .= '
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Help Section -->
          <div class="row mt-3">
            <div class="col-md-12">
              <div class="accordion" id="CockpitAI-help-accordion">
                <div class="accordion-item">
                  <h2 class="accordion-header" id="help-heading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help-collapse" aria-expanded="false" aria-controls="help-collapse">
                      <i class="bi bi-info-circle me-2"></i> ' . $this->app->getDef('text_help_CockpitAI') . '
                    </button>
                  </h2>
                  <div id="help-collapse" class="accordion-collapse collapse" aria-labelledby="help-heading" data-bs-parent="#CockpitAI-help-accordion">
                    <div class="accordion-body">
                      <div class="row">
                        <div class="col-md-4">
                          <h6><i class="bi bi-star"></i> ' . $this->app->getDef('label_score_x') . '</h6>
                          <p class="small">' . $this->app->getDef('text_help_score_x') . '</p>
                        </div>
                        <div class="col-md-4">
                          <h6><i class="bi bi-graph-up-arrow"></i> ' . $this->app->getDef('label_score_y') . '</h6>
                          <p class="small">' . $this->app->getDef('text_help_score_y') . '</p>
                        </div>
                        <div class="col-md-4">
                          <h6><i class="bi bi-grid-3x3"></i> ' . $this->app->getDef('label_quadrant') . '</h6>
                          <p class="small">' . $this->app->getDef('text_help_quadrants') . '</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <link rel="stylesheet" href="' . $cssUrl . '">
        <script src="' . $jsUrl . '"></script>
        
        <script>
        function caiInit() {
          if (window._caiInitDone) return;
          window._caiInitDone = true;
          var ajaxUrl     = "' . $ajaxUrl . '";
          var ajaxLoadUrl = "' . $ajaxLoadUrl . '";
          var productId   = ' . $productId . ';
          var languageId  = ' . $languageId . ';

          // Chart.js instances — kept at ready() scope so refresh can destroy them
          var caiChartY = null;

          // ── Chart helper ──────────────────────────────────────────────────
          // Draws a Chart.js line chart for Score Y history on a given canvas.
          function caiDrawLineChart(canvasId, history, scoreKey, color) {
            var canvas = document.getElementById(canvasId);
            if (!canvas || !history || history.length === 0) return null;
            if (typeof Chart === "undefined") return null;

            var labels = history.map(function(h) { return h.date || ""; });
            var values = history.map(function(h) { return parseFloat(h[scoreKey] || 0); });
            var ptR    = values.length <= 20 ? 4 : 2;

            return new Chart(canvas, {
              type: "line",
              data: {
                labels: labels,
                datasets: [{
                  data: values,
                  borderColor: color,
                  backgroundColor: color + "22",
                  borderWidth: 2,
                  pointRadius: ptR,
                  pointHoverRadius: ptR + 2,
                  pointBackgroundColor: color,
                  pointBorderColor: "#fff",
                  pointBorderWidth: 1.5,
                  fill: true,
                  tension: 0.35
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                  y: {
                    min: 0, max: 100,
                    ticks: { stepSize: 20, font: { size: 10 } },
                    grid: { color: "#f0f2f5" }
                  },
                  x: {
                    ticks: { font: { size: 9 }, maxRotation: 30, autoSkip: true, maxTicksLimit: 10 },
                    grid: { display: false }
                  }
                },
                plugins: {
                  legend: { display: false },
                  tooltip: { callbacks: { label: function(c) { return " " + c.parsed.y.toFixed(1) + " / 100"; } } },
                  annotation: {
                    annotations: {
                      tHigh: { type:"line", yMin:70, yMax:70, borderColor:"#16a34a55", borderWidth:1, borderDash:[4,4] },
                      tLow:  { type:"line", yMin:30, yMax:30, borderColor:"#dc262655", borderWidth:1, borderDash:[4,4] }
                    }
                  }
                }
              }
            });
          }

          // ── Fetch history and init Score Y chart (for PHP-rendered results) ─
          var _phpRendered = ' . ($hasAnalysis ? 'true' : 'false') . ';

          if (_phpRendered) {
            // PHP already rendered results — fetch history and bind collapse for Score Y
            $.getJSON(ajaxLoadUrl, {product_id: productId, language_id: languageId, history: 1}, function(resp) {
              var hist = (resp && resp.history && resp.history.length > 0) ? resp.history : [];
              if (hist.length > 0) {
                // #factors-y is already in the PHP-rendered DOM — bind directly
                var $fyPhp = $("#factors-y");
                $fyPhp.off("shown.bs.collapse.caiInit").on("shown.bs.collapse.caiInit", function() {
                  if (!caiChartY) {
                    caiChartY = caiDrawLineChart("cai-chart-y", hist, "score_y", "#198754");
                  }
                  $fyPhp.off("shown.bs.collapse.caiInit");
                });
              }
            });
          } else {
            // No PHP results — try to load last analysis via AJAX on page open
            $.ajax({
              url: ajaxLoadUrl,
              type: "GET",
              data: { product_id: productId, language_id: languageId },
              dataType: "json",
              success: function(response) {
                if (response && response.success && response.data) {
                  displayAnalysisResults(response.data);
                  $("#CockpitAI-results").show();
                  $("#CockpitAI-analysis-container").hide();
                }
              },
              error: function() { /* silent fail */ }
            });
          }

          // ── Refresh button ────────────────────────────────────────────────
          $("body").on("click", "#CockpitAI-refresh-btn", function() {
            if (caiChartY) { caiChartY.destroy(); caiChartY = null; }
            $("#CockpitAI-results").hide();
            $("#CockpitAI-loading").hide();
            $("#CockpitAI-error").hide();
            $("#CockpitAI-analysis-container").show();
          });

          // ── Analyze button ────────────────────────────────────────────────
          $("body").on("click", "#CockpitAI-analyze-btn", function() {
            var pid = $(this).data("product-id");
            $("#CockpitAI-analysis-container").hide();
            $("#CockpitAI-error").hide();
            $("#CockpitAI-results").hide();
            $("#CockpitAI-loading").show();

            $.ajax({
              url: ajaxUrl,
              type: "POST",
              data: { product_id: pid, language_id: languageId },
              dataType: "json",
              success: function(response) {
                console.log("CockpitAI response:", response);
                $("#CockpitAI-loading").hide();
                if (response.success) {
                  displayAnalysisResults(response.data);
                  $("#CockpitAI-results").show();
                } else {
                  $("#CockpitAI-error-message").text(response.error || "' . $this->app->getDef('text_analysis_error') . '");
                  $("#CockpitAI-error").show();
                  $("#CockpitAI-analysis-container").show();
                }
              },
              error: function(xhr, status, error) {
                $("#CockpitAI-loading").hide();
                $("#CockpitAI-error-message").text("' . $this->app->getDef('text_analysis_error') . ' (" + error + ")");
                $("#CockpitAI-error").show();
                $("#CockpitAI-analysis-container").show();
              }
            });
          });

          // ── displayAnalysisResults ────────────────────────────────────────
          function displayAnalysisResults(data) {
            var html = "";

            // ── Header ──────────────────────────────────────────────────────
            var seoStatus = data.header?.seo_status || "NOT_ANALYZED";
            var seoClass  = seoStatus === "ANALYZED" ? "success" : "warning";
            var analysisDate = data.header?.analysis_date ? new Date(data.header.analysis_date).toLocaleString() : "";
            var seoScore  = data.header?.seo_score;
            var seoStale  = data.header?.seo_data_stale === true;

            html += "<div class=\"alert alert-success mb-3\">";
            html += "  <div class=\"d-flex justify-content-between align-items-center\">";
            html += "    <div>";
            html += "      <h5 class=\"mb-1\"><i class=\"bi bi-check-circle\"></i> ' . $this->app->getDef('text_analysis_success') . '</h5>";
            html += "      <small>" + analysisDate + " | " + (data.technical?.pipeline_duration_ms || 0).toFixed(0) + "ms</small>";
            html += "      <span class=\"badge bg-" + seoClass + " ms-2\">SEO: " + seoStatus;
            if (seoScore !== null && seoScore !== undefined && seoScore > 0) {
              html += " " + Math.round(seoScore) + "/100";
            }
            html += "</span>";
            html += "    </div>";
            html += "  </div>";
            html += "</div>";

            // Stale-SEO banner: shown when a fresh SEO optimization happened
            // AFTER the cached CockpitAI analysis.  The seo_status badge
            // above already reflects the latest SEO state, but Score X / Y /
            // quadrant / recommendations are still based on the cached
            // snapshot — re-running CockpitAI will refresh them.
            if (seoStale) {
              html += "<div class=\"alert alert-warning d-flex align-items-center mb-3\">";
              html += "  <i class=\"bi bi-arrow-clockwise me-2\"></i>";
              html += "  <div>SEO has been re-optimized since the last CockpitAI analysis. ";
              html += "    The Score X / Y and recommendations below are based on the previous state — ";
              html += "    re-run the analysis to refresh them.";
              html += "  </div>";
              html += "</div>";
            }

            // ── Score X & Y cards ───────────────────────────────────────────
            var scoreX = parseFloat(data.score_x?.value || 0);
            var scoreY = parseFloat(data.score_y?.value || 0);

            html += "<div class=\"row mb-3\">";

            // Score X card — quadrant distribution + recommendation
            var qCode      = data.quadrant?.code     || "Q_intermediate";
            var qLabel     = data.quadrant?.label    || qCode;
            var qStrategy  = data.quadrant?.strategy || "";
            var qColors    = {Q1:"#16a34a", Q2:"#2563eb", Q3:"#dc2626", Q4:"#d97706", Q_intermediate:"#9ca3af"};
            var qDescs     = {
              Q1: "High quality \u00b7 High commercial",
              Q2: "High quality \u00b7 Low commercial",
              Q3: "Low quality \u00b7 Low commercial",
              Q4: "Low quality \u00b7 High commercial",
              Q_intermediate: "Transition zone"
            };
            var qBorderColor = qColors[qCode] || "#9ca3af";

            html += "  <div class=\"col-md-6\">";
            html += "    <div class=\"card\" style=\"border-color:" + qBorderColor + ";border-width:2px;\">";
            html += "      <div class=\"card-header text-white\" style=\"background:" + qBorderColor + ";\">";
            html += "        <h6 class=\"mb-0\"><i class=\"bi bi-star\"></i> ' . $this->app->getDef('label_score_x') . ' &mdash; " + scoreX.toFixed(1) + "/100</h6>";
            html += "      </div>";
            html += "      <div class=\"card-body\">";
            // Score bar
            html += "        <div class=\"progress mb-3\" style=\"height:16px;\">";
            html += "          <div class=\"progress-bar\" role=\"progressbar\" style=\"width:" + scoreX + "%;background:" + qBorderColor + ";\">" + scoreX.toFixed(1) + "%</div>";
            html += "        </div>";
            // Quadrant distribution bars
            var qDefs = [
              {code:"Q1",    label:"Q1 Scaling",      desc:"High quality \u00b7 High commercial", color:"#16a34a"},
              {code:"Q2",    label:"Q2 Acquisition",  desc:"High quality \u00b7 Low commercial",  color:"#2563eb"},
              {code:"Q3",    label:"Q3 Rework/Kill",  desc:"Low quality \u00b7 Low commercial",   color:"#dc2626"},
              {code:"Q4",    label:"Q4 Optimization", desc:"Low quality \u00b7 High commercial",  color:"#d97706"},
              {code:"Q_intermediate", label:"Monitoring", desc:"Transition zone",                 color:"#9ca3af"}
            ];
            // Build chart using Chart.js horizontal bar
            html += "        <canvas id=\"cai-chart-quadrant\" style=\"max-height:200px;\"></canvas>";
            // Store quadrant data for after html() injection
            html += "        <div class=\"mt-2 pt-2 border-top\">";
            html += "          <span class=\"badge\" style=\"background:" + qBorderColor + ";font-size:12px;\">" + qCode + " — " + qLabel + "</span>";
            html += "          <p class=\"mt-1 mb-0 text-muted\" style=\"font-size:12px;\">" + qStrategy + "</p>";
            html += "        </div>";
            html += "      </div>";
            html += "    </div>";
            html += "  </div>";

            // Score Y card
            html += "  <div class=\"col-md-6\">";
            html += "    <div class=\"card border-success\">";
            html += "      <div class=\"card-header bg-success text-white\">";
            html += "        <h6 class=\"mb-0\"><i class=\"bi bi-graph-up-arrow\"></i> ' . $this->app->getDef('label_score_y') . '</h6>";
            html += "      </div>";
            html += "      <div class=\"card-body text-center\">";
            html += "        <h2 class=\"display-4 text-success mb-2\">" + scoreY.toFixed(1) + "<small class=\"text-muted\">/100</small></h2>";
            html += "        <div class=\"progress mb-2\" style=\"height: 20px;\">";
            html += "          <div class=\"progress-bar bg-success\" role=\"progressbar\" style=\"width:" + scoreY + "%\">" + scoreY.toFixed(1) + "%</div>";
            html += "        </div>";
            if (data.score_y?.factors && Object.keys(data.score_y.factors).length > 0) {
              // Extract key commercial metrics directly from Y factors
              var fy = data.score_y.factors;
              html += "        <button class=\"btn btn-sm btn-outline-success\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#factors-y\" aria-expanded=\"false\">";
              html += "          <i class=\"bi bi-list\"></i> Metrics";
              html += "        </button>";
              html += "        <div class=\"collapse mt-2\" id=\"factors-y\">";
              html += "          <ul class=\"list-group list-group-flush text-start\">";
              if (fy.views)      html += "            <li class=\"list-group-item\"><i class=\"bi bi-eye\"></i> Views: <strong>" + (fy.views.value ?? "N/A") + "</strong></li>";
              if (fy.orders)     html += "            <li class=\"list-group-item\"><i class=\"bi bi-cart\"></i> Orders: <strong>" + (fy.orders.value ?? "N/A") + "</strong></li>";
              if (fy.conversion) html += "            <li class=\"list-group-item\"><i class=\"bi bi-percent\"></i> Conversion: <strong>" + ((parseFloat(fy.conversion.value || 0)) * 100).toFixed(2) + "%</strong></li>";
              if (fy.returns)    html += "            <li class=\"list-group-item\"><i class=\"bi bi-arrow-return-left\"></i> Returns: <strong>" + (fy.returns.value ?? "N/A") + "</strong></li>";
              if (fy.velocity)   html += "            <li class=\"list-group-item\"><i class=\"bi bi-lightning-charge\"></i> Velocity: <strong>" + (fy.velocity.value !== null && fy.velocity.value !== undefined ? parseFloat(fy.velocity.value).toFixed(2) : "N/A") + "</strong></li>";
              html += "          </ul>";
              html += "          <div class=\"mt-3 pt-2 border-top\">";
              html += "            <p class=\"text-muted mb-1\" style=\"font-size:11px;text-transform:uppercase;letter-spacing:.5px;font-weight:600;\">Score Y — historical evolution</p>";
              html += "            <div style=\"position:relative;height:200px;\"><canvas id=\"cai-chart-y\"></canvas></div>";
              html += "          </div>";
              html += "        </div>";
            }
            html += "      </div>";
            html += "    </div>";
            html += "  </div>";

            html += "</div>";

            // ── Quadrant ────────────────────────────────────────────────────
            if (data.quadrant) {
              var qCode = data.quadrant.code || "Q_intermediate";
              var qColor = qCode === "Q1" ? "success" : qCode === "Q2" ? "warning"
                         : qCode === "Q3" ? "danger"  : qCode === "Q4" ? "info" : "secondary";
              // JSON uses .strategy (not .explanation)
              html += "<div class=\"row mb-3\">";
              html += "  <div class=\"col-md-12\">";
              html += "    <div class=\"card border-" + qColor + "\">";
              html += "      <div class=\"card-header bg-" + qColor + " text-white\">";
              html += "        <h6 class=\"mb-0\"><i class=\"bi bi-grid-3x3\"></i> ' . $this->app->getDef('label_quadrant') . ': " + qCode + "</h6>";
              html += "      </div>";
              html += "      <div class=\"card-body\">";
              html += "        <h5>" + (data.quadrant.label || "") + "</h5>";
              html += "        <p class=\"mb-0\">" + (data.quadrant.strategy || "") + "</p>";
              html += "      </div>";
              html += "    </div>";
              html += "  </div>";
              html += "</div>";
            }

            // ── LLM Analysis ────────────────────────────────────────────────
            // JSON: data.analysis.text (not data.llm_analysis.analysis)
            if (data.analysis && data.analysis.text) {
              html += "<div class=\"row mb-3\">";
              html += "  <div class=\"col-md-12\">";
              html += "    <div class=\"card\">";
              html += "      <div class=\"card-header bg-light\">";
              html += "        <h6 class=\"mb-0\"><i class=\"bi bi-chat-left-text\"></i> ' . $this->app->getDef('label_analysis') . '";
              if (data.analysis.fallback_used) {
                html += " <span class=\"badge bg-warning\">fallback</span>";
              }
              html += "        </h6>";
              html += "      </div>";
              html += "      <div class=\"card-body\">";
              html += "        <p class=\"lead\">" + data.analysis.text + "</p>";
              html += "      </div>";
              html += "    </div>";
              html += "  </div>";
              html += "</div>";
            }

            // ── Action plan ─────────────────────────────────────────────────
            if (data.action_plan && data.action_plan.actions && data.action_plan.actions.length > 0) {
              html += "<div class=\"row mb-3\">";
              html += "  <div class=\"col-md-12\">";
              html += "    <div class=\"card\">";
              html += "      <div class=\"card-header bg-light\">";
              html += "        <h6 class=\"mb-0\"><i class=\"bi bi-list-check\"></i> ' . $this->app->getDef('label_actions') . ' (" + data.action_plan.actions.length + ")</h6>";
              html += "      </div>";
              html += "      <div class=\"card-body\">";
              html += "        <div class=\"list-group\">";
              data.action_plan.actions.forEach(function(action) {
                var pClass = action.priority === "critical" ? "danger"
                           : action.priority === "high"     ? "warning"
                           : action.priority === "medium"   ? "info" : "secondary";
                var pIcon  = action.priority === "critical" ? "exclamation-triangle"
                           : action.priority === "high"     ? "exclamation-circle"
                           : action.priority === "medium"   ? "info-circle" : "check-circle";
                html += "          <div class=\"list-group-item\">";
                html += "            <span class=\"badge bg-" + pClass + " me-2\"><i class=\"bi bi-" + pIcon + "\"></i> " + action.priority.toUpperCase() + "</span>";
                html += "            <strong>" + action.label + "</strong>";
                if (action.exclusive) html += " <span class=\"badge bg-dark ms-1\">EXCLUSIVE</span>";
                if (action.description) html += "<p class=\"mb-0 mt-1 text-muted\"><small>" + action.description + "</small></p>";
                html += "          </div>";
              });
              html += "        </div>";
              html += "      </div>";
              html += "    </div>";
              html += "  </div>";
              html += "</div>";
            }

            // ── History (RAG strings) ───────────────────────────────────────
            // JSON: history[] = array of raw content strings from EmbeddingService
            if (data.history && data.history.length > 0) {
              html += "<div class=\"row mb-3\">";
              html += "  <div class=\"col-md-12\">";
              html += "    <div class=\"card\">";
              html += "      <div class=\"card-header bg-light\">";
              html += "        <h6 class=\"mb-0\"><i class=\"bi bi-clock-history\"></i>' . $this->app->getDef('text_analysis_hiytory') . ' (" + data.history.length + ")</h6>";
              html += "      </div>";
              html += "      <div class=\"card-body\">";
              data.history.forEach(function(entry, idx) {
                var content = typeof entry === "object" ? (entry.content || JSON.stringify(entry)) : entry;
                
                html += "        <div class=\"mb-2\">";
                html += "          <small class=\"text-muted\">' . $this->app->getDef('text_analysis') . ' #" + (data.history.length - idx) + "</small>";
                html += "          <pre class=\"bg-light p-2 rounded small mb-0\" style=\"white-space:pre-wrap\">" + content + "</pre>";
                html += "        </div>";
              });
              html += "      </div>";
              html += "    </div>";
              html += "  </div>";
              html += "</div>";
            }

            // ── Buttons ─────────────────────────────────────────────────────
            html += "<div class=\"row\">";
            html += "  <div class=\"col-md-12 text-center\">";
            html += "    <button type=\"button\" class=\"btn btn-primary\" id=\"CockpitAI-refresh-btn\">";
            html += "      <i class=\"bi bi-arrow-clockwise\"></i> ' . $this->app->getDef('button_refresh') . '";
            html += "    </button>";
            html += "    <button type=\"button\" class=\"btn btn-outline-secondary ms-2\" onclick=\"window.print()\">";
            html += "      <i class=\"bi bi-printer\"></i> Print";
            html += "    </button>";
            html += "  </div>";
            html += "</div>";

            $("#CockpitAI-results").html(html);

            // ── Draw quadrant distribution chart immediately (canvas is now in DOM) ──
            (function() {
              var canvas = document.getElementById("cai-chart-quadrant");
              if (!canvas || typeof Chart === "undefined") return;

              var qDefs = [
                {code:"Q1",             label:"Q1 Scaling",      color:"#16a34a"},
                {code:"Q2",             label:"Q2 Acquisition",  color:"#2563eb"},
                {code:"Q3",             label:"Q3 Rework/Kill",  color:"#dc2626"},
                {code:"Q4",             label:"Q4 Optimization", color:"#d97706"},
                {code:"Q_intermediate", label:"Monitoring",      color:"#9ca3af"}
              ];
              var currentQ  = qCode;
              var barValues = [], barColors = [], barLabels = [];
              qDefs.forEach(function(q) {
                barLabels.push(q.label);
                barColors.push(q.code === currentQ ? q.color : q.color + "55");
                barValues.push(q.code === currentQ ? parseFloat(scoreX) : 0);
              });

              new Chart(canvas, {
                type: "bar",
                data: {
                  labels: barLabels,
                  datasets: [{
                    data: barValues, backgroundColor: barColors,
                    borderColor: barColors.map(function(c){ return c.substring(0,7); }),
                    borderWidth: 1, borderRadius: 4
                  }]
                },
                options: {
                  indexAxis: "y", responsive: true, maintainAspectRatio: false,
                  scales: {
                    x: { min:0, max:100, ticks:{stepSize:20,font:{size:10}}, grid:{color:"#f0f2f5"} },
                    y: { ticks:{font:{size:10}}, grid:{display:false} }
                  },
                  plugins: {
                    legend: {display:false},
                    tooltip: { callbacks: { label: function(ctx) {
                      return ctx.parsed.x > 0 ? " Score X: "+ctx.parsed.x.toFixed(1)+" — current position" : " Not current quadrant";
                    }}}
                  }
                }
              });
            })();

            // ── Load history then bind Score Y chart on collapse open ─────────
            // IMPORTANT: #factors-y was just injected into the DOM via .html() above.
            // We must query it AFTER injection and attach the listener to the live element.
            // History fetch is async — we store the listener setup inside the ajax callbacks
            // so caiHistory is guaranteed to be populated before the chart draws.
            if (caiChartY) { caiChartY.destroy(); caiChartY = null; }

            var _fallbackHistory = [{ date: (data.header?.analysis_date||"").substring(0,10),
                                      score_x: parseFloat(data.score_x?.value||0),
                                      score_y: parseFloat(data.score_y?.value||0) }];

            function _bindChartY(hist) {
              // #factors-y is already in the DOM (injected above) — select it directly
              var $fy = $("#CockpitAI-results").find("#factors-y");
              $fy.off("shown.bs.collapse.caiPost").on("shown.bs.collapse.caiPost", function() {
                if (!caiChartY) {
                  caiChartY = caiDrawLineChart("cai-chart-y", hist, "score_y", "#198754");
                }
                $fy.off("shown.bs.collapse.caiPost");
              });
            }

            $.ajax({
              url: ajaxLoadUrl, type: "GET",
              data: { product_id: productId, language_id: languageId, history: 1 },
              dataType: "json",
              success: function(resp) {
                var hist = (resp && resp.history && resp.history.length > 0) ? resp.history : _fallbackHistory;
                _bindChartY(hist);
              },
              error: function() { _bindChartY(_fallbackHistory); }
            });
          }

        }
        </script>
      ';

        $output = <<<EOD
<!-- ######################## -->
<!--  Start CockpitAI Tab  -->
<!-- ######################## -->
<div class="tab-pane" id="section_CockpitAI_content">
  <div class="mainTitle">
    <span class="col-md-2">{$title}</span>
  </div>
  {$content}
</div>
<script>
$('#section_CockpitAI_content').appendTo('#productsTabs .tab-content');
$('#productsTabs .nav-tabs').append('<li class="nav-item"><a data-bs-target="#section_CockpitAI_content" role="tab" data-bs-toggle="tab" class="nav-link">{$tab_title}</a></li>');
caiInit();
</script>
<!-- ######################## -->
<!-- End CockpitAI Tab  -->
<!-- ######################## -->
EOD;
      } else {
        // Configuration Mode - Display module options (future implementation)
        $tab_title = $this->app->getDef('tab_cockpit_ai');
        $title = $this->app->getDef('text_config_title');

        $content = '
        <div class="mt-1"></div>
        <div class="alert alert-info" role="alert">
          <div><h4><i class="bi bi-gear"></i> ' . $title . '</h4></div>
          <div class="mt-1"></div>
          <div>Configuration options will be implemented in subsequent tasks.</div>
          <div class="mt-2">
            <p><strong>' . $this->app->getDef('text_config_t_high') . ':</strong> ' . (\defined('CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH') ? (float)CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH : 70.0) . '</p>
            <p><strong>' . $this->app->getDef('text_config_t_low') . ':</strong> ' . (\defined('CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW') ? (float)CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW : 30.0) . '</p>
            <p><strong>' . $this->app->getDef('text_config_strategy_x') . ':</strong> ' . (\defined('CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_X') ? CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_X : 'quality') . '</p>
            <p><strong>' . $this->app->getDef('text_config_strategy_y') . ':</strong> ' . (\defined('CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_Y') ? CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_Y : 'performance') . '</p>
          </div>
        </div>
      ';

        $output = <<<EOD
<!-- ######################## -->
<!--  Start CockpitAI Options  -->
<!-- ######################## -->
<div class="tab-pane" id="section_CockpitAI_Options_content">
  <div class="mainTitle">
    <span class="col-md-2">{$title}</span>
  </div>
  {$content}
</div>
<script>
$('#section_CockpitAI_Options_content').appendTo('#productsTabs .tab-content');
$('#productsTabs .nav-tabs').append('<li class="nav-item"><a data-bs-target="#section_CockpitAI_Options_content" role="tab" data-bs-toggle="tab" class="nav-link">{$tab_title}</a></li>');
</script>
<!-- ######################## -->
<!-- End CockpitAI Options  -->
<!-- ######################## -->
EOD;
      }

      return $output;
    }

    /**
     * Render analysis results as HTML
     *
     * @param array $data Analysis metadata from database
     * @return string HTML content
     */
    private function renderAnalysisResults(array $data): string
    {
      $html = '';

      // Header - adapt to actual metadata structure
      $seoStatus = $data['seo']['status'] ?? 'NOT_ANALYZED';
      $seoClass = $seoStatus === 'ANALYZED' ? 'success' : 'warning';
      $analysisDate = $data['technical']['timestamp'] ?? date('Y-m-d H:i:s');
      $pipelineDuration = $data['technical']['pipeline_duration_ms'] ?? 0;

      $html .= '<div class="alert alert-success mb-3">';
      $html .= '  <div class="d-flex justify-content-between align-items-center">';
      $html .= '    <div>';
      $html .= '      <h5 class="mb-1"><i class="bi bi-check-circle"></i> ' . $this->app->getDef('text_analysis_success') . '</h5>';
      $html .= '      <small>' . $analysisDate . ' | ' . round($pipelineDuration) . 'ms</small>';
      $html .= '      <span class="badge bg-' . $seoClass . ' ms-2">SEO: ' . $seoStatus . '</span>';
      $html .= '    </div>';
      $html .= '  </div>';
      $html .= '</div>';

      // Scores - adapt to actual metadata structure
      $scoreX = $data['scores']['score_x'] ?? 0;
      $scoreY = $data['scores']['score_y'] ?? 0;

      $html .= '<div class="row mb-3">';

      // Score X
      $html .= '  <div class="col-md-6">';
      $html .= '    <div class="card border-primary">';
      $html .= '      <div class="card-header bg-primary text-white">';
      $html .= '        <h6 class="mb-0"><i class="bi bi-star"></i> ' . $this->app->getDef('label_score_x') . '</h6>';
      $html .= '      </div>';
      $html .= '      <div class="card-body text-center">';
      $html .= '        <h2 class="display-4 text-primary mb-2">' . number_format($scoreX, 1) . '<small class="text-muted">/100</small></h2>';
      $html .= '        <div class="progress mb-2" style="height: 20px;">';
      $html .= '          <div class="progress-bar bg-primary" role="progressbar" style="width:' . $scoreX . '%">' . number_format($scoreX, 1) . '%</div>';
      $html .= '        </div>';

      // Quadrant horizontal bar chart (Chart.js) + recommendation
      $qCode        = $data['scores']['quadrant'] ?? 'Q_intermediate';
      $qHexColors   = ['Q1'=>'#16a34a','Q2'=>'#2563eb','Q3'=>'#dc2626','Q4'=>'#d97706','Q_intermediate'=>'#9ca3af'];
      $qBorderColor = $qHexColors[$qCode] ?? '#9ca3af';
      $qDescMap     = [
        'Q1'=>'High quality · High commercial','Q2'=>'High quality · Low commercial',
        'Q3'=>'Low quality · Low commercial',  'Q4'=>'Low quality · High commercial',
        'Q_intermediate'=>'Transition zone',
      ];
      $qStrategyMap = [
        'Q1'=>'Maintain and amplify.',
        'Q2'=>'Improve visibility and commercial reach.',
        'Q3'=>'Major rework required or consider removal.',
        'Q4'=>'Improve product sheet quality to unlock sales potential.',
        'Q_intermediate'=>'Monitor and maintain — no urgent action required.',
      ];
      $qRows = [
        ['Q1','Q1 Scaling'],['Q2','Q2 Acquisition'],['Q3','Q3 Rework/Kill'],
        ['Q4','Q4 Optimization'],['Q_intermediate','Monitoring'],
      ];
      $jsLabels   = json_encode(array_column($qRows, 1));
      $jsValues   = json_encode(array_map(fn($r) => $r[0] === $qCode ? (float)$scoreX : 0, $qRows));
      $jsBgColors = json_encode(array_map(
        fn($r) => $r[0] === $qCode ? ($qHexColors[$r[0]] ?? '#9ca3af') : ($qHexColors[$r[0]] ?? '#9ca3af') . '44',
        $qRows
      ));
      $html .= '        <button class="btn btn-sm btn-outline-primary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#factors-x" aria-expanded="false">';
      $html .= '          <i class="bi bi-grid-3x3"></i> Quality breakdown';
      $html .= '        </button>';
      $html .= '        <div class="collapse mb-2" id="factors-x">';
      $html .= '          <canvas id="cai-chart-quadrant" style="max-height:200px;width:100%;"></canvas>';
      $html .= '        </div>';
      $html .= '        <div class="mt-2 pt-2 border-top text-start">';
      $html .= '          <span class="badge mb-1" style="background:' . $qBorderColor . ';font-size:12px;">' . htmlspecialchars($qCode . ' — ' . ($qDescMap[$qCode] ?? '')) . '</span>';
      $html .= '          <p class="mb-0 text-muted" style="font-size:12px;">' . htmlspecialchars($qStrategyMap[$qCode] ?? '') . '</p>';
      $html .= '        </div>';
      $html .= '        <script>(function(){';
      $html .= '          function _caiDrawQChart(){';
      $html .= '            var c=document.getElementById("cai-chart-quadrant");';
      $html .= '            if(!c||typeof Chart==="undefined")return;';
      $html .= '            if(c._caiDone)return; c._caiDone=true;';
      $html .= '            new Chart(c,{type:"bar",data:{labels:' . $jsLabels . ',datasets:[{data:' . $jsValues . ',backgroundColor:' . $jsBgColors . ',borderRadius:4}]},';
      $html .= '            options:{indexAxis:"y",responsive:true,maintainAspectRatio:false,';
      $html .= '            scales:{x:{min:0,max:100,ticks:{stepSize:20,font:{size:10}},grid:{color:"#f0f2f5"}},y:{ticks:{font:{size:10}},grid:{display:false}}},';
      $html .= '            plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return c.parsed.x>0?" Score X: "+c.parsed.x.toFixed(1)+" (current)":" Not current quadrant";}}}}}});';
      $html .= '          }';
      $html .= '          var el=document.getElementById("factors-x");';
      $html .= '          if(el){el.addEventListener("shown.bs.collapse",_caiDrawQChart);}';
      $html .= '        })();</script>';

      $html .= '      </div>';
      $html .= '    </div>';
      $html .= '  </div>';

      // Score Y
      $html .= '  <div class="col-md-6">';
      $html .= '    <div class="card border-success">';
      $html .= '      <div class="card-header bg-success text-white">';
      $html .= '        <h6 class="mb-0"><i class="bi bi-graph-up-arrow"></i> ' . $this->app->getDef('label_score_y') . '</h6>';
      $html .= '      </div>';
      $html .= '      <div class="card-body text-center">';
      $html .= '        <h2 class="display-4 text-success mb-2">' . number_format($scoreY, 1) . '<small class="text-muted">/100</small></h2>';
      $html .= '        <div class="progress mb-2" style="height: 20px;">';
      $html .= '          <div class="progress-bar bg-success" role="progressbar" style="width:' . $scoreY . '%">' . number_format($scoreY, 1) . '%</div>';
      $html .= '        </div>';

      // Toggle button + metrics + chart canvas — mirrors displayAnalysisResults() JS
      $factorsY  = $data['factors_y'] ?? [];
      $commMet   = $data['commercial_metrics'] ?? [];
      $hasFactorsY = !empty($factorsY) || !empty($commMet);
      if ($hasFactorsY) {
        $html .= '        <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#factors-y" aria-expanded="false">';
        $html .= '          <i class="bi bi-list"></i> Metrics';
        $html .= '        </button>';
        $html .= '        <div class="collapse mt-2" id="factors-y">';
        $html .= '          <ul class="list-group list-group-flush text-start">';
        // Commercial metrics summary
        if (!empty($commMet)) {
          if (isset($commMet['views_30d']))      $html .= '<li class="list-group-item"><i class="bi bi-eye"></i> Views: <strong>' . $commMet['views_30d'] . '</strong></li>';
          if (isset($commMet['orders']))         $html .= '<li class="list-group-item"><i class="bi bi-cart"></i> Orders: <strong>' . $commMet['orders'] . '</strong></li>';
          if (isset($commMet['conversion_rate'])) $html .= '<li class="list-group-item"><i class="bi bi-percent"></i> Conversion: <strong>' . number_format((float)$commMet['conversion_rate'] * 100, 2) . '%</strong></li>';
          if (isset($commMet['returns']))        $html .= '<li class="list-group-item"><i class="bi bi-arrow-return-left"></i> Returns: <strong>' . $commMet['returns'] . '</strong></li>';
        }
        // Velocity from factors_y if available
        if (isset($factorsY['velocity'])) {
          $velVal = $factorsY['velocity']['value'] ?? null;
          $velStr = $velVal !== null ? number_format((float)$velVal, 2) : 'N/A';
          $html .= '<li class="list-group-item"><i class="bi bi-lightning-charge"></i> Velocity: <strong>' . $velStr . '</strong></li>';
        }
        $html .= '          </ul>';
        $html .= '          <div class="mt-3 pt-2 border-top">';
        $html .= '            <p class="text-muted mb-1" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Score Y — historical evolution</p>';
        $html .= '            <div style="position:relative;height:200px;"><canvas id="cai-chart-y"></canvas></div>';
        $html .= '          </div>';
        $html .= '        </div>';
      }

      $html .= '      </div>';
      $html .= '    </div>';
      $html .= '  </div>';

      $html .= '</div>';

      // SEO Score (if analyzed)
      if ($seoStatus === 'ANALYZED' && isset($data['seo']['score'])) {
        $seoScore = $data['seo']['score'];
        $seoColor = $seoScore >= 70 ? 'success' : ($seoScore >= 50 ? 'warning' : 'danger');

        $html .= '<div class="row mb-3">';
        $html .= '  <div class="col-md-12">';
        $html .= '    <div class="card border-' . $seoColor . '">';
        $html .= '      <div class="card-header bg-light">';
        $html .= '        <h6 class="mb-0"><i class="bi bi-search"></i> SEO Score</h6>';
        $html .= '      </div>';
        $html .= '      <div class="card-body">';
        $html .= '        <div class="d-flex align-items-center">';
        $html .= '          <h3 class="mb-0 me-3">' . $seoScore . '/100</h3>';
        $html .= '          <div class="progress flex-grow-1" style="height: 25px;">';
        $html .= '            <div class="progress-bar bg-' . $seoColor . '" role="progressbar" style="width:' . $seoScore . '%">' . $seoScore . '%</div>';
        $html .= '          </div>';
        $html .= '        </div>';
        $html .= '      </div>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';
      }

      // LLM Analysis - add before actions
      if (isset($data['analysis']['text']) && !empty($data['analysis']['text'])) {
        $html .= '<div class="row mb-3">';
        $html .= '  <div class="col-md-12">';
        $html .= '    <div class="card">';
        $html .= '      <div class="card-header bg-light">';
        $html .= '        <h6 class="mb-0"><i class="bi bi-chat-left-text"></i> ' . $this->app->getDef('label_analysis');
        if ($data['analysis']['fallback_used'] ?? false) {
          $html .= ' <span class="badge bg-warning">fallback</span>';
        }
        $html .= '        </h6>';
        $html .= '      </div>';
        $html .= '      <div class="card-body">';
        $html .= '        <p class="lead">' . nl2br(HTML::output($data['analysis']['text'])) . '</p>';
        $html .= '      </div>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';
      }

      // Action Plan - adapt to actual metadata structure
      if (isset($data['actions']) && count($data['actions']) > 0) {
        $html .= '<div class="row mb-3">';
        $html .= '  <div class="col-md-12">';
        $html .= '    <div class="card">';
        $html .= '      <div class="card-header bg-light">';
        $html .= '        <h6 class="mb-0"><i class="bi bi-list-check"></i> ' . $this->app->getDef('label_actions') . ' (' . count($data['actions']) . ')</h6>';
        $html .= '      </div>';
        $html .= '      <div class="card-body">';
        $html .= '        <div class="list-group">';

        foreach ($data['actions'] as $action) {
          $pClass = $action['priority'] === 'critical' ? 'danger' : ($action['priority'] === 'high' ? 'warning' : ($action['priority'] === 'medium' ? 'info' : 'secondary'));
          $pIcon = $action['priority'] === 'critical' ? 'exclamation-triangle' : ($action['priority'] === 'high' ? 'exclamation-circle' : ($action['priority'] === 'medium' ? 'info-circle' : 'check-circle'));

          $html .= '          <div class="list-group-item">';
          $html .= '            <span class="badge bg-' . $pClass . ' me-2"><i class="bi bi-' . $pIcon . '"></i> ' . strtoupper($action['priority']) . '</span>';
          $html .= '            <strong>' . HTML::output($action['label']) . '</strong>';
          if ($action['exclusive'] ?? false) {
            $html .= ' <span class="badge bg-dark ms-1">EXCLUSIVE</span>';
          }
          if (!empty($action['description'])) {
            $html .= '<p class="mb-0 mt-1 text-muted"><small>' . HTML::output($action['description']) . '</small></p>';
          }
          $html .= '          </div>';
        }

        $html .= '        </div>';
        $html .= '      </div>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';
      }

      // History info
      if (isset($data['history'])) {
        $history = $data['history'];
        $html .= '<div class="row mb-3">';
        $html .= '  <div class="col-md-12">';
        $html .= '    <div class="alert alert-info">';
        $html .= '      <i class="bi bi-clock-history"></i> ';
        $html .= '      <strong>Analysis #' . ($history['analysis_number'] ?? 1) . '</strong> | ';
        $html .= '      Trend: ' . ($history['trend'] ?? 'stable') . ' | ';
        $html .= '      ΔX: ' . ($history['delta_x'] ?? 0) . 'pts | ';
        $html .= '      ΔY: ' . ($history['delta_y'] ?? 0) . 'pts';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</div>';
      }

      // Refresh button
      $html .= '<div class="row">';
      $html .= '  <div class="col-md-12 text-center">';
      $html .= '    <button type="button" class="btn btn-primary" id="CockpitAI-refresh-btn">';
      $html .= '      <i class="bi bi-arrow-clockwise"></i> ' . $this->app->getDef('button_refresh');
      $html .= '    </button>';
      $html .= '  </div>';
      $html .= '</div>';

      return $html;
    }
  }