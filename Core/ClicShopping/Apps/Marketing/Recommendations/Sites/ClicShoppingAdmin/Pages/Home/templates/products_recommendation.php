<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;

$CLICSHOPPING_Recommendations = Registry::get('Recommendations');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Currencies = Registry::get('Currencies');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Image = Registry::get('Image');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$action = $_GET['action'] ?? '';

$languages = $CLICSHOPPING_Language->getLanguages();

$customers_group = GroupsB2BAdmin::getAllGroups();
$customers_group_name = '';

foreach ($customers_group as $value) {
  $customers_group_name .= '<option value="' . $value['id'] . '">' . $value['text'] . '</option>';
} // end empty action
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/product_recommendations.png', $CLICSHOPPING_Recommendations->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Recommendations->getDef('heading_title'); ?></span>
          <span class="col-md-2">
           <div class="row">
            <?php
            if (MODE_B2B_B2C == 'True') {
              if (isset($_POST['customers_group_id'])) {
                $customers_group_id = HTML::sanitize($_POST['customers_group_id']);
              } else {
                $customers_group_id = null;
              }

              echo HTML::form('grouped', $CLICSHOPPING_Recommendations->link('ProductsRecommendation'));
              echo HTML::selectMenu('customers_group_id', GroupsB2BAdmin::getAllGroups(), $customers_group_id, 'onchange="this.form.submit();"');
              echo '</form>';
            }
            ?>
           </div>
         </span>
          <span class="col-md-3">
            <?php
            if (MODE_B2B_B2C == 'True' && isset($_POST['customers_group_id'])) {
              echo HTML::button($CLICSHOPPING_Recommendations->getDef('button_reset'), null, $CLICSHOPPING_Recommendations->link('ProductsRecommendation'), 'warning');
            }
            ?>
         </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING DES COUPS DE COEUR                                             -->
  <!-- //################################################################################################################ -->
  <?php echo HTML::form('delete_all', $CLICSHOPPING_Recommendations->link('Recommendations&Recommendations&DeleteAll&page=' . $page)); ?>

  <div id="toolbar" class="float-end">
    <button id="button"
            class="btn btn-danger"><?php echo $CLICSHOPPING_Recommendations->getDef('button_delete'); ?></button>
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
    data-check-on-init="true"
    data-search="true">

  <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected" data-sortable="true" data-visible="false"
          data-switchable="false"><?php echo $CLICSHOPPING_Recommendations->getDef('id'); ?></th>
      <th data-switchable="false">&nbsp;</th>
      <th data-field="products"
          data-sortable="true"><?php echo $CLICSHOPPING_Recommendations->getDef('table_heading_products'); ?></th>
      <?php
      if (MODE_B2B_B2C == 'True') {
        ?>
        <th><?php echo $CLICSHOPPING_Recommendations->getDef('table_heading_products_group'); ?></th>
        <?php
      }
      ?>
      <th data-field="price"
          data-sortable="true"><?php echo $CLICSHOPPING_Recommendations->getDef('table_heading_products_price'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Recommendations->getDef('table_heading_status'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Recommendations->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    if (isset($_POST['customers_group_id'])) {
      $Qrecommendations = $CLICSHOPPING_Recommendations->db->prepare('select distinctrow  SQL_CALC_FOUND_ROWS p.products_id,
                                                                                                            p.products_model,
                                                                                                            p.products_image,
                                                                                                            p.products_price,
                                                                                                            pd.products_name,
                                                                                                            r.products_id,
                                                                                                            r.customers_group_id,
                                                                                                            r.customers_id,
                                                                                                            r.status
                                                                   from :table_products p,
                                                                        :table_products_recommendations r,
                                                                        :table_products_description pd
                                                                  where p.products_id = pd.products_id
                                                                  and pd.language_id = :language_id
                                                                  and p.products_id = r.products_id
                                                                  and r.customers_group_id = :customers_group_id
                                                                  order by pd.products_name
                                                                  limit :page_set_offset, :page_set_max_results
                                                                ');

      $Qrecommendations->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qrecommendations->bindInt(':customers_group_id', $customers_group_id);
      $Qrecommendations->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qrecommendations->execute();
    } else {
      $Qrecommendations = $CLICSHOPPING_Recommendations->db->prepare('select distinctrow SQL_CALC_FOUND_ROWS p.products_id,
                                                                                                            p.products_model,
                                                                                                            p.products_image,
                                                                                                            p.products_price,
                                                                                                            pd.products_name,
                                                                                                            r.products_id,
                                                                                                            r.customers_id,
                                                                                                            r.status
                                                                           from :table_products p,
                                                                                :table_products_recommendations r,
                                                                                :table_products_description pd
                                                                          where p.products_id = pd.products_id
                                                                          and pd.language_id = :language_id
                                                                          and p.products_id = r.products_id
                                                                          order by pd.products_name                                                                          
                                                                          limit :page_set_offset, :page_set_max_results
                                                                          ');

      $Qrecommendations->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qrecommendations->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qrecommendations->execute();
    }

    $listingTotalRow = $Qrecommendations->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qrecommendations->fetch()) {
        ?>
        <tr>
          <td></td>
          <td><?php echo $Qrecommendations->valueInt('products_id'); ?></td>
          <td><?php echo $CLICSHOPPING_Image->getSmallImageAdmin($Qrecommendations->valueInt('products_id')); ?></td>
          <td><?php echo $Qrecommendations->value('products_name') . ' [' . $Qrecommendations->value('products_model') . ']'; ?></td>
          <?php
          if (MODE_B2B_B2C == 'True') {
            if ($Qrecommendations->valueInt('customers_group_id') != 0 && $Qrecommendations->valueInt('customers_group_id') != 99) {
              $all_groups_name = GroupsB2BAdmin::getCustomersGroupName($Qrecommendations->valueInt('customers_group_id'));
            } elseif ($Qrecommendations->valueInt('customers_group_id') == 99) {
              $all_groups_name = $CLICSHOPPING_Recommendations->getDef('text_all_groups');
            } else {
              $all_groups_name = $CLICSHOPPING_Recommendations->getDef('visitor_name');
            }
            ?>
            <td><?php echo $all_groups_name; ?></td>
            <?php
          } // end mode b2B_B2C
          ?>
          <td
            class="text-start"><?php echo $CLICSHOPPING_Currencies->format($Qrecommendations->valueDecimal('products_price')); ?></td>
          <td class="text-center">
            <?php
            if ($Qrecommendations->valueInt('status') == 1) {
              echo '<a href="' . $CLICSHOPPING_Recommendations->link('Recommendations&Recommendations&SetFlag&page=' . (int)$page . '&flag=0&id=' . (int)$Qrecommendations->valueInt('products_id')) . '"><i class="bi-check text-success"></i></a>';
            } else {
              echo '<a href="' . $CLICSHOPPING_Recommendations->link('Recommendations&Recommendations&SetFlag&page=' . (int)$page . '&flag=1&id=' . (int)$Qrecommendations->valueInt('products_id')) . '"><i class="bi bi-x text-danger"></i></a>';
            }
            ?>
          </td>
          <td class="text-end">
            <?php
            echo HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Edit&pID=' . $Qrecommendations->valueInt('products_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Recommendations->getDef('icon_edit') . '"></i></h4>');
            echo '&nbsp;';
            ?>
          </td>
        </tr>
        <?php
      }
    }
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qrecommendations->getPageSetLabel($CLICSHOPPING_Recommendations->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"> <?php echo $Qrecommendations->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>