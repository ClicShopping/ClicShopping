<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Catalog\Categories\Classes\Shop\CategoryTree;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class he_header_multi_template
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;
  public $pages;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('modules_header_multi_template_title');
    $this->description = CLICSHOPPING::getDef('modules_header_multi_template_description');

    if (\defined('MODULES_HEADER_MULTI_TEMPLATE_STATUS')) {
      $this->sort_order = \defined('MODULES_HEADER_MULTI_TEMPLATE_SORT_ORDER') ? (int)MODULES_HEADER_MULTI_TEMPLATE_SORT_ORDER : 0;
      $this->enabled = \defined('MODULES_HEADER_MULTI_TEMPLATE_STATUS') ? (MODULES_HEADER_MULTI_TEMPLATE_STATUS == 'True') : false;
      $this->pages = \defined('MODULES_HEADER_MULTI_TEMPLATE_TEMPLATE_DISPLAY_PAGES') ? MODULES_HEADER_MULTI_TEMPLATE_TEMPLATE_DISPLAY_PAGES : 'all';
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_CategoryTree = Registry::get('CategoryTree');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Service = Registry::get('Service');
    $CLICSHOPPING_Banner = Registry::get('Banner');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Tax = Registry::get('Tax');
    $CLICSHOPPING_Category = Registry::get('Category');

    $CLICSHOPPING_CategoryTree->reset();
    $CLICSHOPPING_CategoryTree->setMaximumLevel(1);
    $CLICSHOPPING_CategoryTree->setParentGroupString('<ul class="TemplateHeaderCategoriesNavigation">', '</ul>', true);
    $CLICSHOPPING_CategoryTree->setChildString('<li class="TemplateHeaderCategoriesNavigation">', '</li>');

    $cPath = $CLICSHOPPING_Category->getPath();

    $languages_string = $CLICSHOPPING_Language->getFlag();
    $content_width = \defined('MODULES_HEADER_MULTI_TEMPLATE_TEMPLATE_CONTENT_WIDTH') ? (int)MODULES_HEADER_MULTI_TEMPLATE_TEMPLATE_CONTENT_WIDTH : 12;
    $login = HTML::button(CLICSHOPPING::getDef('modules_header_multi_template_account_login'), null, null, 'primary', null, 'sm');

      // Intégration des attributs structurés pour le protocole WebMCP (IA)
     $form_advanced_result = '<form name="searchData" action="' . CLICSHOPPING::link(null, 'Search&Q') . '" method="post" id="searchData" mcp-tool="searchProducts" mcp-description="Recherche les produits et catégories de la boutique e-commerce par mots-clés">';
    $form = HTML::form('loginForm', CLICSHOPPING::link(null, 'Account&LogIn&Process'), 'post', 'id="loginForm"', ['tokenize' => true]);
    $endform = '</form>';

    if (\is_array($CLICSHOPPING_Category->getCategories())) {
      $categories_dropdown = HTML::form('categoriesDropdown', 'index.php', null, 'id="categoriesDropdown"', ['tokenize' => true]);
      $categories_dropdown .= HTML::selectField('cPath', $CLICSHOPPING_CategoryTree->getShopCategoryTree(), $cPath, 'onchange="this.form.submit();"');
      $categories_dropdown .= '</form>';
    } else {
      $categories_dropdown = '';
    }

    if ($CLICSHOPPING_Service->isStarted('Banner')) {
      if ($banner = $CLICSHOPPING_Banner->bannerExists('dynamic', MODULES_HEADER_MULTI_MODULE_LOGO_BANNER_GROUP)) {
        $logo_header = $CLICSHOPPING_Banner->displayBanner('static', $banner);
      } else {
        $logo_header = '';
      }

      if ($banner = $CLICSHOPPING_Banner->bannerExists('dynamic', MODULES_HEADER_MULTI_MODULE_BANNER_2_GROUP)) {
        $banner_header = $CLICSHOPPING_Banner->displayBanner('static', $banner);
      } else {
        $banner_header = '';
      }
    }

    if ($CLICSHOPPING_ShoppingCart->getCountContents() > 0) {
      $shopping_cart = $CLICSHOPPING_ShoppingCart->getCountContents() . '&nbsp;' . CLICSHOPPING::getDef('modules_header_multi_template_shopping_cart_product') . '&nbsp;';
    } else {
      $shopping_cart = CLICSHOPPING::getDef('modules_header_multi_template_shopping_cart_no_products');
    }

    if (substr(CLICSHOPPING::getBaseNameIndex(), 0, 8) != 'checkout') {
      $currency_header = $CLICSHOPPING_Currencies->getCurrenciesDropDown('headerMultiTemplateDefaultCurrencies');
    }

    $header_template = '<!-- header template start -->' . "\n";

    $filename = $CLICSHOPPING_Template->getTemplateModulesFilename($this->group . '/template_html/' . MODULES_HEADER_MULTI_TEMPLATE_FILES);

    if (is_file($filename)) {
      ob_start();
      require_once($filename);
      $header_template .= ob_get_clean();
    } else {
      echo '<div class="alert alert-warning text-center" role="alert">' . CLICSHOPPING::getDef('template_does_not_exist') . '</div>';
      exit;
    }

    $header_template .= '<!-- header template end -->' . "\n";

    $CLICSHOPPING_Template->addBlock($header_template, $this->group);
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_HEADER_MULTI_TEMPLATE_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_HEADER_MULTI_TEMPLATE_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please, select the width of your module ?',
        'configuration_key' => 'MODULES_HEADER_MULTI_TEMPLATE_TEMPLATE_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Indicate a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the template you want to use ?',
        'configuration_key' => 'MODULES_HEADER_MULTI_TEMPLATE_FILES',
        'configuration_value' => 'multi_template_default.php',
        'configuration_description' => 'Select the the template you want to use.',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_multi_template_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the banner group for the logo',
        'configuration_key' => 'MODULES_HEADER_MULTI_MODULE_LOGO_BANNER_GROUP',
        'configuration_value' => SITE_THEMA . '_multi_template_logo',
        'configuration_description' => 'Indicate the banner group<br /><br /><strong>Note :</strong><br /><i>The group must be created or selected whtn you create a banner in Marketing / banner</i>',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the banner group for this image 2',
        'configuration_key' => 'MODULES_HEADER_MULTI_MODULE_BANNER_2_GROUP',
        'configuration_value' => SITE_THEMA . '_multi_template_banner',
        'configuration_description' => 'Indicate the banner group<br /><br /><strong>Note :</strong><br /><i>The group must be created or selected whtn you create a banner in Marketing / banner</i>',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULES_HEADER_MULTI_TEMPLATE_SORT_ORDER',
        'configuration_value' => '100',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate the page where the module is displayed',
        'configuration_key' => 'MODULES_HEADER_MULTI_TEMPLATE_TEMPLATE_DISPLAY_PAGES',
        'configuration_value' => 'all',
        'configuration_description' => 'Select the page where the module is displayed',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => 'clic_cfg_set_select_pages_list',
        'date_added' => 'now()'
      ]
    );
  }

  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  public function keys()
  {
    return array('MODULES_HEADER_MULTI_TEMPLATE_STATUS',
      'MODULES_HEADER_MULTI_TEMPLATE_TEMPLATE_CONTENT_WIDTH',
      'MODULES_HEADER_MULTI_TEMPLATE_FILES',
      'MODULES_HEADER_MULTI_MODULE_LOGO_BANNER_GROUP',
      'MODULES_HEADER_MULTI_MODULE_BANNER_2_GROUP',
      'MODULES_HEADER_MULTI_TEMPLATE_SORT_ORDER',
      'MODULES_HEADER_MULTI_TEMPLATE_TEMPLATE_DISPLAY_PAGES'
    );
  }
}


