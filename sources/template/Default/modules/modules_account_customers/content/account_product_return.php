<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

echo $form;

// ----------------------
// ------ Customer info   -----
// ----------------------
?>
<div class="col-md-<?php echo $content_width; ?>">
  <?php
  if ($CLICSHOPPING_MessageStack->exists('main')) {
    echo $CLICSHOPPING_MessageStack->get('main');
  }

  // ----------------------
  // ----- Infos   -----
  // ----------------------
  ?>
  <div class="mt-1"></div>

  <div class="row">
    <div class="col-md-12">
      <div class="page-title Account_CustomersReturn">
        <h3><?php echo CLICSHOPPING::getDef('module_account_product_return_heading_title'); ?></h3></div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="hr"></div>
  <div class="mt-1"></div>

  <div class="row">
    <div class="col-sm-6">
      <div class="card">
        <div class="card-header"><h5><?php echo CLICSHOPPING::getDef('text_customer_information'); ?></h5></div>
        <div class="card-body">
          <div class="card-text">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="InputFirstName"
                         class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('text_name'); ?></label>
                  <div class="col-md-5">
                    <?php echo $customers_name; ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="inputEmail"
                         class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('text_email_address'); ?></label>
                  <div class="col-md-8">
                    <?php echo $customers_email_address; ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="inputTelephone"
                         class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('text_telephone_number'); ?></label>
                  <div class="col-md-8">
                    <?php echo $customers_telephone; ?>
                  </div>
                </div>
              </div>
            </div>


            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="inputTelephone"
                         class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('text_address'); ?></label>
                  <div class="col-md-8">
                    <br/><?php echo $customers_street_address; ?><br/>
                    <?php echo $customers_suburb; ?><br/>
                    <?php echo $customers_city; ?><br/>
                    <?php echo $customers_postcode; ?><br/>
                    <?php echo $customers_country . ' ' . $customers_state; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6">
      <div class="card">
        <div class="card-header"><h5><?php echo CLICSHOPPING::getDef('text_customer_shipping_information'); ?></h5>
        </div>
        <div class="card-body">
          <div class="card-text">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="InputFirstName"
                         class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('text_name'); ?></label>
                  <div class="col-md-5">
                    <?php echo $delivery_name; ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="inputTelephone"
                         class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('text_address'); ?></label>
                  <div class="col-md-8">
                    <br/><?php echo $delivery_street_address; ?><br/>
                    <?php echo $delivery_suburb; ?><br/>
                    <?php echo $delivery_city; ?><br/>
                    <?php echo $delivery_postcode; ?><br/>
                    <?php echo $delivery_country . ' ' . $delivery_state; ?>
                    <p></p>
                    <p></p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php
  // ----------------------
  // ----- order   -----
  // ----------------------
  ?>
  <div class="mt-1"></div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="page-title Account_CustomersReturn">
        <h3><?php echo CLICSHOPPING::getDef('module_account_product_return_order_title'); ?></h3></div>
    </div>
  </div>

  <div class="mt-1"></div>
  <div class="hr"></div>
  <div class="mt-1"></div>


  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="Order"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('module_account_product_return_order_id'); ?></label>
        <div class="col-md-3">
          <?php echo $order_id; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="Order"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('module_account_product_return_order_date'); ?></label>
        <div class="col-md-3">
          <?php echo $purchased_date; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="Order"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('module_account_product_return_product_name'); ?></label>
        <div class="col-md-3">
          <?php echo $product_name; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="Order"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('module_account_product_return_product_model'); ?></label>
        <div class="col-md-3">
          <?php echo $product_model; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-1"></div>
  <div class="hr"></div>
  <div class="mt-1"></div>

  <?php
  // ----------------------
  // ----- Return   -----
  // ----------------------
  ?>
  <div class="mt-1"></div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="page-title Account_CustomersReturn">
        <h3><?php echo CLICSHOPPING::getDef('module_account_product_return_product_return_title'); ?></h3></div>
    </div>
  </div>

  <div class="mt-1"></div>
  <div class="hr"></div>
  <div class="mt-1"></div>

  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="Quantity"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('module_account_product_return_product_quantity'); ?></label>
        <div class="col-md-1">
          <?php echo HTML::inputField('product_quantity', $product_quantity, 'min="1"', 'number', null, 'form-control'); ?>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="Reason"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('module_account_product_return_reason_return'); ?></label>
        <div class="col-md-8">
          <?php echo $reason_return; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="Opened"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('module_account_product_return_product_opened'); ?></label>
        <div class="col-md-8">
          <?php echo $reason_opened; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="Comment"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('module_account_product_return_faulty'); ?></label>
        <div class="col-md-8">
          <?php echo HTML::textAreaField('comment', null, 500, 5, 'required placeholder="' . CLICSHOPPING::getDef('module_account_product_return_faulty') . '"'); ?>
        </div>
      </div>
    </div>
  </div>

  <div class="hr"></div>
  <div class="mt-1"></div>
  <div class="col-md-12">
    <div class="control-group">
      <div>
        <div class="buttonSet">
          <span
            class="col-md-2"><?php echo HTML::button(CLICSHOPPING::getDef('button_back'), null, CLICSHOPPING::link(null, 'Account&Main'), 'primary'); ?></span>
          <span
            class="col-md-2 float-end text-end"><?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), null, null, 'success'); ?></span>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
echo $endform;
?>
<div class="mt-1"></div>
<div class="col-md-12">
  <div class="row">
    <div class="alert alert-info" role="info">
      <?php echo CLICSHOPPING::getDef('text_alert_info', ['withdrawal' => $withdrawal]); ?>
    </div>
  </div>
</div>
