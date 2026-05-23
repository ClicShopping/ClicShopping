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

$CLICSHOPPING_TaxClass = Registry::get('TaxClass');
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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/tax_classes.gif', $CLICSHOPPING_TaxClass->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TaxClass->getDef('heading_title'); ?></span>
          <span
            class="col-md-7 text-end"><?php echo HTML::button($CLICSHOPPING_TaxClass->getDef('button_insert'), null, $CLICSHOPPING_TaxClass->link('Insert&page=' . $page), 'success'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <table border="0" width="100%" cellspacing="0" cellpadding="2">
    <td>
      <table class="table table-sm table-hover table-striped">
        <thead>
        <tr class="dataTableHeadingRow">
          <th><?php echo $CLICSHOPPING_TaxClass->getDef('table_heading_tax_classes'); ?></th>
          <th><?php echo $CLICSHOPPING_TaxClass->getDef('table_heading_tax_description'); ?></th>
          <th class="text-end"><?php echo $CLICSHOPPING_TaxClass->getDef('table_heading_action'); ?>&nbsp;</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $Qclasse = $CLICSHOPPING_TaxClass->db->prepare('select  SQL_CALC_FOUND_ROWS  tax_class_id,
                                                                                       tax_class_title,
                                                                                       tax_class_description,
                                                                                       last_modified,
                                                                                       date_added
                                                          from :table_tax_class
                                                          order by tax_class_title
                                                          limit :page_set_offset,
                                                                :page_set_max_results
                                                          ');

        $Qclasse->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
        $Qclasse->execute();

        $listingTotalRow = $Qclasse->getPageSetTotalRows();

        if ($listingTotalRow > 0) {

          while ($Qclasse->fetch()) {
            if ((!isset($_GET['tID']) || (isset($_GET['tID']) && ($_GET['tID'] == $Qclasse->valueInt('tax_class_id')))) && !isset($tcInfo)) {
              $tcInfo = new ObjectInfo($Qclasse->toArray());
            }
            ?>
            <th scope="row"><?php echo $Qclasse->value('tax_class_title'); ?></th>
            <td><?php echo $Qclasse->value('tax_class_description'); ?></td>
            <td class="text-end">
              <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
                <?php
                echo '<a href="' . $CLICSHOPPING_TaxClass->link('Edit&page=' . $page . '&tID=' . $Qclasse->valueInt('tax_class_id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_TaxClass->getDef('icon_edit') . '"></i></h4></a>';
                echo '&nbsp;';
                echo '<a href="' . $CLICSHOPPING_TaxClass->link('Delete&page=' . $page . '&tID=' . $Qclasse->valueInt('tax_class_id')) . '"><h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_TaxClass->getDef('icon_delete') . '"></i></h4></a>';
                echo '&nbsp;';
                ?>
              </div>
            </td>
            </tr>

            <?php
          } // end while
        }
        ?>
        </tbody>
      </table>
    </td>
  </table>
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qclasse->getPageSetLabel($CLICSHOPPING_TaxClass->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"> <?php echo $Qclasse->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>

</div>
