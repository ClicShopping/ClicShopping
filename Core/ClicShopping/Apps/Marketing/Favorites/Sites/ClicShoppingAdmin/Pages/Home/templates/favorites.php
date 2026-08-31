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

use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;

$CLICSHOPPING_Favorites = Registry::get('Favorites');
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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_favorites.png', $CLICSHOPPING_Favorites->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Favorites->getDef('heading_title'); ?></span>
          <span class="col-md-2">
           <div>
             <div>
<?php
if (MODE_B2B_B2C == 'True') {

  if (isset($_POST['customers_group_id'])) {
    $customers_group_id = HTML::sanitize($_POST['customers_group_id']);
  } else {
    $customers_group_id = null;
  }

  echo HTML::form('grouped', $CLICSHOPPING_Favorites->link('Favorites'));
  echo HTML::selectMenu('customers_group_id', GroupsB2BAdmin::getAllGroups(), $customers_group_id, 'onchange="this.form.submit();"');
  echo '</form>';
}
?>
             </div>
           </div>
         </span>
          <span class="col-md-3">
<?php
if (MODE_B2B_B2C == 'True' && isset($_POST['customers_group_id'])) {
  echo HTML::button($CLICSHOPPING_Favorites->getDef('button_reset'), null, $CLICSHOPPING_Favorites->link('Favorites'), 'warning');
}
?>
         </span>
          <span class="col-md-4 text-end">
