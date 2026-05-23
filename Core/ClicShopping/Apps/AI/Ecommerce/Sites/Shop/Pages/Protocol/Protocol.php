<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Sites\Shop\Pages\Protocol;

use ClicShopping\Apps\AI\Ecommerce\Sites\Shop\Pages\ACP\ACP as AcpPage;
use ClicShopping\Apps\AI\Ecommerce\Sites\Shop\Pages\UCP\UCP as UcpPage;
use ClicShopping\OM\SimpleLogger;
use ClicShopping\OM\Registry;

class Protocol extends \ClicShopping\OM\Domains\PagesAbstract
{
  protected string|null $file = null;
  protected bool $use_site_template = false;

  public function init()
  {
    $logger = Registry::exists('SimpleLogger') ? Registry::get('SimpleLogger') : new SimpleLogger('Ecommerce_Protocol');

    $path = $_SERVER['REQUEST_URI'] ?? '';
    $query = $_GET;

    $isAcp = isset($query['ACP']) || isset($query['OpenAI']) || str_contains(strtolower($path), 'acp');
    $isUcp = isset($query['UCP']) || isset($query['Google']) || str_contains(strtolower($path), 'ucp');

    if ($isUcp && !$isAcp) {
      $logger->info('Protocol routing', ['protocol' => 'UCP', 'path' => $path]);
      $page = new UcpPage();
      $page->init();
      return;
    }

    if ($isAcp && !$isUcp) {
      $logger->info('Protocol routing', ['protocol' => 'ACP', 'path' => $path]);
      $page = new AcpPage();
      $page->init();
      return;
    }

    // Default fallback: try ACP then UCP
    if ($isAcp) {
      $logger->info('Protocol routing', ['protocol' => 'ACP', 'path' => $path]);
      $page = new AcpPage();
      $page->init();
      return;
    }

    if ($isUcp) {
      $logger->info('Protocol routing', ['protocol' => 'UCP', 'path' => $path]);
      $page = new UcpPage();
      $page->init();
      return;
    }

    $logger->warning('Protocol routing failed', ['path' => $path]);
    http_response_code(404);
    echo json_encode(['error' => 'Unknown protocol'], JSON_UNESCAPED_SLASHES);
  }
}
