<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Administrators\Sites\ClicShoppingAdmin\Pages\Home\Actions\Administrators;

use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Administrators');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Mail = Registry::get('Mail');

    $name = HTML::sanitize($_POST['name']);
    $first_name = HTML::sanitize($_POST['first_name']);
    $username = HTML::sanitize($_POST['username']);
    $password = HTML::sanitize($_POST['password']);
    $access = HTML::sanitize($_POST['access_administrator']);
    $email_verification =  isset($_POST['email_verification']) ? HTML::sanitize($_POST['email_verification']) : 0;

    if (empty($access)) {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_administrator_select'), 'error');
      if (\is_null($_GET['aID'])) {
        $this->app->redirect('Edit&aID=' . $_GET['aID']);
      }
    }

    $Qcheck = $this->app->db->get('administrators', ['id',
      'user_name',
      'first_name',
      'name',
      'access'
    ], [
        'id' => (int)$_GET['aID']
      ]
    );

// update username in current session if changed
    if (($Qcheck->valueInt('id') === $_SESSION['admin']['id']) && ($username !== $_SESSION['admin']['username'])) {
      $_SESSION['admin']['username'] = $username;
      $_SESSION['admin']['name'] = $name;
      $_SESSION['admin']['first_name'] = $first_name;
      $_SESSION['admin']['access'] = $access;
    }

    $sql_array = [
      'user_name' => $username,
      'name' => $name,
      'first_name' => $first_name,
      'access' => $access,
      'email_verification' => $email_verification,
      'last_modified' => 'now()'
    ];

    $this->app->db->save('administrators', $sql_array, ['id' => (int)$_GET['aID']]);

    if (!empty($password)) {
      $this->app->db->save('administrators', [
        'user_password' => Hash::encrypt($password),
      ], [
          'id' => (int)$_GET['aID']
        ]
      );
    }

// mail report
    $to_addr = STORE_OWNER_EMAIL_ADDRESS;
    $from_name = STORE_NAME;
    $from_addr = STORE_OWNER_EMAIL_ADDRESS;
    $to_name = STORE_OWNER_EMAIL_ADDRESS;
    $subject = $this->app->getDef('report_password_change_subject', ['username' => $username]);

    $CLICSHOPPING_Mail->addHtml($this->app->getDef('report_password_change_text', ['username' => $username]));
    $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

    $this->app->redirect('Administrators&aID=' . (int)$_GET['aID']);
  }
}