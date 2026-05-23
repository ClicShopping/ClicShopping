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
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Communication\PageManager\Classes\ClicShoppingAdmin\PageManagerAdmin;

$CLICSHOPPING_PageManager = Registry::get('PageManager');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$languages = $CLICSHOPPING_Language->getLanguages();

$parameters = [
  'pages_title' => '',
  'page_time' => '',
  'page_date_start' => '',
  'page_date_closed' => '',
  'pages_html_text' => '',
  'sort_order' => '',
  'customers_group_id' => '',
  'status' => '',
  'page_manager_head_title_tag' => '',
  'page_manager_head_keywords_tag' => '',
  'page_manager_head_desc_tag' => ''
];

$bInfo = new ObjectInfo($parameters);

if (isset($_GET['error'])) {
  $page_error = HTML::sanitize($_GET['error']);
} else {
  $page_error = false;
}

$bID = null;
$page_date_start = '';
$page_date_closed = '';

if (isset($_GET['bID'])) {
  $bID = HTML::sanitize($_GET['bID']);

  $Qpage = $CLICSHOPPING_PageManager->db->prepare('select s.status,
                                                            s.links_target,
                                                            s.customers_group_id,
                                                            s.sort_order,
                                                            s.page_type,
                                                            s.page_box,
                                                            p.pages_title,
                                                            p.pages_html_text,
                                                            p.externallink,
                                                            s.page_time,
                                                            p.language_id,
                                                            s.page_date_start,
                                                            s.page_date_closed,
                                                            s.page_general_condition,
                                                            p.page_manager_head_title_tag,
                                                            p.page_manager_head_keywords_tag,
                                                            p.page_manager_head_desc_tag
                                                      from :table_pages_manager s left join :table_pages_manager_description p on s.pages_id = p.pages_id
                                                      where s.pages_id = :pages_id
                                                      ');
  $Qpage->bindint(':pages_id', (int)$bID);
  $Qpage->execute();


  while ($Qpage->fetch()) {
    $languageid = $Qpage->valueInt('language_id');
    $customers_group_id = $Qpage->valueInt('customers_group_id');
    $page_type = $Qpage->value('page_type');
    $links_target = $Qpage->value('links_target');
    $page_box = $Qpage->value('page_box');
    $sort_order = $Qpage->valueInt('sort_order');
    $page_time = $Qpage->value('page_time');

    if (!empty($Qpage->value('page_date_start'))) {
      $page_date_start = DateTime::toShortWithoutFormat($Qpage->value('page_date_start'));
    } else {
      $page_date_start = '';
    }

    if (!empty($Qpage->value('page_date_closed'))) {
      $page_date_closed = DateTime::toShortWithoutFormat($Qpage->value('page_date_closed'));
    } else {
      $page_date_closed = '';
    }

    $page_general_condition = $Qpage->value('page_general_condition');

    $pagetitle[$languageid] = $Qpage->value('pages_title');
    $pages_html_text[$languageid] = $Qpage->value('pages_html_text');
    $externallink[$languageid] = $Qpage->value('externallink');

    $page_manager_head_title_tag[$languageid] = $Qpage->value('page_manager_head_title_tag');
    $page_manager_head_desc_tag[$languageid] = $Qpage->value('page_manager_head_title_tag');
    $page_manager_head_keywords_tag[$languageid] = $Qpage->value('page_manager_head_title_tag');
  }
} else {
  $bInfo->ObjectInfo($_POST);
}

$bIDif = '';

if (isset($bID) && !empty($bID)) {
  $bIDif = '&bID=' . (int)$bID;
}

if (isset($_POST['page_type']) && HTML::sanitize($_POST['page_type']) != 0) {
  $page_type = HTML::sanitize($_POST['page_type']);
}

// Type de la page demandee dans le menu deroulant
if ($page_type == 1) {
  $page_manager_introduction_page = 'false';
} else {
  $page_manager_introduction_page = 'true';
}

if ($page_type == 2) {
  $page_manager_main_page = 'false';
} else {
  $page_manager_main_page = 'true';
}

if ($page_type == 3) {
  $page_manager_contact_us = 'false';
} else {
  $page_manager_contact_us = 'true';
}

if ($page_type == 4) {
  $page_manager_informations = 'false';
} else {
  $page_manager_informations = 'true';
}


$page_type_statut = [];

$page_type_statut[] = ['id' => '1',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_introduction_page'),
  'disabled' => $page_manager_introduction_page
];

$page_type_statut[] = ['id' => '2',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_main_page'),
  'disabled' => $page_manager_main_page
];

$page_type_statut[] = ['id' => '3',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_contact_us'),
  'disabled' => $page_manager_contact_us
];

$page_type_statut[] = ['id' => '4',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_informations'),
  'disabled' => $page_manager_informations
];

$page_type_statut[] = ['id' => '5',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_menu_header'),
  'disabled' => $page_manager_informations
];

$page_type_statut[] = ['id' => '6',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_menu_footer'),
  'disabled' => $page_manager_informations
];

$page_box_statut = [];

$page_box_statut[] = ['id' => '0',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_main_box')
];

$page_box_statut[] = ['id' => '1',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_secondary_box')
];

$page_box_statut[] = ['id' => '2',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_landing_page')
];

$page_box_statut[] = ['id' => '3',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_none')
];

// Type de lien demande dans le menu deroulant
$links_target_statut = [];

$links_target_statut[] = ['id' => '_self',
  'text' => $CLICSHOPPING_PageManager->getDef('text_link_same_windows')
];

$links_target_statut[] = ['id' => '_blank',
  'text' => $CLICSHOPPING_PageManager->getDef('text_link_new_windows')
];

// seclect if the page is general condition or not
$page_general_condition_statut = [];

$page_general_condition_statut[] = ['id' => '0',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_text_no')
];

$page_general_condition_statut[] = ['id' => '1',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_text_yes')
];
?>
<script type="text/javascript">
  function popupImageWindow(url) {
    window.open(url, 'popupImageWindow', 'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=no,resizable=yes,copyhistory=no,width=100,height=100,screenX=150,screenY=150,top=150,left=150')
  }
</script>

<script type="text/javascript">
  function disableIt(a) {
    document.getElementById(a).disabled = true;
  }

  function enableIt(a) {
    document.getElementById(a).disabled = false;
  }
</script>

<?php
echo $CLICSHOPPING_Wysiwyg::getWysiwyg();

if ($page_error === true) {
  ?>
  <div class="alert alert-danger" role="alert">
    <?php
    echo '<h4><i class="bi bi-exclamation-diamond" title="' . $CLICSHOPPING_PageManager->getDef('icon_warning') . '"></i></h4> ';
    echo $CLICSHOPPING_PageManager->getDef('warning_edit_customers');
    ?>
  </div>
  <?php
}

$form_action = 'Insert';

if (isset($_GET['bID']) && !empty($_GET['bID'])) {
  $form_action = 'Update';
}

echo HTML::form('page_manager', $CLICSHOPPING_PageManager->link('PageManager&Save&' . $form_action . '&' . (isset($page) ? 'page=' . $page . '&' : '')), 'post', 'enctype="multipart/form-data"');
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/page_manager.gif', $CLICSHOPPING_PageManager->getDef('heading_title_edition'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_PageManager->getDef('heading_title_edition'); ?></span>
          <span class="col-md-6 text-end">
<?php
echo HTML::hiddenField('pages_id', $bID);
echo HTML::button($CLICSHOPPING_PageManager->getDef('button_cancel'), null, $CLICSHOPPING_PageManager->link('PageManager&' . (isset($page) ? 'page=' . $page . '&' : '') . ((!isset($bID) and $bID != '') ? 'bID=' . $bID : '')), 'warning') . '&nbsp;';

if (isset($_GET['bID'])) {
  echo HTML::button($CLICSHOPPING_PageManager->getDef('button_update'), null, null, 'success');
} else {
  echo HTML::button($CLICSHOPPING_PageManager->getDef('button_insert'), null, null, 'success');
}

?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <?php
  if ($page_error === true) {
    ?>

    <div class="alert alert-danger" role="alert">
      <span><?php echo '<h4><i class="bi bi-exclamation-diamond" title="' . $CLICSHOPPING_PageManager->getDef('icon_warning') . '"></i></h4>'; ?></span>
      <span><?php echo $CLICSHOPPING_PageManager->getDef('warning_edit_customers'); ?></span>
    </div>
    <?php
  }
  ?>
  <div id="pageManagerTabs" style="overflow: auto;">
    <?php
    if ($page_type == 4) {
      ?>
      <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
        <li
          class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_PageManager->getDef('tab_general') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_PageManager->getDef('tab_page_link'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_PageManager->getDef('tab_page_description'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab4" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_PageManager->getDef('tab_page_meta_tag'); ?></a></li>
      </ul>
      <?php
    } elseif ($page_type == 3) {
      ?>
      <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
        <li
          class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_PageManager->getDef('tab_general') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_PageManager->getDef('tab_page_description'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab4" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_PageManager->getDef('tab_page_meta_tag'); ?></a></li>
      </ul>
      <?php
    } elseif ($page_type == 5 || $page_type == 6) {
      ?>
      <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
        <li
          class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_PageManager->getDef('tab_general') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_PageManager->getDef('tab_page_link'); ?></a></li>
      </ul>
      <?php
    } else {
      ?>
      <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
        <li
          class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_PageManager->getDef('tab_general') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_PageManager->getDef('tab_page_description'); ?></a></li>
      </ul>
      <?php
    }
    ?>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- ############################################################ //-->
        <!--          ONGLET Information General                          //-->
        <!-- ############################################################ //-->
        <div class="tab-pane active" id="tab1">
          <div class="mainTitle"><?php echo $CLICSHOPPING_PageManager->getDef('title_name_page'); ?></div>
          <div class="adminformTitle">
            <?php
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              if ($page_error === true) {
                if ($languages_title_error == $languages[$i]['id']) {
                  ?>
                  <div class="row">
                    <div class="col-md-5">
                      <div class="form-group row">
                        <label for="Langue"
                               class="col-5 col-form-label"> <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                        <div class="col-md-5">
                          <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?>
                          <?php echo HTML::inputField('pages_title_' . $languages[$i]['id'], $pagetitle[$languages[$i]['id']] ?? '', 'required aria-required="true" id="' . 'pages_title_' . $languages[$i]['id'] . '" maxlength="64"', false); ?>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php
                } else {
                  ?>
                  <div class="row">
                    <div class="col-md-5">
                      <div class="form-group row">
                        <label for="Langue"
                               class="col-5 col-form-label"> <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                        <div class="col-md-5">
                          <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php
                }
              } else {
                ?>
                <div class="row">
                  <div class="col-md-5">
                    <div class="form-group row">
                      <label for="Langue"
                             class="col-5 col-form-label"> <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                      <div class="col-md-5">
                        <?php echo HTML::inputField('pages_title_' . $languages[$i]['id'], $pagetitle[$languages[$i]['id']] ?? '', 'required aria-required="true" id="' . 'pages_title_' . $languages[$i]['id'] . '" maxlength="64"', false); ?>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mt-1"></div>
                <?php
              }
            }
            ?>
          </div>
          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_PageManager->getDef('title_pages_type'); ?></div>

          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_PageManager->getDef('title_pages_type'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('title_pages_type'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::selectMenu('page_type', $page_type_statut, $page_type ?? ''); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php
          echo $CLICSHOPPING_Hooks->output('PageManager', 'CustomerGroup', null, 'display');

          if ($page_type == 4 || $page_type == 3) {
            ?>
            <div class="mt-1"></div>
            <div class="mainTitle"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_box'); ?></div>
            <div class="adminformTitle">
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_PageManager->getDef('text_pages_box'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_box'); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::selectMenu('page_box', $page_box_statut, $page_box ?? ''); ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }

          if ($page_type == 4) {
            if (empty($page_general_condition)) $page_general_condition = 0;
            ?>
            <div class="mt-1"></div>
            <div
              class="mainTitle"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_general_conditions'); ?></div>
            <div class="adminformTitle">
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_PageManager->getDef('text_pages_general_conditions'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_general_conditions'); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::selectMenu('page_general_condition', $page_general_condition_statut, $page_general_condition); ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
          ?>
          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_PageManager->getDef('title_pages_date'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_PageManager->getDef('text_pages_date_start'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_date_start'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('page_date_start', $page_date_start, null, 'date'); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_PageManager->getDef('text_pages_date_closed'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_date_closed'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('page_date_closed', $page_date_closed, null, 'date'); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_PageManager->getDef('title_divers'); ?></div>
          <div class="adminformTitle">
            <?php
            if ($page_type == 1) {
              ?>
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_PageManager->getDef('text_pages_time'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_time'); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::inputField('page_time', $page_time ?? '') . '<i>&nbsp;' . $CLICSHOPPING_PageManager->getDef('text_pages_time_seconde'); ?></i>
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-1"></div>
              <?php
            }
            ?>
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_PageManager->getDef('text_pages_sort_order'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_sort_order'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('sort_order', $sort_order ?? ''); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <?php
            if ($page_type == 1) {
              ?>
              <div class="mt-1"></div>
              <div class="alert alert-info" role="alert">
                <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_PageManager->getDef('title_help_page_manager') . '"></i></h4> ' . $CLICSHOPPING_PageManager->getDef('title_help_page_manager') ?></div>
                <div class="mt-1"></div>
                <div><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_type_information'); ?></div>
              </div>
              <?php
            }
            ?>
          </div>
        </div>
        <!-- ############################################################ //-->
        <!--               ONGLET Type de lien sur la page		          //-->
        <!-- ############################################################ //-->

        <?php
        if ($page_type == 4 || $page_type == 5 || $page_type == 6) {
          if ($page_type == 6 || $page_type == 5) {
            echo HTML::hiddenField('page_box', 3);
          }
          ?>
          <div class="tab-pane" id="tab2">
            <div class="mainTitle"><?php echo $CLICSHOPPING_PageManager->getDef('title_link'); ?></div>
            <div class="adminformTitle">
              <?php
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                ?>
                <div class="row">
                  <div class="col-md-5">
                    <div class="form-group row">
                      <label for="Lang"
                             class="col-5 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']) . ' ' . $CLICSHOPPING_PageManager->getDef('text_pages_external_link'); ?></label>
                      <div class="col-md-5">
                        <?php echo HTML::inputField('externallink_' . $languages[$i]['id'], $externallink[$languages[$i]['id']] ?? '', 'placeholder="https://www."'); ?>
                      </div>
                      <div class="mt-1"></div>
                    </div>
                  </div>
                </div>
                <?php
              }
              ?>
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="Lang"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_intext'); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::selectMenu('links_target', $links_target_statut, $links_target ?? ''); ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php
        }
        ?>
        <!-- //################################################################################################################ -->
        <!--               ONGLET Information description		          //-->
        <!-- //################################################################################################################ -->
        <div class="tab-pane" id="tab3">
          <div
            class="mainTitle"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_information_description'); ?></div>
          <div class="adminformTitle">
            <div class="accordion" id="accordionExample">
              <?php
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                if (isset($pages_html_text[$languages[$i]['id']])) {
                  $text_description = $pages_html_text[$languages[$i]['id']];
                } else {
                  $text_description = null;
                }
                ?>
                <div class="accordion-item">
                  <h2 class="accordion-header" id="heading<?php $i; ?>">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                      <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?>
                    </button>
                  </h2>
                  <?php
                  if ($i == 0) {
                    $show = ' show';
                  } else {
                    $show = '';
                  }
                  ?>

                  <div id="collapseOne" class="accordion-collapse collapse <?php echo $show; ?>"
                       aria-labelledby="heading<?php $i; ?>" data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                      <?php
                      $name = 'pages_html_text_[' . $languages[$i]['id'] . ']';
                      $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                      echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '300', str_replace('& ', '&amp; ', trim($text_description)), 'id="' . $ckeditor_id . '"');
                      ?>
                    </div>
                  </div>
                </div>
                <?php
              }
              ?>
            </div>
          </div>
          <div class="mt-1"></div>
        </div>
        <!-- //################################################################################################################ -->
        <!--               ONGLET Information seo		          //-->
        <!-- //################################################################################################################ -->
        <?php
        if ($page_type == 1 || $page_type == 3 || $page_type == 4) {
        ?>
        <div class="tab-pane" id="tab4">
          <div class="mainTitle"><?php echo $CLICSHOPPING_PageManager->getDef('text_products_page_seo'); ?></div>
          <div class="adminformTitle">
            <div class="col-md-12">
              <div class="row text-center" id="productsGoogleKeywords">
                <a href="https://www.google.fr/trends"
                   target="_blank"><?php echo CLICSHOPPING::getDef('keywords_google_trend'); ?></a>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="accordion" id="accordionExample">
              <?php
              for ($i = 0, $n = \count($languages);
              $i < $n;
              $i++) {
              $languages_id = $languages[$i]['id'];

              if (isset($bID)) {
                $title_tag = PageManagerAdmin::getPageManagerHeadTitleTag($bID, $languages_id);
                $descrition_tag = PageManagerAdmin::getPageManagerHeadDescTag($bID, $languages_id);
                $keywords_tag = PageManagerAdmin::getPageManagerHeadKeywordsTag($bID, $languages_id);
              } else {
                $title_tag = null;
                $descrition_tag = null;
                $keywords_tag = null;
              }
              ?>
              <div class="accordion-item">
                <h2 class="accordion-header" id="heading<?php $i; ?>">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne"
                          aria-expanded="true" aria-controls="collapseOne">
                    <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?>
                  </button>
                </h2>
                <?php
                if ($i == 0) {
                  $show = ' show';
                } else {
                  $show = '';
                }
                ?>
                <div id="collapseOne" class="accordion-collapse collapse <?php echo $show; ?>"
                     aria-labelledby="heading<?php $i; ?>" data-bs-parent="#accordionExample">
                  <div class="accordion-body">

                    <div class="row" id="pageManagerSeoTitle<?php echo $languages_id; ?>">
                      <div class="col-md-10">
                        <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                          <label for="<?php echo $CLICSHOPPING_PageManager->getDef('text_products_page_title'); ?>"
                                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_products_page_title'); ?></label>
                          <div class="col-md-7 input-group"
                               id="page_manager_head_title_tag<?php echo $languages_id; ?>">
                            <?php echo HTML::inputField('page_manager_head_title_tag_' . $languages_id, $title_tag, 'maxlength="70" size="77" id="page_manager_head_title_tag_' . $languages_id . '"', false); ?>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="mt-1"></div>
                    <div class="row" id="pageManagerSeoDescription<?php echo $languages_id; ?>">
                      <div class="col-md-6">
                        <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                          <label
                            for="<?php echo $CLICSHOPPING_PageManager->getDef('text_products_header_description'); ?>"
                            class="col-1 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_products_header_description'); ?></label>
                          <div class="col-md-8 input-group" id="page_manager_head_desc_tag<?php echo $languages_id; ?>">
                            <?php echo HTML::textAreaField('page_manager_head_desc_tag_' . $languages_id, $descrition_tag, '110', '5', 'id="page_manager_head_desc_tag_' . $languages_id . '"'); ?>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="mt-1"></div>
                    <div class="row" id="pageManagerSeoKeywords<?php echo $languages_id; ?>">
                      <div class="col-md-10">
                        <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                          <label for="<?php echo $CLICSHOPPING_PageManager->getDef('text_products_keywords'); ?>"
                                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_products_keywords'); ?></label>
                          <div class="col-md-7 input-group"
                               id="page_manager_head_keywords_tag<?php echo $languages_id; ?>">
                            <?php echo HTML::inputField('page_manager_head_keywords_tag_' . $languages_id, $keywords_tag, 'maxlength="70" size="77" id="page_manager_head_keywords_tag_' . $languages_id . '"', false); ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php
                }
                }
                ?>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_PageManager->getDef('title_help_seo') . '"></i></h4> ' . $CLICSHOPPING_PageManager->getDef('title_help_seo') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_PageManager->getDef('help_seo'); ?></div>
          </div>
        </div>
      </div>

      <div class="mt-1"></div>
      <?php
      //***********************************
      // extension
      //***********************************
      echo $CLICSHOPPING_Hooks->output('PageManager', 'PageTab', null, 'display');
      ?>
    </div>
  </div>
</div>
</form>
