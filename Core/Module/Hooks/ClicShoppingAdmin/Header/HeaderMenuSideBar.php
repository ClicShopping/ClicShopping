<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Header;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\ErrorHandler;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function count;
use function is_null;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Tools\AdministratorMenu\Classes\ClicShoppingAdmin\AdministratorMenu;
use ClicShopping\Apps\Tools\WhosOnline\Classes\ClicShoppingAdmin\WhosOnlineAdmin;
class HeaderMenuSideBar
{
  /**
   * Generates and displays the structured HTML output for a vertical navigation menu.
   * The method dynamically builds the menu structure based on the hierarchy of parent and sub-menu items
   * as retrieved from the database. The menu includes support for multiple levels, user sessions, and administrative tools.
   *
   * - Constructs primary and nested menu items (up to four levels) based on the parent-child relationship of menu options.
   * - Integrates session-based admin functionalities like user details, logout options, error logs, and "Who's Online".
   * - Provides support for collapsible menus with toggle functionality for enhanced user interaction.
   *
   * @return string|bool Returns the generated HTML string for the navigation menu if conditions are met.
   *                     Returns false if prerequisites such as session or configuration are not met.
   */
  public function display(): string|bool
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $output = '';

    if (isset($_SESSION['admin']) && VERTICAL_MENU_CONFIGURATION == 'true') {

      $Qmenus = AdministratorMenu::getHeaderMenu();

      $menu_parent = [];
      $menu_sub = [];

      foreach ($Qmenus as $menus) {
        if ($menus['parent_id'] == 0) {
          $menu_parent[$menus['id']] = $menus;
        } else {
          if (isset($menu_parent[$menus['parent_id']]) && !is_null($menu_parent[$menus['parent_id']])) {
            $menu_parent[$menus['parent_id']]['sub_menu'][$menus['id']] = $menus['id'];
            $menu_sub[$menus['id']] = $menus;
          } elseif (isset($menu_sub[$menus['parent_id']]) && !is_null($menu_sub[$menus['parent_id']])) {
            $menu_sub[$menus['parent_id']]['sub_menu'][$menus['id']] = $menus['id'];
            $menu_sub[$menus['id']] = $menus;
          }
        }
      }
//************************************
//
//************************************
      $output = '<!-- Start left Menu -->' . "\n";
      $output .= '<div class="page-content content" id="content">';
      $output .= '<div id="page-content">';
      $output .= '<div class="sidebarCollapse float-start">';
      $output .= '<button id="sidebarCollapse" type="button" class="btn btn-light bg-header"><i class="bi bi-layout-three-columns"></i></button>';
      $output .= '</div>';

      $output .= '<div class="py-1 px-1 bg-header">';
      $output .= '<h6 class="m-3 text-end">';

      $output .= (isset($_SESSION['admin']) ? '&nbsp;' . AdministratorAdmin::getUserAdmin() . '&nbsp; - &nbsp;<a href="' . CLICSHOPPING::link('login.php', 'action=logoff') . '" class="headerLink"><i class="bi bi-power" aria-hidden="true"></i></a>' : '');

      if ($_SESSION['admin']['access'] === 1 && count(glob(ErrorHandler::getDirectory() . 'errors-*.txt', GLOB_NOSORT)) > 0) {
        $output .= '&nbsp; - &nbsp; ' . HTML::link(CLICSHOPPING::link(null, 'A&Tools\EditLogError&LogError'), '<i class="bi bi-exclamation-circle-fill text-warning"></i>');
      }

      $output .= (isset($_SESSION['admin']) ? '&nbsp; - &nbsp; ' . HTML::link(CLICSHOPPING::link(null, 'A&Tools\WhosOnline&WhosOnline'), HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'header/clients.gif', CLICSHOPPING::getDef('text_header_online_customers'), '16', '16')) : '');
      $output .= (isset($_SESSION['admin']) ? '&nbsp;&nbsp; ' . CLICSHOPPING::getDef('text_header_number_of_customers', ['online_customer' => WhosOnlineAdmin::getCountWhosOnline()]) . '&nbsp;&nbsp;' : '');

      $output .= '</h6>';
      $output .= '</h6>';
      $output .= '</div>';

      $output .= '<!-- start vertical menu -->';
      $output .= '<div class="vertical-nav bg-header" id="sidebar">';
      $output .= '<div class="py-1 px-1 mb-4 bg-header">';

      $output .= '<div class="media d-flex align-items-center" id="my-nav">';
      $output .= HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'header/logo_clicshopping1.webp', 'ClicShopping', '51', '51', 'mr-3 rounded-circle img-thumbnail shadow-s');
      $output .= '<div class="textLogo">';
      $output .= '<h4 class="m-0">&nbsp;&nbsp;ClicShopping</h4>';
      $output .= '</div>';

      $output .= '<div class="sidebarCollapse1 sidebarHide">';
      $output .= '&nbsp;&nbsp;&nbsp;<button id="sidebarCollapse1" type="button" class="btn"><i class="bi bi-layout-three-columns"></i></button>';
      $output .= '</div>';

      $output .= '</div>';
      $output .= '<div class="media-body">';
      $output .= '</div>';
      $output .= '</div>';
      $output .= '<div class="addScrollSideBar" id="addScrollSideBar">';
      $output .= '<div class="collapse show d-md-flex bg-light pt-2 pl-0 min-vh-100 bg-header" id="sidebar">';
      $output .= '<ul class="nav flex-column flex-nowrap overflow-hidden collapsed" id="submenu1sub1" aria-expanded="false">';

