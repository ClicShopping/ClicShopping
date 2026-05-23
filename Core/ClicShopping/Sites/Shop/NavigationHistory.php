<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use function array_slice;
use function count;
use function in_array;
use function is_array;
/**
 * Class NavigationHistory
 * Handles the navigation history of web pages for the current user's session. Tracks visited pages
 * and allows for navigation back to previous pages, including snapshot management for redirecting to specific pages.
 */
class NavigationHistory
{

  public array $path = [];
  public array $snapshot = [];

  /**
   * Constructor for initializing NavigationHistory.
   *
   * @param bool $add_current_page Determines whether the current page should be added to the navigation history.
   * @return void
   */
  public function __construct(bool $add_current_page = false)
  {
    if (isset($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['data']) && is_array($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['data']) && !empty($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['data'])) {
      $this->path =& $_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['data'];
    }

    if (isset($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['snapshot']) && is_array($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['snapshot']) && !empty($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['snapshot'])) {
      $this->snapshot =& $_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['snapshot'];
    }

    if ($add_current_page === true) {
      $this->addCurrentPage();
    }
  }

  /**
   * Adds the current page to the navigation history. This includes details such as the application, action,
   * request type (mode), and GET/POST data. Ensures the navigation history is updated without duplicating entries
   * for the same application and action combination. Also verifies the validity of actions.
   *
   * @return void
   */
  public function addCurrentPage()
  {
    $action_counter = 0;
    $application_key = null;
    $action = [];

    foreach ($_GET as $key => $value) {
      if (!isset($application_key) && ($key == CLICSHOPPING::getSiteApplication())) {
        $application_key = $action_counter;

        $action_counter++;

        continue;
      }

      $action[] = [$key => $value];

      if ($this->siteApplicationActionExists(implode('\\', array_keys($action))) === false) {
        array_pop($action);

        break;
      }

      $action_counter++;
    }

    $action_get = http_build_query($action);

    if (is_array($this->path)) {
      for ($i = 0, $n = count($this->path); $i < $n; $i++) {
        if (($this->path[$i]['application'] == CLICSHOPPING::getSiteApplication()) && ($this->path[$i]['action'] == $action_get)) {
          array_splice($this->path, $i);
          break;
        }
      }
    }

    $this->path[] = [
      'application' => CLICSHOPPING::getSiteApplication(),
      'action' => $action_get,
      'mode' => HTTP::getRequestType(),
      'get' => array_slice($_GET, $action_counter),
      'post' => $_POST
    ];

    if (!isset($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['data'])) {
      $_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['data'] = $this->path;
    }
  }

  /**
   * Removes the current page from the navigation path. If the path becomes empty, it resets the path.
   *
   * @return void
   */
  public function removeCurrentPage()
  {
    array_pop($this->path);

    if (empty($this->path)) {
      $this->resetPath();
    }
  }

  /**
   * Determines if a path exists in the internal path stack based on the given position.
   *
   * @param int $back The position from the end of the path stack to check. Defaults to 1.
   *                  Must be a numeric value greater than or equal to 1.
   * @return bool Returns true if a path exists at the specified position, otherwise false.
   */
  public function hasPath($back = 1)
  {
    if ((is_numeric($back) === false) || (is_numeric($back) && ($back < 1))) {
      $back = 1;
    }

    return isset($this->path[count($this->path) - $back]);
  }

  /**
   * Generates a URL based on a specific location in the navigation path.
   *
   * @param int $back The number of steps back in the navigation path to retrieve the URL from. Defaults to 1. If the value
   *                  is non-numeric, less than 1, or invalid, it defaults to 1.
   *
   * @return string The generated URL corresponding to the specified point in the navigation path.
   */
  public function getPathURL($back = 1)
  {
    if ((is_numeric($back) === false) || (is_numeric($back) && ($back < 1))) {
      $back = 1;
    }

    $back = count($this->path) - $back;

    return CLICSHOPPING::link(null, $this->path[$back]['application'] . '&' . $this->path[$back]['action'] . '&' . $this->parseParameters($this->path[$back]['get']));
  }

