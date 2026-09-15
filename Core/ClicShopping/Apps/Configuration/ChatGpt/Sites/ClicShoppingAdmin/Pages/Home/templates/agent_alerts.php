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

// User reports of the last 7 days, grouped by kind of request. Collected here because the summary
// card is rendered before the tab that lists them.
$Qreports = $CLICSHOPPING_ChatGpt->db->prepare('select coalesce(i.request_type, "unknown") as request_type,
                                                       count(*) as negative_count,
                                                       max(f.date_added) as last_at,
                                                       substring_index(group_concat(distinct i.question separator 0x1e), 0x1e, 3) as sample_questions,
                                                       substring_index(group_concat(distinct nullif(json_unquote(json_extract(f.feedback_data, "$.feedback_text")), "") separator 0x1e), 0x1e, 3) as sample_comments,
                                                       (select count(*)
                                                          from :table_rag_interactions t
                                                         where coalesce(t.request_type, "unknown") = coalesce(i.request_type, "unknown")
                                                           and t.date_added >= date_sub(now(), interval 7 day)) as answers_total
                                                from :table_rag_feedback f
                                                left join :table_rag_interactions i on f.interaction_id = i.client_interaction_id
                                                where f.feedback_type = "negative"
                                                and f.date_added >= date_sub(now(), interval 7 day)
                                                group by coalesce(i.request_type, "unknown")
                                                order by negative_count desc
                                               ');
$Qreports->execute();

$reports = $Qreports->fetchAll();
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
    <div class="col-md">
      <div class="card text-center border-warning">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_overdue_objectives'); ?></h5>
          <h2 id="alert-overdue" class="text-warning">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md">
      <div class="card text-center border-danger">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_systematic_issues'); ?></h5>
          <h2 id="alert-systematic" class="text-danger">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md">
      <div class="card text-center border-warning">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_failed_consensus'); ?></h5>
          <h2 id="alert-consensus" class="text-warning">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md">
      <div class="card text-center border-danger">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_failed_objectives'); ?></h5>
          <h2 id="alert-failed" class="text-danger">-</h2>
        </div>
      </div>
    </div>
    <div class="col-md">
      <div class="card text-center border-warning">
        <div class="card-body">
          <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_negative_feedback'); ?></h5>
          <h2 id="alert-negative" class="text-warning"><?php echo \count($reports); ?></h2>
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
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="negative-tab" data-bs-toggle="tab" data-bs-target="#negative" type="button">
        <?php echo $CLICSHOPPING_ChatGpt->getDef('text_negative_feedback'); ?>
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

    <!-- User Reports Tab -->
    <div class="tab-pane fade" id="negative" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <div class="alert alert-info">
            <?php echo $CLICSHOPPING_ChatGpt->getDef('text_negative_note'); ?>
          </div>
          <?php echo HTML::form('delete_reports', $CLICSHOPPING_ChatGpt->link('AgentAlerts&DeleteAll')); ?>

          <div id="negative-toolbar" class="float-end">
            <button id="negative-delete" class="btn btn-danger btn-sm">
              <i class="bi bi-trash"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('button_delete_reports'); ?>
            </button>
          </div>

          <table
            id="negative-table"
            data-toggle="table"
            data-icons-prefix="bi"
            data-icons="icons"
            data-id-field="request_type"
            data-select-item-name="selected[]"
            data-click-to-select="true"
            data-toolbar="#negative-toolbar"
            data-buttons-class="primary"
            data-show-columns="true"
            data-mobile-responsive="true"
            data-check-on-init="true"
            data-show-export="true">

            <thead class="dataTableHeadingRow">
              <tr>
                <th data-checkbox="true" data-field="state"></th>
                <th data-field="request_type" data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_request_type'); ?></th>
                <th data-field="negative_count" data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_negatives'); ?></th>
                <th data-field="answers_total" data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_answers_total'); ?></th>
                <th data-field="negative_rate" data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_negative_rate'); ?></th>
                <th data-field="sample_questions" data-switchable="false"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_sample_questions'); ?></th>
                <th data-field="sample_comments"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_sample_comments'); ?></th>
                <th data-field="last_at" data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_last_report'); ?></th>
                <th data-field="severity" data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_severity'); ?></th>
              </tr>
            </thead>
            <tbody>
            <?php
            foreach ($reports as $report) {
              $negative_count = (int)$report['negative_count'];
              $answers_total = (int)$report['answers_total'];
              $negative_rate = $answers_total > 0 ? $negative_count / $answers_total : 0;

              // Never critical on a handful: three reports AND a third of the answers.
              $severity = ($negative_count >= 3 && $negative_rate >= 0.3) ? 'critical' : 'warning';
              ?>
              <tr>
                <td></td>
                <td><?php echo HTML::outputProtected($report['request_type']); ?></td>
                <td><?php echo $negative_count; ?></td>
                <td><?php echo $answers_total; ?></td>
                <td><?php echo number_format($negative_rate * 100, 1); ?>%</td>
                <td>
                  <ul class="mb-0 ps-3">
                    <?php
                    foreach (array_filter(explode("\x1e", (string)$report['sample_questions'])) as $question) {
                      ?>
                      <li>
                        <?php echo HTML::outputProtected($question); ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-1 py-0"
                                data-purge-question="<?php echo HTML::outputProtected($question); ?>"
                                title="<?php echo HTML::outputProtected($CLICSHOPPING_ChatGpt->getDef('text_purge_question_cache_title')); ?>">
                          <i class="bi bi-eraser"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_purge_question_cache'); ?>
                        </button>
                      </li>
                      <?php
                    }
                    ?>
                  </ul>
                </td>
                <td>
                  <ul class="mb-0 ps-3">
                    <?php
                    foreach (array_filter(explode("\x1e", (string)$report['sample_comments'])) as $comment) {
                      echo '<li>' . HTML::outputProtected($comment) . '</li>';
                    }
                    ?>
                  </ul>
                </td>
                <td><?php echo HTML::outputProtected($report['last_at']); ?></td>
                <td><span class="badge bg-<?php echo $severity === 'critical' ? 'danger' : 'warning'; ?>"><?php echo $severity; ?></span></td>
              </tr>
              <?php
            }

            if ($reports === []) {
              echo '<tr><td colspan="9" class="text-center">' . $CLICSHOPPING_ChatGpt->getDef('text_no_negative_feedback') . '</td></tr>';
            }
            ?>
            </tbody>
          </table>

          <?php echo '</form>'; ?>
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
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_negative'); ?></li>
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
  purgeQuestionCacheEndpoint: 'ajax/RAG/purge_question_cache.php',
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
    severity: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_severity'); ?>",
    no_negative_feedback: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_negative_feedback'); ?>",
    purge_question_cache: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_purge_question_cache'); ?>",
    purge_question_cache_title: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_purge_question_cache_title'); ?>",
    purge_done: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_purge_done'); ?>",
    purge_none: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_purge_none'); ?>",
    purge_failed: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_purge_failed'); ?>",
    delete_reports_confirm: "<?php echo $CLICSHOPPING_ChatGpt->getDef('text_delete_reports_confirm'); ?>"
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
