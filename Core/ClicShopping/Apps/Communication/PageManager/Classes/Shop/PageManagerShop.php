<?php
/**
 *
 */

namespace ClicShopping\Apps\Communication\PageManager\Classes\Shop;

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
/**
 * PageManagerShop is a class that provides functionality to interact with
 * and manage pages displayed in various sections of the ClicShopping shop front-end.
 */
class PageManagerShop
{
  protected int $id;
  private mixed $db;
  protected mixed $customer;
  private mixed $lang;
  protected mixed $rewriteUrl;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->customer = Registry::get('Customer');
    $this->lang = Registry::get('Language');
    $this->rewriteUrl = Registry::get('RewriteUrl');
  }

  /**
   * Retrieves the count of pages from the pages manager table where the status is active and the page type is 1.
   *
   * @return int The number of pages meeting the specified criteria.
   */
  private function pageManagerDisplayPageIntroCount(): int
  {
    $Qpages = $this->db->prepare('select count(*) as count
                                     from :table_pages_manager
                                     where status = 1
                                     and page_type = 1
                                    ');
    $Qpages->execute();

    return $Qpages->valueInt('count');
  }

  /**
   * Retrieves the display time for the introductory page in the page manager.
   *
   * This method queries the database for a random active introductory page
   * and retrieves its associated display time. If no introductory pages are
   * available, a default time of 0 is returned.
   *
   * @return int The display time for the introductory page, or 0 if no pages are available.
   */
  public function pageManagerDisplayPageIntroTime(): int
  {
    if ($this->pageManagerDisplayPageIntroCount() > 0) {
      $Qpages = $this->db->prepare('select p.pages_id,
                                               p.page_time
                                        from :table_pages_manager p
                                        where p.status = 1
                                        and p.page_type = 1
                                        order by rand()
                                       ');
      $Qpages->execute();

      $pages = [
        'pages_id' => $Qpages->valueInt('pages_id'),
        'page_time' => $Qpages->value('page_time')
      ];
    } else {
      $pages = [
        'pages_id' => 0,
        'page_time' => 0
      ];
    }

    return $pages['page_time'];
  }

  /**
   * Retrieves and returns the introductory HTML content of a page managed by the page manager.
   *
   * This method checks if there are any active pages available for display as intros.
   * If available, it randomly selects one active page with a specified language ID and page type,
   * retrieves its HTML content, and returns it. If no pages are available, it returns an empty string.
   *
   * @return string The HTML content of the page intro, or an empty string if no intro is available.
   */
  public function pageManagerDisplayPageIntro(): string
  {
    if ($this->pageManagerDisplayPageIntroCount() > 0) {
      $Qpages = $this->db->prepare('select p.pages_id,
                                              s.pages_html_text
                                       from :table_pages_manager p,
                                            :table_pages_manager_description s
                                       where p.status = 1
                                       and p.pages_id = s.pages_id
                                       and p.page_type = 1
                                       and s.language_id= :language_id
                                       order by rand()
                                       limit 1
                                      ');
      $Qpages->bindValue(':language_id', (int)$this->lang->getId());
      $Qpages->execute();

      $pages = ['pages_id' => $Qpages->valueInt('pages_id'),
        'pages_html_text' => $Qpages->value('pages_html_text')
      ];
    } else {
      $pages = [
        'pages_id' => 0,
        'pages_html_text' => ''
      ];
    }

    return $pages['pages_html_text'];
  }

  /**
   * Retrieves and returns the HTML content of a front page from the page manager.
   *
   * This method first checks if there are any active front page entries (page_type = 2).
   * If entries are found, it randomly selects one page and retrieves its associated
   * HTML content (along with its ID). It ensures that the selected page matches the
   * specified language and customer group or falls back to default values.
   *
   * If no entries are found, it returns default values with an empty HTML content
   * and page ID set to 0.
   *
   * @return string The HTML content of the randomly selected front page or an empty string if no pages are available.
   */
  public function pageManagerDisplayFrontPage(): string
  {
    $Qpages = $this->db->prepare('select count(*) as count
                                     from :table_pages_manager
                                     where status = 1
                                     and page_type = 2
                                   ');

    $Qpages->execute();

    if ($Qpages->valueInt('count') > 0) {
      $Qpages = $this->db->prepare('select p.pages_id,
                                              s.pages_html_text
                                       from :table_pages_manager p,
                                            :table_pages_manager_description s
                                       where p.status = 1
                                       and p.page_type = 2
                                       and p.pages_id = s.pages_id
                                       and (s.language_id  = :language_id or
                                            s.language_id = 0)
                                       and (p.customers_group_id = :customers_group_id or
                                            p.customers_group_id = 99
                                           )
                                       order by rand()
                                       limit 1
                                     ');

      $Qpages->bindInt(':language_id', (int)$this->lang->getId());
      $Qpages->bindInt(':customers_group_id', (int)$this->customer->getCustomersGroupID());
      $Qpages->execute();

      $pages = [
        'pages_id' => $Qpages->valueInt('pages_id'),
        'pages_html_text' => $Qpages->value('pages_html_text')
      ];
    } else {
      $pages = [
        'pages_id' => 0,
        'pages_html_text' => ''
      ];
    }

    return $pages['pages_html_text'];
  }

  /**
   *
   */
  public function pageManagerDisplayContact()
  {
    $Qpages = $this->db->prepare('select  p.pages_id,
                                             s.pages_html_text
                                     from :table_pages_manager p,
                                          :table_pages_manager_description s
                                     where p.pages_id = s.pages_id
                                     and p.status = 1
                                     and p.page_type = 3
                                     and (s.language_id  = :language_id or s.language_id  = 0)
                                     and (p.customers_group_id = :customers_group_id  or p.customers_group_id = 99)
                                  ');

    $Qpages->bindInt(':language_id', (int)$this->lang->getId());
    $Qpages->bindInt(':customers_group_id', (int)$this->customer->getCustomersGroupID());

    $Qpages->execute();

    if ($Qpages->fetch() !== true) {
      $pages = [
        'pages_id' => $Qpages->valueInt('pages_id'),
        'pages_html_text' => $Qpages->value('pages_html_text')
      ];
    } else {
      $pages = [
        'pages_id' => 0,
        'pages_html_text' => ''
      ];
    }

    return $pages['pages_html_text'];
  }

  /**
   * Generates and retrieves the HTML display box for the page manager.
   *
   * This method queries the database to fetch a list of active pages for display purposes.
   * Based on the retrieved data, it dynamically generates an HTML structure by including links
   * and formatting based on the provided parameters. The resulting HTML string can be used
   * to display page manager content in a box layout.
   *
   * @param string $start_class The starting HTML wrapper, defaulting to '<div class="pageManagerDisplayBox">'.
   * @param string $end_class The ending HTML wrapper, defaulting to '</div>'.
   * @param string $separation A string that separates the individual contents of the box, defaulting to an empty string.
   * @return string The generated HTML content for the page manager display box.
   */
  public function pageManagerDisplayBox($start_class = '<div class="pageManagerDisplayBox">', $end_class = '</div>', $separation = ''): string
  {
    $QPage = $this->db->prepare('select  p.pages_id,
                                            p.sort_order,
                                            p.status,
                                            p.page_box,
                                            s.pages_title,
                                            s.pages_html_text,
                                            s.externallink,
                                            p.links_target
                                     from :table_pages_manager p,
                                          :table_pages_manager_description s
                                     where p.pages_id = s.pages_id
                                     and p.status = 1
                                     and (p.page_type = 4 or p.page_type = 3)
                                     and (s.language_id  = :language_id or s.language_id  = 0)
                                     and p.page_box = 0
                                     and (p.customers_group_id = :customers_group_id  or p.customers_group_id = 99)
                                     order by p.sort_order, s.pages_title
                                    ');

    $QPage->bindInt(':language_id', $this->lang->getId());
    $QPage->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

    $QPage->setCache('boxe_page_manager_primary-lang' . $this->lang->getId());

    $QPage->execute();

    $separ = $separation;

    $page_liste_box = $start_class;

    while ($QPage->fetch() !== false) {
      if (!empty($QPage->value('externallink'))) {
        $search = str_contains($QPage->value('externallink'), 'index.php');

        if ($search === false) {
          $page_liste_box .= '<span class="InformationFooter">' . HTML::link($QPage->value('externallink'), $QPage->value('pages_title'), 'target="' . $QPage->value('links_target') . ' rel="noreferrer" title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"') . '</span>';
        } else {
          $page_liste_box .= '<span class="InformationFooter">' . HTML::link(CLICSHOPPING::link($QPage->value('externallink')), $QPage->value('pages_title'), 'target="' . $QPage->value('links_target') . '" title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"') . '</span>' . '<br />';
        }
      } else {
        if ($QPage->valueInt('pages_id') != 3) {
          $link = $this->rewriteUrl->getPageManagerContentUrl($QPage->valueInt('pages_id'));
        } else {
          $link = CLICSHOPPING::link(null, 'Info&Contact');
        }

        if (!empty($QPage->value('pages_title'))) {
          $search = str_contains($QPage->value('externallink'), 'index.php');

          if ($search === false) {
            $page_liste_box .= $start_class . $separ . HTML::link($link, $QPage->value('pages_title'), 'target="' . $QPage->value('links_target') . '" rel="noreferrer" title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"') . $end_class;
          } else {
            $page_liste_box .= $start_class . $separ . HTML::link($link, $QPage->value('pages_title'), 'target="' . $QPage->value('links_target') . '" title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"') . $end_class . '<br />';
          }
        }
      }
    }

    $page_liste_box .= '</div>';
    $page = ['text' => $page_liste_box];

    return $page['text'];
  }

  /**
   * Generates and retrieves the HTML for the secondary box display in the page manager.
   *
   * This method queries the database for active pages of specific types that are part of
   * the secondary box display, then formats them into an HTML string with optional
   * customization for start and end classes, as well as separation between items.
   * The results include internal or external links formatted with appropriate attributes.
   *
   * @param string $start_class The HTML markup to use at the start of the secondary box display.
   * @param string $end_class The HTML markup to use at the end of the secondary box display.
   * @param string $separation The HTML or text separator to use between items in the secondary box.
   * @return string The generated HTML for the secondary box display.
   */
  public function pageManagerDisplaySecondaryBox($start_class = '<div class="pageManagerDisplaySecondaryBox">', $end_class = '</div>', $separation = '|'): string
  {
    $QPageSecondary = $this->db->prepare('select SQL_CALC_FOUND_ROWS p.pages_id,
                                                                        p.sort_order,
                                                                        p.status,
                                                                        p.page_box,
                                                                        s.pages_title,
                                                                        s.pages_html_text,
                                                                        s.externallink,
                                                                        p.links_target
                                           from :table_pages_manager p,
                                                :table_pages_manager_description s
                                           where p.pages_id = s.pages_id
                                           and p.status = 1
                                           and (p.page_type = 4 or p.page_type = 3)
                                           and (s.language_id  = :language_id or s.language_id  = 0)
                                           and p.page_box = 1
                                           and (p.customers_group_id = :customers_group_id  or p.customers_group_id = 99)
                                           order by p.sort_order,
                                                    s.pages_title
                                        ');

    $QPageSecondary->bindInt(':language_id', $this->lang->getId());
    $QPageSecondary->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

    $QPageSecondary->setCache('boxe_page_manager_secondary-' . $this->lang->getId());

    $QPageSecondary->execute();

    $TotalRow = $QPageSecondary->getPageSetTotalRows();

    if ($TotalRow > 0) {
      $rows = 0;
      $page_liste_box_secondary = $start_class;

      while ($QPageSecondary->fetch()) {
        $rows++;

        if ($rows != 1) {
          $separ = $separation;
        } else {
          $separ = '';
        }

        if (!empty($QPageSecondary->value('externallink'))) {
          $search = str_contains($QPageSecondary->value('externallink'), 'index.php');

          if ($search === false) {
            $page_liste_box_secondary .= '<span class="SecondaryBoxInformation">' . HTML::link($QPageSecondary->value('externallink'), $QPageSecondary->value('pages_title'), 'target="' . $QPageSecondary->value('links_target') . '" rel="noreferrer" title="' . $QPageSecondary->value('pages_title') . '"  id="' . $QPageSecondary->value('pages_title') . '"') . '</span>';
          } else {
            $page_liste_box_secondary .= '<span class="SecondaryBoxInformation">' . HTML::link(CLICSHOPPING::link($QPageSecondary->value('externallink')), $QPageSecondary->value('pages_title'), 'target="' . $QPageSecondary->value('links_target') . '" title="' . $QPageSecondary->value('pages_title') . '"  id="' . $QPageSecondary->value('pages_title') . '"') . '</span><br />';
          }
        } else {
          if ($QPageSecondary->valueInt('pages_id') != 3) {
            $link_secondary = $this->rewriteUrl->getPageManagerContentUrl($QPageSecondary->valueInt('pages_id'));
          } else {
            $link_secondary = CLICSHOPPING::link(null, 'Info&Contact');
          }

          if (!empty($QPageSecondary->value('pages_title'))) {
            $search = str_contains($QPageSecondary->value('externallink'), 'index.php');

            if ($search === false) {
              $page_liste_box_secondary .= $start_class . $separ . HTML::link($link_secondary, $QPageSecondary->value('pages_title'), 'target="' . $QPageSecondary->value('links_target') . '" rel="noreferrer" title="' . $QPageSecondary->value('pages_title') . '"  id="' . $QPageSecondary->value('pages_title') . '"') . $end_class;
            } else {
              $page_liste_box_secondary .= $start_class . $separ . HTML::link($link_secondary, $QPageSecondary->value('pages_title'), 'target="' . $QPageSecondary->value('links_target') . '" title="' . $QPageSecondary->value('pages_title') . '"  id="' . $QPageSecondary->value('pages_title') . '"') . $end_class . '<br />';
            }
          }
        }
      }

      $page_liste_box_secondary .= '</div>';

      $pages_liste_info_box_secondary = ['text' => $page_liste_box_secondary];

      return $pages_liste_info_box_secondary['text'];
    }
  }

  /**
   * Generates the header menu from the page manager.
   *
   * This method retrieves a list of active pages of a specific type from the database
   * and constructs a formatted header menu. It applies specified start and end classes
   * as well as a separation string between menu items. It also respects external link configurations
   * and includes relevant attributes for links.
   *
   * @param string $start_class The HTML class or tag to append at the start of each menu item.
   * @param string $end_class The HTML class or tag to append at the end of each menu item.
   * @param string $separation The string or character used to separate menu items.
   * @return string The constructed header menu as an HTML-formatted string.
   */
  public function pageManagerDisplayHeaderMenu($start_class = '<span class="menuHeaderPageManager">', $end_class = '</span>', $separation = '|'): string
  {
    $QPage = $this->db->prepare('select  p.pages_id,
                                            p.sort_order,
                                            p.status,
                                            p.page_box,
                                            s.pages_title,
                                            s.externallink,
                                            p.links_target
                                     from :table_pages_manager p,
                                          :table_pages_manager_description s
                                     where p.pages_id = s.pages_id
                                     and p.status = 1
                                     and p.page_type = 5
                                     and (s.language_id  = :language_id or s.language_id  = 0)
                                     and (p.customers_group_id = :customers_group_id  or p.customers_group_id = 99)
                                     order by p.sort_order, s.pages_title
                                    ');

    $QPage->bindInt(':language_id', (int)$this->lang->getId());
    $QPage->bindInt(':customers_group_id', (int)$this->customer->getCustomersGroupID());

    $QPage->setCache('page_manager_display_header_menu-lang' . $this->lang->getId());

    $QPage->execute();

    $rows = 0;
    $page_menu_header = '';

//***********************************
// -------- menu header -----------
//***********************************
    while ($QPage->fetch() !== false) {
      $rows++;

      if ($rows != 1) {
        $separ = $separation;
      } else {
        $separ = '';
      }

      if (!empty($QPage->value('externallink'))) {
        $search = (str_contains($QPage->value('externallink'), 'index.php'));
        $search1 = (str_contains($QPage->value('externallink'), 'http'));

        if ($search === true) {
          $page_menu_header .= $start_class . $separ;
          $page_menu_header .= HTML::link(CLICSHOPPING::link(null, null), $QPage->value('pages_title'), 'target="' . $QPage->value('links_target') . '" class="menuHeaderPageManager" rel="noreferrer"  title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"');
          $page_menu_header .= $end_class;
        } elseif ($search1 === true) {
          $page_menu_header .= $start_class . $separ;
          $page_menu_header .= HTML::link($QPage->value('externallink'), $QPage->value('pages_title'), 'class="menuHeaderPageManager" target="' . $QPage->value('links_target') . '" title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"');
          $page_menu_header .= $end_class;
        } else {
          $page_menu_header .= $start_class . $separ;
          $page_menu_header .= HTML::link(CLICSHOPPING::link(null, $QPage->value('externallink')), $QPage->value('pages_title'), 'target="' . $QPage->value('links_target') . '" class="menuHeaderPageManager" rel="noreferrer"  title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"');
          $page_menu_header .= $end_class;
        }
      }
    }

    $pages = ['text' => $page_menu_header];

    return $pages['text'];
  }

  /**
   * Generates the footer menu for the page manager.
   *
   * This method retrieves active footer menu items from the database based on the user's
   * language and customer group. The retrieved items are formatted with the specified
   * start class, end class, and separation string, and returned as a formatted string.
   *
   * @param string $start_class The HTML markup or text to be prepended to each menu item. Default is '<span class="menuFooterPageManager">'.
   * @param string $end_class The HTML markup or text to be appended to each menu item. Default is '</span>'.
   * @param string $separation The string to separate each menu item. Default is ' | '.
   * @return string The generated footer menu as a formatted HTML string.
   */
  public function pageManagerDisplayFooterMenu($start_class = '<span class="menuFooterPageManager">', $end_class = '</span>', $separation = ' | '): string
  {
    $QPage = $this->db->prepare('select  p.pages_id,
                                            p.sort_order,
                                            p.status,
                                            p.page_box,
                                            s.pages_title,
                                            s.externallink,
                                            p.links_target
                                     from :table_pages_manager p,
                                          :table_pages_manager_description s
                                     where p.pages_id = s.pages_id
                                     and p.status = 1
                                     and p.page_type = 6
                                     and (s.language_id  = :language_id or s.language_id  = 0)
                                     and (p.customers_group_id = :customers_group_id  or p.customers_group_id = 99)
                                     order by p.sort_order, s.pages_title
                                    ');

    $QPage->bindInt(':language_id', (int)$this->lang->getId());
    $QPage->bindInt(':customers_group_id', (int)$this->customer->getCustomersGroupID());

    $QPage->setCache('page_manager_display_footer_menu-lang' . $this->lang->getId());

    $QPage->execute();

    $rows = 0;
    $page_menu_header = '';

//***********************************
// -------- menu header -----------
//***********************************
    while ($QPage->fetch() !== false) {
      $rows++;

      if ($rows != 1) {
        $separ = $separation;
      } else {
        $separ = '';
      }

      if (!empty($QPage->value('externallink'))) {
        $search = str_contains($QPage->value('externallink'), 'index.php');

        if ($search === false) {
          $page_menu_header .= $start_class . $separ . HTML::link($QPage->value('externallink'), $QPage->value('pages_title'), 'target="' . $QPage->value('links_target') . ' class="menuFooterPageManager" rel="noreferrer"  title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"') . $end_class;
        } else {
          $page_menu_header .= $start_class . $separ . HTML::link(CLICSHOPPING::link($QPage->value('externallink')), $QPage->value('pages_title'), 'class="menuHeaderPageManager" target="' . $QPage->value('links_target') . '"  title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"') . $end_class;
        }
      }
    }

    $pages = ['text' => $page_menu_header];

    return $pages['text'];
  }

  /**
   * Generates and retrieves the HTML content for displaying footer pages in the page manager.
   *
   * This method retrieves pages from the database that qualify as footer pages based on
   * their type, status, language, customer group, and sort order. Links for the footer
   * are created dynamically, including external and internal links, with attributes such
   * as target and title. The resulting content is wrapped in a container and returned as
   * a string of HTML.
   *
   * @return string The HTML content representing the footer pages in the page manager.
   */
  public function pageManagerDisplayFooter(): string
  {
    $QPage = $this->db->prepare('select  p.pages_id,
                                            p.sort_order,
                                            p.status,
                                            p.page_box,
                                            s.pages_title,
                                            s.pages_html_text,
                                            s.externallink,
                                            p.links_target
                                     from :table_pages_manager p,
                                          :table_pages_manager_description s
                                     where p.pages_id = s.pages_id
                                     and p.status = 1
                                     and (p.page_type = 4 or p.page_type = 3)
                                     and (s.language_id  = :language_id or s.language_id  = 0)
                                     and p.page_box = 0
                                     and (p.customers_group_id = :customers_group_id  or p.customers_group_id = 99)
                                     order by p.sort_order, s.pages_title
                                    ');

    $QPage->bindInt(':language_id', (int)$this->lang->getId());
    $QPage->bindInt(':customers_group_id', (int)$this->customer->getCustomersGroupID());

    $QPage->setCache('page_manager_display_footer-lang' . $this->lang->getId());

    $QPage->execute();

    $rows = 0;

//***********************************
// -------- boxe et footer -----------
//***********************************
    $page_liste_footer = '<div class="footerPageManager">';

    while ($QPage->fetch() !== false) {
      $rows++;

      if ($rows != 1) {
        $separation = ' | ';
      } else {
        $separation = '';
      }

      if (!empty($QPage->value('externallink'))) {
        $search = str_contains($QPage->value('externallink'), 'index.php');
        if ($search === false) {
          $page_liste_footer .= $separation . HTML::link($QPage->value('externallink'), $QPage->value('pages_title'), 'target="' . $QPage->value('links_target') . '"', 'class="footerPageManager" rel="noreferrer" title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"');
        } else {
          $page_liste_footer .= $separation . HTML::link(CLICSHOPPING::link($QPage->value('externallink')), $QPage->value('pages_title'), 'class="footerPageManager" target="' . $QPage->value('links_target') . '" title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"');
        }
      } else {
        if ($QPage->valueInt('pages_id') != 3) {
          $link = $this->rewriteUrl->getPageManagerContentUrl($QPage->valueInt('pages_id'));
        } else {
          $link = CLICSHOPPING::link(null, 'Info&Contact');
        }

        if (!empty($QPage->value('pages_html_text'))) {
          $page_liste_footer .= $separation . HTML::link($link, $QPage->value('pages_title'), 'target="' . $QPage->value('links_target') . '"', ' class="footerPageManager" rel="noreferrer" title="' . $QPage->value('pages_title') . '"  id="' . $QPage->value('pages_title') . '"');
        }
      }
    }

    $page_liste_footer .= '</div>';
    $pages = ['text' => $page_liste_footer];

    return $pages['text'];
  }

  /**
   * Retrieves the HTML content and metadata for a specific page in the page manager.
   *
   * This method queries the database to fetch the page details associated with the given
   * page ID, considering filters such as page type, status, language, and customer group.
   * If the page type matches a specific value, it redirects to a predefined URL.
   * If the page is not found, it redirects to the shop's homepage.
   *
   * @param int $id The ID of the page to retrieve from the page manager.
   * @return string The HTML content of the page, or redirects to a URL if applicable.
   */
  public function pageManagerDisplayInformation(int $id): string
  {
    $QPage = $this->db->prepare('select p.pages_id,
                                          p.page_type,
                                          s.pages_html_text,
                                          s.pages_title
                                     from :table_pages_manager p,
                                          :table_pages_manager_description s
                                     where p.pages_id = s.pages_id
                                     and p.status = 1
                                     and (p.page_type = 3 or p.page_type = 4)
                                     and p.pages_id = :pages_id
                                     and (s.language_id  = :language_id or s.language_id  = 0)
                                     and (p.customers_group_id = :customers_group_id  or p.customers_group_id = 99)
                                  ');
    $QPage->bindInt(':pages_id', $id);
    $QPage->bindInt(':language_id', $this->lang->getId());
    $QPage->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

    $QPage->execute();

    $QPage->setCache('boxe_page_manager_display_information-lang' . $this->lang->getId());

    $pages = [];

    if ($QPage->fetch() !== false) {
      if ($QPage->value('page_type') == 3) {
        CLICSHOPPING::redirect(HTTP::getShopUrlDomain() . 'index.php?Info&Contact');
      } else {
        $pages = [
          'pages_id' => $QPage->valueInt('pages_id'),
          'pages_title' => $QPage->value('pages_title'),
          'pages_html_text' => $QPage->value('pages_html_text')
        ];
      }
    } else {
      CLICSHOPPING::redirect(HTTP::getShopUrlDomain() . 'index.php');
    }

    return $pages['pages_html_text'];
  }

  /**
   * Retrieves the display title of a specific page in the page manager.
   *
   * This method queries the database for a page with the provided ID,
   * ensuring it matches the specified criteria such as active status,
   * page type, language, and customer group. It returns the page title
   * if available. For a specific page type, the user is redirected
   * to a designated URL.
   *
   * @param int $id The unique identifier of the page to retrieve the title for.
   * @return string|null The title of the page, or null if the page does not exist or does not meet the criteria.
   */
  public function pageManagerDisplayTitle(int $id): string|null
  {
    $QPage = $this->db->prepare('select p.pages_id,
                                          p.page_type,
                                          s.pages_title
                                     from :table_pages_manager p,
                                          :table_pages_manager_description s
                                     where p.pages_id = s.pages_id
                                     and p.status = 1
                                     and (p.page_type = 3 or p.page_type = 4)
                                     and p.pages_id = :pages_id
                                     and (s.language_id  = :language_id or s.language_id  = 0)
                                     and (p.customers_group_id = :customers_group_id  or p.customers_group_id = 99)
                                  ');
    $QPage->bindInt(':pages_id', $id);
    $QPage->bindInt(':language_id', $this->lang->getId());
    $QPage->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

    $QPage->execute();

    $QPage->setCache('boxe_page_manager_display_title-lang' . $this->lang->getId());

    $pages = [];

    if ($QPage->fetch() !== false) {
      if ($QPage->valueInt('page_type') == 3) {
        CLICSHOPPING::redirect(HTTP::getShopUrlDomain() . 'index.php?Info&Contact');
      } else {
        $pages = [
          'pages_id' => $QPage->valueInt('pages_id'),
          'pages_title' => $QPage->value('pages_title')
        ];
      }
    }

    return $pages['pages_title'];
  }

  /**
   * Updates the status of a page in the page manager.
   *
   * This method sets the status of a specified page, updating relevant fields such as
   * the status change date and related timestamps. It clears the cache after updating.
   * If the status is not recognized, it returns -1.
   *
   * @param int $pages_id The ID of the page to update.
   * @param int $status The desired status of the page (1 for active, 0 for inactive).
   * @return bool|int True on successful execution, or -1 if the provided status is invalid.
   */
  private function setPageManagerStatus($pages_id, $status): bool|int
  {
    if ($status == 1) {
      $Qupdate = $this->db->prepare('update :table_pages_manager
                                        set status = 1,
                                            date_status_change =  now(),
                                            page_date_start = null
                                        where pages_id = :pages_id
                                      ');
      $Qupdate->bindInt(':pages_id', $pages_id);

      $this->getClearCache();

      return $Qupdate->execute();
    } elseif ($status == 0) {
      $Qupdate = $this->db->prepare('update :table_pages_manager
                                        set status = 0,
                                            date_status_change = now(),
                                            page_date_closed = null
                                        where pages_id = :pages_id
                                      ');

      $Qupdate->bindInt(':pages_id', $pages_id);
      $Qupdate->execute();

      $this->getClearCache();

      return $Qupdate->execute();
    } else {
      return -1;
    }
  }

  /**
   * Activates pages in the page manager that meet specific criteria.
   *
   * This method scans the pages manager for pages with a defined start date
   * that has passed and are not currently active. It updates their status to active
   * and clears the cache after processing all applicable pages.
   *
   * @return void This method does not return any value.
   */
  public function activatePageManager(): void
  {
    $QPages = $this->db->query('select pages_id
                                  from :table_pages_manager
                                  where page_date_start is not null
                                  and page_date_start <= now()
                                  and status != 1
                                 ');

    $QPages->execute();

    if ($QPages->fetch() !== false) {
      do {
        $this->setPageManagerStatus($QPages->valueInt('pages_id'), 1);
      } while ($QPages->fetch());

      $this->getClearCache();
    }
  }

  /**
   * Expires active pages in the page manager based on their closing date.
   *
   * This method checks for pages in the page manager table that are active,
   * have a non-null closing date, and whose closing date has already passed.
   * It updates the status of such pages to inactive and clears the cache
   * for the updates to take effect.
   *
   * @return void
   */
  public function expirePageManager(): void
  {
    $QPages = $this->db->query('select pages_id
                                    from :table_pages_manager
                                    where status = 1
                                    and page_date_closed is not null
                                    and now() >= page_date_closed
                                  ');

    $QPages->execute();

    if ($QPages->fetch() !== false) {
      do {
        $this->setPageManagerStatus($QPages->valueInt('pages_id'), 0);
      } while ($QPages->fetch());

      $this->getClearCache();
    }
  }


  /**
   * Retrieves the general conditions page content for the customer group.
   *
   * This method determines and fetches the appropriate general conditions page content.
   * It performs several database queries to identify the correct content based on the
   * customer's group ID, general condition settings, and active status of the page.
   * If the customer does not belong to any group, it returns false. Otherwise, it returns
   * the HTML text of the matched general conditions page or an empty string if no page is found.
   *
   * @return string|false The HTML content of the general conditions page for the customer group,
   *                      or false if the customer's group ID is not defined.
   */
  public function pageManagerGeneralCondition(): string|false
  {
    $general_condition = '';

    if (!\is_null($this->customer->getCustomersGroupID())) {
      $QpageManagerGeneralGroup = $this->db->prepare('select pages_id,
                                                                customers_group_id
                                                         from :table_pages_manager
                                                         where (customers_group_id = 99 or customers_group_id = :customers_group_id)
                                                         and page_type = 4
                                                         and page_general_condition = 1
                                                         and status = 1
                                                       ');
      $QpageManagerGeneralGroup->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());
      $QpageManagerGeneralGroup->execute();

      if ($QpageManagerGeneralGroup->valueInt('customers_group_id') == 99) {
        $QpageManagerGeneralCondition = $this->db->prepare('select pmd.pages_html_text,
                                                                      pm.customers_group_id
                                                             from :table_pages_manager pm,
                                                                  :table_pages_manager_description pmd
                                                             where  pm.customers_group_id = 99
                                                             and pm.page_general_condition = 1
                                                             and pm.page_type = 4
                                                             and pm.pages_id = :pages_id
                                                             and pmd.language_id = :language_id
                                                             and pmd.pages_id = pm.pages_id
                                                             and pm.status = 1
                                                             limit 1
                                                            ');
        $QpageManagerGeneralCondition->bindInt(':language_id', $this->lang->getId());
        $QpageManagerGeneralCondition->bindInt(':pages_id', $QpageManagerGeneralGroup->valueInt('pages_id'));

        $QpageManagerGeneralCondition->execute();
      } else {
        $QpageManagerGeneralGroup = $this->db->prepare('select pages_id
                                                           from :table_pages_manager
                                                           where (customers_group_id <> 99 or customers_group_id = :customers_group_id)
                                                           and page_type = 4
                                                           and page_general_condition = 1
                                                           and status = 1
                                                         ');
        $QpageManagerGeneralGroup->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());
        $QpageManagerGeneralGroup->execute();

        if ($this->customer->getCustomersGroupID() == 0) {
          $QpageManagerGeneralCondition = $this->db->prepare('select pmd.pages_html_text
                                                                 from :table_pages_manager pm,
                                                                      :table_pages_manager_description pmd
                                                                 where  pm.customers_group_id = 0
                                                                 and pm.page_general_condition = 1
                                                                 and pm.page_type = 4
                                                                 and pm.pages_id = :pages_id
                                                                 and pmd.language_id = :language_id
                                                                 and pmd.pages_id = pm.pages_id
                                                                 and pm.status = 1
                                                                 limit 1
                                                                ');
          $QpageManagerGeneralCondition->bindInt(':language_id', $this->lang->getId());
          $QpageManagerGeneralCondition->bindInt(':pages_id', $QpageManagerGeneralGroup->valueInt('pages_id'));


          $QpageManagerGeneralCondition->execute();
        } else {
          $QpageManagerGeneralCondition = $this->db->prepare('select pmd.pages_html_text,
                                                                        pm.customers_group_id
                                                                 from :table_pages_manager pm,
                                                                      :table_pages_manager_description pmd
                                                                 where  pm.customers_group_id = :customers_group_id
                                                                 and pm.page_general_condition = 1
                                                                 and pm.page_type = 4
                                                                 and pm.pages_id = :pages_id
                                                                 and pmd.language_id = :language_id
                                                                 and pmd.pages_id = pm.pages_id
                                                                 and pm.status = 1
                                                                 limit 1
                                                                ');
          $QpageManagerGeneralCondition->bindInt(':language_id', $this->lang->getId());
          $QpageManagerGeneralCondition->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());
          $QpageManagerGeneralCondition->bindInt(':pages_id', $QpageManagerGeneralGroup->valueInt('pages_id'));

          $QpageManagerGeneralCondition->execute();
        }
      }

      if ($QpageManagerGeneralCondition->fetch() !== false) {
        if (!empty($QpageManagerGeneralCondition->value('pages_html_text'))) {
          $general_condition = $QpageManagerGeneralCondition->value('pages_html_text');
        }
      } else {
        $general_condition = '';
      }

      return $general_condition;
    } else {
      return false;
    }
  }

  /**
   * Clears cached data related to the page manager and its associated components.
   *
   * This method removes various cached entries used for managing and displaying page
   * content, ensuring the system reflects the latest updates.
   *
   * @return void This method does not return any value.
   */
  private function getClearCache(): void
  {
    Cache::clear('boxe_page_manager_primary-lang');
    Cache::clear('boxe_page_manager_secondary-lang');
    Cache::clear('page_manager_display_header_menu-lang');
    Cache::clear('page_manager_display_footer_menu-lang');
    Cache::clear('page_manager_display_footer-lang');
    Cache::clear('boxe_page_manager_display_information-lang');
    Cache::clear('boxe_page_manager_display_title-lang');
  }
}
