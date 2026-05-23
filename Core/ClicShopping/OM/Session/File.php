<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Session;

use ClicShopping\OM\CLICSHOPPING;
use function strlen;

/**
 * File-based session handler implementation.
 *
 * This class extends the SessionAbstract class and implements the PHP SessionHandlerInterface.
 * It uses file storage for session handling, providing methods for session lifecycle management.
 */
class File extends \ClicShopping\OM\Domains\SessionAbstract implements \SessionHandlerInterface
{
  protected string $path;

  /**
   * Constructor for initializing session handling.
   *
   * Ensures the session directory exists, sets the save path for session data,
   * and registers the current instance as the session handler.
   *
   * @return void
   */
  public function __construct()
  {
    if (!is_dir(CLICSHOPPING::BASE_DIR . 'Work/Session')) {
      mkdir(CLICSHOPPING::BASE_DIR . 'Work/Session', 0777, true);
    }

    $this->setSavePath(CLICSHOPPING::BASE_DIR . 'Work/Session');

    session_set_save_handler($this, true);
  }

  /**
   * Checks if a session file exists for the given session ID.
   *
   * @param string $session_id The session ID to check.
   * @return bool Returns true if the session file exists, false otherwise.
   */
  public function exists(string $session_id): bool
  {
    $id = basename($session_id);

    return is_file($this->path . '/sess_' . $id);
  }

  /**
   * Ensures that the session save path exists, creating the directory if necessary.
   *
   * @param string $save_path The path where the session data will be saved.
   * @param string $name The name of the session.
   * @return bool Returns true on success.
   */
  public function open(string $save_path, string $name): bool
  {
    if (!is_dir($save_path)) {
      mkdir($save_path, 0777);
    }

    return true;
  }

  /**
   * Closes the currently active session and releases session resources.
   *
   * @return bool Returns true on success.
   */
  public function close(): bool
  {
    return true;
  }

  /**
   * Reads the session data for the given session ID.
   *
   * @param string $session_id The ID of the session to read.
   * @return string The session data as a string, or an empty string if no data exists.
   */
  public function read(string $session_id): string
  {
    $id = basename($session_id);

    $result = false;


    if ($this->exists($id)) {
      $result = file_get_contents($this->path . '/sess_' . $id);
    }

    if ($result === false) {
      $result = '';
    }

    return $result;
  }

  /**
   * Writes session data to a file.
   *
   * @param string $session_id The session ID.
   * @param string $session_data The data to be written to the session file.
   * @return bool Returns true on success or false on failure.
   */
  public function write(string $session_id, string$session_data)
  {
    $id = basename(mb_convert_encoding($session_id, 'UTF-8', 'ISO-8859-1'));
    return file_put_contents($this->path . '/sess_' . $id, $session_data) !== false;
  }

  /**
   * Destroys a session based on the provided session ID.
   *
   * @param string $session_id The ID of the session to be destroyed.
   * @return bool Returns true if the session file was successfully deleted or if it does not exist.
   */
  public function destroy(string $session_id): bool
  {
    $id = basename($session_id);

    if ($this->exists($id)) {
      return unlink($this->path . '/sess_' . $id);
    }

    return true;
  }

  /**
   * Deletes session files that have expired based on their file modification time.
   *
   * @param int $maxlifetime The maximum lifetime (in seconds) for session files before they are considered expired.
   * @return bool Returns true upon completion of cleanup.
   */
  public function gc(int $maxlifetime): bool
  {
    foreach (glob($this->path . '/sess_*', GLOB_NOSORT) as $file) {
      if (filemtime($file) + $maxlifetime < time()) {
        unlink($file);
      }
    }

    return true;
  }

  /**
   * Sets the session save path to the specified directory.
   *
   * @param string $path The path to set as the session save path. If the path ends with a '/', it will be trimmed.
   * @return void
   */
  public function setSavePath(string $path): void
  {
    if ((strlen($path) > 1) && (substr($path, -1) == '/')) {
      $path = substr($path, 0, -1);
    }

    session_save_path($path);

    $this->path = session_save_path();
  }
}
