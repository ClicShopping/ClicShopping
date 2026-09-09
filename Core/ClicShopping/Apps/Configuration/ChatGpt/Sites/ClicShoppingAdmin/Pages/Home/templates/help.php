<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\AiModelsAdmin;

  $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
  $CLICSHOPPING_Page = Registry::get('Site')->getPage();
  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');
  $CLICSHOPPING_Hooks = Registry::get('Hooks');

  // Model catalogue is data-driven: the table reflects whatever the admin catalogued (AI Models),
  // so this page never goes stale as models are added, priced or retired.
  $models = AiModelsAdmin::getModels();

?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
            <?php
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_rag_dashboard'), null, $CLICSHOPPING_ChatGpt->link('Dashboard'), 'primary') . ' ';
              if (defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True') {
                echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_rag_websearch'), null, $CLICSHOPPING_ChatGpt->link('RagWebSearch'), 'info') . ' ';
              }
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-1"></div>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <h4><i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('heading_title'); ?></h4>
          </div>
          <div class="card-body">

            <!-- Introduction -->
            <section class="mb-4">
              <h5 class="text-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_intro_title'); ?></h5>
              <p class="lead">
                <?php echo $CLICSHOPPING_ChatGpt->getDef('help_intro_text'); ?>
              </p>
              <ul>
                <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_intro_example_1'); ?>"</li>
                <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_intro_example_2'); ?>"</li>
                <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_intro_example_3'); ?>"</li>
              </ul>
              <p>
                <?php echo $CLICSHOPPING_ChatGpt->getDef('help_intro_description'); ?>
              </p>
            </section>

            <hr>

            <!-- Model Capabilities Explanation -->
            <section class="mb-4">
              <h5 class="text-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_capabilities_title'); ?></h5>
              <p><?php echo $CLICSHOPPING_ChatGpt->getDef('help_capabilities_intro'); ?></p>

              <div class="row">
                <div class="col-md-6">
                  <div class="card bg-light mb-3">
                    <div class="card-body">
                      <h6 class="card-title"><i class="bi bi-bar-chart"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('help_analytics_title'); ?></h6>
                      <p class="card-text">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('help_analytics_description'); ?>
                      </p>
                      <p class="mb-0"><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_analytics_examples_title'); ?></strong></p>
                      <ul class="small">
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_analytics_example_1'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_analytics_example_2'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_analytics_example_3'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_analytics_example_4'); ?></li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="card bg-light mb-3">
                    <div class="card-body">
                      <h6 class="card-title"><i class="bi bi-search"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('help_semantic_title'); ?></h6>
                      <p class="card-text">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('help_semantic_description'); ?>
                      </p>
                      <p class="mb-0"><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_semantic_examples_title'); ?></strong></p>
                      <ul class="small">
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_semantic_example_1'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_semantic_example_2'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_semantic_example_3'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_semantic_example_4'); ?></li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <hr>

            <!-- Model Catalogue (data-driven from AI Models) -->
            <section class="mb-4">
              <h5 class="text-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_comparison_title'); ?></h5>
              <p><?php echo $CLICSHOPPING_ChatGpt->getDef('help_comparison_intro'); ?></p>

              <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                  <thead class="table-dark">
                    <tr>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_provider'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_model'); ?></th>
                      <th class="text-end"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_context'); ?></th>
                      <th class="text-end"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_input_price'); ?></th>
                      <th class="text-end"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_output_price'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_analytics'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_status'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($models)): ?>
                    <tr><td colspan="7" class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_empty'); ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($models as $mdl): ?>
                    <tr<?php echo ((int)($mdl['ai_model_status_default'] ?? 0) === 1) ? ' class="table-success"' : ''; ?>>
                      <td><?php echo htmlspecialchars((string)($mdl['ai_model_provider_code'] ?? ''), ENT_QUOTES); ?></td>
                      <td>
                        <strong><?php echo htmlspecialchars((string)($mdl['model_display_name'] ?? ''), ENT_QUOTES); ?></strong>
                        <div class="small text-muted">
                          <?php echo htmlspecialchars((string)($mdl['model_technical_name'] ?? ''), ENT_QUOTES); ?><?php echo ($mdl['ai_model_description'] ?? '') !== '' ? ' — ' . htmlspecialchars((string)$mdl['ai_model_description'], ENT_QUOTES) : ''; ?>
                        </div>
                      </td>
                      <td class="text-end"><?php echo number_format((int)($mdl['ai_model_context_window'] ?? 0)); ?></td>
                      <td class="text-end"><?php echo number_format((float)($mdl['ai_model_token_input_price'] ?? 0), 2); ?></td>
                      <td class="text-end"><?php echo number_format((float)($mdl['ai_model_token_output_price'] ?? 0), 2); ?></td>
                      <td>
                        <?php echo ((int)($mdl['ai_model_ai_capable'] ?? 0) === 1)
                          ? '<span class="badge bg-success">' . $CLICSHOPPING_ChatGpt->getDef('help_badge_yes') . '</span>'
                          : '<span class="badge bg-secondary">' . $CLICSHOPPING_ChatGpt->getDef('help_badge_no') . '</span>'; ?>
                      </td>
                      <td>
                        <?php
                          if ((int)($mdl['ai_model_status'] ?? 0) !== 1) {
                            echo '<span class="badge bg-secondary">' . $CLICSHOPPING_ChatGpt->getDef('help_status_inactive') . '</span>';
                          } else {
                            echo '<span class="badge bg-success">' . $CLICSHOPPING_ChatGpt->getDef('help_status_active') . '</span>';
                            if ((int)($mdl['ai_model_status_default'] ?? 0) === 1) {
                              echo ' <span class="badge bg-primary">' . $CLICSHOPPING_ChatGpt->getDef('help_status_default') . '</span>';
                            }
                            if ((int)($mdl['ai_model_status_llm_recommended'] ?? 0) === 1) {
                              echo ' <span class="badge bg-info">' . $CLICSHOPPING_ChatGpt->getDef('help_status_recommended') . '</span>';
                            }
                            if ((int)($mdl['ai_model_status_fallback'] ?? 0) === 1) {
                              echo ' <span class="badge bg-warning">' . $CLICSHOPPING_ChatGpt->getDef('help_status_fallback') . '</span>';
                            }
                          }
                        ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <p class="text-muted small"><em><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_note'); ?></em></p>
            </section>

            <hr>

            <!-- Example Queries -->
            <section class="mb-4">
              <h5 class="text-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_title'); ?></h5>

              <div class="row">
                <div class="col-md-6">
                  <h6 class="text-secondary"><i class="bi bi-bar-chart"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_analytics_title'); ?></h6>
                  <div class="card bg-light">
                    <div class="card-body">
                      <ul class="mb-0">
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_analytics_1'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_analytics_2'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_analytics_3'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_analytics_4'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_analytics_5'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_analytics_6'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_analytics_7'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_analytics_8'); ?>"</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <div class="col-md-6">
                  <h6 class="text-secondary"><i class="bi bi-search"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_semantic_title'); ?></h6>
                  <div class="card bg-light">
                    <div class="card-body">
                      <ul class="mb-0">
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_semantic_1'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_semantic_2'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_semantic_3'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_semantic_4'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_semantic_5'); ?>"</li>
                        <li>"<?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_semantic_6'); ?>"</li>
                      </ul>
                      <p class="text-danger small mb-0 mt-2">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('help_examples_semantic_note'); ?>
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <hr>

            <!-- Technical Notes -->
            <section class="mb-4">
              <h5 class="text-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_technical_title'); ?></h5>
              <div class="alert alert-info">
                <h6><i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('help_embeddings_title'); ?></h6>
                <p>
                  <?php echo $CLICSHOPPING_ChatGpt->getDef('help_embeddings_text'); ?>
                </p>
                <p class="mb-0">
                  <?php echo $CLICSHOPPING_ChatGpt->getDef('help_embeddings_with'); ?><br>
                  <?php echo $CLICSHOPPING_ChatGpt->getDef('help_embeddings_without'); ?>
                </p>
              </div>

              <div class="alert alert-warning">
                <h6><i class="bi bi-exclamation-triangle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('help_notes_title'); ?></h6>
                <ul class="mb-0">
                  <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_notes_1'); ?></li>
                  <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_notes_2'); ?></li>
                  <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_notes_3'); ?></li>
                  <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_notes_4'); ?></li>
                </ul>
              </div>

              <div class="alert alert-secondary">
                <h6><i class="bi bi-cpu"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('help_llmconfig_title'); ?></h6>
                <p><?php echo $CLICSHOPPING_ChatGpt->getDef('help_llmconfig_intro'); ?></p>
                <ul class="mb-0">
                  <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_llmconfig_context'); ?></li>
                  <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_llmconfig_reasoning'); ?></li>
                  <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_llmconfig_speed'); ?></li>
                </ul>
              </div>

              <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('help_llmconfig_reliability'); ?><br>
                <?php echo $CLICSHOPPING_ChatGpt->getDef('help_llmconfig_pricing'); ?>
              </div>
            </section>

            <!-- Footer -->
            <div class="text-center mt-4 pt-3 border-top">
              <p class="text-muted">
                <small>
                  <i class="bi bi-check-circle text-success"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('help_footer_validated'); ?><br>
                  <?php echo $CLICSHOPPING_ChatGpt->getDef('help_footer_support'); ?>
                </small>
              </p>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="py-4"></div>
