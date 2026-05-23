<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_OrdersStatusInvoice = Registry::get('OrdersStatusInvoice');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$orders_status_invoice_inputs_string = '';
$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$languages = $CLICSHOPPING_Language->getLanguages();
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/configuration_26.gif', $CLICSHOPPING_OrdersStatusInvoice->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_OrdersStatusInvoice->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
<?php
echo HTML::button($CLICSHOPPING_OrdersStatusInvoice->getDef('button_cancel'), null, $CLICSHOPPING_OrdersStatusInvoice->link('OrdersStatusInvoice'), 'warning') . ' ';
echo HTML::form('status_orders_status_invoice', $CLICSHOPPING_OrdersStatusInvoice->link('OrdersStatusInvoice&Insert&page=' . $page));
echo HTML::button($CLICSHOPPING_OrdersStatusInvoice->getDef('button_insert'), null, null, 'success')
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('text_info_heading_new_orders_status'); ?></strong>
  </div>
  <?php echo HTML::form('status_invoice', CLICSHOPPING::link('orders_status_invoice.php', 'page=' . $page . '&action=insert')); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('text_info_edit_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('text_info_edit_intro'); ?></label>
        </div>
      </div>
    </div>
    <?php
    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      ?>
      <div class="row">
        <div class="col-md-5">
          <div class="form-group row">
            <label for="<?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('lang'); ?>"
                   class="col-5 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
            <div class="col-md-5">
              <?php echo HTML::inputField('orders_status_invoice_name[' . $languages[$i]['id'] . ']', '', 'class="form-control" required aria-required="true" Authentificator'); ?>
            </div>
          </div>
        </div>
      </div>
      <?php
    }
    ?>
    <div class="mt-1"></div>
    <div class="col-md-12">
      <span class="col-md-3"></span>
      <ul class="list-group-slider list-group-flush">
        <li class="list-group-item-slider">
          <label class="switch">
            <?php echo HTML::checkboxField('default', null, null, 'class="success"'); ?>
            <span class="slider"></span>
          </label>
        </li>
        <span class="text-slider"><?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('text_set_default'); ?></span>
      </ul>
    </div>
  </div>

  </form>
</div>