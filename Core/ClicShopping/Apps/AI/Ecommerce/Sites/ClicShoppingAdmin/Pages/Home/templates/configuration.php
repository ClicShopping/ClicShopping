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
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_Ecommerce->getDef('heading_configuration'), '40', '40'); ?>
          </span>
          <span class="col-md-3 pageHeading">
            <?php echo '&nbsp;' . $CLICSHOPPING_Ecommerce->getDef('heading_configuration'); ?>
          </span>
          <span class="col-md-8 text-end">
            <?php
              echo HTML::button($CLICSHOPPING_Ecommerce->getDef('button_back'), null, $CLICSHOPPING_Ecommerce->link('Configuration'), 'primary');
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
          ⚙️ <?php echo $CLICSHOPPING_Ecommerce->getDef('section_domain_configuration'); ?>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            <h5><?php echo $CLICSHOPPING_Ecommerce->getDef('text_configuration_info_title'); ?></h5>
            <p><?php echo $CLICSHOPPING_Ecommerce->getDef('text_configuration_info'); ?></p>
          </div>

          <div class="row mt-4">
            <div class="col-md-6">
              <div class="card">
                <div class="card-header">
                  🔧 <?php echo $CLICSHOPPING_Ecommerce->getDef('subsection_entity_config'); ?>
                </div>
                <div class="card-body">
                  <p><?php echo $CLICSHOPPING_Ecommerce->getDef('text_entity_config_desc'); ?></p>
                  <table class="table table-sm">
                    <thead>
                      <tr>
                        <th><?php echo $CLICSHOPPING_Ecommerce->getDef('column_entity'); ?></th>
                        <th><?php echo $CLICSHOPPING_Ecommerce->getDef('column_table'); ?></th>
                        <th><?php echo $CLICSHOPPING_Ecommerce->getDef('column_id_column'); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_products'); ?></td>
                        <td><code>products</code></td>
                        <td><code>products_id</code></td>
                      </tr>
                      <tr>
                        <td><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_orders'); ?></td>
                        <td><code>orders</code></td>
                        <td><code>orders_id</code></td>
                      </tr>
                      <tr>
                        <td><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_customers'); ?></td>
                        <td><code>customers</code></td>
                        <td><code>customers_id</code></td>
                      </tr>
                      <tr>
                        <td><?php echo $CLICSHOPPING_Ecommerce->getDef('entity_categories'); ?></td>
                        <td><code>categories</code></td>
                        <td><code>categories_id</code></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="card">
                <div class="card-header">
                  🛡️ <?php echo $CLICSHOPPING_Ecommerce->getDef('subsection_guardrails'); ?>
                </div>
                <div class="card-body">
                  <p><?php echo $CLICSHOPPING_Ecommerce->getDef('text_guardrails_desc'); ?></p>
                  <div class="alert alert-warning">
                    <strong><?php echo $CLICSHOPPING_Ecommerce->getDef('text_sensitive_tables'); ?>:</strong>
                    <ul class="mb-0">
                      <li><code>customers_password</code></li>
                      <li><code>orders_payment_info</code></li>
                      <li><code>customers_email</code></li>
                      <li><code>customers_phone</code></li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row mt-4">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  📝 <?php echo $CLICSHOPPING_Ecommerce->getDef('subsection_llm_prompts'); ?>
                </div>
                <div class="card-body">
                  <p><?php echo $CLICSHOPPING_Ecommerce->getDef('text_llm_prompts_desc'); ?></p>
                  <div class="alert alert-info">
                    <p><?php echo $CLICSHOPPING_Ecommerce->getDef('text_prompts_location'); ?></p>
                    <code>ClicShoppingAdmin/Core/Languages/{language}/ecommerce/</code>
                  </div>
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