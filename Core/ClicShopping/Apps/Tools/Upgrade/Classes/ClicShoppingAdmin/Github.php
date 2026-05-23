<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use ClicShopping\Sites\ClicShoppingAdmin\ModuleDownload;
use ClicShopping\Apps\Tools\Upgrade\Upgrade as UpgradeApp;
use function count;
use function is_null;
use function is_object;
use function strlen;

class Github
{
  public mixed $app;
  protected $github;
  protected string $githubRepo;
  protected $context;
  protected string $coreName;
  protected string $githubRepoClicShoppingCore;
  public ?string $githubRepoName;
  protected string $saveFileFromGithub;
  protected string $cacheGithub;
  public string $cacheGithubTemp;
  protected string $ModuleInfosJson;

  public function __construct()
  {
    if (!Registry::exists('Upgrade')) {
      Registry::set('Upgrade', new UpgradeApp());
    }

    $this->app = Registry::get('Upgrade');

    $this->githubUrl = 'https://github.com';
    $this->githubApi = 'https://api.github.com';
    $this->githubRepo = 'repos';
    $this->context = stream_context_create(array('http' => array('header' => 'User-Agent: ClicShopping')));

    $this->coreName = 'ClicShopping';
    $this->githubRepoClicShoppingincludes = 'ClicShopping_V3';

    if (isset($_POST['addons_apps'])) {
      if ($_POST['addons_apps'] == 'official') {
        $this->githubRepoName = 'ClicShoppingOfficialModulesV3';
      } else { // community
        $this->githubRepoName = 'ClicShoppingV3Community';
      }
    }

    $this->saveFileFromGithub = CLICSHOPPING::BASE_DIR . 'Work/Temp';
    $this->cacheGithub = CLICSHOPPING::BASE_DIR . 'Work/Cache/Github/';
    $this->cacheGithubTemp = CLICSHOPPING::BASE_DIR . 'Work/Cache/Github/Temp/';
    $this->ModuleInfosJson = 'ModuleInfosJson';
  }

  /*
  * getGithubincludesRepo : ClicShopping includes
  * @param
  * @return  $url url about the ClicShopping includes
  * @access
  */
  /**
   * Constructs the GitHub repository URL for includes.
   *
   * @return string The constructed GitHub includes repository URL.
   */
  private function getGithubincludesRepo()
  {
    $url = $this->githubApi . '/' . $this->githubRepo . '/' . $this->coreName . '/' . $this->githubRepoClicShoppingincludes;

    return $url;
  }

  /*
  * user agent in Header
  * @param
  * @return  user agent
  */
  /**
   * Retrieves the current context.
   *
   * @return mixed The current context value.
   */
  private function setContext()
  {
    return $this->context;
  }

  /**
   * Retrieves the core JSON information from a remote source or cache.
   *
   * This method attempts to fetch the core JSON version information either from
   * a cache (if valid) or directly from a remote repository. If the cache is
   * outdated or unavailable, the method retrieves the JSON data online,
   * processes it, and stores it in the cache.
   *
   * @return mixed Returns the JSON decoded data if successful, or false if an
   * error occurs during retrieval or processing.
   */
  public function getJsonCoreInformation()
  {
    $versionCache = new Cache('clicshopping_core_information');

    if ($versionCache->exists(30) !== false) {
      $result = $versionCache->get();
      // Cached objects are stored as arrays to avoid unserialize class issues.
      if ($result instanceof \__PHP_Incomplete_Class) {
        $result = null;
      }
      if (is_array($result)) {
        $result = (object)$result;
      }
    } else {
      try {
        $response = HTTP::getResponse([
          'url' => $this->getGithubincludesRepo() . '/contents/Core/ClicShopping/version.json?ref=master'
        ]);

        if ($response !== false) {
          $json = @file_get_contents($this->getGithubincludesRepo() . '/contents/Core/ClicShopping/version.json?ref=master', true, $this->context);

          if ($json === false) {
            throw new \Exception('Failed to fetch version metadata');
          }

          $url = json_decode($json);
          if (!isset($url->download_url)) {
            throw new \Exception('Download URL not found in metadata');
          }

          $url_download = @file_get_contents($url->download_url, true, $this->setContext());

          if ($url_download === false) {
            throw new \Exception('Failed to fetch version content');
          }

          $data = json_decode($url_download, true);
          if (!is_array($data)) {
            throw new \Exception('Invalid version content');
          }

          $versionCache->save($data);
          $result = (object)$data;
        } else {
          $result = false;
        }
      } catch (\Throwable $e) {
        $result = false;
      }
    }

    return $result;
  }

