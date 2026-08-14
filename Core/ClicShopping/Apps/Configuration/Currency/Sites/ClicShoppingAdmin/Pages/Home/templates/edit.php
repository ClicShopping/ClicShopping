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

$CLICSHOPPING_Currency = Registry::get('Currency');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$Qcurrency = $CLICSHOPPING_Currency->db->prepare('select currencies_id,
                                                          title,
                                                          code,
                                                          symbol_left,
                                                          symbol_right,
                                                          decimal_point,
                                                          thousands_point,
                                                          decimal_places,
                                                          value,
                                                          surcharge
                                                  from :table_currencies
                                                  where currencies_id = :currencies_id
                                                ');
$Qcurrency->bindInt(':currencies_id', $_GET['cID']);
$Qcurrency->execute();

$cInfo = new ObjectInfo($Qcurrency->toArray());

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

echo HTML::form('Currency', $CLICSHOPPING_Currency->link('Currency&Update&page=' . $page . '&cID=' . $cInfo->currencies_id));
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/currencies.gif', $CLICSHOPPING_Currency->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Currency->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
<?php
echo HTML::button($CLICSHOPPING_Currency->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_Currency->getDef('button_cancel'), null, $CLICSHOPPING_Currency->link('Currency&page=' . $page . '&cID=' . $cInfo->currencies_id), 'warning');
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Currency->getDef('text_info_heading_edit_currency'); ?></strong></div>
  <div class="adminformTitle">
    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_edit_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_edit_intro'); ?></label>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_symbol_title">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_title'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_title'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('title', $cInfo->title); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_symbol_code">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_code'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_code'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('code', $cInfo->code); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_symbol_left">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_symbol_left'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_symbol_left'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('symbol_left', $cInfo->symbol_left); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_symbol_right">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_symbol_right'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_symbol_right'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('symbol_right', $cInfo->symbol_right); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_decimal_point">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_decimal_point'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_decimal_point'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('decimal_point', $cInfo->decimal_point); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_thousands_point">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_thousands_point'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_thousands_point'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('thousands_point', $cInfo->thousands_point); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_decimal_places">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_decimal_places'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_decimal_places'); ?></label>
          <div class="col-md-7">
            <?php echo HTML::inputField('decimal_places', $cInfo->decimal_places); ?>
          </div>
        </div>
      </div>
    </div>


    <div class="row" id="currencies_value">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_value'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_value'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('value', $cInfo->value); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_surcharge">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_surcharge'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_surcharge'); ?></label>
          <div class="col-md-7">
            <?php echo HTML::inputField('surcharge', $cInfo->surcharge); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="default_currencies">
      <?php
      if (DEFAULT_CURRENCY != $cInfo->code) {
        ?>
        <div class="mt-1"></div>
        <div class="col-md-12 text-center">
          <span class="col-md-3"></span>
          <ul class="list-group-slider list-group-flush">
            <span
              class="text-slider"><?php echo $CLICSHOPPING_Currency->getDef('text_info_set_as_default', ['text_set_default' => $CLICSHOPPING_Currency->getDef('text_set_default')]); ?></span>
            <li class="list-group-item-slider">
              <label class="switch">
                <?php echo HTML::checkboxField('default', null, null, 'class="success"'); ?>
                <span class="slider"></span>
              </label>
            </li>
          </ul>
        </div>
        <?php
      }
      ?>
    </div>
  </div>
</div>
</form>