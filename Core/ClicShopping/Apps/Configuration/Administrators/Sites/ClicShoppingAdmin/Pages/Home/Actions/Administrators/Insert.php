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

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Administrators');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $name = HTML::sanitize($_POST['name']);
    $first_name = HTML::sanitize($_POST['first_name']);
    $username = HTML::sanitize($_POST['username']);
    $password = HTML::sanitize($_POST['password']);
    $access = HTML::sanitize($_POST['access_administrator']);
    $email_verification =  isset($_POST['email_verification']) ? HTML::sanitize($_POST['email_verification']) : 1;

    if (empty($access)) {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_administrator_select'), 'error');
      $this->app->redirect('Administrators.php&Insert');
    }

    $Qcheck = $this->app->db->get('administrators', 'id', ['user_name' => $username], null, 1);

    if (!empty($username)) {
      if (!$Qcheck->check()) {

      $inset_array =  [
        'user_name' => $username,
        'user_password' => Hash::encrypt($password),
        'name' => $name,
        'first_name' => $first_name,
        'access' => $access,
        'email_verification' => $email_verification,
        'date_added' => 'now()'
      ];

        $this->app->db->save('administrators', $inset_array);
      }
    } else {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_administrator_exists'), 'error');
    }

    $this->app->redirect('Administrators');
  }
}