  /**
   * Extracts the contents of a zip file to a specified destination.
   *
   * @param string $source The path to the source zip file.
   * @param string $destination The path to the directory where the contents should be extracted.
   */
  private function getExtractZip(string $source, string $destination)
  {
    return $this->extracfile->getExtractZip($source, $destination);
  }

  /*
  * user open or Close the store during the upgrade process
  * @param $value,true / false
  * @return
  *
  */
  /**
   * Handles the close and open store functionality.
   *
   * @param mixed $value The value to be processed by the close and open store method.
   * @return void
   */
  private function getCloseOpenStore($value): void
  {
    $this->extracfile->getCloseOpenStore($value);
  }

  /**
   * Checks for updates in the online directory by invoking the corresponding method in the extracfile object.
   *
   * @return void
   */
  private function checkDirectoryOnlineUpdate()
  {
    $this->extracfile->checkDirectoryOnlineUpdate();
  }


  /**
   * Retrieves the list of module templates available in the default directory.
   * The method scans the specified directory, excluding certain files and directories,
   * and returns an array of available module templates with their identifiers and names.
   *
   * @return array The list of module templates including their IDs and descriptive names.
   */
  public function getModuleTemplateDirectory()
  {
    $default_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/Default/modules';
    $module[] = [
      'id' => '0',
      'text' => $this->app->getDef('text_select_module_template')
    ];

    $exclude = [
      '..',
      '.',
      'customers_address',
      'download',
      'index.php',
      '_htaccess',
      '.htaccess'];

    $module_dir = array_diff(scandir($default_directory), $exclude);

    foreach ($module_dir as $filename) {
      $module[] = [
        'id' => $filename,
        'text' => str_replace('_ ', '', $filename)
      ];
    }

    return $module;
  }

  /**
   * Retrieves an array of module directories with their respective IDs and descriptions.
   *
   * @return array Returns an array of associative arrays, where each element contains 'id' and 'text' keys representing the module identifier and description respectively.
   */
  public function getModuleDirectory(): array
  {
    $module = array(array(
      'id' => '0', 'text' => $this->app->getDef('text_select_module_fix')),
      array('id' => 'export', 'text' => 'Modules Export'),
      array('id' => 'header_tags', 'text' => 'Modules Header Tags'),
      array('id' => 'hooks', 'text' => 'Modules Hooks'),
      array('id' => 'catalog', 'text' => 'Modules Apps Catalog'),
      array('id' => 'communication', 'text' => 'Modules Apps Communication'),
      array('id' => 'configuration', 'text' => 'Modules Apps Configuration'),
      array('id' => 'customer', 'text' => 'Modules Apps Customer'),
      array('id' => 'marketing', 'text' => 'Modules Apps Marketing'),
      array('id' => 'order', 'text' => 'Modules Apps Orders'),
      array('id' => 'total', 'text' => 'Modules Apps Orders Total'),
      array('id' => 'payment', 'text' => 'Modules Apps Payment'),
      array('id' => 'listing', 'text' => 'Modules Apps Products listing'),
      array('id' => 'report', 'text' => 'Modules Apps Report'),
      array('id' => 'shipping', 'text' => 'Modules Apps Shipping'),
      array('id' => 'social', 'text' => 'Modules Apps Social network'),
      array('id' => 'tools', 'text' => 'Modules Apps Tools'),
      array('id' => 'service', 'text' => 'Modules Apps Web service'),
      array('id' => 'other', 'text' => 'Modules Apps Others'),
      array('id' => 'template', 'text' => 'Template Design'),
      array('id' => 'language', 'text' => 'Language'),
    );

    return $module;
  }

//*************************************************************
// Cache
//*************************************************************

