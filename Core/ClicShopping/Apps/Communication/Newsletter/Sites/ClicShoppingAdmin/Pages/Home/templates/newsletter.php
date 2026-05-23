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

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Newsletter = Registry::get('Newsletter');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$action = $_GET['action'] ?? '';
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/newsletters.gif', $CLICSHOPPING_Newsletter->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Newsletter->getDef('heading_title'); ?></span>
          <span
            class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_Newsletter->getDef('button_insert'), null, $CLICSHOPPING_Newsletter->link('Update'), 'success') . '&nbsp;'; ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <?php echo $CLICSHOPPING_Hooks->output('Stats', 'StatsCustomersNewsletterBySex', null, 'display'); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING                                                            -->
  <!-- //################################################################################################################ -->
  <?php
  echo HTML::form('delete_all', $CLICSHOPPING_Newsletter->link('Newsletter&DeleteAll&page=' . $page));
  ?>

  <div id="toolbar" class="float-end">
    <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Newsletter->getDef('button_delete'); ?></button>
  </div>

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-id-field="selected"
    data-select-item-name="selected[]"
    data-click-to-select="true"
    data-sort-order="asc"
    data-sort-name="selected"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-search="true">

  <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected" data-sortable="true" data-visible="false"
          data-switchable="false"><?php echo $CLICSHOPPING_Newsletter->getDef('id'); ?></th>
      <th data-switchable="false"></th>
      <th data-field="newletter"><?php echo $CLICSHOPPING_Newsletter->getDef('table_heading_newsletters'); ?></th>
      <th data-field="size"
          class="text-center"><?php echo $CLICSHOPPING_Newsletter->getDef('table_heading_size'); ?></th>
      <th data-field="module" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Newsletter->getDef('table_heading_module'); ?></th>
      <th data-field="language" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Newsletter->getDef('table_heading_language'); ?></th>
      <?php
      // Permettre l'affichage des groupes en mode B2B
      if (MODE_B2B_B2C == 'True') {
        ?>
        <th data-field="b2b" data-sortable="true"
            class="text-center"><?php echo $CLICSHOPPING_Newsletter->getDef('table_heading_b2b'); ?></th>
        <?PHP
      }
      ?>
      <th data-field="sent" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Newsletter->getDef('table_heading_sent'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Newsletter->getDef('table_heading_status'); ?></th>
      <th data-field="action" data-switchable="false" class="text-end"
          data-width="150"><?php echo $CLICSHOPPING_Newsletter->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qnewsletters = $CLICSHOPPING_Newsletter->db->prepare('select SQL_CALC_FOUND_ROWS newsletters_id,
                                                                                         title,
                                                                                         length(content) as content_length,
                                                                                         module,
                                                                                         date_added,
                                                                                         date_sent,
                                                                                         status,
                                                                                         languages_id,
                                                                                         customers_group_id,
                                                                                         locked,
                                                                                         newsletters_accept_file,
                                                                                         newsletters_twitter,
                                                                                         newsletters_customer_no_account
                                                            from :table_newsletters
                                                            order by date_added desc
                                                            limit :page_set_offset, :page_set_max_results
                                                            ');

    $Qnewsletters->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qnewsletters->execute();

    $listingTotalRow = $Qnewsletters->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qnewsletters->fetch()) {
        if ($Qnewsletters->valueInt('languages_id') != 0) {
          $QnewslettersLanguages = $CLICSHOPPING_Newsletter->db->prepare('select name
                                                                         from :table_languages
                                                                         where languages_id = :language_id
                                                                        ');
          $QnewslettersLanguages->bindInt(':language_id', $Qnewsletters->valueInt('languages_id'));
          $QnewslettersLanguages->execute();

          $newsletters_language = $QnewslettersLanguages->fetch();
        } else {
          $newsletters_language['name'] = $CLICSHOPPING_Newsletter->getDef('text_all_languages');
        }

        if ((!isset($_GET['nID']) || (isset($_GET['nID']) && ((int)$_GET['nID'] === $Qnewsletters->valueInt('newsletters_id')))) && !isset($nInfo)) {
          $nInfo = new ObjectInfo($Qnewsletters->toArray());
        }
        ?>
        <tr>
          <td></td>
          <td><?php echo $Qnewsletters->valueInt('newsletters_id'); ?></td>
          <td></td>
          <th
            scope="row"><?php echo '<a href="' . $CLICSHOPPING_Newsletter->link('Newsletter&Preview&page=' . $page . '&nID=' . $Qnewsletters->valueInt('newsletters_id')) . '">' . $Qnewsletters->value('title') . '</a>'; ?></th>
          <td class="text-center"><?php echo number_format($Qnewsletters->value('content_length')) . ' bytes'; ?></td>
          <td class="text-center"><?php echo $Qnewsletters->value('module'); ?></td>
          <td class="text-center"><?php echo $newsletters_language['name']; ?></td>
          <?php
          // Permettre l'affichage des groupes en mode B2B
          if (MODE_B2B_B2C == 'True') {
            $QcustomersGroup = $CLICSHOPPING_Newsletter->db->prepare('select customers_group_name
                                                                      from :table_customers_groups
                                                                      where customers_group_id = :customers_group_id
                                                                    ');
            $QcustomersGroup->bindInt(':customers_group_id', $Qnewsletters->valueInt('customers_group_id'));
            $QcustomersGroup->execute();

            $customer_group = $QcustomersGroup->value('customers_group_name');

            if (empty($customer_group)) {
              $customer_group = $CLICSHOPPING_Newsletter->getDef('text_all_customers');
            }

            ?>
            <td class="text-center"><?php echo $customer_group; ?></td>
            <?PHP
          }
          ?>
          <td class="text-center"><?php if ($Qnewsletters->valueInt('status') == 1) {
              echo '<i class="bi-check text-success"></i>';
            } else {
              echo '<i class="bi bi-x text-danger"></i>';
            } ?></td>
          <td class="text-center"><?php if ($Qnewsletters->valueInt('locked') > 0) {
              echo '<h4><i class="bi bi-lock" title="' . $CLICSHOPPING_Newsletter->getDef('icon_locked') . '"></i></h4>';
            } else {
              echo '<h4><i class="bi bi-unlock" title="' . $CLICSHOPPING_Newsletter->getDef('icon_unlocked') . '"></i></h4>';
            } ?>
          </td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              if ($Qnewsletters->valueInt('locked') > 0) {
                echo '<a href="' . $CLICSHOPPING_Newsletter->link('Update&page=' . $page . '&nID=' . $Qnewsletters->valueInt('newsletters_id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Newsletter->getDef('icon_edit') . '"></i></h4></a>&nbsp;';
              }
              echo '&nbsp;';
              echo '<a href="' . $CLICSHOPPING_Newsletter->link('Preview&page=' . $page . '&nID=' . $Qnewsletters->valueInt('newsletters_id')) . '"><h4><i class="bi bi-easel3" title="' . $CLICSHOPPING_Newsletter->getDef('icon_preview') . '"></i></h4></a>';
              echo '&nbsp;';

              if ($Qnewsletters->valueInt('locked') > 0) {
                echo '<a href="' . $CLICSHOPPING_Newsletter->link('Newsletter&Unlock&page=' . $page . '&nID=' . $Qnewsletters->valueInt('newsletters_id')) . '"><h4><i class="bi bi-unlock" title="' . $CLICSHOPPING_Newsletter->getDef('icon_unlocked') . '"></i></h4></a>';
              } else {
                echo '<a href="' . $CLICSHOPPING_Newsletter->link('Newsletter&Lock&page=' . $page . '&nID=' . $Qnewsletters->valueInt('newsletters_id')) . '"><h4><i class="bi bi-lock" title="' . $CLICSHOPPING_Newsletter->getDef('icon_locked') . '"></i></h4></a>';
              }
              echo '&nbsp;';
              if ($Qnewsletters->valueInt('locked') > 0) {
                echo '&nbsp;<a href="' . $CLICSHOPPING_Newsletter->link('Send&page=' . $page . '&nID=' . $Qnewsletters->valueInt('newsletters_id') . '&nlID=' . $Qnewsletters->valueInt('languages_id') . '&cgID=' . $Qnewsletters->valueInt('customers_group_id') . '&ac=' . $Qnewsletters->valueInt('newsletters_accept_file') . '&at=' . $Qnewsletters->valueInt('newsletters_twitter') . '&ana=' . $Qnewsletters->valueInt('newsletters_customer_no_account')) . '"><h4><i class="bi bi-send" title="' . $CLICSHOPPING_Newsletter->getDef('image_send') . '"></i></h4></a>';
              }
              echo '&nbsp;';
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
  </form><!-- end form delete all -->
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qnewsletters->getPageSetLabel($CLICSHOPPING_Newsletter->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qnewsletters->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>