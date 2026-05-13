<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_SecurityCheck = Registry::get('SecurityCheck');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$form_action = 'Insert';
$variable = '';

if ((isset($_GET['Edit']) && isset($_GET['cID']) && !empty($_GET['cID']))) {
  $form_action = 'Update';
  $variable = '&cID=' . HTML::sanitize($_GET['cID']);
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

echo HTML::form('ip_restriction', $CLICSHOPPING_SecurityCheck->link('IpRestriction&' . $form_action . $variable));
if ($form_action == 'Update') echo HTML::hiddenField('id', HTML::sanitize($_GET['cID']));
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/cybermarketing.gif', $CLICSHOPPING_SecurityCheck->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_SecurityCheck->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
            <?php
            echo HTML::button($CLICSHOPPING_SecurityCheck->getDef('button_cancel'), null, $CLICSHOPPING_SecurityCheck->link('IpRestriction&page=' . $page . $variable), 'warning') . '&nbsp;';
            echo(($form_action == 'Insert') ? HTML::button($CLICSHOPPING_SecurityCheck->getDef('button_insert'), null, null, 'success') : HTML::button($CLICSHOPPING_SecurityCheck->getDef('button_update'), null, null, 'success'));
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  if (isset($_GET['cID'])) {
    $cID = $_GET['cID'];
  } else {
    $cID = null;
  }

  $QIpRestriction = $CLICSHOPPING_SecurityCheck->db->prepare('select id,
                                                                       ip_restriction,
                                                                       ip_comment
                                                                from :table_ip_restriction
                                                                where id = :id
                                                              ');
  $QIpRestriction->bindInt(':id', $cID);
  $QIpRestriction->execute();
  ?>
  <div id="IprestrcitionTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_SecurityCheck->getDef('tab_general') . '</a>'; ?></li>
    </ul>

    <div class="tabsClicShopping">
      <div class="tab-content">
        <?php
        // -- ------------------------------------------------------------ //
        // --          ONGLET Information Général de la Marque          //
        // -- ------------------------------------------------------------ //
        ?>
        <div class="tab-pane active" id="tab1">
          <div class="col-md-12 mainTitle">
            <div
              class="float-start"><?php echo $CLICSHOPPING_SecurityCheck->getDef('title_iprestricton_general'); ?></div>
          </div>
          <div class="adminformTitle">

            <div class="row" id="IpRestriction">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_SecurityCheck->getDef('text_ip_restriction'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_SecurityCheck->getDef('text_ip_restriction'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('ip_restriction', $QIpRestriction->value('ip_restriction') ?? null, 'required aria-required="true" id="ip_restriction" placeholder="' . $CLICSHOPPING_SecurityCheck->getDef('text_ip_restriction') . '"', 'ip_restriction'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="IpComment">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_SecurityCheck->getDef('text_ip_comment'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_SecurityCheck->getDef('text_ip_comment'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('ip_comment', $QIpRestriction->value('ip_comment') ?? null, 'id="ip_comment" placeholder="' . $CLICSHOPPING_SecurityCheck->getDef('text_ip_comment') . '"', 'ip_comment'); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('IpRestriction', 'ProductsContentTab1', null, 'display'); ?>
        </div>
      </div>
    </div>
    <?php echo $CLICSHOPPING_Hooks->output('IpRestriction', 'PageContent', null, 'display'); ?>
  </div>
</div>
</form>