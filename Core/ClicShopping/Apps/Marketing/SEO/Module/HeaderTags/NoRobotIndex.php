<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Module\HeaderTags;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\Marketing\SEO\SEO as SEOApp;
use ClicShopping\OM\Domains\HeaderTagsAbstract;
use ClicShopping\Sites\ClicShoppingAdmin\TemplateAdmin;
use ClicShopping\Sites\Shop\ListingParameterWitness;
use ClicShopping\Sites\Shop\Template;
use ClicShopping\Sites\Shop\UrlCanonicalizer;

class NoRobotIndex extends HeaderTagsAbstract
{
  /**
   * Presentation parameters another concern already owns, therefore never a reason to keep a page
   * out of the index. Every other key UrlCanonicalizer declares is a facet, so a 7th one added
   * tomorrow becomes noindex by default — the fail-safe direction.
   *
   * `language` belongs to the hreflang module: /…/language-fr is a legitimate canonical variant.
   * `page` is already arbitrated by UrlCanonicalizer::enforceListingBounds(), which redirects or
   * 404s the out-of-range ones; those that survive must stay crawlable.
   */
  private const INDEXABLE_PARAMETERS = ['language', 'page'];

  /**
   * The directives this module can emit, in the order they appear in the tag.
   */
  private const DIRECTIVES = ['noindex', 'nofollow', 'nosnippet', 'noarchive', 'noimageindex'];

  /**
   * Pages seeded as noindex at install time, on one criterion: a page that only exists inside a
   * session, a transaction or a form has no content to offer an engine.
   *
   * Intersected with the real page registry before being written, so an entry designating no page
   * can no longer be stored — the value inherited from the previous module held a dozen of those.
   */
  private const DEFAULT_NOINDEX_PAGES = [
    // Account and session
    'Account', 'Account/AddressBook', 'Account/AddressBookProcess', 'Account/Create',
    'Account/CreatePro', 'Account/Delete', 'Account/Edit', 'Account/Gdpr', 'Account/History',
    'Account/HistoryInfo', 'Account/LogIn', 'Account/LogInAuth', 'Account/Main',
    'Account/MyFeedBack', 'Account/MyFeedBackHistory', 'Account/Newsletters',
    'Account/NewslettersNoAccount', 'Account/Notifications', 'Account/OrderConditions',
    'Account/Password', 'Account/PasswordForgotten', 'Account/PasswordReset',
    'Account/ProductReturn', 'Account/ProductReturnHistory', 'Account/ProductReturnHistoryInfo',
    'Account/SocialLogIn', 'GuestCustomer/Create',
    // Transactional
    'Cart', 'Checkout', 'Checkout/Billing', 'Checkout/Confirmation', 'Checkout/PaymentAddress',
    'Checkout/Shipping', 'Checkout/ShippingAddress', 'Checkout/Success',
    // Forms and tools. Compare encodes an arbitrary product selection: a combinatorial URL space.
    'Info/Contact', 'Info/SSLcheck', 'Products/TellAFriend', 'Products/ReviewsWrite', 'Compare',
    'Export',
    // Internal search: one URL per keyword typed, over content the catalogue already carries.
    'Search', 'Search/Q', 'Search/AdvancedSearch'
  ];

  public mixed $lang;
  public mixed $app;
  private mixed $template;
  public mixed $group;

  /**
   * Initializes the class by setting up necessary application-wide objects,
   * loading definitions, and establishing module-specific configurations such as title,
   * description, status, and sorting order.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('SEO')) {
      Registry::set('SEO', new SEOApp());
    }

    $this->app = Registry::get('SEO');
    $this->lang = Registry::get('Language');
    $this->group = 'header_tags'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/header_tag/no_robot_index');

    $this->title = $this->app->getDef('module_header_tags_no_robot_index_title');
    $this->description = $this->app->getDef('module_header_tags_no_robot_index_description');

    if (\defined('MODULE_HEADER_TAGS_NO_ROBOT_INDEX_STATUS')) {
      $this->sort_order = MODULE_HEADER_TAGS_NO_ROBOT_INDEX_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_HEADER_TAGS_NO_ROBOT_INDEX_STATUS == 'True');
    }
  }

  /**
   * Checks if the current instance is enabled.
   *
   * @return bool True if the instance is enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * @return array The presentation parameters whose presence keeps the page out of the index.
   */
  public static function noIndexParameters(): array
  {
    return array_values(array_diff(UrlCanonicalizer::getPresentationParameters(), self::INDEXABLE_PARAMETERS));
  }

