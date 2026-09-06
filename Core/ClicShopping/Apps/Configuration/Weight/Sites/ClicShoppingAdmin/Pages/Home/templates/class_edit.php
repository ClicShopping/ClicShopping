<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Weight\Classes\ClicShoppingAdmin\WeightAdmin;

$CLICSHOPPING_Weight = Registry::get('Weight');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

$Qweight = $CLICSHOPPING_Weight->db->prepare('select wc.weight_class_id,
                                                       wc.weight_class_key,
                                                       wc.language_id,
                                                       wc.weight_class_title,
                                                       tc.weight_class_from_id,
                                                       tc.weight_class_to_id,
                                                       tc.weight_class_rule
                                              from :table_weight_classes wc,
                                                   :table_weight_classes_rules tc 
                                              where wc.weight_class_id = :weight_class_id
                                              and tc.weight_class_to_id = :weight_class_to_id
                                              ');
$Qweight->bindInt(':weight_class_id', $_GET['wID']);
$Qweight->bindInt(':weight_class_to_id', $_GET['tID']);
$Qweight->execute();

$wInfo = new ObjectInfo($Qweight->toArray());

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

if ($CLICSHOPPING_MessageStack->exists('class_edit')) {
  echo $CLICSHOPPING_MessageStack->get('class_edit');
}
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/weight.png', $CLICSHOPPING_Weight->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Weight->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
<?php
echo HTML::form('status_tax_class', $CLICSHOPPING_Weight->link('Weight&ClassUpdate&page=' . $page . '&wID=' . $_GET['wID'] . '&tID=' . $_GET['tID']));
echo HTML::button($CLICSHOPPING_Weight->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_Weight->getDef('button_cancel'), null, $CLICSHOPPING_Weight->link('Weight'), 'warning');
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Weight->getDef('text_info_heading_edit_weight'); ?></strong></div>
  <div class="adminformTitle">

    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Weight->getDef('text_info_edit_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Weight->getDef('text_info_edit_intro'); ?></label>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Weight->getDef('text_info_class_title'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Weight->getDef('text_info_class_title'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::selectField('weight_class_id', WeightAdmin::getClassesPullDown(), $wInfo->weight_class_id); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Weight->getDef('text_info_class_title_to_id'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Weight->getDef('text_info_class_title_to_id'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::selectField('weight_class_to_id', WeightAdmin::getClassesPullDown(), $wInfo->weight_class_to_id); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Weight->getDef('text_info_class_conversaion_value'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Weight->getDef('text_info_class_conversaion_value'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('weight_class_rule', $wInfo->weight_class_rule); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
</div>