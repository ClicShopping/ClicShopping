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

use ClicShopping\Apps\Tools\EditDesign\Classes\Listing;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_EditDesign = Registry::get('EditDesign');

$action = $_GET['action'] ?? '';

$directory_selected = null;

if (isset($_POST['directory_html'])) $directory_selected = HTML::sanitize($_POST['directory_html']);
if (isset($_GET['directory_html'])) $directory_selected = HTML::sanitize($_GET['directory_html']);

$filename_selected = null;

if (isset($_POST['filename'])) $filename_selected = HTML::sanitize($_POST['filename']);
if (isset($_GET['filename'])) $filename_selected = HTML::sanitize($_GET['filename']);

$file = CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDynamicTemplateDirectory() . '/modules/' . $directory_selected . '/template_html/' . $filename_selected;

if (is_file($file)) {
  $code = file_get_contents($file);
} else {
  $code = null;
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
            <span
              class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/edit_html.png', $CLICSHOPPING_EditDesign->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_EditDesign->getDef('heading_title'); ?></span>
          <?php
          if (empty($action)) {
            $form_action = 'directory';
            ?>
            <span class="col-md-2 text-center">
                <?php
                echo HTML::form('directory', $CLICSHOPPING_EditDesign->link('EditListing&action=' . $form_action), 'post', 'enctype="multipart/form-data"');
                echo HTML::selectMenu('directory_html', Listing::getDirectoryTemplateProducts(), $directory_selected, 'onchange="this.form.submit();"');
                ?>
                </form>
              </span>
            <span
              class="col-md-5 text-end"><?php echo HTML::button($CLICSHOPPING_EditDesign->getDef('button_cancel'), null, $CLICSHOPPING_EditDesign->link('EditDesign'), 'danger'); ?></span>
            <?php
          } else {
            ?>
            <span class="col-md-2 text-center">
                 <?php
                 echo HTML::form('edit_file_html', $CLICSHOPPING_EditDesign->link('EditListing&action=directory'));
                 echo HTML::selectMenu('directory_html', Listing::getDirectoryTemplateProducts(), $directory_selected, 'onchange="this.form.submit();"');
                 echo '      ' . HTML::selectMenu('filename', Listing::getFilenameTemplateProducts(), $filename_selected, 'onchange="this.form.submit();"');
                 ?>
                </form>
              </span>
            <span class="col-md-5 text-end">
                <?php
                if (MODE_DEMO == 'False') {
                  echo HTML::form('areahtml', $CLICSHOPPING_EditDesign->link('EditDesign&UpdateListing'), 'post', 'enctype="multipart/form-data"');
                  echo HTML::button($CLICSHOPPING_EditDesign->getDef('button_update'), null, null, 'success') . ' ';
                }

                echo HTML::button($CLICSHOPPING_EditDesign->getDef('button_cancel'), null, $CLICSHOPPING_EditDesign->link('EditDesign'), 'danger');
                ?>
              </span>
            <?php
          }
          ?>


        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  if ($action == 'directory') {
    ?>
    <div>
      <?php
      echo HTML::hiddenField('directory_html', $directory_selected);
      echo HTML::hiddenField('filename', $filename_selected);
      echo HTML::textAreaField('code', $code, '', '', 'id="code"');
      ?>
    </div>
    <?php
  }
  ?>
  <div class="mt-1"></div>
  <div class="alert alert-info" role="alert">
    <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_EditDesign->getDef('title_help_edit_html') . '"></i></h4> ' . $CLICSHOPPING_EditDesign->getDef('title_help_edit_html') ?></div>
    <div class="mt-1"></div>
    <div><?php echo $CLICSHOPPING_EditDesign->getDef('text_help_edit_html'); ?></div>
  </div>
</div>