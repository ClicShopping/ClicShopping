<?php

use ClicShopping\OM\CLICSHOPPING;

?>

<div class="<?php echo $text_position; ?> col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="row">
    <span class="col-md-8"><?php echo $date_reviews . '<br />' . $customer_review; ?></span>
    <span class="col-md-4 text-end productsReviewsListingContentRating"
          itemprop="ratingValue"><?php echo $review_star; ?></span>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <span class="col-md-10"><?php echo $review_text; ?></span>
    <?php
    if ($delete_comment == 'True') {
      ?>
      <span class="col-md-2 text-end"><?php echo $delete_reviews ?></span>
      <?php
    }
    ?>
  </div>
  <?php
  if (MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_SENTIMENT_TAG == 'True') {
    ?>
    <div class="row">
        <span class="col-md-10 productsReviewsListingContentTag">
          <?php
          echo CLICSHOPPING::getDef('modules_products_reviews_listing_content_text_sentiment') . ' ';
          foreach ($customer_tag as $value) {
            echo ' <span class="badge text-bg-primary">' . $value . '</span> ';
          }
          ?>
        </span>
    </div>
    <?php
  }
  ?>
  <div class="mt-1"></div>
  <div class="hr"></div>
  <div class="mt-1"></div>
</div>