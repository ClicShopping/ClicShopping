<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\ClicShoppingAdmin\AddressAdmin;

$CLICSHOPPING_TaxRates = Registry::get('TaxRates');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Tax = Registry::get('Tax');

$Qrates = $CLICSHOPPING_TaxRates->db->prepare('select tax_rates_id,
                                                        tax_zone_id,
                                                        tax_class_id,
                                                        tax_priority,
                                                        tax_rate,
                                                        tax_description,
                                                        last_modified,
                                                        date_added,
                                                        code_tax_erp
                                                 from :table_tax_rates
                                                 where tax_rates_id = :tax_rates_id
                                                ');
$Qrates->bindInt(':tax_rates_id', $_GET['tID']);
$Qrates->execute();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/tax_rates.gif', $CLICSHOPPING_TaxRates->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TaxRates->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
<?php
echo HTML::form('tax_class', $CLICSHOPPING_TaxRates->link('TaxRates&Update&page=' . $page . '&tID=' . $Qrates->valueInt('tax_rates_id')));
echo HTML::button($CLICSHOPPING_TaxRates->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_TaxRates->getDef('button_cancel'), null, $CLICSHOPPING_TaxRates->link('TaxRates'), 'warning');
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_TaxRates->getDef('text_info_heading_edit_tax_rate'); ?></strong></div>
  <div class="adminformTitle">

    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxRates->getDef('text_info_edit_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxRates->getDef('text_info_edit_intro'); ?></label>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxRates->getDef('text_info_class_title'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxRates->getDef('text_info_class_title'); ?></label>
          <div class="col-md-5">
            <?php echo $CLICSHOPPING_Tax->getTaxClassesPullDown('tax_class_id', $Qrates->valueInt('tax_class_id')); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxRates->getDef('text_info_zone_name'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxRates->getDef('text_info_zone_name'); ?></label>
          <div class="col-md-5">
            <?php
            echo AddressAdmin::getGeoZonesPullDown('tax_zone_id', $Qrates->valueInt('tax_zone_id')); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxRates->getDef('text_info_rate_description'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxRates->getDef('text_info_rate_description'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('tax_description', $Qrates->value('tax_description')); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxRates->getDef('text_info_tax_rate'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxRates->getDef('text_info_tax_rate'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('tax_rate', $Qrates->valueDecimal('tax_rate'), 'required aria-required="true"'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxRates->getDef('text_info_tax_erp'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxRates->getDef('text_info_tax_erp'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('code_tax_erp', $Qrates->value('code_tax_erp')); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxRates->getDef('text_info_tax_rate_priority'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxRates->getDef('text_info_tax_rate_priority'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('tax_priority', $Qrates->valueInt('tax_priority')); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
</div>