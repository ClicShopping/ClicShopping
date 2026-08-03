<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use function in_array;
use function is_array;

/**
 * Makes the SEO PRO router strict: once the request has been routed, no path segment
 * may stay unaccounted for.
 *
 * SEFU turns every path segment into a $_GET entry and Shop::setPage() only ever reads
 * the FIRST key, so any extra or invented segment used to be served in 200 — the same
 * content answering under an unbounded number of URLs. This class closes that hole:
 *
 *  - the request designates nothing (unknown page, unknown action, unknown id) -> 404
 *  - the request designates an existing resource through a non canonical spelling
 *    (wrong or missing slug, junk segments, case, trailing slash, parameter order) -> 301
 *
 * This class knows NO page and NO route: which requests are canonicalizable is decided by
 * the Apps themselves through the `SiteUrl/Canonical` hook, so an App installed later —
 * or a page added under Sites/Shop/Pages — is taken into account without touching Core.
 * An App that ships no provider is never redirected: strictness is opt-in per App.
 *
 * Hook contract — `Module\Hooks\Shop\SiteUrl\Canonical::execute(array $parameters): array|null`
 *   $parameters: ['stem' => string[], 'stem_key' => string, 'leftover' => array,
 *                 'presentation' => string, 'route' => array|null]
 *   return null    the request is not this App's business
 *   return ['canonical' => '<absolute url>']  the canonical URL of the designated resource
 *   return ['not_found' => true]              the App owns the request but the resource does not exist
 */
class UrlCanonicalizer
{
  /**
   * Presentation parameters kept on a canonical URL, in canonical order. This is a display
   * vocabulary shared by every listing, not an inventory of pages: adding a page never
   * requires touching it.
   */
  private const PRESENTATION_PARAMETERS = ['page', 'sort', 'view', 'filter_id', 'language', 'currency'];

  /**
   * The presentation parameters only a LISTING honours. The other two (`language`, `currency`) are
   * global display state every page carries — the language switcher rebuilds its links from the
   * canonical, so stripping them would break it.
   *
   * The shape gates admit any integer, so `page` and `filter_id` alone keep the URL space infinite
   * on a page that paginates nothing: `…/Description/<slug>/Id-3/page-99999` answered 200 with a
   * self-referencing canonical. Only the owning App knows whether its page is a listing, hence
   * withoutListingParameters() and the `"listing": false` declaration.
   */
  private const LISTING_PARAMETERS = ['page', 'sort', 'view', 'filter_id'];

  /**
   * The shape each presentation value must have to reach a canonical URL, mirroring what the
   * consumers already enforce (DbStatement::setPageSet wants is_numeric, ProductsListing wants
   * /^[1-8][ad]$/, ProductsListingContext only honours view=line). The canonicalizer was the one
   * component copying these values verbatim, which turned the six keys into an unbounded 200
   * space: /Id-3/sort-anything-i-want answered 200 with a self-referencing canonical.
   */
  private const PRESENTATION_VALUE_SHAPES = [
    'page' => '/^[1-9][0-9]*$/',
    'sort' => '/^[0-9]+[ad]$/',
    'view' => '/^(line|grid)$/',
    'filter_id' => '/^[1-9][0-9]*$/',
    'language' => '/^[a-z]{2,5}$/',
    'currency' => '/^[A-Za-z]{3}$/'
  ];

  /**
   * Canonical URL computed for the request being served, so the `<link rel="canonical">` tag can
   * reuse it instead of re-deriving it from $_GET with its own guards — the two used to disagree,
   * and the category and editorial pages ended up with no tag at all.
   */
  private static ?string $canonical = null;

  /**
   * Route segments the router consumed, kept for the modules that need to identify the page
   * independently of the URL syntax (robots noindex, ...). Recorded for EVERY request, including
   * the ones the strict contract does not apply to.
   */
  private static array $stem = [];

  /**
   * The App route the router resolved, kept so the canonical can be recomputed after the fact
   * for another language — the default provider reads the owning App from it.
   */
  private static ?array $route = null;

  /**
   * Path entries left once the route stem is removed, kept so the canonical can be recomputed
   * after the listings have run — an out-of-range page number is only visible then.
   */
  private static array $leftover = [];

  /**
   * @return string|null The canonical URL of the request being served, null when no App claimed it.
   */
  public static function getCanonicalUrl(): ?string
  {
    return self::$canonical;
  }

