<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Apps;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Groups = Registry::get('Groups');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

// Permettre l'utilisation de des groupes clients
if (MODE_B2B_B2C == 'False') CLICSHOPPING::redirect();

$error = false;
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/define_language.gif', $CLICSHOPPING_Groups->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Groups->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end">
<?php
echo HTML::form('customers', $CLICSHOPPING_Groups->link('Groups&Insert'), 'post', 'onSubmit="return check_form();"');
echo HTML::button($CLICSHOPPING_Groups->getDef('button_insert'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_Groups->getDef('button_cancel'), null, $CLICSHOPPING_Groups->link('Groups'), 'warning');
?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-1"></div>
  <?php
  if ($error === true) {
    ?>
    <div class="alert alert-warning">
      <?php
      if ($error == 'categorie') {
        echo '&nbsp;<h4><i class="bi bi-exclamation-diamond" title="' . $CLICSHOPPING_Groups->getDef('icon_warning') . '"></i></h4>&nbsp;' . $CLICSHOPPING_Groups->getDef('entry_groups_categorie_error');
      } elseif ($error == 'name') {
        echo '&nbsp;<h4><i class="bi bi-exclamation-diamond" title="' . $CLICSHOPPING_Groups->getDef('icon_warning') . '"></i></h4>&nbsp;' . $CLICSHOPPING_Groups->getDef('entry_groups_name_error');
      }
      ?>
    </div>
    <div class="mt-1"></div>
    <?php
  }
  ?>
  <!-- //################################################################################################################ -->
  <!--          ONGLET General informations groupe          //-->
  <!-- //################################################################################################################ -->
  <div id="CustomersGroupTab" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Groups->getDef('tab_general') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Groups->getDef('tab_orders'); ?></a></li>
      <li
        class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Groups->getDef('tab_shipping'); ?></a></li>
    </ul>

    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- //################################################################################################################ -->
        <!--          ONGLET General informations groupe          //-->
        <!-- //################################################################################################################ -->
        <div class="tab-pane active" id="tab1">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Groups->getDef('title_group_name'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Groups->getDef('entry_groups_name'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Groups->getDef('entry_groups_name'); ?></label>
                  <div class="col-md-5">
                    <?php
                    if ($error == "name") {
                      echo HTML::inputField('customers_group_name', '', 'required aria-required="true" id="customers_group_name" placeholder="' . $CLICSHOPPING_Groups->getDef('entry_groups_name') . '" maxlength="32" style="border: 2px solid #FF0000"', true) . '&nbsp;' . (!\is_null($CLICSHOPPING_Groups->getDef('entry_groups_name')) ? '<span class="inputRequirement"></span>' : '');
                    } else {
                      echo HTML::inputField('customers_group_name', '', 'required aria-required="true" id="customers_group_name" placeholder="' . $CLICSHOPPING_Groups->getDef('entry_groups_name') . '" maxlength="32"', true) . '&nbsp;' . (!\is_null($CLICSHOPPING_Groups->getDef('entry_groups_name')) ? '<span class="inputRequirement"></span>' : '');
                    }
                    ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Groups->getDef('entry_color_bar'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Groups->getDef('entry_color_bar'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('color_bar', null, 'size=12 class="color {pickerPosition:\'right\'}"', false); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_Groups->getDef('title_default_discount'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Groups->getDef('entry_valeur_discount'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Groups->getDef('entry_valeur_discount'); ?></label>
                  <div class="col-md-8">
                    <span
                      class="col-md-1 main"><?php echo HTML::inputField('customers_group_discount', null, 'required aria-required="true" id="customers_group_discount" maxlength="5" size=5', false); ?></span>
                    <span
                      class="col-md-5 main"><?php echo $CLICSHOPPING_Groups->getDef('entry_valeur_discount_note'); ?></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_Groups->getDef('title_default_quantity'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Groups->getDef('entry_text_quantity_default'); ?>"
                         class="col-10 col-form-label"><?php echo $CLICSHOPPING_Groups->getDef('entry_text_quantity_default'); ?></label>
                  <div class="col-md-8">
                    <?php echo HTML::inputField('customers_group_quantity_default', null, 'maxlength="5" size=5', false); ?>
                    <span
                      class="col-md-5 main"><?php echo $CLICSHOPPING_Groups->getDef('entry_text_quantity_note'); ?></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_Groups->getDef('title_group_tax'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-12">
                <span class="col-md-2 main"><?php echo $CLICSHOPPING_Groups->getDef('entry_group_tax'); ?></span>
                <div class="custom-control custom-radio custom-control-inline">
                  <?php echo HTML::radioField('group_tax', 'true', true, 'class="custom-control-input" id="text_group_tax_inc" name="text_group_tax_inc"'); ?>
                  <label class="custom-control-label"
                         for="text_group_tax_inc"><?php echo $CLICSHOPPING_Groups->getDef('text_group_tax_inc'); ?></label>
                </div>
                <div class="custom-control custom-radio custom-control-inline">
                  <?php echo HTML::radioField('group_tax', 'false', null, 'class="custom-control-input" id="text_group_tax_ex" name="text_group_tax_ex"'); ?>
                  <label class="custom-control-label"
                         for="text_group_tax_ex"><?php echo $CLICSHOPPING_Groups->getDef('text_group_tax_ex'); ?></label>
                </div>
                <span class="col-md-5 main"><?php echo $CLICSHOPPING_Groups->getDef('entry_group_tax_note'); ?></span>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Groups->getDef('help_title_onglet_general') . '"></i></h4> ' . $CLICSHOPPING_Groups->getDef('help_title_onglet_general') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_Groups->getDef('help_group_tax'); ?></div>
          </div>
        </div>
        <!-- //################################################################################################################ -->
        <!--          ONGLET Facturation          //-->
        <!-- //################################################################################################################ -->
        <div class="tab-pane" id="tab2">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Groups->getDef('title_order_customer_default'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <div class="col-md-5">
                    <div class="custom-control custom-radio">
                      <?php echo HTML::radioField('group_order_taxe', '0', true, 'class="custom-control-input" id="options_order_taxe" name="options_order_taxe"'); ?>
                      <label class="custom-control-label"
                             for="options_order_taxe"><?php echo $CLICSHOPPING_Groups->getDef('options_order_taxe'); ?></label>
                    </div>
                    <div class="custom-control custom-radio">
                      <?php echo HTML::radioField('group_order_taxe', '1', null, 'class="custom-control-input" id="options_order_no_taxe" name="options_order_no_taxe"'); ?>
                      <label class="custom-control-label"
                             for="options_order_no_taxe"><?php echo $CLICSHOPPING_Groups->getDef('options_order_no_taxe'); ?></label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_Groups->getDef('title_group_paiement_default'); ?></div>
          <div class="adminformTitle">
            <?php
            $module_key = 'MODULE_PAYMENT_INSTALLED';

            $Qconfiguration_payment = $CLICSHOPPING_Groups->db->prepare('select configuration_value
                                                                from :table_configuration
                                                                where configuration_key = :configuration_key
                                                              ');
            $Qconfiguration_payment->bindValue(':configuration_key', $module_key);
            $Qconfiguration_payment->execute();

            $modules_payment = explode(';', $Qconfiguration_payment->value('configuration_value'));
            $module_active = $modules_payment;

            $include_modules = [];

            foreach ($modules_payment as $value) {
              if (strpos($value, '\\') !== false) {
                $class = Apps::getModuleClass($value, 'Payment');

                $include_modules[] = ['class' => $value,
                  'file' => $class
                ];
              } else {
                $class = basename($value, '.php');
                $include_modules[] = ['class' => $class,
                  'file' => $value
                ];
              }
            }

            for ($i = 0, $n = \count($include_modules); $i < $n; $i++) {
              if (strpos($include_modules[$i]['class'], '\\') !== false) {
                Registry::set('Payment_' . str_replace('\\', '_', $include_modules[$i]['class']), new $include_modules[$i]['file']);
                $module = Registry::get('Payment_' . str_replace('\\', '_', $include_modules[$i]['class']));
                ?>
                <div class="row">
                  <div class="col-md-12">
                    <div class="form-group row">
                      <div class="col-md-12">
                        <ul class="list-group-slider list-group-flush">
                          <li class="list-group-item-slider">
                            <label class="switch">
                              <?php echo HTML::checkboxField('payment_unallowed[' . $i . ']', $module->code, 1, 'class="success"'); ?>
                              <span class="slider"></span>
                            </label>
                          </li>
                          <span class="text-slider"><?php echo $module->title; ?></span>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
                <?php
                if (isset($_POST['payment_unallowed'][$i]) && $_POST['payment_unallowed'][$i]) {
                  $_POST['group_payment_unallowed'] .= $_POST['payment_unallowed'][$i] . ',';
                }
              }
            } // end for
            ?>

          </div>
          <div class="mt-1"></div>
          <div class="alert alert-info">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Groups->getDef('help_title_onglet_facturation') . '"></i></h4> ' . $CLICSHOPPING_Groups->getDef('help_title_onglet_facturation') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_Groups->getDef('help_order_tax'); ?></div>
          </div>
        </div>
        <!-- //################################################################################################################ -->
        <!--          ONGLET Livraison          //-->
        <!-- //################################################################################################################ -->
        <div class="tab-pane" id="tab3">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Groups->getDef('title_group_shipping_default'); ?></div>
          <div class="adminformTitle">
            <?php
            $module_key = 'MODULE_SHIPPING_INSTALLED';

            $Qconfiguration_shipping = $CLICSHOPPING_Groups->db->prepare('select configuration_value
                                                                            from :table_configuration
                                                                            where configuration_key = :configuration_key
                                                                          ');
            $Qconfiguration_shipping->bindValue(':configuration_key', $module_key);
            $Qconfiguration_shipping->execute();

            $modules_shipping = explode(';', $Qconfiguration_shipping->value('configuration_value'));
            $module_active = $modules_shipping;

            $include_modules = [];

            foreach ($modules_shipping as $value) {
              if (strpos($value, '\\') !== false) {
                $class = Apps::getModuleClass($value, 'Shipping');

                $include_modules[] = ['class' => $value,
                  'file' => $class
                ];
              }
            }

            for ($i = 0, $n = \count($include_modules); $i < $n; $i++) {
              if (strpos($include_modules[$i]['class'], '\\') !== false) {
                Registry::set('Shipping_' . str_replace('\\', '_', $include_modules[$i]['class']), new $include_modules[$i]['file']);
                $module = Registry::get('Shipping_' . str_replace('\\', '_', $include_modules[$i]['class']));
                ?>
                <div class="row">
                  <div class="col-md-5">
                    <div class="form-group row">
                      <div class="col-md-12">
                        <ul class="list-group-slider list-group-flush">
                          <li class="list-group-item-slider">
                            <label class="switch">
                              <?php echo HTML::checkboxField('shipping_unallowed[' . $i . ']', $module->code, 1, 'class="success"'); ?>
                              <span class="slider"></span>
                            </label>
                          </li>
                          <span class="text-slider"><?php echo $module->title; ?></span>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
                <?php
                if (isset($_POST['shipping_unallowed'][$i]) && $_POST['shipping_unallowed'][$i]) {
                  $_POST['group_shipping_unallowed'] .= $_POST['shipping_unallowed'][$i] . ',';
                }
              }
            } // end for
            ?>
          </div>
        </div>
        </form>
      </div>
    </div>
    <?php echo $CLICSHOPPING_Hooks->output('Groups', 'pageTab', null, 'display'); ?>
  </div>
</div>
<script src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/colorpicker/jscolor.js'); ?>"></script>
