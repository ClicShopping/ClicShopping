<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TemplateEmail\Sites\ClicShoppingAdmin\Pages\Home\Actions;

class Edit extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $this->page->setFile('edit.php');
    $this->page->data['action'] = 'Update';
  }
}