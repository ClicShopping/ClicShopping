<?php
/**
 *
 */

namespace ClicShopping\Apps\Communication\PageManager\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;
/**
 * RSS Class.
 * Handles RSS feed-related functionality, such as generating namespace links,
 * setting site titles, descriptions, and retrieving feed configurations.
 */
class RSS
{

  protected int $products_id;
  protected string $site_name;
  private mixed $db;
  private mixed $lang;
  protected $navigationHistory;
  protected $rewriteUrl;

  /**
   * Constructor
   *
   * @param string $xmlns XML namespace
   * @param string $site_name the name of your site
   */
  public function __construct()
  {
    $this->site_name = HTML::outputProtected(STORE_NAME);
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');
    $this->navigationHistory = Registry::get('NavigationHistory');
    $this->rewriteUrl = Registry::get('RewriteUrl');
  }

  /**
   * Generates a string containing multiple XML namespace declarations.
   * The returned string includes a range of XML namespaces commonly used in RSS feeds, RDF data, and various web standards.
   *
   * @return string A formatted string of XML namespace declarations, or an empty string if none are available.
   */
  public function xmlns(): string
  {
// set more namespaces if you need them

    $xmlns = '
                xmlns:access="http://www.bloglines.com/about/specs/fac-1.0"
                xmlns:admin="http://webns.net/mvcb/"
                xmlns:ag="http://purl.org/rss/1.0/modules/aggregation/"
                xmlns:annotate="http://purl.org/rss/1.0/modules/annotate/"
                xmlns:app="http://www.w3.org/2007/app"
                xmlns:atom="http://www.w3.org/2005/Atom"
                xmlns:audio="http://media.tangent.org/rss/1.0/"
                xmlns:blogChannel="http://backend.userland.com/blogChannelModule"
                xmlns:cc="http://web.resource.org/cc/"
                xmlns:cf="http://www.microsoft.com/schemas/rss/core/2005"
                xmlns:company="http://purl.org/rss/1.0/modules/company"
                xmlns:content="http://purl.org/rss/1.0/modules/content/"
                xmlns:conversationsNetwork="http://conversationsnetwork.org/rssNamespace-1.0/"
                xmlns:cp="http://my.theinfo.org/changed/1.0/rss/"
                xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:dcterms="http://purl.org/dc/terms/"
                xmlns:email="http://purl.org/rss/1.0/modules/email/"
                xmlns:ev="http://purl.org/rss/1.0/modules/event/"
                xmlns:feedburner="http://rssnamespace.org/feedburner/ext/1.0"
                xmlns:fh="http://purl.org/syndication/history/1.0"
                xmlns:foaf="http://xmlns.com/foaf/0.1/"
                xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#"
                xmlns:georss="http://www.georss.org/georss"
                xmlns:geourl="http://geourl.org/rss/module/"
                xmlns:g="http://base.google.com/ns/1.0"
                xmlns:gml="http://www.opengis.net/gml"
                xmlns:icbm="http://postneo.com/icbm"
                xmlns:image="http://purl.org/rss/1.0/modules/image/"
                xmlns:indexing="urn:atom-extension:indexing"
                xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
                xmlns:kml20="http://earth.google.com/kml/2.0"
                xmlns:kml21="http://earth.google.com/kml/2.1"
                xmlns:kml22="http://www.opengis.net/kml/2.2"
                xmlns:l="http://purl.org/rss/1.0/modules/link/"
                xmlns:mathml="http://www.w3.org/1998/Math/MathML"
                xmlns:media="http://search.yahoo.com/mrss/"
                xmlns:openid="http://openid.net/xmlns/1.0"
                xmlns:opensearch10="http://a9.com/-/spec/opensearchrss/1.0/"
                xmlns:opensearch="http://a9.com/-/spec/opensearch/1.1/"
                xmlns:opml="http://www.opml.org/spec2"
                xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
                xmlns:ref="http://purl.org/rss/1.0/modules/reference/"
                xmlns:reqv="http://purl.org/rss/1.0/modules/richequiv/"
                xmlns:rss090="http://my.netscape.com/rdf/simple/0.9/"
                xmlns:rss091="http://purl.org/rss/1.0/modules/rss091#"
                xmlns:rss1="http://purl.org/rss/1.0/"
                xmlns:rss11="http://purl.org/net/rss1.1#"
                xmlns:search="http://purl.org/rss/1.0/modules/search/"
                xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
                xmlns:ss="http://purl.org/rss/1.0/modules/servicestatus/"
                xmlns:str="http://hacks.benhammersley.com/rss/streaming/"
                xmlns:sub="http://purl.org/rss/1.0/modules/subscription/"
                xmlns:svg="http://www.w3.org/2000/svg"
                xmlns:sx="http://feedsync.org/2007/feedsync"
                xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
                xmlns:taxo="http://purl.org/rss/1.0/modules/taxonomy/"
                xmlns:thr="http://purl.org/rss/1.0/modules/threading/"
                xmlns:trackback="http://madskills.com/public/xml/rss/module/trackback/"
                xmlns:wfw="http://wellformedweb.org/CommentAPI/"
                xmlns:wiki="http://purl.org/rss/1.0/modules/wiki/"
                xmlns:xhtml="http://www.w3.org/1999/xhtml"
                xmlns:xlink="http://www.w3.org/1999/xlink"
                xmlns:xrd="xri://$xrd*($v*2.0)"
                xmlns:xrds="xri://$xrds"
                ';

    $xmlns = $xmlns ? ' ' . $xmlns : '';

    return $xmlns;
  }

