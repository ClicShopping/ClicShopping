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

$CLICSHOPPING_Api = Registry::get('Api');
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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/api.png', $CLICSHOPPING_Api->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Api->getDef('heading_title'); ?></span>
          <span
            class="col-md-7 text-end">
            <?php
              echo HTML::button($CLICSHOPPING_Api->getDef('button_configure'), null, $CLICSHOPPING_Api->link('Configure'), 'primary') . ' ';
              echo HTML::button($CLICSHOPPING_Api->getDef('button_insert'), null, $CLICSHOPPING_Api->link('Edit'), 'success') . ' ';
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING DES                                                        -->
  <!-- //################################################################################################################ -->

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="value"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="id" data-sortable="true"><?php echo $CLICSHOPPING_Api->getDef('table_heading_api_id'); ?></th>
      <th data-field="username"
          data-sortable="true"><?php echo $CLICSHOPPING_Api->getDef('table_heading_api_username'); ?></th>
      <th data-field="key"
          class="text-center"><?php echo $CLICSHOPPING_Api->getDef('table_heading_api_key_text'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Api->getDef('table_heading_api_status'); ?></th>
      <th data-field="date_added" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Api->getDef('table_heading_api_date_added'); ?></th>
      <th data-field="date_modified" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Api->getDef('table_heading_api_date_modified'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Api->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qapi = $CLICSHOPPING_Api->db->prepare('select SQL_CALC_FOUND_ROWS api_id,
                                                                        username,
                                                                        api_key,
                                                                        status,
                                                                        date_added,
                                                                        date_modified
                                                        from :table_api
                                                        order by api_id
                                                        limit :page_set_offset, :page_set_max_results
                                                      ');

    $Qapi->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qapi->execute();

    $listingTotalRow = $Qapi->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qapi->fetch()) {
        ?>
        <td><?php echo $Qapi->value('api_id'); ?></td>
        <td><?php echo $Qapi->value('username'); ?></td>
        <td><?php echo substr($Qapi->value('api_key'), -40, 40) . '...'; ?></td>
        <td class="text-center">
          <?php
          if ($Qapi->valueInt('status') == 1) {
            echo HTML::link($CLICSHOPPING_Api->link('Api&SetFlag&flag=0&cID=' . $Qapi->valueInt('api_id') . '&page=' . $page), '<i class="bi-check text-success"></i>');
          } else {
            echo HTML::link($CLICSHOPPING_Api->link('Api&SetFlag&flag=1&cID=' . $Qapi->valueInt('api_id') . '&page=' . $page), '<i class="bi bi-x text-danger"></i>');
          }
          ?>
        </td>
        <td><?php echo DateTime::toShort($Qapi->value('date_added')); ?></td>
        <td><?php echo DateTime::toShort($Qapi->value('date_modified')); ?></td>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            echo HTML::link($CLICSHOPPING_Api->link('Edit&page=' . $page . '&cID=' . $Qapi->valueInt('api_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Api->getDef('icon_edit') . '"></i></h4>');
            echo '&nbsp;';
            echo HTML::link($CLICSHOPPING_Api->link('Api&Delete&page=' . $page . '&cID=' . $Qapi->valueInt('api_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Api->getDef('icon_delete') . '"></i></h4>');
            echo '&nbsp;';
            ?>
          </div>
        </td>
        </tr>
        <?php
      }
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qapi->getPageSetLabel($CLICSHOPPING_Api->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qapi->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>