class explodeCategoryTree extends categoryTree
{
  public $parent_group_start_string = null;
  public $parent_group_end_string = null;
  public $parent_group_apply_to_root = false;
  public $root_start_string = '<li class="dropdown">';
  public $root_end_string = '</li>';
  public $parent_start_string = '<ul class="dropdown-menu multi-column columns-2">';
  public $parent_end_string = '</ul>';
  public $child_start_string = '<li>';
  public $child_end_string = '</li>';

  private function _buildCategorytree($parent_id, $level = 0)
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($this->_data[$parent_id])) {
      $result = '';

      foreach ($this->_data[$parent_id] as $category_id => $category) {
        if ($this->breadcrumb_usage === true) {
          $category_link = $this->buildBreadcrumb($category_id);
        } else {
          $category_link = $category_id;
        }
        if (($this->follow_cpath === true) && \in_array($category_id, $this->cpath_array, true)) {
          $link_title = $this->cpath_start_string . $category['name'] . $this->cpath_end_string;
//            $link_image =  $this->cpath_start_string . HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $category['image'], HTML::outputProtected($category['name']), 150, 150, null, true) . $this->cpath_end_string;
        } else {
          $link_title = $category['name'];

          if ($level < 1) {
            $link_image = HTML::link(CLICSHOPPING::link(null, 'cPath=' . $category['id']), HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $category['image'], HTML::outputProtected($category['name']), 150, 150, null, true));
          }
        }

        if (isset($this->_data[$category_id]) && ($level != 0)) {
          $result .= '<li class="dropdown dropdown-submenu multi-column-dropdown"><a href="#" tabindex="-1" class="dropdown-toggle" data-bs-toggle="dropdown">';
          $caret = false;
        } elseif (isset($this->_data[$category_id]) && (($this->max_level == '0') || ($this->max_level > $level + 1))) {
          $result .= $this->root_start_string;
          $result .= '<a href="#" tabindex="-1" class="dropdown-toggle" data-bs-toggle="dropdown">';
          $caret = '<span class="caret"></span>';

        } else {
          $result .= $this->child_start_string;
          $result .= '<a href="' . CLICSHOPPING::link(null, 'cPath=' . $category_link) . '">';
          $caret = false;
        }

        $result .= str_repeat($this->spacer_string, $this->spacer_multiplier * $level);
        $result .= $link_title . (($caret !== false) ? $caret : null) . '</a>';

        if (isset($this->_data[$category_id]) && (($this->max_level == '0') || ($this->max_level > $level + 1))) {
// uncomment below to show parent category link //


          $root_link_title = '<span class="hidden-xs">';
          if ($level < 1) {
            $root_link_title .= '<div class="row col-md-12">';
            $root_link_title .= '<div class="col-md-6 headerCategoriesImages" style="padding-bottom:10px;">' . $link_image . '</div>';
            $root_link_title .= '<div class="col-md-6 bi bi-card-list">&nbsp;' . $link_title . '</div>';
            $root_link_title .= '</div>';
          } else {
            $root_link_title .= '<div class="col-md-12 bi bi-card-list" style="padding-bottom:10px;">&nbsp;' . $link_title . '</div>';
          }

          $root_link_title .= '<li class="visible-xs dropdown-divider"></li>';
          $root_link_title .= '</span>';

          // divider added for clarity - comment out if you no like //
//            $root_link_title .= '<li class="dropdown-divider"></li>';


          $result .= $this->parent_start_string;
          $result .= '<li>' . HTML::link(CLICSHOPPING::link(null, 'cPath=' . $category_link), $root_link_title) . '</li>';
          $result .= $this->_buildCategorytree($category_id, $level + 1);

//            $result .= '<div class="col-md-6" style="padding-top:10px;">'.$this->_buildCategorytree($category_id, $level + 1).'</div>';
          $result .= $this->parent_end_string;
          $result .= $this->child_end_string;
        } else {
          $result .= $this->root_end_string;
        }
      }
    }
    
    return $result;
  }


  public function getExTree()
  {
    return $this->_buildCategorytree($this->root_category_id);
  }

  public function buildCategorytree($class = '')
  {
    $CLICSHOPPING_CategoryTree = Registry::get('CategoryTree');

    if (empty($class)) $class = 'nav navbar-nav';

    $data = '<ul class="' . $class . '">' . $CLICSHOPPING_CategoryTree->getExTree() . '</ul>';

    return $data;
  }
}
