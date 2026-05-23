<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;

class Reviews extends \ClicShopping\OM\Modules\AdminDashboardAbstract
{
  public mixed $lang;
  public mixed $app;
  public $group;

  /**
   * Initializes the Reviews module by setting up the required app instance, language definitions,
   * title, description, and configuration settings.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('Reviews')) {
      Registry::set('Reviews', new ReviewsApp());
    }

    $this->app = Registry::get('Reviews');
    $this->lang = Registry::get('Language');

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/reviews');

    $this->title = $this->app->getDef('module_admin_dashboard_reviews_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_reviews_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_REVIEWS_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_REVIEWS_APP_SORT_ORDER;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_REVIEWS_APP_STATUS == 'True');
    }
  }

  /**
   * Generates and returns the HTML content for displaying a reviews dashboard module.
   * The method retrieves reviews data from a database, formats it into an HTML table, and applies various settings
   * such as sortable columns, mobile responsiveness, and action buttons.
   *
   * @return string The generated HTML output for the reviews module dashboard.
   */
  public function getOutput()
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $content_width = 'col-md-' . (int)MODULE_ADMIN_DASHBOARD_REVIEWS_APP_CONTENT_WIDTH;

    $content = '<div class="' . $content_width . '">';
    $content .= '<div class="mt-1"></div>';
    $content .= '<table 
        id="table"
        data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
        data-sort-name="added"
        data-sort-order="asc"
        data-toolbar="#toolbar"
        data-buttons-class="primary"
        data-show-toggle="true"
        data-show-columns="true"
        data-mobile-responsive="true"
        data-check-on-init="true">';

    $content .= '<thead class="dataTableHeadingRow">';
    $content .= '<tr>';

    $content .= '
          <th data-field="products" scope="col"> ' . $this->app->getDef('table_heading_products') . '</th>
          <th data-field="added" data-sortable="true" scope="col">' . $this->app->getDef('table_heading_date_added') . '</th>
          <th data-field="author" scope="col"> ' . $this->app->getDef('table_heading_review_author') . '</th>
          <th data-field="rating" scope="col">' . $this->app->getDef('table_heading_rating') . '</th>                    
          <th data-field="approved" scope="col"> ' . $this->app->getDef('table_heading_approved') . '</th>
          <th data-field="action" data-switchable="false" class="text-end">' . $this->app->getDef('table_heading_action') . '&nbsp;</th>
        </tr>
      ';

    $content .= '</thead>';
    $content .= '<tbody>';

    $Qreviews = $this->app->db->prepare('select r.reviews_id, 
                                                  r.date_added,
                                                  r.customers_name, 
                                                  r.reviews_rating, 
                                                  r.status,
                                                  pd.products_name 
                                            from :table_reviews r, 
                                                 :table_products_description pd 
                                             where pd.products_id = r.products_id 
                                            and pd.language_id = :language_id
                                            and r.status = 0
                                            order by r.date_added desc limit 6
                                          ');

    $Qreviews->bindInt(':language_id', $CLICSHOPPING_Language->getID());
    $Qreviews->bindInt(':language_id', $CLICSHOPPING_Language->getID());
    $Qreviews->execute();

    while ($Qreviews->fetch()) {
      if ($Qreviews->valueInt('status') == 1) {
        $status_icon = HTML::link($this->app->link('Reviews&SetFlag&flag=0&id=' . $Qreviews->valueInt('reviews_id')), '<i class="bi-check text-success"></i>');
      } else {
        $status_icon = HTML::link($this->app->link('Reviews&SetFlag&flag=1&id=' . $Qreviews->valueInt('reviews_id')), '<i class="bi bi-x text-danger"></i>');
      }

      $content .= '<tr class="dataTableRow backgroundBlank">' .
        '    <td class="dataTableContent">' . HTML::outputProtected($Qreviews->value('products_name')) . '</td>' .
        '    <td class="dataTableContent">' . DateTime::toShort($Qreviews->value('date_added')) . '</td>' .
        '    <td class="dataTableContent">' . HTML::outputProtected(Hash::displayDecryptedDataText($Qreviews->value('customers_name'))) . '</td>' .
        '    <td class="dataTableContent"><i>' . HTML::stars($Qreviews->valueInt('reviews_rating')) . '</i></td>' .
        '    <td class="dataTableContent text-center">' . $status_icon . '</td>' .
        '   <td class="dataTableContent text-end">' . HTML::link($this->app->link('&Edit&page=' . (int)$_GET['page'] . '&rID=' . $Qreviews->valueInt('reviews_id')), '<h4><i class="bi bi-pencil" title="' . $this->app->getDef('icon_edit') . '"></i></h4>') . '</td>' .
        '  </tr>';

      $content .= ' </tbody>';
      $content .= '</table>';
      $content .= '</div>';
      $content .= '</div>';

      $output = <<<EOD
  <!-- ######################## -->
  <!--  Start Reviews     -->
  <!-- ######################## -->
             {$content}
  <!-- ######################## -->
  <!--  Start Reviews      -->
  <!-- ######################## -->
EOD;

      return $output;
    }
  }

  /**
   * Installs the module by saving its configuration settings into the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to enable this Module ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_REVIEWS_APP_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this Module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Select the width to display',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_REVIEWS_APP_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 to 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_REVIEWS_APP_SORT_ORDER',
        'configuration_value' => '45',
        'configuration_description' => 'Sort order of display. Lowest is displayed first.',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the configuration keys associated with the Reviews Dashboard module.
   *
   * @return array The list of configuration keys.
   */
  public function keys()
  {
    return ['MODULE_ADMIN_DASHBOARD_REVIEWS_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_REVIEWS_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_REVIEWS_APP_SORT_ORDER'
    ];
  }
}
