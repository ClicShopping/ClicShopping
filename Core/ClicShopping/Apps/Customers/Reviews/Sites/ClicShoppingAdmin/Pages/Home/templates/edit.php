<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\DateTime;
  use ClicShopping\OM\Hash;
  use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Reviews = Registry::get('Reviews');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Language = Registry::get('Language');

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

if (isset($_GET['rID'])) $rID = HTML::sanitize($_GET['rID']);

$Qreviews = $CLICSHOPPING_Reviews->db->prepare('select r.reviews_id,
                                                          r.products_id,
                                                          r.customers_name,
                                                          r.date_added,
                                                          r.last_modified,
                                                          r.reviews_read,
                                                          r.status,
                                                          r.reviews_rating,
                                                          r.customers_tag,
                                                          rd.reviews_text,
                                                          rd.languages_id
                                                   from :table_reviews r,
                                                        :table_reviews_description rd
                                                   where r.reviews_id = :reviews_id
                                                   and r.reviews_id = rd.reviews_id
                                                  ');
$Qreviews->bindValue(':reviews_id', (int)$rID);
$Qreviews->execute();

$Qproducts = $CLICSHOPPING_Reviews->db->prepare('select products_image
                                                   from :table_products
                                                   where products_id = :products_id
                                                  ');
$Qproducts->bindValue(':products_id', $Qreviews->valueInt('products_id'));
$Qproducts->execute();

$QproductsName = $CLICSHOPPING_Reviews->db->prepare('select products_name
                                                       from :table_products_description
                                                       where products_id = :products_id
                                                       and language_id = :language_id
                                                      ');
$QproductsName->bindValue(':products_id', $Qreviews->valueInt('products_id'));
$QproductsName->bindValue(':language_id', $CLICSHOPPING_Language->getId());
$QproductsName->execute();

$rInfo_array = array_merge($Qreviews->toArray(), $Qproducts->toArray(), $QproductsName->toArray());
$rInfo = new ObjectInfo($rInfo_array);

//creation du tableau pour le  dropdown des status des commentaires
$status_array = array(array('id' => '1', 'text' => $CLICSHOPPING_Reviews->getDef('entry_status_yes')),
  array('id' => '0', 'text' => $CLICSHOPPING_Reviews->getDef('entry_status_no'))
);

echo HTML::form('update', $CLICSHOPPING_Reviews->link('Reviews&Update&page=' . $page . '&rID=' . $_GET['rID']), 'post', 'enctype="multipart/form-data"');
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/reviews.gif', $CLICSHOPPING_Reviews->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Reviews->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end">
            <?php
            echo HTML::button($CLICSHOPPING_Reviews->getDef('button_cancel'), null, $CLICSHOPPING_Reviews->link('Reviews&page=' . $page . '&rID=' . $rInfo->reviews_id), 'warning') . '&nbsp;';
            echo HTML::hiddenField('language_id', $rInfo->languages_id);
            echo HTML::button($CLICSHOPPING_Reviews->getDef('button_update'), null, null, 'success');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div id="reviewsTab">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Reviews->getDef('tab_general'); ?></a></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- //################################################################################################################ -->
        <!--          ONGLET Information General sur l'avis client          //-->
        <!-- //################################################################################################################ -->
        <div class="col-md-12 mainTitle">
          <div class="float-start"><?php echo $CLICSHOPPING_Reviews->getDef('title_reviews_general'); ?></div>
        </div>
        <div class="adminformTitle">
          <div>&nbsp;</div>
          <div class="row">
            <div class="col-md-8">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Reviews->getDef('entry_products'); ?>"
                       class="col-3 col-form-label"><?php echo $CLICSHOPPING_Reviews->getDef('entry_products'); ?></label>
                <div class="col-md-5">
                  <?php echo '<strong>' . $rInfo->products_name . '</strong>'; ?>
                </div>
              </div>
            </div>
            <span class="col-md-4 text-center float-end">
              <div class="adminformAide text-center">
                <td><?php echo HTML::image($CLICSHOPPING_Template->getDirectoryShopTemplateImages() . $rInfo->products_image, $rInfo->products_name, (int)SMALL_IMAGE_WIDTH, (int)SMALL_IMAGE_HEIGHT, 'hspace="5" vspace="5"'); ?></td>
              </div>
            </span>
          </div>

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Reviews->getDef('customers_name'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Reviews->getDef('customers_name'); ?></label>
                <div class="col-md-5">
                  <?php echo '<strong>' . HTML::outputProtected(Hash::displayDecryptedDataText($rInfo->customers_name)) . '</strong>'; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Reviews->getDef('entry_date'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Reviews->getDef('entry_date'); ?></label>
                <div class="col-md-5">
                  <?php echo '<strong>' . DateTime::toLong($rInfo->date_added);
                  '</strong>'; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Reviews->getDef('entry_status'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Reviews->getDef('entry_status'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::selectMenu('status', $status_array, (($rInfo->status == '1') ? '1' : '0')); ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- //################################################################################################################ -->
        <!--          avis client          //-->
        <!-- //################################################################################################################ -->
        <div>&nbsp;</div>
        <div class="col-md-12 mainTitle">
          <div class="float-start"><?php echo $CLICSHOPPING_Reviews->getDef('title_reviews_entry'); ?></div>
        </div>
        <div class="adminformTitle">
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Reviews->getDef('entry_review'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Reviews->getDef('entry_review'); ?></label>
                <div class="col-md-7">
                  <?php echo HTML::textAreaField('reviews_text', $rInfo->reviews_text, '80', '10'); ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- //################################################################################################################ -->
        <!--         Evaluation client          //-->
        <!-- //################################################################################################################ -->
        <div>&nbsp;</div>
        <div class="col-md-12 mainTitle">
          <div class="float-start"><?php echo $CLICSHOPPING_Reviews->getDef('title_reviews_rating'); ?></div>
        </div>
        <div class="adminformTitle" id="rating">
          <div class="row">
            <div class="col-md-12">
              <span class="col-md-2"><?php echo $CLICSHOPPING_Reviews->getDef('entry_rating'); ?></span>
              <span class="col-md-10">
                <?php echo '<i>' . HTML::stars($rInfo->reviews_rating) . '</i>'; ?>
              </span>
            </div>
          </div>
          <div class="row" id="custimer_sentiment">
            <div class="col-md-12">
              <span class="col-md-2"><?php echo $CLICSHOPPING_Reviews->getDef('entry_customer_sentiment_tag'); ?></span>
              <?php
              $customer_tag = $rInfo->customers_tag;
              $customer_tag = explode(',', $customer_tag);

              if (\count($customer_tag) > 0) {
                foreach ($customer_tag as $value) {
                  ?>
                  <span class="badge text-bg-primary"><?php echo ' <i>' . $value . '</i>'; ?></span>
                  <?php
                }
              }
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
echo HTML::hiddenField('reviews_id', $rInfo->reviews_id) . HTML::hiddenField('products_id', $rInfo->products_id) .
  HTML::hiddenField('customers_name', $rInfo->customers_name) .
  HTML::hiddenField('products_name', $rInfo->products_name) .
  HTML::hiddenField('products_image', $rInfo->products_image) .
  HTML::hiddenField('date_added', $rInfo->date_added) .
  HTML::hiddenField('languages_id', $rInfo->languages_id);
?>
</td>
</form>


