<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Module\Hooks\ClicShoppingAdmin\DashboardShortCut;

use ClicShopping\Apps\Catalog\Categories\Categories as categoriesApp;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
/**
 * The DashboardShortCutCategories class contains the logic for displaying the categories shortcut on the dashboard.
 */
class DashboardShortCutCategories implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Categories application component.
   *
   * Checks if the `Categories` component exists in the Registry. If it does not,
   * it creates and registers a new instance of `categoriesApp`.
   * It then retrieves the registered `Categories` component and loads necessary language definitions
   * for the dashboard shortcut categories module.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Categories')) {
      Registry::set('Categories', new categoriesApp());
    }

    $this->app = Registry::get('Categories');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/DashboardShortCut/dashboard_shortcut_categories');
  }

  /**
   * Generates and returns an HTML link for the categories shortcut if the application status allows it.
   *
   * @return string HTML link for the categories shortcut if the application is active, otherwise returns false.
   */
  public function display(): string
  {
    if (!\defined('CLICSHOPPING_APP_CATEGORIES_CT_STATUS') || CLICSHOPPING_APP_CATEGORIES_CT_STATUS == 'False') {
      return false;
    }

    $output = HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Categories&Categories'), null, 'class="btn btn-primary btn-sm" role="button"></i><span class="bi bi-list-ul" title="' . $this->app->getDef('heading_short_categories') . '"') . ' ';

    return $output;
  }
}