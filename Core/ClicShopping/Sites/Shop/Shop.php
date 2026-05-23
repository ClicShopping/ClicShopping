<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\Apps;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Cookies;
use ClicShopping\OM\Db;
use ClicShopping\OM\Hooks;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Language;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Service;
use ClicShopping\OM\Session;

use ClicShopping\Apps\Tools\WhosOnline\Classes\Shop\WhosOnlineShop;
use ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin\CacheAdmin;

use function array_slice;
use function count;
use function define;

/**
 * Class Shop
 *
 * Represents the main Shop application.
 * Initializes key components and services required for the Shop site and manages page routing.
 * Extends the SitesAbstract class to utilize base site functionality.
 */
class Shop extends \ClicShopping\OM\Domains\SitesAbstract
{
  protected static ?string $_application;
  protected array $ignored_actions;

  /**
   * Initializes the essential components and services required for the application.
   * This method sets up the following:
   * - Cookies management
   * - Database connection
   * - Hooks system
   * - Application configuration and settings
   * - Session management and handling
   * - Security measures
   * - Template system
   * - Language configurations
   * - Shopping cart actions
   * - WhosOnline tracking and updates
   * - Service execution
   * - Breadcrumb initialization
   *
   * @return void
   */
  protected function init()
  {
    $CLICSHOPPING_Cookies = new Cookies();
    Registry::set('Cookies', $CLICSHOPPING_Cookies);

    try {
      $CLICSHOPPING_Db = Db::initialize();
      Registry::set('Db', $CLICSHOPPING_Db);
    } catch (\Exception $e) {
      HTTP::redirect(CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'error_documents/maintenance.php');
    }

    Registry::set('Hooks', new Hooks());

// set the application parameters
    $Qcfg = $CLICSHOPPING_Db->prepare('select configuration_key as k,
                                             configuration_value as v
                                         from :table_configuration
                                       ');

    if ($Qcfg === false || !is_object($Qcfg)) {
      throw new \RuntimeException('Database prepare failed');
    }

      // Conserver le cache DB existant
      $Qcfg->setCache('configuration');

      // Vérifier d'abord dans Memcached
      $cache_key = 'shop_configuration';
      $cached_config = false;

      if (defined('USE_REDIS') && USE_REDIS == 'True') {
        try {
          $redis = new \Redis();
          $redis->connect('localhost', 6379, 1);
          $cached_config = $redis->get($cache_key);
        } catch (\Exception $e) {
          $cached_config = false;
        }
      }

      if (defined('USE_MEMCACHED') && USE_MEMCACHED == 'True') {
        $memcached = CacheAdmin::getMemcached();
        if ($memcached !== false) {
          $cached_config = $memcached->get($cache_key);
        }
      }

      if ($cached_config === false) {
        $Qcfg->execute();
        $config_data = [];

        while ($Qcfg->fetch()) {
          $key = $Qcfg->value('k');
          $value = $Qcfg->value('v');

          if (!defined($key)) {
            define($key, $value);
          }

          $config_data[$key] = $value;
        }

        // Définir la durée de vie du cache en s'assurant qu'elle est un entier positif
        $cache_ttl = isset($config_data['MEMCACHED_CACHE_LIFETIME']) ? (int)$config_data['MEMCACHED_CACHE_LIFETIME'] : 3600;

        if ($cache_ttl <= 0) {
          $cache_ttl = 3600; // Valeur par défaut si la valeur de la DB est 0 ou invalide
        }

        // Stocker dans le cache
        if (defined('USE_REDIS') && USE_REDIS == 'True' && isset($redis)) {
          $redis->setex($cache_key, $cache_ttl, $config_data);
        } elseif (defined('USE_MEMCACHED') && USE_MEMCACHED == 'True' && isset($memcached)) {
          $memcached->set($cache_key, $config_data, $cache_ttl);
        }
      } else {
        // Utiliser les données du cache
        foreach ($cached_config as $key => $value) {
          define($key, $value);
        }
      }

// set the session name and save path
    $CLICSHOPPING_Session = Session::load();
    Registry::set('Session', $CLICSHOPPING_Session);

// start the session
    $CLICSHOPPING_Session->start();

    $this->ignored_actions[] = session_name();

//request
    if ((HTTP::getRequestType() === 'NONSSL') && (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') && (parse_url(CLICSHOPPING::getConfig('http_server'), PHP_URL_SCHEME) == 'https')) {
      $url_req = 'https://' . $_SERVER['HTTP_HOST'] . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');

      HTTP::redirect($url_req, 301);
    }

// Security
      $securityFile = dirname(CLICSHOPPING::BASE_DIR) . '/Module/SecurityPro/Security.php';

      if (is_file($securityFile)) {
        require_once $securityFile;
      }

    $security_pro = new \Security();

// If you need to exclude a file from cleansing then you can add it like below
//$security_pro->addExclusion( 'some_file.php' );
    $security_pro->cleanse(CLICSHOPPING::getBaseNameIndex());

//template
    Registry::set('Template', new Template());

// language
    $CLICSHOPPING_Language = new Language();
    $CLICSHOPPING_Language->setUseCache(true);
    Registry::set('Language', $CLICSHOPPING_Language);

// language
// voir ligne 84
    $CLICSHOPPING_Language->getLanguageCode();

// include the language translations
    $CLICSHOPPING_Language->loadDefinitions('main');

// Shopping cart actions
    if (isset($_GET['action'])) {
// redirect the customer to a friendly cookie-must-be-enabled page if cookies are disabled
      if (Registry::get('Session')->hasStarted() === false) {
        CLICSHOPPING::redirect(null, 'Info&Cookies');
      }
    }

    WhosOnlineShop::getUpdateWhosOnline();

    Registry::get('Hooks')->watch('Session', 'Recreated', 'execute', function ($parameters) {
      WhosOnlineShop::getWhosOnlineUpdateSession_id($parameters['old_id'], session_id());
    });

    $dirRoot = CLICSHOPPING::getConfig('dir_root', 'Shop');

    if (is_string($dirRoot) && $dirRoot !== '') {
      $file = $dirRoot . 'Core/config_clicshopping.php';

      if (is_file($file)) {
        require_once $file;
      }
    } else {
      trigger_error('Missing config value: dir_root', E_USER_WARNING);
    }

    Registry::set('Service', new Service());
    Registry::get('Service')->start();

//must start after manufacturer service
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Breadcrumb->getCategoriesManufacturer();
  }

  /**
   * Sets the current page based on the default page, GET request parameters, or routing configuration.
   *
   * This method determines the appropriate controller class for the requested page
   * and initializes it. If a valid page controller is identified, the class must
   * implement the `ClicShopping\OM\Interfaces«PagesInterface` interface. The method will execute
   * any actions associated with the page.
   *
   * The selection process prioritizes custom namespaces over default namespaces,
   * and uses routing information or GET request parameters to resolve the page code.
   *
   * @return void
   */
  public function setPage(): void
  {

// en relation avec SitesAbstract
    $page_code = $this->default_page;

    if (class_exists('ClicShopping\Custom\Sites\\' . $this->code . '\Pages\\' . $page_code . '\\' . $page_code)) {
      $class = 'ClicShopping\Custom\Sites\\' . $this->code . '\Pages\\' . $page_code . '\\' . $page_code;
    } elseif (class_exists('ClicShopping\Sites\\' . $this->code . '\Pages\\' . $page_code . '\\' . $page_code)) {
      $class = 'ClicShopping\Sites\\' . $this->code . '\Pages\\' . $page_code . '\\' . $page_code;
    }

    if (!empty($_GET)) {
      if (($route = Apps::getRouteDestination()) !== null) {
        $this->route = $route;

        // Check if destination exists and contains a slash
        if (isset($route['destination']) && is_string($route['destination']) && str_contains($route['destination'], '/')) {
          list($vendor_app, $page) = explode('/', $route['destination'], 2);

          // get controller class name from namespace
          $page_namespace = explode('\\', $page);
          $page_code = $page_namespace[count($page_namespace) - 1];

          if (class_exists('ClicShopping\Apps\\' . $vendor_app . '\\' . $page . '\\' . $page_code)) {
            $class = 'ClicShopping\Apps\\' . $vendor_app . '\\' . $page . '\\' . $page_code;
          }
        }
      } else {
        // If no route is defined, check the GET parameters
        $key = array_keys($_GET)[0];

        if (is_string($key) && preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
          $req = basename($key);
        } else {
          http_response_code(404);
          HTTP::redirect(CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'error_documents/404.php');
        }

        if (class_exists('ClicShopping\Custom\Sites\\' . $this->code . '\Pages\\' . $req . '\\' . $req)) {
          $page_code = $req;

          $class = 'ClicShopping\Custom\Sites\\' . $this->code . '\Pages\\' . $page_code . '\\' . $page_code;
        } elseif (class_exists('ClicShopping\Sites\\' . $this->code . '\Pages\\' . $req . '\\' . $req)) {
          $page_code = $req;

          $class = 'ClicShopping\Sites\\' . $this->code . '\Pages\\' . $page_code . '\\' . $page_code;
        }
      }
    }

    if (isset($class)) {
      if (is_subclass_of($class, 'ClicShopping\OM\Interfaces\PagesInterface')) {
        $this->page = new $class($this);

        $this->page->runActions();
      } else {
        trigger_error('ClicShopping\Sites\Shop\Shop::setPage() - ' . $page_code . ': Page does not implement ClicShopping\OM\Interfaces\PagesInterface and cannot be loaded.');
      }
    }
  }

  /**
   * Resolves a route from a given set of available routes.
   *
   * This method matches a provided route against a set of predefined routes, determines
   * the most specific match, and returns its associated destination.
   *
   * @param array $route The route to be resolved, represented as an ordered array of path segments.
   * @param array $routes The collection of available routes, where keys represent vendor applications
   *                      and values are arrays mapping paths to destination pages.
   * @return array|null Returns an associative array containing 'path', 'destination', and 'score' of the best match,
   *                    or null if no matching route is found.
   */
  public static function resolveRoute(array $route, array $routes)
  {
    $result = [];

    foreach ($routes as $vendor_app => $paths) {
      foreach ($paths as $path => $page) {
        $path_array = explode('&', $path);

        if (count($path_array) <= count($route)) {
          if ($path_array == array_slice($route, 0, count($path_array))) {
            $result[] = [
              'path' => $path,
              'destination' => $vendor_app . DIRECTORY_SEPARATOR . $page,
              'score' => count($path_array)
            ];
          }
        }
      }
    }

    if (!empty($result)) {
      usort($result, function ($a, $b) {
        if ($a['score'] == $b['score']) {
          return 0;
        }

        return ($a['score'] < $b['score']) ? 1 : -1; // sort highest to lowest
      }
      );

      return $result[0];
    }
  }
}
