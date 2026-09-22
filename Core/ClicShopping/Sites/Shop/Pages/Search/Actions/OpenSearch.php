<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Sites\Shop\Pages\Search\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

class OpenSearch extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    if (!\defined('MODULE_HEADER_TAGS_OPENSEARCH_STATUS') || (MODULE_HEADER_TAGS_OPENSEARCH_STATUS != 'True')) {
      exit;
    }

    $cfg = static fn(string $key): string => \defined($key) ? HTML::outputProtected((string)\constant($key)) : '';


    $searchUrl = HTML::outputProtected(CLICSHOPPING::link(null, 'Search&Q&keywords=', false, false)) . '{searchTerms}';

    $output = '<?xml version="1.0"?>' . "\n"
      . '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/" xmlns:moz="http://www.mozilla.org/2006/browser/search/">' . "\n"
      . '<ShortName>' . $cfg('MODULE_HEADER_TAGS_OPENSEARCH_SITE_SHORT_NAME') . '</ShortName>' . "\n"
      . '<Description>' . $cfg('MODULE_HEADER_TAGS_OPENSEARCH_SITE_DESCRIPTION') . '</Description>' . "\n";

    foreach (['SITE_CONTACT' => 'Contact', 'SITE_TAGS' => 'Tags', 'SITE_ATTRIBUTION' => 'Attribution'] as $key => $tag) {
      $value = $cfg('MODULE_HEADER_TAGS_OPENSEARCH_' . $key);

      if ($value !== '') {
        $output .= '<' . $tag . '>' . $value . '</' . $tag . '>' . "\n";
      }
    }

    if (\defined('MODULE_HEADER_TAGS_OPENSEARCH_SITE_ADULT_CONTENT') && MODULE_HEADER_TAGS_OPENSEARCH_SITE_ADULT_CONTENT == 'True') {
      $output .= '<AdultContent>true</AdultContent>' . "\n";
    }

    $icon = $cfg('MODULE_HEADER_TAGS_OPENSEARCH_SITE_ICON');

    if ($icon !== '') {
      $output .= '<Image height="16" width="16" type="image/x-icon">' . $icon . '</Image>' . "\n";
    }

    $image = $cfg('MODULE_HEADER_TAGS_OPENSEARCH_SITE_IMAGE');

    if ($image !== '') {
      $output .= '<Image height="64" width="64" type="image/png">' . $image . '</Image>' . "\n";
    }

    $output .= '<InputEncoding>UTF-8</InputEncoding>' . "\n"
      . '<Url type="text/html" method="get" template="' . $searchUrl . '" />' . "\n"
      . '</OpenSearchDescription>' . "\n";

    // The dispatcher DROPS what an action returns (PagesAbstract::runAction): a raw document is
    // echoed and the request ends here, as the RSS action does.
    header('Content-Type: application/opensearchdescription+xml; charset=UTF-8');
    echo $output;
    exit;
  }
}