//--------------------------------------------------------------
// level 1
      $i = 1;

      foreach ($menu_parent as $key => $menus) {
        $i = $i + 1;
        $image = '';

        if ($menus['link'] != '') {
          $output .= '<li class="nav-item p-2 m-1">' . HTML::link(CLICSHOPPING::link($menus['link']), $menus['label'], 'class="nav-link"') . '</li>';
        } else {
          $output .= '<li class="nav-item active m-1"><a href="#submenu1sub' . $i . '" data-bs-target="#submenu1sub' . $i . '" data-bs-toggle="collapse" class="nav-link collapsed text-uppercase"><i class="bi bi-plus-circle"></i>' . $image . ' ' . $menus['label'] . '</a>
                        <div class="collapse" id="submenu1sub' . $i . '" aria-expanded="false">
                      ';
        }

//--------------------------------------------------------------
// level 2
        if (isset($menus['sub_menu'])) {
          $output .= '<ul class="flex-column pl-1 nav" id="submenu2sub1" aria-expanded="false">';

          $n = 1;
          foreach ($menus['sub_menu'] as $second_level) {
            ++$n;
            $image = '';

            if ($menu_sub[$second_level]['link'] != '') {
              $output .= '<li class="nav-item p-1 small m-1">' . HTML::link(CLICSHOPPING::link($menu_sub[$second_level]['link']), $image . ' ' . $menu_sub[$second_level]['label'], 'class="nav-link"') . '</li>';
            } else {
              $output .= '<li class="nav-item active m-1"><a href="#submenu2sub' . $n . '" data-bs-target="#submenu2sub' . $n . '" data-bs-toggle="collapse" class="nav-link collapsed text-uppercase small"><i class="bi bi-plus-circle"></i> ' . $image . ' ' . $menu_sub[$second_level]['label'] . '</a>
                            <div class="collapse" id="submenu2sub' . $n . '" aria-expanded="false">
                            ';
            }

//--------------------------------------------------------------
// level 3
            if (isset($menu_sub[$second_level]['sub_menu'])) {
              $output .= '<ul class="flex-column p-2 nav" id="submenu3sub1" aria-expanded="false"">';
              $z = 1;

              foreach ($menu_sub[$second_level]['sub_menu'] as $third_level) {
                $z = $z + 1;
                $image = '';

                if (!is_null($menu_sub[$third_level]['link'])) {
                  $output .= '<li class="nav-item p-1 small m-1">' . HTML::link(CLICSHOPPING::link($menu_sub[$third_level]['link']), $image . ' ' . $menu_sub[$third_level]['label'], ' class="nav-link"') . '</li>';
                } else {
                  $output .= '<li class="nav-item p-2 m-1"><a href="#submenu3sub' . $z . '" data-bs-target="#submenu3sub' . $z . '" data-bs-toggle="collapse" class="nav-link collapsed text-uppercase">--- ' . $image . ' ' . $menu_sub[$third_level]['label'] . '</a>
                                <div class="collapse" id="submenu3sub' . $z . '" aria-expanded="false">
                              ';
                }

//--------------------------------------------------------------
// level 4
                if (isset($menu_sub[$third_level]['sub_menu'])) {
                  $output .= '<ul class="flex-column pl-2 nav" id="submenu4sub1" aria-expanded="false">';
                  $x = 1;

                  foreach ($menu_sub[$third_level]['sub_menu'] as $fourth_level) {
                    $x = $x + 1;
                    $image = '';

                    if (!is_null($menu_sub[$fourth_level]['link'])) {
                      $output .= '<li class="nav-item p-1 small m-1">' . HTML::link(CLICSHOPPING::link($menu_sub[$fourth_level]['link']), $image . ' ' . $menu_sub[$fourth_level]['label'], 'nav-link') . '</li>';
                    } else {
                      $output .= '<li class="nav-item p-2 m-1"><a href="#submenu4sub' . $x . '"  data-bs-target="#submenu4sub' . $x . '" data-bs-toggle="collapse" class="nav-link collapsed text-uppercase">---- ' . $image . ' ' . $menu_sub[$fourth_level]['label'] . '</a>
                                    <div class="collapse" id="submenu4sub' . $x . '" aria-expanded="false">
                                    ';
                    }
                  }

                  if (is_null($menu_sub[$fourth_level]['link'])) {
                    $output .= '</div>
                                  </li>';
                  }

                  $output .= '</ul>';
                }

//--------------------------------------------------------------
// 3eme

                if (is_null($menu_sub[$third_level]['link'])) {
                  $output .= '</div>
                                </li>';
                }
              }

              $output .= '</ul>'; //énd menu
            }

//------------------------------------------------------------
// 2eme
            if (is_null($menu_sub[$second_level]['link'])) {
              $output .= '</div>
                            </li>';
            }
          }

          $output .= '</ul>'; //énd menu
        }

//----------------------------------------------------------
//1er
        if ($menus['link'] == '') {
          $output .= '</div>
                      </li>';
        }
      }

      $output .= '</ul>';


      $output .= '</div>';
      $output .= '</div>';
      $output .= '</div>';
      $output .= '<!-- Start left Menu  -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}

