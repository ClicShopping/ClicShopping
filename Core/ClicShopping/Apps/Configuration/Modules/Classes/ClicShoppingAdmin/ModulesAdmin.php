<?php
/**
 * Class ModulesAdmin
 *
 * Provides functionality to manage and retrieve specific module types within the admin configuration.
 */

namespace ClicShopping\Apps\Configuration\Modules\Classes\ClicShoppingAdmin;

use ClicShopping\OM\OrderTotalSequence;
use ClicShopping\OM\Registry;

class ModulesAdmin
{
  /**
   * Read-only migration diagnostic: the installed order total modules whose stored position
   * contradicts their declared fiscal rank.
   *
   * Existing chains are never reordered behind the merchant's back, so without this a sequence
   * inherited from an earlier version stays silently wrong. It reports, it never writes.
   *
   * @return array<int, string>
   */
  public static function misplacedOrderTotalModules(): array
  {
    if (!\defined('MODULE_ORDER_TOTAL_INSTALLED') || MODULE_ORDER_TOTAL_INSTALLED === null) {
      return [];
    }

    return array_column(
      OrderTotalSequence::misplaced(explode(';', (string)MODULE_ORDER_TOTAL_INSTALLED)),
      'module'
    );
  }

  /**
   * Reconcile MODULE_{TYPE}_INSTALLED with what the listing screen actually found installed.
   *
   * $reorderable is false for the order total chain and that is the whole point: there, the stored
   * order IS the order of CALCULATION, decided at install time from each module's declared fiscal
   * role (OM\OrderTotalSequence). Re-sorting it by sort_order would silently move every existing
   * shop's tax base — sort_order governs the printed line only.
   *
   * @param array<int|string, string> $installed_modules
   */
  public static function syncInstalledModules(string $module_key, array $installed_modules, bool $reorderable): void
  {
    if (!$reorderable) {
      return;
    }

    ksort($installed_modules);

    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->get('configuration', 'configuration_value', ['configuration_key' => $module_key]);

    if ($Qcheck->fetch() !== false) {
      if ($Qcheck->value('configuration_value') != implode(';', $installed_modules)) {
        $CLICSHOPPING_Db->save('configuration', [
          'configuration_value' => implode(';', $installed_modules),
          'last_modified' => 'now()'
        ],
          ['configuration_key' => $module_key]
        );
      }
    } else {
      $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'Installed Modules',
          'configuration_key' => $module_key,
          'configuration_value' => implode(';', $installed_modules),
          'configuration_description' => 'This is automatically updated. No need to edit.',
          'configuration_group_id' => 6,
          'sort_order' => 0,
          'date_added' => 'now()'
        ]
      );
    }
  }

  /**
   * Register the module type in TEMPLATE_BLOCK_GROUPS when it renders template blocks.
   */
  public static function syncTemplateBlockGroups(string $module_type): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->get('configuration', 'configuration_value', ['configuration_key' => 'TEMPLATE_BLOCK_GROUPS']);

    if ($Qcheck->fetch() !== false) {
      $tbgroups_array = explode(';', $Qcheck->value('configuration_value'));

      if (!\in_array($module_type, $tbgroups_array)) {
        $tbgroups_array[] = $module_type;
        sort($tbgroups_array);

        $CLICSHOPPING_Db->save('configuration', [
          'configuration_value' => implode(';', $tbgroups_array),
          'last_modified' => 'now()'
        ],
          ['configuration_key' => 'TEMPLATE_BLOCK_GROUPS']
        );
      }
    } else {
      $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'Installed Template Block Groups',
          'configuration_key' => 'TEMPLATE_BLOCK_GROUPS',
          'configuration_value' => $module_type,
          'configuration_description' => 'This is automatically updated. No need to edit.',
          'configuration_group_id' => 6,
          'sort_order' => 0,
          'date_added' => 'now()'
        ]
      );
    }
  }

  /**
   * @param string|null $module_type
   * @return string|null
   */
  public function getSwitchModules(?string $module_type): ?string
  {
    $appModuleType = null;

    switch ($module_type) {
      case 'dashboard':
        $appModuleType = 'AdminDashboard';
        break;
      case 'header_tags':
        $appModuleType = 'HeaderTags';
        break;
      case 'payment':
        $appModuleType = 'Payment';
        break;

      case 'shipping':
        $appModuleType = 'Shipping';
        break;

      case 'order_total':
        $appModuleType = 'OrderTotal';
        break;
    }

    return $appModuleType;
  }
}