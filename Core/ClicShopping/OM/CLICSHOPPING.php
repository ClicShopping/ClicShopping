<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use ClicShopping\Service\Shop\SEFU;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;


use DirectoryIterator;
use PDO;
use function array_slice;
use function call_user_func_array;
use function count;
use function defined;
use function func_get_args;
use function function_exists;
use function is_array;
use function is_null;
use function strlen;

/**
 * Core class responsible for initializing the application and handling main configurations, sites, and linking.
 */
class CLICSHOPPING
{
  public const BASE_DIR = CLICSHOPPING_BASE_DIR;
  public const VALID_CLASS_NAME_REGEXP = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/'; // https://php.net/manual/en/language.oop5.basic.php

  protected static string $version;
  protected static string $site = 'Shop';
  protected static array $cfg = [];
  protected static ?string $application;
  protected static string $directoryVersion;

  /**
   * Initializes the system by setting up configuration, error handling, HTTP settings, and the site application.
   *
   * @return void
   */
  public static function initialize()
  {
    self::loadConfig();

    DateTime::setTimeZone();

    ErrorHandler::initialize();

    HTTP::setRequestType();
    HTTP::getHSTS();

    self::setSiteApplication();
  }

  /**
   * Retrieves and returns the current version number.
   *
   * The method reads the version information from a `version.json` file,
   * validates that the version number is numeric, and caches it for future calls.
   * If the version number is not numeric, an error is triggered.
   *
   * @return string|null The version number as a string, or null if it is not set or invalid.
   */
  public static function getVersion(): string|null
  {
    if (!isset(self::$version)) {
      try {
        $file = self::BASE_DIR . 'version.json';

        $current = @file_get_contents($file);
        if ($current === false) {
          throw new \Exception('Cannot read file: ' . $file);
        }

        $v = json_decode($current);
        if (!isset($v->version)) {
          throw new \Exception('Missing version field in: ' . $file);
        }

        if (is_numeric($v->version)) {
          self::$version = $v->version;
        } else {
          trigger_error('Version number is not numeric. Please verify: ' . $file);
        }
      } catch (\Throwable $e) {
        return null;
      }
    }

    return self::$version;
  }

  /**
   * Retrieves the version directory if it has been set, otherwise attempts to set it
   * by reading and decoding the content of a 'version.json' file in the base directory.
   * Ensures the directory version is numeric, otherwise triggers an error.
   *
   * @return string|null Returns the directory version as a string if set, or null if not set.
   */
  public static function getVersionDirectory(): string|null
  {
    if (!isset(self::$version)) {
      $file = self::BASE_DIR . 'version.json';

      $current = trim(file_get_contents($file));

      $v = json_decode($current);

      if (is_numeric($v->directory_version)) {
        self::$directoryVersion = $v->directory_version;
      } else {
        trigger_error('Directory Version number is not numeric. Please verify: ' . $file);
      }
    }
    return self::$directoryVersion;
  }

  /**
   * Checks if a given site exists by verifying its class name and checking if the associated class exists.
   *
   * @param string $site The name of the site to check.
   * @return bool True if the site exists, false otherwise.
   */
  public static function siteExists(string $site): bool
  {
    return self::isValidClassName($site) && class_exists('ClicShopping\Sites\\' . $site . '\\' . $site);
  }

  /**
   * Loads the specified site or uses the default site if none is provided.
   *
   * @param string|null $site The name of the site to load. If null, the default site will be used.
   * @return void
   */
  public static function loadSite(string|null $site = null)
  {
    if (!isset($site)) {
      $site = self::$site;
    }

    self::setSite($site);
  }

  /**
   * Sets the current site and initializes the site-related class.
   *
   * @param string $site The name of the site to be set. If the site does not exist, the current site will remain unchanged.
   * @return void
   */
  public static function setSite(string $site)
  {
    if (!self::siteExists($site)) {
      $site = self::$site;
    }

    self::$site = $site;

    $class = 'ClicShopping\Sites\\' . $site . '\\' . $site;

    $CLICSHOPPING_Site = new $class();
    Registry::set('Site', $CLICSHOPPING_Site);

    $CLICSHOPPING_Site->setPage();
  }

