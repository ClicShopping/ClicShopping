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
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Tools\AdministratorMenu\Classes\ClicShoppingAdmin\AdministratorMenu;
use ClicShopping\Apps\Tools\WhosOnline\Classes\ClicShoppingAdmin\WhosOnlineAdmin;

class HeaderMenu
{
  /**
   * Generates and returns the HTML for the admin header menu, including navigation and additional components.
   *
   * The method dynamically constructs a multi-level menu based on the configuration
   * and user session information. It includes support for nested submenus and interactive elements.
   * Additional icons and links are displayed based on the admin's access level and available warnings or errors.
   *
   * @return string The constructed HTML output for the admin header menu.
   */
  public function display(): string
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $output = '';

    if (isset($_SESSION['admin']) && VERTICAL_MENU_CONFIGURATION == 'false') {
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

      $output .= '<!-- Start Header Menu -->' . "\n";
      $output .= '<div class="headerFond" id="headerFond">
          <span class="headerLogo" id="headerLogo">' . HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'header/logo_clicshopping.webp', 'ClicShopping', '166', '55') . '</span>
          <span class="infoHeader" id="infoHeader">
        ';

      if (isset($_SESSION['admin'])) {
        $output .=  Gpt::gptModalMenu();

        if ($_SESSION['admin']['access'] == 1 && count(glob(ErrorHandler::getDirectory() . 'phpmail_error-*.txt', GLOB_NOSORT)) > 0) {
          $output .= '<span>' . HTML::link(CLICSHOPPING::link(null, 'A&Tools\EditLogError&LogErrorPhpMailer'), '<i class="bi bi-exclamation-circle-fill text-warning" title="Mail"></i>') . '</span> ';
        }

        if ($_SESSION['admin']['access'] == 1 && count(glob(ErrorHandler::getDirectory() . 'errors-*.txt', GLOB_NOSORT)) > 0) {
          $output .= '<span>' . HTML::link(CLICSHOPPING::link(null, 'A&Tools\EditLogError&LogError'), '<i class="bi bi-exclamation-circle-fill text-danger"></i>') . '</span> ';
        }

        $output .= '<span>' . HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'header/administrateur.gif', CLICSHOPPING::getDef('text_header_user_administrator'), '16', '16') . '</span>';
        $output .= '
          <span class="menuJSCookTexte">' . (isset($_SESSION['admin']) ? '&nbsp;' . AdministratorAdmin::getUserAdmin() . '&nbsp; - &nbsp;<a href="' . CLICSHOPPING::link('login.php', 'action=logoff') . '" class="headerLink"><i class="bi bi-power" aria-hidden="true"></i></a>' : '') . ' &nbsp;&nbsp;</span>
          <span class="InfosHeaderWhoOnline">' . (isset($_SESSION['admin']) ? HTML::link(CLICSHOPPING::link(null, 'A&Tools\WhosOnline&WhosOnline'), HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'header/clients.gif', CLICSHOPPING::getDef('text_header_online_customers'), '16', '16')) : '') . '</span>
          <span class="menuJSCookTexte InfosHeaderWhoOnline">' . (isset($_SESSION['admin']) ? '&nbsp;' . CLICSHOPPING::getDef('text_header_number_of_customers', ['online_customer' => WhosOnlineAdmin::getCountWhosOnline()]) . '&nbsp;&nbsp;' : '') . '</span>
          ';
      }

      $output .= '</span>';
      $output .= '</div>';
      $output .= '<div class="headerLine"></div>';
      $output .= '<div class="backgroundMenu">';
      $output .= '<span class="float-start">';
      $output .= '<nav class="main-nav" role="navigation">';
      $output .= '<input id="main-menu-state" type="checkbox"/>';
      $output .= '<label class="main-menu-btn" for="main-menu-state">';
      $output .= '<span class="main-menu-btn-icon"></span>';
      $output .= '</label>';

      $output .= '<ul id="main-menu" class="sm sm-mint">';

      // level 1
      foreach ($menu_parent as $key => $menus) {
        $image = '';

        if ($menus['link'] != '') {
          $output .= '<li>' . HTML::link(CLICSHOPPING::link($menus['link']), $menus['label']) . '</li>';
        } else {
          $output .= '<li><a class="nav-link">' . $image . ' ' . $menus['label'] . '</a>';
        }

        //--------------------------------------------------------------
        // level 2
        if (isset($menus['sub_menu'])) {
          $output .= '<ul>';
          foreach ($menus['sub_menu'] as $second_level) {
            $image = '';

            if ($menu_sub[$second_level]['link'] != '') {
              $output .= '<li>' . HTML::link(CLICSHOPPING::link($menu_sub[$second_level]['link']), $image . ' ' . $menu_sub[$second_level]['label']) . '</li>';
            } else {
              $output .= '<li class="sub_menu_1st_level"><a class="nav-link">' . $image . ' ' . $menu_sub[$second_level]['label'] . '</a>';
            }
            //--------------------------------------------------------------
            // level 3
            if (isset($menu_sub[$second_level]['sub_menu'])) {
              $output .= '<ul>';

              foreach ($menu_sub[$second_level]['sub_menu'] as $third_level) {
                $image = '';

                if (!is_null($menu_sub[$third_level]['link'])) {
                  $output .= '<li>' . HTML::link(CLICSHOPPING::link($menu_sub[$third_level]['link']), $image . ' ' . $menu_sub[$third_level]['label']) . '</li>';
                } else {
                  $output .= '<li><a class="nav-link">' . $image . ' ' . $menu_sub[$third_level]['label'] . '</a>';
                }

                //--------------------------------------------------------------
                // level 4
                if (isset($menu_sub[$third_level]['sub_menu'])) {
                  //              $output .= '<ul>';

                  foreach ($menu_sub[$third_level]['sub_menu'] as $fourth_level) {
                    $image = '';

                    if (!is_null($menu_sub[$fourth_level]['link'])) {
                      $output .= '<li>' . HTML::link(CLICSHOPPING::link($menu_sub[$fourth_level]['link']), $image . ' ' . $menu_sub[$fourth_level]['label']) . '</li>';
                    } else {

                      $output .= '<li><a class="nav-link">' . $image . ' ' . $menu_sub[$fourth_level]['label'] . '</a>';
                    }
                  }

                  if (is_null($menu_sub[$fourth_level]['link'])) {
                    $output .= '</li>';
                  }
                  //              $output .= '</ul>';
                }

                //--------------------------------------------------------------
                if (is_null($menu_sub[$third_level]['link'])) {
                  $output .= '</li>';
                }
              }

              $output .= '</ul>'; //énd menu
            }

            if (is_null($menu_sub[$second_level]['link'])) {
              $output .= '</li>';
            }
          }
          $output .= '</ul>';
        }
        if ($menus['link'] != '') {
          $output .= '</li>';
        }
      }

      $output .= '</ul>';
      $output .= '</nav>';
      $output .= '</span>';
      $output .= '</div>';
      $output .= '<div class="clearfix"></div>';
      $output .= '<!-- End Header Menu -->' . "\n";
    }

    return $output;
  }
}