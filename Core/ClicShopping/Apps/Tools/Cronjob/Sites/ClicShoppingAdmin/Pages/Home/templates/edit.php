<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Cronjob = Registry::get('Cronjob');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$languages = $CLICSHOPPING_Language->getLanguages();
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/cron.jpeg', $CLICSHOPPING_Cronjob->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Cronjob->getDef('heading_title'); ?></span>
          <?php
          $form_action = 'Insert';

          if (isset($_GET['Update'])) {
            $cron_id = HTML::sanitize($_GET['cronId']);
            $form_action = 'Update&cronId=' . $cron_id;
          }
          ?>
          <span class="col-md-9 text-end">
            <?php
            echo HTML::form('cronjob', $CLICSHOPPING_Cronjob->link('Cronjob&' . $form_action));
            $cron = [];

            if (isset($_GET['Update'])) {
              $Qcron = $CLICSHOPPING_Cronjob->db->prepare('select code,
                                                                    cycle,
                                                                    action,
                                                                    status,
                                                                    description
                                                             from :table_cron
                                                             where cron_id = :cron_id
                                                            ');

              $Qcron->bindValue('cron_id', $cron_id);
              $Qcron->execute();

              $cron = $Qcron->ToArray();
            }

            echo HTML::button($CLICSHOPPING_Cronjob->getDef('button_cancel'), null, $CLICSHOPPING_Cronjob->link('Cronjob'), 'primary') . '&nbsp;';

            if ($form_action == 'Insert') {
              echo HTML::button($CLICSHOPPING_Cronjob->getDef('button_insert'), null, null, 'success');
            } else {
              echo HTML::button($CLICSHOPPING_Cronjob->getDef('button_update'), null, null, 'success');
            }
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div id="productsCronjobTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Cronjob->getDef('tab_general') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- //#################################################################### //-->
        <!--          ONGLET Information General de la Promotion                    //-->
        <!-- //#################################################################### //-->

        <div class="mainTitle"><?php echo $CLICSHOPPING_Cronjob->getDef('title_cronjob_general'); ?></div>
        <div class="adminformTitle" id="Information">
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <div class="mt-5"></div>

                <div class="mt-1"></div>
                <div class="row">
                  <div class="col-md-5" id="code1">
                    <div class="form-group row">
                      <label for="<?php echo $CLICSHOPPING_Cronjob->getDef('text_cronjob_code'); ?>"
                             class="col-5 col-form-label"><?php echo $CLICSHOPPING_Cronjob->getDef('text_cronjob_code'); ?></label>
                      <div class="col-md-5">
                        <?php echo HTML::inputField('code', $cron['code'] ?? '', 'required aria-required="true" placeholder="' . $CLICSHOPPING_Cronjob->getDef('text_cronjob_code') . '"'); ?>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-1"></div>
                <div class="row">
                  <div class="col-md-5" id="cycle">
                    <div class="form-group row">
                      <label for="<?php echo $CLICSHOPPING_Cronjob->getDef('text_cronjob_cycle'); ?>"
                             class="col-5 col-form-label"><?php echo $CLICSHOPPING_Cronjob->getDef('text_cronjob_cycle'); ?></label>
                      <div class="col-md-5">
                        <?php echo HTML::inputField('cycle', $cron['cycle'] ?? '', 'required aria-required="true" placeholder="' . $CLICSHOPPING_Cronjob->getDef('text_cronjob_cycle') . '"'); ?>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-1"></div>
                <div class="row">
                  <div class="col-md-5" id="action">
                    <div class="form-group row">
                      <label for="<?php echo $CLICSHOPPING_Cronjob->getDef('text_cronjob_action'); ?>"
                             class="col-5 col-form-label"><?php echo $CLICSHOPPING_Cronjob->getDef('text_cronjob_action'); ?></label>
                      <div class="col-md-5">
                        <?php echo HTML::inputField('action', $cron['action'] ?? '', 'placeholder="' . $CLICSHOPPING_Cronjob->getDef('text_cronjob_action') . '"'); ?>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-1"></div>
                <div class="row">
                  <div class="col-md-5" id="status">
                    <div class="form-group row">
                      <label for="<?php echo $CLICSHOPPING_Cronjob->getDef('text_cronjob_status'); ?>"
                             class="col-5 col-form-label"><?php echo $CLICSHOPPING_Cronjob->getDef('text_cronjob_status'); ?></label>
                      <div class="col-md-5">
                        <ul class="list-group-slider list-group-flush">
                          <li class="list-group-item-slider">
                            <label class="switch">
                              <?php echo HTML::checkboxField('status', '1', $cron['status'] ?? 0, 'class="success"'); ?>
                              <span class="slider"></span>
                            </label>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-1"></div>
                <div class="row">
                  <div class="col-md-8" id="description">
                    <div class="form-group row">
                      <label for="<?php echo $CLICSHOPPING_Cronjob->getDef('text_cronjob_description'); ?>"
                             class="col-3 col-form-label"><?php echo $CLICSHOPPING_Cronjob->getDef('text_cronjob_description'); ?></label>
                      <div class="col-md-7">
                        <?php echo HTML::textAreaField('description', $cron['description'] ?? '', 'placeholder="' . $CLICSHOPPING_Cronjob->getDef('text_cronjob_description') . '"');?>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
        <div class="mt-1"></div>
      </div>
      <?php
      //***********************************
      // extension
      //***********************************
      echo $CLICSHOPPING_Hooks->output('Cronjob', 'PageTab', null, 'display');
      ?>
    </div>
  </div>
  </form>
</div>