  /**
   * Retrieves the current site name.
   *
   * @return string The name of the site.
   */
  public static function getSite(): string
  {
    return self::$site;
  }

  /**
   * Checks if a site is set.
   *
   * @return bool Returns true if a site is set, otherwise false.
   */
  public static function hasSite(): bool
  {
    return isset(self::$site);
  }

  /**
   * Checks whether the current site has a specific page available.
   *
   * @return mixed Returns the result of the hasPage method from the Site registry entry.
   */
  public static function hasSitePage(): mixed
  {
    return Registry::get('Site')->hasPage();
  }

  /**
   * Retrieves the file associated with the current site page.
   *
   * @return mixed The file of the current site page, as determined by the Page object.
   */
  public static function getSitePageFile(): mixed
  {
    return Registry::get('Site')->getPage()->getFile();
  }

  /**
   * Retrieves and utilizes the site template associated with the current page file.
   *
   * @return mixed The result of using the site template, which depends on the implementation
   * of the `useSiteTemplate` method in the current page object.
   */
  public static function useSiteTemplateWithPageFile(): mixed
  {
    return Registry::get('Site')->getPage()->useSiteTemplate();
  }

  /**
   * Determines if the current page is an RPC (Remote Procedure Call) page.
   *
   * @return bool Returns true if the current page is an RPC page, otherwise false.
   */
  public static function isRPC(): bool
  {
    $CLICSHOPPING_Site = Registry::get('Site');

    return $CLICSHOPPING_Site->hasPage() && $CLICSHOPPING_Site->getPage()->isRPC();
  }

