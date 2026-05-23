<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_Reviews = Registry::get('Reviews');

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
?>
<section class="reviews" id="reviews">
  <div class="contentContainer">
    <div class="contentText">
      <?php
      if ($CLICSHOPPING_Reviews->getTotalReviews() == 0) {
        ?>
        <div class="mt-1"></div>
        <div class="alert alert-info" role="alert"><?php echo CLICSHOPPING::getDef('text_no_reviews'); ?></div>
        <?php
      } else {
        echo $CLICSHOPPING_Template->getBlocks('modules_products_reviews');
      }
      ?>
    </div>
  </div>
</section>
