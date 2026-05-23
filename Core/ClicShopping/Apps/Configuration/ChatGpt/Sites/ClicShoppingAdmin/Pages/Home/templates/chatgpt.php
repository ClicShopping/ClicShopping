<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Infrastructure\Metrics\Statistics;

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
          <?php
          if (MODE_DEMO == 'False') {
            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_configure'), null, $CLICSHOPPING_ChatGpt->link('Configure'), 'primary') . ' ';
          }

          if (defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS === 'True') {
            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_rag_dashboard'), null, $CLICSHOPPING_ChatGpt->link('DashBoard'), 'danger') . ' ';
            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_rag_websearch'), null, $CLICSHOPPING_ChatGpt->link('RagWebSearch'), 'success') . ' ';
          }

          echo HTML::form('delete_everything', $CLICSHOPPING_ChatGpt->link('ChatGpt&DeleteEverything'));
          echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_delete'), null, null, 'danger');
          echo '</form>'
          ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- ################# -->
  <!-- Hooks Stats - just use execute function to display the hook-->
  <!-- ################# -->
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <?php
          $stat_result = Statistics::getTotalTokenByMonth();

          if(is_array($stat_result)) {
            if ($stat_result['promptTokens'] > 0) {
              ?>
              <div class="col-md-3 col-12">
                <div class="card bg-danger">
                  <div class="card-body">
                    <h6
                      class="card-title text-white"><?php echo $CLICSHOPPING_ChatGpt->getDef('stat_prompt_tokens'); ?></h6>
                    <div class="card-text">
                      <div class="col-sm-12">
                          <span class="float-start">
                            <i class="bi bi-clipboard2-pulse-fill text-white"></i>
                          </span>
                        <span class="float-end">
                          <div class="col-sm-12 text-white"><?php echo $stat_result['promptTokens']; ?></div>
                          </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }

            if ($stat_result['completionTokens'] > 0) {
              ?>
              <div class="col-md-3 col-12">
                <div class="card bg-success">
                  <div class="card-body">
                    <h6
                      class="card-title text-white"><?php echo $CLICSHOPPING_ChatGpt->getDef('stat_completion_tokens'); ?></h6>
                    <div class="card-text">
                      <div class="col-sm-12">
                          <span class="float-start">
                            <i class="bi bi-bar-chart-fill text-white"></i>
                          </span>
                        <span class="float-end">
                          <div class="col-sm-12 text-white"><?php echo $stat_result['completionTokens']; ?></div>
                          </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }

            if ($stat_result['totalTokens'] > 0) {
              ?>
              <div class="col-md-3 col-12">
                <div class="card bg-primary">
                  <div class="card-body">
                    <h6
                      class="card-title text-white"><?php echo $CLICSHOPPING_ChatGpt->getDef('stat_total_tokens'); ?></h6>
                    <div class="card-text">
                      <div class="col-sm-12">
                          <span class="float-start">
                            <i class="bi bi-graph-up text-white"></i>
                          </span>
                        <span class="float-end">
                          <div class="col-sm-12 text-white"><?php echo $stat_result['totalTokens']; ?></div>
                          </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
          }

          if (Gpt::getErrorRateGpt() !== false) {
            ?>
            <div class="col-md-3 col-12">
              <div class="card bg-warning">
                <div class="card-body">
                  <h6
                    class="card-title text-white"><?php echo $CLICSHOPPING_ChatGpt->getDef('stat_total_no_response'); ?></h6>
                  <div class="card-text">
                    <div class="col-sm-12">
                      <span class="float-start">
                        <i class="bi bi-graph-up text-white"></i>
                      </span>
                      <span class="float-end">
                        <div
                          class="col-sm-12 text-white"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_rate_error_gpt') . ' ' . Gpt::getErrorRateGpt(); ?></div>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }

          echo $CLICSHOPPING_Hooks->output('Stats', 'StatsGpt', null, 'display');
          ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                            LISTING                                                           -->
  <!-- //################################################################################################################ -->

  <?php echo HTML::form('delete_all', $CLICSHOPPING_ChatGpt->link('ChatGpt&DeleteAll') . '&page=' . $page); ?>

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
    data-sort-name="chatgpt"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-show-export="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected"
          data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_chatgpt_id'); ?></th>
      <th data-field="question" class="text-center"
          data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_chatgpt_question'); ?></th>
      <th data-field="date_added"
          data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_chatgpt_date_added'); ?></th>
      <th data-field="user_admin"
          data-sortable="true"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_chatgpt_user_admin'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_ChatGpt->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $QchatGpt = $CLICSHOPPING_ChatGpt->db->prepare('select SQL_CALC_FOUND_ROWS gpt_id,
                                                                                 question,
                                                                                 response,
                                                                                 date_added,
                                                                                 user_admin
                                                      from :table_gpt
                                                      order by date_added DESC
                                                      limit :page_set_offset, :page_set_max_results
                                                    ');

    $QchatGpt->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $QchatGpt->execute();

    $listingTotalRow = $QchatGpt->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($QchatGpt->fetch()) {
        ?>
        <tr>
          <td></td>
          <td><?php echo $QchatGpt->valueInt('gpt_id'); ?></td>
          <td class="test-start"><?php echo substr($QchatGpt->value('question'), 0, 200) . "..."; ?></td>
          <td><?php echo DateTime::toShort($QchatGpt->value('date_added')); ?></td>
          <td><?php echo $QchatGpt->value('user_admin'); ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_ChatGpt->link('Edit&page=' . $page . '&cID=' . $QchatGpt->valueInt('gpt_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_ChatGpt->getDef('icon_edit') . '"></i></h4>');
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $QchatGpt->getPageSetLabel($CLICSHOPPING_ChatGpt->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $QchatGpt->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
<div class="py-4"></div>