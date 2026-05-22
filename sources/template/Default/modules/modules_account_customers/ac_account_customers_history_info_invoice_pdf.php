<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Pages\Account\Classes\HistoryInfo;

class ac_account_customers_history_info_invoice_pdf
{

  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_account_customers_history_info_invoice_pdf_title');
    $this->description = CLICSHOPPING::getDef('module_account_customers_history_info_invoice_pdf_description');

    if (\defined('MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_TITLE_STATUS')) {
      $this->sort_order = \defined('MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_TITLE_SORT_ORDER') ? (int)MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_TITLE_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_TITLE_STATUS') && MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_TITLE_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($_GET['Account'], $_GET['HistoryInfo'])) {

      $content_width = \defined('MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_CONTENT_WIDTH') ? (int)MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_CONTENT_WIDTH : 12;
// Count the number of the history order
      $count_history_info = HistoryInfo::getHistoryInfoCount();
// Display the pdf type in function the status
      if ($count_history_info > 0) {
        $display_support = HistoryInfo::getDisplayHistoryInfoSupport();
        $order_id = HTML::sanitize($_GET['order_id']);

        if ($display_support == 0) {
          $print_invoice_pdf = HTML::link(CLICSHOPPING::link(null, 'Account&OrderInvoice&order_id=' . (int)$order_id), HTML::Button(null, 'bi bi-printer-fill HistoyInfoPDF'),  'target="_blank" rel="noopener"');
        }
      }

      $account_history = '<!-- Start ac_account_customers_history_info_invoice_pdf --> ' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/account_customers_history_info_invoice_pdf'));
      $account_history .= ob_get_clean();

      $account_history .= '<!-- end ac_account_customers_history_info_invoice_pdf -->' . "\n";

      $CLICSHOPPING_Template->addBlock($account_history, $this->group);

    } // php_self
  } // function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_TITLE_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_TITLE_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the module',
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_TITLE_SORT_ORDER',
        'configuration_value' => '110',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '100',
        'set_function' => '',
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
    return array(
      'MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_TITLE_STATUS',
      'MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_CONTENT_WIDTH',
      'MODULE_ACCOUNT_CUSTOMERS_HISTORY_INFO_INVOICE_PDF_TITLE_SORT_ORDER'
    );
  }
}