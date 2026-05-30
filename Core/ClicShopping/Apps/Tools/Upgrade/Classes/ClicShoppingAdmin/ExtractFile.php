<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Tools\Upgrade\Upgrade as UpgradeApp;
use ClicShopping\Sites\ClicShoppingAdmin\ModuleDownload;
use RuntimeException;
use ZipArchive;

class ExtractFile
{
  public mixed $app;
  public string $saveFileFromGithub;
  public string $cacheGithub;
  public string $cacheGithubTemp;
  public string $ModuleInfosJson;

  /**
   * Represents the DT module configuration for the ClicShoppingAdmin application.
   * This class extends the base ConfigAbstract class and provides implementation
   * specific to the DT module, including install and uninstall functionalities.
   */

  /**
   * Extracts the contents of a ZIP archive to a specified destination directory.
   *
   * @param string $source The path to the ZIP archive file to be extracted.
   * @param string $destination The directory where the ZIP contents will be extracted.
   *
   * @return bool Returns true if the extraction is successful, otherwise false.
   */
  private function getExtractZip(string $source, string $destination): bool
  {
    $zip = new ZipArchive;

    if ($zip->open($source) === true) {
      $zip->extractTo($destination);
      $zip->close();
      return true;
    } else {
      return false;
    }
  }

  /**
   * Updates the configuration value for the store's offline status.
   *
   * @param string $value The configuration value to update for the store's offline status.
   * @return void
   */
  public function getCloseOpenStore(string $value): void
  {
    $Qupdate = $this->app->db->prepare('update :table_configuration
                                            set configuration_value = :configuration_value,
                                            last_modified = now()
                                            where configuration_key = :configuration_key
                                           ');
    $Qupdate->bindValue(':configuration_value', $value);
    $Qupdate->bindValue(':configuration_key', 'STORE_OFFLINE');
    $Qupdate->execute();
  }

  /**
   * Checks if necessary directories exist and are writable, creating them if needed.
   *
   * @return bool Returns true if all required directories exist and are writable; otherwise, false.
   * @throws RuntimeException If a directory cannot be created.
   */
  public function checkDirectory(): bool
  {
    if (!is_dir($this->saveFileFromGithub)) {
      if (!mkdir($concurrentDirectory = $this->saveFileFromGithub, 0777, true) && !is_dir($concurrentDirectory)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
      }
      return true;
    } elseif (FileSystem::isWritable($this->saveFileFromGithub)) {
      return true;
    } else {
      return false;
    }

    if (!is_dir($this->cacheGithub)) {
      if (!mkdir($concurrentDirectory = $this->cacheGithub, 0777, true) && !is_dir($concurrentDirectory)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
      }
      return true;
    } elseif (FileSystem::isWritable($this->cacheGithub)) {
      return true;
    } else {
      return false;
    }

    if (!is_dir($this->cacheGithubTemp)) {
      if (!mkdir($concurrentDirectory = $this->cacheGithubTemp, 0777, true) && !is_dir($concurrentDirectory)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
      }
      return true;
    } elseif (FileSystem::isWritable($this->cacheGithubTemp)) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Downloads a file from the specified URL and saves it locally.
   *
   * @param string $file_url The URL of the file to be downloaded.
   * @return string|null Returns the local file path where the downloaded file is saved, or null if the download failed.
   */
  public function downloadFile(string $file_url)
  {
    // Security: Validate URL to prevent SSRF and unauthorized downloads
    $parsed_url = parse_url($file_url);
    
    // Only allow HTTPS URLs
    if (!isset($parsed_url['scheme']) || $parsed_url['scheme'] !== 'https') {
      $this->messageStack->add($this->app->getDef('text_file_download_error'), 'error');
      CLICSHOPPING::redirect('Upgrade&Marketplace');
      return null;
    }

    // Optional: Whitelist specific domains (e.g., GitHub)
    // Uncomment and modify as needed:
      $allowed_domains = ['github.com', 'api.github.com'];
      if (!in_array($parsed_url['host'] ?? '', $allowed_domains)) {
        $this->messageStack->add('Download from unauthorized domain', 'error');
        CLICSHOPPING::redirect('Upgrade&Marketplace');
        return null;
       }

    $downloaded_file = @file_get_contents($file_url);

    if ($downloaded_file === false) {
      $this->messageStack->add($this->app->getDef('text_file_download_error'), 'error');

      CLICSHOPPING::redirect('Upgrade&Marketplace');
    } else {
      $path_parts = pathinfo($file_url);
      $file_name = $path_parts['filename'] . '.zip';
      $file_name = str_replace('_zip', '', $file_name);

      $filename_localisation = $this->saveFileFromGithub . '/' . $file_name;
      file_put_contents($filename_localisation, $downloaded_file);

      return $filename_localisation;
    }
  }

  /**
   * Installs files from the provided zip file and copies them to their respective destinations.
   *
   * @param string $filename_localisation The path to the zip file to be installed.
   * @return void
   */
  public function installFiles(string $filename_localisation): void
  {
    // Security: Validate ZIP contents to prevent path traversal attacks
    $zip = new ZipArchive();
    if ($zip->open($filename_localisation) === true) {
      $has_traversal = false;
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        // Check for path traversal
        if (str_contains($filename, '..') || str_starts_with($filename, '/') || str_starts_with($filename, '\\')) {
          $has_traversal = true;
          break;
        }
        // Check for absolute paths
        if (preg_match('/^[a-zA-Z]:\\/', $filename)) {
          $has_traversal = true;
          break;
        }
      }
      $zip->close();
      
      if ($has_traversal) {
        $this->messageStack->add('Invalid ZIP file: contains malicious path entries', 'error');
        $this->app->redirect('Marketplace');
        return;
      }
    }

    $this->getExtractZip($filename_localisation, $this->saveFileFromGithub);

//check if not readme.zip
    $dir = str_replace('.zip', '', $filename_localisation);

    if (is_dir($dir)) {
// copy json in cache
      $json_source = $dir . '/' . $this->ModuleInfosJson . '/';
      $json_destination = $this->cacheGithub;

      @ModuleDownload::smartCopy($json_source, $json_destination);

// copy files their directories
      $source = $dir;
      $dest = CLICSHOPPING::getConfig('dir_root', 'Shop');

      @ModuleDownload::smartCopy($source, $dest);

      if (isset($source)) {
        @ModuleDownload::removeDirectory($source);
      }

      $this->messageStack->add($this->app->getDef('success_file_installed'), 'success', 'main');

      $this->app->redirect('MarketplaceSuccess');
    } else {
      $this->messageStack->add($this->app->getDef('error_file_not_installed'), 'error', 'main');
      $this->app->redirect('Marketplace');
    }
  }

  /**
   * Retrieves and decodes a JSON file.
   *
   * @param string $json_file The name of the JSON file to retrieve and decode.
   * @return array|bool Returns the decoded JSON data as an array if successful, or false on failure.
   */
  public function getJsonInfo(string $json_file): array|bool
  {
    $cache_directory = CLICSHOPPING::BASE_DIR . 'Work/Cache/Marketplace/';

    if (is_file($cache_directory . $json_file)) {
      $get_json_file = file_get_contents($cache_directory . $json_file, true);
      $result = json_decode($get_json_file);

      return $result;
    } else {
      $this->messageStack->add($this->app->getDef('error_json_file'), 'error', 'main');
      return false;
    }
  }
}