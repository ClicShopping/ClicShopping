<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

$CLICSHOPPING_LoggerAdmin = Registry::get('LoggerAdmin');

if (DISPLAY_PAGE_PARSE_TIME == 'true') {
  if (!\is_object($CLICSHOPPING_LoggerAdmin)) {
    $CLICSHOPPING_LoggerAdmin = Registry::get('LoggerAdmin');
  }

  echo '<div class="row">';
  echo '<div class="col-md-12 alert alert-info">';
  echo $CLICSHOPPING_LoggerAdmin->timerStop(DISPLAY_PAGE_PARSE_TIME);
  echo '</div>';
  echo '</div>';
  echo '<div class="mt-1"></div>';
}
