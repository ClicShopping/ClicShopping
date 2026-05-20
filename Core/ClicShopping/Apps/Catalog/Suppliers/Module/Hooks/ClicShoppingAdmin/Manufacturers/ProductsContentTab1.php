<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Module\Hooks\ClicShoppingAdmin\Manufacturers;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Catalog\Suppliers\Suppliers as SuppliersApp;

use function count;
use function is_array;

class ProductsContentTab1 implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $db;

  /**
   * Constructor method to initialize the Suppliers application.
   *
   * This method checks if the 'Suppliers' registry key exists. If it does not exist,
   * a new instance of the Suppliers application is created and registered. It also
   * sets the local application property and loads the required definitions for
   * the specified module.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Suppliers')) {
      Registry::set('Suppliers', new SuppliersApp());
    }

    $this->app = Registry::get('Suppliers');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Manufacturers/page_content_tab_1');
  }

  /**
   * Retrieves the supplier's ID associated with a given manufacturer's ID.
   *
   * @return int The ID of the supplier.
   */
  private function getSuppliersId(): int
  {
    $Qsuppliers = $this->app->db->prepare('select suppliers_id 
                                               from :table_manufacturers
                                               where manufacturers_id = :manufacturers_id
                                             ');
    $Qsuppliers->bindInt('manufacturers_id', $_GET['mID']);
    $Qsuppliers->execute();

    $suppliers_id = $Qsuppliers->valueInt('suppliers_id');

    return $suppliers_id;
  }

  /**
   * Generates and returns the HTML content and JavaScript required for a manufacturer autocomplete and modal dialog.
   *
   * @return string The HTML and JavaScript output for the manufacturer autocomplete and modal dialog.
   */
  public function display(): string
  {
    if (!\defined('CLICSHOPPING_APP_SUPPLIERS_CS_STATUS') || CLICSHOPPING_APP_SUPPLIERS_CS_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['mID'])) {
      $suppliers_id = $this->getSuppliersId();
    }

    $Qsuppliers = $this->app->db->prepare('select suppliers_id,
                                                     suppliers_name
                                               from :table_suppliers
                                               order by suppliers_name
                                              ');
    $Qsuppliers->execute();

    $count = $Qsuppliers->rowCount();

    $output = '';

    if ($count > 0) {
      $suppliers_name_array[] = [
        'id' => 0,
        'text' => CLICSHOPPING::getDef('text_select')
      ];

      while ($Qsuppliers->fetch()) {
        $suppliers_name_array[] = [
          'id' => $Qsuppliers->valueInt('suppliers_id'),
          'text' => $Qsuppliers->value('suppliers_name')
        ];
      }

      $output .= '<div class="mt-1"></div>';
      $output .= '<div class="row" id="supplierName">';
      $output .= '<label for="code" class="col-2 col-form-label">' . $this->app->getDef('text_suppliers_suppliers_name') . '</label>';
      $output .= '<div class="col-md-5">';
      $output .= HTML::selectField('suppliers_id', $suppliers_name_array, $suppliers_id ?? 0);
      $output .= '</div>';
      $output .= '</div>';
    }

    $output = <<<EOD
<!-- ######################## -->
<!--  Start Suppliers Hooks      -->
<!-- ######################## -->
<script>
$('#manufacturersLanguage').append(
    '{$output}'
);
</script>
<!-- ######################## -->
<!--  End Suppliers hoosk      -->
<!-- ######################## -->
EOD;

    return $output;
  }
}