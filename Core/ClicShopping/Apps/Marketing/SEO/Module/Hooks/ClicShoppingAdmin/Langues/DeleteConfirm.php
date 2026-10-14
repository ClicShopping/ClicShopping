<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\SEO\SEO as SEOApp;

class DeleteConfirm implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the SEO application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('SEO')) {
      Registry::set('SEO', new SEOApp());
    }

    $this->app = Registry::get('SEO');
  }

  /**
   * Deletes a record from the 'seo' table based on the specified language ID.
   *
   * @param int $id The identifier of the language to delete.
   * @return void
   */
  private function delete(int $id)
  {
    if (!\is_null($id)) {
      $this->app->db->delete('seo', ['language_id' => $id]);
    }
  }

  /**
   * Executes the delete operation if the 'DeleteConfirm' parameter is set in the GET request.
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