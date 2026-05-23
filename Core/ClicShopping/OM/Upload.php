<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use function in_array;
use function is_array;

/**
 * Class responsible for handling file uploads via POST or PUT requests, validating file extensions, permissions,
 * and destination validations, and saving the uploaded files to the desired directory.
 */
class Upload
{
  protected $_file;
  protected string $_filename;
  protected string $_destination;
  protected int $_permissions;
  protected array $_extensions = [];
  protected bool $_replace = false;
  protected array $_upload = [];
  protected int $_maxFileSize = 10485760; // 10MB default
  protected array $_dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht', 'phar', 'exe', 'bat', 'cmd', 'com', 'sh', 'cgi', 'pl', 'jar', 'jsp', 'asp', 'aspx', 'htaccess', 'htpasswd'];
  protected bool $_strictMimeValidation = true;

  /**
   * Constructor to initialize file handling with specified parameters.
   *
   * @param string $file The file to be processed.
   * @param string $destination The destination directory where the file will be processed.
   * @param string|null $permissions Optional. File permissions to be set. Defaults to '777' if not specified.
   * @param array|null $extensions Optional. Additional extensions to be added.
   * @param bool $replace Indicates whether to replace existing files. Defaults to false.
   *
   * @return void
   */
  public function __construct($file, $destination, $permissions = null, $extensions = null, bool $replace = false)
  {
// Remove trailing directory separator
    if (substr($destination, -1) == '/') {
      $destination = substr($destination, 0, -1);
    }

    if (!isset($permissions)) {
      $permissions = '777';
    }

    $this->_file = $file;
    $this->_destination = $destination;

    $this->setPermissions($permissions);

    if (isset($extensions)) {
      $this->addExtensions($extensions);
    }

    $this->_replace = $replace;
  }

  /**
   * Validates file extension against allowed and dangerous extensions.
   *
   * @param string $filename The filename to validate
   * @return bool Returns true if extension is valid, false otherwise
   */
  protected function validateExtension(string $filename): bool
  {
    $extension = mb_strtolower(substr($filename, strrpos($filename, '.') + 1));
    
    // Check against dangerous extensions
    if (in_array($extension, $this->_dangerousExtensions)) {
      return false;
    }
    
    // If specific extensions are configured, check against them
    if (!empty($this->_extensions)) {
      return in_array($extension, $this->_extensions);
    }
    
    return true;
  }

