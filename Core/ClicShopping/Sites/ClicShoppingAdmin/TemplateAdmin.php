<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
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
  public static function getCatalogFiles(?string $catalog_files = null): array
  {
    $file_array = [
      'Account&AddressBook',
      'Account&Create',
      'Account&CreatePro',
      'Account&CreateProSuccess',
      'Account&Delete',
      'Account&Edit',
      'Account&History',
      'Account&HistoryInfo',
      'Account&LogIn',
      'Account&MyFeedBack',
      'Account&Newsletter',
      'Account&NewsletterNoAccount',
      'Account&NewsletterNoAccountSuccess',
      'Account&Notification',
      'Account&Password',
      'Blog&Categories',
      'Blog&Content',
      'Categories',
      'cPath',
      'Cart',
      'Checkout&Shipping',
      'Checkout&ShippingAddress',
      'Checkout&Billing',
      'Checkout&PaymentAddress',
      'Checkout&Confirmation',
      'Checkout&Success',
      'Compare&ProductsCompare',
      'Info&Contact',
      'Info&Cookies',
      'Info&Content',
      'Info&SiteMap',
      'Info&SSLcheck',
      'FlashSelling&ProductsFlashSelling',
      'Products&Description',
      'Products&Favorites',
      'Products&Featured',
      'Products&ProductsNew',
      'Products&Specials',
      'Products&TellAFriend',
      'Products&Recommendations',
      'search&AdvancedSearch',
      'search&Q',
    ];

    if (SEARCH_ENGINE_FRIENDLY_URLS_PRO == 'true' || SEARCH_ENGINE_FRIENDLY_URLS == 'true') {
      $file_array = str_replace(['&'], ['/'], $file_array);
    }

    if (!is_null($catalog_files)) {
      $file_array = [$catalog_files];
    }

    return $file_array;
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