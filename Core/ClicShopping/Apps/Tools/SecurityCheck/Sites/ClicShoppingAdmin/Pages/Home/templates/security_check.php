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

  $CLICSHOPPING_SecurityCheck = Registry::get('SecurityCheck');
  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');
  $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
  $CLICSHOPPING_Language = Registry::get('Language');

  $CLICSHOPPING_Language->loadDefinitions('security_check', null, null, 'Shop');

  $info = CLICSHOPPING::getSystemInformation();
  $server = parse_url(CLICSHOPPING::getConfig('http_server'));

  function sortSecmModules($a, $b)
  {
    return strcasecmp($a['title'], $b['title']);
  }

  $types = ['info', 'warning', 'error', 'danger'];

  $modules = [];

  if ($secdir = @dir(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/SecurityCheck/')) {
    while ($file = $secdir->read()) {
      if (!is_dir(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/SecurityCheck/' . $file)) {
        if (substr($file, strrpos($file, '.')) == '.php') {
          $class = 'securityCheck_' . substr($file, 0, strrpos($file, '.'));

          include(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/SecurityCheck/' . $file);
          $instance = new $class();

          $modules[] = [
            'title'    => isset($instance->title) ? $instance->title : substr($file, 0, strrpos($file, '.')),
            'class'    => $class,
            'code'     => substr($file, 0, strrpos($file, '.')),
            'instance' => $instance,
          ];
        }
      }
    }

    $secdir->close();
  }

  if ($extdir = @dir(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/SecurityCheck/extended/')) {
    while ($file = $extdir->read()) {
      if (!is_dir(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/SecurityCheck/extended/' . $file)) {

        if (substr($file, strrpos($file, '.')) == '.php') {
          $class = 'securityCheckExtended_' . substr($file, 0, strrpos($file, '.'));

          include(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/SecurityCheck/extended/' . $file);

          $instance = new $class();

          $modules[] = [
            'title'    => isset($instance->title) ? $instance->title : substr($file, 0, strrpos($file, '.')),
            'class'    => $class,
            'code'     => substr($file, 0, strrpos($file, '.')),
            'instance' => $instance,
          ];
        }
      }
    }

    $extdir->close();
  }

  usort($modules, 'sortSecmModules');
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/cybermarketing.gif', $CLICSHOPPING_SecurityCheck->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-5 pageHeading"><?php echo $CLICSHOPPING_SecurityCheck->getDef('heading_title'); ?></span>
          <span
            class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_SecurityCheck->getDef('button_reset'), null, $CLICSHOPPING_SecurityCheck->link('SecurityCheck'), 'warning'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <table class="table table-sm table-hover">
    <thead>
    <tr class="dataTableHeadingRow">
      <th width="20">&nbsp</th>
      <th><?php echo $CLICSHOPPING_SecurityCheck->getDef('table_heading_title'); ?></th>
      <th><?php echo $CLICSHOPPING_SecurityCheck->getDef('table_heading_module'); ?></th>
      <th><?php echo $CLICSHOPPING_SecurityCheck->getDef('table_heading_info'); ?></th>
    </tr>
    </thead>
    <tbody>

    <?php
      foreach ($modules as $module) {
        $secCheck = $module['instance'];

        if (!\in_array($secCheck->type, $types)) {
          $secCheck->type = 'info';
        }

        $output = '';

        if ($secCheck->pass()) {
          $secCheck->type = 'success';
        } else {
          $output = $secCheck->getMessage();
        }

        echo '  <tr class="text-' . $secCheck->type . '">' . "\n" .
          '    <td class="text-center text-' . $secCheck->type .'">' . $secCheck->type . '</td>' . "\n" .
          '    <td vertical-align="top" style="white-space: nowrap;">' . HTML::outputProtected($module['title']) . '</td>' . "\n" .
          '    <td>' . HTML::outputProtected($module['code']) . '</td>' . "\n" .
          '    <td>' . $output . '</td>' . "\n" .
          '  </tr>' . "\n";
      }
    ?>
    <tbody>
  </table>
</div>
<div class="py-2"></div>