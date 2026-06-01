<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager\Sites\ClicShoppingAdmin\Pages\Home\Actions\BannerManager;

use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {
    $CLICSHOPPING_BannerManager = Registry::get('BannerManager');
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (!isset($_GET['bID'])) {
      $CLICSHOPPING_BannerManager->redirect('BannerManager&page=' . $page);
    }

    $banners_id = (int)HTML::sanitize($_GET['bID']);

    if (isset($_POST['delete_image']) && ($_POST['delete_image'] == 'on')) {

      $Qbanner = $CLICSHOPPING_BannerManager->db->get('banners', 'banners_image', ['banners_id' => (int)$banners_id]);

// delete image
      if (!empty($Qbanner->value('banners_image')) && is_file($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages() . $Qbanner->value('banners_image'))) {
        if (FileSystem::isWritable($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages() . $Qbanner->value('banners_image'))) {
          unlink($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages() . $Qbanner->value('banners_image'));
        } else {
          $CLICSHOPPING_MessageStack->add($CLICSHOPPING_BannerManager->getDef('error_image_is_not_writable'), 'error');
        }
      } else {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_BannerManager->getDef('error_image_does_not_exist'), 'error');
      }
    }

    $CLICSHOPPING_BannerManager->db->delete('banners', ['banners_id' => (int)$banners_id]);
    $CLICSHOPPING_BannerManager->db->delete('banners_history', ['banners_id' => (int)$banners_id]);

    $CLICSHOPPING_Hooks->call('BannerManager', 'RemoveBanner');

    $CLICSHOPPING_MessageStack->add($CLICSHOPPING_BannerManager->getDef('success_banner_removed'), 'success');

    $CLICSHOPPING_BannerManager->redirect('BannerManager&page=' . $page);
  }
}