  /*
   * Unlik the cache if the delay is passed
   * @param $module_name name of the module
   * @return  $file, file information
   * @access private
   */
  /**
   * Checks the cache related to the given module name and removes it if it is outdated.
   *
   * @param string $module_name The name of the module whose cache will be checked.
   * @return void
   */
  private function getCheckDateCache(string $module_name)
  {
    $life = 864000; // 1 month

    if (is_file($this->cacheGithub . $module_name)) {
      $cache_file = $this->cacheGithub . $module_name;
      $count = filemtime($cache_file) > (time() - $life);

      if ($count < 1 && filesize($cache_file)) {
        unlink($this->cacheGithub . $module_name);
      }
    } elseif (is_file($this->cacheGithubTemp . $module_name)) {
      $cache_file = $this->cacheGithubTemp . $module_name;
      $count = filemtime($cache_file) > (time() - $life);

      if ($count < 1 && filesize($cache_file)) {
        unlink($this->cacheGithubTemp . $module_name);
      }
    }
  }

  /**
   * Checks the cache files of a given module and determines their status or retrieves the necessary file data.
   *
   * @param string $module_name The name of the module to check or retrieve from the cache.
   * @return string|bool Returns the cache source ('cacheGithub' or 'cacheGithubTemp') if the module exists in the cache, `true` if successful, or `false` if the file does not exist or an error occurs.
   */
  public function getCheckCacheFile(string $module_name)
  {
    if (!empty($module_name)) {
      $this->getCheckDateCache($module_name);

      if (is_file($this->cacheGithub . $module_name)) {
        $result = 'cacheGithub';
      } elseif (is_file($this->cacheGithubTemp . $module_name)) {
        $result = 'cacheGithubTemp';
      } else {
        $file_name = $module_name;
        $file_array = explode('.', $file_name);
        $extension = count($file_array) - 1;
        $filename = substr($file_name, 0, strlen($file_name) - strlen($file_array[$extension]) - 1);

        $content_json_file = @file_get_contents($this->getGithubRepo() . $filename . '/contents/' . $this->ModuleInfosJson . '/' . $module_name . '?ref=master', true, $this->context);
        $content_download_file = json_decode($content_json_file);

        if (!empty($content_download_file->download_url)) {
          $file_link = $content_download_file->download_url;

          $file_json_content = @file_get_contents($file_link, true, $this->context);

          if (!empty($file_link)) {
            $headers = get_headers($file_link, true);

            if (isset($headers['Content-Length'])) {
              @file_put_contents($this->cacheGithubTemp . '/' . $module_name, $file_json_content);
            }

            $result = false;
          } else {
            $result = true;
          }
        } else {
          $result = false;
        }
      }

      return $result;
    }
  }

  /**
   * Retrieves the temporary cache file for the given module name if available.
   *
   * @param string $module_name The name of the module for which the temporary cache file is retrieved.
   * @return mixed Returns the decoded temporary cache file content if found, otherwise returns false.
   */
  public function getCacheFileTemp(string $module_name)
  {
    $check = $this->getCheckCacheFile($module_name);

    if ($check == 'cacheGithubTemp') {
      $file = @file_get_contents($this->cacheGithubTemp . $module_name, true, $this->context);
      $file = json_decode($file);

      return $file;
    } else {
      return false;
    }
  }

  /**
   * Retrieves the cached file content for the specified module name if it exists and is valid.
   *
   * @param string $module_name The name of the module for which the cache file is to be retrieved.
   * @return mixed The decoded content of the cache file if it exists and passes the cache check, or null otherwise.
   */
  public function getCacheFile(string $module_name)
  {
    $check = $this->getCheckCacheFile($module_name);

    if ($check == 'cacheGithub') {
      $file = @file_get_contents($this->cacheGithub . $module_name, true, $this->context);
      $file = json_decode($file);

      return $file;
    } else {
      return null;
    }
  }

//*************************************************************
// Limit
//*************************************************************

