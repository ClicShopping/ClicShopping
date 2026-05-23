<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\CLICSHOPPING;

/**
 * ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Dashboard\ActionLinkedin
 *
 * This class is a module hook for the ClicShoppingAdmin dashboard. It generates and displays
 * an HTML block that includes a LinkedIn "Add to Profile" button.
 * This feature allows users to add a ClicShopping professional certification to their LinkedIn profile.
 */
class ActionLinkedin
{

  /**
   * Generates and returns the HTML output for displaying a LinkedIn certificate.
   *
   * @return string The HTML output for displaying the LinkedIn Add to Profile button.
   */
  public function execute()
  {
    $output = '<div class="mt-1"></div>
                <div class="text-center">' . CLICSHOPPING::getDef('linkedin_certificate_clicshopping') . '</div>
                <div class="text-center"><a href="https://www.linkedin.com/profile/add?_ed=0_7nTFLiuDkkQkdELSpruCwGpUMNDIZlXEx27EQU-qViHbBgqjFXJtPnC2wRYrmwrBaSgvthvZk7wTBMS3S-m0L6A6mLjErM6PJiwMkk6nYZylU7__75hCVwJdOTZCAkdv&pfCertificationName=ClicShopping%20professionnal&pfCertificationUrl=http%3A%2F%2Fwww.clicshopping.org&trk=onsite_html" rel="noreferrer" target="_blank"><img src="../images/ClicShoppingAdmin/linkedin.webp" alt="LinkedIn Add to Profile button"></a></div>
                ';

    return $output;
  }
}