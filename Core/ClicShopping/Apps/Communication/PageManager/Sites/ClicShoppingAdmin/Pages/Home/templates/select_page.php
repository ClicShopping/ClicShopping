<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_PageManager = Registry::get('PageManager');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$languages = $CLICSHOPPING_Language->getLanguages();

$page_type_contact = 'true';

$Qpages = $CLICSHOPPING_PageManager->db->prepare('select page_type
                                                     from :table_pages_manager
                                                     where page_type = 3
                                                    ');

$Qpages->execute();

if ($Qpages->fetch() !== false) {
  while ($Qpages->fetch()) {
    if ($Qpages->valueInt('page_type') == '3') {
      $page_type_contact = 'false';
    }
  }
}

$page_type_statut = [];

$page_type_statut[] = ['id' => '0',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_select')
];

$page_type_statut[] = ['id' => '1',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_introduction_page')
];

$page_type_statut[] = ['id' => '2',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_main_page')
];

if ($page_type_contact == 'true') {
  $page_type_statut[] = ['id' => '3',
    'text' => $CLICSHOPPING_PageManager->getDef('page_manager_contact_us')
  ];
}

$page_type_statut[] = ['id' => '4',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_informations')
];

$page_type_statut[] = ['id' => '5',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_menu_header')
];

$page_type_statut[] = ['id' => '6',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_menu_footer')
];

// Type de la page demande dans le menu deroulant pour le choix d'affichage du text dans une des 2 boxes
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

// Select if the page is general condition page or or not
$page_general_condition_statut = [];
$page_general_condition_statut[] = ['id' => '0',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_text_no')
];

$page_general_condition_statut[] = ['id' => '1',
  'text' => $CLICSHOPPING_PageManager->getDef('page_manager_text_yes')
];
?>
<div class="contentBody">
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/page_manager.gif', $CLICSHOPPING_PageManager->getDef('heading_title_new'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_PageManager->getDef('heading_title_new'); ?></span>
          <span class="col-md-6 text-end">
<?php
echo HTML::form('page_manager', $CLICSHOPPING_PageManager->link('Edit'), 'post', 'enctype="multipart/form-data"');
echo HTML::button(CLICSHOPPING::getDef('button_cancel'), null, $CLICSHOPPING_PageManager->link('PageManager&PageManager' . (isset($page) ? 'page=' . $page . '&' : '') . (!empty($bID) and $bID != '' ? 'bID=' . $bID : '')), 'warning') . '&nbsp;';
echo HTML::button(CLICSHOPPING::getDef('button_new'), null, null, 'success', null, null);
?>
         </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="mainTitle"><?php echo $CLICSHOPPING_PageManager->getDef('title_pages_choose'); ?></div>
  <div class="adminformTitle">
    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_PageManager->getDef('text_pages_choose'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_PageManager->getDef('text_pages_choose'); ?></label>
          <div class="col-md-3">
            <?php echo HTML::selectMenu('page_type', $page_type_statut, null, 'onchange="this.form.submit();"'); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
</div>
<script>
  $(document).ready(function () {
    $('#select').change(function () {
      location.href = $(this).val();
    });
  });
</script>