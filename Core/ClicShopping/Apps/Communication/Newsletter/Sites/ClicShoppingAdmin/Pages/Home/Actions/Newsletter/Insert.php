<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Communication\Newsletter\Sites\ClicShoppingAdmin\Pages\Home\Actions\Newsletter;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {
    $CLICSHOPPING_Newsletter = Registry::get('Newsletter');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    $newsletter_module = basename(HTML::sanitize($_POST['module']));

    $title = HTML::sanitize($_POST['title']);
    $content = $_POST['message'];
    $customers_group_id = HTML::sanitize($_POST['customers_group_id']);
    $languages_newsletter_id = HTML::sanitize($_POST['languages_id']);
    $newsletters_accept_file = HTML::sanitize($_POST['newsletters_accept_file']);

    $newsletter_error = false;

    if (empty($newsletter_module) && $newsletter_module != 'Newsletter') {
      $CLICSHOPPING_Newsletter->redirect('Newsletter&Newsletter&page=' . $page);
    }

    if (empty($newsletter_module) && $newsletter_module != 'ProductNotification') {
      $CLICSHOPPING_Newsletter->redirect('Newsletter&Newsletter&page=' . $page);
    }

    $allowed = array_map(function ($v) {
      return basename($v, '.php');
    }, glob(CLICSHOPPING::BASE_DIR . 'Apps/Communication/Newsletter/Module/ClicShoppingAdmin/Newsletter/*.php'));

    if (!\in_array($newsletter_module, $allowed)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Newsletter->getDef('error_newsletter_module_not_exists'), 'danger');
      $newsletter_error = true;
    }

    if ($newsletters_accept_file == 'on') {
      $newsletters_accept_file = 0;
    } else {
      $newsletters_accept_file = 1;
    }

    if (empty($title)) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Newsletter->getDef('error_newsletter_title'), 'error');
      $newsletter_error = true;
    }

    if ((empty($newsletter_module)) || !is_file(CLICSHOPPING::BASE_DIR . 'Apps/Communication/Newsletter/Module/ClicShoppingAdmin/Newsletter/' . $newsletter_module . '.php')) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Newsletter->getDef('error_newsletter_module'), 'error');
      $newsletter_error = true;
    }

    if ($newsletter_error === false) {
      $sql_data_array = [
        'title' => $title,
        'content' => $content,
        'module' => $newsletter_module,
        'languages_id' => (int)$languages_newsletter_id,
        'customers_group_id' => (int)$customers_group_id,
        'newsletters_accept_file' => (int)$newsletters_accept_file
      ];

      $sql_data_array['date_added'] = 'now()';
      $sql_data_array['status'] = 0;
      $sql_data_array['locked'] = 0;

      $CLICSHOPPING_Newsletter->db->save('newsletters', $sql_data_array);

      $newsletter_id = $CLICSHOPPING_Newsletter->db->lastInsertId();

      $CLICSHOPPING_Hooks->call('Newsletter', 'Insert');

      $CLICSHOPPING_Newsletter->redirect('Newsletter&page=' . $page . '&nID=' . $newsletter_id);
    }
  }
}