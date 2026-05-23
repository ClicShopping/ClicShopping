<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\OM\Interfaces;

/**
 * Interface SitesInterface
 *
 * Defines the structure for handling pages and resolving routes
 * in the context of the application.
 */
interface SitesInterface
{
  /**
   * Determines whether a page exists.
   *
   * This method checks if a page is available or defined within the context
   * of the implementation. The return value typically indicates the presence
   * or absence of a page entity.
   *
   * @return bool True if a page exists, otherwise false.
   */
  public function hasPage();

  /**
   * Retrieves a page based on the implementation details of the method.
   * The specific page returned will depend on the context and internal logic.
   * May interact with other components or data sources to determine the page.
   *
   * @return mixed The retrieved page, format or structure may vary.
   */
  public function getPage();

  /**
   *
   */
  public function setPage();

  /**
   * Resolves a given route against a set of defined routes.
   *
   * @param array $route The route to resolve, typically containing route components such as path and parameters.
   * @param array $routes The collection of predefined routes to match against.
   */
  public static function resolveRoute(array $route, array $routes);
}