  /**
   * Determines the module search directory or template directory based on user input.
   * Prioritizes `install_module_template_directory` if set and valid,
   * otherwise prioritizes `install_module_directory`,
   * and defaults to `module_search` if none of the prior conditions are met.
   *
   * @return string Returns the sanitized module directory or search string.
   */
  public function getSearchModule(): string
  {
    if (isset($_POST['install_module_template_directory']) && $_POST['install_module_template_directory'] != '0 ' && !empty($_POST['install_module_template_directory'])) {
      $result = HTML::sanitize($_POST['install_module_template_directory']);
    } elseif (isset($_POST['install_module_directory']) && $_POST['install_module_directory'] != '0 ' && !empty($_POST['install_module_directory'])) {
      $result = HTML::sanitize($_POST['install_module_directory']);
    } else {
      $result = HTML::sanitize($_POST['module_search']);
    }

    return $result;
  }

  /**
   * Executes a search on a GitHub repository and retrieves the results based on the specified name or the default organization and module.
   *
   * @param string|null $name The specific repository URL to search. If null, the search will default to the organization and module defined in the instance.
   * @return mixed The decoded JSON response from the GitHub search API.
   */
  public function getSearchInsideRepo(string|null $name = null)
  {
    if (is_null($name)) {
      $search = $this->githubApi . '/search/repositories?q=org%3A' . $this->githubRepoName . '+' . $this->getSearchModule();
      $search_url = @file_get_contents($search, true, $this->setContext()); //content of readme.
    } else {
      $search_url = @file_get_contents($name, true, $this->setContext()); //content of readme.
    }

    $result = json_decode($search_url);

    return $result;
  }

  /**
   * Retrieves the total count of search results from the repository.
   *
   * @return int The total count of search results. Returns 0 if the result is not an object.
   */
  public function getSearchTotalCount(): int
  {
    $result = $this->getSearchInsideRepo();

    if (is_object($result)) {
      $count = $result->total_count;
    } else {
      $count = 0;
    }

    return $count;
  }

  /**
   * Constructs and returns the URL for a GitHub repository using predefined class properties.
   *
   * @return string The URL of the GitHub repository.
   */
  private function getGithubRepo(): string
  {
    $url = $this->githubApi . '/' . $this->githubRepo . '/' . $this->githubRepoName . '/';

    return $url;
  }

  /**
   * Retrieves the local version of a specified JSON module.
   *
   * @param string $module_name The name of the module whose local version is to be retrieved.
   * @return mixed The version of the module if found, or -1 if the module does not exist locally.
   */
  public function getJsonModuleLocalVersion(string $module_name)
  {
    if (is_file($this->cacheGithub . $module_name . '.json')) {
      $json = file_get_contents($this->cacheGithub . $module_name . '.json', true, $this->context);
      $result = json_decode($json);
      $version = $result->version;
    } elseif (is_file($this->cacheGithubTemp . $module_name . '.json')) {
      $json = file_get_contents($this->cacheGithubTemp . $module_name . '.json', true, $this->context);
      $result = json_decode($json);
      $version = $result->version;
    } else {
      $version = -1;
    }

    return $version;
  }

//******************************************
// Module
//******************************************

  /**
   * Retrieves JSON content information for a specified module from the repository.
   *
   * @param string $module_name The name of the module for which the repository content information is fetched.
   */
  public function getJsonRepoContentInformationModule(string $module_name)
  {
    $json = @file_get_contents($this->getGithubRepo() . $module_name . '/contents/' . $this->ModuleInfosJson . '?ref=master', true, $this->context);
    $result = json_decode($json);

//include cache in Github Tmp
    $this->getCacheFile($module_name);

    return $result;
  }

  /**
   * Retrieves JSON module information from a given URL and decodes it into a PHP object.
   *
   * @param string $url The URL from which the JSON data will be fetched.
   *
   * @return mixed Returns the decoded JSON data as a PHP object or null if the decoding fails.
   */
  public function getJsonModuleInformaton(string $url)
  {
    $json = @file_get_contents($url, true, $this->setContext());
    $result_content_module = json_decode($json);

    return $result_content_module;
  }

  /**
   * Downloads the module master archive from a specified GitHub link and saves it as a zip file.
   *
   * @param string $module_name Name of the module to be archived and saved.
   * @return void
   */
  public function getModuleMasterArchive(string $module_name)
  {
    if (!empty($module_name) || !is_null($module_name)) {
      $url = HTML::sanitize($_POST['githubLink']);

      if (!empty($url)) {
        $authentication = @file_get_contents($url);

        file_put_contents($this->saveFileFromGithub . '/' . $module_name . '.zip', $authentication);
      } else {
        $this->app->rediret('Upgrade');
      }
    }
  }

