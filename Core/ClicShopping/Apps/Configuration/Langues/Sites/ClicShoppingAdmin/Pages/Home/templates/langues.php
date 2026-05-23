<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Langues = Registry::get('Langues');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

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
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Langues->getDef('heading_title'); ?></span>
          <span
            class="col-md-7 text-end">
            <?php
              if (MODE_DEMO == 'False') {
                echo HTML::button($CLICSHOPPING_Langues->getDef('button_new'), null, $CLICSHOPPING_Langues->link('Insert&page=' . $page), 'success');
              }
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING                                                            -->
  <!-- //################################################################################################################ -->

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="status"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-search="true">

  <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="name"><?php echo $CLICSHOPPING_Langues->getDef('table_heading_language_name'); ?></th>
      <th data-field="code"><?php echo $CLICSHOPPING_Langues->getDef('table_heading_language_code'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Langues->getDef('table_heading_language_status'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Langues->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qlanguages = $CLICSHOPPING_Langues->db->prepare('select SQL_CALC_FOUND_ROWS languages_id,
                                                                                   name,
                                                                                   code,
                                                                                   image,
                                                                                   directory,
                                                                                   sort_order,
                                                                                   status,
                                                                                   locale
                                                        from :table_languages
                                                        order by sort_order
                                                        limit :page_set_offset, :page_set_max_results
                                                        ');

    $Qlanguages->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qlanguages->execute();

    $listingTotalRow = $Qlanguages->getPageSetTotalRows();

    if ($listingTotalRow > 0) {

      while ($Qlanguages->fetch()) {
        if ((!isset($_GET['lID']) || (isset($_GET['lID']) && ((int)$_GET['lID'] === $Qlanguages->valueInt('languages_id')))) && !isset($lInfo)) {
          $lInfo = new ObjectInfo($Qlanguages->toArray());
        }

        if (DEFAULT_LANGUAGE == $Qlanguages->value('code')) {
          echo '                <th scope="row"><strong>' . $Qlanguages->value('name') . ' (' . $CLICSHOPPING_Langues->getDef('text_default') . ')</strong></th>' . "\n";
        } else {
          echo '                <th scope="row">' . $Qlanguages->value('name') . '</th>' . "\n";
        }
        ?>
        <th scope="row"><?php echo $Qlanguages->value('code'); ?></th>
        <td class="text-center">
          <?php
          //pb when the english when the status is off
          //      if ($Qlanguages->valueInt('languages_id') != 1 && DEFAULT_LANGUAGE != $Qlanguages->value('code')) {
          if ($Qlanguages->valueInt('status') == 1) {
            if (DEFAULT_LANGUAGE != $Qlanguages->value('code')) {
              echo HTML::link($CLICSHOPPING_Langues->link('Langues&SetFlag&flag=0&page=' . $page . '&lid=' . $Qlanguages->valueInt('languages_id')), '<i class="bi-check text-success"></i>');
            } else {
              echo '<i class="bi-check text-success"></i>';
            }
          } else {
            echo HTML::link($CLICSHOPPING_Langues->link('Langues&SetFlag&flag=1&page=' . $page . '&lid=' . $Qlanguages->valueInt('languages_id')), '<i class="bi bi-x text-danger"></i>');
          }
          //      }
          ?>
        </td>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            echo HTML::link($CLICSHOPPING_Langues->link('Edit&page=' . $page . '&lID=' . $Qlanguages->valueInt('languages_id') . '&action=edit'), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Langues->getDef('icon_edit') . '"></i></h4>');
            echo '&nbsp;';

            if ($Qlanguages->valueInt('languages_id') > 1) {
              echo HTML::link($CLICSHOPPING_Langues->link('Delete&page=' . $page . '&lID=' . $Qlanguages->valueInt('languages_id') . '&action=delete'), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Langues->getDef('icon_delete') . '"></i></h4>');
            }
            ?>
          </div>
        </td>
        </tr>
        <?php
      }
    } // end $listingTotalRow
    ?>
    </tbody>
  </table>

  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qlanguages->getPageSetLabel($CLICSHOPPING_Langues->getDef('text_display_number_of_link')); ?></div>
        <div class="float-end text-end"> <?php echo $Qlanguages->getPageSetLinks(); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
  <div class="mt-1"></div>
  <div class="alert alert-info">
    <?php echo $CLICSHOPPING_Langues->getDef('text_help'); ?>
  </div>
</div>
