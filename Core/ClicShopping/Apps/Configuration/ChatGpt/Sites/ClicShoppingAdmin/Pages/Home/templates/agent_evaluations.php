<?php
/**
 * Agent Evaluations Monitoring Interface
 * 
 * Displays recent evaluations, score distributions, and consensus sessions
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * 
 * @date 2026-01-28
 * 
 * Requirements: 9.2
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title_agent_evaluations'), '40', '40'); ?>
          </span>
          <span class="col-md-7 pageHeading">
            &nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('heading_title_agent_evaluations'); ?>
          </span>
          <span class="col-md-4 text-end">
            <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_refresh'), null, null, 'primary', ['params' => 'onclick="refreshData()"']); ?>
            <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back_dashboard'), null, $CLICSHOPPING_ChatGpt->link('DashBoard'), 'warning'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3"></div>

  <!-- Statistics Cards -->
  <div class="row">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_evaluations'); ?></h5>
          <h2 id="stat-total-evaluations">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_average_score'); ?></h5>
          <h2 id="stat-avg-score">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_consensus_sessions'); ?></h5>
          <h2 id="stat-consensus-sessions">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_consensus_rate'); ?></h5>
          <h2 id="stat-consensus-rate">-</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3"></div>

  <!-- Tabs -->
  <ul class="nav nav-tabs flex-column flex-sm-row" id="evaluationTabs" role="tablist" >
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="evaluations-tab" data-bs-toggle="tab" data-bs-target="#evaluations" type="button">
        <?php echo $CLICSHOPPING_ChatGpt->getDef('text_recent_evaluations'); ?>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="distribution-tab" data-bs-toggle="tab" data-bs-target="#distribution" type="button">
        <?php echo $CLICSHOPPING_ChatGpt->getDef('text_score_distribution'); ?>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="consensus-tab" data-bs-toggle="tab" data-bs-target="#consensus" type="button">
        <?php echo $CLICSHOPPING_ChatGpt->getDef('text_consensus_sessions'); ?>
      </button>
    </li>
  </ul>

  <div class="tab-content" id="evaluationTabContent">
    <!-- Recent Evaluations Tab -->
    <div class="tab-pane fade show active" id="evaluations" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <div id="evaluations-loading" class="text-center" style="display: none;">
            <div class="spinner-border" role="status"></div>
          </div>
          <table
            id="tableEvaluator"
            data-toggle="table"
            data-icons-prefix="bi"
            data-icons="icons"
            data-toolbar="#toolbar"
            data-buttons-class="primary"
            data-show-columns="true"
            data-mobile-responsive="true"
            data-show-export="true">

            <thead class="dataTableHeadingRow">
            <tr>
                <th data-field="Evaluator"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluator'); ?></th>
                <th data-field="Producer"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_producer'); ?></th>
                <th data-field="Output_tYPE"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_output_type'); ?></th>
                <th data-field="Overall"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_overall_score'); ?></th>
                <th data-field="Accuracy"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_accuracy'); ?></th>
                <th data-field="Completeness"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_completeness'); ?></th>
                <th data-field="Efficiency"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_efficiency'); ?></th>
                <th data-field="Clarity"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_clarity'); ?></th>
                <th data-field="Date"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_date'); ?></th>
                <th data-field="Actions"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_actions'); ?></th>
              </tr>
            </thead>
            <tbody id="evaluations-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Score Distribution Tab -->
    <div class="tab-pane fade" id="distribution" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <canvas id="score-distribution-chart" width="400" height="200"></canvas>
            </div>
            <div class="col-md-6">
              <div class="mt-3 mt-md-0">
                <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_scores_by_dimension'); ?></h5>
                <table class="table">
                  <thead>
                    <tr>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_dimension'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_average_score'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_visual'); ?></th>
                    </tr>
                  </thead>
                  <tbody id="avg-scores-tbody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Consensus Sessions Tab -->
    <div class="tab-pane fade " id="consensus" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <table
            id="table"
            data-toggle="table"
            data-icons-prefix="bi"
            data-icons="icons"
            data-toolbar="#toolbar"
            data-buttons-class="primary"
            data-show-columns="true"
            data-mobile-responsive="true"
            data-show-export="true">

            <thead class="dataTableHeadingRow">
              <tr>
                <th data-field="ID"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_id'); ?></th>
                <th data-field="Evaluation"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluation_id'); ?></th>
                <th data-field="Dynamic"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_dynamic_consensus'); ?></th>
                <th data-field="Static"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_static_consensus'); ?></th>
                <th data-field="Difference"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_difference'); ?></th>
                <th data-field="Percent"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_difference_percent'); ?></th>
                <th data-field="Created"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_created'); ?></th>
              </tr>
            </thead>
            <tbody id="consensus-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Evaluation Details Modal -->
<div class="modal fade" id="evaluationDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluation_details'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="evaluation-details-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $CLICSHOPPING_ChatGpt->getDef('button_close'); ?></button>
      </div>
    </div>
  </div>
</div>
<div class="py-2"></div>

<div class="accordion mt-4" id="helpAccordion3">
  <div class="accordion-item border-info">
    <h2 class="accordion-header" id="headingHelp3">
      <button class="accordion-button collapsed bg-light"
              type="button"
              data-bs-toggle="collapse"
              data-bs-target="#collapseHelp3"
              aria-expanded="false"
              aria-controls="collapseHelp3">

        <i class="bi bi-question-circle me-2"></i>
        <?php echo $CLICSHOPPING_ChatGpt->getDef('help_title'); ?>
      </button>
    </h2>
    <div id="collapseHelp3"
         class="accordion-collapse collapse"
         aria-labelledby="headingHelp3"
         data-bs-parent="#helpAccordion3">
      <div class="accordion-body">
        <p><?php echo $CLICSHOPPING_ChatGpt->getDef('help_description'); ?></p>
        <ul class="mb-0">
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_scores'); ?></li>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_consensus'); ?></li>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_distribution'); ?></li>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usage'); ?></li>
        </ul>
      </div>
    </div>
  </div>
</div>
<div class="py-4"></div>

<script>
window.AgentEvaluationsConfig = {
  baseUrl: '<?php echo CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin'); ?>',
  statsEndpoint: 'ajax/Agent/get_agent_evaluation_stats.php',
  evaluationsEndpoint: 'ajax/Agent/get_agent_evaluations.php',
  labels: {
    details: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_details'); ?>",
    number_of_evaluations: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_number_of_evaluations'); ?>",
    reached: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_reached'); ?>",
    failed: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_failed'); ?>",
    pending: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_pending'); ?>",
    na: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_na'); ?>",
    none: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_none'); ?>",
    evaluation_id: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluation_id'); ?>",
    evaluator: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluator'); ?>",
    producer: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_producer'); ?>",
    output_type: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_output_type'); ?>",
    scores: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_scores'); ?>",
    overall: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_overall'); ?>",
    accuracy: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_accuracy'); ?>",
    completeness: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_completeness'); ?>",
    efficiency: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_efficiency'); ?>",
    clarity: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_clarity'); ?>",
    feedback: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_feedback'); ?>",
    strengths: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_strengths'); ?>",
    improvements: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_improvements'); ?>",
    evaluated: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluated'); ?>"
  }
};
</script>
<script src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Agent/agent_evaluations.js'); ?>"></script>