  /**
   * Creates a URL by combining the specified page, parameters, and additional options like session ID and SEO settings.
   *
   * @param string|null $page The page name or path to include in the URL. If not provided, a default page is determined based on the application and SEO settings.
   * @param string|null $parameters Additional URL parameters to append to the query string. Supports specific character sanitization.
   * @param bool $add_session_id Determines whether to append the session ID to the URL. Default is true.
   * @param bool $search_engine_safe Specifies if the URL should be transformed to a search engine friendly format. Default is true.
   * @return string The constructed URL based on the provided parameters and configuration.
   */
  public static function link(?string $page = null, ?string $parameters = null, bool $add_session_id = true, bool $search_engine_safe = true): string
  {
    /*
     * remove index.php for the seo
     */
    if (is_null($page)) {
      if (self::getSite() === 'ClicShoppingAdmin') {
        $page = self::getConfig('bootstrap_file');
      } else {
        if ((\defined('SEARCH_ENGINE_FRIENDLY_URLS_PRO') && SEARCH_ENGINE_FRIENDLY_URLS_PRO == 'true') && (defined('SEARCH_ENGINE_FRIENDLY_URLS') && SEARCH_ENGINE_FRIENDLY_URLS == 'true')) {
//SEO with htaccess
// force to remove seo htaccess if the customer is connected
          if (isset($_SESSION['login_customer_id'])) {
            $page = self::getConfig('bootstrap_file');
          } else {
            $page = '';
          }
        } elseif ((\defined('SEARCH_ENGINE_FRIENDLY_URLS') && SEARCH_ENGINE_FRIENDLY_URLS == 'true')) {
          $page = self::getConfig('bootstrap_file');
        } else {
          $page = self::getConfig('bootstrap_file');
        }
      }
    }

    $page = HTML::sanitize($page);

    $site = $req_site = self::$site;

    if ((str_contains($page, '/')) && (preg_match('/^([A-Z][A-Za-z0-9-_]*)\/(.*)$/', $page, $matches) === 1) && self::siteExists($matches[1])) {
      $req_site = $matches[1];
      $page = $matches[2];
    }

    if (!is_bool($add_session_id)) {
      $add_session_id = true;
    }

    if (!is_bool($search_engine_safe)) {
      $search_engine_safe = true;
    }

    if (($add_session_id === true) && ($site !== $req_site)) {
      $add_session_id = false;
    }

    $link = self::getConfig('http_server', $req_site) . self::getConfig('http_path', $req_site) . $page;

    if (!empty($parameters)) {
      $p = HTML::sanitize($parameters);

      if (self::$site == 'ClicShoppingAdmin') {
        $replace_backslash = "%5C";
      } else {
        $replace_backslash = "\\";
      }

      $search = [
        "\\", // apps
        '{', // product attributes
        '}' // product attributes
      ];

      $replace = [
        $replace_backslash,
        '%7B',
        '%7D'
      ];

      $p = str_replace($search, $replace, $p);
//xss patch
      if ((\defined('SEARCH_ENGINE_FRIENDLY_URLS') && SEARCH_ENGINE_FRIENDLY_URLS == 'false')) {
        $p = htmlspecialchars($p);
      }

      $link .= '?' . $p;
      $separator = '&';
    } else {
      $separator = '?';
    }

    while ((substr($link, -1) == '&') || (substr($link, -1) == '?')) {
      $link = substr($link, 0, -1);
    }

// Add the session ID when moving from different HTTP and HTTPS servers, or when session is active
    if (($add_session_id === true) && Registry::exists('Session')) {
      $CLICSHOPPING_Session = Registry::get('Session');

      if ($CLICSHOPPING_Session->hasStarted() && ($CLICSHOPPING_Session->isForceCookies() === false)) {
        if ((strlen(session_id()) > 0) || (((HTTP::getRequestType() == 'NONSSL') && (parse_url(self::getConfig('http_server', $req_site), PHP_URL_SCHEME) == 'https')) || ((HTTP::getRequestType() == 'SSL') && (parse_url(self::getConfig('http_server', $req_site), PHP_URL_SCHEME) == 'http')))) {
          $link .= $separator . HTML::sanitize(session_name() . '=' . session_id());
        }
      }
    }

    while (str_contains($link, '&&')) {
      $link = str_replace('&&', '&', $link);
    }

    /*
     * Change url syntax if Seo is enable or not
     */

    if (self::getSite() === 'Shop') {
//SEO with htaccess
      if ($search_engine_safe === true && SEFU::start() && \defined('SEARCH_ENGINE_FRIENDLY_URLS_PRO') && SEARCH_ENGINE_FRIENDLY_URLS_PRO == 'true' && (defined('SEARCH_ENGINE_FRIENDLY_URLS') && SEARCH_ENGINE_FRIENDLY_URLS == 'true')) {
//SEO with htaccess
// remove seo htaccess if the customer is connected
        if (isset($_SESSION['login_customer_id'])) {
          $link = str_replace(['?', '&', '='], ['/', '/', '-'], $link);
        } else {
          $link = str_replace(['?', '&', '='], ['', '/', '-'], $link);
        }
      } elseif ($search_engine_safe === true && SEFU::start() && (\defined('SEARCH_ENGINE_FRIENDLY_URLS') && SEARCH_ENGINE_FRIENDLY_URLS == 'true')) {
        $link = str_replace(['?', '&', '='], ['/', '/', '-'], $link);
      }
    }

    return $link;
  }

  /**
   * Generates a URL link to an image located in the configured HTTP images path.
   *
   * This method processes the given arguments to construct the image path
   * based on the site configuration and requested page. If the page argument
   * includes a specific site, it will validate the site and adjust the path
   * accordingly. Finally, it delegates the URL generation to the `link` method.
   *
   * @return string The constructed URL to the image.
   */
  public static function linkImage(): string
  {
    $args = func_get_args();

    if (!isset($args[0])) {
      $args[0] = null;
    }

    if (!isset($args[1])) {
      $args[1] = null;
    }

    $args[2] = false;

    $page = $args[0];
    $req_site = self::$site;

    if ((str_contains($page, '/')) && (preg_match('/^([A-Z][A-Za-z0-9-_]*)\/(.*)$/', $page, $matches) === 1) && self::siteExists($matches[1])) {
      $req_site = $matches[1];
      $page = $matches[2];
    }

    $args[0] = $req_site . DIRECTORY_SEPARATOR . self::getConfig('http_images_path', $req_site) . $page;

    $url = (static::link(...))(...$args);

    return $url;
  }

