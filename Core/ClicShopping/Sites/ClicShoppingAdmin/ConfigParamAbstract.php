<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\HTML;
use ReflectionClass;
use function constant;
use function defined;
/**
 * This abstract class provides a template to define configurable parameters.
 * It includes methods for initialization, input handling, and rendering configuration forms for an admin interface.
 */
abstract class ConfigParamAbstract
{
  protected string $code;
  protected string $key_prefix;
  protected string $key;
  public $title;
  public $description;
  public $default;
  public int|null $sort_order = 0;

  abstract protected function init();

  /**
   * Initializes the object by setting the code and key properties
   * based on the class name and a key prefix, and calls the init method.
   *
   * @return void
   */
  public function __construct()
  {
    $this->code = (new ReflectionClass($this))->getShortName();

    $this->key = $this->key_prefix . $this->code;

    $this->init();
  }

  /**
   * Retrieves the input value for the current key. If the key is not defined, the default value (if set) is returned.
   *
   * @return mixed The retrieved value associated with the key, or the default value if the key is not defined.
   */
  protected function getInputValue()
  {
    $key = mb_strtoupper($this->key);
    $value = defined($key) ? constant($key) : null;

    if (!isset($value) && isset($this->default)) {
      $value = $this->default;
    }

    return $value;
  }

  /**
   * Retrieves the input field HTML element for the given key and input value.
   *
   * @return string The HTML string of the input field.
   */
  public function getInputField()
  {
    $input = HTML::inputField($this->key, $this->getInputValue());

    return $input;
  }

  /**
   *
   * @return string The generated HTML structure, incorporating the input field, title, and description.
   */
  public function getSetField()
  {
    $input = $this->getInputField();

    $result = <<<EOT
  <div class="row">
    <div class="col-md-7">
      <strong>{$this->title} : </strong>
      <p>{$this->description}</p>
    </div>
    <div class="col-md-5">
      {$input}
    </div>
  </div>
EOT;

    return $result;
  }
}
