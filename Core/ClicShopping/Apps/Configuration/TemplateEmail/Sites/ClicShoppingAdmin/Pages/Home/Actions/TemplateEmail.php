<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TemplateEmail\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class TemplateEmail extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_TemplateEmail = Registry::get('TemplateEmail');

    $this->page->setFile('template_email.php');
    $this->page->data['action'] = 'TemplateEmail';

    $CLICSHOPPING_TemplateEmail->loadDefinitions('Sites/ClicShoppingAdmin/TemplateEmail');
  }
}