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

$CLICSHOPPING_Specials = Registry::get('Specials');
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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/specials.gif', $CLICSHOPPING_Specials->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Specials->getDef('heading_title'); ?></span>
          <?php
          $form_action = 'Insert';

          if (isset($_GET['sID'])) {
            $form_action = 'Update';
          }
          ?>
          <span class="col-md-9 text-end">
<?php
echo HTML::form('products_specials', $CLICSHOPPING_Specials->link('Specials&' . $form_action));
if ($form_action == 'Update') echo HTML::hiddenField('specials_id', $_GET['sID']) . HTML::hiddenField('page', $page);
echo HTML::button($CLICSHOPPING_Specials->getDef('button_cancel'), null, $CLICSHOPPING_Specials->link('Specials&page=' . $page . (isset($_GET['sID']) ? '&sID=' . $_GET['sID'] : '')), 'warning', null, null) . '&nbsp;';
echo(($form_action == 'Insert') ? HTML::button($CLICSHOPPING_Specials->getDef('button_insert'), null, null, 'success') : HTML::button($CLICSHOPPING_Specials->getDef('button_update'), null, null, 'success'));
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

    $Qproducts = $CLICSHOPPING_Specials->db->prepare('select p.products_id,
                                                                pd.products_name,
                                                                s.customers_group_id,
                                                                p.products_price,
                                                                s.specials_new_products_price,
                                                                s.scheduled_date,
                                                                s.expires_date,
                                                                s.flash_discount
                                                         from :table_products p,
                                                              :table_products_description pd,
                                                              :table_specials s
                                                         where p.products_id = pd.products_id
                                                         and pd.language_id = :language_id
                                                         and p.products_id = s.products_id
                                                         and s.specials_id = :specials_id
                                                        ');

    $Qproducts->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $Qproducts->bindInt(':specials_id', (int)$_GET['sID']);
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
    $sInfo->flash_discount = '';
    $scheduled_date = null;
    $expires_date = null;

// create an array of products on special, which will be excluded from the pull down menu of products
// (when creating a new product on special)

    $specials_array = [];

    $Qspecials = $CLICSHOPPING_Specials->db->prepare('select p.products_id,
                                                               s.customers_group_id
                                                       from :table_products p,
                                                            :table_specials s
                                                       where s.products_id = p.products_id
                                                       and p.products_status = 1
                                                    ');

    $Qspecials->execute();

    while ($Qspecials->fetch()) {
      $specials_array[] = (int)$Qspecials->valueInt('products_id') . ":" . $Qspecials->valueInt('customers_group_id');
    }

    $input_groups = [];

    if (isset($_GET['sID']) && $sInfo->customers_group_id != 0) {
      $QcustomerGroupPrice = $CLICSHOPPING_Specials->db->prepare('select customers_group_price
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

  <div id="specialsTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Specials->getDef('tab_general') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- //#################################################################### //-->
        <!--          ONGLET Information General de la Promotion                    //-->
        <!-- //#################################################################### //-->

        <div class="mainTitle"><?php echo $CLICSHOPPING_Specials->getDef('title_specials_general'); ?></div>
        <div class="adminformTitle" id="tabContentSpecials">
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Specials->getDef('text_specials_product'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Specials->getDef('text_specials_product'); ?></label>
                <div class="col-md-5">
                  <?php
                  echo (isset($sInfo->products_name)) ? $sInfo->products_name . ' <small>(' . $CLICSHOPPING_Currencies->format($sInfo->products_price) . ')</small>' : HTMLOverrideAdmin::selectMenuProductsPullDown('products_id', null, $specials_array);
                  echo HTML::hiddenField('products_price', (isset($sInfo->products_price) ? $sInfo->products_price : ''));

                  if (isset($_GET['sID'])) {
                    echo HTML::hiddenField('products_id', $sInfo->products_id);
                  }
                  ?>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Specials->getDef('text_specials_price'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Specials->getDef('text_specials_price'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('specials_price', (isset($sInfo->specials_new_products_price) ? $sInfo->specials_new_products_price : ''), 'required aria-required="true" id="specials_title"'); ?>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Specials->getDef('text_specials_flash_discount'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Specials->getDef('text_specials_flash_discount'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::checkboxField('flash_discount', '1', (isset($sInfo->flash_discount) ? $sInfo->flash_discount : '')); ?>
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
          echo $CLICSHOPPING_Hooks->output('Specials', 'PageTwitter', null, 'display');
        }

        echo $CLICSHOPPING_Hooks->output('Specials', 'CustomerGroup', null, 'display');
        ?>
        <div class="mt-1"></div>
        <div class="mainTitle"><?php echo $CLICSHOPPING_Specials->getDef('title_specials_date'); ?></div>
        <div class="adminformTitle">

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Specials->getDef('text_specials_start_date'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Specials->getDef('text_specials_start_date'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('schdate', $scheduled_date, 'placeholder="' . $CLICSHOPPING_Specials->getDef('text_specials_start_date') . '"', 'date'); ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Specials->getDef('text_specials_expires_date'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Specials->getDef('text_specials_expires_date'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('expdate', $expires_date, 'placeholder="' . $CLICSHOPPING_Specials->getDef('text_specials_expires_date') . '"', 'date'); ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-1"></div>
        <div class="alert alert-info" role="alert">
          <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Specials->getDef('title_help_products_specials_price') . '"></i></h4> ' . $CLICSHOPPING_Specials->getDef('title_help_products_specials_price') ?></div>
          <div class="mt-1"></div>
          <div><?php echo $CLICSHOPPING_Specials->getDef('text_help_specials_price'); ?></div>
        </div>
      </div>
      <?php
      //***********************************
      // extension
      //***********************************
      echo $CLICSHOPPING_Hooks->output('Special', 'PageTab', null, 'display');
      ?>
    </div>
  </div>
  </form>
</div>