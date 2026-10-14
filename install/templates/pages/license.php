<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;

?>


<div class="card">
  <div class="card-header">
    <?php echo TEXT_TITLE_WELCOME; ?>
  </div>
  <div class="card-block">
    <p class="card-text">
    <form action="index.php" method="get">
      <?php echo HTML::selectMenu('language', $languages_array, $language, 'onChange="this.form.submit();"'); ?>
    </form>
    </p>
  </div>
</div>

<div class="mt-1"></div>
<p><?php echo TEXT_LICENCE; ?></p>

<div class="mt-1"></div>
<div class="card">
  <div class="card-header">
    License
  </div>
  <div class="card-block">
    <p class="card-text col-md-12">
      <?php include_once('license.txt'); ?>
    </p>
  </div>
</div>

<div class="mt-1"></div>
<?php echo HTML::form('form', 'verify.php'); ?>
<div class="col-md-12 text-end">
  <?php echo HTML::button(TEXT_ACCEPT_LICENCE, null, null, 'success'); ?>
</div>
</form>
