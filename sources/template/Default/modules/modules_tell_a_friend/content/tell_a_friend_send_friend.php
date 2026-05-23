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
  <div class="modulesTellAFriendSendFriendPageHeader">
    <h3><?php echo CLICSHOPPING::getDef('modules_tell_a_friend_title_friend_details'); ?></h3></div>

  <div class="col-md-12">
    <div class="form-group row">
      <label for="inputFromName"
             class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('modules_tell_a_friend_field_friend_name'); ?></label>
      <div class="col-md-7">
        <?php echo $name; ?>
      </div>
    </div>
  </div>
  <div class="col-md-12">
    <div class="form-group row">
      <label for="inputFromName"
             class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('modules_tell_a_friend_field_friend_email'); ?></label>
      <div class="col-md-7">
        <?php echo $customer_email; ?>
      </div>
    </div>
  </div>
</div>