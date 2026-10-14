<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

/**
 * Directory template with a drop down for all template
 *
 * @param string $value all_template
 * @return string configuration_value, $filename_array,  $template_directory, the directory name
 *
 */

function clic_cfg_set_all_template_directory_list_pull_down($value)
{
  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

  return $CLICSHOPPING_Template->getAllTemplate($value);
}