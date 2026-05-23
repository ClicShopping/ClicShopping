<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Gdpr = Registry::get('Gdpr');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/client_attente.gif', $CLICSHOPPING_Gdpr->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Gdpr->getDef('heading_title'); ?></span>

          <span class="col-md-3">
              <?php
              echo HTML::form('search', $CLICSHOPPING_Gdpr->link('Gdpr'), 'post', '', ['session_id' => true]);
              echo HTML::inputField('search', '', 'id="inputKeywords" placeholder="' . $CLICSHOPPING_Gdpr->getDef('heading_title_search') . '"');
              ?>
            </form>
            <?php
            if (isset($_POST['search'])) {
              ?>
              <span
                class="text-end"><?php echo HTML::button($CLICSHOPPING_Gdpr->getDef('button_reset'), null, $CLICSHOPPING_Gdpr->link('Gdpr'), 'warning'); ?></span>
              <?php
            }
            ?>
          </span>
          <span class="col-md-3 text-end">
            <?php echo HTML::button($CLICSHOPPING_Gdpr->getDef('button_configure'), null, $CLICSHOPPING_Gdpr->link('Configure'), 'primary'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 alert alert-warning" role="alert">
    <?php echo $CLICSHOPPING_Gdpr->getDef('text_info', ['date_info' => CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_DATE]); ?>
  </div>
  <div class="mt-1"></div>
  <?php echo HTML::form('delete_all', $CLICSHOPPING_Gdpr->link('Gdpr&DeleteAll&page=' . $page)); ?>

  <div id="toolbar" class="float-end">
    <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Gdpr->getDef('button_delete'); ?></button>
  </div>
  <div class="mt-1"></div>
  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-select-item-name="selected[]"
    data-click-to-select="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected" data-sortable="true" data-visible="false"
          data-switchable="false"><?php echo $CLICSHOPPING_Gdpr->getDef('id'); ?></th>
      <th data-field="lastname"><?php echo $CLICSHOPPING_Gdpr->getDef('table_heading_lastname'); ?></th>
      <th data-field="firstname"><?php echo $CLICSHOPPING_Gdpr->getDef('table_heading_firstname'); ?></th>
      <th data-field="company"><?php echo $CLICSHOPPING_Gdpr->getDef('table_heading_email'); ?></th>
      <th data-field="account_created"
          class="text-end"><?php echo $CLICSHOPPING_Gdpr->getDef('table_heading_account_last_logon'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Gdpr->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $search = '';

    if (isset($_POST['search'])) {
      $keywords = HTML::sanitize($_POST['search']);

      $Qcustomers = $CLICSHOPPING_Gdpr->db->prepare('select SQL_CALC_FOUND_ROWS c.customers_id,
                                                                                   c.customers_lastname,
                                                                                   c.customers_firstname,
                                                                                   c.customers_email_address,
                                                                                   c.gdpr,
                                                                                   ci.customers_info_id,
                                                                                   datediff(now(), ci.customers_info_date_of_last_logon) as datediff
                                                        from :table_customers,
                                                             :table_customers_info ci
                                                        where (c.customers_lastname like :keywords
                                                                or c.customers_firstname like :keywords
                                                                or c.customers_email_address like :keywords
                                                              )
                                                        and c.gdpr = 0
                                                        and c.customers_id = ci.customers_info_id
                                                        limit :page_set_offset,
                                                              :page_set_max_results
                                                        ');
      $Qcustomers->bindValue(':keywords', '%' . $keywords . '%');
      $Qcustomers->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);

      $Qcustomers->execute();
    } else {
      $Qcustomers = $CLICSHOPPING_Gdpr->db->prepare('select SQL_CALC_FOUND_ROWS c.customers_id,
                                                                                  c.customers_lastname,
                                                                                  c.customers_firstname,
                                                                                  c.customers_email_address,
                                                                                  c.gdpr,
                                                                                  ci.customers_info_id,
                                                                                  datediff(now(), ci.customers_info_date_of_last_logon) as datediff
                                                        from :table_customers c,
                                                             :table_customers_info ci
                                                        where c.gdpr = 0
                                                        and c.customers_id = ci.customers_info_id                                                      
                                                        limit :page_set_offset,
                                                              :page_set_max_results
                                                      ');
      $Qcustomers->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);

      $Qcustomers->execute();
    }
    $listingTotalRow = $Qcustomers->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qcustomers->fetch()) {
        if ($Qcustomers->value('datediff') > (int)CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_DATE) {
          ?>
          <tr>
            <td></td>
            <td><?php echo $Qcustomers->valueInt('customers_id'); ?></td>
            <td></td>
            <?php echo Hash::displayDecryptedDataText($Qcustomers->value('customers_lastname')); ?></td>
            <td><?php echo Hash::displayDecryptedDataText($Qcustomers->value('customers_firstname')); ?></td>
            <td><?php echo Hash::displayDecryptedDataText($Qcustomers->value('customers_email_address')); ?></td>
            <td
              class="text-end"><?php echo DateTime::toShort($Qcustomers->value('customers_info_date_of_last_logon')); ?></td>
            <td class="text-end">
              <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
                <?php
                echo HTML::link(CLICSHOPPING::link(null, 'A&Customers\Customers&Edit&cID=' . $Qcustomers->valueInt('customers_id')), '<h4><i class="bi bi-person" title="' . $CLICSHOPPING_Gdpr->getDef('icon_edit_customer') . '"></i></h4>');
                echo '&nbsp;';
                ?>
              </div>
            </td>
          </tr>
          <?php
        }
      }
    } // end $listingTotalRow
    ?>
    </tbody>
  </table>
  </form>
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qcustomers->getPageSetLabel($CLICSHOPPING_Gdpr->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qcustomers->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
  <!-- body_eof //-->
</div>