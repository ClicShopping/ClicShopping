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

$CLICSHOPPING_Zones = Registry::get('Zones');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/zones.gif', $CLICSHOPPING_Zones->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-3 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Zones->getDef('heading_title'); ?></span>
          <span class="col-md-3">
           <div>
              <?php
              echo HTML::form('search', $CLICSHOPPING_Zones->link('Zones'), 'post', null, ['session_id' => true]);
              echo HTML::inputField('search', '', 'id="inputKeywords" placeholder="' . $CLICSHOPPING_Zones->getDef('heading_title_search') . '"');

              if (isset($_POST['search'])) {
                echo HTML::button($CLICSHOPPING_Zones->getDef('button_reset'), null, $CLICSHOPPING_Zones->link('Zones'), 'warning') . '&nbsp;';
                $search = HTML::sanitize($_POST['search']);
              } elseif (isset($_GETT['search'])) {
                echo HTML::button($CLICSHOPPING_Zones->getDef('button_reset'), null, $CLICSHOPPING_Zones->link('Zones'), 'warning') . '&nbsp;';
                $search = HTML::sanitize($_GET['search']);
              } else {
                $search = '';
              }
              ?>
             </form>
            </div>
          </span>
          <span class="col-md-5 text-end">
            <?php echo HTML::button($CLICSHOPPING_Zones->getDef('button_new'), null, $CLICSHOPPING_Zones->link('Insert&page=' . $page), 'success'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING DES produits                                      -->
  <!-- //################################################################################################################ -->
  <?php echo HTML::form('flag_all', $CLICSHOPPING_Zones->link('Zones&AllFlag', 'page=' . $page)); ?>

  <div id="toolbar" class="float-end">
    <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Zones->getDef('button_status'); ?></button>
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
    data-sort-name="country_name"
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
          data-switchable="false"><?php echo $CLICSHOPPING_Zones->getDef('id'); ?></th>
      <th data-field="country_name"
          data-sortable="true"><?php echo $CLICSHOPPING_Zones->getDef('table_heading_country_name'); ?></th>
      <th data-field="country_zone"
          data-sortable="true"><?php echo $CLICSHOPPING_Zones->getDef('table_heading_zone_name'); ?></th>
      <th data-field="zone_code" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Zones->getDef('table_heading_zone_code'); ?></th>
      <th data-field="zone_status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Zones->getDef('table_heading_zone_status'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Zones->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    if (isset($search)) {
      $Qzones = $CLICSHOPPING_Zones->db->prepare('select  SQL_CALC_FOUND_ROWS  z.zone_id,
                                                                                c.countries_id,
                                                                                c.countries_name,
                                                                                z.zone_name,
                                                                                z.zone_code,
                                                                                z.zone_country_id,
                                                                                z.zone_status
                                                      from :table_zones z,
                                                           :table_countries c
                                                      where z.zone_country_id = c.countries_id
                                                      and c.countries_name like :search
                                                      order by c.countries_name,
                                                               z.zone_name
                                                      limit :page_set_offset,
                                                            :page_set_max_results
                                                      ');

      $Qzones->bindValue(':search', '%' . $search . '%');
      $Qzones->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qzones->execute();
    } else {
      $Qzones = $CLICSHOPPING_Zones->db->prepare('select  SQL_CALC_FOUND_ROWS  z.zone_id,
                                                                                c.countries_id,
                                                                                c.countries_name,
                                                                                z.zone_name,
                                                                                z.zone_code,
                                                                                z.zone_country_id,
                                                                                z.zone_status
                                                      from :table_zones z,
                                                           :table_countries c
                                                      where z.zone_country_id = c.countries_id
                                                      order by c.countries_name,
                                                               z.zone_name
                                                      limit :page_set_offset,
                                                            :page_set_max_results
                                                      ');
      $Qzones->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qzones->execute();
    }

    $listingTotalRow = $Qzones->getPageSetTotalRows();

    if ($listingTotalRow > 0) {

      while ($Qzones->fetch()) {
        if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ((int)$_GET['cID'] === $Qzones->valueInt('zone_id')))) && !isset($cInfo)) {
          $cInfo = new ObjectInfo($Qzones->toArray());
        }
        ?>
        <td></td>
        <td><?php echo $Qzones->valueInt('zone_id'); ?></td>
        <td scope="row"><?php echo $Qzones->value('countries_name'); ?></td>
        <td><?php echo $Qzones->value('zone_name'); ?></td>
        <td><?php echo $Qzones->value('zone_code'); ?></td>
        <td class="text-center">
          <?php
          if ($Qzones->valueInt('zone_status') == 0) {
            echo '<a href="' . $CLICSHOPPING_Zones->link('Zones&SetFlag&page=' . $page . '&flag=1&id=' . $Qzones->valueInt('zone_id') . '&search=' . $search) . '"><i class="bi-check text-success"></i></a>';
          } else {
            echo '<a href="' . $CLICSHOPPING_Zones->link('Zones&SetFlag&page=' . $page . '&flag=0&id=' . $Qzones->valueInt('zone_id')) . '&search=' . $search . '"><i class="bi bi-x text-danger"></i></a>';
          }
          ?>
        </td>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            echo '<a href="' . $CLICSHOPPING_Zones->link('Edit&page=' . $page . '&cID=' . $Qzones->valueInt('zone_id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Zones->getDef('icon_edit') . '"></i></h4></a>';
            echo '&nbsp;';
            echo '<a href="' . $CLICSHOPPING_Zones->link('Delete&&page=' . $page . '&cID=' . $Qzones->valueInt('zone_id')) . '"><h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Zones->getDef('icon_delete') . '"></i></h4></a>';
            echo '&nbsp;';
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
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div
        class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qzones->getPageSetLabel($CLICSHOPPING_Zones->getDef('text_display_number_of_link')); ?></div>
      <div
        class="float-end text-end"><?php echo $Qzones->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
    </div>
  </div>
</div>