  /**
   * Whether the request being served carries a facet — one the VISITOR asked for.
   *
   * A witnessed parameter is read from the snapshot ONLY, never from $_GET as a fallback: the
   * product boxes rewrite $_GET['sort'] with their own default, so a home page nobody sorted is
   * served with {"sort":"5a"}. Falling back there noindexes the whole shop (measured 2026-08-05).
   * $_GET is the right source for the parameters no listing can judge, `currency` today.
   */
  public static function carriesFacetParameter(): bool
  {
    $witnessable = ListingParameterWitness::getWitnessableParameters();

    foreach (self::noIndexParameters() as $parameter) {
      $value = \in_array($parameter, $witnessable, true)
        ? ListingParameterWitness::requested($parameter)
        : ($_GET[$parameter] ?? null);

      if (\is_string($value) && $value !== '') {
        return true;
      }
    }

    return false;
  }

  /**
   * Whether the administrator designated the current page as one to keep out of the index.
   *
   * A ticked page IS noindexed — the opposite of the DISPLAY_PAGES gate, where ticking HIDES the
   * module. Hence a distinct key name, so neither reading can be mistaken for the other.
   *
   * @param string $pages The stored selection, `;` separated, or `all`.
   * @param array|null $identifiers The page to judge; defaults to the current one.
   */
  public static function designatesNoIndexPage(string $pages, ?array $identifiers = null): bool
  {
    $pages = trim($pages);

    if ($pages === '') {
      return false;
    }

    if ($pages === 'all') {
      return true;
    }

    // Both sides on `/`: the stored value uses `&` or `/` depending on whether SEO friendly URLs
    // were on when it was saved.
    $selected = array_filter(
      array_map('trim', explode(';', str_replace('&', '/', $pages))),
      static fn(string $page): bool => $page !== ''
    );

    return \count(array_intersect($selected, $identifiers ?? Template::getCurrentPageIdentifiers())) > 0;
  }

  /**
   * Assembles the content of the robots tag. An empty string means no tag at all.
   *
   * @param array $flags The five directives, keyed by name.
   * @param bool $page_designated The administrator listed this page.
   * @param bool $facet_present The request carries a facet.
   */
  public static function robotsDirectives(array $flags, bool $page_designated, bool $facet_present): string
  {
    $directives = [];

    if (!empty($flags['noindex']) || $page_designated || $facet_present) {
      $directives[] = 'noindex';
    }

    if (!empty($flags['nofollow'])) {
      $directives[] = 'nofollow';
    } elseif ($directives !== []) {
      // Preserves the `noindex,follow` the shop has always emitted: the links of a page kept out
      // of the index still lead to pages that belong in it.
      $directives[] = 'follow';
    }

    foreach (['nosnippet', 'noarchive', 'noimageindex'] as $directive) {
      if (!empty($flags[$directive])) {
        $directives[] = $directive;
      }
    }

    return implode(',', $directives);
  }

  /**
   * Assembles the content of the googlebot tag, which stays GLOBAL: it inherits neither the page
   * selection nor the facets. Its whole point is to give Google rules that DIFFER from the others.
   *
   * @param array $flags The five directives, keyed by name.
   */
  public static function googlebotDirectives(array $flags): string
  {
    $directives = [];

    foreach (self::DIRECTIVES as $directive) {
      if (!empty($flags[$directive])) {
        $directives[] = $directive;
      }
    }

    return implode(',', $directives);
  }

  /**
   * Emits at most two tags, each at most once: the shop's robots directives, and the googlebot
   * override when the administrator set one. No directive to state means no tag at all.
   *
   * @return string The meta tags, empty when this page has nothing to declare.
   */
  public function getOutput()
  {
    $output = '';

    $pages = \defined('MODULE_HEADER_TAGS_NO_ROBOT_INDEX_NOINDEX_PAGES')
      ? (string)MODULE_HEADER_TAGS_NO_ROBOT_INDEX_NOINDEX_PAGES
      : '';

    $robots = self::robotsDirectives(
      $this->directiveFlags('MODULE_HEADER_TAGS_NO_ROBOT_INDEX_'),
      self::designatesNoIndexPage($pages),
      self::carriesFacetParameter()
    );

    if ($robots !== '') {
      $output .= '<meta name="robots" content="' . $robots . '">' . "\n";
    }

    $googlebot = self::googlebotDirectives($this->directiveFlags('MODULE_HEADER_TAGS_NO_ROBOT_INDEX_GOOGLEBOT_'));

    if ($googlebot !== '') {
      $output .= '<meta name="googlebot" content="' . $googlebot . '">' . "\n";
    }

    return $output;
  }

