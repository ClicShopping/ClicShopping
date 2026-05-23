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

use ClicShopping\Apps\Configuration\ProductsLength\Classes\ClicShoppingAdmin\ProductsLengthAdmin;

$CLICSHOPPING_ProductsLength = Registry::get('ProductsLength');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_length.png', $CLICSHOPPING_ProductsLength->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ProductsLength->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
            <?php
              echo HTML::button($CLICSHOPPING_ProductsLength->getDef('button_insert_products_length'), null, $CLICSHOPPING_ProductsLength->link('ProductsLengthInsert&page=' . $page), 'primary') . ' ';
              echo HTML::button($CLICSHOPPING_ProductsLength->getDef('button_insert_class'), null, $CLICSHOPPING_ProductsLength->link('ClassInsert&page=' . $page), 'success');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING                                                            -->
  <!-- //################################################################################################################ -->

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="symbol"
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
      <th data-field="length_class_id"
          data-sortable="true"><?php echo $CLICSHOPPING_ProductsLength->getDef('table_heading_products_length_class_id'); ?></th>
      <th data-field="symbol"
          data-sortable="true"><?php echo $CLICSHOPPING_ProductsLength->getDef('table_heading_products_length_class_symbol'); ?></th>
      <th data-field="type"
          data-sortable="true"><?php echo $CLICSHOPPING_ProductsLength->getDef('table_heading_products_length_class_type'); ?></th>
      <th data-field="class_to_id"
          data-sortable="true"><?php echo $CLICSHOPPING_ProductsLength->getDef('table_heading_products_length_class_to_id'); ?></th>
      <th
        data-field="rule"><?php echo $CLICSHOPPING_ProductsLength->getDef('table_heading_products_length_class_rule'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_ProductsLength->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qproducts_length = $CLICSHOPPING_ProductsLength->db->prepare('select SQL_CALC_FOUND_ROWS  wc.products_length_class_id,
                                                                                                     wc.products_length_class_key,
                                                                                                     wc.language_id,
                                                                                                     wc.products_length_class_title,
                                                                                                     tc.products_length_class_from_id,
                                                                                                     tc.products_length_class_to_id,
                                                                                                     tc.products_length_class_rule
                                                                          from :table_products_length_classes wc,
                                                                               :table_products_length_classes_rules tc 
                                                                          where wc.products_length_class_id = tc.products_length_class_from_id
                                                                          and wc.language_id = :language_id
                                                                          limit :page_set_offset,
                                                                                :page_set_max_results
                                                                          ');
    $Qproducts_length->bindInt(':language_id', $CLICSHOPPING_Language->getID());
    $Qproducts_length->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qproducts_length->execute();

    $listingTotalRow = $Qproducts_length->getPageSetTotalRows();

    if ($listingTotalRow > 0) {

      while ($Qproducts_length->fetch()) {
        if ((!isset($_GET['wID']) || (isset($_GET['wID']) && ((int)$_GET['wID'] === $Qproducts_length->valueInt('products_length_id')))) && !isset($trInfo)) {
          $trInfo = new ObjectInfo($Qproducts_length->toArray());
        }

        $products_length_class_title = ProductsLengthAdmin::getTitle($Qproducts_length->valueInt('products_length_class_to_id'), $CLICSHOPPING_Language->getID());

        ?>
        <tr>
          <td scope="row"><?php echo $Qproducts_length->valueInt('products_length_class_id'); ?></td>
          <td><?php echo $Qproducts_length->value('products_length_class_key'); ?></td>
          <td><?php echo $Qproducts_length->value('products_length_class_title'); ?></td>
          <td><?php echo $products_length_class_title; ?></td>
          <td><?php echo $Qproducts_length->value('products_length_class_rule'); ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_ProductsLength->link('ClassEdit&page=' . $page . '&wID=' . $Qproducts_length->valueInt('products_length_class_id') . '&tID=' . $Qproducts_length->valueInt('products_length_class_to_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_ProductsLength->getDef('icon_edit') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_ProductsLength->link('ProductsLengthEdit&page=' . $page . '&wID=' . $Qproducts_length->valueInt('products_length_class_id')), '<h4><i class="bi bi-clipboard2" title="' . $CLICSHOPPING_ProductsLength->getDef('icon_edit_class_title') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_ProductsLength->link('ClassDelete&page=' . $page . '&wID=' . $Qproducts_length->valueInt('products_length_class_id') . '&tID=' . $Qproducts_length->valueInt('products_length_class_to_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_ProductsLength->getDef('icon_delete') . '"></i></h4>');
              echo '&nbsp;';
              ?>
            </div>
            >
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
  <div class="mt-1"></div>
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qproducts_length->getPageSetLabel($CLICSHOPPING_ProductsLength->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qproducts_length->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
