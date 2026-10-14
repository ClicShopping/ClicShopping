<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * @param $values
 * @param $key
 * @return string
 */
function ht_datepicker_jquery_edit_pages($values, $key)
{
  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

  $select_array = $CLICSHOPPING_Template->getListCatalogFilesNotIncluded();
  sort($select_array);
  $values_array = explode(';', $values);

  $output = '';
  foreach ($select_array as $file) {
    $output .= HTML::checkboxField('ht_datepicker_jquery_file[]', $file, \in_array($file, $values_array)) . '&nbsp;' . HTML::outputProtected($file) . '<br />';
  }

  if (!empty($output)) {
    $output = '<br />' . substr($output, 0, -6);
  }

  $output .= HTML::hiddenField('configuration[' . $key . ']', '', 'id="htrn_files"');

  $output .= '<script>
                   function htrn_update_cfg_value() {
                    var htrn_selected_files = \'\';

                    if ($(\'input[name="ht_datepicker_jquery_file[]"]\').length > 0) {
                      $(\'input[name="ht_datepicker_jquery_file[]"]:checked\').each(function() {
                        htrn_selected_files += $(this).attr(\'value\') + \';\';
                      });

                      if (htrn_selected_files.length > 0) {
                        htrn_selected_files = htrn_selected_files.substring(0, htrn_selected_files.length - 1);
                      }
                    }

                    $(\'#htrn_files\').val(htrn_selected_files);
                  }

                  $(public function() {
                    htrn_update_cfg_value();

                    if ($(\'input[name="ht_datepicker_jquery_file[]"]\').length > 0) {
                      $(\'input[name="ht_datepicker_jquery_file[]"]\').change(function() {
                        htrn_update_cfg_value();
                      });
                    }
                  });
                  </script>';

  return $output;
}