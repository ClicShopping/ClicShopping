<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * The MessageStack class manages information messages to be displayed.
 * Messages that are shown are automatically removed from the stack.
 */

namespace ClicShopping\OM;

use function count;
use function in_array;
use function is_array;

class MessageStack
{
  protected array $data = [];

  /**
   * Initializes the class and sets up session handling and hooks.
   * Registers a shutdown function to save messages to the session,
   * restores messages from the session upon initialization,
   * and sets up hooks for session and account handling.
   *
   * @return void
   */
  public function __construct()
  {
    register_shutdown_function(function () {
      if (!empty($this->data)) {
        $_SESSION['MessageStack_Data'] = $this->data;
      }
    });

    if (isset($_SESSION['MessageStack_Data']) && is_array($_SESSION['MessageStack_Data'])) {
      foreach ($_SESSION['MessageStack_Data'] as $group => $messages) {
        foreach ($messages as $message) {
          $this->add($message['text'], $message['type'], $group);
        }
      }

      unset($_SESSION['MessageStack_Data']);
    }


    Registry::get('Hooks')->watch('Session', 'StartAfter', 'execute', function () {
      if (isset($_SESSION['MessageStack_Data']) && !empty($_SESSION['MessageStack_Data'])) {
        foreach ($_SESSION['MessageStack_Data'] as $group => $messages) {
          foreach ($messages as $message) {
            $this->add($message['text'], $message['type'], $group);
          }
        }

        unset($_SESSION['MessageStack_Data']);
      }

    });

    Registry::get('Hooks')->watch('Account', 'LogoutAfter', 'execute', function () {
      $this->reset('main');
    });
  }

  /**
   * Adds a message to a specified group with a defined type.
   *
   * @param string $message The message text to be added.
   * @param string $type The type of the message (default is 'error', automatically converted to 'danger').
   * @param string $group The group to which the message is added (default is 'main').
   * @return void
   */
  public function add(string $message, string $type = 'error', string $group = 'main')
  {
    switch ($type) {
      case 'error':
        $type = 'danger';
        break;
    }

    $stack = [
      'text' => $message,
      'type' => $type
    ];

    if (!$this->exists($group) || !in_array($stack, $this->data[$group])) {
      $this->data[$group][] = $stack;
    }
  }

  /**
   * Resets the data, either for a specific group or entirely if no group is provided.
   *
   * @param string|null $group The group to reset. If null, all data will be reset.
   * @return void
   */
  public function reset(?string $group = null)
  {
    if (isset($group)) {
      if ($this->exists($group)) {
        unset($this->data[$group]);
      }
    } else {
      $this->data = [];
    }
  }

  /**
   * Checks if a specific group exists in the data or if the data is not empty.
   *
   * @param string|null $group The name of the group to check for existence. If null, checks if the data is not empty.
   * @return bool Returns true if the specified group exists or if the data is not empty, otherwise false.
   */
  public function exists(?string $group = null)
  {
    if (isset($group)) {
      return array_key_exists($group, $this->data);
    }

    return !empty($this->data);
  }

  /**
   * Determines if the data container has any content.
   *
   * @return bool Returns true if the data container is not empty, false otherwise.
   */
  public function hasContent()
  {
    return !empty($this->data);
  }

  /**
   * Retrieves and formats alert messages for a specified group.
   *
   * @param string $group The group identifier to retrieve alert messages from.
   * @return string The formatted alert messages as an HTML string. Returns an empty string if no messages exist for the specified group.
   */
  public function get(string $group): string
  {
    $result = '';

    if ($this->exists($group)) {
      $data = [];

      if (is_array($this->data[$group])) {
        foreach ($this->data[$group] as $message) {
          $data['alert-' . $message['type']][] = $message['text'];
        }

        foreach ($data as $type => $messages) {
          $result .= '<div class="alert ' . HTML::outputProtected($type) . '" role="alert">';

          foreach ($messages as $message) {
            $result .= '<p>' . $message . '</p>';
          }

          $result .= '</div>';
        }

        unset($this->data[$group]);
      }
    }

    return $result;
  }

  /**
   * Retrieves all data for a specific group if provided, or all data otherwise.
   *
   * @param string|null $group The name of the group to retrieve data for, or null to retrieve all data.
   * @return array The data belonging to the specified group or all data if no group is specified.
   */
  public function getAll(?string $group = null)
  {
    if (isset($group)) {
      if ($this->exists($group)) {
        return $this->data[$group];
      }

      return [];
    }

    return $this->data;
  }

  /**
   * Retrieves the size of the data collection. If a group name is provided, it returns the count of items within that specific group.
   *
   * @param string|null $group An optional group name to count the items of a specific group.
   * @return int The count of items in the entire data collection or in the specified group if provided.
   */
  public function size(?string $group = null): int
  {
    if (isset($group)) {
      if ($this->exists($group)) {
        return count($this->data[$group]);
      }

      return 0;
    }

    return count($this->data);
  }
}
