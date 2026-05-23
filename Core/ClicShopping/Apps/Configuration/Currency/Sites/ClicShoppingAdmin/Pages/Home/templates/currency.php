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
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Currency = Registry::get('Currency');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/currencies.gif', $CLICSHOPPING_Currency->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Currency->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
          <?php
            echo HTML::button($CLICSHOPPING_Currency->getDef('button_insert'), null, $CLICSHOPPING_Currency->link('Insert'), 'success') . ' ';
            echo HTML::button($CLICSHOPPING_Currency->getDef('button_update_all'), null, $CLICSHOPPING_Currency->link('Currency&UpdateAll&page=' . $page), 'info');
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
    data-check-on-init="true"
    data-search="true">

  <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="name"
          data-sortable="true"><?php echo $CLICSHOPPING_Currency->getDef('table_heading_currency_name'); ?></th>
      <th data-field="code"><?php echo $CLICSHOPPING_Currency->getDef('table_heading_currency_code'); ?></th>
      <th data-field="surchage"
          class="text-end"><?php echo $CLICSHOPPING_Currency->getDef('table_heading_currency_surcharge'); ?></th>
      <th data-field="value" data-sortable="true"
          class="text-end"><?php echo $CLICSHOPPING_Currency->getDef('table_heading_currency_value'); ?></th>
      <th data-field="last_updated"
          class="text-center"><?php echo $CLICSHOPPING_Currency->getDef('table_heading_currency_last_updated'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Currency->getDef('table_heading_currency_status'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Currency->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qcurrency = $CLICSHOPPING_Currency->db->prepare('select SQL_CALC_FOUND_ROWS currencies_id,
                                                                                    title,
                                                                                    code,
                                                                                    symbol_left,
                                                                                    symbol_right,
                                                                                    decimal_point,
                                                                                    thousands_point,
                                                                                    decimal_places,
                                                                                    last_updated,
                                                                                    value,
                                                                                    status,
                                                                                    surcharge
                                                        from :table_currencies
                                                        order by title
                                                        limit :page_set_offset, :page_set_max_results
                                                      ');

    $Qcurrency->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qcurrency->execute();

    $listingTotalRow = $Qcurrency->getPageSetTotalRows();

    if ($listingTotalRow > 0) {

      while ($Qcurrency->fetch()) {
        if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ((int)$_GET['cID'] == $Qcurrency->valueInt('currencies_id')))) && !isset($cInfo)) {
          $cInfo = new ObjectInfo($Qcurrency->toArray());
        }

        if (DEFAULT_CURRENCY == $Qcurrency->value('code')) {
          echo '                <th scope="row"><strong>' . $Qcurrency->value('title') . ' (' . $CLICSHOPPING_Currency->getDef('text_default') . ')</strong></th>' . "\n";
        } else {
          echo '                <th scope="row">' . $Qcurrency->value('title') . '</th>' . "\n";
        }
        ?>
        <td><?php echo $Qcurrency->value('code'); ?></td>
        <td class="text-end"><?php echo $Qcurrency->valueDecimal('surcharge'); ?></td>
        <td class="text-end"><?php echo number_format($Qcurrency->valueDecimal('value'), 8); ?></td>
        <td class="text-center"><?php echo DateTime::toShort($Qcurrency->value('last_updated')); ?></td>
        <td class="text-center">
          <?php
          if ($Qcurrency->valueInt('status') == 1) {
            if (DEFAULT_CURRENCY == $Qcurrency->value('code')) {
              echo '<i class="bi-check text-success"></i>';
            } else {
              echo HTML::link($CLICSHOPPING_Currency->link('Currency&SetFlag&flag=0&cID=' . $Qcurrency->valueInt('currencies_id') . '&page=' . $page), '<i class="bi-check text-success"></i>');
            }
          } else {
            echo HTML::link($CLICSHOPPING_Currency->link('Currency&SetFlag&flag=1&cID=' . $Qcurrency->valueInt('currencies_id') . '&page=' . $page), '<i class="bi bi-x text-danger"></i>');
          }
          ?>
        </td>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            echo HTML::link($CLICSHOPPING_Currency->link('Edit&page=' . $page . '&cID=' . $Qcurrency->valueInt('currencies_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Currency->getDef('icon_edit') . '"></i></h4>');
            echo '&nbsp;';
            echo HTML::link($CLICSHOPPING_Currency->link('Delete&page=' . $page . '&cID=' . $Qcurrency->valueInt('currencies_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Currency->getDef('icon_delete') . '"></i></h4>');
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qcurrency->getPageSetLabel($CLICSHOPPING_Currency->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qcurrency->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>