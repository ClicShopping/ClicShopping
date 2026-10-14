<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Retrieve the contents of a directory.
 *
 * @param string $base The base directory to scan.
 * @param bool $recursive Whether to search directories recursively.
 * @return array An array containing the contents of the directory.
 */
class FileSystem
{
  /**
   * Retrieves the contents of a directory as an array of file paths.
   *
   * @param string $base The base directory to scan for contents.
   * @param bool $recursive Whether to scan the directory recursively. Defaults to true.
   * @return array An array of file paths contained within the specified directory.
   */
  public static function getDirectoryContents(string $base, bool $recursive = true): array
  {
    $base = str_replace('\\', '/', $base); // Unix style directory separator "/"

    $flags = FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_SELF | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;

    if ($recursive === true) {
      $dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, $flags));
    } else {
      $dir = new FilesystemIterator($base, $flags);
    }

    $result = [];

    foreach ($dir as $file) {
      $result[] = $file->getPathName();
    }

    return $result;
  }

  /**
   * Determines whether a specified location is writable. Optionally checks writability recursively
   * by traversing up the directory tree until an existing location is found.
   *
   * @param string $location The file or directory path to check for writability.
   * @param bool $recursive_check Optional. If true, checks writability recursively by moving up the directory hierarchy.
   * @return bool Returns true if the location is writable, otherwise false.
   */
  public static function isWritable(string $location, bool $recursive_check = false): bool
  {
    clearstatcache(true, $location);

    if ($recursive_check) {
      while (!file_exists($location)) {
        $parent = dirname($location);

        if ($parent === $location) {
          return false;
        }

        $location = $parent;
      }
    }

    if (!file_exists($location)) {
      return is_writable(dirname($location));
    }

    if (is_dir($location)) {
      $tmpFile = rtrim($location, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid('wtest_', true);

      $fp = @fopen($tmpFile, 'wb');

      if ($fp === false) {
        return false;
      }

      fclose($fp);
      unlink($tmpFile);

      return true;
    }

    return is_writable($location);
  }

  /**
   * Recursively removes a directory and its contents or performs a dry run to check the writability of the directory and its files.
   *
   * @param string $dir The directory to be removed.
   * @param bool $dry_run Flag to determine whether to perform actual deletion (false) or a dry run to check permissions (true).
   * @return array Returns an array of the processing results for each file and directory, including type, source, and operation result.
   */
  public static function rmdir(string $dir, bool $dry_run = false): array
  {
    $result = [];

    if (is_dir($dir)) {
      foreach (static::getDirectoryContents($dir, false) as $file) {
        if (is_dir($file)) {
          $result = [...$result, ...static::rmdir($file, $dry_run)];
        } else {
          $result[] = [
            'type' => 'file',
            'source' => $file,
            'result' => ($dry_run === false) ? unlink($file) : static::isWritable($file)
          ];
        }
      }

      $result[] = [
        'type' => 'directory',
        'source' => $dir,
        'result' => ($dry_run === false) ? rmdir($dir) : static::isWritable($dir)
      ];
    }

    return $result;
  }

  /**
   * Checks if a specified directory is empty.
   *
   * @param string $directory The
   */
  public static function isDirectoryEmpty(string $directory): bool
  {
    $dir = new FilesystemIterator($directory, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_SELF | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);

    return ($dir->valid() === false);
  }

  /**
   * Converts a given pathname to use the correct directory separator for the current system.
   *
   * @param string $pathname The path to be converted.
   * @return string The pathname with the correct directory separator.
   */
  public static function displayPath(string $pathname): string
  {
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pathname);
  }

  /**
   * Removes a file or directory at the specified location and its contents if applicable.
   *
   * @param string $source The file or directory path to be removed.
   * @return void
   */
  public static function rmFile(string $source)
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (is_dir($source)) {
      $dir = dir($source);
      while ($file = $dir->read()) {
        if (($file != '.') && ($file != '..')) {
          if (FileSystem::isWritable($source . DIRECTORY_SEPARATOR . $file)) {
            static::rmFile($source . DIRECTORY_SEPARATOR . $file);
          } else {
            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_file_not_removeable') . ' ' . $source . DIRECTORY_SEPARATOR . $file, 'error');
          }
        }
      }
      $dir->close();

      if (static::isWritable($source)) {
        rmdir($source);
      } else {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_file_not_removeable') . ' ' . $source, 'error');
      }
    } else {
      if (static::isWritable($source)) {
        unlink($source);
      } else {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_file_not_removeable') . ' ' . $source, 'error');
      }
    }
  }

  /**
   * Determines the type and permissions of a file based on its mode.
   *
   * @param string $mode The file's mode, typically provided as a result of the `fileperms()` function.
   * @return string A string representing the file type and its permissions in standard UNIX format.
   */
  public static function getFilePermissions(string $mode)
  {
// determine type
    if (($mode & 0xC000) == 0xC000) { // unix domain socket
      $type = 's';
    } elseif (($mode & 0x4000) == 0x4000) { // directory
      $type = 'd';
    } elseif (($mode & 0xA000) == 0xA000) { // symbolic link
      $type = 'l';
    } elseif (($mode & 0x8000) == 0x8000) { // regular file
      $type = '-';
    } elseif (($mode & 0x6000) == 0x6000) { //bBlock special file
      $type = 'b';
    } elseif (($mode & 0x2000) == 0x2000) { // character special file
      $type = 'c';
    } elseif (($mode & 0x1000) == 0x1000) { // named pipe
      $type = 'p';
    } else { // unknown
      $type = '?';
    }

// determine permissions
    $owner = [];
    $group = [];
    $world = [];

    $owner['read'] = ($mode & 00400) ? 'r' : '-';
    $owner['write'] = ($mode & 00200) ? 'w' : '-';
    $owner['execute'] = ($mode & 00100) ? 'x' : '-';
    $group['read'] = ($mode & 00040) ? 'r' : '-';
    $group['write'] = ($mode & 00020) ? 'w' : '-';
    $group['execute'] = ($mode & 00010) ? 'x' : '-';
    $world['read'] = ($mode & 00004) ? 'r' : '-';
    $world['write'] = ($mode & 00002) ? 'w' : '-';
    $world['execute'] = ($mode & 00001) ? 'x' : '-';

// adjust for SUID, SGID and sticky bit
    if ($mode & 0x800) $owner['execute'] = ($owner['execute'] == 'x') ? 's' : 'S';
    if ($mode & 0x400) $group['execute'] = ($group['execute'] == 'x') ? 's' : 'S';
    if ($mode & 0x200) $world['execute'] = ($world['execute'] == 'x') ? 't' : 'T';

    return $type .
      $owner['read'] . $owner['write'] . $owner['execute'] .
      $group['read'] . $group['write'] . $group['execute'] .
      $world['read'] . $world['write'] . $world['execute'];
  }

  /**
   * Copies a file from the source location to the destination location.
   *
   * @param string $source The path to the source file that needs to be copied.
   * @param string $destination The path where the file should be copied to.
   * @return bool Returns true if the file was successfully copied, otherwise false.
   */
  public static function copyFile(string $source, string $destination): bool
  {
    $target_dir = dirname($destination);

    if (!is_dir($target_dir)) {
      if (!mkdir($target_dir, 0777, true) && !is_dir($target_dir)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $target_dir));
      }
    }

    return copy($source, $destination);
  }

  /**
   * Moves a file from the source location to the destination location.
   *
   * @param string $source The path to the source file.
   * @param string $destination The path to the destination file.
   * @return bool Returns true if the file was moved successfully, false otherwise.
   */
  public static function moveFile(string $source, string $destination): bool
  {
    $target_dir = dirname($destination);

    if (!is_dir($target_dir)) {
      if (!mkdir($target_dir, 0777, true) && !is_dir($target_dir)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $target_dir));
      }
    }

    if (copy($source, $destination)) {
      unlink($source);
      return true;
    }

    return false;
  }
}
