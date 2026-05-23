<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Ecommerce = Registry::get('Ecommerce');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_Ecommerce->getDef('heading_help'), '40', '40'); ?>
          </span>
          <span class="col-md-3 pageHeading">
            <?php echo '&nbsp;' . $CLICSHOPPING_Ecommerce->getDef('heading_help'); ?>
          </span>
          <span class="col-md-8 text-end">
            <?php
              echo HTML::button($CLICSHOPPING_Ecommerce->getDef('button_back'), null, $CLICSHOPPING_Ecommerce->link('Help'), 'primary');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3"></div>

  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          ❓ <?php echo $CLICSHOPPING_Ecommerce->getDef('section_help'); ?>
        </div>
        <div class="card-body">
          <div class="accordion" id="helpAccordion">
            <!-- FAQ 1 -->
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                  <?php echo $CLICSHOPPING_Ecommerce->getDef('faq_1_question'); ?>
                </button>
              </h2>
              <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                  <?php echo $CLICSHOPPING_Ecommerce->getDef('faq_1_answer'); ?>
                </div>
              </div>
            </div>

            <!-- FAQ 2 -->
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                  <?php echo $CLICSHOPPING_Ecommerce->getDef('faq_2_question'); ?>
                </button>
              </h2>
              <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                  <?php echo $CLICSHOPPING_Ecommerce->getDef('faq_2_answer'); ?>
                </div>
              </div>
            </div>

            <!-- FAQ 3 -->
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                  <?php echo $CLICSHOPPING_Ecommerce->getDef('faq_3_question'); ?>
                </button>
              </h2>
              <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                  <?php echo $CLICSHOPPING_Ecommerce->getDef('faq_3_answer'); ?>
                </div>
              </div>
            </div>

            <!-- FAQ 4 -->
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                  <?php echo $CLICSHOPPING_Ecommerce->getDef('faq_4_question'); ?>
                </button>
              </h2>
              <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                  <?php echo $CLICSHOPPING_Ecommerce->getDef('faq_4_answer'); ?>
                </div>
              </div>
            </div>

            <!-- FAQ 5 -->
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                  <?php echo $CLICSHOPPING_Ecommerce->getDef('faq_5_question'); ?>
                </button>
              </h2>
              <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                <div class="accordion-body">
                  <?php echo $CLICSHOPPING_Ecommerce->getDef('faq_5_answer'); ?>
                </div>
              </div>
            </div>
          </div>

          <hr class="mt-4">

          <div class="row mt-4">
            <div class="col-md-6">
              <h5><?php echo $CLICSHOPPING_Ecommerce->getDef('section_documentation'); ?></h5>
              <ul class="list-group">
                <li class="list-group-item">
                  <a href="#" target="_blank">
                    📖 <?php echo $CLICSHOPPING_Ecommerce->getDef('doc_architecture'); ?>
                  </a>
                </li>
                <li class="list-group-item">
                  <a href="#" target="_blank">
                    📖 <?php echo $CLICSHOPPING_Ecommerce->getDef('doc_api'); ?>
                  </a>
                </li>
                <li class="list-group-item">
                  <a href="#" target="_blank">
                    📖 <?php echo $CLICSHOPPING_Ecommerce->getDef('doc_examples'); ?>
                  </a>
                </li>
              </ul>
            </div>
            <div class="col-md-6">
              <h5><?php echo $CLICSHOPPING_Ecommerce->getDef('section_support'); ?></h5>
              <div class="alert alert-info">
                <p><strong><?php echo $CLICSHOPPING_Ecommerce->getDef('support_contact'); ?></strong></p>
                <p><?php echo $CLICSHOPPING_Ecommerce->getDef('support_email'); ?></p>
                <p><?php echo $CLICSHOPPING_Ecommerce->getDef('support_forum'); ?></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
