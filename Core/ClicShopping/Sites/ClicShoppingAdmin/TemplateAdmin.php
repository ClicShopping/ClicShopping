<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\Apps;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use DirectoryIterator;
use function in_array;
use function is_null;
/**
 * TemplateAdmin class handles various directory and path-related operations
 * for the administration area of the application. It extends the Template class
 * from the Shop namespace, inheriting basic template functionalities and adding
 * specific methods for the admin site environment.
 */
class TemplateAdmin extends \ClicShopping\Sites\Shop\Template
{
  protected string $directoryAdminLanguages = 'languages/';
  protected string $directoryAdmin = 'ClicShoppingAdmin/';
  protected string $directoryAdminBoxes = 'boxes/';
  protected string $directoryAdminImages = 'images/';
  protected string $directoryAdminIncludes = 'Core/';
  protected string $directoryAdminModules = 'modules/';
  protected string $directoryAdminSources = 'sources/';
  public $default;
  public $key;

  /**********************************************
   * Path
   ************************************************/

  /**
   * Retrieves the directory path for the shop's default template in HTML format.
   *
   * @return string The full path to the shop's default template directory.
   */
  public function getDirectoryPathShopDefaultTemplateHtml(): string
  {
    return parent::getPathRoot() . parent::getDefaultTemplateDirectory(); // /sources/template/default
  }

  /**
   * Retrieves the path to the shop languages directory.
   *
   * @return string The path to the shop languages directory.
   */
  public function getPathLanguageShopDirectory(): string
  {
    $path_shop_languages_directory = parent::getPathRoot() . $this->directoryAdminSources . $this->directoryAdminLanguages;

    return $path_shop_languages_directory;
  }

  /**
   * Retrieves the path to the shop's download directory. If a specific directory is provided,
   * it appends it to the base download directory path.
   *
   * @param string|null $directory Optional directory to append to the base download directory path.
   * @return string The full path to the shop's download directory, possibly including the appended directory.
   */
  public function getPathDownloadShopDirectory(?string $directory = null): string
  {
    $path_shop_public_download_directory = parent::getPathDownloadShopDirectory($directory);

    return $path_shop_public_download_directory;
  }

  /**
   * Retrieves the directory path for the shop module's HTML template.
   *
   * @param string $name Name of the module to locate the HTML template for.
   * @return string The full directory path to the HTML template of the specified module.
   */

  public function getDirectoryPathModuleShopTemplateHtml(string $name): string
  {
    if (file_exists(parent::getPathRoot() . parent::getDynamicTemplateDirectory() . DIRECTORY_SEPARATOR . $this->directoryAdminModules . $name . '/template_html/')) {
      $template_directory = parent::getPathRoot() . parent::getDynamicTemplateDirectory() . DIRECTORY_SEPARATOR . $this->directoryAdminModules . $name . '/template_html/';
    } else {
      $template_directory = parent::getPathRoot() . $this->getDefaultTemplateDirectory() . DIRECTORY_SEPARATOR . $this->directoryAdminModules . $name . '/template_html/';
    }

    return $template_directory;
  }

  /**
   * Retrieves the directory path for shop template images.
   *
   * @return string The complete directory path to shop template images.
   */

  public function getDirectoryPathTemplateShopImages(): string
  {
    return parent::getPathRoot() . parent::getDirectoryTemplateImages(); // CLICSHOPPING::getConfig('dir_root', 'Shop1') . 'sources/images/
  }


  /**
   * Retrieves the HTTP URL for the template shop images directory.
   *
   * @return string The full URL to the shop images directory.
   */

  public function getHttpTemplateShopImages(): string
  {
    return HTTP::getShopUrlDomain() . parent::getDirectoryTemplateImages(); // CLICSHOPPING::getConfig('dir_root', 'Shop1') . 'sources/images/
  }

  /**
   * Retrieves the directory path for language files.
   *
   * @return string The directory path where language files are stored.
   */

  public function getDirectoryPathLanguage(): string
  {
    return parent::getTemplateSource() . 'languages/';
  }

  /**
   * Retrieves the directory path for the shop module within the modules directory.
   *
   * @return string The full path to the shop module directory.
   */
  public function getDirectoryPathModuleShop(): string
  {
    $modules_catalog_directory = $this->getModulesDirectory() . DIRECTORY_SEPARATOR . $this->directoryAdminModules;

    return $modules_catalog_directory;
  }


