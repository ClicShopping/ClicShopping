<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Hash;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Orders\ReturnOrders\Classes\ClicShoppingAdmin\ReturnProduct;

$CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Currencies = Registry::get('Currencies');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_favorites.png', $CLICSHOPPING_ReturnOrders->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ReturnOrders->getDef('heading_title'); ?></span>
          <?php
          $form_action = 'Save';
          ?>
          <span class="col-md-9 text-end">
            <?php
            echo HTML::form('return', $CLICSHOPPING_ReturnOrders->link('ReturnOrders&Save'));
            echo HTML::hiddenField('rId', $_GET['rID']);
            echo HTML::hiddenField('page', $page);
            echo HTML::button($CLICSHOPPING_ReturnOrders->getDef('button_cancel'), null, $CLICSHOPPING_ReturnOrders->link('ReturnOrders&page=' . $page . (isset($_GET['rID']) ? '&rID=' . $_GET['rID'] : '')), 'warning', null, null) . '&nbsp;';
            echo HTML::button($CLICSHOPPING_ReturnOrders->getDef('button_update'), null, null, 'success');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  $form_action = 'Insert';

  if (isset($_GET['rID'])) {
    $return_id = HTML::sanitize($_GET['rID']);
    $form_action = 'Update';

    $Qreturn = $CLICSHOPPING_ReturnOrders->db->prepare('select return_id,
                                                               return_ref,
                                                               order_id,
                                                               customer_id,
                                                               customer_firstname,
                                                               customer_lastname,
                                                               customer_telephone,
                                                               customer_email,
                                                               product_id,
                                                               product_model,
                                                               product_name,
                                                               opened,
                                                               return_reason_id,
                                                               return_action_id,
                                                               return_status_id,
                                                               comment,
                                                               date_ordered,
                                                               date_added,
                                                               date_modified,
                                                               archive
                                                       from :table_return_orders
                                                       where archive = 0
                                                       and return_id = :return_id
                                                       ');

    $Qreturn->bindInt(':return_id', $return_id);
    $Qreturn->execute();
  }
  ?>

  <div id="productsReturnOrdersTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_ReturnOrders->getDef('tab_general') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_ReturnOrders->getDef('tab_history') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <div class="tab-pane active" id="tab1">
          <div class="col-md-12 mainTitle">
            <div class="float-start"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref'); ?></div>
            <div
              class="float-end"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_user_name') . AdministratorAdmin::getUserAdmin(); ?></div>
          </div>
          <div class="adminformTitle" id="adminformTitleTab1">

            <div class="col-md-12">
              <span><h3><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_description_customer'); ?></h3></span>
            </div>


            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderId">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_id'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_id'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::inputField('order_id', $Qreturn->value('order_id'), 'required aria-required="true" id="ref_return_orders" placeholder="' . $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_id') . '"', 'ref_return_order_id'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderDate">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_date'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_date'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::inputField('date_ordered', $Qreturn->value('date_ordered'), 'required aria-required="true" id="ref_return_orders_date" placeholder="' . $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_date') . '"', 'ref_return_orders_date'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderCustomerfirstName">
              <div class="col-md-7">
                <div class="form-group row">
                  <label
                    for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_firstname'); ?>"
                    class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_firstname'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::inputField('customer_firstname',  Hash::displayDecryptedDataText($Qreturn->value('customer_firstname')), 'required aria-required="true" id="ref_return_orders_customer_first_name" placeholder="' . $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_firstname') . '"', 'ref_return_orders_customer_first_name'); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderCustomerlastName">
              <div class="col-md-7">
                <div class="form-group row">
                  <label
                    for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_lastname'); ?>"
                    class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_lastname'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::inputField('customer_lastname',  Hash::displayDecryptedDataText($Qreturn->value('customer_lastname')), 'required aria-required="true" id="ref_return_orders_customer_last__name" placeholder="' . $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_lastname') . '"', 'ref_return_orders_customer_lastname'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderCustomerEmail">
              <div class="col-md-7">
                <div class="form-group row">
                  <label
                    for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_email'); ?>"
                    class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_email'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::inputField('customer_email',  Hash::displayDecryptedDataText($Qreturn->value('customer_email')), 'required aria-required="true" id="ref_return_orders_customer_email" placeholder="' . $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_email') . '"', 'ref_return_orders_customer_customer_email'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderCustomerPhone">
              <div class="col-md-7">
                <div class="form-group row">
                  <label
                    for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_phone'); ?>"
                    class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_phone'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::inputField('customer_telephone',  Hash::displayDecryptedDataText($Qreturn->value('customer_telephone')), 'required aria-required="true" id="ref_return_orders_customer_phone" placeholder="' . $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_customer_phone') . '"', 'ref_return_orders_customer_customer_phone'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="col-md-12">
              <span><h3><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_description_order_return'); ?></h3></span>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderReturnProductName">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_product_name'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_product_name'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::inputField('product_name', $Qreturn->value('product_name'), 'required aria-required="true" id="ref_return_orders_product_name" placeholder="' . $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_product_name') . '"', 'ref_return_orders_customer_product_name'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderReturnProductModel">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_product_model'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_product_model'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::inputField('product_model', $Qreturn->value('product_model'), 'required aria-required="true" id="ref_return_orders_product_model" placeholder="' . $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_product_model') . '"', 'ref_return_orders_product_model'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderReturnReason">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_return_reason'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_return_reason'); ?></label>
                  <div class="col-md-7">
                    <?php
                    $Qreason = $CLICSHOPPING_ReturnOrders->db->prepare('select return_reason_id,
                                                                                 language_id,
                                                                                 name
                                                                          from :table_return_orders_reason
                                                                          where language_id = :language_id
                                                                          ');
                    $Qreason->bindInt(':language_id', $CLICSHOPPING_Language->getId());
                    $Qreason->execute();

                    $return_reason_array = [];

                    while ($Qreason->fetch()) {
                      $return_reason_array[] = [
                        'id' => $Qreason->valueInt('return_reason_id'),
                        'text' => $Qreason->value('name')
                      ];
                    }

                    $return_reason_id = $Qreturn->valueInt('return_reason_id');

                    echo HTML::selectField('return_reason', $return_reason_array, $return_reason_id);
                    ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>

            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderReturnProductModel">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_comment'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_comment'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::textAreaField('comment', $Qreturn->value('comment'), null, null, 'id="ref_return_orders_comment"'); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderReturnReturnAction">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_return_action'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_return_action'); ?></label>
                  <div class="col-md-7">
                    <?php echo ReturnProduct::getDropDownAction($Qreturn->valueInt('return_action_id')); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('ReturnOrders', 'ReturnOrdersContentTab1', null, 'display'); ?>
        </div>
        <?php
        // ----------------------------------------------------------- //-->
        //          Return order description                              //-->
        // ----------------------------------------------------------- //-->
        ?>
        <div class="tab-pane" id="tab2">
          <div class="col-md-12 mainTitle">
            <span><?php echo $CLICSHOPPING_ReturnOrders->getDef('tab_history'); ?></span>
          </div>
          <div class="adminformTitle" id="adminformTitleTab2">
            <div class="mt-1"></div>
            <div class="row" id="addHistory">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_add_history'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_add_history'); ?></label>
                  <div class="col-md-7">
                    <?php echo ReturnProduct::getDropDownStatus($Qreturn->valueInt('return_status_id')); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row" id="notify">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_notify'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_notify'); ?></label>
                  <div class="col-md-5">
                    <ul class="list-group-slider list-group-flush">
                      <li class="list-group-item-slider">
                        <label class="switch">
                          <?php echo HTML::checkboxField('notify', '1', $Qreturn->value('notify'), 'class="success"'); ?>
                          <span class="slider"></span>
                        </label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row" id="refReturnOrderReturnOpened">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_opened'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_opened'); ?></label>
                  <div class="col-md-7">
                    <?php echo ReturnProduct::getDropDownReasonOpened($Qreturn->valueInt('opened')); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row" id="Comment">
              <div class="col-md-7">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_comment'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_comment'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::textAreaField('comment', null, null, 5, 'placeholder="' . $CLICSHOPPING_ReturnOrders->getDef('text_ref_return_orders_comment') . '"'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <table class="table table-sm table-hover" id="statusSummary">
              <thead class="dataTableHeadingRow">
              <tr>
                <td><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_date_added'); ?></td>
                <td><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_comment'); ?></td>
                <td><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_status'); ?></td>
                <td><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_notified'); ?></td>
                <td><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_admin'); ?></td>
              </tr>
              </thead>
              <tbody>
              <?php
              $Qhistory = $CLICSHOPPING_ReturnOrders->db->prepare('select return_history_id,
                                                                         return_id,
                                                                         return_status_id,
                                                                         notify,
                                                                         comment,
                                                                         date_added,
                                                                         admin_user_name
                                                                  from :table_return_orders_history                                                        
                                                                  ');
              $Qhistory->execute();

              while ($Qhistory->fetch()) {
                ?>
                <tr>
                  <td><?php echo $Qhistory->value('date_added'); ?></td>
                  <td><?php echo $Qhistory->value('comment'); ?></td>
                  <td>
                    <?php
                    $QhistoryStatus = $CLICSHOPPING_ReturnOrders->db->prepare('select return_status_id,
                                                                                       language_id,
                                                                                       name
                                                                                from :table_return_orders_status
                                                                                where language_id = :language_id
                                                                                and :return_status_id = return_status_id
                                                                               ');
                    $QhistoryStatus->bindInt(':language_id', $CLICSHOPPING_Language->getId());
                    $QhistoryStatus->bindInt(':return_status_id', $Qhistory->valueInt('return_status_id'));
                    $QhistoryStatus->execute();

                    echo $QhistoryStatus->value('name');
                    ?>
                  <td>
                    <?php
                    if ($Qhistory->valueInt('notify') === 1) {
                      echo '<i class="bi-check text-success"></i>' . "\n";
                    } else {
                      echo '<i class="bi bi-x text-danger"></i>' . "\n";
                    }
                    ?>
                  </td>
                  <td><?php echo $Qhistory->value('admin_user_name'); ?></td>
                </tr>
                <?php
              }
              ?>
              </tbody>
            </table>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('ReturnOrders', 'ReturnOrdersContentTab2', null, 'display'); ?>
        </div>
      </div>
      <?php
      //***********************************
      // extension
      //***********************************
      echo $CLICSHOPPING_Hooks->output('ReturnOrders', 'PageTab', null, 'display');
      ?>
    </div>
  </div>
  </form>
</div>