<?php
echo HTML::button($CLICSHOPPING_Favorites->getDef('button_new'), null, $CLICSHOPPING_Favorites->link('Edit&page=' . $page . '&action=new'), 'success');
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
  <?php echo HTML::form('delete_all', $CLICSHOPPING_Favorites->link('Favorites&Favorites&DeleteAll&page=' . $page)); ?>

  <div id="toolbar" class="float-end">
    <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Favorites->getDef('button_delete'); ?></button>
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
          data-switchable="false"><?php echo $CLICSHOPPING_Favorites->getDef('id'); ?></th>
      <th data-switchable="false"></th>
      <th data-switchable="false">&nbsp;</th>
      <th data-field="products"
          data-sortable="true"><?php echo $CLICSHOPPING_Favorites->getDef('table_heading_products'); ?></th>
      <?php
      // Permettre le changement de groupe en mode B2B
      if (MODE_B2B_B2C == 'True') {
        ?>
        <th><?php echo $CLICSHOPPING_Favorites->getDef('table_heading_products_group'); ?></th>
        <?php
      }
      ?>
      <th data-field="price"
          data-sortable="true"><?php echo $CLICSHOPPING_Favorites->getDef('table_heading_products_price'); ?></th>
      <th data-field="scheduled_date" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Favorites->getDef('table_heading_scheduled_date'); ?></th>
      <th data-field="expires_date" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Favorites->getDef('table_heading_expires_date'); ?></th>
      <th data-field="archive" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Favorites->getDef('table_heading_archive'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Favorites->getDef('table_heading_status'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Favorites->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    if (isset($_POST['customers_group_id'])) {
      $Qfavorites = $CLICSHOPPING_Favorites->db->prepare('select  SQL_CALC_FOUND_ROWS p.products_id,
                                                                                    p.products_model,
                                                                                    p.products_image,
                                                                                    pd.products_name,
                                                                                    p.products_price,
                                                                                    s.products_favorites_id,
                                                                                    s.customers_group_id,
                                                                                    s.products_favorites_date_added,
                                                                                    s.products_favorites_last_modified,
                                                                                    s.scheduled_date,
                                                                                    s.expires_date,
                                                                                    s.date_status_change,
                                                                                    s.status,
                                                                                    p.products_archive
                                                       from :table_products p,
                                                            :table_products_favorites s,
                                                            :table_products_description pd
                                                      where p.products_id = pd.products_id
                                                      and pd.language_id = :language_id
                                                      and p.products_id = s.products_id
                                                      and s.customers_group_id = :customers_group_id
                                                      order by pd.products_name
                                                      limit :page_set_offset, :page_set_max_results
                                                    ');

      $Qfavorites->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qfavorites->bindInt(':customers_group_id', $customers_group_id);
      $Qfavorites->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qfavorites->execute();
    } else {
      $Qfavorites = $CLICSHOPPING_Favorites->db->prepare('select SQL_CALC_FOUND_ROWS p.products_id,
                                                                                  p.products_model,
                                                                                  p.products_image,
                                                                                  pd.products_name,
                                                                                  p.products_price,
                                                                                  s.products_favorites_id,
                                                                                  s.customers_group_id,
                                                                                  s.products_favorites_date_added,
                                                                                  s.products_favorites_last_modified,
                                                                                  s.scheduled_date,
                                                                                  s.expires_date,
                                                                                  s.date_status_change,
                                                                                  s.status,
                                                                                  p.products_archive
                                                       from :table_products p,
                                                            :table_products_favorites s,
                                                            :table_products_description pd
                                                      where p.products_id = pd.products_id
                                                      and pd.language_id = :language_id
                                                      and p.products_id = s.products_id
                                                      order by pd.products_name
                                                      limit :page_set_offset, :page_set_max_results
                                                      ');

      $Qfavorites->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qfavorites->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qfavorites->execute();
    }

    $listingTotalRow = $Qfavorites->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qfavorites->fetch()) {
        if ((!isset($_GET['sID']) || (isset($_GET['sID']) && ($_GET['sID'] == $Qfavorites->valueInt('products_favorites_id')))) && !isset($sInfo)) {
          $Qproduct = $CLICSHOPPING_Favorites->db->get('products', 'products_image', ['products_id' => $Qfavorites->valueInt('products_id')]);

          $sInfo_array = array_merge($Qfavorites->toArray(), $Qproduct->toArray());
          $sInfo = new ObjectInfo($sInfo_array);
        }
        ?>
        <tr>
          <td></td>
          <td><?php echo $Qfavorites->valueInt('products_favorites_id'); ?></td>
          <td scope="row" width="50px">
            <?php echo HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Preview&pID=' . $Qfavorites->valueInt('products_id') . '?page=' . $page), '<h4><i class="bi bi-easil3" title="' . $CLICSHOPPING_Favorites->getDef('icon_preview') . '"></i></h4>'); ?>
          </td>
          <td><?php echo $CLICSHOPPING_Image->getSmallImageAdmin($Qfavorites->valueInt('products_id')); ?></td>
          <td><?php echo $Qfavorites->value('products_name') . ' [' . $Qfavorites->value('products_model') . ']'; ?></td>
          <?php
          if (MODE_B2B_B2C == 'True') {
            if ($Qfavorites->valueInt('customers_group_id') != 0 && $Qfavorites->valueInt('customers_group_id') != 99) {
              $all_groups_name_products_favorites = GroupsB2BAdmin::getCustomersGroupName($Qfavorites->valueInt('customers_group_id'));
            } elseif ($Qfavorites->valueInt('customers_group_id') == 99) {
              $all_groups_name_products_favorites = $CLICSHOPPING_Favorites->getDef('text_all_groups');
            } else {
              $all_groups_name_products_favorites = $CLICSHOPPING_Favorites->getDef('visitor_name');
            }
            ?>
            <td><?php echo $all_groups_name_products_favorites; ?></td>
            <?php
          } // end mode b2B_B2C
          ?>
          <td
            class="text-start"><?php echo $CLICSHOPPING_Currencies->format($Qfavorites->value('products_price')); ?></td>
          <?php
          if (!\is_null($Qfavorites->value('scheduled_date'))) {
            ?>
            <td class="text-center"><?php echo DateTime::toShort($Qfavorites->value('scheduled_date')); ?></td>
            <?php
          } else {
            ?>
            <td class="text-center"></td>
            <?php
          }

          if (!\is_null($Qfavorites->value('expires_date'))) {
            ?>
            <td class="text-center"><?php echo DateTime::toShort($Qfavorites->value('expires_date')); ?></td>
            <?php
          } else {
            ?>
            <td class="text-center"></td>
            <?php
          }

          if ($Qfavorites->valueInt('products_archive') == 1) {
            ?>
            <td class="text-center"><i class="bi-check text-success"></i></td>
            <?php
          } else {
            ?>
            <td></td>
            <?php
          }
          ?>
          <td class="text-center">
            <?php
            if ($Qfavorites->valueInt('status') == 1) {
              echo '<a href="' . $CLICSHOPPING_Favorites->link('Favorites&Favorites&SetFlag&page=' . (int)$page . '&flag=0&id=' . (int)$Qfavorites->valueInt('products_favorites_id')) . '"><i class="bi-check text-success"></i></a>';
            } else {
              echo '<a href="' . $CLICSHOPPING_Favorites->link('Favorites&Favorites&SetFlag&page=' . (int)$page . '&flag=1&id=' . (int)$Qfavorites->valueInt('products_favorites_id')) . '"><i class="bi bi-x text-danger"></i></a>';
            }
            ?>
          </td>
          <td class="text-end">
            <?php
            echo '<a href="' . $CLICSHOPPING_Favorites->link('Edit&page=' . (int)$page . '&sID=' . (int)$Qfavorites->valueInt('products_favorites_id') . '&action=update') . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Favorites->getDef('icon_edit') . '"></i></h4></a>';
            echo '&nbsp;';
            ?>
          </td>
        </tr>
        <?php
      } // end while
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qfavorites->getPageSetLabel($CLICSHOPPING_Favorites->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"> <?php echo $Qfavorites->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>