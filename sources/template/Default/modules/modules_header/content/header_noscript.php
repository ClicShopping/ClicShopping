<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="col-md-<?php echo $content_width; ?> modulesHeaderNoscript">
  <noscript>
    <div class="alert alert-warning" role="alert">
      <div class="modulesHeaderNoscriptInner text-center">
        <?php echo CLICSHOPPING::getDef('module_header_noscript_text'); ?>
      </div>
    </div>
  </noscript>
</div>