  /**
   * Reads the five directive switches sharing a prefix.
   *
   * @param string $prefix The constant prefix, robots or googlebot.
   * @return array directive => enabled.
   */
  private function directiveFlags(string $prefix): array
  {
    $flags = [];

    foreach (self::DIRECTIVES as $directive) {
      $key = $prefix . strtoupper($directive);
      $flags[$directive] = \defined($key) && constant($key) == 'True';
    }

    return $flags;
  }

  /**
   * The seeded selection, kept to the pages the registry actually knows.
   *
   * @return string The `;` terminated value, in the shape clic_cfg_set_select_pages_list writes.
   */
  public function defaultNoIndexPages(): string
  {
    $registry = [];

    foreach (TemplateAdmin::getCatalogFiles() as $page) {
      $registry[str_replace('&', '/', $page)] = $page;
    }

    $value = '';

    foreach (self::DEFAULT_NOINDEX_PAGES as $page) {
      if (isset($registry[$page])) {
        $value .= $registry[$page] . ';';
      }
    }

    return $value;
  }

  /**
   * Installs the module by saving configuration settings into the database.
   *
   * @return void
   */
  public function install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to install this module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Display sort order',
        'configuration_key' => 'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_SORT_ORDER',
        'configuration_value' => '45',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Pages which must not be indexed',
        'configuration_key' => 'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_NOINDEX_PAGES',
        'configuration_value' => $this->defaultNoIndexPages(),
        'configuration_description' => 'A ticked page is served with a noindex tag',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_select_pages_list',
        'date_added' => 'now()'
      ]
    );

    $sort_order = 3;

    foreach ($this->directiveSettings() as $key => $wording) {
      $this->app->db->save('configuration', [
          'configuration_title' => $wording['title'],
          'configuration_key' => $key,
          'configuration_value' => 'False',
          'configuration_description' => $wording['description'],
          'configuration_group_id' => '6',
          'sort_order' => (string)$sort_order++,
          'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
          'date_added' => 'now()'
        ]
      );
    }
  }

  /**
   * The ten directive switches, five for every engine and five for Googlebot alone.
   *
   * @return array configuration key => title and description.
   */
  private function directiveSettings(): array
  {
    return [
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_NOINDEX' => [
        'title' => 'Do you want to activate the noindex meta ?',
        'description' => 'If you activate this meta, the whole shop is kept out of the index'
      ],
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_NOFOLLOW' => [
        'title' => 'Do you want to activate the nofollow meta ?',
        'description' => 'If you activate this meta, your links are not followed'
      ],
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_NOSNIPPET' => [
        'title' => 'Do you want to activate the nosnippet meta ?',
        'description' => 'If you activate this meta, no description is shown under the title in the results'
      ],
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_NOARCHIVE' => [
        'title' => 'Do you want to activate the noarchive meta ?',
        'description' => 'If you activate this meta, your content is not archived'
      ],
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_NOIMAGEINDEX' => [
        'title' => 'Do you want to activate the noimageindex meta ?',
        'description' => 'If you activate this meta, your images are not indexed'
      ],
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_GOOGLEBOT_NOINDEX' => [
        'title' => 'Googlebot only : noindex ?',
        'description' => 'Applies to Googlebot only, on every page, whatever the selection above'
      ],
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_GOOGLEBOT_NOFOLLOW' => [
        'title' => 'Googlebot only : nofollow ?',
        'description' => 'Applies to Googlebot only, on every page'
      ],
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_GOOGLEBOT_NOSNIPPET' => [
        'title' => 'Googlebot only : nosnippet ?',
        'description' => 'Applies to Googlebot only, on every page'
      ],
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_GOOGLEBOT_NOARCHIVE' => [
        'title' => 'Googlebot only : noarchive ?',
        'description' => 'Applies to Googlebot only, on every page'
      ],
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_GOOGLEBOT_NOIMAGEINDEX' => [
        'title' => 'Googlebot only : noimageindex ?',
        'description' => 'Applies to Googlebot only, on every page'
      ]
    ];
  }

  /**
   * Removes configuration entries from the database table by executing a delete query.
   *
   * This method constructs a SQL DELETE statement to remove rows from the
   * :table_configuration table based on the provided configuration keys.
   *
   * @return int|bool Returns the number of affected rows if the query is executed successfully,
   *                  or false on failure.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Every key install() writes, so remove() takes them all back. The directive keys are derived
   * from the very list install() iterates: ht_robots wrote nine keys and declared eight, and its
   * MODULE_HEADER_TAGS_ROBOTS_NOINDEX_ON outlived every uninstall.
   *
   * @return array The 13 configuration keys of this module.
   */
  public function keys()
  {
    return array_merge([
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_STATUS',
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_SORT_ORDER',
      'MODULE_HEADER_TAGS_NO_ROBOT_INDEX_NOINDEX_PAGES'
    ], array_keys($this->directiveSettings()));
  }
}
