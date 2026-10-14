<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="modulesTellAFriendMessagePageHeader">
    <h3><?php echo CLICSHOPPING::getDef('modules_tell_a_friend_message_title_friend_message'); ?></h3>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-11"><?php echo $message ?></div>
</div>