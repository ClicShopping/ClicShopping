<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\BannerManager\BannerManager as BannerManagerApp;

class DeleteConfirm implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   *
   */
  public function __construct()
  {
    if (!Registry::exists('BannerManager')) {
      Registry::set('BannerManager', new BannerManagerApp());
    }

    $this->app = Registry::get('BannerManager');
  }

  /**
   * Deletes a banner based on the given language ID.
   *
   * @param int $id The ID of the language to delete banners for.
   * @return void
   */
  private function delete(int $id)
  {
    if (!\is_null($id)) {
      $this->app->db->delete('banners', ['languages_id' => $id]);
    }
  }

  /**
   * Executes the main functionality of the method. Checks for a specific confirmation parameter
   * in the request and performs a delete operation based on the provided ID if the parameter exists.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['DeleteConfirm'])) {
      $id = HTML::sanitize($_GET['lID']);
      $this->delete($id);
    }
  }
}