  /**
   * Generates a public URL based on the provided arguments and the current site configuration.
   * This method dynamically constructs the URL using specific page and site information.
   *
   * @return string The generated URL linking to the public site directory.
   * http://localhost/clicshopping_test/public/Sites/ClicShoppingAdmin/
   * http://localhost/clicshopping_test/public/Sites/Shop/
   */
  public static function linkPublic(): string
  {
    $args = func_get_args();

    if (!isset($args[0])) {
      $args[0] = null;
    }

    if (!isset($args[1])) {
      $args[1] = null;
    }

    $args[2] = false;

    $page = $args[0];
    $req_site = self::$site;

    if ((str_contains($page, '/')) && (preg_match('/^([A-Z][A-Za-z0-9-_]*)\/(.*)$/', $page, $matches) === 1) && self::siteExists($matches[1])) {
      $req_site = $matches[1];
      $page = $matches[2];
    }

    $args[0] = 'Shop/public/Sites/' . $req_site . DIRECTORY_SEPARATOR . $page;

    $url = (self::link(...))(...$args);

    return $url;
  }

  /**
   * Redirects to a generated URL based on the provided arguments.
   *
   * @return string The URL to which the redirection is performed.
   */
  public static function redirect(): string
  {
    $args = func_get_args();

    $url = (self::link(...))(...$args);

    if ((strstr($url, "\n") !== false) || (strstr($url, "\r") !== false)) {
      $url = self::link(null, '', false);
    }

    HTTP::redirect($url);
  }

