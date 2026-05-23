<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Image = Registry::get('Image');
$CLICSHOPPING_StatsProductsNotification = Registry::get('StatsProductsNotification');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

// show customers for a product
if (isset($_GET['show_customers']) && (int)$_GET['pID']) {
$products_id = HTML::sanitize($_GET['pID']);
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading""><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/client.gif', $CLICSHOPPING_StatsProductsNotification->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_StatsProductsNotification->getDef('heading_title'); ?></span>
          <span
            class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_StatsProductsNotification->getDef('button_back'), null, $CLICSHOPPING_StatsProductsNotification->link('StatsProductsNotification'), 'primary'); ?></span>
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
    data-sort-name="number"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-show-export="true"
    data-search="true">

  <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="number"
          data-sortable="true"><?php echo $CLICSHOPPING_StatsProductsNotification->getDef('table_heading_number'); ?></th>
      <th data-field="name"
          data-sortable="true"><?php echo $CLICSHOPPING_StatsProductsNotification->getDef('table_heading_name'); ?></th>
      <th data-field="email"><?php echo $CLICSHOPPING_StatsProductsNotification->getDef('table_heading_email'); ?></th>
      <th data-field="date"
          data-sortable="true"><?php echo $CLICSHOPPING_StatsProductsNotification->getDef('table_heading_date'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_StatsProductsNotification->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qcustomers = $CLICSHOPPING_StatsProductsNotification->dbprepare('select  SQL_CALC_FOUND_ROWS c.customers_firstname,
                                                                                                 c.customers_lastname,
                                                                                                 c.customers_email_address,
                                                                                                 pn.date_added
                                                                    from :table_customers c,
                                                                         :table_products_notifications pn
                                                                    where c.customers_id = pn.customers_id
                                                                    and pn.products_id = :products_id
                                                                    order by c.customers_firstname,
                                                                             c.customers_lastname
                                                                    limit :page_set_offset,
                                                                          :page_set_max_results
                                                                    ');
    $Qcustomers->bindInt(':products_id', (int)$products_id);
    $Qcustomers->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qcustomers->execute();

    $listingTotalRow = $Qcustomers->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
    $rows = 0;
    while ($customers = $Qcustomers->fetch()) {
      $rows++;

      if (\strlen($rows) < 2) {
        $rows = '0' . $rows;
      }
      ?>
      <tr>
        <td width="30" nowrap class="dataTableContent"><?php echo $rows; ?>.</td>
        <td
          class="dataTableContent"><?php echo HTML::link(CLICSHOPPING::link(null, 'A&Customers\Customers&Customers&search=' . $Qcustomers->value('customers_lastname')), $Qcustomers->value('customers_firstname') . ' ' . $Qcustomers->value('customers_lastname')); ?></td>
        <td
          class="dataTableContent"><?php echo HTML::link(CLICSHOPPING::link(null, 'A&Communication\EMail&EMail&customer=' . $Qcustomers->value('customers_email_address')), $Qcustomers->value('customers_email_address')) ?></td>
        <td class="dataTableContent"><?php echo DateTime::toLong($Qcustomers->value('date_added')); ?>&nbsp;
        </td>
        <td class="dataTableContent text-end">
          <?php
          echo HTML::link(CLICSHOPPING::link(null, 'A&Customers\Customers&Customers&search=' . $Qcustomers->value('customers_lastname')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_StatsProductsNotification->getDef('icon_edit_customer') . '"></i></h4>');
          echo '&nbsp;';
          echo HTML::link(CLICSHOPPING::link(null, 'A&Communication\EMail&EMail&customer=' . $Qcustomers->value('customers_email_address')), '<h4><i class="bi bi-send" title="' . $CLICSHOPPING_StatsProductsNotification->getDef('icon_email') . '"></i></h4>');
          ?>
        </td>
      </tr>
      <?php
    }
    ?>
    </tbody>
  </table>
  <div class="row">
    <div class="col-md-12">
      <div
        class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qcustomers->getPageSetLabel($CLICSHOPPING_StatsProductsNotification->getDef('text_display_number_of_link')); ?></div>
      <div
        class="float-end text-end"><?php echo $Qcustomers->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
    </div>
  </div>
  <?php
  } // end $listingTotalRow
  } else {
  if (isset($page) && ($page > 1)) $rows = $page * (int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN - (int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN;
  ?>
  <!-- body //-->
  <div class="contentBody">
    <div class="row">
      <div class="col-md-12">
        <div class="card card-block headerCard">
          <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie_produit.gif', $CLICSHOPPING_StatsProductsNotification->getDef('heading_title'), '40', '40'); ?></span>
            <span class="col-md-3 pageHeading"
                  width="250"><?php echo '&nbsp;' . $CLICSHOPPING_StatsProductsNotification->getDef('heading_title'); ?></span>
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
      data-sort-name="number"
      data-sort-order="asc"
      data-toolbar="#toolbar"
      data-buttons-class="primary"
      data-show-toggle="true"
      data-show-columns="true"
      data-mobile-responsive="true"
      data-check-on-init="true"
      data-show-export="true">

      <thead class="dataTableHeadingRow">
      <tr>
        <th data-switchable="false" width="20"></th>
        <th data-switchable="false" width="50"></th>
        <th data-field="number"
            data-sortable="true"><?php echo $CLICSHOPPING_StatsProductsNotification->getDef('table_heading_number'); ?></th>
        <th data-field="products"
            data-sortable="true"><?php echo $CLICSHOPPING_StatsProductsNotification->getDef('table_heading_products'); ?></th>
        <th data-field="model" data-sortable="true"
            class="text-center"><?php echo $CLICSHOPPING_StatsProductsNotification->getDef('table_heading_model'); ?></th>
        <th data-field="count" data-sortable="true"
            class="text-center"><?php echo $CLICSHOPPING_StatsProductsNotification->getDef('table_heading_count'); ?></th>
        <th data-field="action" data-switchable="false"
            class="text-end"><?php echo $CLICSHOPPING_StatsProductsNotification->getDef('table_heading_action'); ?></th>
      </tr>
      </thead>
      <tbody>
      <?php

      $Qproducts = $CLICSHOPPING_StatsProductsNotification->db->prepare('select  SQL_CALC_FOUND_ROWS count(pn.products_id) as count_notifications,
                                                                                                        pn.products_id,
                                                                                                        pd.products_name,
                                                                                                        p.products_image,
                                                                                                        p.products_model
                                                                           from :table_products_notifications pn,
                                                                                :table_products_description pd,
                                                                                :table_products p,
                                                                                :table_customers c
                                                                           where pn.products_id = pd.products_id
                                                                           and pd.language_id = :language_id
                                                                           and pn.customers_id = c.customers_id
                                                                           and pn.products_id = p.products_id
                                                                           group by pn.products_id order by count_notifications desc,
                                                                                    pn.products_id
                                                                          limit :page_set_offset,
                                                                                :page_set_max_results
                                                                          ');

      $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qproducts->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qproducts->execute();

      $listingTotalRow = $Qproducts->getPageSetTotalRows();

      $rows = 0;

      if ($listingTotalRow > 0) {
        while ($products = $Qproducts->fetch()) {
          $rows++;

          if (\strlen($rows) < 2) {
            $rows = '0' . $rows;
          }
          ?>
          <tr>
            <td scope="row"
                width="50px"><?php echo HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Preview&pID=' . $Qproducts->valueInt('products_id') . '?page=' . $page), '<h4><i class="bi bi-easil3" title="' . $CLICSHOPPING_StatsProductsNotification->getDef('icon_preview') . '"></i></h4>'); ?></td>
            <td><?php echo $CLICSHOPPING_Image->getSmallImageAdmin($Qproducts->valueInt('products_id'));; ?></td>
            <td><?php echo $rows; ?>.</td>
            <td><?php echo HTML::link(CLICSHOPPING::link('StatsProductsNotification&show_customers&pID=' . $products['products_id'] . '&page=' . $page), $Qproducts->value('products_name')); ?></td>
            <td
              class="text-center"><?php echo HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Edit&pID=' . $Qproducts->valueInt('products_id')), $Qproducts->value('products_model')); ?></td>
            <td class="text-center"><?php echo $Qproducts->valueInt('count_notifications'); ?>&nbsp;</td>
            <td class=text-end">
              <?php
              echo HTML::link(CLICSHOPPING::link('StatsProductsNotification&show_customers&pID=' . $Qproducts->valueInt('products_id') . '&page=' . $page), '<h4><i class="bi bi-person" title="' . $CLICSHOPPING_StatsProductsNotification->getDef('icon_edit_customer') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Edit&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_StatsProductsNotification->getDef('icon_edit') . '"></i></h4>');
              ?>
          </tr>
          <?php
        } // end $listingTotalRow
      }
      ?>
      </tbody>
    </table>

    <?php
    if ($listingTotalRow > 0) {
      ?>
      <div class="row">
        <div class="col-md-12">
          <div
            class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qproducts->getPageSetLabel($CLICSHOPPING_StatsProductsNotification->getDef('text_display_number_of_link')); ?></div>
          <div
            class="float-end text-end"><?php echo $Qproducts->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
        </div>
      </div>
      <?php
    } // end $listingTotalRow
    } // end else
    ?>
  </div>


