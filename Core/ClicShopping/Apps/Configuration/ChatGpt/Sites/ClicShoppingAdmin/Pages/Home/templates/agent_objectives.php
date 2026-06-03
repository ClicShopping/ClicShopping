<?php
/**
 * Agent Objectives Management Interface
 * 
 * Displays active objectives by agent, allows manual approval/cancellation,
 * and shows objective metrics and status
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * 
 * @date 2026-01-28
 * 
 * Requirements: 2.1, 2.5
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
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title_agent_objectives'), '40', '40'); ?>
          </span>
          <span class="col-md-7 pageHeading">
            &nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('heading_title_agent_objectives'); ?>
          </span>
          <span class="col-md-4 text-end">
            <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_refresh'), null, null, 'primary', ['params' => 'onclick="refreshObjectives()"']); ?>
            <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back_dashboard'), null, $CLICSHOPPING_ChatGpt->link('Dashboard'), 'warning'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3"></div>

  <!-- Filters -->
  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('text_filters'); ?></h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-3">
              <label for="filter-agent"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent'); ?></label>
              <select id="filter-agent" class="form-control" onchange="applyFilters()">
                <option value=""><?php echo $CLICSHOPPING_ChatGpt->getDef('text_all_agents'); ?></option>
                <option value="AnalyticsAgent">AnalyticsAgent</option>
                <option value="ReasoningAgent">ReasoningAgent</option>
                <option value="ValidationAgent">ValidationAgent</option>
                <option value="CorrectionAgent">CorrectionAgent</option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="filter-status"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_status'); ?></label>
              <select id="filter-status" class="form-control" onchange="applyFilters()">
                <option value=""><?php echo $CLICSHOPPING_ChatGpt->getDef('text_all_statuses'); ?></option>
                <option value="pending"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_pending'); ?></option>
                <option value="approved"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_approved'); ?></option>
                <option value="active"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_active'); ?></option>
                <option value="completed"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_completed'); ?></option>
                <option value="failed"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_failed'); ?></option>
                <option value="cancelled"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_cancelled'); ?></option>
              </select>
            </div>
            <div class="col-md-3">
              <label for="filter-priority"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_priority'); ?></label>
              <select id="filter-priority" class="form-control" onchange="applyFilters()">
                <option value=""><?php echo $CLICSHOPPING_ChatGpt->getDef('text_all_priorities'); ?></option>
                <option value="low"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_low'); ?></option>
                <option value="medium"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_medium'); ?></option>
                <option value="high"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_high'); ?></option>
                <option value="critical"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_critical'); ?></option>
              </select>
            </div>
            <div class="col-md-3">
              <label>&nbsp;</label>
              <button class="btn btn-secondary form-control" onclick="clearFilters()"><?php echo $CLICSHOPPING_ChatGpt->getDef('button_clear_filters'); ?></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3"></div>

  <!-- Objectives Table -->
  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent_objectives'); ?></h5>
        </div>
        <div class="card-body">
          <div id="objectives-loading" class="text-center" style="display: none;">
            <div class="spinner-border" role="status">
              <span class="visually-hidden"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_loading'); ?></span>
            </div>
          </div>
          <div id="objectives-error" class="alert alert-danger" style="display: none;"></div>
          <div id="objectives-container">
            <table class="table table-striped table-hover" id="objectives-table">
              <thead>
                <tr>
                  <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_id'); ?></th>
                  <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent'); ?></th>
                  <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_goal'); ?></th>
                  <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_priority'); ?></th>
                  <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_status'); ?></th>
                  <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_created'); ?></th>
                  <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_estimated_completion'); ?></th>
                  <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_actions'); ?></th>
                </tr>
              </thead>
              <tbody id="objectives-tbody">
                <!-- Populated by JavaScript -->
              </tbody>
            </table>
            <div id="pagination-container" class="mt-3">
              <!-- Pagination controls -->
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Objective Details Modal -->
<div class="modal fade" id="objectiveDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_objective_details'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="objective-details-body">
        <!-- Populated by JavaScript -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $CLICSHOPPING_ChatGpt->getDef('button_close'); ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Action Confirmation Modal -->
<div class="modal fade" id="actionConfirmModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="action-confirm-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_confirm_action'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="action-confirm-message"></p>
        <div id="action-reason-container" style="display: none;">
          <label for="action-reason"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_reason'); ?></label>
          <textarea id="action-reason" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $CLICSHOPPING_ChatGpt->getDef('button_cancel'); ?></button>
        <button type="button" class="btn btn-primary" id="action-confirm-btn"><?php echo $CLICSHOPPING_ChatGpt->getDef('button_confirm'); ?></button>
      </div>
    </div>
  </div>
</div>

<script>
window.AgentObjectivesConfig = {
  baseUrl: '<?php echo CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin'); ?>',
  objectivesEndpoint: 'ajax/Agent/get_agent_objectives.php',
  manageEndpoint: 'ajax/Agent/agent_manage_objective.php',
  labels: {
    error_loading: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_error_loading_objectives'); ?>",
    network_error: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_network_error'); ?>",
    no_objectives: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_objectives'); ?>",
    approve: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_approve'); ?>",
    cancel: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_cancel'); ?>",
    activate: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_activate'); ?>",
    complete: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_complete'); ?>",
    fail: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_fail'); ?>",
    no_actions: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_actions'); ?>",
    confirm_title_prefix: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_confirm_title_prefix'); ?>",
    confirm_message_prefix: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_confirm_message_prefix'); ?>",
    confirm_message_suffix: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_confirm_message_suffix'); ?>",
    error_prefix: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_error_prefix'); ?>",
    action_labels: {
      approve: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_approve'); ?>",
      cancel: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_cancel'); ?>",
      activate: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_activate'); ?>",
      complete: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_complete'); ?>",
      fail: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_fail'); ?>"
    },
    objective_id: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_objective_id'); ?>",
    agent: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent'); ?>",
    goal: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_goal'); ?>",
    success_criteria: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_success_criteria'); ?>",
    priority: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_priority'); ?>",
    status: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_status'); ?>",
    created: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_created'); ?>",
    estimated_completion: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_estimated_completion'); ?>",
    seconds: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_seconds'); ?>",
    metrics: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_metrics'); ?>",
    failure_reason: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_failure_reason'); ?>",
    previous: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_previous'); ?>",
    next: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_next'); ?>"
  }
};
</script>



<div class="row">
  <div class="col-md-12">
    <div class="accordion mt-4" id="helpAccordion">
      <div class="accordion-item border-info">
        <h2 class="accordion-header" id="headingHelp">
          <button class="accordion-button collapsed bg-light"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#collapseHelp"
                  aria-expanded="false"
                  aria-controls="collapseHelp">
            <i class="bi bi-question-circle me-2"></i>
            <?php echo $CLICSHOPPING_ChatGpt->getDef('help_title'); ?>
          </button>
        </h2>
        <div id="collapseHelp"
             class="accordion-collapse collapse"
             aria-labelledby="headingHelp"
             data-bs-parent="#helpAccordion">
          <div class="accordion-body">
            <p><?php echo $CLICSHOPPING_ChatGpt->getDef('help_description'); ?></p>

            <ul class="mb-0">
              <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_statuses'); ?></li>
              <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_priority'); ?></li>
              <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_actions'); ?></li>
              <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_metrics'); ?></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="py-4"></div>
<script src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Agent/agent_objectives_filters.js'); ?>"></script>
<script src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Agent/agent_objectives.js'); ?>"></script>