  /**
   * Retrieves the path to the specified template header or footer for the admin area.
   *
   * @param string $file The name of the file to retrieve within the template directory.
   * @param string $template The name of the template to use. Defaults to 'Default'.
   * @return string The full path to the specified template file.
   */
  public function getTemplateHeaderFooterAdmin(string $file, string $template = 'Default'): string
  {

    if (isset($template)) {
      $template = CLICSHOPPING::BASE_DIR . 'Sites/' . CLICSHOPPING::getSite() . '/Templates/' . $template . DIRECTORY_SEPARATOR . $file;
    }

    return $template;
  }

  /**
   * Retrieves the directory path for templates.
   *
   * @return string The path to the template directory.
   */
  public function getTemplateDirectory(): string
  {
    return parent::getTemplateDirectory(); //sources/template
  }

  /*
  * get the Relative Path for dynamic template directory
  *
  * @param string $themaFilename , filename in this module
  *
  * //sources/template/SITE_THEMA
  * @return string
  */
  /**
   * Retrieves the directory path for dynamic templates.
   *
   * @return string The directory path for dynamic templates.
   */
  public function getDynamicTemplateDirectory(): string
  {
    return parent::getDynamicTemplateDirectory(); //sources/template/SITE_THEMA
  }

  /*
  * get the Relative Path for image directory
  *
  * @param string $themaFilename , filename in this module
  *
  * @return string
  */
  /**
   * Retrieves the directory path for admin image assets within the application's file structure.
   *
   * @return string The full path to the administrative image directory.
   */
  public function getImageDirectory(): string
  {
    return CLICSHOPPING::getConfig('http_server') . CLICSHOPPING::getConfig('http_path', 'Shop') . $this->directoryAdminImages . $this->directoryAdmin;
  }

  /*
  * get the Relative Path for image shop directory
  *
  * @param string $themaFilename , filename in this module
  *
  * @return string
  */
  /**
   * Retrieves the full directory path for shop images.
   *
   * @return string The complete URL or path for the shop images directory.
   */
  public function getImageDirectoryShop(): string
  {
    return CLICSHOPPING::getConfig('http_server') . CLICSHOPPING::getConfig('http_path', 'Shop') . $this->directoryAdminSources . $this->_directoryTemplateImages;
  }

  /**
   * Retrieves the directory path for admin boxes.
   *
   * @return string The directory path for admin boxes.
   */
  public function getBoxeDirectory(): string
  {
    $directory = $this->directoryAdminIncludes . $this->directoryAdminBoxes; //'includes/boxes/'

    return $directory;
  }

  /**
   * Retrieves the directory path for language-related files within the admin includes.
   *
   * @return string The path to the language directory.
   */
  public function getLanguageDirectory(): string
  {
    $directory = $this->directoryAdminIncludes . $this->directoryAdminLanguages; //'includes/languages/'

    return $directory;
  }

  /**
   * Retrieves the modules directory path.
   *
   * @return string The path to the modules directory.
   */
  public function getModulesDirectory(): string
  {
    $directory = parent::getPathRoot() . $this->directoryAdminIncludes;

    return $directory;
  }

  /**
   * Retrieves the directory path for shop template images.
   *
   * @return string The full directory path to the shop template images.
   */
  public function getDirectoryShopTemplateImages(): string
  {
    $directory = CLICSHOPPING::getConfig('http_server') . CLICSHOPPING::getConfig('http_path', 'Shop') . parent::getDirectoryTemplateImages(); //'CLICSHOPPING::getConfig('https_path', 'Shop')  . 'sources/images/'

    return $directory;
  }

  /**
   * Retrieves the directory path for shop sources.
   *
   * @return string The directory path for shop sources.
   */
  public function getDirectoryShopSources(): string
  {
    $directory = parent::getTemplateSource(); //' CLICSHOPPING::getConfig('dir_root') . 'sources/'

    return $directory;
  }

  /**
   * Retrieves an array of catalog file paths, optionally replacing elements with a provided file or formatting them
   * based on SEO-friendly URL settings.
   *
   * @param string|null $catalog_files Optional specific catalog file to replace the array contents. If null, returns the default array.
   * @return array Returns an array of catalog file paths, formatted based on SEO settings if applicable.
   */
  /**
   * Markers the box matching understands but that designate no page class: "Categories" stands for
   * any category listing (see Sites/Shop/Template.php, which substitutes it when a cPath is set)
   * and "cPath" for the parameter itself. They cannot be discovered, so they are declared here.
   */
  private const VIRTUAL_PAGES = ['Categories', 'cPath'];

