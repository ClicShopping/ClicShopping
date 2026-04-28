<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\OM;

use ArrayIterator;
use CachingIterator;
use function count;
use function in_array;
use function is_array;
use function is_null;
use function is_string;
use function strlen;

/**
 * Namespace for organizing the HTML class logic.
 */

class HTML
{
  /**
   * Processes a given string by trimming it and replacing characters based on a translation array.
   *
   * @param string|null $string The input string to be processed. If null or empty, an empty string is returned.
   * @param array|null $translate An associative array of characters to translate. Defaults to translating double quotes (") to single quotes (').
   * @return string The processed string after applying trim and character translations.
   */

  public static function output(?string $string, ?array $translate = null): string
  {
    if (is_null($string) || empty($string)) {
      return '';
    }

    if (!isset($translate)) {
      $translate = [
        '"' => '\''
      ];
    }

    return strtr(trim($string), $translate);
  }

  /**
   * Returns a string with special characters converted to HTML entities to protect against XSS attacks.
   *
   * @param string|null $string The input string to be protected. If null or empty, an empty string is returned.
   * @return string The protected string with HTML entities encoded.
   */
  public static function outputProtected(?string $string): string
  {
    if (is_null($string) || empty($string)) {
      return '';
    }

    return htmlspecialchars(trim($string), ENT_QUOTES | ENT_HTML5);
  }

  /**
   * Sanitizes the given string by replacing unwanted characters and patterns.
   *
   * @param string|null $string The input string to sanitize. Can be null or empty.
   * @return string The sanitized string. If the input is null or empty, an empty string is returned.
   */
  public static function sanitize($string)
  {
    if (is_null($string) || empty($string)) {
      return '';
    }
    $patterns = [
      '/ +/',
      '/[<>]/',
      '/&lt;/',
      '/&gt;/',
      '/%3c/',
      '/%2f/'
    ];

    $replace = [
      ' ',
      '_',
      '_',
      '_',
      '_',
      '_'
    ];

    return preg_replace($patterns, $replace, $string) ?? '';
  }


  /**
   * Generates an HTML anchor tag based on the provided URL, element content, and optional parameters.
   *
   * @param string $url The URL for the anchor tag's href attribute.
   * @param string|null $element The content to be displayed between the opening and closing anchor tags.
   * @param string|null $parameters Optional. Additional attributes to include in the anchor tag.
   * @return string The complete anchor tag as an HTML string.
   */

  public static function link(string $url, ?string $element, mixed $parameters = null)
  {
    $safe_url = static::sanitizeUrl($url);
    $safe_element = $element;
    if (!empty($element)) {
      $trimmed = trim($element);
      
      // FIRST: Check for dangerous content that should ALWAYS be escaped
      $has_dangerous_content = (
        stripos($trimmed, 'javascript:') !== false ||
        stripos($trimmed, 'data:text/html') !== false ||
        stripos($trimmed, 'vbscript:') !== false ||
        preg_match('/\son\w+\s*=/i', $trimmed) // Event handlers (onclick, onerror, etc.)
      );
      
      if ($has_dangerous_content) {
        // Contains dangerous content - ALWAYS escape
        $safe_element = static::outputProtected($element);
      } else {
        // No dangerous content - check if it's safe HTML from our methods
        $is_safe_html = (
          // Images with data-src or src and alt attributes (our pattern)
          preg_match('/^<img\s+(data-)?src="[^"]*"\s+alt="[^"]*"/', $trimmed) ||
          // Spans with any standard attribute (class, itemprop, id, data-*)
          preg_match('/^<span\s+(class|itemprop|id|data-\w+)="[^"]*">/', $trimmed) ||
          // Icons with class attribute
          preg_match('/^<i\s+class="[^"]*"/', $trimmed) ||
          // Buttons with type attribute
          preg_match('/^<button\s+type="[^"]*"/', $trimmed) ||
          // Heading tags (h1-h6) with content
          preg_match('/^<h[1-6]>/', $trimmed) ||
          // Divs with class attribute
          preg_match('/^<div\s+class="[^"]*">/', $trimmed) ||
          // Paragraphs with class attribute
          preg_match('/^<p\s+class="[^"]*">/', $trimmed) ||
          // Any other HTML tag (as long as no dangerous content)
          preg_match('/^<[^>]+>/', $trimmed)
        );
        
        if (!$is_safe_html) {
          // Plain text or unrecognized pattern - escape it
          $safe_element = static::outputProtected($element);
        }
        // else: Keep the safe HTML as-is
      }
    }
    
    // Security: Parameters should be pre-validated by caller, but we sanitize the output
    $safe_parameters = !empty($parameters) ? ' ' . static::sanitizeHtmlAttributes($parameters) : '';
    
    return '<a href="' . $safe_url . '"' . $safe_parameters . '>' . $safe_element . '</a>';
  }

  /**
   * Converts a URL link to a format suitable for AJAX applications.
   *
   * @param string $link The URL link to be converted.
   * @return string The converted link with specific characters replaced for AJAX compatibility.
   */
  public static function AjaxAppLink(string $link): string
  {
    $link = str_replace('&amp;', '&', $link);
    $link = str_replace('%5C', '\\\\', $link);

    return $link;
  }

