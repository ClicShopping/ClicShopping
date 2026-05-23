<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_PageManager = Registry::get('PageManager');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$languages = $CLICSHOPPING_Language->getLanguages();
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/page_manager.gif', $CLICSHOPPING_PageManager->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_PageManager->getDef('heading_title'); ?></span>
          <span
            class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_PageManager->getDef('button_new'), null, $CLICSHOPPING_PageManager->link('SelectPage'), 'success'); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING DES produits                                      -->
  <!-- //################################################################################################################ -->
  <?php
  echo HTML::form('delete_all', $CLICSHOPPING_PageManager->link('PageManager&DeleteAll&page=' . $page));
  ?>

  <div id="toolbar" class="float-end">
    <button id="button"
            class="btn btn-danger"><?php echo $CLICSHOPPING_PageManager->getDef('button_delete'); ?></button>
  </div>

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-id-field="selected"
    data-select-item-name="selected[]"
    data-click-to-select="true"
    data-sort-order="asc"
    data-sort-name="type_page"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-search="true">

  <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected" data-sortable="true" data-visible="false"
          data-switchable="false"><?php echo $CLICSHOPPING_PageManager->getDef('id'); ?></th>
      <th data-field="id" data-sortable="true" data-switchable="false"><?php echo 'Id'; ?></th>
      <th data-switchable="false"></th>
      <th data-switchable="false"></th>
      <th data-field="page"><?php echo $CLICSHOPPING_PageManager->getDef('table_heading_pages'); ?></th>
      <th data-field="type_page"
          data-sortable="true"><?php echo $CLICSHOPPING_PageManager->getDef('table_heading_type_page'); ?></th>
      <?php
      if (MODE_B2B_B2C == 'True') {
        ?>
        <th data-field="group" data-sortable="group"
            class="text-center"><?php echo $CLICSHOPPING_PageManager->getDef('table_heading_customers_group'); ?></th>
        <?php
      }
      ?>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_PageManager->getDef('table_heading_status'); ?></th>
      <th data-field="target"
          class="text-center"><?php echo $CLICSHOPPING_PageManager->getDef('table_heading_links_target'); ?></th>
      <th data-field="page_type" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_PageManager->getDef('table_heading_page_type'); ?></th>
      <th data-field="sort_order" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_PageManager->getDef('table_heading_sort_order'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_PageManager->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qpages = $CLICSHOPPING_PageManager->db->prepare('select SQL_CALC_FOUND_ROWS  p.pages_id,
                                                                               p.links_target,
                                                                               p.page_type,
                                                                               p.page_box,
                                                                               p.status,
                                                                               p.sort_order,
                                                                               p.date_added,
                                                                               p.page_date_start,
                                                                               p.page_date_closed,
                                                                               p.last_modified,
                                                                               p.date_status_change,
                                                                               s.pages_title,
                                                                               s.externallink,
                                                                               p.customers_group_id,
                                                                               p.page_general_condition
                                                  from :table_pages_manager p,
                                                       :table_pages_manager_description s
                                                  where s.language_id= :language_id
                                                  and p.pages_id = s.pages_id
                                                  order by p.sort_order, 
                                                           p.page_type
                                                  limit :page_set_offset,
                                                        :page_set_max_results
                                                  ');

    $Qpages->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $Qpages->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qpages->execute();

    $listingTotalRow = $Qpages->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qpages->fetch()) {
        if (MODE_B2B_B2C == 'True') {
          $QcustomersGroup = $CLICSHOPPING_PageManager->db->prepare('select customers_group_name
                                                                  from :table_customers_groups
                                                                  where customers_group_id = :customers_group_id
                                                                ');
          $QcustomersGroup->bindInt(':customers_group_id', $Qpages->valueInt('customers_group_id'));
          $QcustomersGroup->execute();

          $customers_group = $QcustomersGroup->fetch();
          
          // Initialize as array if fetch returned false
          if ($customers_group === false) {
            $customers_group = ['customers_group_name' => ''];
          }

          if ($Qpages->valueInt('customers_group_id') == 99) {
            $customers_group['customers_group_name'] = $CLICSHOPPING_PageManager->getDef('text_all_groups');
          } elseif ($Qpages->valueInt('customers_group_id') == 0) {
            $customers_group['customers_group_name'] = $CLICSHOPPING_PageManager->getDef('normal_customer');
          }
}

        if (!isset($_GET['bID']) || ((isset($_GET['bID'])) && (int)$_GET['bID'] === $Qpages->valueInt('pages_id') && !isset($bInfo))) {
          $bInfo = new ObjectInfo($Qpages->toArray());
        }
        ?>
        <tr>
          <td></td>
          <td><?php echo $Qpages->valueInt('pages_id'); ?></td>
          <td><?php echo $Qpages->valueInt('pages_id'); ?></td>
          <td></td>

          <?php
          if (!empty($Qpages->valueInt('pages_id')) && $Qpages->valueInt('page_type') == 4 && empty($Qpages->value('externallink'))) {
            ?>
            <td
              class="text-center"><?php echo '<a href="' . HTTP::getShopUrlDomain() . 'index.php?Info&Content&' . 'pagesId=' . $Qpages->valueInt('pages_id') . '" target="_blank" rel="noreferrer"><h4><i class="bi bi-easil3" title="' . $CLICSHOPPING_PageManager->getDef('icon_preview') . '"></i></h4></a>'; ?></td>
            <?php
          } else {
            ?>
            <td></td>
            <?php
          }
          ?>
          <td><?php echo $Qpages->value('pages_title'); ?></td>
          <?php
          if ($Qpages->valueInt('page_type') == 1) {
            $page_function = $CLICSHOPPING_PageManager->getDef('page_manager_introduction_page');
          } elseif ($Qpages->valueInt('page_type') == 2) {
            $page_function = $CLICSHOPPING_PageManager->getDef('page_manager_main_page');
          } elseif ($Qpages->valueInt('page_type') == 3) {
            $page_function = $CLICSHOPPING_PageManager->getDef('page_manager_contact_us');
          } elseif ($Qpages->valueInt('page_type') == 5) {
            $page_function = $CLICSHOPPING_PageManager->getDef('page_manager_menu_header');
          } elseif ($Qpages->valueInt('page_type') == 6) {
            $page_function = $CLICSHOPPING_PageManager->getDef('page_manager_menu_footer');
          } else {
            $page_function = $CLICSHOPPING_PageManager->getDef('page_manager_informations');
          }
          ?>
          <td><?php echo $page_function; ?></td>
          <?php
          if (MODE_B2B_B2C == 'True') {
            ?>
            <td><?php echo $customers_group['customers_group_name']; ?></td>
            <?php
          }
          ?>
          <td class="text-center">
            <?php
            if ($Qpages->valueInt('status') == 1) {
              echo '<a href="' . $CLICSHOPPING_PageManager->link('PageManager&SetFlag&flag=0&id=' . $Qpages->valueInt('pages_id')) . '"><i class="bi-check text-success"></i></a>';
            } else {
              echo '<a href="' . $CLICSHOPPING_PageManager->link('PageManager&SetFlag&flag=1&id=' . $Qpages->valueInt('pages_id')) . '"><i class="bi bi-x text-danger"></i></a>';
            }
            ?>
          </td>
          <?php
          if ($Qpages->value('links_target') == '_self') {
            $links_target = $CLICSHOPPING_PageManager->getDef('text_link_same_windows');
          } elseif ($Qpages->value('links_target') == '_blank') {
            $links_target = $CLICSHOPPING_PageManager->getDef('text_link_new_windows');
          } else {
            $links_target = '';
          }
          ?>
          <td><?php echo $links_target; ?></td>
          <?php
          if ($Qpages->valueInt('page_box') == 0) {
            $page_box = $CLICSHOPPING_PageManager->getDef('page_manager_main_box');
          } elseif ($Qpages->valueInt('page_box') == 1) {
            $page_box = $CLICSHOPPING_PageManager->getDef('page_manager_secondary_box');
          } elseif ($Qpages->valueInt('page_box') == 2) {
            $page_box = $CLICSHOPPING_PageManager->getDef('page_manager_landing_page');
          } else {
            $page_box = $CLICSHOPPING_PageManager->getDef('page_manager_none');
          }
          ?>
          <td><?php echo $page_box; ?></td>
          <td class="text-center"><?php echo $Qpages->valueInt('sort_order'); ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo '<a href="' . $CLICSHOPPING_PageManager->link('Edit&bID=' . $Qpages->valueInt('pages_id') . '&page=' . $page) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_PageManager->getDef('icon_edit') . '"></i></h4></a>';
              echo '&nbsp;';

              if ($Qpages->valueInt('pages_id') === 3 || $Qpages->valueInt('pages_id') === 4 || $Qpages->valueInt('pages_id' === 5)) {
                echo '&nbsp;';
              } else {
                echo '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_PageManager->getDef('icon_delete') . '"></i></h4>';
              }
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
  </form><!-- end form delete all -->

  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qpages->getPageSetLabel($CLICSHOPPING_PageManager->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qpages->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