  /**
   * Retrieves a language definition using the Language class registered in the system.
   *
   * @return string Returns the language definition corresponding to the provided arguments.
   */
  public static function getDef(): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    return ($CLICSHOPPING_Language->getDef(...))(...func_get_args());
  }

  /**
   * Checks if the specified route exists by comparing the beginning of the $_GET keys with the given path array.
   *
   * @param array $path The expected path represented as an array of strings.
   * @return bool True if the given path matches the start of $_GET keys, false otherwise.
   */
  public static function hasRoute(array $path): bool
  {
    return array_slice(array_keys($_GET), 0, count($path)) == $path;
  }

  /**
   * Loads configuration files for the application.
   *
   * This method handles the loading of default global configuration files,
   * as well as site-specific configuration files located in predefined directories.
   *
   * @return void
   */
  public static function loadConfig(): void
  {
    self::loadConfigFile(self::BASE_DIR . 'Conf/global.php', 'global');

    if (is_file(self::BASE_DIR . 'Custom/Conf/global.php')) {
      self::loadConfigFile(self::BASE_DIR . 'Custom/Conf/global.php', 'global');
    }

    foreach (glob(self::BASE_DIR . 'Sites/*', GLOB_ONLYDIR) as $s) {
      $s = basename($s);
      if (self::siteExists($s, false) && is_file(self::BASE_DIR . 'Sites/' . $s . '/site_conf.php')) {
        self::loadConfigFile(self::BASE_DIR . 'Sites/' . $s . '/site_conf.php', $s);

        if (is_file(self::BASE_DIR . 'Custom/Sites/' . $s . '/site_conf.php')) {
          self::loadConfigFile(self::BASE_DIR . 'Custom/Sites/' . $s . '/site_conf.php', $s);
        }
      }
    }
  }

  /**
   * Loads a configuration file and parses its contents into the specified group.
   *
   * @param string $file The path to the configuration file to be loaded.
   * @param string $group The group name under which the configuration data will be stored.
   * @return void
   */

  public static function loadConfigFile(string $file, string $group): void
  {
    $cfg = [];
    $ini = false;

    if (is_file($file)) {
      include($file);

      if (isset($ini)) {
        $cfg = parse_ini_string($ini);
      }
    }

    if (!empty($cfg)) {
      self::$cfg[$group] = (isset(self::$cfg[$group])) ? array_merge(self::$cfg[$group], $cfg) : $cfg;
    }
  }

  /**
   * Retrieves a configuration value for a given key and group.
   *
   * @param string $key The configuration key to retrieve.
   * @param string|null $group The group from which to retrieve the configuration. Defaults to the current site if not provided.
   * @return mixed|null The configuration value if found, or null if the key does not exist in the specified group or global configuration.
   */
  public static function getConfig(string $key, string|null $group = null)
  {
    if (!isset($group)) {
      $group = self::getSite();
    }

    if (isset(self::$cfg[$group][$key])) {
      return self::$cfg[$group][$key];
    }

    if (isset(self::$cfg['global'][$key])) {
      return self::$cfg['global'][$key];
    }
  }

  /**
   * Checks whether a configuration key exists in the specified group or globally.
   *
   * @param string $key The configuration key to check.
   * @param string|null $group The configuration group to check within. If null, defaults to the current site.
   * @return bool Returns true if the configuration key exists in the given group or globally; otherwise, false.
   */
  public static function configExists(string $key, string|null $group = null): bool
  {
    if (!isset($group)) {
      $group = self::getSite();
    }

    if (isset(self::$cfg[$group][$key])) {
      return true;
    }

    return isset(self::$cfg['global'][$key]);
  }

  /**
   * Sets a configuration value for a specified key within an optional group.
   *
   * @param string $key The configuration key.
   * @param mixed $value The configuration value to be set.
   * @param string|null $group The group to which the configuration belongs. Defaults to 'global' if not provided.
   * @return void
   */
  public static function setConfig(string $key, mixed $value, string|null $group = null)
  {
    if (!isset($group)) {
      $group = 'global';
    }

    self::$cfg[$group][$key] = $value;
  }

  /**
   * Autoloads classes based on their namespace and file structure.
   *
   * This method checks if the class belongs to the 'ClicShopping' namespace and attempts to load
   * the corresponding file from predefined directories. It supports loading from both core and custom
   * directories, as well as handling external libraries.
   *
   * @param string $class The fully qualified name of the class to be autoloaded.
   * @return bool Returns true if the class file was successfully loaded, false otherwise.
   */
  public static function autoload(string $class)
  {
    $prefix = 'ClicShopping\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
      $class_path = str_replace('\\', '/', $class);

      $file = self::BASE_DIR . 'External' . DIRECTORY_SEPARATOR . $class_path . '.php';

      if (is_file($file)) {
        require_once($file);

        return true;
      }

      $site_dirs = [
        'Sites',
        'Custom'
      ];

      foreach ($site_dirs as $site_dir) {
        $dir = new DirectoryIterator(self::BASE_DIR . $site_dir);

        foreach ($dir as $f) {
          if (!$f->isDot() && $f->isDir()) {
            $file = $f->getPath() . DIRECTORY_SEPARATOR . $f->getFilename() . DIRECTORY_SEPARATOR . 'External' . DIRECTORY_SEPARATOR . $class_path . '.php';

            if (is_file($file)) {
              require($file);

              return true;
            }
          }
        }
      }

      return false;
    }

    if (strncmp($prefix . 'OM\Module\\', $class, strlen($prefix . 'OM\Module\\')) === 0) { // TODO remove and fix namespace
      $file = dirname(self::BASE_DIR) . DIRECTORY_SEPARATOR . str_replace(['ClicShopping\OM\\', '\\'], ['', '/'], $class) . '.php';
      $custom = dirname(self::BASE_DIR) . DIRECTORY_SEPARATOR . str_replace(['ClicShopping\OM\\', '\\'], ['ClicShopping\Custom\OM\\', '/'], $class) . '.php';
    } else {
      $file = dirname(self::BASE_DIR) . DIRECTORY_SEPARATOR . str_replace('\\', '/', $class) . '.php';
      $custom = str_replace('ClicShopping/OM/', 'ClicShopping/Custom/OM/', $file);
    }

    if (is_file($custom)) {
      require_once($custom);
    } elseif (is_file($file)) {
      require_once($file);
    }

    if (is_file(self::BASE_DIR . 'External/vendor/autoload.php')) {
      require_once(self::BASE_DIR . 'External/vendor/autoload.php');
    }
  }

  /**
   * Checks if the specified site application exists.
   *
   * @param string $application The name of the application to check.
   * @return bool Returns true if the application exists, false otherwise.
   */
  protected static function siteApplicationExists(string $application): bool
  {
    $class = self::isValidClassName($application) && class_exists('ClicShopping\\Sites\\' . self::getSite() . '\\Pages\\' . $application . '\\' . $application);

    return $class;
  }

  /**
   * Sets the site application based on the provided application name or the query parameters.
   *
   * @param string|null $application The name of the application to set. If null, it attempts to determine the application from query parameters.
   * @return void
   */
  protected static function setSiteApplication(?string $application = null)
  {
    if (isset($application)) {
      if (!self::siteApplicationExists($application)) {
        trigger_error('Apps \'' . $application . '\' does not exist for Site \'' . self::getSite());

        $application = null;
      }
    } else {
      if (!empty($_GET)) {
        $key = key(array_slice($_GET, 0, 1, true));

        if (isset($key)) {
          $requested_application = HTML::sanitize(basename($key));

          if ($requested_application == self::getSite()) {
            $key = key(array_slice($_GET, 1, 1, true));

            if (isset($key)) {

              $requested_application = HTML::sanitize(basename($key));
            }
          }

          if ((preg_match('/^[A-Za-z0-9-_]+$/', $requested_application) === 1) && self::siteApplicationExists($requested_application)) {
            $application = $requested_application;
          }
        }
      }
    }

    self::$application = $application;
  }

  /**
   * Retrieves the current site application.
   *
   * @return string|null Returns the current site application name if set, or null if not set.
   */
  public static function getSiteApplication(): ?string
  {
    return self::$application;
  }

  /**
   * Retrieves all GET parameters as a query string while excluding specified keys.
   *
   * @param mixed $exclude A single key or an array of keys to exclude from the returned query string.
   *                        If not provided, a default list of keys is excluded.
   * @return string A query string containing all GET parameters except the excluded ones.
   *                The string does not include the trailing ampersand (&) if present.
   */
  public static function getAllGET(mixed $exclude = null)
  {
    if (!is_array($exclude)) {
      if (!empty($exclude)) {
        $exclude = [$exclude];
      } else {
        $exclude = [];
      }
    }

    $params = '';

    $array = [
      self::getSite(),
      Registry::get('Session')->getName(),
      'error',
      'x',
      'y'
    ];

    $exclude = array_merge($exclude, $array);

    if (is_array($_GET)) {
      foreach ($_GET as $key => $value) {
        if (!in_array($key, $exclude, true)) {
          if (is_array($value)) {
            $value = http_build_query([$key => $value]);
            $params .= $value . '&';
          } else {
            $params .= $key . (!empty($value) ? '=' . $value : '') . '&';
          }
        }
      }
    }

    if (!empty($params)) {
      $params = substr($params, 0, -1);
    }

    return $params;
  }

  /**
   * Retrieves and returns the index path of the script relative to the configured 'http_path'.
   *
   * @return string The index path of the current script.
   */
  public static function getIndex(): string
  {
    $req = parse_url($_SERVER['SCRIPT_NAME']);
    $result = substr($req['path'], strlen(self::getConfig('http_path', 'Shop')));

    return $result;
  }

  /**
   * Retrieves the base name of the index.
   *
   * @return string The base name of the index file.
   */
  public static function getBaseNameIndex(): string
  {
    return basename(self::getIndex());
  }

  /**
   * Converts an associative array into a query string-style formatted string.
   *
   * @param array $array The associative array to be converted into a string.
   * @param string|array $exclude Keys to be excluded from the resulting string. Default is an empty string.
   * @param string|array $equals The character(s) used to separate keys and values in the resulting string. Default is '='.
   * @param string $separator The character used to separate each key-value pair in the resulting string. Default is '&'.
   * @return string|null The formatted string representation of the array, or null if the input is not an array.
   */
  public static function arrayToString(array $array, string|array $exclude = '', string|array $equals = '=', string $separator = '&'): ?string
  {
    if (!is_array($exclude)) {
      $exclude = [];
    }

    $get_string = '';

    if (is_array($array)) {
      foreach ($array as $key => $value) {
        if ((!\in_array($key, $exclude, true)) && ($key != 'x') && ($key != 'y')) {
          $stringValue = is_array($value) ? self::arrayToString($value) : (string)$value;
          $get_string .= $key . $equals . $stringValue . $separator;
        }
      }

      $remove_chars = strlen($separator);
      $get_string = substr($get_string, 0, -$remove_chars);
    }

    return $get_string;
  }

  /**
   * Validates whether the provided class name matches the defined pattern.
   *
   * @param string $classname The class name to validate.
   * @return bool True if the class name is valid, false otherwise.
   */
  public static function isValidClassName(string $classname): bool
  {
    return preg_match(self::VALID_CLASS_NAME_REGEXP, $classname) === 1;
  }

  /**
   * Retrieves detailed information about the system, MySQL, PHP, and other server configurations.
   *
   * @return array An associative array containing the following information:
   *               - ClicShopping: Version details of the application.
   *               - system: Details about the system including date, hostname, operating system,
   *                 kernel version, uptime, and HTTP server.
   *               - mysql: MySQL server version and current date/time.
   *               - php: Detailed PHP configurations such as version, Zend engine version,
   *                 SAPI type, memory limits, error reporting settings, file upload settings,
   *                 and loaded extensions.
   */
  public static function getSystemInformation(): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qdate = $CLICSHOPPING_Db->query('select now() as datetime');

    try {
      [$system, $host, $kernel] = preg_split('/[\s,]+/', @exec('uname -a'), 5);
    } catch (\Throwable $e) {
      $system = $host = $kernel = 'unavailable';
    }

    try {
      $uptime = @exec('uptime');
    } catch (\Throwable $e) {
      $uptime = 'unavailable';
    }

    try {
      $version = self::getVersion();
    } catch (\Throwable $e) {
      $version = 'unavailable';
    }

    $data = [];

    $data['clicshopping'] = ['version' => $version];

    $data['system'] = [
      'date' => date('Y-m-d H:i:s O T'),
      'system' => $system,
      'host' => $host,
      'os' => PHP_OS,
      'kernel' => $kernel,
      'uptime' => $uptime,
      'http_server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
    ];

    $data['mysql'] = [
      'version' => $CLICSHOPPING_Db->getAttribute(PDO::ATTR_SERVER_VERSION),
      'date' => $Qdate->value('datetime')
    ];

    $data['php'] = [
      'version' => PHP_VERSION,
      'zend' => zend_version(),
      'sapi' => PHP_SAPI,
      'int_size' => defined('PHP_INT_SIZE') ? PHP_INT_SIZE : '',
      'open_basedir' => (int)@ini_get('open_basedir'),
      'memory_limit' => @ini_get('memory_limit'),
      'error_reporting' => error_reporting(),
      'display_errors' => (int)@ini_get('display_errors'),
      'allow_url_fopen' => (int)@ini_get('allow_url_fopen'),
      'file_uploads' => (int)@ini_get('file_uploads'),
      'upload_max_filesize' => @ini_get('upload_max_filesize'),
      'post_max_size' => @ini_get('post_max_size'),
      'disable_functions' => @ini_get('disable_functions'),
      'disable_classes' => @ini_get('disable_classes'),
      'filter.default' => @ini_get('filter.default'),
      'unicode.semantics' => (int)@ini_get('unicode.semantics'),
      'zend_thread_safty' => (int)function_exists('zend_thread_id'),
      'extensions' => get_loaded_extensions()
    ];

    return $data;
  }

  /**
   * Check if a Apps contant is activated or not
   * @param array $constant
   * @return bool
   */
  public static function checkAppsIsActivated(array $constant): bool
  {
    $requiredConstants = $constant;

    foreach ($requiredConstants as $const) {
      if (!\defined($const) || \constant($const) !== 'True') {
        return false;
      }
    }

    return true;
  }
}
