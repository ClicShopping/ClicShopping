<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
            <?php
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_rag_websearch_dashboard'), null, $CLICSHOPPING_ChatGpt->link('DashBoard'), 'success') . ' ';
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_insert_rag_websearch'), null, $CLICSHOPPING_ChatGpt->link('RagWebSearchEdit&page=' . $page), 'danger') . ' ';
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back'), null, $CLICSHOPPING_ChatGpt->link('ChatGpt&ChatGpt'), 'primary') . '&nbsp;';
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-1"></div>
<!-- //################################################################################################################ -->
<!-- //                                             LISTING                                                                      -->
<!-- //################################################################################################################ -->

    <?php echo HTML::form('delete_all', $CLICSHOPPING_ChatGpt->link('RagWebSearch&DeleteAll&page=' . $page)); ?>

    <div id="toolbar" class="float-end">
      <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_ChatGpt->getDef('button_delete'); ?></button>
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
      data-sort-name="name"
      data-toolbar="#toolbar"
      data-buttons-class="primary"
      data-show-toggle="true"
      data-show-columns="true"
      data-mobile-responsive="true"
      data-check-on-init="true"
     >

      <thead class="dataTableHeadingRow">
      <tr>
        <th data-checkbox="true" data-field="state"></th>
        <th data-field="selected" data-sortable="true" data-visible="false"
            data-switchable="false"><?php echo $CLICSHOPPING_ChatGpt->getDef('id'); ?></th>
        <th data-field="site_domain"
            data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_site_domain'); ?></th>
        <th data-field="authority_score" data-sortable="true"
            class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_authority_score'); ?></th>
        <th data-field="status" data-sortable="true"
            class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_status'); ?></th>
        <th data-field="code2" data-sortable="true"
            class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_description'); ?></th>
        <th data-field="action" data-switchable="false"
            class="text-end"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_action'); ?>&nbsp;
        </th>
      </tr>
      </thead>
      <tbody>
      <?php
      $QragWebSearch = $CLICSHOPPING_ChatGpt->db->prepare('select SQL_CALC_FOUND_ROWS id,
                                                                                       site_domain,
                                                                                       authority_score,
                                                                                       status,
                                                                                       description
                                                            from :table_rag_websearch
                                                            order by id
                                                            limit :page_set_offset, :page_set_max_results
                                                            ');

      $QragWebSearch->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $QragWebSearch->execute();

      $listingTotalRow = $QragWebSearch->getPageSetTotalRows();

      if ($listingTotalRow > 0) {
        while ($QragWebSearch->fetch()) {
      ?>
      <tr>
        <td></td>
        <td><?php echo $QragWebSearch->valueInt('id'); ?></td>
        <td><?php echo $QragWebSearch->value('site_domain'); ?></td>
        <td><?php echo $QragWebSearch->valueDecimal('authority_score'); ?></td>
         <td class="text-start"><?php echo $QragWebSearch->value('description'); ?></td>
        <td class="text-center">
          <?php
          if ($QragWebSearch->valueInt('status') == 1) {
            echo HTML::link($CLICSHOPPING_ChatGpt->link('RagWebSearch&SetFlag&flag=0&cID=' . $QragWebSearch->valueInt('id') . '&page=' . $page), '<i class="bi-check text-success"></i>');
          } else {
            echo HTML::link($CLICSHOPPING_ChatGpt->link('RagWebSearch&SetFlag&flag=1&cID=' . $QragWebSearch->valueInt('id') . '&page=' . $page), '<i class="bi bi-x text-danger"></i>');
          }
          ?>
        </td>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            echo HTML::link($CLICSHOPPING_ChatGpt->link('RagWebSearchEdit&page=' . $page . '&cID=' . $QragWebSearch->valueInt('id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_ChatGpt->getDef('icon_edit') . '"></i></h4>');
            echo '&nbsp;';
            ?>
          </div>
        </td>
      </tr>
      <?php
        }
      }
      ?>
      </tbody>
    </table>
    </form>

  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $QragWebSearch->getPageSetLabel($CLICSHOPPING_ChatGpt->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $QragWebSearch->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>
<div class="py-4"></div>