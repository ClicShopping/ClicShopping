<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
if (\defined('STORE_PAGE_PARSE_TIME') && STORE_PAGE_PARSE_TIME == 'true') {
  $time_start = explode(' ', PAGE_PARSE_START_TIME);
  $time_end = explode(' ', microtime());
  $parse_time = number_format(($time_end[1] + $time_end[0] - ($time_start[1] + $time_start[0])), 3);

  error_log(date(STORE_PARSE_DATE_TIME_FORMAT) . ' - ' . $_SERVER['REQUEST_URI'] . ' (' . $parse_time . 's)' . "\n", 3, STORE_PAGE_PARSE_TIME_LOG);
}

if (\defined('DISPLAY_PAGE_PARSE_TIME') && DISPLAY_PAGE_PARSE_TIME == 'true') {
  $time_start = explode(' ', PAGE_PARSE_START_TIME);
  $time_end = explode(' ', microtime());
  $parse_time = number_format(($time_end[1] + $time_end[0] - ($time_start[1] + $time_start[0])), 3);

  echo '<span class="smallText">Parse Time: ' . $parse_time . 's</span>';
}
