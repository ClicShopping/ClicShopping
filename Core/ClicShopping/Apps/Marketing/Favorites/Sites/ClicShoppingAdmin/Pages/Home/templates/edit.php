<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\ClicShoppingAdmin\HTMLOverrideAdmin;

$CLICSHOPPING_Favorites = Registry::get('Favorites');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Currencies = Registry::get('Currencies');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$languages = $CLICSHOPPING_Language->getLanguages();
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
          <?php
          $form_action = 'Insert';

          if (isset($_GET['sID'])) {
            $form_action = 'Update';
          }
          ?>
          <span class="col-md-9 text-end">
<?php
echo HTML::form('favorites', $CLICSHOPPING_Favorites->link('Favorites&' . $form_action));
if ($form_action == 'Update') echo HTML::hiddenField('products_favorites_id', $_GET['sID']) . HTML::hiddenField('page', $page);
echo HTML::button($CLICSHOPPING_Favorites->getDef('button_cancel'), null, $CLICSHOPPING_Favorites->link('Favorites&page=' . $page . (isset($_GET['sID']) ? '&sID=' . $_GET['sID'] : '')), 'warning', null, null) . '&nbsp;';
echo(($form_action == 'Insert') ? HTML::button($CLICSHOPPING_Favorites->getDef('button_insert'), null, null, 'success') : HTML::button($CLICSHOPPING_Favorites->getDef('button_update'), null, null, 'success'));
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  $form_action = 'Insert';

  if (isset($_GET['sID'])) {
    $form_action = 'Update';

    $Qproducts = $CLICSHOPPING_Favorites->db->prepare('select p.products_id,
                                                                pd.products_name,
                                                                s.customers_group_id,
                                                                p.products_price,
                                                                s.scheduled_date,
                                                                s.expires_date
                                                          from :table_products p,
                                                               :table_products_description pd,
                                                               :table_products_favorites s
                                                          where p.products_id = pd.products_id
                                                          and pd.language_id = :language_id
                                                          and p.products_id = s.products_id
                                                          and s.products_favorites_id = :products_favorites_id
                                                          ');

    $Qproducts->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $Qproducts->bindInt(':products_favorites_id', (int)$_GET['sID']);
    $Qproducts->execute();

    $product = $Qproducts->fetch();

    $sInfo = new ObjectInfo($Qproducts->toArray());

    if (!empty($sInfo->scheduled_date)) {
      $scheduled_date = DateTime::toShortWithoutFormat($sInfo->scheduled_date);
    } else {
      $scheduled_date = null;
    }

    if (!empty($sInfo->expires_date)) {
      $expires_date = DateTime::toShortWithoutFormat($sInfo->expires_date);
    } else {
      $expires_date = null;
    }
  } else {

    $sInfo = new ObjectInfo(array());

    $sInfo->products_name = null;
    $scheduled_date = null;
    $expires_date = null;

// create an array of products on special, which will be excluded from the pull down menu of products
// (when creating a new product on special)

    $products_favorites_array = [];

    $Qproducts = $CLICSHOPPING_Favorites->db->prepare('select p.products_id,
                                                                 ph.customers_group_id
                                                          from :table_products p,
                                                                :table_products_favorites ph
                                                          where ph.products_id = p.products_id
                                                          and p.products_status = 1
                                                          ');

    $Qproducts->execute();

    while ($Qproducts->fetch()) {
      $products_favorites_array[] = (int)$Qproducts->valueInt('products_id') . ":" . $Qproducts->valueInt('customers_group_id');
    }

    $input_groups = [];

    if (isset($_GET['sID']) && $sInfo->customers_group_id != 0) {

      $QcustomerGroupPrice = $CLICSHOPPING_Favorites->db->prepare('select customers_group_price
                                                                    from :table_products_groups
                                                                    where products_id = :products_id
                                                                    and customers_group_id =  :customers_group_id
                                                                  ');
      $QcustomerGroupPrice->bindInt(':products_id', $sInfo->products_id);
      $QcustomerGroupPrice->bindInt(':customers_group_id', $sInfo->customers_group_id);

      $QcustomerGroupPrice->execute();

      if ($customer_group_price === $QcustomerGroupPrice->fetch()) {
        $sInfo->products_price = $customer_group_price['customers_group_price'];
      }
    }
  }
  ?>

  <div id="productsFavoritesTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Favorites->getDef('tab_general') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- //#################################################################### //-->
        <!--          ONGLET Information General de la Promotion                    //-->
        <!-- //#################################################################### //-->

        <div class="mainTitle"><?php echo $CLICSHOPPING_Favorites->getDef('title_products_favorites_general'); ?></div>
        <div class="adminformTitle" id="tabContentFavorites">
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Favorites->getDef('text_products_favorites_groups'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Favorites->getDef('text_products_favorites_groups'); ?></label>
                <div class="col-md-5">
                  <?php
                  echo (isset($sInfo->products_name)) ? $sInfo->products_name . ' <small>(' . $CLICSHOPPING_Currencies->format($sInfo->products_price) . ')</small>' : HTMLOverrideAdmin::selectMenuProductsPullDown('products_id', null, $products_favorites_array);
                  echo HTML::hiddenField('products_price', (isset($sInfo->products_price) ? $sInfo->products_price : ''));

                  if (isset($_GET['sID'])) {
                    echo HTML::hiddenField('products_id', $sInfo->products_id);
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php
        //***********************************
        // extension
        //***********************************
        if (!isset($_GET['Udapte'])) {
          echo $CLICSHOPPING_Hooks->output('Favorites', 'PageTwitter', null, 'display');
        }

        echo $CLICSHOPPING_Hooks->output('Favorites', 'CustomerGroup', null, 'display');
        ?>
        <div class="mt-1"></div>
        <div class="mainTitle"><?php echo $CLICSHOPPING_Favorites->getDef('title_products_favorites_date'); ?></div>
        <div class="adminformTitle">

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Favorites->getDef('text_products_favorites_scheduled_date'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Favorites->getDef('text_products_favorites_scheduled_date'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('schdate', $scheduled_date, 'placeholder="' . $CLICSHOPPING_Favorites->getDef('text_products_favorites_scheduled_date') . '"', 'date'); ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Favorites->getDef('text_products_favorites_expires_date'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Favorites->getDef('text_products_favorites_expires_date'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('expdate', $expires_date, 'placeholder="' . $CLICSHOPPING_Favorites->getDef('text_products_favorites_expires_date') . '"', 'date'); ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-1"></div>
        <div class="alert alert-info" role="alert">
          <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Favorites->getDef('text_help_products_favorites') . '"></i></h4>'; ?></div>
          <div class="mt-1"></div>
          <div><?php echo $CLICSHOPPING_Favorites->getDef('text_help_products_favorites_price'); ?></div>
        </div>
      </div>
      <?php
      //***********************************
      // extension
      //***********************************
      echo $CLICSHOPPING_Hooks->output('Favorites', 'PageTab', null, 'display');
      ?>
    </div>
  </div>
  </form>
</div>