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

use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Newsletter = Registry::get('Newsletter');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$action = $_GET['action'] ?? '';

if (isset($_GET['nID'])) {
  $nID = HTML::sanitize($_GET['nID']);
  echo HTML::form('newsletter', $CLICSHOPPING_Newsletter->link('Newsletter&Update&page=' . $page));
  echo HTML::hiddenField('newsletter_id', $nID);
} else {
  $nID = null;
  echo HTML::form('newsletter', $CLICSHOPPING_Newsletter->link('Newsletter&Insert&page=' . $page));
}
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
          <span class="col-md-6 text-end">
<?php
if (isset($_GET['Update'])) {
  echo HTML::button($CLICSHOPPING_Newsletter->getDef('button_cancel'), null, $CLICSHOPPING_Newsletter->link('Newsletter&page=' . $page . '&nID=' . $nID), 'warning') . '&nbsp;';
  echo HTML::button($CLICSHOPPING_Newsletter->getDef('button_update'), null, null, 'success');
} else {
  echo HTML::button($CLICSHOPPING_Newsletter->getDef('button_cancel'), null, $CLICSHOPPING_Newsletter->link('Newsletter&page=' . $page), 'warning') . '&nbsp;';
  echo HTML::button($CLICSHOPPING_Newsletter->getDef('button_save'), null, null, 'success');
}
?>
           </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  $parameters = ['title' => '',
    'content' => '',
    'module' => '',
    'languages_id' => '',
    'customers_group_id' => '',
    'newsletters_accept_file' => ''
  ];

  $nInfo = new ObjectInfo($parameters);

  if (isset($_GET['Update']) && !\is_null($nID)) {
    $Qnewsletter = $CLICSHOPPING_Newsletter->db->get('newsletters', [
      'title',
      'content',
      'module',
      'languages_id',
      'customers_group_id',
      'newsletters_accept_file'
    ], [
        'newsletters_id' => (int)$nID
      ]
    );
    $nInfo->ObjectInfo($Qnewsletter->toArray());

//ok
    if (!isset($nInfo->newsletters_accept_file)) $nInfo->newsletters_accept_file = '1';
    switch ($nInfo->newsletters_accept_file) {
      case '0':
        $in_accept_file = false;
        $out_accept_file = true;
        break;
      case '1':
        $in_accept_file = true;
        $out_accept_file = false;
        break;
      default:
        $in_accept_file = true;
        $out_accept_file = false;
        break;
    }
  } else {
    $in_accept_file = $in_accept_file ?? false;
    $out_accept_file = $out_accept_file ?? true;
  }


  $file_extension = substr(CLICSHOPPING::getIndex(), strrpos(CLICSHOPPING::getIndex(), '.'));
  $directory_array = [];

  if ($dir = dir(CLICSHOPPING::BASE_DIR . 'Apps/Communication/Newsletter/Module/ClicShoppingAdmin/Newsletter/')) {
    while ($file = $dir->read()) {
      if (!is_dir(CLICSHOPPING::BASE_DIR . 'Apps/Communication/Newsletter/Module/ClicShoppingAdmin/Newsletter/' . $file)) {
        if (substr($file, strrpos($file, '.')) == $file_extension) {
          $directory_array[] = $file;
        }
      }
    }
    sort($directory_array);
    $dir->close();
  }

  for ($i = 0, $n = \count($directory_array); $i < $n; $i++) {
    $modules_array[] = [
      'id' => substr($directory_array[$i], 0, strrpos($directory_array[$i], '.')),
      'text' => substr($directory_array[$i], 0, strrpos($directory_array[$i], '.'))
    ];
  }

  // Put languages information into an array for drop-down boxes
  $customers_group = GroupsB2BAdmin::getAllGroups();

  for ($i = 0, $n = \count($customers_group); $i < $n; $i++) {
    $values_customers_group_id[$i + 1] = [
      'id' => $customers_group[$i]['id'],
      'text' => $customers_group[$i]['text']
    ];
  }

  // Put languages information into an array for drop-down boxes
  $languages = $CLICSHOPPING_Language->getLanguages();

  $values_languages_id[0] = [
    'id' => '0',
    'text' => $CLICSHOPPING_Newsletter->getDef('text_all_languages')
  ];

  for ($i = 0, $n = \count($languages); $i < $n; $i++) {
    $values_languages_id[$i + 1] = [
      'id' => $languages[$i]['id'],
      'text' => $languages[$i]['name']
    ];
  }
  ?>
  <div id="newsletterTab" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li class="nav-item"><a href="#tab1" role="tab" data-bs-toggle="tab"
                              class="nav-link active"><?php echo $CLICSHOPPING_Newsletter->getDef('tab_general'); ?></a>
      </li>
      <li class="nav-item"><a href="#tab2" role="tab" data-bs-toggle="tab"
                              class="nav-link"><?php echo $CLICSHOPPING_Newsletter->getDef('tab_description'); ?></a>
      </li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- ----------------------------------------------------------- //-->
        <!--          ONGLET Information General de la Banniere          //-->
        <!-- ----------------------------------------------------------- //-->
        <div class="tab-pane active" id="tab1">
          <div class="mainTitle"></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_module'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_module'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::selectMenu('module', $modules_array, $nInfo->module); ?>
                  </div>
                </div>
              </div>
            </div>
            <?php
            // Permettre l'affichage des groupes en mode B2B
            if (MODE_B2B_B2C == 'True') {
              ?>
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_customers_group'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_customers_group'); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::selectMenu('customers_group_id', $values_customers_group_id, $nInfo->customers_group_id); ?>
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
                  <label for="<?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_language'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_language'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::selectMenu('languages_id', $values_languages_id, $nInfo->languages_id); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="row" id="newsletterTitle">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_title'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_title'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('title', $nInfo->title, 'required aria-required="true" id="tilte"', true); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="row" id="newsletterFile">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_create_file_html'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_create_file_html'); ?></label>
                  <div class="col-md-5">
                    <div class="custom-control custom-radio custom-control-inline">
                      <?php echo HTML::radioField('newsletters_accept_file', '1', $in_accept_file, 'class="custom-control-input" id="newsletters_accept_file_yes" name="newsletters_accept_file_yes"'); ?>
                      <label class="custom-control-label"
                             for="newsletters_accept_file_yes"><?php echo $CLICSHOPPING_Newsletter->getDef('text_yes'); ?></label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                      <?php echo HTML::radioField('newsletters_accept_file', '0', $out_accept_file, 'class="custom-control-input" id="newsletters_accept_file_no" name="newsletters_accept_file_no"'); ?>
                      <label class="custom-control-label"
                             for="newsletters_accept_file_no"><?php echo $CLICSHOPPING_Newsletter->getDef('text_no'); ?></label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php echo $CLICSHOPPING_Hooks->output('Newsletter', 'NewsletterContentTab1', null, 'display'); ?>
          </div>
        </div>
        <?php
        //-------------------------------------------
        //                     Tab 2
        //------------------------------------------

        echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
        ?>
        <div class="tab-pane" id="tab2">
          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_Newsletter->getDef('text_newsletter_content'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group row">
                  <div class="col-md-12">
                    <?php
                    $name = 'message';
                    $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                    echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '300', $nInfo->content, 'id="' . $ckeditor_id . '"');
                    ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
        </div>
      </div>
    </div>
    <?php echo $CLICSHOPPING_Hooks->output('Newsletter', 'PageTab', null, 'display'); ?>
    </form>
  </div>
</div>