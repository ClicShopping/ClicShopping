<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_EditDesign = Registry::get('EditDesign');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/edit_design.png', $CLICSHOPPING_EditDesign->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_EditDesign->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12">
    <div class="row">
      <div class="col-md-12">
        <div class="card text-center">
          <div class="card-header">
            <h3><?php echo $CLICSHOPPING_EditDesign->getDef('text_default_template', ['template' => SITE_THEMA]); ?></h3>
            <?php
            if (SITE_THEMA == 'Default') {
              echo '<h5><p class="text-danger">' . $CLICSHOPPING_EditDesign->getDef('text_warning_template') . '</p></h5>';
            }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12">
    <div class="row">
      <div class="col-sm-3">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title"><?php echo $CLICSHOPPING_EditDesign->getDef('text_gabari'); ?></h5>
            <p class="card-text"><?php echo $CLICSHOPPING_EditDesign->getDef('text_description'); ?></p>
            <span class="text-end">
              <h4>
                <?php echo HTMl::link($CLICSHOPPING_EditDesign->link('EditGabari'), '<i class="bi bi-pencil" title="' . $CLICSHOPPING_EditDesign->getDef('icon_edit') . '"></i>'); ?>
              </h4>
            </span>
          </div>
        </div>
      </div>

      <div class="col-sm-3">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title"><?php echo $CLICSHOPPING_EditDesign->getDef('text_module_content'); ?></h5>
            <p class="card-text"><?php echo $CLICSHOPPING_EditDesign->getDef('text_description'); ?></p>
            <span class="text-end">
              <h4>
                <?php echo HTMl::link($CLICSHOPPING_EditDesign->link('EditModuleContent'), '<i class="bi bi-pencil" title="' . $CLICSHOPPING_EditDesign->getDef('icon_edit') . '"></i>'); ?>
              </h4>
            </span>
          </div>
        </div>
      </div>

      <div class="col-sm-3">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title"><?php echo $CLICSHOPPING_EditDesign->getDef('text_product_listing'); ?></h5>
            <p class="card-text"><?php echo $CLICSHOPPING_EditDesign->getDef('text_description'); ?></p>
            <span class="text-end">
              <h4>
                <?php echo HTMl::link($CLICSHOPPING_EditDesign->link('EditListing'), '<i class="bi bi-pencil" title="' . $CLICSHOPPING_EditDesign->getDef('icon_edit') . '"></i>'); ?>
              </h4>
            </span>
          </div>
        </div>
      </div>

      <div class="col-sm-3">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title"><?php echo $CLICSHOPPING_EditDesign->getDef('text_css'); ?></h5>
            <p class="card-text"><?php echo $CLICSHOPPING_EditDesign->getDef('text_description'); ?></p>
            <span class="text-end">
              <h4>
                <?php echo HTMl::link($CLICSHOPPING_EditDesign->link('EditCss'), '<i class="bi bi-pencil" title="' . $CLICSHOPPING_EditDesign->getDef('icon_edit') . '"></i>'); ?>
              </h4>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-1"></div>
  <div class="col-md-12">
    <div class="row">
      <div class="alert alert-info" role="alert">
        <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_EditDesign->getDef('title_help_edit_html') . '"></i></h4> ' . $CLICSHOPPING_EditDesign->getDef('title_help_edit_html') ?></div>
        <div class="mt-1"></div>
        <div><?php echo $CLICSHOPPING_EditDesign->getDef('text_help_design'); ?></div>
      </div>
    </div>
  </div>
</div>
</div>
