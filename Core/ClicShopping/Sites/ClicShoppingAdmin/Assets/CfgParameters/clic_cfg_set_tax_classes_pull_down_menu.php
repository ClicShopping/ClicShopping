<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * @param $default
 * @param $key
 * @return string
 */
function clic_cfg_set_tax_classes_pull_down_menu($default, $key = null)
{
//    $CLICSHOPPING_Language = Registry::get('Language');
  $CLICSHOPPING_Db = Registry::get('Db');

  $name = (empty($key)) ? 'configuration_value' : 'configuration[' . $key . ']';

  $tax_class_array = array(array('id' => 0,
//                                   'text' => CLICSHOPPING::getDef('parameter_none')
    'text' => CLICSHOPPING::getDef('text_none')
  )
  );

  $Qclasses = $CLICSHOPPING_Db->query('select tax_class_id,
                                          tax_class_title
                                   from :table_tax_class
                                   order by tax_class_title
                                  ');
  $Qclasses->execute();

  while ($Qclasses->fetch()) {
    $tax_class_array[] = array('id' => $Qclasses->valueInt('tax_class_id'),
      'text' => $Qclasses->value('tax_class_title'));
  }

  return HTML::selectMenu($name, $tax_class_array, $default);
}