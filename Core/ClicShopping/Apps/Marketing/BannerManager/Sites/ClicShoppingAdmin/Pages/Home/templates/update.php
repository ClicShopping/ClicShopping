<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_BannerManager = Registry::get('BannerManager');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$parameters = [
  'expires_date' => '',
  'date_scheduled' => '',
  'banners_title' => '',
  'banners_url' => '',
  'banners_group' => '',
  'banners_target' => '',
  'banners_image' => '',
  'banners_html_text' => '',
  'expires_impressions' => '',
  'banners_title_admin' => '',
  'banners_theme' => ''
];

$bInfo = new ObjectInfo($parameters);

if (isset($_GET['bID'])) {
  $bID = HTML::sanitize($_GET['bID']);

  $Qbanner = $CLICSHOPPING_BannerManager->db->get('banners', [
    'banners_title',
    'banners_url',
    'banners_image',
    'banners_group',
    'banners_target',
    'banners_html_text',
    'status',
    'date_scheduled',
    'expires_date',
    'expires_impressions',
    'date_status_change',
    'customers_group_id',
    'languages_id',
    'banners_title_admin',
    'banners_theme'
  ], [
      'banners_id' => (int)$bID
    ]
  );

  $bInfo->ObjectInfo($Qbanner->toArray());

}

$languages = $CLICSHOPPING_Language->getLanguages();

$values_languages_id[0] = [
  'id' => '0',
  'text' => $CLICSHOPPING_BannerManager->getDef('text_all_languages')
];

for ($i = 0, $n = \count($languages); $i < $n; $i++) {
  $values_languages_id[$i + 1] = [
    'id' => $languages[$i]['id'],
    'text' => $languages[$i]['name']
  ];
}

$groups_array = [];

$Qgroups = $CLICSHOPPING_BannerManager->db->get('banners', 'distinct banners_group', null, 'banners_group');

while ($Qgroups->fetch()) {
  $groups_array[] = [
    'id' => $Qgroups->value('banners_group'),
    'text' => $Qgroups->value('banners_group')
  ];
}


$theme_array = [];
if (!empty($bInfo->date_scheduled)) {
  $date_scheduled = DateTime::toShortWithoutFormat($bInfo->date_scheduled);
} else {
  $date_scheduled = '';
}

if (!empty($bInfo->date_scheduled)) {
  $expires_date = DateTime::toShortWithoutFormat($bInfo->expires_date);
} else {
  $expires_date = '';
}

// reactions au niveau du clique
$banners_target_array = array(array('id' => '_self', 'text' => $CLICSHOPPING_BannerManager->getDef('text_banners_same_windows')),
  array('id' => '_blank', 'text' => $CLICSHOPPING_BannerManager->getDef('text_banners_new_windows'))
);

echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/banner_manager.gif', $CLICSHOPPING_BannerManager->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_BannerManager->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end">
            <?php
            echo HTML::form('new_banner', $CLICSHOPPING_BannerManager->link('BannerManager&Update', (isset($page) ? 'page=' . $page . '&' : '')), 'post', 'enctype="multipart/form-data"');
            echo HTML::hiddenField('banners_id', (int)$bID);
            echo HTML::button($CLICSHOPPING_BannerManager->getDef('button_cancel'), null, $CLICSHOPPING_BannerManager->link('BannerManager&BannerManager' . (isset($page) ? 'page=' . $page . '&' : '') . (isset($_GET['bID']) ? 'bID=' . $_GET['bID'] : '')), 'warning') . ' ';
            echo HTML::button($CLICSHOPPING_BannerManager->getDef('button_update'), null, null, 'success');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div id="BannerManagerTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li class="nav-item"><a href="#tab1" role="tab" data-bs-toggle="tab"
                              class="nav-link active"><?php echo $CLICSHOPPING_BannerManager->getDef('tab_general'); ?></a>
      </li>
      <li class="nav-item"><a href="#tab2" role="tab" data-bs-toggle="tab"
                              class="nav-link"><?php echo $CLICSHOPPING_BannerManager->getDef('tab_img'); ?></a></li>
      <li class="nav-item"><a href="#tab3" role="tab" data-bs-toggle="tab"
                              class="nav-link"><?php echo $CLICSHOPPING_BannerManager->getDef('tab_code_html'); ?></a>
      </li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- ##########################################################  //-->
        <!--          ONGLET Information General de la Banniere          //-->
        <!-- ##########################################################  //-->
        <div class="tab-pane active" id="tab1">
          <div class="mainTitle"><?php echo $CLICSHOPPING_BannerManager->getDef('title_banners_general'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_title'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_title'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('banners_title', $bInfo->banners_title, 'required aria-required="true" placeholder="' . $CLICSHOPPING_BannerManager->getDef('text_banners_title') . '"'); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_title_admin'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_title_admin'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('banners_title_admin', $bInfo->banners_title_admin, 'required aria-required="true" placeholder="' . $CLICSHOPPING_BannerManager->getDef('text_banners_title') . '"'); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_url'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_url'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('banners_url', $bInfo->banners_url); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_target'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_target'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::selectMenu('banners_target', $banners_target_array, $bInfo->banners_target); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <?php echo $CLICSHOPPING_Hooks->output('BannerManager', 'CustomerGroup', null, 'display'); ?>

          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_group'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_language'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_language'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::selectMenu('languages_id', $values_languages_id, $bInfo->languages_id); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_group'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_group'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::selectMenu('banners_group', $groups_array, $bInfo->banners_group); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_new_group'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_new_group'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('new_banners_group', '', '', ((\count($groups_array) > 0) ? false : true)); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_theme'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_theme'); ?></label>
                  <div class="col-md-5">
                    <?php echo $CLICSHOPPING_Template->updateTemplate('banners_theme', $CLICSHOPPING_BannerManager->getDef('text_banners_all_themes'), $bInfo->banners_theme); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_BannerManager->getDef('title_banners_date'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_scheduled_at'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_scheduled_at'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('date_scheduled', $date_scheduled, null, 'date'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_expires_on'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_expires_on'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('expires_date', $expires_date, null, 'date'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_impressions'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_or_at') . ' ' . $CLICSHOPPING_BannerManager->getDef('text_banners_impressions'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('expires_impressions', $bInfo->expires_impressions, 'maxlength="7" size="7"'); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_BannerManager->getDef('text_help_banners_image') . '"></i></h4> ' . $CLICSHOPPING_BannerManager->getDef('title_help_banners_image') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_expiracy_note') . '<br />' . $CLICSHOPPING_BannerManager->getDef('text_banners_scheduled_note'); ?></div>
          </div>
        </div>
        <!-- ##########################################################  //-->
        <!--          ONGLET Image banniere          //-->
        <!-- ##########################################################  //-->
        <div class="tab-pane" id="tab2">
          <div class="mainTitle"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_image'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-12">
                <span
                  class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/banner_manager.gif', $CLICSHOPPING_BannerManager->getDef('text_categories_image_vignette'), '40', '40'); ?></span>
                <span
                  class="col-md-3 main"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_image'); ?></span>
                <span
                  class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_categories.gif', $CLICSHOPPING_BannerManager->getDef('text_banners_image_visuel'), '40', '40'); ?></span>
                <span
                  class="col-md-7 main"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_image_visuel'); ?></span>
              </div>
              <div class="col-md-12">
                <div class="adminformAide">
                  <div class="row">
                    <span
                      class="col-md-4 text-center float-start"><?php echo $CLICSHOPPING_Wysiwyg::fileFieldImageCkEditor('banners_image_local', null, '300', '300'); ?></span>
                    <span class="col-md-8 text-center float-end">
                      <div class="col-md-12">
<?php
echo $CLICSHOPPING_ProductsAdmin->getInfoImage($bInfo->banners_image, $CLICSHOPPING_BannerManager->getDef('text_banners_image'));
echo HTML::hiddenField('banners_image_show', $bInfo->banners_image);
?>
                       </div>
                      <div class="col-md-12 text-end">
                        <?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_image_delete') . HTML::checkboxField('delete_image', 'yes', false); ?>
                      </div>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_BannerManager->getDef('text_help_banners_image') . '"></i></h4> ' . $CLICSHOPPING_BannerManager->getDef('text_help_banners_image') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_insert_note'); ?></div>
          </div>
        </div>
        <!-- ##########################################################  //-->
        <!--          ONGLET Texte HTML Banniere          //-->
        <!-- ##########################################################  //-->
        <div class="tab-pane" id="tab3">
          <div class="mainTitle"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_html'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_html_text'); ?>"
                         class="col-3 col-form-label"><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_html_text'); ?></label>
                  <div class="col-md-7">
                    <?php echo HTML::textAreaField('banners_html_text', $bInfo->banners_html_text, '500', '10'); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_BannerManager->getDef('text_help_banners_image') . '"></i></h4> ' . $CLICSHOPPING_BannerManager->getDef('text_help_banners_image') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_BannerManager->getDef('text_banners_banner_note'); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
</div>