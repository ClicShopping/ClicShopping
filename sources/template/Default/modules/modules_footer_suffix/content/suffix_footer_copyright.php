<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="mt-1"></div>
<div class="hr"></div>
<div class="text-center footerSuffix">
  <div class="footerSuffixCopyright">
    <span
      class="footerSuffixCopyright"><?php echo CLICSHOPPING::getDef('modules_footer_suffix_copyright_text') . ' ' . $shop_owner_copyright; ?></span>
  </div>
  <div class="footerSuffixTrademark">
    <span
      class="footerSuffixTrademark"><?php echo $logo . ' ' . CLICSHOPPING::getDef('modules_footer_suffix_trademark_text') . ' ' . $clicshopping_copyright; ?></span>
  </div>
</div>