  /**
   * Validates MIME type of uploaded file using finfo.
   *
   * @param string $filePath Path to the file to validate
   * @return bool Returns true if MIME type is valid, false otherwise
   */
  protected function validateMimeType(string $filePath): bool
  {
    if (!$this->_strictMimeValidation) {
      return true;
    }

    if (empty($filePath) || !file_exists($filePath) || !is_readable($filePath)) {
      return false;
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($filePath);

    // Note : Pas de finfo_close() ici.
    // En PHP 8.4/8.5+, cela génère un E_DEPRECATED.
    // L'objet est automatiquement détruit par le Garbage Collector.

    if ($mimeType === false) {
      return false;
    }

    // Define allowed MIME types based on configured extensions
    $allowedMimeTypes = [
      // Images
      'image/jpeg' => ['jpg', 'jpeg'],
      'image/png' => ['png'],
      'image/gif' => ['gif'],
      'image/webp' => ['webp'],
      'image/svg+xml' => ['svg'],
      'image/bmp' => ['bmp'],
      'image/tiff' => ['tif', 'tiff'],

      // Documents
      'application/pdf' => ['pdf'],
      'application/msword' => ['doc'],
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
      'application/vnd.ms-excel' => ['xls'],
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
      'application/vnd.ms-powerpoint' => ['ppt'],
      'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],

      // Archives
      'application/zip' => ['zip'],
      'application/x-rar-compressed' => ['rar'],
      'application/x-7z-compressed' => ['7z'],
      'application/x-tar' => ['tar'],
      'application/gzip' => ['gz'],

      // Text
      'text/plain' => ['txt'],
      'text/csv' => ['csv'],
      'text/html' => ['html', 'htm'],
      'text/css' => ['css'],
      'application/json' => ['json'],
      'application/xml' => ['xml'],
      'text/xml' => ['xml'],

      // Video
      'video/mp4' => ['mp4'],
      'video/mpeg' => ['mpeg', 'mpg'],
      'video/quicktime' => ['mov'],
      'video/x-msvideo' => ['avi'],
      'video/webm' => ['webm'],

      // Audio
      'audio/mpeg' => ['mp3'],
      'audio/wav' => ['wav'],
      'audio/ogg' => ['ogg'],
      'audio/webm' => ['webm'],
    ];

    // Check if detected MIME type matches expected extension
    if (isset($allowedMimeTypes[$mimeType])) {
      $fileExtension = strtolower($this->getExtension());
      return in_array($fileExtension, $allowedMimeTypes[$mimeType], true);
    }

    return false;
  }

  /**
   * Validates file magic bytes (file signature) for common file types.
   *
   * @param string $filePath Path to the file to validate
   * @return bool Returns true if magic bytes are valid, false otherwise
   */
  protected function validateMagicBytes(string $filePath): bool
  {
    $handle = fopen($filePath, 'rb');
    if (!$handle) {
      return false;
    }

    $bytes = fread($handle, 16);
    fclose($handle);

    $extension = $this->getExtension();

    // Define magic bytes for common file types
    $magicBytes = [
      'jpg' => ["\xFF\xD8\xFF"],
      'jpeg' => ["\xFF\xD8\xFF"],
      'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
      'gif' => ["GIF87a", "GIF89a"],
      'pdf' => ["%PDF"],
      'zip' => ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"],
      'webp' => ["RIFF"],
      'bmp' => ["BM"],
      'tif' => ["II\x2A\x00", "MM\x00\x2A"],
      'tiff' => ["II\x2A\x00", "MM\x00\x2A"],
    ];

    // If extension has defined magic bytes, validate them
    if (isset($magicBytes[$extension])) {
      foreach ($magicBytes[$extension] as $magic) {
        if (str_starts_with($bytes, $magic)) {
          return true;
        }
      }
      return false;
    }

    // If no magic bytes defined for this extension, allow it
    return true;
  }

  /**
   * Sanitizes filename to prevent path traversal and other attacks.
   *
   * @param string $filename The filename to sanitize
   * @return string Sanitized filename
   */
  protected function sanitizeFilename(string $filename): string
  {
    // Remove path components
    $filename = basename($filename);
    
    // Remove null bytes
    $filename = str_replace("\0", '', $filename);
    
    // Remove directory traversal attempts
    $filename = str_replace(['../', '..\\', '../', '..\\'], '', $filename);
    
    // Remove special characters except alphanumeric, dots, dashes, underscores
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    // Prevent double extensions (e.g., file.php.jpg)
    $parts = explode('.', $filename);
    if (count($parts) > 2) {
      $extension = array_pop($parts);
      $basename = implode('_', $parts);
      $filename = $basename . '.' . $extension;
    }
    
    // Ensure filename is not empty
    if (empty($filename) || $filename === '.') {
      $filename = 'file_' . bin2hex(random_bytes(8));
    }
    
    return $filename;
  }

  /**
   * Validates and processes file upload requests using either PUT or POST methods.
   * Checks if the uploaded file meets configured requirements such as extensions,
   * MIME types, magic bytes, and ensures it is saved to a writable destination directory.
   *
   * @return bool Returns true if the file upload is successfully validated and processed;
   *              otherwise, false.
   */
  public function check(): bool
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_GET[$this->_file])) {
      $temp_filename = 'temp_' . random_int(100000, 999999);

      while (file_exists(CLICSHOPPING::BASE_DIR . 'Work/Temp/' . $temp_filename)) {
        $temp_filename = 'temp_' . random_int(100000, 999999);
      }

      $input = fopen('php://input', 'r');

      $size = file_put_contents(CLICSHOPPING::BASE_DIR . 'Work/Temp/' . $temp_filename, $input);

      fclose($input);

      if (isset($_SERVER['CONTENT_LENGTH']) && ($size == $_SERVER['CONTENT_LENGTH'])) {
        $this->_upload = [
          'type' => 'PUT',
          'name' => $_GET[$this->_file],
          'size' => $size,
          'temp_filename' => $temp_filename
        ];
      } else {
        $CLICSHOPPING_MessageStack->add('File Upload [PUT]: $_SERVER[\'CONTENT_LENGTH\'] (' . (int)$_SERVER['CONTENT_LENGTH'] . ') not set or not equal to stream size (' . (int)$size . ')', 'warning');
      }
    } elseif (isset($_FILES[$this->_file])) {
      if ($_FILES[$this->_file]['error'] === UPLOAD_ERR_NO_FILE) {
        return false;
      }

      if (isset($_FILES[$this->_file]['tmp_name'])
        && !empty($_FILES[$this->_file]['tmp_name'])
        && is_uploaded_file($_FILES[$this->_file]['tmp_name'])
        && ($_FILES[$this->_file]['size'] > 0)
        && file_exists($_FILES[$this->_file]['tmp_name'])
      ) {
        $this->_upload = [
          'type' => 'POST',
          'name' => $_FILES[$this->_file]['name'],
          'size' => $_FILES[$this->_file]['size'],
          'tmp_name' => $_FILES[$this->_file]['tmp_name']
        ];
      }
    }

    if (!empty($this->_upload)) {
      // Sanitize filename
      $this->_upload['name'] = $this->sanitizeFilename($this->_upload['name']);

      // Validate file size
      if ($this->_upload['size'] > $this->_maxFileSize) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_file_too_large') . ' (Max: ' . ($this->_maxFileSize / 1048576) . 'MB)', 'warning');
        return false;
      }

      // Validate file size is not zero
      if ($this->_upload['size'] <= 0) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_file_empty'), 'warning');
        return false;
      }

      // Validate extension
      if (!$this->validateExtension($this->_upload['name'])) {
        $message = CLICSHOPPING::getDef('error_filetype_not_allowed');
        if (!empty($this->_extensions)) {
          $message .= ' ' . implode(', ', $this->_extensions);
        }
        $CLICSHOPPING_MessageStack->add($message, 'warning');
        return false;
      }

      // Get file path for content validation
      $filePath = ($this->_upload['type'] == 'PUT')
        ? CLICSHOPPING::BASE_DIR . 'Work/Temp/' . $this->_upload['temp_filename']
        : $this->_upload['tmp_name'];

      // Validate MIME type
      if (!$this->validateMimeType($filePath)) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_file_mime_type_invalid'), 'warning');
        return false;
      }

      // Validate magic bytes for known file types
      if (!$this->validateMagicBytes($filePath)) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_file_signature_invalid'), 'warning');
        return false;
      }

      // Validate destination directory
      if (!is_dir($this->_destination)) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_catalog_image_directory_does_not_exist') . $this->_destination, 'warning');
        return false;
      }

      if (!FileSystem::isWritable($this->_destination)) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getConfig('error_catalog_image_directory_not_writeable') . $this->_destination, 'warning');
        return false;
      }

      return true;
    }

    return false;
  }

  /**
   * Saves the uploaded file to the specified destination directory.
   * Depending on the upload type ('PUT' or 'POST'), the method either renames or moves the file
   * to the target location, ensuring no duplicate names are present if the `_replace` property is true.
   * Permissions are applied to the saved file.
   * A warning message is added to the message stack if the file cannot be saved.
   *
   * @return bool Returns true if the file is successfully saved, otherwise returns false.
   */
  public function save()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if ($this->_replace === true) {
      while (file_exists($this->_destination . DIRECTORY_SEPARATOR . $this->getFilename())) {

        $salt = bin2hex(random_bytes(5));

        $this->setFilename($salt . '_' . $this->getFilename());
      }
    }

    if ($this->_upload['type'] == 'PUT') {
      if (rename(CLICSHOPPING::BASE_DIR . 'Work/Temp/' . $this->_upload['temp_filename'], $this->_destination . DIRECTORY_SEPARATOR . $this->getFilename())) {
        chmod($this->_destination . DIRECTORY_SEPARATOR . $this->getFilename(), $this->_permissions);

        return true;
      }
    } elseif ($this->_upload['type'] == 'POST') {
      if (move_uploaded_file($this->_upload['tmp_name'], $this->_destination . DIRECTORY_SEPARATOR . $this->getFilename())) {
        chmod($this->_destination . DIRECTORY_SEPARATOR . $this->getFilename(), $this->_permissions);

        return true;
      }
    }

    $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_file_not_saved'), 'warning');

    return false;
  }

  /**
   * Sets the permissions for the current object.
   *
   * @param mixed $permissions The permissions value to be set, which will be converted to an octal decimal.
   * @return void
   */
  public function setPermissions($permissions)
  {
    $this->_permissions = octdec($permissions);
  }

  /**
   * Adds one or more extensions to the existing list of extensions.
   *
   * @param mixed $extensions A single extension as a string or multiple extensions as an array.
   * @return void
   */
  public function addExtensions($extensions)
  {
    if (!is_array($extensions)) {
      $extensions = [$extensions];
    }

    $extensions = array_map('mb_strtolower', $extensions);

    $this->_extensions = array_merge($this->_extensions, $extensions);
  }

  /**
   * Sets the replace flag.
   *
   * @param bool $bool Indicates whether to enable or disable the replace flag.
   * @return void
   */
  public function setReplace(bool $bool)
  {
    $this->_replace = ($bool === true);
  }

  /**
   * Sets the maximum file size allowed for upload.
   *
   * @param int $bytes Maximum file size in bytes
   * @return void
   */
  public function setMaxFileSize(int $bytes)
  {
    $this->_maxFileSize = $bytes;
  }

  /**
   * Enables or disables strict MIME type validation.
   *
   * @param bool $strict Whether to enable strict MIME validation
   * @return void
   */
  public function setStrictMimeValidation(bool $strict)
  {
    $this->_strictMimeValidation = $strict;
  }

  /**
   * Adds dangerous extensions to the blacklist.
   *
   * @param array $extensions Array of extensions to add to blacklist
   * @return void
   */
  public function addDangerousExtensions(array $extensions)
  {
    $extensions = array_map('mb_strtolower', $extensions);
    $this->_dangerousExtensions = array_merge($this->_dangerousExtensions, $extensions);
  }

  /**
   * Retrieves the destination property.
   *
   * @return mixed The value of the destination property.
   */
  public function getDestination()
  {
    return $this->_destination;
  }

  /**
   * Sets the filename property.
   *
   * @param string $filename The name of the file to be set.
   * @return void
   */
  public function setFilename(string $filename)
  {
    $this->_filename = $filename;
  }

  /**
   * Retrieves the filename.
   *
   * @return string Returns the filename if set, otherwise returns the upload's name.
   */
  public function getFilename()
  {
    if (isset($this->_filename)) {
      return $this->_filename;
    }

    return $this->_upload['name'];
  }

  /**
   * Retrieves the file extension from the filename in lowercase.
   *
   * @return string The file extension in lowercase.
   */
  public function getExtension(): string
  {
    return mb_strtolower(substr($this->getFilename(), strrpos($this->getFilename(), '.') + 1));
  }

  /**
   * Retrieves the permissions property.
   *
   * @return mixed Returns the value of the _permissions property.
   */
  public function getPermissions()
  {
    return $this->_permissions;
  }

  /**
   * Destructor method that ensures the temporary uploaded file is deleted if it exists.
   *
   * @return void
   */
  public function __destruct()
  {
    if (isset($this->_upload['temp_filename']) && file_exists(CLICSHOPPING::BASE_DIR . 'Work/Temp/' . $this->_upload['temp_filename'])) {
      unlink(CLICSHOPPING::BASE_DIR . 'Work/Temp/' . $this->_upload['temp_filename']);
    }
  }
}
