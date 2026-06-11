<?php
/**
 * Agent Alerts Management Interface
 * 
 * Displays system alerts, overdue objectives, and systematic issues
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * 
 * @date 2026-01-28
 * 
 * Requirements: 2.3, 9.3
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
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title_agent_alerts'), '40', '40'); ?>
          </span>
          <span class="col-md-7 pageHeading">
            &nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('heading_title_agent_alerts'); ?>
          </span>
          <span class="col-md-4 text-end">
            <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_refresh'), null, null, 'primary', ['params' => 'data-fn="refreshAlerts"']); ?>
            <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back_dashboard'), null, $CLICSHOPPING_ChatGpt->link('DashboardDataScientist'), 'warning'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3"></div>

  <!-- Alert Summary Cards -->
  <div class="row">
    <div class="col-md-3">
      <div class="card text-center border-warning">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_overdue_objectives'); ?></h5>
          <h2 id="alert-overdue" class="text-warning">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-danger">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_systematic_issues'); ?></h5>
          <h2 id="alert-systematic" class="text-danger">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-warning">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_failed_consensus'); ?></h5>
          <h2 id="alert-consensus" class="text-warning">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-danger">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_failed_objectives'); ?></h5>
          <h2 id="alert-failed" class="text-danger">-</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3"></div>

  <!-- Tabs -->
  <ul class="nav nav-tabs flex-column flex-sm-row" id="alertTabs" role="tablist" >
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="overdue-tab" data-bs-toggle="tab" data-bs-target="#overdue" type="button">
        <?php echo $CLICSHOPPING_ChatGpt->getDef('text_overdue_objectives'); ?>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="systematic-tab" data-bs-toggle="tab" data-bs-target="#systematic" type="button">
        <?php echo $CLICSHOPPING_ChatGpt->getDef('text_systematic_issues'); ?>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="consensus-tab" data-bs-toggle="tab" data-bs-target="#consensus" type="button">
        <?php echo $CLICSHOPPING_ChatGpt->getDef('text_failed_consensus'); ?>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="failed-tab" data-bs-toggle="tab" data-bs-target="#failed" type="button">
        <?php echo $CLICSHOPPING_ChatGpt->getDef('text_failed_objectives'); ?>
      </button>
    </li>
  </ul>

  <div class="tab-content" id="alertTabContent">
    <!-- Overdue Objectives Tab -->
    <div class="tab-pane fade show active" id="overdue" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <div id="overdue-loading" class="text-center" style="display: none;">
            <div class="spinner-border" role="status"></div>
          </div>
          <table
            id="tableAlert"
            data-toggle="table"
            data-icons-prefix="bi"
            data-icons="icons"
            data-toolbar="#toolbar"
            data-buttons-class="primary"
            data-show-columns="true"
            data-mobile-responsive="true"
            data-check-on-init="true"
            data-show-export="true">

            <thead class="dataTableHeadingRow">
            <tr>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_objective_id'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_goal'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_priority'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_created'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_estimated_time'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_overdue_by'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_actions'); ?></th>
              </tr>
            </thead>
            <tbody id="overdue-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Systematic Issues Tab -->
    <div class="tab-pane fade" id="systematic" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <div class="alert alert-info">
            <?php echo $CLICSHOPPING_ChatGpt->getDef('text_systematic_note'); ?>
          </div>
          <table class="table table-striped">
            <thead>
              <tr>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluations'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_score'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_min_score'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_max_score'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_severity'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_actions'); ?></th>
              </tr>
            </thead>
            <tbody id="systematic-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Failed Consensus Tab -->
    <div class="tab-pane fade" id="consensus" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <table class="table table-striped">
            <thead>
              <tr>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_session_id'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_output_id'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_participants'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_initial_scores'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_created'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_actions'); ?></th>
              </tr>
            </thead>
            <tbody id="consensus-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Failed Objectives Tab -->
    <div class="tab-pane fade" id="failed" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <table class="table table-striped">
            <thead>
              <tr>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_objective_id'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_goal'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_priority'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_failure_reason'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_failed_at'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_actions'); ?></th>
              </tr>
            </thead>
            <tbody id="failed-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Agent Details Modal -->
<div class="modal fade" id="agentDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent_details'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="agent-details-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $CLICSHOPPING_ChatGpt->getDef('button_close'); ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Consensus Details Modal -->
<div class="modal fade" id="consensusDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_consensus_details'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="consensus-details-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $CLICSHOPPING_ChatGpt->getDef('button_close'); ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Objective Details Modal -->
<div class="modal fade" id="alertObjectiveDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_objective_details'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="alert-objective-details-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $CLICSHOPPING_ChatGpt->getDef('button_close'); ?></button>
      </div>
    </div>
  </div>
</div>



<div class="accordion mt-4" id="helpAccordion2">
  <div class="accordion-item border-info">

    <h2 class="accordion-header" id="headingHelp2">
      <button class="accordion-button collapsed bg-light"
              type="button"
              data-bs-toggle="collapse"
              data-bs-target="#collapseHelp2"
              aria-expanded="false"
              aria-controls="collapseHelp2">

        <i class="bi bi-question-circle me-2"></i>
        <?php echo $CLICSHOPPING_ChatGpt->getDef('help_title'); ?>
      </button>
    </h2>

    <div id="collapseHelp2"
         class="accordion-collapse collapse"
         aria-labelledby="headingHelp2"
         data-bs-parent="#helpAccordion2">

      <div class="accordion-body">
        <p><?php echo $CLICSHOPPING_ChatGpt->getDef('help_description'); ?></p>

        <ul class="mb-0">
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_overdue'); ?></li>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_systematic'); ?></li>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_consensus'); ?></li>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_failed'); ?></li>
        </ul>
      </div>

    </div>

  </div>
</div>

<script>
window.AgentAlertsConfig = {
  baseUrl: '<?php echo CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin'); ?>',
  alertsEndpoint: 'ajax/Agent/get_agent_alerts.php',
  objectivesEndpoint: 'ajax/Agent/get_agent_objectives.php',
  manageEndpoint: 'ajax/Agent/agent_manage_objective.php',
  labels: {
    no_overdue: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_overdue'); ?>",
    no_systematic: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_systematic'); ?>",
    no_failed_consensus: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_failed_consensus'); ?>",
    no_failed_objectives: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_failed_objectives'); ?>",
    escalate: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_escalate'); ?>",
    escalating: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_escalating'); ?>",
    escalation_sent: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_escalation_sent'); ?>",
    escalated: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_escalated'); ?>",
    details: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_details'); ?>",
    na: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_na'); ?>",
    not_overdue: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_not_overdue'); ?>",
    objective_id: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_objective_id'); ?>",
    agent: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent'); ?>",
    goal: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_goal'); ?>",
    priority: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_priority'); ?>",
    failure_reason: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_failure_reason'); ?>",
    failed_at: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_failed_at'); ?>",
    session_id: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_session_id'); ?>",
    output_id: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_output_id'); ?>",
    participants: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_participants'); ?>",
    created: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_created'); ?>",
    evaluations: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluations'); ?>",
    avg_score: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_score'); ?>",
    min_score: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_min_score'); ?>",
    max_score: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_max_score'); ?>",
    severity: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_severity'); ?>"
  }
};
</script>
<script src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Agent/agent_alerts.js'); ?>"></script>


<div class="py-4"></div>
<style>
.card {
  margin-bottom: 1rem;
}

.table {
  font-size: 0.9rem;
}

.badge {
  font-size: 0.85rem;
}

.border-warning {
  border-width: 2px !important;
}

.border-danger {
  border-width: 2px !important;
}
</style>
