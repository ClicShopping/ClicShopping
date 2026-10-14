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

$CLICSHOPPING_Langues = Registry::get('Langues');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$id = HTML::sanitize($_GET['lID']);

$Qlanguages = $CLICSHOPPING_Langues->db->prepare('select languages_id,
                                                           name,
                                                           code,
                                                           image,
                                                           directory,
                                                           sort_order,
                                                           status,
                                                           locale
                                                    from :table_languages
                                                    where languages_id = :languages_id
                                                    ');
$Qlanguages->bindInt(':languages_id', (int)$id);

$Qlanguages->execute();

$lInfo = new ObjectInfo($Qlanguages->toArray());

$icons = [];

foreach (glob(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/third_party/flag-icon-css/flags/4x3/*.svg') as $file) {
  $code = basename($file, '.svg');

  $icons[] = ['id' => $code,
    'text' => $code
  ];
}

$directories = [];

foreach (glob(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/languages/*', GLOB_ONLYDIR) as $dir) {
  $code = basename($dir);

  $directories[] = ['id' => $code,
    'text' => $code
  ];
}

foreach (glob(CLICSHOPPING::getConfig('dir_root', 'ClicShoppingAdmin') . 'Core/languages/*', GLOB_ONLYDIR) as $dir) {
  $code = basename($dir);

  if (array_search($code, array_column($directories, 'id')) === false) {
    $directories[] = ['id' => $code,
      'text' => $code
    ];
  }
}

uasort($directories, function ($a, $b) {
  if ($a['id'] == $b['id']) {
    return 0;
  }

  return ($a['id'] < $b['id']) ? -1 : 1;
});

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/languages.gif', $CLICSHOPPING_Langues->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Langues->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Langues->getDef('text_info_heading_edit_language'); ?></strong></div>
  <?php echo HTML::form('languages', $CLICSHOPPING_Langues->link('Langues&Save&page=' . $page . '&lID=' . $lInfo->languages_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Langues->getDef('text_info_edit_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Langues->getDef('text_info_edit_intro'); ?></label>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Langues->getDef('text_info_language_name'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Langues->getDef('text_info_language_name'); ?></label>
          <div class="col-md-5">
            <?php
            if ($id == 1) {
              echo HTML::inputField('name', $lInfo->name, 'readonly required aria-required="true"');
            } else {
              echo HTML::inputField('name', $lInfo->name, 'required aria-required="true"');
            }
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Langues->getDef('text_info_language_code'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Langues->getDef('text_info_language_code'); ?></label>
          <div class="col-md-5">
            <?php
            if ($id == 1) {
              echo HTML::inputField('code', $lInfo->code, 'readonly required aria-required="true"');
            } else {
              echo HTML::inputField('code', $lInfo->code, 'required aria-required="true"');
            }
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Langues->getDef('text_info_language_image'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Langues->getDef('text_info_language_image'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::selectField('image', $icons, $lInfo->image); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Langues->getDef('text_info_language_directory'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Langues->getDef('text_info_language_directory'); ?></label>
          <div class="col-md-5">
            <?php
            if ($id == 1) {
              echo HTML::inputField('directory', $lInfo->directory, 'readonly required aria-required="true"');
            } else {
              echo HTML::selectField('directory', $directories, $lInfo->directory);
            }
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Langues->getDef('text_info_language_locale'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Langues->getDef('text_info_language_locale'); ?></label>
          <div class="col-md-5">
            <?php
            if ($id == 1) {
              echo HTML::inputField('locale', $lInfo->locale, 'readonly required aria-required="true"');
            } else {
              echo HTML::inputField('locale', $lInfo->locale, 'placeholder="' . $CLICSHOPPING_Langues->getDef('text_locale') . '" required aria-required="true"');
            }
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Langues->getDef('text_info_language_sort_order'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Langues->getDef('text_info_language_sort_order'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('sort_order', $lInfo->sort_order); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <?php
      if (DEFAULT_LANGUAGE != $lInfo->code) {
        ?>
        <div class="col-md-12">
          <span class="col-md-5"></span>
          <ul class="list-group-slider list-group-flush">
            <span class="text-slider"><?php echo $CLICSHOPPING_Langues->getDef('text_set_default'); ?></span>
            <li class="list-group-item-slider">
              <label class="switch">
                <?php echo HTML::checkboxField('default', null, null, 'class="success"'); ?>
                <span class="slider"></span>
              </label>
            </li>
          </ul>
        </div>
        <?php
      }
      ?>
      <div class="mt-1"></div>
      <div class="col-md-12 text-center">
        <?php
          if (MODE_DEMO == 'False') {
            echo '<span>' . HTML::button($CLICSHOPPING_Langues->getDef('button_update'), null, null, 'primary', null, 'sm') . ' </span>';
          }

          echo '<span>' . HTML::button($CLICSHOPPING_Langues->getDef('button_cancel'), null, $CLICSHOPPING_Langues->link('Langues&page=' . $page . '&lID=' . $lInfo->languages_id), 'warning', null, 'sm') .'</span>'; ?>
      </div>
    </div>
  </div>
  </form>
</div>