<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TemplateEmail\Sites\ClicShoppingAdmin\Pages\Home\Actions;

class TemplateEmail extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $this->page->setFile('template_email.php');
    $this->page->data['action'] = 'TemplateEmail';
  }
}