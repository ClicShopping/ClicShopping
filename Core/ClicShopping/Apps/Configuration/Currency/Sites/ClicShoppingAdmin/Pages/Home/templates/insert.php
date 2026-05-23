<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Currency = Registry::get('Currency');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qcurrency = $CLICSHOPPING_Currency->db->prepare('select currencies_id,
                                                          code,
                                                          title,
                                                          symbol_left,
                                                          symbol_right,
                                                          decimal_point,
                                                          thousands_point,
                                                          decimal_places,
                                                          value,
                                                          surcharge
                                                    from :table_currencies
                                                    ');
$Qcurrency->execute();

$cInfo = new ObjectInfo($Qcurrency->toArray());

$currency_select = json_decode(file_get_contents(CLICSHOPPING::BASE_DIR . 'External/CommonCurrencies.json'), true);
$currency_select_array = array(array('id' => '', 'text' => $CLICSHOPPING_Currency->getDef('text_info_common_currency')));

foreach ($currency_select as $cs) {
  if (!isset($CLICSHOPPING_Currency->currency[$cs['code']])) {
    $currency_select_array[] = array('id' => $cs['code'], 'text' => '[' . $cs['code'] . '] ' . $cs['title']);
  }
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
echo HTML::form('currency', $CLICSHOPPING_Currency->link('Currency&Insert&page=' . $page . (isset($cInfo) ? '&cID=' . $cInfo->currencies_id : '')));
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
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Currency->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
<?php
echo HTML::button($CLICSHOPPING_Currency->getDef('button_cancel'), null, $CLICSHOPPING_Currency->link('Currency&page=' . $page), 'warning') . ' ';
echo HTML::button($CLICSHOPPING_Currency->getDef('button_insert'), null, null, 'success');
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Currency->getDef('text_info_heading_new_currency'); ?></strong></div>
  <div class="adminformTitle">
    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_insert_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_insert_intro'); ?></label>
        </div>
      </div>
    </div>


    <div class="row" id="currency_choice">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_choice'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_choice'); ?></label>
          <div class="col-md-7">
            <?php echo HTML::selectField('cs', $currency_select_array, '', 'onchange="updateForm();"'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_symbol_title">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_title'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_title'); ?></label>
          <div class="col-md-7">
            <?php echo HTML::inputField('title', null, 'required aria-required="true"'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_symbol_code">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_code'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_code'); ?></label>
          <div class="col-md-7">
            <?php echo HTML::inputField('code', null, 'required aria-required="true"'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_symbol_left">
      <div class="col-md-7">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_symbol_left'); ?>"
                 class="col-7 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_symbol_left'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('symbol_left'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_symbol_right">
      <div class="col-md-7">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_symbol_right'); ?>"
                 class="col-7 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_symbol_right'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('symbol_right'); ?>
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
          <div class="col-md-7">
            <?php echo HTML::inputField('thousands_point'); ?>
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
            <?php echo HTML::inputField('decimal_places'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="currencies_value">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_value'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Currency->getDef('text_info_currency_value'); ?></label>
          <div class="col-md-7">
            <?php echo HTML::inputField('value'); ?>
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
            <?php echo HTML::inputField('surcharge'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row" id="default_currencies">
      <div class="col-md-12">
        <span class="col-md-5"></span>
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
    </div>
  </div>
</div>
</form>

<script type="text/javascript">
  var currency_select = new Array();
  <?php
  foreach ($currency_select_array as $cs) {
    if (!empty($cs['id'])) {
      echo 'currency_select["' . $cs['id'] . '"] = new Array("' . $currency_select[$cs['id']]['title'] . '", "' . $currency_select[$cs['id']]['symbol_left'] . '", "' . $currency_select[$cs['id']]['symbol_right'] . '", "' . $currency_select[$cs['id']]['decimal_point'] . '", "' . $currency_select[$cs['id']]['thousands_point'] . '", "' . $currency_select[$cs['id']]['decimal_places'] . '");' . "\n";
    }
  }
  ?>

  function updateForm() {
    var cs = document.forms["currency"].cs[document.forms["currency"].cs.selectedIndex].value;

    document.forms["currency"].title.value = currency_select[cs][0];
    document.forms["currency"].code.value = cs;
    document.forms["currency"].symbol_left.value = currency_select[cs][1];
    document.forms["currency"].symbol_right.value = currency_select[cs][2];
    document.forms["currency"].decimal_point.value = currency_select[cs][3];
    document.forms["currency"].thousands_point.value = currency_select[cs][4];
    document.forms["currency"].decimal_places.value = currency_select[cs][5];
    document.forms["currency"].value.value = 1;
  }
</script>