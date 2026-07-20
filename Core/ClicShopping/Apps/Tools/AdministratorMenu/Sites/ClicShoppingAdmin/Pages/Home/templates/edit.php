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

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Tools\AdministratorMenu\Classes\ClicShoppingAdmin\AdministratorMenu;

$CLICSHOPPING_AdministratorMenu = Registry::get('AdministratorMenu');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

if (isset($_GET['cID'])) {
  $Qcategories = $CLICSHOPPING_AdministratorMenu->db->prepare('select a.id,
                                                                        a.link,
                                                                        a.parent_id,
                                                                        a.access,
                                                                        a.sort_order,
                                                                        a.image,
                                                                        a.b2b_menu,
                                                                        a.app_code,
                                                                        amd.label
                                                                  from :table_administrator_menu a,
                                                                       :table_administrator_menu_description amd
                                                                  where a.id = amd.id
                                                                  and a.id = :id
                                                                  and amd.language_id = :language_id
                                                                  order by a.parent_id,
                                                                           a.sort_order
                                                                  ');
  $Qcategories->bindInt(':id', (int)$_GET['cID']);
  $Qcategories->bindInt(':language_id', $CLICSHOPPING_Language->getId());
  $Qcategories->execute();

  $cInfo = new ObjectInfo($Qcategories->toArray());
} else {
  $cInfo = new ObjectInfo(array());
}

if (isset($_GET['cPath'])) {
  $cPath = HTML::sanitize($_GET['cPath']);
} else {
  $cPath = 0;
}

if (isset($_GET['cID'])) {
  $cID = HTML::sanitize($_GET['cID']);
} else {
  $cID = 0;
}

$languages = $CLICSHOPPING_Language->getLanguages();
$form_action = (isset($_GET['cID'])) ? 'Update' : 'Insert';

echo HTML::form('category', $CLICSHOPPING_AdministratorMenu->link('AdministratorMenu&' . $form_action . '&cPath=' . $cPath . '&cID=' . $cID), 'post');

?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/menu.png', $CLICSHOPPING_AdministratorMenu->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_AdministratorMenu->getDef('heading_title'); ?></span>
          <span class="col-md-9 text-end">
            <span
              class="text-end"><?php echo HTML::hiddenField('parent_id', $cInfo->parent_id ?? null) . HTML::button($CLICSHOPPING_AdministratorMenu->getDef('button_update'), null, null, 'success'); ?>&nbsp;</span>
            <span class="text-end"
                  style="padding-left:5px;"><?php echo HTML::button($CLICSHOPPING_AdministratorMenu->getDef('button_cancel'), null, $CLICSHOPPING_AdministratorMenu->link('AdministratorMenu&cPath=' . $cPath), 'warning'); ?>&nbsp;</span>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>


  <div>
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_AdministratorMenu->getDef('tab_general') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <?php
        // -------------------------------------------------------------------
        //          ONGLET General sur la description de la categorie
        // -------------------------------------------------------------------
        ?>
        <div class="tab-pane active" id="tab1">
          <div class="col-md-12 mainTitle">
            <div><?php echo $CLICSHOPPING_AdministratorMenu->getDef('heading_title'); ?></div>
          </div>
          <div class="adminformTitle">
            <?php
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="code"
                           class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::inputField('label[' . $languages[$i]['id'] . ']', AdministratorMenu::getAdministratorMenuLabel($cInfo->id ?? null, $languages[$i]['id']), 'class="form-control" required aria-required="true" Authentificator id="label" placeholder="' . $CLICSHOPPING_AdministratorMenu->getDef('text_menu') . '"', true) . '&nbsp;'; ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
            ?>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_edit_link'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_edit_link'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('link', $cInfo->link ?? null, 'placeholder="' . $CLICSHOPPING_AdministratorMenu->getDef('text_edit_link') . '"'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_edit_access_administrator'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_edit_access_administrator'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::selectMenu('access_administrator', AdministratorAdmin::getAdministratorMenuRight($CLICSHOPPING_AdministratorMenu->getDef('text_selected')), $cInfo->access ?? null); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_image'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_image'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('image', $cInfo->image ?? null, 'placeholder="' . $CLICSHOPPING_AdministratorMenu->getDef('text_edit_image') . '"'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_edit_b2b_menu'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_edit_b2b_menu'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::checkboxField('b2b_menu', $cInfo->b2b_menu ?? null, $cInfo->b2b_menu ?? null); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_edit_sort_order'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_edit_sort_order'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('sort_order', $cInfo->sort_order ?? null, 'placeholder="' . $CLICSHOPPING_AdministratorMenu->getDef('text_edit_sort_order') . '" size="2"'); ?>
                  </div>
                </div>
              </div>
            </div>

            <?php
            if (isset($_GET['Edit'])) {
              ?>
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_select_menu'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_select_menu'); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::selectMenu('move_to_category_id', AdministratorMenu::getLabelTree(), $cPath) . HTML::hiddenField('current_category_id', $cPath); ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
</div>