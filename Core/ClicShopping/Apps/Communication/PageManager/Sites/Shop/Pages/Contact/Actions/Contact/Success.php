<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sites\Shop\Pages\Contact\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Success extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_PageManager = Registry::get('PageManager');

// templates
    $this->page->setFile('success.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('contact_success');
    $this->page->data['action'] = 'Success';
//language
    $CLICSHOPPING_PageManager->loadDefinitions('Sites/Shop/ContactSuccess/contact_success');

    $CLICSHOPPING_Breadcrumb->add($CLICSHOPPING_PageManager->getDef('navbar_title_1'), CLICSHOPPING::link(null, 'Info&Contact&Success'));
  }
}