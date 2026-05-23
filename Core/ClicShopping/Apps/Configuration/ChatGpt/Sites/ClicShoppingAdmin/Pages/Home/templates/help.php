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

  $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
  $CLICSHOPPING_Page = Registry::get('Site')->getPage();
  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');
  $CLICSHOPPING_Hooks = Registry::get('Hooks');

  $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

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
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_rag_dashboard'), null, $CLICSHOPPING_ChatGpt->link('DashBoard'), 'primary') . ' ';
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

            <!-- Model Comparison Table -->
            <section class="mb-4">
              <h5 class="text-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_comparison_title'); ?></h5>
              <p><?php echo $CLICSHOPPING_ChatGpt->getDef('help_comparison_intro'); ?></p>
              
              <div class="table-responsive">
                <table class="table table-bordered table-hover">
                  <thead class="table-dark">
                    <tr>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_model'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_best_for'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_analytics'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_semantic'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_speed'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_cost'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_table_recommendation'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="table-success">
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4o_name'); ?></strong></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4o_best'); ?></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_badge_yes'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_badge_yes'); ?></span></td>
                      <td><span class="badge bg-warning"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4o_speed'); ?></span></td>
                      <td><span class="badge bg-warning"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4o_cost'); ?></span></td>
                      <td><span class="badge bg-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4o_recommendation'); ?></span></td>
                    </tr>
                    
                    <tr class="table-info">
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt41mini_name'); ?></strong></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt41mini_best'); ?></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_badge_yes'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_badge_yes'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt41mini_speed'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt41mini_cost'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt41mini_recommendation'); ?></span></td>
                    </tr>
                    
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4omini_name'); ?></strong></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4omini_best'); ?></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_badge_yes'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_badge_yes'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4omini_speed'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4omini_cost'); ?></span></td>
                      <td><span class="badge bg-info"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4omini_recommendation'); ?></span></td>
                    </tr>
                    
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_phi4_name'); ?></strong></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_phi4_best'); ?></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_badge_no'); ?></span></td>
                      <td><span class="badge bg-danger"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_badge_no'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_phi4_speed'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_phi4_cost'); ?></span></td>
                      <td><span class="badge bg-warning"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_phi4_recommendation'); ?></span></td>
                    </tr>
                    
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_mistral_name'); ?></strong></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_mistral_best'); ?></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_badge_yes'); ?></span></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_badge_yes'); ?></span></td>
                      <td><span class="badge bg-warning"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_mistral_speed'); ?></span></td>
                      <td><span class="badge bg-warning"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_mistral_cost'); ?></span></td>
                      <td><span class="badge bg-secondary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_mistral_recommendation'); ?></span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <hr>

            <!-- Use Case Recommendations -->
            <section class="mb-4">
              <h5 class="text-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecases_title'); ?></h5>
              
              <div class="accordion" id="useCaseAccordion">
                
                <!-- Use Case 1 -->
                <div class="accordion-item">
                  <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                      <i class="bi bi-building me-2"></i> <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_business_title'); ?></strong>
                    </button>
                  </h2>
                  <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#useCaseAccordion">
                    <div class="accordion-body">
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_business_model'); ?>:</strong> <span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt41mini_name'); ?></span></p>
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_business_why'); ?></strong></p>
                      <ul>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_business_reason_1'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_business_reason_2'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_business_reason_3'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_business_reason_4'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_business_reason_5'); ?></li>
                      </ul>
                      <p class="mb-0"><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_business_perfect'); ?></strong></p>
                    </div>
                  </div>
                </div>

                <!-- Use Case 2 -->
                <div class="accordion-item">
                  <h2 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                      <i class="bi bi-cash-stack me-2"></i> <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_volume_title'); ?></strong>
                    </button>
                  </h2>
                  <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#useCaseAccordion">
                    <div class="accordion-body">
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_volume_model'); ?>:</strong> <span class="badge bg-info"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4omini_name'); ?></span></p>
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_volume_why'); ?></strong></p>
                      <ul>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_volume_reason_1'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_volume_reason_2'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_volume_reason_3'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_volume_reason_4'); ?></li>
                      </ul>
                      <p class="mb-0"><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_volume_perfect'); ?></strong></p>
                    </div>
                  </div>
                </div>

                <!-- Use Case 3 -->
                <div class="accordion-item">
                  <h2 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                      <i class="bi bi-shield-lock me-2"></i> <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_privacy_title'); ?></strong>
                    </button>
                  </h2>
                  <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#useCaseAccordion">
                    <div class="accordion-body">
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_privacy_model'); ?>:</strong> <span class="badge bg-warning"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_phi4_name'); ?></span></p>
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_privacy_why'); ?></strong></p>
                      <ul>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_privacy_reason_1'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_privacy_reason_2'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_privacy_reason_3'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_privacy_reason_4'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_privacy_reason_5'); ?></li>
                      </ul>
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_privacy_note'); ?></strong></p>
                      <p class="mb-0"><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_privacy_perfect'); ?></strong></p>
                    </div>
                  </div>
                </div>

                <!-- Use Case 4 -->
                <div class="accordion-item">
                  <h2 class="accordion-header" id="headingFour">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">
                      <i class="bi bi-star me-2"></i> <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_maximum_title'); ?></strong>
                    </button>
                  </h2>
                  <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#useCaseAccordion">
                    <div class="accordion-body">
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_maximum_model'); ?>:</strong> <span class="badge bg-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4o_name'); ?></span></p>
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_maximum_why'); ?></strong></p>
                      <ul>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_maximum_reason_1'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_maximum_reason_2'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_maximum_reason_3'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_maximum_reason_4'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_maximum_reason_5'); ?></li>
                      </ul>
                      <p class="mb-0"><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_maximum_perfect'); ?></strong></p>
                    </div>
                  </div>
                </div>

                <!-- Use Case 5 -->
                <div class="accordion-item">
                  <h2 class="accordion-header" id="headingFive">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive">
                      <i class="bi bi-globe me-2"></i> <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_diversity_title'); ?></strong>
                    </button>
                  </h2>
                  <div id="collapseFive" class="accordion-collapse collapse" data-bs-parent="#useCaseAccordion">
                    <div class="accordion-body">
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_diversity_model'); ?>:</strong> <span class="badge bg-secondary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_mistral_name'); ?></span></p>
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_diversity_why'); ?></strong></p>
                      <ul>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_diversity_reason_1'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_diversity_reason_2'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_diversity_reason_3'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_diversity_reason_4'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_diversity_reason_5'); ?></li>
                      </ul>
                      <p class="mb-0"><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_usecase_diversity_perfect'); ?></strong></p>
                    </div>
                  </div>
                </div>

              </div>
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

            <!-- Performance Information -->
            <section class="mb-4">
              <h5 class="text-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_title'); ?></h5>
              <p><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_intro'); ?></p>
              
              <div class="table-responsive">
                <table class="table table-sm table-bordered">
                  <thead class="table-light">
                    <tr>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_table_model'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_table_time'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_table_success'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_table_queries'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt41mini_name'); ?></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_gpt41mini'); ?></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_success'); ?></span></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_queries'); ?></td>
                    </tr>
                    <tr>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4omini_name'); ?></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_gpt4omini'); ?></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_success'); ?></span></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_queries'); ?></td>
                    </tr>
                    <tr>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_phi4_name'); ?></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_phi4'); ?></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_success'); ?></span></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_queries'); ?></td>
                    </tr>
                    <tr>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_mistral_name'); ?></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_mistral'); ?></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_success'); ?></span></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_queries'); ?></td>
                    </tr>
                    <tr>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_model_gpt4o_name'); ?></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_gpt4o'); ?></td>
                      <td><span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_success'); ?></span></td>
                      <td><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_queries'); ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p class="text-muted small">
                <em><?php echo $CLICSHOPPING_ChatGpt->getDef('help_performance_note'); ?></em>
              </p>
            </section>

            <hr>

            <!-- Quick Decision Guide -->
            <section class="mb-4">
              <h5 class="text-primary"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_title'); ?></h5>
              <div class="card border-primary">
                <div class="card-body">
                  <h6 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_subtitle'); ?></h6>
                  <ol>
                    <li class="mb-2">
                      <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q1'); ?></strong>
                      <ul>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q1_yes'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q1_no'); ?></li>
                      </ul>
                    </li>
                    <li class="mb-2">
                      <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q2'); ?></strong>
                      <ul>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q2_yes'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q2_no'); ?></li>
                      </ul>
                    </li>
                    <li class="mb-2">
                      <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q3'); ?></strong>
                      <ul>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q3_value'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q3_cost'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q3_capability'); ?></li>
                        <li><?php echo $CLICSHOPPING_ChatGpt->getDef('help_decision_q3_diversity'); ?></li>
                      </ul>
                    </li>
                  </ol>
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