  /*
  * read the first bit of a file
  *
  * @param string $url url of image
  * @return string
  */
  /**
   * Checks if a file exists at the given URL.
   *
   * @param string $url The URL of the file to check.
   *
   * @return bool Returns true if the file exists at the given URL, otherwise false.
   */
  public static function getUrlFileExists(string $url): bool
  {
    if (@file_get_contents($url, false, NULL, 0, 1)) {
      return true;
    }

    return false;
  }

  /**
   * Generates an HTML image element with various customizable attributes.
   *
   * @param string $src The source URL for the image. Defaults to an empty string.
   * @param string|null $alt The alternate text for the image. Defaults to null.
   * @param string|null $width The width of the image. Defaults to null.
   * @param string|null $height The height of the image. Defaults to null.
   * @param string|null $parameters Additional HTML attributes for the image element. Defaults to an empty string.
   * @param bool $responsive Determines whether the image should have responsive styling applied. Defaults to true.
   * @param string $bootstrap_css Any additional Bootstrap CSS classes to apply. Defaults to an empty string.
   *
   * @return string The generated HTML string for the image element.
   */
  public static function image(string $src = '', ?string $alt = null, ?string $width = null, ?string $height = null, ?string $parameters = '', bool $responsive = true, string $bootstrap_css = ''): string
  {
    if ((empty($src) || ($src == CLICSHOPPING::linkImage(''))) && (IMAGE_REQUIRED == 'false')) {
      return false;
    }

    if (CLICSHOPPING::getSite() == 'Shop') {
      $CLICSHOPPING_Template = Registry::get('Template');

      if ((empty($src) || is_null($src) || static::getUrlFileExists($src) === false) && IMAGE_REQUIRED == 'true') {
        $image = CLICSHOPPING::getConfig('http_server') . CLICSHOPPING::getConfig('http_path', 'Shop') . $CLICSHOPPING_Template->getDirectoryTemplateImages() . 'icons/nophoto.png';

        if (!is_file(CLICSHOPPING::getConfig('dir_root', 'Shop') . $image)) {
          $src = 'images/nophoto.png';
        } else {
          $src = $image;
        }
      }
    } elseif (CLICSHOPPING::getSite() == 'ClicShoppingAdmin') {
      if ((empty($src) && static::getUrlFileExists($src) === false) && IMAGE_REQUIRED == 'true') {
        $src = CLICSHOPPING::getConfig('http_server') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'images/nophoto.png';
      }
    }

    if (CLICSHOPPING::getSite() == 'Shop') {
      $image = '<img data-src="' . static::output(CLICSHOPPING::getConfig('http_server') . CLICSHOPPING::getConfig('http_path', 'Shop') . $src) . '" alt="' . static::output($alt) . '"';
    } else {
      $image = '<img src="' . static::output($src) . '" alt="' . static::output($alt) . '"';
    }

    if (isset($alt) && (strlen($alt) > 0)) {
      $image .= ' title="' . static::output($alt) . '"';
    }

    if (isset($width) && (strlen($width) > 0)) {
      $image .= ' width="' . static::output($width) . '"';
    }

    if (isset($height) && (strlen($height) > 0)) {
      $image .= ' height="' . static::output($height) . '"';
    }

    $class = [];

    $class[] = ' lozad media-object';

    if ($responsive === true) {
      $class[] = ' img-fluid';
    }

    if (!empty($bootstrap_css)) {
      $class[] = $bootstrap_css;
    }

    if (!empty($class)) {
      $image .= ' class="' . implode(' ', $class) . '"';
    }

    if (!empty($parameters)) {
      $image .= ' ' . $parameters;
    }

    $image .= ' />';

    return $image;
  }

  /**
   * Generates an HTML form element with optional parameters and flags.
   *
   * @param string $name The name attribute of the form.
   * @param string|null $action The action URL the form submits to. Defaults to null.
   * @param string|null $method The HTTP method used for form submission (e.g., "post" or "get"). Defaults to "post".
   * @param string|null $parameters Additional attributes to include in the form element. Defaults to an empty string.
   * @param array $flags An associative array of flags for form behavior:
   *                     - 'tokenize' (bool): Whether to include a form token for security. Defaults to false if not set.
   *                     - 'session_id' (bool): Whether to include session ID in a hidden field. Defaults to false if not set.
   *                     - 'action' (mixed): The value for a hidden "action" field, if specified.
   * @return string The generated HTML form element as a string.
   */

  public static function form(string $name, ?string $action = null, ?string $method = 'post', ?string $parameters = '', array $flags = []): string
  {
    if (!isset($flags['tokenize']) || !is_bool($flags['tokenize'])) {
      $flags['tokenize'] = false;
    }

    if (!isset($flags['session_id']) || !is_bool($flags['session_id'])) {
      $flags['session_id'] = false;
    }

    // Security: Validate method against whitelist
    $allowed_methods = ['post', 'get'];
    $safe_method = in_array(strtolower($method ?? 'post'), $allowed_methods) ? strtolower($method) : 'post';

    // Security: Sanitize form action URL
    $safe_action = static::sanitizeUrl($action);

    $form = '<form name="' . static::outputProtected($name) . '" action="' . $safe_action . '" method="' . $safe_method . '"';

    if (!empty($parameters)) {
      // Security: Sanitize additional parameters
      $form .= ' ' . static::sanitizeHtmlAttributes($parameters);
    }

    $form .= '>';

    if (isset($flags['action'])) {
      $form .= static::hiddenField('action', $flags['action']);
    }

    if (($flags['session_id'] === true) && Registry::get('Session')->hasStarted() && (strlen(session_id()) > 0) && !Registry::get('Session')->isForceCookies()) {
      $form .= static::hiddenField(session_name(), session_id());
    }

    if (($flags['tokenize'] === true) && isset($_SESSION['sessiontoken'])) {
      $form .= static::hiddenField('formid', $_SESSION['sessiontoken']);
    }

    return $form;
  }

