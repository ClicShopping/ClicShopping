<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use ZipArchive;
use function count;
use function is_array;

/**
 * Provides an extension to the standard PHP ZipArchive class with additional functionality.
 */
class Zip extends ZipArchive
{
  /**
   * Returns a string description for a given zip status code.
   *
   * @param int $status The status code returned by a Zip operation.
   * @return string A description of the given status code, specifying the type of error or status.
   */
  public static function zipStatusString($status)
  {
    return match ((int)$status) {
      ZipArchive::ER_OK => 'N No error',
      ZipArchive::ER_MULTIDISK => 'N Multi-disk zip archives not supported',
      ZipArchive::ER_RENAME => 'S Renaming temporary file failed',
      ZipArchive::ER_CLOSE => 'S Closing zip archive failed',
      ZipArchive::ER_SEEK => 'S Seek error',
      ZipArchive::ER_READ => 'S Read error',
      ZipArchive::ER_WRITE => 'S Write error',
      ZipArchive::ER_CRC => 'N CRC error',
      ZipArchive::ER_ZIPCLOSED => 'N Containing zip archive was closed',
      ZipArchive::ER_NOENT => 'N No such file',
      ZipArchive::ER_EXISTS => 'N File already exists',
      ZipArchive::ER_OPEN => 'S Can\'t open file',
      ZipArchive::ER_TMPOPEN => 'S Failure to create temporary file',
      ZipArchive::ER_ZLIB => 'Z Zlib error',
      ZipArchive::ER_MEMORY => 'N Malloc failure',
      ZipArchive::ER_CHANGED => 'N Entry has been changed',
      ZipArchive::ER_COMPNOTSUPP => 'N Compression method not supported',
      ZipArchive::ER_EOF => 'N Premature EOF',
      ZipArchive::ER_INVAL => 'N Invalid argument',
      ZipArchive::ER_NOZIP => 'N Not a zip archive',
      ZipArchive::ER_INTERNAL => 'N Internal error',
      ZipArchive::ER_INCONS => 'N Zip archive inconsistent',
      ZipArchive::ER_REMOVE => 'S Can\'t remove file',
      ZipArchive::ER_DELETED => 'N Entry has been deleted',
      default => sprintf('Unknown status %s', $status),
    };
  }

  /**
   * Checks if the given path represents a directory.
   *
   * @param string $path The path to check.
   * @return bool True if the path ends with a directory separator ('/'), false otherwise.
   */
  public static function isDir($path)
  {
    return substr($path, -1) == '/';
  }

  /**
   * Constructs and returns a tree representation of file paths within the object.
   *
   * Loops through available files, splits their paths by slashes, and organizes them
   * hierarchically into an array structure. This method ensures all directory paths
   * and their respective files are properly nested.
   *
   * @return array A hierarchical array representing the file and directory structure.
   */
  public function getTree(): array
  {
    $Tree = [];

    for ($i = 0; $i < $this->numFiles; $i++) {
      $path = $this->getNameIndex($i);
      $pathBySlash = array_values(explode('/', $path));
      $c = count($pathBySlash);
      $temp = &$Tree;

      for ($j = 0; $j < $c - 1; $j++) {
        if (isset($temp[$pathBySlash[$j]])) {
          $temp = &$temp[$pathBySlash[$j]];
        } else {
          $temp = &$temp[$pathBySlash[$j]];
        }

        if ($this->isDir($path) !== true) {
          $temp[] = $pathBySlash[$c - 1];
        }
      }
    }

    return $Tree;
  }

  /**
   * Creates a zip archive from the provided array of files.
   *
   * @param array $files Array of file paths to be included in the zip archive.
   * @param string $destination Path to the destination zip file.
   * @param bool $overwrite Whether to overwrite the zip file if it already exists.
   * @return bool Returns true if the zip file is successfully created, otherwise false.
   */
  public static function Create(array $files = [], string $destination = '', bool $overwrite = false)
  {
    //if the zip file already exists and overwrite is false, return false
    if (file_exists($destination) && !$overwrite) {
      return false;
    }

    $valid_files = [];

    if (is_array($files)) {
      foreach ($files as $file) {
        if (file_exists($file)) {
          $valid_files[] = $file;
        }
      }
    }

    if (count($valid_files)) {
      $zip = new ZipArchive();
      if ($zip->open($destination, $overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
        return false;
      }
//add the files
      foreach ($valid_files as $file) {
        $zip->addFile($file, $file);
      }
//debug
//echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;

      $zip->close();

      return file_exists($destination);
    } else {
      return false;
    }
  }
}