  /**
   * Canonical URL of the CURRENT resource as it is spelled in another language.
   *
   * The language switcher used to rebuild its links from $_GET, where the slug is the one of
   * the language being left: /dinning-bar/cPath-3/language-fr, which the router then had to
   * 301 onto /art-de-la-table-bar/…. Asking the providers again with a target language gives
   * the right spelling straight away — the slug is the only part that changes.
   *
   * @param int $language_id The target language.
   * @param string $language_code Its code, appended as the `language` presentation parameter.
   * @return string|null The canonical URL in that language, null when no App claims the request
   *                     (the caller must then keep its own fallback).
   */
  public static function getCanonicalUrlInLanguage(int $language_id, string $language_code): ?string
  {
    if (!Registry::exists('Hooks')) {
      return null;
    }

    if (!\defined('SEARCH_ENGINE_FRIENDLY_URLS_PRO') || SEARCH_ENGINE_FRIENDLY_URLS_PRO != 'true') {
      return null;
    }

    $leftover = array_slice($_GET, \count(self::$stem), null, true);

    // The target language replaces whatever the current URL carries.
    $leftover['language'] = $language_code;

    $verdict = self::askProviders(self::$stem, $leftover, self::$route, $language_id);

    return isset($verdict['canonical']) ? (string)$verdict['canonical'] : null;
  }

  /**
   * @return string The route the request resolved to, in the "Page&Action" form the shop uses to
   *                designate a page (empty for the index).
   */
  public static function getRouteStem(): string
  {
    return implode('&', self::$stem);
  }

  /**
   * Enforces the strict contract for the current request. Returns without doing anything
   * whenever the request is outside the SEO perimeter — the safe default is always to serve.
   *
   * @param array $stem Ordered route segments consumed by the router (page code + actions).
   * @param array|null $route The App route the router resolved, when it came from one.
   */
  public static function enforce(array $stem, ?array $route = null): void
  {
    // Recorded before any early return: the page identity is useful even when the strict
    // contract does not apply (query string URL, logged-in session, POST).
    self::$stem = $stem;
    self::$route = $route;

    if (!self::isEnforceable()) {
      return;
    }

    $leftover = self::$leftover = array_slice($_GET, \count($stem), null, true);
    $verdict = self::askProviders($stem, $leftover, $route);

    if (isset($verdict['not_found'])) {
      self::notFound();
    }

    if (isset($verdict['canonical'])) {
      self::$canonical = (string)$verdict['canonical'];

      // Past this point the request already IS canonical, so the tag matches the served URL.
      self::redirectIfNotCanonical(self::$canonical);

      return;
    }

    // No App claims this request: it may still designate nothing at all.
    if (self::designatesNothing($stem, $leftover)) {
      self::notFound();
    }
  }

  /**
   * Second pass, run once every listing of the request has queried: drops a page number no listing
   * can serve. `/dinning-bar/cPath-3/page-999` used to answer 200 with an empty listing and a
   * self-referencing canonical — the form gate proves `999` is well-formed, only the query knows
   * the shop stops at page 1.
   *
   * Deliberately conservative, because several listings share the `page` keyword on one page: the
   * bound used is the HIGHEST any of them offers, so a page is dropped only when none can serve it.
   *
   * ⚠️ Must be called while the response body has not started — hence the chokepoint at the end of
   * Template::buildBlocks(), measured to be after every page set of the request and before the
   * first byte of output. A listing querying LATER than that is not accounted for.
   *
   * @param array $bounds Page-set bounds observed on the request, keyword => highest page servable.
   */
  public static function enforceListingBounds(array $bounds): void
  {
    if (self::$canonical === null || $bounds === [] || headers_sent() || !self::isEnforceable()) {
      return;
    }

    $reachable = self::$leftover;

    foreach ($bounds as $keyword => $pages) {
      if (!in_array($keyword, self::PRESENTATION_PARAMETERS, true)) {
        continue;
      }

      if (isset($reachable[$keyword]) && (int)$reachable[$keyword] > (int)$pages) {
        unset($reachable[$keyword]);
      }
    }

    if ($reachable === self::$leftover) {
      return;
    }

    $verdict = self::askProviders(self::$stem, $reachable, self::$route);

    if (isset($verdict['canonical'])) {
      self::$canonical = (string)$verdict['canonical'];

      self::redirectIfNotCanonical(self::$canonical);
    }
  }

  /**
   * Determines whether the strict contract applies to the current request.
   */
  private static function isEnforceable(): bool
  {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !empty($_POST)) {
      return false;
    }

    if (!\defined('SEARCH_ENGINE_FRIENDLY_URLS') || SEARCH_ENGINE_FRIENDLY_URLS != 'true') {
      return false;
    }

