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

$CLICSHOPPING_Language = Registry::get('Language');
Registry::set('WeightAdmin', new WeightAdmin());
$CLICSHOPPING_WeightAdmin = Registry::get('WeightAdmin');


$Qweight = $CLICSHOPPING_Weight->db->prepare('select language_id,
                                                       weight_class_title,
                                                       weight_class_key
                                              from :table_weight_classes
                                              where weight_class_id = :weight_class_id
                                              ');
$Qweight->bindInt(':weight_class_id', $_GET['wID']);
$Qweight->execute();

$wInfo = new ObjectInfo($Qweight->toArray());

$languages = $CLICSHOPPING_Language->getLanguages();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
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
echo HTML::form('weight_edit', $CLICSHOPPING_Weight->link('Weight&WeightUpdate&page=' . $page));
echo HTML::button($CLICSHOPPING_Weight->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_Weight->getDef('button_cancel'), null, $CLICSHOPPING_Weight->link('Weight'), 'warning');

echo HTML::hiddenField('weight_class_id', HTML::sanitize($_GET['wID']));
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
            <?php
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="code"
                           class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                    <div class="col-md-10">
                      <?php echo HTML::inputField('weight_class_title[' . $languages[$i]['id'] . ']', (isset($weight_class_title[$languages[$i]['id']]) ? $weight_class_title[$languages[$i]['id']] : $CLICSHOPPING_WeightAdmin->getTitle($_GET['wID'], $languages[$i]['id'])), 'class="form-control" required aria-required="true" Authentificator id="weight_class_title" placeholder="' . $CLICSHOPPING_Weight->getDef('text_weight_class_title') . '"', true) . '&nbsp;'; ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="weight_class_title"
                 class="col-2 col-form-label"><?php echo $CLICSHOPPING_Weight->getDef('text_weight_class_key'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('weight_class_key', $wInfo->weight_class_key, 'class="form-control" required aria-required="true" Authentificator id="weight_class_title" placeholder="' . $CLICSHOPPING_Weight->getDef('text_weight_class_key') . '"'); ?>
          </div>
        </div>
      </div>
    </div>
    </form>
  </div>