  public static function getCatalogFiles(?string $catalog_files = null): array
  {
    if (!is_null($catalog_files)) {
      return [$catalog_files];
    }

    $file_array = array_merge(self::VIRTUAL_PAGES, static::discoverShopPages());

    sort($file_array);

    if (SEARCH_ENGINE_FRIENDLY_URLS_PRO == 'true' || SEARCH_ENGINE_FRIENDLY_URLS == 'true') {
      $file_array = str_replace(['&'], ['/'], $file_array);
    }

    return $file_array;
  }

  /**
   * Lists every Shop page an administrator can attach a module to, discovered from the same
   * sources the router itself resolves: the core pages, the Custom/ overrides and the routes the
   * installed Apps declare in their clicshopping.json.
   *
   * Two derived criteria, no exclusion list. A page qualifies when it owns a `templates/`
   * directory: that keeps the machine endpoints out (api, mcp, cronjob, webservice, payment
   * webhooks, RSS, sitemap), none of which has one. An action of a qualifying page qualifies in
   * turn only when it fixes a template — see actionRendersPage() — which keeps out the processing
   * actions that redirect, stream a file or print XML (Cart&Add, Checkout&Process, Account&LogOff).
   *
   * Replaces a list of 42 hardcoded entries of which 9 designated pages that no longer existed
   * (`Account&Newsletter` for `Newsletters`, `search&Q` in lowercase, `Compare&ProductsCompare`...):
   * selecting one produced a rule that could never match, and no App installed afterwards could
   * ever appear.
   *
   * @return array The page identifiers, in the "Page&Action" form.
   */
  protected static function discoverShopPages(): array
  {
    static $discovered = null;

    if ($discovered !== null) {
      return $discovered;
    }

    $pages = [];

    foreach (['Sites/Shop/Pages', 'Custom/Sites/Shop/Pages'] as $directory) {
      $pages = array_merge($pages, static::discoverPagesDirectory(CLICSHOPPING::BASE_DIR . $directory));
    }

    $pages = array_merge($pages, static::discoverAppRoutes());

    $discovered = array_values(array_unique($pages));

    return $discovered;
  }

  /**
   * Reads one Pages/ directory: a page contributes its own code plus one entry per action class.
   *
   * @param string $directory Absolute path of a Pages/ directory.
   * @return array The identifiers found, empty when the directory does not exist.
   */
  private static function discoverPagesDirectory(string $directory): array
  {
    $pages = [];

    if (!is_dir($directory)) {
      return $pages;
    }

    foreach (new DirectoryIterator($directory) as $entry) {
      if ($entry->isDot() || !$entry->isDir()) {
        continue;
      }

      $page = $entry->getFilename();
      $path = $entry->getPathname();

      if (!CLICSHOPPING::isValidClassName($page) || !is_file($path . '/' . $page . '.php') || !is_dir($path . '/templates')) {
        continue;
      }

      $pages[] = $page;

      if (!is_dir($path . '/Actions')) {
        continue;
      }

      foreach (new DirectoryIterator($path . '/Actions') as $action) {
        if ($action->isDot() || $action->isDir() || $action->getExtension() !== 'php') {
          continue;
        }

        if (static::actionRendersPage($action->getPathname())) {
          $pages[] = $page . '&' . $action->getBasename('.php');
        }
      }
    }

    return $pages;
  }

  /**
   * Reads the Shop routes declared by the installed Apps, keeping the ones whose destination page
   * renders a template.
   *
   * @return array The route identifiers.
   */
  private static function discoverAppRoutes(): array
  {
    $routes = [];
    $vendor_directory = CLICSHOPPING::BASE_DIR . 'Apps';

    if (!is_dir($vendor_directory)) {
      return $routes;
    }

    foreach (new DirectoryIterator($vendor_directory) as $vendor) {
      if ($vendor->isDot() || !$vendor->isDir()) {
        continue;
      }

      foreach (new DirectoryIterator($vendor->getPathname()) as $app) {
        if ($app->isDot() || !$app->isDir()) {
          continue;
        }

        $vendor_app = $vendor->getFilename() . '\\' . $app->getFilename();

        if (!Apps::exists($vendor_app) || (($json = Apps::getInfo($vendor_app)) === false)) {
          continue;
        }

        foreach ($json['routes']['Shop'] ?? [] as $stem => $destination) {
          if (!is_string($destination)) {
            continue;
          }

          $target = $app->getPathname() . '/' . str_replace('\\', '/', $destination);

          if (!is_dir($target . '/templates')) {
            continue;
          }

          // The last segment of the stem names an action of the destination page, when it names
          // one at all: "Sitemap" and "cronjob&runall" resolve to no action file.
          $segments = explode('&', $stem);
          $action = $target . '/Actions/' . basename(end($segments)) . '.php';

          if (is_file($action) && !static::actionRendersPage($action)) {
            continue;
          }

          $routes[] = $stem;
        }
      }
    }

    return $routes;
  }

