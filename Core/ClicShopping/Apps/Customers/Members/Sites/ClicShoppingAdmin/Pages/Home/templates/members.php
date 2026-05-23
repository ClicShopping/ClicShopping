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
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Members = Registry::get('Members');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

// Permettre l'utilisation de l'approbation des comptes en mode B2B
if (\defined('B2C') && B2C == 'true') {
  CLICSHOPPING::redirect();
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/client_attente.gif', $CLICSHOPPING_Members->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-6 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Members->getDef('heading_title'); ?></span>
          <?php
          if (MEMBER == 'true') {
          ?>
          <span class="col-md-2">
            <div>
              <div>
                  <?php
                  echo HTML::form('search', $CLICSHOPPING_Members->link('Members'), 'post', '', ['session_id' => true]);
                  echo HTML::inputField('search', '', 'id="inputKeywords" placeholder="' . $CLICSHOPPING_Members->getDef('heading_title_search') . '"');
                  ?>
                </form>
              </div>
            </div>
          </span>
          <?php
          if (isset($_POST['search'])) {
            ?>
            <span
              class="col-md-3 text-end"><?php echo HTML::button($CLICSHOPPING_Members->getDef('button_reset'), null, $CLICSHOPPING_Members->link('Members'), 'warning'); ?></span>
            <?php
          }
          ?>

        </div>
      </div>
    </div>
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
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="lastname"><?php echo $CLICSHOPPING_Members->getDef('table_heading_lastname'); ?></th>
      <th data-field="firstname"><?php echo $CLICSHOPPING_Members->getDef('table_heading_firstname'); ?></th>
      <th data-field="company"><?php echo $CLICSHOPPING_Members->getDef('table_heading_company'); ?></th>
      <th data-field="account_created"
          class="text-end"><?php echo $CLICSHOPPING_Members->getDef('table_heading_account_created'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Members->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $search = '';

    if (isset($_POST['search'])) {
      $keywords = HTML::sanitize($_POST['search']);

      $Qcustomers = $CLICSHOPPING_Members->db->prepare('select  SQL_CALC_FOUND_ROWS  c.customers_id,
                                                                                       c.customers_lastname,
                                                                                       c.customers_firstname,
                                                                                       a.entry_company,
                                                                                       c.customers_email_address,
                                                                                       a.entry_country_id,
                                                                                       c.customers_group_id
                                                        from :table_customers c left join :table_address_book a on c.customers_id = a.customers_id
                                                        where (c.customers_lastname like :keywords
                                                                or c.customers_firstname like :keywords
                                                                or c.customers_email_address like :keywords
                                                              )
                                                        and member_level = 0
                                                        and c.customers_default_address_id = a.address_book_id
                                                        order by c.customers_lastname,
                                                                 c.customers_firstname
                                                        limit :page_set_offset,
                                                              :page_set_max_results
                                                        ');

      $Qcustomers->bindvalue(':keywords', '%' . $keywords . '%');
      $Qcustomers->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qcustomers->execute();
    } else {
      $Qcustomers = $CLICSHOPPING_Members->db->prepare('select  SQL_CALC_FOUND_ROWS  c.customers_id,
                                                                                     c.customers_lastname,
                                                                                     c.customers_firstname,
                                                                                     a.entry_company,
                                                                                     c.customers_email_address,
                                                                                     a.entry_country_id,
                                                                                     c.customers_group_id
                                                      from :table_customers c left join :table_address_book a on c.customers_id = a.customers_id
                                                      where member_level = 0
                                                      and c.customers_default_address_id = a.address_book_id
                                                      order by c.customers_lastname,
                                                               c.customers_firstname
                                                      limit :page_set_offset,
                                                            :page_set_max_results
                                                      ');

      $Qcustomers->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qcustomers->execute();
    }

    $listingTotalRow = $Qcustomers->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qcustomers->fetch()) {
        $Qinfo = $CLICSHOPPING_Members->db->prepare('select customers_info_date_account_created as date_account_created,
                                                             customers_info_date_account_last_modified as date_account_last_modified,
                                                             customers_info_date_of_last_logon as date_last_logon,
                                                             customers_info_number_of_logons as number_of_logons
                                                       from :table_customers_info
                                                       where customers_info_id = :customers_info_id
                                                       ');
        $Qinfo->bindInt(':customers_info_id', $Qcustomers->valueInt('customers_id'));
        $Qinfo->execute();

        if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ((int)$_GET['cID'] === $Qcustomers->valueInt('customers_id')))) && !isset($lInfo)) {
          $lInfo = new ObjectInfo($Qinfo->toArray());

          $Qcountry = $CLICSHOPPING_Members->db->prepare('select countries_name
                                                             from :table_countries
                                                             where countries_id = :countries_id
                                                            ');
          $Qcountry->bindInt(':countries_id', $Qcustomers->valueInt('entry_country_id'));
          $Qcountry->execute();

          $Qreviews = $CLICSHOPPING_Members->db->prepare('select count(*) as number_of_reviews
                                                             from :table_reviews
                                                             where customers_id = :customers_id
                                                            ');
          $Qreviews->bindInt(':customers_id', $Qcustomers->valueInt('customers_id'));
          $Qreviews->execute();

          $customer_info = array_merge($Qcountry->toArray(), $Qinfo->toArray(), $Qreviews->toArray());

          $cInfo_array = array_merge($Qcustomers->toArray(), (array)$customer_info);
          $cInfo = new ObjectInfo($cInfo_array);
        }
        ?>
        <tr>
          <td scope="row"><?php echo Hash::displayDecryptedDataText($Qcustomers->value('customers_lastname')); ?></td>
          <td><?php echo Hash::displayDecryptedDataText($Qcustomers->value('customers_firstname')); ?></td>
          <td><?php echo Hash::displayDecryptedDataText($Qcustomers->value('entry_company')); ?></td>
          <td class="text-end"><?php echo DateTime::toShort($Qinfo->value('date_account_created')); ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_Members->link('AcceptMembers&cID=' . $Qcustomers->valueInt('customers_id')), '<h4><i class="bi bi-bag-plus" title="' . $CLICSHOPPING_Members->getDef('icon_activate') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link(CLICSHOPPING::link(null, 'A&Customers\Customers&Edit&cID=' . $Qcustomers->valueInt('customers_id')), '<h4><i class="bi bi-person" title="' . $CLICSHOPPING_Members->getDef('icon_edit_customer') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link(CLICSHOPPING::link(null, 'A&Communication\EMail&EMail&customer=' . Hash::displayDecryptedDataText($Qcustomers->value('customers_email_address'))), '<h4><i class="bi bi-send" title="' . $CLICSHOPPING_Members->getDef('icon_email') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Members->link('Delete&cID=' . $Qcustomers->valueInt('customers_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Members->getDef('icon_delete') . '"></i></h4>');
              echo '&nbsp;';
              ?>
            </div>
          </td>
        </tr>
        <?php
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qcustomers->getPageSetLabel($CLICSHOPPING_Members->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qcustomers->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  } else {

    ?>
    <div class="alert alert-warning" role="alert">
      <?php echo $CLICSHOPPING_Members->getDef('member_desactivated'); ?>
    </div>
    <?php
  }
  ?>
  <!-- body_eof //-->
</div>