  /**
   * Handles the installation process for a module by extracting its files,
   * copying necessary components to relevant directories, and managing the
   * module's operational state.
   *
   * @param string $file The name of the module file to be installed (without extension).
   * @return void
   */
  public function getInstallModuleTemplate(string $file)
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if ($this->checkDirectoryOnlineUpdate() === true) {
      $this->getCloseOpenStore('true');

      $this->getExtractZip($this->saveFileFromGithub . '/' . $file . '.zip', $this->saveFileFromGithub);

      if (is_dir($this->saveFileFromGithub . '/' . $file . '-master')) {
// copy json in cache
        $json_source = $this->saveFileFromGithub . '/' . $file . '-master/' . $this->ModuleInfosJson . '/';
        $json_destination = $this->cacheGithub;
        @ModuleDownload::smartCopy($json_source, $json_destination);

// copy files their directories
        $source = $this->saveFileFromGithub . '/' . $file . '-master';
        $dest_default_template = CLICSHOPPING::getConfig('dir_root', 'Shop');
        @ModuleDownload::smartCopy($source, $dest_default_template);

// copy in the current theme used
//          $dest_template = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'template/' . SITE_THEMA;
//          ModuleUpload::smartCopy($source, $dest_template);


        if (isset($source)) {
          @ModuleDownload::removeDirectory($source);
        }

        $CLICSHOPPING_MessageStack->add($this->app->getDef('success_files_installed'), 'success', 'header');
      } else {
        $CLICSHOPPING_MessageStack->add($this->app->getDef('error_file_not_installed'), 'error', 'header');
      }

      $this->getCloseOpenStore('false');
    }

    $this->app->redirect('CoreUpgrade');
  }

  /**
   * Installs and fixes the specified module by extracting the zip file,
   * updating directories, and handling post-installation processes.
   *
   * @param string $file The name of the module file to be installed.
   *
   * @return void
   */
  public function getInstallModuleFixe(string $file)
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if ($this->checkDirectoryOnlineUpdate() === true) {
      $this->getCloseOpenStore('true');

      $this->getExtractZip($this->saveFileFromGithub . '/' . $file . '.zip', $this->saveFileFromGithub);

      if (is_dir($this->saveFileFromGithub . '/' . $file . '-master')) {
// copy json in cache
        $json_source = $this->saveFileFromGithub . '/' . $file . '-master/' . $this->ModuleInfosJson . '/';
        $json_destination = $this->cacheGithub;

        @ModuleDownload::smartCopy($json_source, $json_destination);

// copy files their directories
        $source = $this->saveFileFromGithub . '/' . $file . '-master';
        $dest = CLICSHOPPING::getConfig('dir_root', 'Shop');

        @ModuleDownload::smartCopy($source, $dest);

        if (isset($source)) {
          @ModuleDownload::removeDirectory($source);
        }

        $CLICSHOPPING_MessageStack->add($this->app->getDef('success_file_installed'), 'success', 'header');
      } else {
        $CLICSHOPPING_MessageStack->add($this->app->getDef('error_file_not_installed'), 'error', 'header');
      }

      $this->getCloseOpenStore('false');
    }

    $this->app->redirect('ModuleInstallResult&file=' . $file);
  }

  /**
   * Retrieves a dropdown menu for search options with predefined choices.
   *
   * @return string The generated HTML select menu with options for 'official' and 'community' categories, pre-selected based on user input (if available).
   */
  public function getDropDownMenuSearchOption()
  {
    if (isset($_POST['addons_apps'])) {
      $addons_apps = HTML::sanitize($_POST['addons_apps']);
    } else {
      $addons_apps = '';
    }

    $array = [array('id' => 'official', 'text' => $this->app->getDef('text_official')),
      array('id' => 'community', 'text' => $this->app->getDef('text_community'))
    ];

    return HTML::selectMenu('addons_apps', $array, $addons_apps);
  }
}