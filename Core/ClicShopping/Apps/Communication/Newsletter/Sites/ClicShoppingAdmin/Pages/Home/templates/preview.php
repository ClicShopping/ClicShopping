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

  $CLICSHOPPING_Newsletter = Registry::get('Newsletter');
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/newsletters.gif', $CLICSHOPPING_Newsletter->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Newsletter->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_Newsletter->getDef('button_back'), null, $CLICSHOPPING_Newsletter->link('Newsletter&page=' . (int)$_GET['page'] . '&nID=' . (int)$_GET['nID']), 'primary'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
<?php
// --------------------------------------------------------
//       Previsualisation
// --------------------------------------------------------
  if (isset($_GET['nID'])) {
    $nID = HTML::sanitize($_GET['nID']);

    $Qnewsletter = $CLICSHOPPING_Newsletter->db->get('newsletters', ['title',
                                                                    'content',
                                                                    'module'
                                                                    ], [
                                                                      'newsletters_id' => (int)$nID
                                                                    ]
                                              );

    $nInfo = new ObjectInfo($Qnewsletter->toArray());
?>
  <!-- Effacer nl2br qui provoque des br suplémentaire à chaque ligne //-->
  <div><?php echo $nInfo->content; ?></div>
<?php
  }
?>