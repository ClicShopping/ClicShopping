<div class="col-md-<?php echo $content_width; ?>">
  <?php
  if (!empty($error_message)) {
    ?>
    <div class="headerError">
        <span class="headerError">
          <?php echo $error_message; ?>
        </span>
    </div>
    <?php
  }
  if (!empty($info_message)) {
    ?>
    <div class="headerInfo">
        <span class="headerInfo">
          <?php echo $info_message; ?>
        </span>
    </div>
    <?php
  }
  ?>
</div>