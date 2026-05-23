<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Db = Registry::get('Db');
$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
?>
<div class="<?php echo $text_position; ?> col-md-<?php echo $content_width; ?> baguettebox">

  <ul class="thumbnails gallery">
    <?php
    if (!empty($products_small_image)) {
      echo '<li>' . $ticker_pourcentage_discount . $ticker_image . '<a class="thumbnail" href="' . CLICSHOPPING::link($CLICSHOPPING_Template->getDirectoryTemplateImages() . $products_image_zoom) . '" title="' . $products_name . '">' . HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $products_image_zoom, $products_name, null, null, 'title="' . $products_name . '" alt="' . $products_name . '" hspace="5" vspace="5"') . '</a>';

      $Qpi = $CLICSHOPPING_Db->get('products_images', ['image', 'htmlcontent'], ['products_id' => $CLICSHOPPING_ProductsCommon->getID()], 'sort_order');
      $pi = $Qpi->fetchAll();

      $pi_total = \count($pi);

      if ($pi_total > 0) {
        ?>
        <div>
        <?php
        $pi_counter = 0;
        $pi_html = [];

        foreach ($pi as $image) {
          $pi_counter++;

          if (!empty($image['htmlcontent'])) {
            if ($this->getVideo($image['htmlcontent']) === true) {
              $pi_html[] = '<li class="image-additional"><a class="thumbnail popup-youtube" href="' . $image['htmlcontent'] . '"><img src="' . CLICSHOPPING::link($CLICSHOPPING_Template->getDirectoryTemplateImages() . $image['image']) . '" width="' . $thumbail_width . '" height="' . $thumbail_height . '" title="' . $products_name . '" alt="' . $products_name . '" id="piGalImg_' . $pi_counter . '"></a></li>';
              ?>
              </div>
              <?php
            } else {
              $pi_html[] = '<li class="image-additional"><a class="thumbnail" href="' . $image['htmlcontent'] . '"><img src="' . CLICSHOPPING::link($CLICSHOPPING_Template->getDirectoryTemplateImages() . $image['image']) . '" width="' . $thumbail_width . '" height="' . $thumbail_height . '" title="' . $products_name . '" alt="' . $products_name . '" id="piGalImg_' . $pi_counter . '"></a></li>';
            }
          } else {
            $pi_html[] = '<li class="image-additional"><a class="thumbnail" href="' . CLICSHOPPING::link($CLICSHOPPING_Template->getDirectoryTemplateImages() . $image['image']) . '" title="' . $products_name . '">' . HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $image['image'], $products_name, $thumbail_width, $thumbail_height, ' title="' . $products_name . '" alt="' . $products_name . '" hspace="5" vspace="5"  id="piGalImg_' . $pi_counter . '"', true) . '</a></li>';
          }
        }

        if (!empty($pi_html)) {
          echo '    ' . implode('', $pi_html) . '';
        }
      }
    }
    ?>
  </ul>
</div>