  /**
   * Tells a rendering action from a processing one. An action renders when it explicitly fixes the
   * template file: a $this->page->setFile() call, or a $file property that
   * PagesActionsAbstract::__construct() forwards to setFile(). An action that sets neither still
   * renders the page's default main.php, but that case is already covered by the bare page entry.
   *
   * The property must default to a literal string: the constructor guards it with isset(), so the
   * `$file = null` spelling used by the ajax and export actions means the opposite.
   *
   * Read by tokenizing the source, never by loading or running the class: this is called while an
   * admin configuration form is being rendered. An unreadable file renders nothing.
   *
   * @param string $file Absolute path of an action class file.
   * @return bool True when the action fixes a template.
   */
  private static function actionRendersPage(string $file): bool
  {
    static $verdicts = [];

    if (isset($verdicts[$file])) {
      return $verdicts[$file];
    }

    $source = @file_get_contents($file);

    if ($source === false) {
      return $verdicts[$file] = false;
    }

    $tokens = token_get_all($source);
    $renders = false;

    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
      $token = $tokens[$i];

      if (!is_array($token)) {
        continue;
      }

      if ($token[0] === T_OBJECT_OPERATOR || $token[0] === T_NULLSAFE_OBJECT_OPERATOR) {
        $next = $tokens[$i + 1] ?? null;

        if (is_array($next) && $next[0] === T_STRING && $next[1] === 'setFile') {
          $renders = true;
          break;
        }

        continue;
      }

      if ($token[0] === T_VARIABLE && $token[1] === '$file' && static::isTemplateProperty($tokens, $i)) {
        $renders = true;
        break;
      }
    }

