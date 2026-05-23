<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="<?php echo $text_postion; ?> col-md-<?php echo $content_width; ?>">
  <div class="row">
    <div class="mt-1"></div>
    <div class="col-md-12 row">
      <span
        class="col-md-6 float-start col-md-7 productsReviewInfoContentCustomerProductsName"><h3><?php echo $products_name ?></h3></span>
      <span class="col-md-4 text-end productsReviewInfoContentCustomerRating">
         <?php echo $customer_rating; ?>
       </span>
      <span class="col-md-1 text-end productsReviewInfoContentCustomerDeleteReviews">
         <?php if ($delete_comment == 'True') { ?>
           <span><?php echo $delete_reviews; ?></span>
         <?php } ?>
       </span>
    </div>
    <div class="mt-1"></div>
    <div class="col-md-12 productsReviewInfoContentDateAdded">
      <?php echo CLICSHOPPING::getDef('modules_products_reviews_info_content_text_review_date_added', ['date_added' => $date_added]); ?>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="productsReviewInfoContentCustomerReviewBy">
    <h3><?php echo CLICSHOPPING::getDef('modules_products_reviews_info_content_text_review_text_review_by', ['customer_name' => $customer_name]); ?></h3>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <span class="col-md-11 productsReviewInfoContentCustomerText"><?php echo $customer_text; ?></span>
  </div>
  <div class="mt-1"></div>
  <?php
  if (MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_SENTIMENT_TAG == 'True') {
    ?>
    <div class="row">
      <span class="col-md-11 productsReviewInfoContentCustomerTag">
       <?php
       echo CLICSHOPPING::getDef('modules_products_reviews_info_content_text_customers_tag');

       foreach ($customer_tag as $value) {
         echo ' <span class="badge text-bg-primary">' . $value . '</span> ';
       }
       ?>
      </span>
    </div>
    <div class="mt-1"></div>
    <?php
  }
  ?>
</div>