  /**
   * Sets the title based on SEO data from the database.
   *
   * This method retrieves the title from the database for a specific language ID.
   * If the language-specific title is not found, it uses a default title instead.
   *
   * @return string The title of the page based on the SEO data or a default value.
   */
  private function setTitle(): string
  {
    $Qsubmit = $this->db->prepare('select seo_id,
                                            language_id,
                                            seo_defaut_language_title
                                    from :table_seo
                                    where seo_id = 1
                                    and language_id = :language_id
                                    limit 1
                                    ');
    $Qsubmit->bindInt(':language_id', $this->lang->getId());
    $Qsubmit->execute();

    if ($Qsubmit->fetch() !== false) {
      $title = STORE_NAME;
    } else {
      $title = HTML::sanitize($Qsubmit->value('seo_defaut_language_title'));
    }

    return $title;
  }

  /**
   * Retrieves and sets the description based on the current language and SEO configuration.
   *
   * @return string The description retrieved from the database or a sanitized fallback value.
   */
  private function setDescription(): string
  {
    $Qsubmit = $this->db->prepare('select seo_id,
                                            language_id,
                                            seo_defaut_language_description
                                    from :table_seo
                                    where seo_id = 1
                                    and language_id = :language_id
                                    limit 1
                                    ');
    $Qsubmit->bindInt(':language_id', (int)$this->lang->getId());
    $Qsubmit->execute();

    if ($Qsubmit->fetch() !== false) {
      $description = STORE_NAME;
    } else {
      $description = HTML::sanitize($Qsubmit->value('seo_defaut_language_description'));
    }

    return $description;
  }

  /**
   * Retrieves the maximum number of items for a listing.
   *
   * @param int $number_of_item The number of items to be listed. Defaults to 20.
   * @return int The maximum number of items for the listing.
   */
  public function getMaxListing(int $number_of_item = 20): int
  {
    return $number_of_item;
  }

  /**
   * Retrieves a list of RSS products based on specific filters and conditions.
   * Removes the current page from the navigation history and fetches product details,
   * including image, name, description, manufacturer information, and timestamps.
   * The query is adjusted depending on whether stock checking is enabled or disabled,
   * and results are ordered by the product's addition date in descending order.
   *
   * @return array Returns an array of products matching the criteria.
   */
  public function setListRSS(): array
  {
    $this->navigationHistory->removeCurrentPage();

// show the products
    if (STOCK_CHECK == 'true') {
      $Qlisting = $this->db->prepare('select DISTINCTROW  p.products_id,
                                                             p.products_image,
                                                              pd.products_name,
                                                              pd.products_description,
                                                              p.manufacturers_id,
                                                              p.products_date_added,
                                                              p.products_last_modified,
                                                              m.manufacturers_name
                                        from :table_products p left join :table_manufacturers  m on p.manufacturers_id = m.manufacturers_id
                                                                left join :table_specials s on p.products_id = s.products_id,
                                             :table_products_to_categories p2c,
                                             :table_products_description pd
                                        where p.products_status = 1
                                        and p.products_id = pd.products_id
                                        and p.products_view = 1
                                        and p.products_id = p2c.products_id
                                        and p.products_archive = 0
                                        and p.products_quantity > 0
                                        and language_id = :language_id
                                        order by p.products_date_added desc
                                        limit :limit
                                        ');
    } else {
      $Qlisting = $this->db->prepare('select DISTINCTROW p.products_id,
                                                            p.products_image,
                                                            pd.products_name,
                                                            pd.products_description,
                                                            p.manufacturers_id,
                                                            p.products_date_added,
                                                            p.products_last_modified,
                                                            m.manufacturers_name
                                      from  :table_products p left join :table_manufacturers  m on p.manufacturers_id = m.manufacturers_id
                                                             left join :table_specials s on p.products_id = s.products_id,
                                           :table_products_to_categories p2c,
                                           :table_products_description pd
                                      where p.products_status = 1
                                      and p.products_id = pd.products_id
                                      and p.products_view = 1
                                      and p.products_id = p2c.products_id
                                      and p.products_archive = 0
                                      and language_id = :language_id
                                      order by p.products_date_added desc
                                      limit :limit
                                     ');
    }

    $Qlisting->bindInt(':limit', $this->getMaxListing());
    $Qlisting->bindInt(':language_id', (int)$this->lang->getId());

    $Qlisting->execute();

    return $Qlisting->fetchAll();
  }

  /**
   * Calculates and returns the total count of RSS items.
   *
   * @return int The total number of RSS items.
   */
  public function countRSS(): int
  {
    $countRSS = \count($this->setListRSS());
    return $countRSS;
  }

  /**
   * Retrieves the date when the most recently modified product with active status and view status was added.
   *
   * @return string The date the product was added.
   */
  public function productDateAdded(): string
  {
    $Qproducts = $this->db->prepare('select products_date_added
                                        from :table_products
                                        where products_status = 1
                                        and products_view = 1
                                        order by products_last_modified desc
                                        limit 1
                                       ');

    $Qproducts->execute();

    return $Qproducts->value('products_date_added');
  }

  /**
   * Generates and returns a properly formatted RSS feed in XML format.
   *
   * The method constructs the complete RSS feed containing the required
   * channel properties, image, and items dynamically populated based on
   * the data available. It ensures the RSS feed adheres to XML syntax and
   * RSS standards.
   *
   * @return string The generated RSS feed as an XML string.
   */
  public function displayFeed(): string
  {
    $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
    $xml .= '<?xml-stylesheet href="https://www.w3.org/2000/08/w3c-synd/style.css" type="text/css"?>' . "\n";
    $xml .= '<rss version="2.0" ' . "\n";
    $xml .= $this->xmlns();
    $xml .= '>' . "\n";
// channel required properties
    $xml .= '<channel>' . "\n";
    $xml .= '<title>' . $this->setTitle() . '</title>' . "\n";
    $xml .= '<link>' . HTTP::typeUrlDomain() . '</link>' . "\n";
    $xml .= '<description>' . $this->setDescription() . '</description>' . "\n";
    $xml .= '<copyright>' . $this->setTitle() . '</copyright>' . "\n";


    $link_atom = CLICSHOPPING::link(null, 'Info&Rss');
    $link_atom = str_replace('&', '&amp;', $link_atom);

    $xml .= '<atom:link href="' . $link_atom . '" type="application/rss+xml"/>' . "\n";
    $xml .= '<language>' . $_SESSION['language'] . '</language>' . "\n";
    $xml .= '<image>' . "\n";
    $xml .= '<title>' . $this->setTitle() . '</title>' . "\n";
    $xml .= '<link>' . HTTP::typeUrlDomain() . '</link>' . "\n";
    $xml .= '<url>' . HTTP::getShopUrlDomain() . 'sources/images/icons/icon_feed.gif' . '</url>' . "\n";
    $xml .= '</image>' . "\n";

    $xml .= '<docs>https://blogs.law.harvard.edu/tech/rss</docs>' . "\n";

// get RSS channel items
    $rss_item = $this->setListRSS();

    for ($i = 0, $n = \count($rss_item); $i < $n; $i++) {
      $products_id = $rss_item[$i]['products_id'];
      $date_added = date('Y-m-d', strtotime($rss_item[$i]['products_date_added']));

      $name = strip_tags($rss_item[$i]['products_name']);
      $description = strip_tags($rss_item[$i]['products_description']);
      $description = HTMLOverrideCommon::cleanHtmlOptimized($description);

      $url = $this->rewriteUrl->getProductNameUrl($products_id);

// <link>/<guid> are not wrapped in CDATA: escape the ampersands of the URL.
      $link = str_replace('&', '&amp;', $url);

// Title and description are emitted inside CDATA so &, < and > are taken literally.
// Only a stray "]]>" sequence could close the section early, so split it.
      $name = str_replace(']]>', ']]]]><![CDATA[>', $name);
      $description = str_replace(']]>', ']]]]><![CDATA[>', $description);

      $xml .= '<item>' . "\n";
      $xml .= '<title><![CDATA[' . $name . ']]></title>' . "\n";
      $xml .= '<link>' . $link . '</link>' . "\n";
      $xml .= '<description><![CDATA[' . $description . ']]></description>' . "\n";

      $xml .= '<pubDate>' . gmdate("D, d M Y H:i:s", strtotime($date_added)) . ' GMT' . '</pubDate>' . "\n";
      $xml .= '<guid>' . $link . '</guid>' . "\n";
      $xml .= '</item>' . "\n";
    }

    $xml .= '</channel>' . "\n";
    $xml .= '</rss>';

    return $xml;
  }
}