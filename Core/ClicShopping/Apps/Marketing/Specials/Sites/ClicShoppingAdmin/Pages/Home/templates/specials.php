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

$CLICSHOPPING_Specials = Registry::get('Specials');
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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/specials.gif', $CLICSHOPPING_Specials->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Specials->getDef('heading_title'); ?></span>
          <span class="col-md-2">
           <div>
              <?php
              if (MODE_B2B_B2C == 'True') {
                echo HTML::form('grouped', $CLICSHOPPING_Specials->link('Specials'), 'post', '');

                if (isset($_POST['customers_group_id'])) {
                  $customers_group_id = HTML::sanitize($_POST['customers_group_id']);
                } else {
                  $customers_group_id = null;
                }

                echo HTML::selectMenu('customers_group_id', GroupsB2BAdmin::getAllGroups(), $customers_group_id, 'onchange="this.form.submit();"');
                echo '</form>';
              }
              ?>
           </div>
         </span>
          <span class="col-md-3">
            <?php
            if (MODE_B2B_B2C == 'True' && isset($_POST['customers_group_id'])) {
              echo HTML::button($CLICSHOPPING_Specials->getDef('button_reset'), null, $CLICSHOPPING_Specials->link('Specials'), 'warning');
            }
            ?>
         </span>
          <span class="col-md-4 text-end">
            <?php echo HTML::button($CLICSHOPPING_Specials->getDef('button_new'), null, $CLICSHOPPING_Specials->link('Edit&page=' . $page . '&action=new'), 'success'); ?>
         </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING DES PROMOTIONS                                              -->
  <!-- //################################################################################################################ -->
  <?php
  echo HTML::form('delete_all', $CLICSHOPPING_Specials->link('Specials&Specials&DeleteAll&page=' . $page));
  ?>

  <div id="toolbar" class="float-end">
    <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Specials->getDef('button_delete'); ?></button>
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
          data-switchable="false"><?php echo $CLICSHOPPING_Specials->getDef('id'); ?></th>
      <th data-switchable="false"></th>
      <th data-switchable="false"></th>
      <th data-field="heading_products"
          data-sortable="true"><?php echo $CLICSHOPPING_Specials->getDef('table_heading_products'); ?></th>
      <?php
      if (MODE_B2B_B2C == 'True') {
        ?>
        <th data-field="group"
            data-sortable="true"><?php echo $CLICSHOPPING_Specials->getDef('table_heading_products_group'); ?></th>
        <?php
      }
      ?>
      <th data-field="percentage"
          data-sortable="true"><?php echo $CLICSHOPPING_Specials->getDef('table_heading_percentage'); ?></th>
      <th data-field="products_price"
          data-sortable="true"><?php echo $CLICSHOPPING_Specials->getDef('table_heading_products_price'); ?></th>
      <th data-field="scheduled_date'" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Specials->getDef('table_heading_scheduled_date'); ?></th>
      <th data-field="expires_date" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Specials->getDef('table_heading_expires_date'); ?></th>
      <th data-field="falsh_discount" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Specials->getDef('table_heading_flash_discount'); ?></th>
      <th data-field="archive" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Specials->getDef('table_heading_archive'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Specials->getDef('table_heading_status'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Specials->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    if (isset($_POST['customers_group_id']) && $_POST['customers_group_id'] != 0) {
      $customers_group_id = HTML::sanitize($_POST['customers_group_id']);

      $Qspecials = $CLICSHOPPING_Specials->db->prepare('select SQL_CALC_FOUND_ROWS p.products_id,
                                                                                      p.products_model,
                                                                                      pd.products_name,
                                                                                      p.products_image,
                                                                                      p.products_price,
                                                                                      s.specials_id,
                                                                                      s.customers_group_id,
                                                                                      s.specials_new_products_price,
                                                                                      s.specials_date_added,
                                                                                      s.specials_last_modified,
                                                                                      s.scheduled_date,
                                                                                      s.expires_date,
                                                                                      s.date_status_change,
                                                                                      s.status,
                                                                                      p.products_archive,
                                                                                      s.flash_discount
                                                                     from :table_products p,
                                                                          :table_specials s,
                                                                          :table_products_description pd
                                                                    where p.products_id = pd.products_id
                                                                    and p.products_id = s.products_id
                                                                    and pd.language_id = :language_id
                                                                    and s.customers_group_id = :customers_group_id 
                                                                    order by pd.products_name
                                                                    limit :page_set_offset, :page_set_max_results
                                                                    ');

      $Qspecials->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qspecials->bindInt(':customers_group_id', $customers_group_id);
      $Qspecials->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qspecials->execute();
    } else {
      $Qspecials = $CLICSHOPPING_Specials->db->prepare('select SQL_CALC_FOUND_ROWS  p.products_id,
                                                                                        p.products_model,
                                                                                        pd.products_name,
                                                                                        p.products_image,
                                                                                        p.products_price,
                                                                                        s.specials_id,
                                                                                        s.customers_group_id,
                                                                                        s.specials_new_products_price,
                                                                                        s.specials_date_added,
                                                                                        s.specials_last_modified,
                                                                                        s.scheduled_date,
                                                                                        s.expires_date,
                                                                                        s.date_status_change,
                                                                                        s.status,
                                                                                        p.products_archive,
                                                                                        s.flash_discount
                                                                     from :table_products p,
                                                                          :table_specials s,
                                                                          :table_products_description pd
                                                                    where p.products_id = pd.products_id
                                                                    and pd.language_id = :language_id
                                                                    and p.products_id = s.products_id
                                                                    and (s.customers_group_id = 0 or s.customers_group_id = 99)
                                                                    order by pd.products_name
                                                                    limit :page_set_offset, :page_set_max_results
                                                                    ');

      $Qspecials->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qspecials->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qspecials->execute();
    }

    $listingTotalRow = $Qspecials->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qspecials->fetch()) {
        if ((!isset($_GET['sID']) || (isset($_GET['sID']) && ((int)$_GET['sID'] == $Qspecials->valueInt('specials_id')))) && !isset($sInfo)) {

          $Qproduct = $CLICSHOPPING_Specials->db->get('products', 'products_image', ['products_id' => $Qspecials->valueInt('products_id')]);

          $sInfo_array = array_merge($Qspecials->toArray(), $Qproduct->toArray());
          $sInfo = new ObjectInfo($sInfo_array);
        }

        $QcustomersGroupPrice = $CLICSHOPPING_Specials->db->prepare('select customers_group_price
                                                                        from :table_products_groups
                                                                        where products_id = :products_id
                                                                        and customers_group_id =  :customers_group_id
                                                                      ');
        $QcustomersGroupPrice->bindInt(':products_id', $Qspecials->valueInt('products_id'));
        $QcustomersGroupPrice->bindInt(':customers_group_id', $sInfo->customers_group_id);

        $QcustomersGroupPrice->execute();

        if ($QcustomersGroupPrice->fetch()) {
          $price = $Qspecials->valueDecimal('products_price');
          $sInfo->products_price = $price = $QcustomersGroupPrice->valueDecimal('customers_group_price');
        }
        ?>
        <td></td>
        <td><?php echo $Qspecials->valueInt('specials_id'); ?></td>
        <td scope="row"
            width="50px"><?php echo HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Preview&pID=' . $Qspecials->valueInt('products_id') . '?page=' . $page), '<h4><i class="bi bi-easil3" title="' . $CLICSHOPPING_Specials->getDef('icon_preview') . '"></i></h4>'); ?></td>
        <td><?php echo $CLICSHOPPING_Image->getSmallImageAdmin($Qspecials->valueInt('products_id')); ?></td>
        <td><?php echo $Qspecials->value('products_name') . ' [' . $Qspecials->value('products_model') . ']'; ?></td>
        <?php
        if (MODE_B2B_B2C == 'True') {
          if ($Qspecials->valueInt('customers_group_id') != 0 && $Qspecials->valueInt('customers_group_id') != 99) {
            $all_groups_name_special = GroupsB2BAdmin::getCustomersGroupName($Qspecials->valueInt('customers_group_id'));
          } elseif ($Qspecials->valueInt('customers_group_id') == 99) {
            $all_groups_name_special = $CLICSHOPPING_Specials->getDef('text_all_groups');
          } else {
            $all_groups_name_special = $CLICSHOPPING_Specials->getDef('visitor_name');
          }
          ?>
          <td><?php echo $all_groups_name_special; ?></td>
          <?php
        } // end mode b2B_B2C
        ?>
        <td class="text-start">
          <?php
          if ($Qspecials->valueDecimal('products_price') != 0) {
            echo number_format(100 - (($Qspecials->valueDecimal('specials_new_products_price') / $Qspecials->valueDecimal('products_price')) * 100)) . '%';
          }
          ?>
        </td>
        <td class="text-start"><span
            class="oldPrice"><?php echo $CLICSHOPPING_Currencies->format($Qspecials->valueDecimal('products_price')); ?></span><span
            class="specialPrice"><?php echo $CLICSHOPPING_Currencies->format($Qspecials->valueDecimal('specials_new_products_price')); ?></span>
        </td>
        <?php
        if (!\is_null($Qspecials->value('scheduled_date'))) {
          ?>
          <td class="text-center"><?php echo DateTime::toShort($Qspecials->value('scheduled_date')); ?></td>
          <?php
        } else {
          ?>
          <td class="text-center"></td>
          <?php
        }

        if (!\is_null($Qspecials->value('expires_date'))) {
          ?>
          <td class="text-center"><?php echo DateTime::toShort($Qspecials->value('expires_date')); ?></td>
          <?php
        } else {
          ?>
          <td class="text-center"></td>
          <?php
        }
        if ($Qspecials->valueInt('flash_discount') == 1) {
          ?>
          <td class="text-center"><i class="bi-check text-success"></i></td>
          <?php
        } else {
          ?>
          <td></td>

          <?php
        }
        if ($Qspecials->valueInt('products_archive') == 1) {
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
          if ($Qspecials->valueInt('status') == 1) {
            echo '<a href="' . $CLICSHOPPING_Specials->link('Specials&Specials&SetFlag&page=' . (int)$page . '&flag=0&id=' . (int)$Qspecials->valueInt('specials_id')) . '"><i class="bi-check text-success"></i></a>';
          } else {
            echo '<a href="' . $CLICSHOPPING_Specials->link('Specials&Specials&SetFlag&page=' . (int)$page . '&flag=1&id=' . (int)$Qspecials->valueInt('specials_id')) . '"><i class="bi bi-x text-danger"></i></a>';
          }
          ?>
        </td>
        <td class="text-end">
          <?php
          echo '<a href="' . $CLICSHOPPING_Specials->link('Edit&page=' . (int)$page . '&sID=' . (int)$Qspecials->valueInt('specials_id') . '&action=update') . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Specials->getDef('icon_edit') . '"></i></h4></a>';
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qspecials->getPageSetLabel($CLICSHOPPING_Specials->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qspecials->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>