<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<section class="boxe_languages" id="boxe_languages">
  <div class="mt-1"></div>
  <div class="boxeBannerContentsLanguages"><?php echo $languages_banner; ?></div>
  <div class="card boxeContainerLanguages">
    <div class="card-header boxeHeadingLanguages">
      <span
        class="card-title boxeTitleLanguages"><?php echo CLICSHOPPING::getDef('module_boxes_languages_box_title'); ?></span>
    </div>
    <div class="card-body boxeContentArroundLanguages">
      <div class="mt-1"></div>
      <div class="card-text boxeContentsLanguages"><?php echo $languages_string; ?></div>
    </div>
    <div class="card-footer boxeBottomLanguages"></div>
  </div>
</section>