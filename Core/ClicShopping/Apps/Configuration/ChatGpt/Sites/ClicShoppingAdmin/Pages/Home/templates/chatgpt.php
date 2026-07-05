<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\AiModelsAdmin;
use ClicShopping\AI\Infrastructure\Metrics\Statistics;

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
          <?php




          echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_configure'), null, $CLICSHOPPING_ChatGpt->link('Configure'), 'primary') . ' ';


          //modal to build
            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_add_model'), null, $CLICSHOPPING_ChatGpt->link('Create'), 'primary') . ' ';


          if (defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS === 'True') {
            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_rag_dashboard'), null, $CLICSHOPPING_ChatGpt->link('Dashboard'), 'danger') . ' ';
            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_rag_websearch'), null, $CLICSHOPPING_ChatGpt->link('RagWebSearch'), 'success') . ' ';
          }
          ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- ################# -->
  <!-- Hooks Stats - just use execute function to display the hook-->
  <!-- ################# -->
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <?php
          $stat_result = Statistics::getTotalTokenByMonth();

          if(is_array($stat_result)) {
            if ($stat_result['promptTokens'] > 0) {
              ?>
              <div class="col-md-3 col-12">
                <div class="card bg-danger">
                  <div class="card-body">
                    <h6
                      class="card-title text-white"><?php echo $CLICSHOPPING_ChatGpt->getDef('stat_prompt_tokens'); ?></h6>
                    <div class="card-text">
                      <div class="col-sm-12">
                          <span class="float-start">
                            <i class="bi bi-clipboard2-pulse-fill text-white"></i>
                          </span>
                        <span class="float-end">
                          <div class="col-sm-12 text-white"><?php echo $stat_result['promptTokens']; ?></div>
                          </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }

            if ($stat_result['completionTokens'] > 0) {
              ?>
              <div class="col-md-3 col-12">
                <div class="card bg-success">
                  <div class="card-body">
                    <h6
                      class="card-title text-white"><?php echo $CLICSHOPPING_ChatGpt->getDef('stat_completion_tokens'); ?></h6>
                    <div class="card-text">
                      <div class="col-sm-12">
                          <span class="float-start">
                            <i class="bi bi-bar-chart-fill text-white"></i>
                          </span>
                        <span class="float-end">
                          <div class="col-sm-12 text-white"><?php echo $stat_result['completionTokens']; ?></div>
                          </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }

            if ($stat_result['totalTokens'] > 0) {
              ?>
              <div class="col-md-3 col-12">
                <div class="card bg-primary">
                  <div class="card-body">
                    <h6
                      class="card-title text-white"><?php echo $CLICSHOPPING_ChatGpt->getDef('stat_total_tokens'); ?></h6>
                    <div class="card-text">
                      <div class="col-sm-12">
                          <span class="float-start">
                            <i class="bi bi-graph-up text-white"></i>
                          </span>
                        <span class="float-end">
                          <div class="col-sm-12 text-white"><?php echo $stat_result['totalTokens']; ?></div>
                          </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
          }

          if (Gpt::getErrorRateGpt() !== false) {
            ?>
            <div class="col-md-3 col-12">
              <div class="card bg-warning">
                <div class="card-body">
                  <h6
                    class="card-title text-white"><?php echo $CLICSHOPPING_ChatGpt->getDef('stat_total_no_response'); ?></h6>
                  <div class="card-text">
                    <div class="col-sm-12">
                      <span class="float-start">
                        <i class="bi bi-graph-up text-white"></i>
                      </span>
                      <span class="float-end">
                        <div
                          class="col-sm-12 text-white"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_rate_error_gpt') . ' ' . Gpt::getErrorRateGpt() . ' %'; ?></div>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }

          echo $CLICSHOPPING_Hooks->output('Stats', 'StatsGpt', null, 'display');
          ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                     AI MODEL CATALOG LISTING                                                   -->
  <!-- //################################################################################################################ -->
  <table id="table" data-toggle="table" data-icons-prefix="bi" data-icons="icons"
         data-sort-order="asc" data-sort-name="provider" data-show-columns="true"
         data-mobile-responsive="true" data-show-export="true">
    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="provider" data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_provider'); ?></th>
      <th data-field="model" data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_model'); ?></th>
      <th data-field="technical"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_technical'); ?></th>
      <th data-field="context" class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_context'); ?></th>
      <th data-field="status" class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_status'); ?></th>
      <th data-field="default" class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_default'); ?></th>
      <th data-field="fallback" class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_fallback'); ?></th>
      <th data-field="action" data-switchable="false" class="text-end"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach (AiModelsAdmin::getModels() as $m) {
      $mid = (int)$m['ai_model_name_id'];
      ?>
      <tr>
        <td><?php echo HTML::outputProtected($m['ai_model_provider_code']); ?></td>
        <td><?php echo HTML::outputProtected($m['model_display_name']); ?></td>
        <td><code><?php echo HTML::outputProtected($m['model_technical_name']); ?></code></td>
        <td class="text-center"><?php echo (int)$m['ai_model_context_window']; ?></td>
        <td class="text-center">
          <?php
          $flag = ((int)$m['ai_model_status'] === 1) ? 0 : 1;
          $icon = ((int)$m['ai_model_status'] === 1) ? 'bi-check text-success' : 'bi-x text-danger';
          echo HTML::link($CLICSHOPPING_ChatGpt->link('ChatGpt&SetFlag&field=status&cID=' . $mid . '&flag=' . $flag), '<i class="bi ' . $icon . '"></i>');
          ?>
        </td>
        <td class="text-center"><?php echo ((int)$m['ai_model_status_default'] === 1) ? '<i class="bi bi-star-fill text-warning"></i>' : ''; ?></td>
        <td class="text-center"><?php echo ((int)$m['ai_model_status_fallback'] === 1) ? '<i class="bi bi-shield-fill text-info"></i>' : ''; ?></td>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group">
            <?php
            echo HTML::link($CLICSHOPPING_ChatGpt->link('Edit&cID=' . $mid), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_ChatGpt->getDef('icon_edit') . '"></i></h4>');
            echo '&nbsp;';
            echo HTML::link($CLICSHOPPING_ChatGpt->link('Delete&cID=' . $mid), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_ChatGpt->getDef('icon_delete') . '"></i></h4>');
            ?>
          </div>
        </td>
      </tr>
      <?php
    }
    ?>
    </tbody>
  </table>
</div>
<div class="py-4"></div>