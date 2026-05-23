<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Domains;

use ClicShopping\OM\Interfaces;
use ReflectionClass;

/**
 * Abstract base class for implementing site-specific functionality.
 * This class provides basic structure and utilities for managing pages,
 * routes, and site-specific logic.
 */
abstract class SitesAbstract implements Interfaces\SitesInterface
{
  protected string $code;
  protected string $default_page = 'Home'; // else white page
  protected mixed $page;
  public mixed $app;
  protected mixed $route;
  public int $actions_index = 1;

  abstract protected function init();

  abstract public function setPage();

  /**
   * Constructor method.
   *
   * Initializes the class by setting the code property to the short name of the class
   * and calling the init method.
   *
   * @return mixed The return value of the init method.
   */
  final public function __construct()
  {

    $this->code = (new ReflectionClass($this))->getShortName();

    $this->init();
  }

  /**
   * Retrieves the code property of the current object.
   *
   * @return string The code associated with the object.
   */
  public function getCode(): string
  {
    return $this->code;
  }

  /**
   * Checks if a page is set.
   *
   * @return bool Returns true if a page is set, false otherwise.
   */
  public function hasPage(): bool
  {
    return isset($this->page);
  }

  /**
   * Retrieves the current page property.
   *
   * @return mixed Returns the value of the page property.
   */
  public function getPage()
  {
    return $this->page;
  }

  /**
   * Retrieves the current route.
   *
   * @return mixed The route information stored within the instance.
   */
  public function getRoute()
  {
    return $this->route;
  }

  /**
   * Resolves the given route based on the provided routes configuration.
   *
   * @param array $route The route to resolve, typically including parameters such as path or name.
   * @param array $routes A collection of predefined routes against which the provided route is matched.
   * @return array|null Returns the matched route details if a match is found, otherwise null.
   */
  public static function resolveRoute(array $route, array $routes)
  {
  }
}