  /**
   * Generates an HTML input field.
   *
   * @param string $name The name attribute of the input field.
   * @param string $value The value attribute of the input field. Defaults to an empty string.
   * @param string $parameters Additional parameters to include in the input field element.
   * @param string $type The type attribute of the input field (e.g., 'text', 'password'). Defaults to 'text'.
   * @param bool $reinsert_value Whether to reinsert the value from the GET or POST request if available. Defaults to true.
   * @param string $class The CSS class to apply to the input field. Defaults to 'form-control'.
   *
   * @return string The HTML string of the input field element.
   */

  public static function inputField($name, $value = '', $parameters = '', $type = 'text', $reinsert_value = true, $class = 'form-control')
  {
    $field = '<input type="' . static::output($type) . '" name="' . static::output($name) . '"';

    if (($reinsert_value === true) && ((isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name])))) {
      if (isset($_GET[$name]) && is_string($_GET[$name])) {
        $value = $_GET[$name];
      } elseif (isset($_POST[$name]) && is_string($_POST[$name])) {
        $value = $_POST[$name];
      }
    }

    if (!is_null($value)) {
      if (strlen($value) > 0) {
        $field .= ' value="' . static::output($value) . '"';
      }
    }

    if (!empty($parameters)) {
      $field .= ' ' . $parameters . ' class="' . $class . '"';
    } else {
      if (!empty($class)) {
        $field .= ' class="' . $class . '"';
      }
    }

    $field .= ' />';

    return $field;
  }

  /**
   * Generates a password input field.
   *
   * @param string $name The name attribute of the password field.
   * @param string|null $value The default value of the password field. Defaults to null.
   * @param string $parameters Additional parameters for the password field, such as maxlength. Defaults to 'maxlength="40"'.
   *
   * @return string The HTML string for the password input field.
   */

  public static function passwordField(string $name, ?string $value = null, string $parameters = 'maxlength="40"'): string
  {
    return static::inputField($name, $value, $parameters, 'password', false);
  }

  /**
   * Generates an input selection field with specified attributes and options.
   *
   * @param string $name The name attribute of the input field.
   * @param string $type The input type (e.g., "checkbox", "radio").
   * @param array|string|null $values The values for the input field; can be an array of values or a single value.
   * @param string|array|bool|null $default The default value(s) to preselect; can be a string, an array of values, or a boolean.
   * @param string|null $parameters Additional parameters to include in the input element.
   * @param string $separator A string used to separate multiple input fields; defaults to a non-breaking space.
   * @return string The generated HTML string for the input selection field.
   */

  protected static function selectionField(string $name, string $type, mixed $values = null, mixed $default = null,  string|null $parameters = null, string $separator = '&nbsp;&nbsp;')
  {
    if (!is_array($values)) {
      $values = array($values);
    }

    if (str_contains($name, '[')) {
      $name_string = substr($name, 0, strpos($name, '['));

      if (isset($_GET[$name_string])) {
        $default = $_GET[$name_string];
      } elseif (isset($_POST[$name_string])) {
        $default = $_POST[$name_string];
      }
    } else {
      if (isset($_GET[$name])) {
        $default = $_GET[$name];
      } elseif (isset($_POST[$name])) {
        $default = $_POST[$name];
      }
    }

    $field = '';

    $counter = 0;

    foreach ($values as $key => $value) {
      $counter++;

      if (is_array($value)) {
        $selection_value = $value['id'];
        $selection_text = $value['text'];
      } else {
        $selection_value = $value;
        $selection_text = '';
      }

      if (empty($selection_value)) {
        $selection_value = 'on';
      }

      $field .= '<input type="' . static::output($type) . '" name="' . static::outputProtected($name) . '"';


      if (!str_contains($parameters ?? '', 'id=')) {
        $field .= ' id="' . static::output($name) . (count($values) > 1 ? '_' . $counter : '') . '"';
      } elseif (count($values) > 1) {
        $offset = strpos($parameters, 'id="');
        $field .= ' id="' . static::output(substr($parameters, $offset + 4, strpos($parameters, '"', $offset + 4) - ($offset + 4))) . '_' . $counter . '"';
      }

      $field .= ' value="' . static::output($selection_value) . '"';

      if (isset($default) && (($default === true) || (!is_array($default) && ((string)$default == (string)$selection_value)) || (is_array($default) && in_array($selection_value, $default)))) {
        $field .= ' checked="checked"';
      }

      if (!empty($parameters)) {
        $field .= ' ' . $parameters;
      }

      $field .= ' />';

      if (!empty($selection_text)) {
        $field .= '<label for="' . static::output($name) . (count($values) > 1 ? '_' . $counter : '') . '" class="fieldLabel">' . $selection_text . '</label>';
      }

      $field .= $separator;
    }

    if (!empty($field)) {
      $field = substr($field, 0, strlen($field) - strlen($separator));
    }

    return $field;
  }

  /**
   * Generates a checkbox field with specified attributes.
   *
   * @param string $name The name attribute of the checkbox field.
   * @param mixed $values The value(s) assigned to the checkbox option(s). Can be a string or an array.
   * @param mixed $default The default selected value(s). Can be a string or an array.
   * @param mixed $parameters Additional parameters or attributes for the checkbox field.
   * @param string $separator The separator between multiple checkbox options. Default is '&nbsp;&nbsp;'.
   *
   * @return string The rendered HTML string of the checkbox field.
   */

  public static function checkboxField(string $name, mixed $values = null, mixed $default = null, mixed $parameters = null, string $separator = '&nbsp;&nbsp;')
  {
    return static::selectionField($name, 'checkbox', $values, $default, $parameters, $separator);
  }

  /**
   * Generates a group of radio input fields with associated labels, based on the provided values.
   *
   * @param string $name The name attribute for the radio input fields.
   * @param array $values An array of values and corresponding labels for the radio options.
   * @param string|null $default The default selected value. If null, no option is preselected.
   * @param string|null $parameters Additional parameters or attributes for the radio input fields.
   * @param string $separator The string used to separate each radio field. Default is '&nbsp;&nbsp;'.
   * @return string The HTML string for the radio input fields group.
   */

  public static function radioField(string $name, mixed $values, mixed $default = null, mixed $parameters = null, string $separator = '&nbsp;&nbsp;')
  {
    return static::selectionField($name, 'radio', $values, $default, $parameters, $separator);
  }

  /**
   * Generates a text area HTML field with specified attributes and optional override functionality.
   *
   * @param string $name The name attribute of the text area.
   * @param string|null $value The default value of the text area. If override is enabled, can be replaced by GET or POST data.
   * @param int|string|null $width The number of columns (width) of the text area. Defaults to 60 if null.
   * @param int|string|null $height The number of rows (height) of the text area. Defaults to 5 if null.
   * @param string|null $parameters Additional attributes to include in the text area tag.
   * @param bool $override Whether to override the default value with GET or POST data if available. Defaults to true.
   * @return string The generated HTML for the text area element.
   */

  public static function textAreaField(string $name, mixed $value = null, int|string|null $width = 60, int|string|null $height = 5, mixed $parameters = null, bool $override = true)
  {
    if (!is_bool($override)) {
      $override = true;
    }

    if ($override === true) {
      if (isset($_GET[$name])) {
        $value = $_GET[$name];
      } elseif (isset($_POST[$name])) {
        $value = $_POST[$name];
      }
    }

    // Handle null or non-numeric width values
    if ($width === null || !is_numeric($width)) {
      $width = 60;
    }

    // Handle null or non-numeric height values  
    if ($height === null || !is_numeric($height)) {
      $height = 5;
    }

    $field = '<textarea name="' . static::output($name) . '" cols="' . static::output($width) . '" rows="' . static::output($height) . '"';

    if (!str_contains($parameters ?? '', 'id=')) {
      $field .= ' class="form-control" id="' . static::output($name) . '"';
    }

    if (!is_null($parameters)) {
      $field .= ' ' . $parameters;
    }

    $field .= '>' . static::outputProtected($value) . '</textarea>';

    return $field;
  }


  /**
   * Generates an HTML select menu.
   *
   * @param string $name The name attribute for the <select> element.
   * @param array $values An array of options for the select menu. Each item in the array
   *                      should be an associative array with keys 'id', 'text', and optionally 'group' and 'params'.
   * @param mixed|null $default The default selected value. If null, default is selected based on GET or POST data.
   * @param string $parameters Additional HTML attributes for the <select> element.
   * @param bool $required Whether the select menu is required. Defaults to false.
   * @param string $class CSS classes to apply to the <select> element. Defaults to 'form-select form-control'.
   * @return string The generated HTML string for the select menu.
   */

  public static function selectMenu($name, array $values, $default = null, $parameters = '', $required = false, $class = 'form-select form-control')
  {
    $group = false;

    $field = '<select name="' . static::output($name) . '"';

    if ($required === true) {
      $field .= ' required aria-required="true"';
    }

    if (!str_contains($parameters, 'id=')) {
      $field .= ' id="' . static::output($name) . '"';
    }

    if (!empty($parameters)) {
      $field .= ' ' . $parameters;
    }

    if (!empty($class)) {
      $field .= ' class="' . $class . '"';
    }

    $field .= '>';

    if ($required === true) {
      $field .= '<option value="">' . CLICSHOPPING::getDef('text_select') . '</option>';
    }

    if (empty($default) && ((isset($_GET[$name]) && is_string($_GET[$name]) && !is_null($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name] && !is_null($_POST[$name]))))) {
      if (isset($_GET[$name]) && is_string($_GET[$name])) {
        $default = static::output($_GET[$name]);
      } elseif (isset($_POST[$name]) && is_string($_POST[$name])) {
        $default = static::output($_GET[$name]);
      }
    }

    $ci = new CachingIterator(new ArrayIterator($values), CachingIterator::TOSTRING_USE_CURRENT); // used for hasNext() below

    foreach ($ci as $v) {
      if (isset($v['group'])) {
        if ($group != $v['group']) {
          $group = $v['group'];

          $field .= '<optgroup label="' . static::output($v['group']) . '">';
        }
      }

      $field .= '<option value="' . static::output($v['id']) . '"';

      if (isset($default) && ($v['id'] == $default)) {
        $field .= ' selected="selected"';
      }

      if (isset($v['params'])) {
        $field .= ' ' . $v['params'];
      }

      $field .= '>' . static::output($v['text']) . '</option>';

      if (($group !== false) && (($group != $v['group']) || ($ci->hasNext() === false))) {
        $group = false;

        $field .= '</optgroup>';
      }
    }

    $field .= '</select>';

    return $field;
  }


  /**
   * Generates a dropdown menu listing all available countries.
   *
   * @param string $name The name attribute for the dropdown menu.
   * @param mixed|null $selected The selected value in the dropdown menu (default is null).
   * @param string|null $parameters Additional parameters for the dropdown menu (default is null).
   * @return string Returns the HTML markup for the dropdown menu.
   */
  public static function selectMenuCountryList($name, $selected = null, $parameters = null)
  {
    $CLICSHOPPING_Address = Registry::get('Address');

    $countries_array = [array(
      'id' => '',
      'text' => CLICSHOPPING::getDef('text_select'))
    ];

    $countries = $CLICSHOPPING_Address->getCountries();

    for ($i = 0, $n = count($countries); $i < $n; $i++) {
      $countries_array[] = [
        'id' => $countries[$i]['countries_id'],
        'text' => $countries[$i]['countries_name'],
        'iso' => $countries[$i]['countries_iso_code_2']
      ];
    }

    return static::selectMenu($name, $countries_array, $selected, $parameters);
  }

  /**
   * Hides the session ID by creating a hidden field containing the session name if the session has started and the SID is not empty or null.
   *
   * @param string $session_started Flag indicating if the session has started.
   * @param string $SID The session ID to check.
   * @return mixed Returns a hidden field with the session name if conditions are met, otherwise returns false.
   */
  public static function hideSessionId(string $session_started, string $SID)
  {
    if (($session_started === true) && (!empty($SID) || !is_null($SID))) {
      return static::hiddenField(session_name(), session_name());
    } else {
      return false;
    }
  }


  /**
   * Generates an HTML file input field.
   *
   * @param string $name The name attribute of the input field.
   * @param string $parameters Additional parameters or attributes to include in the input field.
   * @return string The HTML string for a file input field.
   */

  public static function fileField($name, $parameters = '')
  {
    return static::inputField($name, '', $parameters, 'file', false);
  }

  /**
   * Generates a timezone select menu based on available timezones.
   *
   * @param string $name The name attribute for the select menu.
   * @param string|null $default The default selected timezone. If null, the server's default timezone will be used.
   * @return string The HTML of the timezone select menu.
   */

  public static function timeZoneSelectMenu($name, $default = null)
  {
    if (!isset($default)) {
      $default = date_default_timezone_get();
    }

    $result = array();

    foreach (DateTime::getTimeZones() as $zone => $zones_array) {
      foreach ($zones_array as $key => $value) {
        $result[] = [
          'id' => $key,
          'text' => $value,
          'group' => $zone
        ];
      }
    }

    return HTML::selectMenu($name, $result, $default);
  }

  /**
   * Breaks a given string into a new format by inserting a specified character
   * after a defined length of non-space characters.
   *
   * @param string $string The input string to be processed.
   * @param int $len The maximum number of consecutive non-space characters before inserting the break character.
   * @param string $break_char The character to insert when the maximum length is exceeded. Default is '-'.
   * @return string The processed string with the break characters inserted.
   */

  public static function breakString($string, $len, $break_char = '-')
  {
    $l = 0;
    $output = '';

    for ($i = 0, $n = strlen($string); $i < $n; $i++) {
      $char = substr($string, $i, 1);
      if ($char != ' ') {
        $l++;
      } else {
        $l = 0;
      }
      if ($l > $len) {
        $l = 1;
        $output .= $break_char;
      }
      $output .= $char;
    }

    return $output;
  }

  /**
   * Generates an image button element.
   *
   * @param string $image The filename of the image to be used as the button.
   * @param string $title The title or alt text for the image (optional).
   * @param string $parameters Additional parameters or attributes for the image tag (optional).
   * @param bool $responsive Indicates whether the image should be rendered as responsive (optional, default false).
   * @return string The generated image button HTML string.
   */
  public static function imageButton($image, $title = '', $parameters = '', $responsive = false)
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Language = Registry::get('Language');

    $image_responsive = '';

    if (!empty($responsive) && ($responsive === false)) {
      $image_responsive = ' class="img-fluid"';
    }

    return static::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . 'template/' . SITE_THEMA . DIRECTORY_SEPARATOR . $CLICSHOPPING_Language->get('directory') . DIRECTORY_SEPARATOR . $image, $title, '', '', $parameters, $responsive, $image_responsive);
  }

  /**
   * Generates an HTML select field with specified options and attributes.
   *
   * @param string $name The name attribute for the select field.
   * @param array $values An array of values for the select field. Each value should include 'id', 'text', and optionally 'group' and 'params'.
   * @param string|null $default The default selected value or null if none should be selected.
   * @param string $parameters Additional parameters for the select field (e.g., onclick, style).
   * @param bool $required Whether the select field is required. If true, adds the "required" attribute.
   * @param string $class The CSS class(es) to apply to the select field. Defaults to 'form-control form-select'.
   * @return string The generated HTML select field.
   */
  public static function selectField($name, $values, $default = null, $parameters = '', $required = false, $class = 'form-control form-select')
  {
    $group = false;

    $field = '<select name="' . static::output($name) . '"';

    if ($required === true) {
      $field .= ' required aria-required="true"';
    }

    if (!empty($parameters)) {
      $field .= ' ' . $parameters;
    }

    if (!empty($class)) {
      $field .= ' class="' . $class . '"';
    }

    $field .= '>';

    if ($required === true) {
      $field .= '<option value="">' . CLICSHOPPING::getDef('entry_text_select') . '</option>';
    }

    if (empty($default) && ((isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name])))) {
      if (isset($_GET[$name]) && is_string($_GET[$name])) {
        $default = $_GET[$name];
      } elseif (isset($_POST[$name]) && is_string($_POST[$name])) {
        $default = $_POST[$name];
      }
    }

    $ci = new CachingIterator(new ArrayIterator($values), CachingIterator::TOSTRING_USE_CURRENT); // used for hasNext() below

    foreach ($ci as $v) {
      if (isset($v['group'])) {
        if ($group !== $v['group']) {
          $group = $v['group'];
          $field .= '<optgroup label="' . static::output($v['group']) . '">';
        }
      }

      $field .= '<option value="' . static::output($v['id']) . '"';

      if (isset($default) && ($v['id'] == $default)) {
        $field .= ' selected="selected"';
      }

      if (isset($v['params'])) {
        $field .= ' ' . $v['params'];
      }

      $field .= '>' . static::output($v['text'], [
          '"' => '&quot;',
          '\'' => '&#039;',
          '<' => '&lt;',
          '>' => '&gt;'
        ]) . '</option>';

      if (($group !== false) && (($group != $v['group']) || ($ci->hasNext() === false))) {
        $group = false;

        $field .= '</optgroup>';
      }
    }

    $field .= '</select>';

    return $field;
  }

  /**
   * Generates an HTML hidden input field.
   *
   * @param string $name The name attribute of the hidden input field.
   * @param string $value Optional. The value attribute of the hidden input field. Default is an empty string.
   * @param string $parameters Optional. Additional parameters to include in the input field element. Default is an empty string.
   * @return string The generated HTML hidden input field.
   */

  public static function hiddenField($name, $value = '', $parameters = '')
  {
    $field = '<input type="hidden" name="' . static::output($name) . '"';

    if (strlen($value) > 0) {
      $field .= ' value="' . static::output($value) . '"';
    } elseif ((isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name]))) {
      if (isset($_GET[$name]) && is_string($_GET[$name])) {
        $field .= ' value="' . static::output($_GET[$name]) . '"';
      } elseif (isset($_POST[$name]) && is_string($_POST[$name])) {
        $field .= ' value="' . static::output($_POST[$name]) . '"';
      }
    }

    if (!empty($parameters)) {
      $field .= ' ' . $parameters;
    }

    $field .= ' />';

    return $field;
  }

  /**
   * Generates an HTML button or anchor tag with specified attributes.
   *
   * @param string|null $title The text or label to display on the button. Optional.
   * @param string|null $icon The CSS class for the icon to include in the button. Optional.
   * @param string|null $link The URL to use for an anchor tag. If set, the button will be an anchor tag instead of a button element. Optional.
   * @param string|null $style The style of the button, corresponding to predefined CSS classes (e.g., 'primary', 'success'). Optional.
   * @param mixed|null $params Additional parameters for the button, such as its type or attributes. Optional.
   * @param string|null $size The size of the button, corresponding to predefined CSS classes (e.g., 'lg', 'sm'). Optional.
   * @return string Returns the HTML string representing the button or anchor tag.
   */

  public static function button(?string $title = null, ?string $icon = null, ?string $link = null, ?string $style = null, $params = null, ?string $size = null): string
  {
    $types = ['submit', 'button', 'reset'];
    $styles = ['primary', 'info', 'success', 'warning', 'danger', 'inverse', 'link', 'new', 'secondary', 'dark', 'light', 'default', 'close'];
    $size_button = ['lg', 'md', 'sm'];

    if (!isset($params['type'])) {
      $params['type'] = 'submit';
    }

    if (!in_array($params['type'], $types)) {
      $params['type'] = 'submit';
    }

    if (($params['type'] == 'submit') && isset($link)) {
      $params['type'] = 'button';
    }

    if (isset($style) && !in_array($style, $styles)) {
      unset($style);
    }

    if (isset($size) && !in_array($size, $size_button)) {
      unset($size);
    }

    $button = '';

    if (($params['type'] == 'button') && isset($link)) {
      // Security: Sanitize URL to prevent XSS
      $button .= '<a href="' . static::sanitizeUrl($link) . '"';
    } else {
      $button .= '<button type="' . static::outputProtected($params['type']) . '"';
    }

    if (isset($params['params'])) {
      // Security: Sanitize additional parameters
      $button .= ' ' . static::sanitizeHtmlAttributes($params['params']);
    }

    $button .= ' class="btn ';

    if (isset($style)) {
      // Security: Style is validated against whitelist above
      $button .= ' btn-' . static::output($style);
    }

    if (isset($size)) {
      // Security: Size is validated against whitelist above
      $button .= ' btn-' . static::output($size);
    }

    $button .= '">';

    if (isset($icon) && !empty($icon)) {
      // Security: Escape icon class to prevent XSS
      $button .= '<i class="' . static::outputProtected($icon) . '"></i> ';
    }

    // Security: Escape title content
    $button .= static::outputProtected($title);

    if (($params['type'] == 'button') && isset($link)) {
      $button .= '</a>';
    } else {
      $button .= '</button>';
    }

    return $button;
  }

  /**
   * Generates a string of star icons based on the provided rating.
   *
   * @param int $rating The number of stars to display (default is 0).
   * @param bool $meta Whether to include a meta tag for the rating (default is true).
   * @param string $style The CSS class to apply to the stars (default is 'text-warning').
   * @return string The rendered HTML string of stars and an optional meta tag.
   */
  public static function stars(int $rating = 0, bool $meta = true, string $style = 'text-warning'): string
  {
    $stars = str_repeat('<i class="bi bi-star-fill ' . $style . '"></i>', $rating);

    if ($meta !== false) {
      $stars .= '<meta itemprop="rating" content="' . $rating . '" />';
    }

    return $stars;
  }

  /**
   * Generates a ticker image HTML element based on the provided parameters.
   *
   * @param string|null $name The name or text to be displayed inside the ticker. Defaults to null.
   * @param string|null $css A string representing CSS class(es) to be applied to the ticker element. Defaults to null.
   * @param bool $display A boolean determining whether the ticker should be displayed. Defaults to false.
   * @return string The HTML string for the ticker element, or an empty string if display is false.
   */
  public static function tickerImage(?string $name = null, ?string $css = null, bool $display = false): string
  {
    $ticker = '';

    if ($display == 'true') {
      $ticker = '<span class="' . $css . '">' . $name . '</span>';
    }
    return $ticker;
  }

  /**
   * Generates a dropdown menu for selecting countries based on their ISO code.
   *
   * @param string $name The name attribute for the select menu.
   * @param string $selected The pre-selected option's ISO code. Defaults to an empty string.
   * @param string $parameters Additional parameters to include in the HTML select tag. Defaults to an empty string.
   * @return string The HTML string for the select menu.
   */
  public static function selectMenuIsoList($name, $selected = '', $parameters = '')
  {
    $CLICSHOPPING_Address = Registry::get('Address');
    $countries_array = array(array('id' => '',
      'text' => CLICSHOPPING::getDef('entry_text_select'))
    );

    $countries = $CLICSHOPPING_Address->getCountries();

    for ($i = 0, $n = count($countries); $i < $n; $i++) {
      $countries_array[] = [
        'id' => $countries[$i]['countries_iso_code_2'],
        'text' => $countries[$i]['countries_name']
      ];
    }

    return HTML::selectMenu($name, $countries_array, $selected, $parameters);
  }

  /**
   * Removes specific HTML comments marked with a known pattern from the provided HTML string.
   *
   * @param string $clean_html The HTML content to be processed and stripped of specific comment patterns.
   * @return string The HTML content with the targeted comments removed.
   */
  public static function cleanHtmlComments(string $clean_html): string
  {
    $its_cleaned = '';

    if (strpos($clean_html, '<!--//*') > 1) {
      $the_end1 = strpos($clean_html, '<!--//*') - 1;
      $the_start2 = strpos($clean_html, '*//-->') + 7;
      $its_cleaned = substr($clean_html, 0, $the_end1);
      $its_cleaned .= substr($clean_html, $the_start2);
    } else {
      $its_cleaned = $clean_html;
    }

    return $its_cleaned;
  }

  /**
   * Removes accents from the provided string and replaces them with their non-accented equivalents.
   *
   * @param string $character The input string that may contain accented characters.
   * @return string The string with accented characters replaced by their non-accented equivalents.
   */

  public static function removeFileAccents(string $character): string
  {
    $accent = ['À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ'];
    $new_accent = ['A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o'];

    return str_replace($accent, $new_accent, $character);
  }

  /**
   * Replaces all occurrences of a search string with a replacement string in the given input.
   *
   * @param string $search The string to search for.
   * @param string $replace The string to replace the search string with.
   * @param string $name The input string in which replacements will be made.
   * @return string The modified string after replacements have been applied.
   */
  public static function replaceString(string $search = '', string $replace = '', string $name = ''): string
  {
    return str_replace($search, $replace, $name);
  }
  
   /**
   * Generates a group of color swatch buttons for the color_picker option type.
   *
   * Unlike radioField(), this method does NOT use <input type="radio"> elements,
   * because hidden radio inputs combined with "required" cause browsers to block
   * form submission even when one option is selected (the browser cannot scroll
   * to an invisible input to show the validation error).
   *
   * Instead, the approach is:
   *  - One <input type="hidden"> "sentinel" per option group, carrying name="id[X]"
   *    and required. It starts empty (invalid) and is filled by JS on swatch click.
   *  - Visual <button type="button"> swatches with data attributes consumed by
   *    products_info_options_color_swatch.js.
   *
   * The JS file must be loaded via:
   *   $footer .= '<script defer src="' . CLICSHOPPING::link(
   *       $CLICSHOPPING_Template->getTemplateDefaultJavaScript(
   *           'clicshopping/products_info_options_color_swatch.js'
   *       )
   *   ) . '"></script>' . "\n";
   *
   * @param string     $name             Form field name, e.g. 'id[42]'
   * @param array      $values           Array of ['id' => int, 'text' => string (hex color), 'price' => string]
   * @param int|false  $selected_value   Currently selected value id, or false if none
   * @param string     $group_id         Unique HTML id for the swatch container, e.g. 'color_group_42'
   * @param string     $option_label     Human-readable option name (for aria-label)
   * @return string    HTML markup for the sentinel input + swatch buttons
   */

  public static function colorSwatchField(
    string   $name,
    array    $values,
    mixed    $selected_value,
    string   $group_id,
    string   $option_label = ''
  ): string
  {
    // Resolve selected value from POST/GET (mirrors selectionField behaviour)
    $name_key = str_contains($name, '[') ? substr($name, 0, strpos($name, '[')) : $name;

    if (isset($_POST[$name_key]) && is_string($_POST[$name_key])) {
      $selected_value = $_POST[$name_key];
    } elseif (isset($_GET[$name_key]) && is_string($_GET[$name_key])) {
      $selected_value = $_GET[$name_key];
    }

    $sentinel_value = ($selected_value !== false && $selected_value !== '') ? (int)$selected_value : '';

    $html = '';

    $html .= '<input type="hidden" name="' . $name . '" id="' . $group_id . '_sentinel"  value="' . $sentinel_value . '" data-color-sentinel="' . $group_id . '" required>';

    // --- Swatch container -----------------------------------------------
    $html .= '<div class="ModuleProductsInfoOptionsColorSwatches"'
      . ' id="' . $group_id . '"'
      . ' role="radiogroup"'
      . ' aria-label="' . $option_label . '"'
      . '>';

    foreach ($values as $value) {
      // On utilise 'color' si dispo (notre hexa), sinon on se rabat sur 'text'
      $color_source = $value['color'] ?? $value['text'];
      $hex          = '#' . ltrim((string)$color_source, '#');

      $swatch_id   = $group_id . '_' . (int)$value['id'];
      $is_selected = ($sentinel_value !== '' && (int)$sentinel_value === (int)$value['id']);
      $title       = $value['text'] . (!empty($value['price']) ? ' ' . $value['price'] : '');

      $html .= '<div class="ModuleProductsInfoOptionsColorSwatchItem">';
      $html .= '<button type="button"'
        . ' id="' . $swatch_id . '"'
        . ' role="radio"'
        . ' aria-checked="' . ($is_selected ? 'true' : 'false') . '"'
        . ' data-swatch-group="' . $group_id . '"'
        . ' data-swatch-value="' . (int)$value['id'] . '"'
        . ' title="' . $title . '"'
        . ' class="ModuleProductsInfoOptionsColorSwatchLabel' . ($is_selected ? ' swatch-selected' : '') . '"'
        . ' style="background-color:' . $hex . ';cursor:pointer;border:none;padding:0;"'
        . '></button>';

      if (!empty($value['price'])) {
        $html .= '<small class="ModuleProductsInfoOptionsColorSwatchPrice">' . $value['price'] . '</small>';
      }

      $html .= '</div>';
    }

    $html .= '</div>'; // .ModuleProductsInfoOptionsColorSwatches

    return $html;
  }

  /**
   * Generaete a model randomly
   * @param int $length
   * @return int, string
   * @throws \Random\RandomException
   */
  public static function generateRandomNumber(int $length = 8): int|string
  {
    $rand = '';

    for ($i = 0; $i < $length; $i++) {
      $rand .= random_int(0, $length);
    }

    return $rand;
  }

  /**
   * Sanitizes a URL to prevent XSS attacks via javascript: protocol and other dangerous schemes.
   * 
   * @param string|null $url The URL to sanitize
   * @return string The sanitized URL, or empty string if dangerous
   */
  protected static function sanitizeUrl(?string $url): string
  {
    if (is_null($url) || empty($url)) {
      return '';
    }

    // Remove whitespace and control characters
    $url = trim($url);
    $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url);

    // Check for dangerous protocols (case-insensitive)
    $dangerous_protocols = [
      'javascript:',
      'data:',
      'vbscript:',
      'file:',
      'about:',
    ];

    $url_lower = strtolower($url);
    
    foreach ($dangerous_protocols as $protocol) {
      // Check for protocol with or without whitespace/encoding tricks
      if (str_starts_with($url_lower, $protocol)) {
        return ''; // Return empty string for dangerous URLs
      }
      
      // Check for encoded versions (e.g., java%09script:)
      $url_decoded = urldecode($url_lower);
      if (str_starts_with($url_decoded, $protocol)) {
        return '';
      }
    }

    // For href attributes, we only need to escape quotes and < >
    // Don't use htmlspecialchars as it will break query strings with &
    // The URL is already in the href="" context which provides protection
    $url = str_replace(['"', "'", '<', '>'], ['%22', '%27', '%3C', '%3E'], $url);

    return $url;
  }

  /**
   * Sanitizes HTML attributes to prevent XSS attacks.
   * This is a basic sanitization - callers should ideally provide pre-validated attributes.
   * 
   * @param string|null $attributes The attributes string to sanitize
   * @return string The sanitized attributes
   */
  protected static function sanitizeHtmlAttributes(?string $attributes): string
  {
    if (is_null($attributes) || empty($attributes)) {
      return '';
    }

    // First, decode any HTML entities to catch encoded attacks
    $attributes = html_entity_decode($attributes, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Remove any event handlers (onclick, onerror, onload, etc.)
    $attributes = preg_replace('/\s*on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $attributes);
    
    // Remove javascript: protocol from any attribute values
    $attributes = preg_replace('/javascript:/i', '', $attributes);
    
    // Remove data: protocol from any attribute values  
    $attributes = preg_replace('/data:/i', '', $attributes);
    
    // Remove vbscript: protocol
    $attributes = preg_replace('/vbscript:/i', '', $attributes);

    return trim($attributes);
  }
}
