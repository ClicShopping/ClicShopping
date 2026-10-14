<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\CLICSHOPPING;

class ActionDonate
{

  /**
   * Constructor method that initializes the class and ensures the current site is 'ClicShoppingAdmin'.
   * If the site is not 'ClicShoppingAdmin', it will redirect to another location.
   *
   * @return void
   */
  public function __construct()
  {

    if (CLICSHOPPING::getSite() != 'ClicShoppingAdmin') {
      CLICSHOPPING::redirect();
    }
  }

  /**
   * Generates and returns a formatted HTML output containing donation and social media links.
   *
   * @return string The HTML content with donation and social media links.
   */
  public function execute()
  {
    $output = '<div class="mt-1"></div>
                 <div class="text-center">
                   <span class="badge bg-light">
                     <a href="https://www.clicshopping.org/forum/clients/donations/" rel="noreferrer" target="_blank">' . CLICSHOPPING::getDef('text_donate') . '</a>
                   </span>
                 </div>
                 <div class="mt-1"></div>
                 <div class="text-center">' . CLICSHOPPING::getDef('text_follow_us') . ' <a href="https://twitter.com/clicshopping" rel="noreferrer" target="_blank"> Twitter</a> | <a href="https://www.facebook.com/Clicshopping-583928135031577/" rel="noreferrer" target="_blank">Facebook</a></div>
                ';

    return $output;
  }
}