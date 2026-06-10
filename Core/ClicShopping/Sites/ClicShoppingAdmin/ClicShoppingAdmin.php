<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
declare(strict_types=1);

/**
 * This class represents the ClicShoppingAdmin site, extending the SitesAbstract class.
 * It orchestrates the initialization of various system components, manages the session,
 * handles application routing, and loads language translations.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

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
use ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin\CacheAdmin;
use Exception;
use function count;

class ClicShoppingAdmin extends \ClicShopping\OM\Domains\SitesAbstract
{
  protected static string $default_application = 'Dashboard';

  /**
   * Initializes and configures the application by setting up essential components and services.
   *
   * This method performs various setup actions, including:
   * - Registering cookie management and session handling.
   * - Establishing a database connection and loading application configurations.
   * - Setting up language support, managing templates, and initiating hooks for the application.
   * - Ensuring proper redirection and secure access to the application.
   * - Preloading system configuration and services.
   *
   * @return void
   */
  protected function init()
  {
    global $login_request;

    $CLICSHOPPING_Cookies = new Cookies();
    Registry::set('Cookies', $CLICSHOPPING_Cookies);

    try {
      $CLICSHOPPING_Db = Db::initialize();
      Registry::set('Db', $CLICSHOPPING_Db);
    } catch (Exception $e) {
      HTTP::redirect(CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'error_documents/maintenance.php');
    }

    Registry::set('Hooks', new Hooks());

// set the application parameters
    $Qcfg = $CLICSHOPPING_Db->prepare('select configuration_key as k,
                                              configuration_value as v
                                     from :table_configuration
                                   ');

    if (is_object($Qcfg)) {
      // Conserver le cache DB existant
      $Qcfg->setCache('configuration');

      // Vérifier d'abord dans Memcached
      $cache_key = 'admin_configuration';
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
        // Utiliser les données du cache Memcached
        foreach ($cached_config as $key => $value) {
          define($key, $value);
        }
      }
    }

// Used in the "Backup Manager" to compress backups
    define('LOCAL_EXE_GZIP', 'gzip');
    define('LOCAL_EXE_GUNZIP', 'gunzip');
    define('LOCAL_EXE_ZIP', 'zip');
    define('LOCAL_EXE_UNZIP', 'unzip');

    $CLICSHOPPING_Session = Session::load();
    Registry::set('Session', $CLICSHOPPING_Session);

    $CLICSHOPPING_Session->start();

// language
    $CLICSHOPPING_Language = new Language();

    Registry::set('Language', $CLICSHOPPING_Language);

// Template
    Registry::set('TemplateAdmin', new TemplateAdmin());

// Take language session
    $CLICSHOPPING_Language->getLanguageCode();

// redirect to login page if administrator is not yet logged in
    if (!isset($_SESSION['admin'])) {
      $redirect = false;

      $current_page = CLICSHOPPING::getBaseNameIndex();

// if the first page request is to the login page, set the current page to the index page
// so the redirection on a successful login is not made to the login page again
      if (($current_page == 'login.php') && !isset($_SESSION['redirect_origin'])) {
        $current_page = 'index.php';
      }

      if ($current_page != 'login.php') {
        if (!isset($_SESSION['redirect_origin'])) {
          $_SESSION['redirect_origin'] = [
            'page' => $current_page,
            'get' => []
          ];
        }

// try to automatically login with the HTTP Authentication values if it exists
        if (!isset($_SESSION['auth_ignore'])) {
          if (isset($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) && !empty($_SERVER['PHP_AUTH_PW'])) {
            $_SESSION['redirect_origin']['auth_user'] = $_SERVER['PHP_AUTH_USER'];
            $_SESSION['redirect_origin']['auth_pw'] = $_SERVER['PHP_AUTH_PW'];
          }
        }

        $redirect = true;
      }

      if (!isset($login_request) || isset($_GET['login_request']) || isset($_POST['login_request']) || isset($_COOKIE['login_request']) || isset($_SESSION['login_request']) || isset($_FILES['login_request']) || isset($_SERVER['login_request'])) {
        $redirect = true;
      }

      if ($redirect === true) {
        CLICSHOPPING::redirect('login.php', (isset($_SESSION['redirect_origin']['auth_user']) ? 'action=process' : ''));
      }
    }

// include the language translations
    $CLICSHOPPING_Language->loadDefinitions('main');

    $current_page = CLICSHOPPING::getBaseNameIndex();

    if ($CLICSHOPPING_Language->definitionsExist(pathinfo($current_page, PATHINFO_FILENAME))) {
      $CLICSHOPPING_Language->loadDefinitions(pathinfo($current_page, PATHINFO_FILENAME));
    }

    if ((HTTP::getRequestType() === 'NONSSL') && ($_SERVER['REQUEST_METHOD'] === 'GET') && (parse_url(CLICSHOPPING::getConfig('http_server'), PHP_URL_SCHEME) == 'https')) {
      $url_req = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

      HTTP::redirect($url_req, 301);
    }

// configuration generale du systeme
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
  }

  /**
   * Sets and initializes the page based on the current request parameters.
   *
   * This method determines the correct page controller class to use based on
   * input from GET parameters or default application settings. It validates
   * whether the resolved class implements the required interface and then
   * initializes and executes the page actions. If the page does not implement
   * the required interface, a trigger error is raised.
   *
   * @return void
   */
  public function setPage()
  {
//important dans la redirection sinon page blanche
    $page_code = static::getDefaultApplication();
    $class = 'ClicShopping\Sites\\' . $this->code . '\Pages\\' . $page_code . '\\' . $page_code;

    if (!empty($_GET)) {
      $req = basename(array_keys($_GET)[0]);

      if (($req == 'A') && (count($_GET) > 1)) {
        $app = array_keys($_GET)[1];

        if (str_contains($app, '\\')) {
          list($vendor, $app) = explode('\\', $app);

          $page = Apps::getRouteDestination(null, $vendor . '\\' . $app);

          if (is_array($page)) {
            $page = (string)reset($page);
          }

          if (Apps::exists($vendor . '\\' . $app) && $page!== null) {
// get controller class name from namespace
            $page_namespace = explode('\\', $page);
            $page_code = $page_namespace[count($page_namespace) - 1];

            if (class_exists('ClicShopping\Apps\\' . $vendor . '\\' . $app . '\\' . $page . '\\' . $page_code)) {
              $this->app = $vendor . '\\' . $app;
              $this->route = $this->app . '\\' . $page;
              $this->actions_index = 2;

              $class = 'ClicShopping\Apps\\' . $this->app . '\\' . $page . '\\' . $page_code;
            }
          }
        }
      } else {
        if (class_exists('ClicShopping\Sites\\' . $this->code . '\Pages\\' . $req . '\\' . $req)) {
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
        trigger_error('ClicShopping\Sites\ClicShoppingAdmin\ClicShoppingAdmin::setPage() - ' . $page_code . ': Page does not implement ClicShopping\OM\Interfaces\PagesInterface and cannot be loaded.');
      }
    }
  }

  /**
   * Resolves and retrieves the first matched route from the provided routes array.
   *
   * @param array $route The route to be resolved.
   * @param array $routes An array of available routes to match against.
   *
   * @return mixed The first matched route from the provided routes array.
   */
  public static function resolveRoute(array $route, array $routes) :mixed
  {
    return array_values($routes)[0] ?? null;
  }

  /**
   *
   * @return string Returns the default application name.
   */
  public static function getDefaultApplication(): string
  {
    return static::$default_application;
  }
}