  /**
   * Sets a snapshot for the navigation history.
   *
   * @param array|null $page If provided, expects an associative array containing information about 'application', 'action',
   *                         'mode', 'get', and 'post'. If null, sets the snapshot to the last entry in the navigation path.
   * @return void
   */
  public function setSnapshot($page = null)
  {
    if (isset($page) && is_array($page)) {
      $this->snapshot = [
        'application' => $page['application'],
        'action' => $page['action'],
        'mode' => $page['mode'],
        'get' => $page['get'],
        'post' => $page['post']
      ];
    } else {
      $this->snapshot = $this->path[count($this->path) - 1];
    }

    if (!isset($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['snapshot'])) {
      $_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['snapshot'] = $this->snapshot;
    }
  }

  /**
   * Checks if a snapshot exists.
   *
   * @return bool Returns true if a snapshot is present, otherwise false.
   */
  public function hasSnapshot()
  {
    return !empty($this->snapshot);
  }

  /**
   * Retrieves the value of a specific key from the snapshot array.
   *
   * @param string $key The key to retrieve the value for.
   * @return mixed|null The value associated with the provided key, or null if the key does not exist.
   */
  public function getSnapshot($key)
  {
    if (isset($this->snapshot[$key])) {
      return $this->snapshot[$key];
    }
  }

  /**
   * Generates the URL based on the snapshot information if available.
   * If no snapshot exists, it redirects to the default target.
   *
   * @return string The generated URL for the snapshot or the default target.
   */
  public function getSnapshotURL()
  {
    if ($this->hasSnapshot()) {
      $url = $this->snapshot['application'];

      if (!empty($this->snapshot['action'])) {
        $url .= '&' . $this->snapshot['action'];
      }

      $params = $this->parseParameters($this->snapshot['get']);
      if (!empty($params)) {
        $url .= '&' . $params;
      }

      $target = CLICSHOPPING::redirect(null, $url);
    } else {
      $target = CLICSHOPPING::redirect();
    }

    return $target;
  }

  /**
   * Redirects to the URL stored in the snapshot and resets the snapshot data.
   *
   * @return string The URL to which the redirection should occur.
   */
  public function redirectToSnapshot()
  {
    $target = $this->getSnapshotURL();

    $this->resetSnapshot();

    return $target;
  }

  /**
   * Resets the navigation path and clears any saved navigation history data in the session.
   *
   * @return void
   */
  public function resetPath()
  {
    $this->path = [];

    if (isset($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['data'])) {
      unset($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['data']);
    }
  }

  /**
   * Resets the snapshot by clearing the local snapshot property and removing
   * any snapshot data stored in the session.
   *
   * @return void
   */
  public function resetSnapshot()
  {
    $this->snapshot = [];

    if (isset($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['snapshot'])) {
      unset($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']['snapshot']);
    }
  }


  /**
   * Resets the navigation history by clearing the current path and snapshot,
   * and removing the NavigationHistory entry from the session.
   *
   * @return void
   */
  public function reset()
  {
    $this->resetPath();
    $this->resetSnapshot();

    if (isset($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory'])) {
      unset($_SESSION[CLICSHOPPING::getSite()]['NavigationHistory']);
    }
  }

  /**
   * Parses an array and converts it into a query string, excluding specified keys.
   *
   * @param array $array The array of parameters to parse into a query string.
   * @param array $additional_exclude An array of keys to exclude from the query string, in addition to the default exclusion list.
   * @return string A query string representation of the input array, excluding specified keys.
   */
  protected function parseParameters($array, $additional_exclude = array())
  {
    $exclude = array('x', 'y', Registry::get('Session')->getName());

    if (is_array($additional_exclude) && !empty($additional_exclude)) {
      $exclude = array_merge($exclude, $additional_exclude);
    }

    $string = '';

    if (is_array($array) && !empty($array)) {
      foreach ($array as $key => $value) {
        if (!in_array($key, $exclude)) {
          $string .= $key . '=' . $value . '&';
        }
      }

      $string = substr($string, 0, -1);
    }

    return $string;    
  }

  /**
   * Checks if a specific site application action class exists.
   *
   * @param string $action The name of the action to check for existence.
   * @return bool Returns true if the action class exists, false otherwise.
   */
  protected function siteApplicationActionExists($action)
  {
    return class_exists('ClicShopping\\OM\\Site\\Shop\\Pages\\' . CLICSHOPPING::getSiteApplication() . '\\Actions\\' . $action);
  }
}