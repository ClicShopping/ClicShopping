<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_SecurityCheck = Registry::get('SecurityCheck');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Language = Registry::get('Language');

$CLICSHOPPING_Language->loadDefinitions('security_check', null, null, 'Shop');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/cybermarketing.gif', $CLICSHOPPING_SecurityCheck->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-4 pageHeading"><?php echo $CLICSHOPPING_SecurityCheck->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
             <?php echo HTML::button($CLICSHOPPING_SecurityCheck->getDef('button_new'), null, $CLICSHOPPING_SecurityCheck->link('EditIpRestriction'), 'success'); ?>
           </span>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING DES COUPS DE COEUR                                             -->
  <!-- //################################################################################################################ -->
  <?php
  echo HTML::form('delete_all', $CLICSHOPPING_SecurityCheck->link('IpRestriction&DeleteAll&page=' . $page));
  ?>

  <div id="toolbar" class="float-end">
    <button id="button"
            class="btn btn-danger"><?php echo $CLICSHOPPING_SecurityCheck->getDef('button_delete'); ?></button>
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
    data-sort-name="selected"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected" data-sortable="true" data-visible="false"
          data-switchable="false"><?php echo $CLICSHOPPING_SecurityCheck->getDef('id'); ?></th>
      <th data-field="ip"
          data-sortable="true"><?php echo $CLICSHOPPING_SecurityCheck->getDef('table_heading_ip'); ?></th>
      <th data-field="comment" data-switchable="false"
      "><?php echo $CLICSHOPPING_SecurityCheck->getDef('table_heading_comment'); ?></th>
      <th data-field="status_shop" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_SecurityCheck->getDef('table_heading_status_shop'); ?></th>
      <th data-field="status_admin" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_SecurityCheck->getDef('table_heading_status_admin'); ?></th>
      <th data-field="count" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_SecurityCheck->getDef('table_heading_count'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_SecurityCheck->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $QIpRestriction = $CLICSHOPPING_SecurityCheck->db->prepare('select SQL_CALC_FOUND_ROWS id,
                                                                                               ip_restriction,
                                                                                               ip_comment,
                                                                                               ip_status_shop,
                                                                                               ip_status_admin
                                                                    from :table_ip_restriction
                                                                    limit :page_set_offset,
                                                                          :page_set_max_results
                                                                    ');

    $QIpRestriction->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $QIpRestriction->execute();

    $listingTotalRow = $QIpRestriction->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($QIpRestriction->fetch()) {
        $Qcount = $CLICSHOPPING_SecurityCheck->db->prepare('select count(*) as number_of_ip
                                                                from :table_ip_restriction_stats 
                                                                where ip_remote = :ip_restriction
                                                               ');

        $Qcount->bindvalue(':ip_restriction', $QIpRestriction->value('ip_restriction'));
        $Qcount->execute();
        ?>
        <tr>
          <td></td>
          <th><?php echo $QIpRestriction->valueInt('id'); ?></th>
          <th><?php echo $QIpRestriction->value('ip_restriction'); ?></th>
          <td><?php echo $QIpRestriction->value('ip_comment'); ?></td>
          <td>
            <?php
            if ($QIpRestriction->valueInt('ip_status_shop') == 1) {
              echo HTML::link($CLICSHOPPING_SecurityCheck->link('IpRestriction&SetFlagShop&flag=0&cID=' . $QIpRestriction->valueInt('id')), '<i class="bi-check text-success"></i>');
            } else {
              echo HTML::link($CLICSHOPPING_SecurityCheck->link('IpRestriction&SetFlagShop&flag=1&cID=' . $QIpRestriction->valueInt('id')), '<i class="bi bi-x text-danger"></i>');
            }

            ?>
          </td>
          <td>
            <?php

            if ($QIpRestriction->valueInt('ip_status_admin') == 1) {
              echo HTML::link($CLICSHOPPING_SecurityCheck->link('IpRestriction&SetFlagAdmin&flag=0&cID=' . $QIpRestriction->valueInt('id')), '<i class="bi-check text-success"></i>');
            } else {
              echo HTML::link($CLICSHOPPING_SecurityCheck->link('IpRestriction&SetFlagAdmin&flag=1&cID=' . $QIpRestriction->valueInt('id')), '<i class="bi bi-x text-danger"></i>');
            }

            ?>
          </td>
          <td><?php echo $Qcount->valueInt('number_of_ip'); ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo '<a href="' . $CLICSHOPPING_SecurityCheck->link('EditIpRestriction&&Edit&cID=' . $QIpRestriction->valueInt('id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_SecurityCheck->getDef('icon_edit') . '"></i></h4></a>';
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $QIpRestriction->getPageSetLabel($CLICSHOPPING_SecurityCheck->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $QIpRestriction->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>