    return $verdicts[$file] = $renders;
  }

  /**
   * Checks that the $file token at $position is a property declaration whose default is a literal
   * string, and not a local variable nor a null default.
   *
   * @param array $tokens The tokenized action source.
   * @param int $position Index of the $file token.
   * @return bool True when the declaration fixes a template.
   */
  private static function isTemplateProperty(array $tokens, int $position): bool
  {
    $modifiers = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR];
    $type_tokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_STRING, T_ARRAY, T_CALLABLE, T_STATIC, T_READONLY, T_NS_SEPARATOR];
    $declared = false;

    for ($i = $position - 1; $i >= 0; $i--) {
      $token = $tokens[$i];

      if ($token === '?' || $token === '|') {
        continue;
      }

      if (!is_array($token)) {
        return false;
      }

      if (in_array($token[0], $modifiers, true)) {
        $declared = true;
        break;
      }

      if (!in_array($token[0], $type_tokens, true)) {
        return false;
      }
    }

    if ($declared === false) {
      return false;
    }

    for ($i = $position + 1, $count = count($tokens); $i < $count; $i++) {
      $token = $tokens[$i];

      if (is_array($token) && $token[0] === T_WHITESPACE) {
        continue;
      }

      if ($token !== '=') {
        return false;
      }

      for ($i++; $i < $count; $i++) {
        $value = $tokens[$i];

        if (is_array($value) && $value[0] === T_WHITESPACE) {
          continue;
        }

        return is_array($value) && $value[0] === T_CONSTANT_ENCAPSED_STRING;
      }
    }

    return false;
  }

  /**
   * Retrieves a list of catalog files not included, optionally starting with a specified bootstrap file.
   *
   * @param string|null $boostrap_file The name of the bootstrap file to include at the start of the list.
   * If null, the default bootstrap file from the configuration will be used.
   * @return array An array containing the list of catalog files, starting with the specified or default bootstrap file.
   */

  public static function getListCatalogFilesNotIncluded(?string $boostrap_file = null): array
  {
    if (is_null($boostrap_file)) $boostrap_file = CLICSHOPPING::getConfig('bootstrap_file');

    $file = static::getCatalogFiles();

    $result = [];

    $result[] = $boostrap_file;

    foreach ($file as $value) {
      $result[] = $value;
    }

    return $result;
  }

  /**
   * Generates a dropdown menu for selecting multiple templates from a specified module.
   *
   * @param string $filename The name of the configuration key to retrieve the current template value.
   * @param string $module The name of the module to fetch templates from.
   * @return string The HTML string for the dropdown menu with available templates.
   */
  public function getMultiTemplatePullDown(string $filename, string $module): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $key = $this->default;

    $template_directory = $this->getDirectoryPathModuleShopTemplateHtml($module);

    if ($contents = @scandir($template_directory)) {
      $fileTypes = ['php']; // Create an array of file types
      $found = []; // Traverse the folder, and add filename to $found array if type matches

      foreach ($contents as $item) {
        $fileInfo = pathinfo($item);
        if (array_key_exists('extension', $fileInfo) && in_array($fileInfo['extension'], $fileTypes)) {
          $found[] = $item;
        }
      }

      if ($found) { // Check the $found array is not empty
        natcasesort($found); // Sort in natural, case-insensitive order, and populate menu
        $filename_array = [];

        foreach ($found as $filename) {
          $filename_array[] = [
            'id' => $filename,
            'text' => $filename
          ];
        }
      }
    }

    $QfileName = $CLICSHOPPING_Db->prepare('select configuration_value
                                               from :table_configuration
                                               where configuration_key = :configuration_key
                                             ');
    $QfileName->bindValue(':configuration_key', $key);

    $QfileName->execute();

    $filename_value = $QfileName->value('configuration_value');

    return HTML::selectMenu($this->key, $filename_array, $filename_value);
  }

  /**
   * Retrieves specific files based on the provided folder, filename, and extension.
   *
   * @param string $source_folder The path to the folder where the search is performed.
   * @param string $filename The name of the file to search for.
   * @param string $ext The extension of the files to search for. Defaults to 'php'.
   * @return mixed The result from the parent method, typically a list or collection of matching files.
   */
  public function getSpecificFiles(string $source_folder, string $filename, string $ext = 'php')
  {
    $result = parent::getSpecificFiles($source_folder, $filename, $ext);

    return $result;
  }

  /**
   * Processes recursive module hooks for a given template.
   *
   * @param string $source_folder The source folder containing templates.
   * @param string $file_get_output The output file data to retrieve.
   * @param string $files_get_call The method or function to call for retrieving files.
   * @param string $hook_call The hook method or function to invoke in the process.
   *
   * @return mixed The result of the parent method processing the recursive module hooks.
   */
  public function useRecursiveModulesHooksForTemplate(string $source_folder, string $file_get_output, string $files_get_call, string $hook_call): mixed
  {
    $result = parent::useRecursiveModulesHooksForTemplate($source_folder, $file_get_output, $files_get_call, $hook_call);

    return $result;
  }

  /**
   * Retrieves all available templates based on the provided parameters and generates
   * a select menu with the template options.
   *
   * @param string $key The key used for configuration or naming the select menu. Defaults to an empty string.
   * @param string $default The default option text to be displayed in the select menu. Defaults to an empty string.
   * @param bool $config Determines whether to build the configuration-based or non-configuration-based select menu. Defaults to true.
   * @return string The HTML string of a select menu with the available template options.
   */
  public function getAllTemplate(string $key = '', string $default = '', $config = true): string
  {
    if ($config === true) {
      $name = (!empty($key) ? 'configuration[' . $key . ']' : 'configuration_value');
    } else {
      $name = $key;
    }

    $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . $this->getTemplateDirectory() . '/';

    $weeds = array('.', '..', '_notes', 'index.php', 'ExNewTemplate', '.htaccess', 'README');

    $directories = array_diff(scandir($template_directory), $weeds);

    if ($config === true) {
      $filename_array = [];
    } else {
      $filename_array[] = [
        'id' => null,
        'text' => $default
      ];
    }

    foreach ($directories as $value) {
      if (is_dir($template_directory . $value)) {
        $filename_array[] = [
          'id' => $value,
          'text' => $value
        ];
      }
    }

    return HTML::selectMenu($name, $filename_array, $value);
  }

  /**
   * Updates the template with the available directory options and returns an HTML select menu.
   *
   * @param ?string $name The name attribute for the select menu.
   * @param string $default The default option text to display in the select menu.
   * @param string|null $item_value The selected value in the select menu, or null if none is selected.
   * @return string The generated HTML select menu.
   */
  public function updateTemplate(?string $name, string $default, string|null $item_value): string
  {
    $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . $this->getTemplateDirectory() . '/';

    $weeds = array('.', '..', '_notes', 'index.php', 'ExNewTemplate', '.htaccess', 'README');

    $directories = array_diff(scandir($template_directory), $weeds);

    $filename_array[] = [
      'id' => null,
      'text' => $default
    ];

    if (empty($item_value)) {
      $item_value = null;
    }

    foreach ($directories as $value) {
      if (is_dir($template_directory . $value)) {
        $filename_array[] = [
          'id' => $value,
          'text' => $value
        ];
      }
    }

    return HTML::selectMenu($name, $filename_array, $item_value);
  }
}