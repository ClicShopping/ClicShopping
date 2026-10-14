<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager;


use ClicShopping\OM\Domains\ConfigurableAppAbstract;


class PageManager extends ConfigurableAppAbstract
{
  /**
   * Pages the shop depends on and that must never be deleted: 3 is the contact redirection
   * entry, 4 and 5 are the terms of sale and the privacy policy, both targeted by the
   * SHOP_CODE_URL_* configuration constants.
   *
   * Single source for the rule: it used to be written once in the listing template and once in
   * the delete action, and the two drifted apart — the template protected 3 and 4 only.
   */
  public const LOCKED_PAGES_ID = [3, 4, 5];

  /**
   * API version for this domain app
   *
   * @var int
   */
  protected $api_version = 1;

  /**
   * Unique identifier for this domain app
   * 
   * Format: ClicShopping_{Vendor}_{AppName}_V{Version}
   * 
   * @var string
   */
  protected string $identifier = 'ClicShopping_PageManager_V1';

  /**
   * Initializes the necessary components or configurations for the class.
   *
   * @return void
   */
  protected function init(): void
  {
  }
}
