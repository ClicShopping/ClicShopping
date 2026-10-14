<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="<?php echo $text_position; ?> col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div
    class="col-md-12"><?php echo CLICSHOPPING::getDef('modules_products_reviews_write_rating_text_sub_title_rating'); ?></div>
  <div class="col-md-12">
    <fieldset class="rating">
      <input type="radio" id="star5" name="rating" value="5"/><label class="bi bi-star-fill" for="star5"
                                                                     title="Awesome - 5 stars"></label>
      <input type="radio" id="star4" name="rating" value="4"/><label class="bi bi-star-fill" for="star4"
                                                                     title="Pretty good - 4 stars"></label>
      <input type="radio" id="star3" name="rating" value="3"/><label class="bi bi-star-fill" for="star3"
                                                                     title="Meh - 3 stars"></label>
      <input type="radio" id="star2" name="rating" value="2"/><label class="bi bi-star-fill" for="star2"
                                                                     title="Kinda bad - 2 stars"></label>
      <input type="radio" id="star1" name="rating" value="1"/><label class="bi bi-star-fill" for="star1"
                                                                     title="Sucks big time - 1 star"></label>
    </fieldset>
  </div>
</div>