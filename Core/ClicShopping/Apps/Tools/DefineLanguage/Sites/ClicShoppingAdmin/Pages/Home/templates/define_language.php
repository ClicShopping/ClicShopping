<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_DefineLanguage = Registry::get('DefineLanguage');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

if (isset($_POST['search']) && !empty($_POST['search'])) {
  $search = HTML::sanitize($_POST['search']);
}

$languages = $CLICSHOPPING_Language->getLanguages();

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

if (!FileSystem::isWritable($CLICSHOPPING_Template->getDirectoryPathLanguage())) {
  ?>
  <div class="alert alert-warning"
       role="alert"><?php echo $CLICSHOPPING_DefineLanguage->getDef('error_language_directory_not_writeable'); ?></div>
  <?php
}
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/define_language.gif', $CLICSHOPPING_DefineLanguage->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-6 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_DefineLanguage->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
            <?php
            echo HTML::form('search_form', $CLICSHOPPING_DefineLanguage->link('DefineLanguage'), 'post', '', ['session_id' => true]) . HTML::inputField('search', null, 'placeholder="' . $CLICSHOPPING_DefineLanguage->getDef('text_search') . '"') . ' ';
            echo '&nbsp;';
            if (!isset($_POST['search'])) {
              echo '&nbsp;';
              echo ' ' . HTML::button($CLICSHOPPING_DefineLanguage->getDef('button_reset_all_languages'), null, $CLICSHOPPING_DefineLanguage->link('DefineLanguage&TableReset'), 'danger');
            }
            ?>
            </form>
          </span>
          <span class="col-md-1 text-end">
            <?php
            if (isset($_POST['search'])) {
              echo HTML::button($CLICSHOPPING_DefineLanguage->getDef('button_reset'), null, $CLICSHOPPING_DefineLanguage->link('DefineLanguage'), 'warning');
            }
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <table class="table table-hover">
    <thead>
    <tr class="dataTableHeadingRow">
      <th class=""><?php echo $CLICSHOPPING_DefineLanguage->getDef('table_heading_content_group_title'); ?></th>
      <th class=""></th>
      <th class="action"></th>
    </tr>
    </thead>
    <tbody>
    <?php
    if (isset($search)) {
      $Qcontent_group = $CLICSHOPPING_DefineLanguage->db->prepare("select distinct content_group
                                                                      from :table_languages_definitions
                                                                      where content_group like " . "'%" . $search . "%'" . " or definition_key like " . "'%" . $search . "%'" . " or definition_value like " . "'%" . $search . "%'" . "
                                                                     ");
    } else {
      $Qcontent_group = $CLICSHOPPING_DefineLanguage->db->prepare('select distinct content_group
                                                                    from :table_languages_definitions
                                                                   ');
    }

    $Qcontent_group->execute();

    while ($Qcontent_group->fetch()) {
      $search_count = '';

      if (isset($search)) {
        $Qcontents = $CLICSHOPPING_DefineLanguage->db->prepare("select languages_id,
                                                                         count(*) as count
                                                                 from :table_languages_definitions
                                                                 where content_group = :content_group
                                                                 and (definition_key like " . "'%" . $search . "%'" . " or definition_value like " . "'%" . $search . "%'" . ")
                                                                 group by languages_id
                                                               ");
        $Qcontents->bindValue(':content_group', $Qcontent_group->value('content_group'));
        $Qcontents->execute();
        do {
          if ($Qcontents->valueInt('count') > 0) {
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              if ($languages[$i]['id'] == $Qcontents->value('languages_id')) {
                $search_count .= ' [' . $languages[$i]['code'] . ':' . $Qcontents->valueInt('count') . ']';
              }
            }
          }
        } while ($Qcontents->fetch());
      }
      ?>
      <tr>
        <td><?php echo $Qcontent_group->value('content_group') . ($search_count > '' ? '<span class="text-info"><small><i>' . $search_count . '</i></small></span>' : ''); ?></td>
        <td class="action text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            echo HTML::link($CLICSHOPPING_DefineLanguage->link('ContentGroup=' . $Qcontent_group->value('content_group')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_DefineLanguage->getDef('icon_edit') . '"></i></h4>');
            echo '&nbsp;';
            if ($search_count > '') {
              echo HTML::link($CLICSHOPPING_DefineLanguage->link('ContentGroup=' . $Qcontent_group->value('content_group') . '&search=' . $search), '<h4><i class="bi bi-funnel" title="' . $CLICSHOPPING_DefineLanguage->getDef('image_filter') . '"></i></h4>');
            }
            ?>
          </div>
        </td>
      </tr>
      <?php
    }
    ?>
    </tbody>
  </table>
</div>
<div class="py-4"></div>