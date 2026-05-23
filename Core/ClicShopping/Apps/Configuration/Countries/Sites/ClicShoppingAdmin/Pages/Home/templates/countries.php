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

$CLICSHOPPING_Countries = Registry::get('Countries');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/countries.gif', $CLICSHOPPING_Countries->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Countries->getDef('heading_title'); ?></span>
          <span
            class="col-md-7 text-end"><?php echo HTML::button($CLICSHOPPING_Countries->getDef('button_insert'), null, $CLICSHOPPING_Countries->link('Insert&page=' . $page), 'success'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING                                                                      -->
  <!-- //################################################################################################################ -->
  <?php echo HTML::form('update_all', $CLICSHOPPING_Countries->link('Countries&UpdateAll&page=' . $page)); ?>

  <div id="toolbar" class="float-end">
    <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Countries->getDef('button_delete'); ?></button>
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
    data-sort-name="name"
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
          data-switchable="false"><?php echo $CLICSHOPPING_Countries->getDef('id'); ?></th>
      <th data-field="name"
          data-sortable="true"><?php echo $CLICSHOPPING_Countries->getDef('table_heading_country_name'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Countries->getDef('table_heading_country_status'); ?></th>
      <th data-field="code2" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Countries->getDef('table_heading_country_code2'); ?></th>
      <th data-field="code3" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Countries->getDef('table_heading_country_code3'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Countries->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qcountries = $CLICSHOPPING_Countries->db->prepare('select SQL_CALC_FOUND_ROWS countries_id,
                                                                                     countries_name,
                                                                                     countries_iso_code_2,
                                                                                     countries_iso_code_3,
                                                                                     status,
                                                                                     address_format_id
                                                        from :table_countries
                                                        order by countries_name
                                                        limit :page_set_offset, :page_set_max_results
                                                        ');

    $Qcountries->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qcountries->execute();

    $listingTotalRow = $Qcountries->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qcountries->fetch()) {
        if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ((int)$_GET['cID'] == $Qcountries->valueInt('countries_id')))) && !isset($cInfo)) {
          $cInfo = new ObjectInfo($Qcountries->toArray());
        }
        ?>
        <tr>
          <td></td>
          <td><?php echo $Qcountries->valueInt('countries_id'); ?></td>
          <td><?php echo $Qcountries->value('countries_name'); ?></td>
          <td class="text-center">
            <?php
            if ($Qcountries->valueInt('status') == 1) {
              echo HTML::link($CLICSHOPPING_Countries->link('Countries&SetFlag&flag=0&cID=' . $Qcountries->valueInt('countries_id') . '&page=' . $page), '<i class="bi-check text-success"></i>');
            } else {
              echo HTML::link($CLICSHOPPING_Countries->link('Countries&SetFlag&flag=1&cID=' . $Qcountries->valueInt('countries_id') . '&page=' . $page), '<i class="bi bi-x text-danger"></i>');
            }
            ?>
          </td>
          <td class="text-center" width="40"><?php echo $Qcountries->value('countries_iso_code_2'); ?></td>
          <td class="text-center" width="40"><?php echo $Qcountries->value('countries_iso_code_3'); ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_Countries->link('Edit&page=' . $page . '&cID=' . $Qcountries->valueInt('countries_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Countries->getDef('icon_edit') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Countries->link('Delete&page=' . $page . '&cID=' . $Qcountries->valueInt('countries_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Countries->getDef('icon_delete') . '"></i></h4>');
              ?>
            </div>
          </td>
        </tr>
        <?php
      } // end while
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qcountries->getPageSetLabel($CLICSHOPPING_Countries->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qcountries->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>
<div class="py-4"></div>