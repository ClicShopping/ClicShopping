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
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', 'Agent Alerts', '40', '40'); ?>
          </span>
          <span class="col-md-7 pageHeading">
            &nbsp;Agent Alerts Management
          </span>
          <span class="col-md-4 text-end">
            <?php echo HTML::button('Refresh', null, null, 'primary', ['params' => 'onclick="refreshAlerts()"']); ?>
            <?php echo HTML::button('Back to Dashboard', null, $CLICSHOPPING_ChatGpt->link('Dashboard'), 'warning'); ?>
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
          <h5 class="card-title">Overdue Objectives</h5>
          <h2 id="alert-overdue" class="text-warning">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-danger">
        <div class="card-body">
          <h5 class="card-title">Systematic Issues</h5>
          <h2 id="alert-systematic" class="text-danger">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-warning">
        <div class="card-body">
          <h5 class="card-title">Failed Consensus</h5>
          <h2 id="alert-consensus" class="text-warning">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center border-danger">
        <div class="card-body">
          <h5 class="card-title">Failed Objectives</h5>
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
        Overdue Objectives
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="systematic-tab" data-bs-toggle="tab" data-bs-target="#systematic" type="button">
        Systematic Issues
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="consensus-tab" data-bs-toggle="tab" data-bs-target="#consensus" type="button">
        Failed Consensus
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="failed-tab" data-bs-toggle="tab" data-bs-target="#failed" type="button">
        Failed Objectives
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
                <th>Objective ID</th>
                <th>Agent</th>
                <th>Goal</th>
                <th>Priority</th>
                <th>Created</th>
                <th>Est. Time</th>
                <th>Overdue By</th>
                <th>Actions</th>
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
            <strong>Note:</strong> Agents listed here have received consistently low evaluation scores (avg < 0.6) over the past 7 days with at least 5 evaluations.
          </div>
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Agent</th>
                <th>Evaluations</th>
                <th>Avg Score</th>
                <th>Min Score</th>
                <th>Max Score</th>
                <th>Severity</th>
                <th>Actions</th>
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
                <th>Session ID</th>
                <th>Output ID</th>
                <th>Participants</th>
                <th>Initial Scores</th>
                <th>Created</th>
                <th>Actions</th>
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
                <th>Objective ID</th>
                <th>Agent</th>
                <th>Goal</th>
                <th>Priority</th>
                <th>Failure Reason</th>
                <th>Failed At</th>
                <th>Actions</th>
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
        <h5 class="modal-title">Agent Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="agent-details-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Consensus Details Modal -->
<div class="modal fade" id="consensusDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Consensus Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="consensus-details-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Objective Details Modal -->
<div class="modal fade" id="alertObjectiveDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Objective Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="alert-objective-details-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
  manageEndpoint: 'ajax/Agent/agent_manage_objective.php'
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
