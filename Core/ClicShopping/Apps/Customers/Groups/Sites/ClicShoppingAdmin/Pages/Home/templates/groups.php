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

$CLICSHOPPING_Groups = Registry::get('Groups');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

// Permettre l'utilisation de des groupes clients
if (MODE_B2B_B2C == 'False') CLICSHOPPING::redirect();

if (isset($_GET['search'])) {
  $_POST['search'] = HTML::sanitize($_GET['search']);
}

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/group_client.gif', $CLICSHOPPING_Groups->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-7 pageHeading"
                width="60%"><?php echo '&nbsp;' . $CLICSHOPPING_Groups->getDef('heading_title'); ?></span>
          <span class="col-md-2 text-end">
            <div>
              <div>
<?php
echo HTML::form('search', $CLICSHOPPING_Groups->link('Groups'), 'post', 'role="form" ', ['session_id' => true]);
echo HTML::inputField('search', '', 'id="inputKeywords" placeholder="' . $CLICSHOPPING_Groups->getDef('heading_title_search') . '"');
?>
                </form>
              </div>
            </div>
          </span>
          <span class="col-md-2 text-end">
            <span class="col-md-6">
              <?php echo HTML::button($CLICSHOPPING_Groups->getDef('button_insert'), null, $CLICSHOPPING_Groups->link('Insert'), 'success'); ?>
            </span>
<?php
if (!isset($_GET['search'])) {
  ?>
  <span
    class="col-md-6"> <?php echo HTML::button($CLICSHOPPING_Groups->getDef('button_update'), null, $CLICSHOPPING_Groups->link('Groups'), 'warning'); ?></span>
  <?php
}
?>
              </span>
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
    data-sort-order="asc"
    data-sort-name="name"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="name" data-sortable="true"><?php echo $CLICSHOPPING_Groups->getDef('table_heading_name'); ?></th>
      <th data-field="color" class="text-center"><?php echo $CLICSHOPPING_Groups->getDef('table_heading_color'); ?></th>
      <th data-field="quantity"><?php echo $CLICSHOPPING_Groups->getDef('table_heading_quantity_default'); ?></th>
      <?php
      if (B2B == 'true') {
        ?>
        <th data-field="discount_b2b"><?php echo $CLICSHOPPING_Groups->getDef('table_heading_discount_b2b'); ?></th>
        <?php
      } else {
        ?>
        <th data-field="discount"><?php echo $CLICSHOPPING_Groups->getDef('table_heading_discount'); ?></th>
        <?php
      }
      ?>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Groups->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <?php
    $search = '';

    if (isset($_POST['search'])) {
      $keywords = HTML::sanitize($_POST['search']);

      $QustomersGroup = $CLICSHOPPING_Groups->db->prepare('select  SQL_CALC_FOUND_ROWS *
                                                       from :table_customers_groups
                                                       where customers_group_name like :search
                                                       limit :page_set_offset,
                                                            :page_set_max_results
                                                      ');
      $QustomersGroup->bindvalue(':search', '%' . $keywords . '%');
      $QustomersGroup->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $QustomersGroup->execute();

    } else {
      $QustomersGroup = $CLICSHOPPING_Groups->db->prepare('select  SQL_CALC_FOUND_ROWS *
                                                       from :table_customers_groups
                                                       limit :page_set_offset,
                                                            :page_set_max_results
                                                      ');
      $QustomersGroup->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $QustomersGroup->execute();
    }

    $listingTotalRow = $QustomersGroup->getPageSetTotalRows();

    if ($listingTotalRow > 0) {

      while ($customers_group = $QustomersGroup->fetch()) {
        if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ((int)$_GET['cID'] == $QustomersGroup->valueInt('customers_group_id')))) && !isset($cInfo)) {
          $cInfo = new ObjectInfo($QustomersGroup->toArray());
        }
        ?>
        <tr>
          <td scope="row"><?php echo $QustomersGroup->value('customers_group_name'); ?></td>
          <td class="text-center">
            <table cellspacing="0" cellpadding="0" border="0" width="30px">
              <td class="text-center" bgcolor="<?php echo $QustomersGroup->value('color_bar'); ?>">&nbsp;</td>
            </table>
          </td>
          <td
            class="text-start"><?php echo $QustomersGroup->valueInt('customers_group_quantity_default'); ?></td>
          <td class="text-start"><?php echo $QustomersGroup->value('customers_group_discount'); ?>%</td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_Groups->link('Edit&page=' . $page . '&cID=' . $QustomersGroup->valueInt('customers_group_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Groups->getDef('icon_edit') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Groups->link('Groups&UpdateAllPrice&page=' . $page . '&cID=' . $QustomersGroup->valueInt('customers_group_id')), '<h4><i class="bi bi-arrow-clockwise" title="' . $CLICSHOPPING_Groups->getDef('icon_update') . '"></i></h4>');
              echo '&nbsp;';

              if ($QustomersGroup->valueInt('customers_group_id') > 1) {
                echo HTML::link($CLICSHOPPING_Groups->link('Groups&Delete&cID=' . $QustomersGroup->valueInt('customers_group_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Groups->getDef('icon_delete') . '"></i></h4>');
                echo '&nbsp;';
              }
              ?>
            </div>
          </td>
        </tr>
        <?php
      }
    } // end $listingTotalRow
    ?>
    </tr>
    </tbody>
  </table>
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $QustomersGroup->getPageSetLabel($CLICSHOPPING_Groups->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $QustomersGroup->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>

