<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\ClicShoppingAdmin\CallUserFuncConfiguration;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Products = Registry::get('Products');
$CLICSHOPPING_Language = Registry::get('Language');

$supplier_inputs_string = '';
$languages = $CLICSHOPPING_Language->getLanguages();

$cKey = HTML::sanitize($_GET['cKey']);

$Qconfiguration = $CLICSHOPPING_Products->db->get('configuration', [
  'configuration_id',
  'configuration_title',
  'configuration_value',
  'use_function'
], [
  'configuration_key' => $cKey
],
  'sort_order'
);

while ($Qconfiguration->fetch()) {
  if ($Qconfiguration->hasValue('use_function') && !\is_null($Qconfiguration->value('use_function'))) {
    $use_function = $Qconfiguration->value('use_function');

    if (preg_match('/->/', $use_function)) {
      $class_method = explode('->', $use_function);

      if (!\is_object($class_method[0])) {
        include_once('Core/classes/' . $class_method[0] . '.php');
        ${$class_method[0]} = new $class_method[0]();
      }

      $cfgValue = CallUserFuncConfiguration::execute($class_method[1], $Qconfiguration->value('configuration_value'), $class_method[0]);

    } else {
      $cfgValue = CallUserFuncConfiguration::execute($use_function, $Qconfiguration->value('configuration_value'));
    }
  } else {
    $cfgValue = $Qconfiguration->value('configuration_value');
  }

  if ((!isset($_GET['cID']) || (isset($_GET['cID']) && ((int)$_GET['cID'] === $Qconfiguration->valueInt('configuration_id')))) && !isset($cInfo)) {

    $Qextra = $CLICSHOPPING_Products->db->get('configuration', [
      'configuration_key',
      'configuration_description',
      'date_added',
      'last_modified',
      'use_function',
      'set_function'
    ], [
        'configuration_id' => $Qconfiguration->valueInt('configuration_id')
      ]
    );

    $cInfo_array = array_merge($Qconfiguration->toArray(), $Qextra->toArray());
    $cInfo = new ObjectInfo($cInfo_array);
  }
}

if ($cInfo->set_function) {
  $value_field = CallUserFuncConfiguration::execute($cInfo->set_function, htmlspecialchars($cInfo->configuration_value), $cInfo->configuration_key);
} else {
  $value_field = HTML::inputField('configuration[' . $cInfo->configuration_key . ']', $cInfo->configuration_value);
}

echo HTML::form('ajaxform', $CLICSHOPPING_Products->link('ConfigurationPopUpFields&Save&cKey=' . $_GET['cKey']), 'post', 'id="ajaxform"');
?>
<div class="row">
  <div class="col-md-12">
    <div class="card card-block headerCard">
      <div class="row">
        <span
          class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/configuration_1.gif', 'configuration', '40', '40'); ?></span>
        <span
          class="col-md-8 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Products->getDef('heading_title'); ?></span>
        <span class="col-md-3">
            <div
              class="text-end">&nbsp;<?php echo HTML::button($CLICSHOPPING_Products->getDef('button_insert'), null, null, 'success', null, 'md', null, 'simple-post'); ?></div>
            <div id="simple-msg" class="text-end"></div>
          </span>
      </div>
    </div>
  </div>
</div>

<div style="padding:20px 10px 30px 10px; text-align:left;">
  <div style="font-weight: bold; font-size:12px;"><?php echo '&nbsp;' . $cInfo->configuration_title; ?></div>
  <div>&nbsp;</div>
  <div><?php echo $cInfo->configuration_description; ?></div>
  <div>&nbsp;</div>
  <div><?php echo $value_field; ?></div>
</div>

</form>

<script defer
        src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/bootstrap/ajax_form/bootstrap_ajax_form_fields_configuration.js'); ?>"></script>

<!-- footer //-->