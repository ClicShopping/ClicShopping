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

$CLICSHOPPING_Featured = Registry::get('Featured');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Currencies = Registry::get('Currencies');
$CLICSHOPPING_Language = Registry::get('Language');

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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_featured.png', $CLICSHOPPING_Featured->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Featured->getDef('heading_title'); ?></span>
          <?php
          $form_action = 'Insert';

          if (isset($_GET['sID'])) {
            $form_action = 'Update';
          }
          ?>
          <span class="col-md-9 text-end">
<?php
echo HTML::form('products_featured', $CLICSHOPPING_Featured->link('Featured&' . $form_action));
if ($form_action == 'Update') echo HTML::hiddenField('products_featured_id', $_GET['sID']) . HTML::hiddenField('page', $page);
echo HTML::button($CLICSHOPPING_Featured->getDef('button_cancel'), null, $CLICSHOPPING_Featured->link('Featured&page=' . $page . (isset($_GET['sID']) ? '&sID=' . $_GET['sID'] : '')), 'warning', null, null) . '&nbsp;';
echo(($form_action == 'Insert') ? HTML::button($CLICSHOPPING_Featured->getDef('button_insert'), null, null, 'success') : HTML::button($CLICSHOPPING_Featured->getDef('button_update'), null, null, 'success'));
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

    $Qproducts = $CLICSHOPPING_Featured->db->prepare('select p.products_id,
                                                              pd.products_name,
                                                              s.customers_group_id,
                                                              p.products_price,
                                                              s.scheduled_date,
                                                              s.expires_date
                                                        from :table_products p,
                                                             :table_products_description pd,
                                                             :table_products_featured s
                                                        where p.products_id = pd.products_id
                                                        and pd.language_id = :language_id
                                                        and p.products_id = s.products_id
                                                        and s.products_featured_id = :products_featured_id
                                                        ');

    $Qproducts->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $Qproducts->bindInt(':products_featured_id', (int)$_GET['sID']);
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

    $products_featured_array = [];

    $Qproducts = $CLICSHOPPING_Featured->db->prepare('select p.products_id,
                                                               ph.customers_group_id
                                                        from :table_products p,
                                                              :table_products_featured ph
                                                        where ph.products_id = p.products_id
                                                        and p.products_status = 1
                                                        ');

    $Qproducts->execute();

    while ($Qproducts->fetch()) {
      $products_featured_array[] = (int)$Qproducts->valueInt('products_id') . ":" . $Qproducts->valueInt('customers_group_id');
    }

    $input_groups = [];

    if (isset($_GET['sID']) && $sInfo->customers_group_id != 0) {

      $QcustomerGroupPrice = $CLICSHOPPING_Featured->db->prepare('select customers_group_price
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

  <div id="productsFeaturedTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Featured->getDef('tab_general') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- //#################################################################### //-->
        <!--          ONGLET Information General de la Promotion                    //-->
        <!-- //#################################################################### //-->

        <div class="mainTitle"><?php echo $CLICSHOPPING_Featured->getDef('title_products_featured_general'); ?></div>
        <div class="adminformTitle" id="Information">
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Featured->getDef('text_products_featured_groups'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Featured->getDef('text_products_featured_groups'); ?></label>
                <div class="col-md-5">
                  <?php
                  echo (isset($sInfo->products_name)) ? $sInfo->products_name . ' <small>(' . $CLICSHOPPING_Currencies->format($sInfo->products_price) . ')</small>' : HTMLOverrideAdmin::selectMenuProductsPullDown('products_id', null, $products_featured_array);
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
          echo $CLICSHOPPING_Hooks->output('Featured', 'PageTwitter', null, 'display');
        }

        echo $CLICSHOPPING_Hooks->output('Featured', 'CustomerGroup', null, 'display');
        ?>
        <div class="mt-1"></div>
        <div class="mainTitle"><?php echo $CLICSHOPPING_Featured->getDef('title_products_featured_date'); ?></div>
        <div class="adminformTitle">

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Featured->getDef('text_products_featured_scheduled_date'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Featured->getDef('text_products_featured_scheduled_date'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('schdate', $scheduled_date, 'placeholder="' . $CLICSHOPPING_Featured->getDef('text_products_featured_scheduled_date') . '"', 'date'); ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Featured->getDef('text_products_featured_expires_date'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Featured->getDef('text_products_featured_expires_date'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('expdate', $expires_date, 'placeholder="' . $CLICSHOPPING_Featured->getDef('text_products_featured_expires_date') . '"', 'date'); ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-1"></div>
        <div class="alert alert-info" role="alert">
          <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Featured->getDef('title_help_products_featured_price') . '"></i></h4> ' . $CLICSHOPPING_Featured->getDef('title_help_products_featured_price') ?></div>
          <div class="mt-1"></div>
          <div><?php echo $CLICSHOPPING_Featured->getDef('text_help_products_featured_price'); ?></div>
        </div>
      </div>
      <?php
      //***********************************
      // extension
      //***********************************
      echo $CLICSHOPPING_Hooks->output('Featured', 'PageTab', null, 'display');
      ?>
    </div>
  </div>
  </form>
</div>