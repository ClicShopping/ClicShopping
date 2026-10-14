<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * @param $text
 * @return string
 */
function ht_datepicker_jquery_show_pages($text)
{
  return nl2br(implode("\n", explode(';', $text)));
}