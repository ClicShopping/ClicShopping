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
  $CLICSHOPPING_Page = Registry::get('Site')->getPage();
  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

  if (!defined('CLICSHOPPING_APP_ECOMMERCE_EC_STATUS') || CLICSHOPPING_APP_ECOMMERCE_EC_STATUS == 'False') {
?>
<div class="alert" role="alert" id="infoMessage">
  <?php echo $CLICSHOPPING_Ecommerce->getDef('text_info_message_warning'); ?>
</div>
<?php
 }
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_Ecommerce->getDef('heading_overview'), '40', '40'); ?>
          </span>
          <span class="col-md-3 pageHeading">
            <?php echo '&nbsp;' . $CLICSHOPPING_Ecommerce->getDef('heading_overview'); ?>
          </span>
          <span class="col-md-8 text-end">
            <?php
              echo HTML::button($CLICSHOPPING_Ecommerce->getDef('button_configuration'), null, $CLICSHOPPING_Ecommerce->link('Configure'), 'primary') . ' ';
              echo HTML::button($CLICSHOPPING_Ecommerce->getDef('button_help'), null, $CLICSHOPPING_Ecommerce->link('Help'), 'info');
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
          <h5><?php echo $CLICSHOPPING_Ecommerce->getDef('section_welcome'); ?></h5>
        </div>
        <div class="card-body">
          <div class="row mb-12">
            <p><?php echo $CLICSHOPPING_Ecommerce->getDef('text_welcome_message'); ?></p>
          </div>
          <div class="col-md-12">
            <h5>📋<?php echo $CLICSHOPPING_Ecommerce->getDef('subsection_domain_info'); ?></h5>
            <table class="table table-sm">
              <tbody>
              <tr>
                <td><strong><?php echo $CLICSHOPPING_Ecommerce->getDef('label_domain_name'); ?></strong></td>
                <td><?php echo $CLICSHOPPING_Ecommerce->getDef('domain_ecommerce'); ?></td>
              </tr>
              <tr>
                <td><strong><?php echo $CLICSHOPPING_Ecommerce->getDef('label_domain_id'); ?></strong></td>
                <td><code>ecommerce</code></td>
              </tr>
              <tr>
                <td><strong><?php echo $CLICSHOPPING_Ecommerce->getDef('label_pure_llm_mode'); ?></strong></td>
                <td><span class="badge bg-success"><?php echo $CLICSHOPPING_Ecommerce->getDef('status_enabled'); ?></span></td>
              </tr>
              <tr>
                <td><strong><?php echo $CLICSHOPPING_Ecommerce->getDef('label_language_processing'); ?></strong></td>
                <td><?php echo $CLICSHOPPING_Ecommerce->getDef('text_english_processing'); ?></td>
              </tr>
              </tbody>
            </table>
          </div>
          <div class="row">
            <div class="col-md-12">
              <h5>📋<?php echo $CLICSHOPPING_Ecommerce->getDef('subsection_supported_entities'); ?></h5>
              <div class="row">
                <div class="col-md-6 mb-4">
                  <ul class="list-group">
                    <li class="list-group-item">
                      <strong><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_products'); ?></strong>
                      <br><small class="text-muted"><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_products_desc'); ?></small>
                    </li>
                    <li class="list-group-item">
                      <strong><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_orders'); ?></strong>
                      <br><small class="text-muted"><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_orders_desc'); ?></small>
                    </li>
                    <li class="list-group-item">
                      <strong><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_customers'); ?></strong>
                      <br><small class="text-muted"><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_customers_desc'); ?></small>
                    </li>
                    <li class="list-group-item">
                      <strong><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_categories'); ?></strong>
                      <br><small class="text-muted"><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_categories_desc'); ?></small>
                    </li>
                  </ul>
                </div>

                <div class="col-md-6 mb-4">
                  <ul class="list-group">
                    <li class="list-group-item">
                      <strong><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_manufacturers'); ?></strong>
                      <br><small class="text-muted"><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_manufacturers_desc'); ?></small>
                    </li>
                    <li class="list-group-item">
                      <strong><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_suppliers'); ?></strong>
                      <br><small class="text-muted"><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_suppliers_desc'); ?></small>
                    </li>
                    <li class="list-group-item">
                      <strong><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_reviews'); ?></strong>
                      <br><small class="text-muted"><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_reviews_desc'); ?></small>
                    </li>
                    <li class="list-group-item">
                      <strong><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_return_orders'); ?></strong>
                      <br><small class="text-muted"><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_return_orders_desc'); ?></small>
                    </li>
                    <li class="list-group-item">
                      <strong><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_reviews_sentiment'); ?></strong>
                      <br><small class="text-muted"><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_reviews_sentiment_desc'); ?></small>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
          <hr>
          <div class="row">
            <div class="col-12">
              <h5>📋<?php echo $CLICSHOPPING_Ecommerce->getDef('subsection_query_capabilities'); ?></h5>
              <div class="alert alert-info">
                <p><?php echo $CLICSHOPPING_Ecommerce->getDef('text_query_capabilities'); ?></p>
              </div>
              <div class="row">
                <div class="col-md-4">
                  <h6><?php echo $CLICSHOPPING_Ecommerce->getDef('subsection_semantic_queries'); ?></h6>
                  <ul>
                    <li><?php echo $CLICSHOPPING_Ecommerce->getDef('query_example_1'); ?></li>
                    <li><?php echo $CLICSHOPPING_Ecommerce->getDef('query_example_2'); ?></li>
                    <li><?php echo $CLICSHOPPING_Ecommerce->getDef('query_example_3'); ?></li>
                  </ul>
                </div>
                <div class="col-md-4">
                  <h6><?php echo $CLICSHOPPING_Ecommerce->getDef('subsection_analytics_queries'); ?></h6>
                  <ul>
                    <li><?php echo $CLICSHOPPING_Ecommerce->getDef('query_example_4'); ?></li>
                    <li><?php echo $CLICSHOPPING_Ecommerce->getDef('query_example_5'); ?></li>
                    <li><?php echo $CLICSHOPPING_Ecommerce->getDef('query_example_6'); ?></li>
                  </ul>
                </div>
                <div class="col-md-4">
                  <h6><?php echo $CLICSHOPPING_Ecommerce->getDef('subsection_hybrid_queries'); ?></h6>
                  <ul>
                    <li><?php echo $CLICSHOPPING_Ecommerce->getDef('query_example_7'); ?></li>
                    <li><?php echo $CLICSHOPPING_Ecommerce->getDef('query_example_8'); ?></li>
                    <li><?php echo $CLICSHOPPING_Ecommerce->getDef('query_example_9'); ?></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="py-4"></div>
