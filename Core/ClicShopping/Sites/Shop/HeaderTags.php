<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
/**
 * Retrieves and formats the meta tag information for the footer.
 * The data is fetched from the database for the current language
 * and is processed to generate clickable links.
 *
 * @return string The formatted meta tag content for the footer.
 */
class HeaderTags
{

  /**
   * Generates and returns the footer tag content based on the default SEO language footer retrieved from the database.
   *
   * @return string The formatted footer tag content with links created for each keyword.
   */

  public static function getFooterTag(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qsubmit_footer = $CLICSHOPPING_Db->prepare('select seo_defaut_language_footer
                                                  from :table_seo
                                                  where language_id = :language_id
                                                ');
    $Qsubmit_footer->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $Qsubmit_footer->execute();

    if ($Qsubmit_footer->fetch()) {
      $footer = HTML::outputProtected($Qsubmit_footer->value('seo_defaut_language_footer'));

      $delimiter = ',';
      $footer = trim(preg_replace('|\\s*(?:' . preg_quote($delimiter) . ')\\s*|', $delimiter, $footer));
      $footer1 = explode(',', $footer);

      $footer_content = '';

      foreach ($footer1 as $value) {
        $footer_content .= HTML::link(CLICSHOPPING::link(null, 'Search&Q&keywords=' . HTML::sanitize($value) . '&search_in_description=1'), $value) . ', ';
      }

      return $footer_content;
    }

    return '';
  }

  /**
   * Generates and returns the canonical URL for the current request by removing specific unnecessary query string parameters.
   *
   * @return string The canonical URL for the current request.
   */
  public static function getCanonicalUrl(): string
  {
    // Same source as the strict router and the canonical tag when it arbitrated the request.
    $canonical = UrlCanonicalizer::getCanonicalUrl();

    if ($canonical !== null) {
      return HTML::outputProtected($canonical);
    }

    $request_uri = (string)($_SERVER['REQUEST_URI'] ?? '');

    // Drop the session id, in both the query string and the SEO PRO path form.
    $request_uri = (string)preg_replace('#[&?]' . preg_quote(session_name(), '#') . '=[^&]*#', '', $request_uri);
    $request_uri = (string)preg_replace('#/' . preg_quote(session_name(), '#') . '-[^/]+#', '', $request_uri);

    return HTML::outputProtected(CLICSHOPPING::getConfig('http_server', 'Shop') . $request_uri);
  }
}