    if (!\defined('SEARCH_ENGINE_FRIENDLY_URLS_PRO') || SEARCH_ENGINE_FRIENDLY_URLS_PRO != 'true') {
      return false;
    }

    // A logged-in session gets a different link syntax and carries transactional state.
    if (isset($_SESSION['login_customer_id'])) {
      return false;
    }

    if (!Registry::exists('Hooks')) {
      return false;
    }

    $path_info = $_SERVER['PATH_INFO'] ?? ($_SERVER['ORIG_PATH_INFO'] ?? '');

    return \strlen($path_info) > 1;
  }

  /**
   * Asks every App that registered a SiteUrl/Canonical hook what the canonical form of the
   * current request is. The first App claiming the request wins.
   *
   * @return array The claiming provider's verdict, or an empty array when none claims it.
   */
  private static function askProviders(array $stem, array $leftover, ?array $route, ?int $language_id = null): array
  {
    $parameters = [
      'stem' => $stem,
      'stem_key' => implode('&', $stem),
      'leftover' => $leftover,
      'presentation' => self::buildPresentationParameters($leftover),
      'route' => $route,
      // null = the language of the request; set only when another spelling is being asked for.
      'language_id' => $language_id
    ];

    $answers = Registry::get('Hooks')->call('SiteUrl', 'Canonical', $parameters);

    foreach ($answers as $answer) {
      if (is_array($answer) && (isset($answer['canonical']) || isset($answer['not_found']))) {
        return $answer;
      }
    }

    return [];
  }

  /**
   * Tells whether the router resolved NOTHING: no route and no page class matched, yet the
   * path carries a segment that is not a presentation parameter.
   *
   * A page code followed by an unresolved segment is deliberately NOT treated here: a page
   * may legitimately handle a segment without an Action class (Sites/Shop/Pages/Account with
   * `Account&Main` does exactly that), so the router cannot tell junk from a valid sub-page.
   * Only the App owning the namespace can, through its SiteUrl/Canonical provider.
   */
  private static function designatesNothing(array $stem, array $leftover): bool
  {
    if (\count($stem) > 0) {
      return false;
    }

    foreach (array_keys($leftover) as $key) {
      if (!in_array($key, self::PRESENTATION_PARAMETERS, true)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Reorders the presentation parameters of a link being generated so the shop emits the
   * canonical spelling straight away.
   *
   * Generators rebuild their query from $_GET, which carries the order the VISITOR arrived
   * with: the same listing linked to `/view-line/page-1/sort-1a` and to
   * `/page-1/sort-1a/view-line` depending on the path taken, and every click that guessed
   * wrong paid a 301. Only `key=value` pairs are moved — a bare segment is a slug, not a
   * parameter, and reordering it would rewrite the resource being addressed.
   *
   * @param string $parameters An '&' separated parameter string, as passed to CLICSHOPPING::link().
   * @return string The same parameters with the presentation ones last, in canonical order.
   */
  public static function canonicalizeParameterOrder(string $parameters): string
  {
    if ($parameters === '') {
      return '';
    }

    $kept = [];
    $presentation = [];

    foreach (explode('&', $parameters) as $pair) {
      if ($pair === '') {
        continue;
      }

      [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);

      if ($value === null || $value === '' || !in_array($key, self::PRESENTATION_PARAMETERS, true)) {
        $kept[] = $pair;

        continue;
      }

      // Last occurrence wins, as PHP itself resolves a repeated query key.
      $presentation[$key] = $pair;
    }

    foreach (self::PRESENTATION_PARAMETERS as $key) {
      if (isset($presentation[$key])) {
        $kept[] = $presentation[$key];
      }
    }

    return implode('&', $kept);
  }

  /**
   * Drops the listing facets from a presentation suffix, for a page that paginates and sorts
   * nothing. A provider calls it on the `presentation` it receives; the declaration
   * `"canonical": {"Shop": {"listing": false}}` does the same without code.
   *
   * Relevance is the half the router cannot judge: the shape gates prove `page-99999` is
   * well-formed, only the owning App knows its page has no pagination at all.
   *
   * @param string $presentation The '&key=value' suffix supplied to the provider.
   * @return string The same suffix without page, sort, view and filter_id.
   */
  public static function withoutListingParameters(string $presentation): string
  {
    if ($presentation === '') {
      return '';
    }

    $kept = '';

    foreach (explode('&', $presentation) as $pair) {
      if ($pair === '') {
        continue;
      }

      [$key] = explode('=', $pair, 2);

      if (!in_array($key, self::LISTING_PARAMETERS, true)) {
        $kept .= '&' . $pair;
      }
    }

    return $kept;
  }

  /**
   * Rebuilds the presentation parameters in canonical order, dropping junk segments.
   *
   * A leftover entry qualifies only if it passes BOTH gates: it sits in the trailing run of the
   * path (see trailingParameters()) and its value has the shape the shop honours. Anything else
   * is re-emitted junk — the canonical of a product whose slug reads "page-turner" used to grow
   * a '/page-turner' tail, so the product's own link 301'd onto a duplicated URL.
   *
   * @return string A '&key=value' suffix ready for the URL generators, or an empty string.
   */
  private static function buildPresentationParameters(array $leftover): string
  {
    $addressed = self::parametersAfterTheResource($leftover);
    $extra = '';

    foreach (self::PRESENTATION_PARAMETERS as $key) {
      if (isset($addressed[$key]) && self::isHonouredValue($key, $addressed[$key])) {
        $extra .= '&' . $key . '=' . $addressed[$key];
      }
    }

    return $extra;
  }

  /**
   * The entries that follow the resource being addressed, i.e. everything after the last segment
   * that carries a value and is not a presentation key — which is where the shop puts them, since
   * canonicalizeParameterOrder() emits the presentation parameters last.
   *
   * SEFU::start() splits EVERY path segment on the first '-', so a decorative slug becomes a
   * key/value pair like any parameter: "set-of-6-glasses" lands as $_GET['set'], and the slug of a
   * product named "Page turner" lands as $_GET['page']. Position is what separates the two — a
   * slug describes the resource, so it always precedes the identifier, never follows it.
   *
   * A valueless entry is NOT a boundary: it is a bare segment (a route code, a junk segment the
   * canonical drops anyway), never the identifier, which always carries its value (`cPath-3`).
   *
   * @param array $leftover $_GET minus the route stem, in path order.
   */
  private static function parametersAfterTheResource(array $leftover): array
  {
    $addressed = [];

    foreach (array_reverse($leftover, true) as $key => $value) {
      if ($value !== '' && !in_array($key, self::PRESENTATION_PARAMETERS, true)) {
        break;
      }

      $addressed[$key] = $value;
    }

    return $addressed;
  }

  /**
   * Tells whether a presentation value is one the shop would actually honour: the right shape,
   * and for language and currency an installed code. The installed check is skipped when the
   * service is not registered yet — the shape gate still stands.
   */
  private static function isHonouredValue(string $key, mixed $value): bool
  {
    if (!\is_string($value) || preg_match(self::PRESENTATION_VALUE_SHAPES[$key], $value) !== 1) {
      return false;
    }

    return match ($key) {
      'language' => !Registry::exists('Language') || Registry::get('Language')->exists($value),
      'currency' => !Registry::exists('Currencies') || Registry::get('Currencies')->isSet($value),
      default => true
    };
  }

  /**
   * Issues a 301 towards the canonical URL when the request spelling differs from it.
   * Comparison is done on the path only, session segment excluded, so a cookie-less
   * visitor cannot be trapped in a redirect loop.
   */
  private static function redirectIfNotCanonical(string $canonical): void
  {
    if ($canonical === '') {
      return;
    }

    $current = self::comparablePath((string)($_SERVER['REQUEST_URI'] ?? ''));
    $target = self::comparablePath($canonical);

    if ($target === '' || $target === $current) {
      return;
    }

    HTTP::redirect($canonical, 301);
  }

  /**
   * Reduces a URL to the fragment that identifies the resource: path only, without the
   * session segment. A trailing slash is NOT trimmed — it is an empty path segment, so it
   * produces a distinct URL that has to be redirected like any other spelling variation.
   */
  private static function comparablePath(string $url): string
  {
    $path = (string)parse_url($url, PHP_URL_PATH);

    return (string)preg_replace('#/' . preg_quote(session_name(), '#') . '-[^/]+#', '', $path);
  }

  /**
   * Answers 404 directly. The historical path went through a 302 towards
   * error_documents/404.php, which search engines read as a soft 404.
   */
  public static function notFound(): never
  {
    http_response_code(404);

    $document = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'error_documents/404.php';

    if (is_file($document)) {
      ob_start();
      include($document);
      $body = (string)ob_get_clean();

      // The document ships with relative asset paths; served in place they need a base.
      $base = CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'error_documents/';
      $body = str_replace('<head>', '<head>' . "\n" . '<base href="' . $base . '">', $body);

      echo $body;
    }

    exit;
  }
}
