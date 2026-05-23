<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class AdministratorAdmin
{
  /**
   * Counts the total number of users in the administrators table.
   *
   * @return int The total number of users.
   */
  public static function CountUser(): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qname = $CLICSHOPPING_Db->prepare('select count(id) as count
                                        from :table_administrators
                                        ');
    $Qname->execute();

    return $Qname->value('count');
  }

  /**
   * Retrieves all administrators from the database, including their ID, name, and first name.
   *
   * @return array An array containing all administrators with their respective ID, name, and first name.
   */
  public static function getAllUserAmin(): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qname = $CLICSHOPPING_Db->prepare('select id,
                                               name,
                                               first_name
                                        from :table_administrators
                                        ');
    $Qname->execute();

    $check_array = $Qname->fetchAll();

    return $check_array;
  }


  /*
   * check is the admin session and access
   *
   * @return array|bool
   */
  public static function hasUserAccess(): array|bool
  {
    $check = true;
    if (!isset($_SESSION['admin']['id'], $_SESSION['admin']['access'])) {
      http_response_code(403);
      return json_encode(['error' => 'Access denied']);
    }

    return $check;
  }

  /**
   * Checks if the current admin user has access rights based on session data and database validation.
   * If the user does not have the required access or session data is missing, redirects to the index page and displays a warning message.
   *
   * @return void
   */
  public static function checkUserAccess(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_SESSION['admin']['id'], $_SESSION['admin']['access'])) {
      $Qcheck = $CLICSHOPPING_Db->prepare('select id
                                          from :table_administrators
                                          where id = :id
                                          and access = :access
                                          and status = 1
                                          ');
      $Qcheck->bindInt(':id', $_SESSION['admin']['id']);
      $Qcheck->bindInt(':access', 1);
      $Qcheck->execute();

      if ($Qcheck->fetch() === false) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('no_access_warning'), 'warning');
        HTTP::redirect('index.php');
      }
    } else {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('no_access_warning'), 'warning');
      HTTP::redirect('index.php');
    }
  }

  /**
   * Retrieves the administrator ID based on the current session's access details.
   *
   * @return int Returns the administrator ID if a matching record is found with the provided session access and status; otherwise, it returns 0 or an empty result.
   */
  public static function getAdminIdByAccess(): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->prepare('select id
                                          from :table_administrators
                                          where id = :id
                                          and access = :access_id
                                          and status = 1
                                          ');
    $Qcheck->bindInt(':id', $_SESSION['admin']['id']);
    $Qcheck->bindInt(':access_id', $_SESSION['admin']['access']);
    $Qcheck->execute();

    $admin_id = $Qcheck->valueInt('id');

    return $admin_id;
  }

  /**
   * Retrieves the full name of an administrator by their ID.
   *
   * @param int $id The ID of the administrator to retrieve the name for.
   * @return string The full name of the administrator, consisting of their first name and last name.
   */
  public static function getAdminNameById(int $id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qname = $CLICSHOPPING_Db->prepare('select name,
                                               first_name
                                        from :table_administrators
                                        where id = :id
                                        ');
    $Qname->bindint(':id', $id);
    $Qname->execute();

    return $Qname->value('first_name') . ' ' . $Qname->value('name');
  }

  /**
   * Retrieves the name of the currently logged-in admin user.
   *
   * This method checks the session data to find the admin user's username and queries the database
   * to fetch their first name and last name. If no admin is logged in, returns a default string.
   *
   * @return string The full name of the logged-in admin user or a default string if no admin is logged in.
   */
  public static function getUserAdmin(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (isset($_SESSION['admin'])) {
      $username = array($_SESSION['admin']);
      $username = $username[0]['username'];

      $Qlogins = $CLICSHOPPING_Db->prepare('select a.name,
                                                     a.first_name
                                              from :table_action_recorder ar,
                                                   :table_administrators a
                                              where  ar.user_id = a.id
                                              and ar.module = :module
                                              and ar.user_name = :user_name
                                              limit 1
                                             ');

      $Qlogins->bindValue(':module', 'ar_admin_login');
      $Qlogins->bindValue(':user_name', $username);

      $Qlogins->execute();

      $administrator = HTML::output($Qlogins->value('first_name') . ' ' . $Qlogins->value('name'));
    } else {
      $administrator = 'Shop action';
    }

    return $administrator;
  }

  /**
   * Retrieves the email address of the currently logged-in admin user.
   *
   * @return string The email address of the admin user.
   */
  public static function getAdminUserEmail(): string
  {
    $username = array($_SESSION['admin']);
    $username = $username[0]['username'];

    return $username;
  }

  /**
   * Retrieves the administrative user ID for the currently logged-in admin user.
   *
   * The method checks the session data to identify the currently logged-in admin user,
   * then queries the database to fetch the associated user ID based on the `ar_admin_login` module action recorder.
   * If no admin user is logged in, the return value will be `null`.
   *
   * @return int|null Returns the administrator's ID if available, or `null` if no admin is logged in.
   */
  public static function getUserAdminId(): ?int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (isset($_SESSION['admin'])) {
      $username = array($_SESSION['admin']);
      $username = $username[0]['username'];

      $Qlogins = $CLICSHOPPING_Db->prepare('select a.id
                                              from :table_action_recorder ar,
                                                   :table_administrators a
                                              where  ar.user_id = a.id
                                              and ar.module = :module
                                              and ar.user_name = :user_name
                                              limit 1
                                             ');

      $Qlogins->bindValue(':module', 'ar_admin_login');
      $Qlogins->bindValue(':user_name', $username);

      $Qlogins->execute();

      $administrator = $Qlogins->valueInt('id');
    } else {
      $administrator = null;
    }

    return $administrator;
  }

  /**
   * Retrieves an array of administrator rights options.
   *
   * @param string $default Optional default text to include in the administrator rights options.
   * @return array An array of administrator rights options, each containing an 'id' and 'text' key.
   */

  public static function getAdministratorRight(string $default = ''): array
  {
    $administrator_right_array = [];

    if ($default) {
      $administrator_right_array[] = [
        'id' => '',
        'text' => $default
      ];
    }

    $administrator_right_array[] = ['id' => '1', 'text' => CLICSHOPPING::getDef('text_all_rights_admin')];
    $administrator_right_array[] = ['id' => '2', 'text' => CLICSHOPPING::getDef('text_rights_employee')];
    $administrator_right_array[] = ['id' => '3', 'text' => CLICSHOPPING::getDef('text_rights_visitor')];

    return $administrator_right_array;
  }

  /**
   * Retrieves the administrator menu right options.
   *
   * @param string $default The default text to include as the first option, if provided.
   * @return array An array of administrator menu right options, each containing an 'id' and 'text' key.
   */

  public static function getAdministratorMenuRight(string $default = ''): array
  {

    $administrator_right_array = [];

    if ($default) {
      $administrator_right_array[] = [
        'id' => '',
        'text' => $default
      ];
    }

    $administrator_right_array[] = array('id' => '0', 'text' => CLICSHOPPING::getDef('text_all_right'));
    $administrator_right_array[] = array('id' => '1', 'text' => CLICSHOPPING::getDef('text_all_rights_admin'));
    $administrator_right_array[] = array('id' => '2', 'text' => CLICSHOPPING::getDef('text_rights_employee'));
    $administrator_right_array[] = array('id' => '3', 'text' => CLICSHOPPING::getDef('text_rights_visitor'));

    return $administrator_right_array;
  }
}