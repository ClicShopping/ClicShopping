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

class Contact extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_PageManager = Registry::get('PageManager');

// select multiple contact for the form
    if (!empty(\defined('CONTACT_DEPARTMENT_LIST') ? CONTACT_DEPARTMENT_LIST : '')) {
      $send_to_array = [];
      $contact_department_list = explode(",", CONTACT_DEPARTMENT_LIST);

      if (\is_array($contact_department_list)) {
        foreach (explode(",", CONTACT_DEPARTMENT_LIST) as $k => $v) {
          $send_to_array[] = [
            'id' => $k,
            'text' => preg_replace('/\<[^*]*/', '', $v)
          ];
        }
      }

      $_POST['send_to_array'] = $send_to_array;
    }

// templates
    $this->page->setFile('contact.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('contact');
    $this->page->data['action'] = 'Process';
//language
    $CLICSHOPPING_PageManager->loadDefinitions('Sites/Shop/Contact/contact');
    $CLICSHOPPING_PageManager->loadDefinitions('Sites/Shop/main');

    $CLICSHOPPING_Breadcrumb->add($CLICSHOPPING_PageManager->getDef('navbar_title'), CLICSHOPPING::link(null, 'Info&Contact'));
  }
}