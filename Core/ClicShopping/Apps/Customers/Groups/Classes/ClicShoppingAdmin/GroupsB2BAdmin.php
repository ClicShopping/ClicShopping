<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use function count;
/**
 * Class GroupsB2BAdmin
 *
 * Provides methods for managing customer groups in the admin panel, including retrieval
 * of customer group names and customer group data.
 */
class GroupsB2BAdmin
{
  protected string $iso;

  /**
   * Retrieves the name of the customer group associated with the given ID.
   *
   * @param int $id The ID of the customer group.
   * @return string The name of the customer group.
   */
  public static function getCustomersGroupName(int $id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcustomersGroup = $CLICSHOPPING_Db->prepare('select customers_group_id,
                                                           customers_group_name
                                                   from :table_customers_groups
                                                   where customers_group_id = :customers_group_id
                                                   order by customers_group_name
                                                  ');
    $QcustomersGroup->bindInt(':customers_group_id', $id);
    $QcustomersGroup->execute();

    return $QcustomersGroup->value('customers_group_name');
  }

  /**
   * Retrieves an array of customer groups from the database.
   *
   * @param string $default The default value to include as the first element of the returned array. If provided, it will be added as an entry with an id of '0'.
   * @return array An array of customer groups, where each group is represented by an associative array with keys 'id' and 'text'.
   */
  public static function getCustomersGroup(string $default = ''): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $customers_group_array = [];

    if (!empty($default)) {
      $customers_group_array[] = [
        'id' => '0',
        'text' => $default
      ];
    }

    $QcustomersGroup = $CLICSHOPPING_Db->prepare('select customers_group_id,
                                                           customers_group_name
                                                   from :table_customers_groups
                                                   order by customers_group_name
                                                  ');

    $QcustomersGroup->execute();

    while ($QcustomersGroup->fetch()) {
      $customers_group_array[] = [
        'id' => $QcustomersGroup->valueInt('customers_group_id'),
        'text' => $QcustomersGroup->value('customers_group_name')
      ];
    }

    return $customers_group_array;
  }

  /**
   * Retrieves all customer groups including a default 'All Groups' entry and a visitor entry.
   *
   * @return array Returns an array of customer groups with id and text for each group.
   */
  public static function getAllGroups(): array
  {
    $customers_group = static::getCustomersGroup();
    $values_customers_group_id = [];

    $customers_group[] = [
      'id' => '99',
      'text' => CLICSHOPPING::getDef('text_all_groups')
    ];

    $values_customers_group_id[0] = [
      'id' => 0,
      'text' => CLICSHOPPING::getDef('visitor_name')
    ];

    for ($i = 0, $n = count($customers_group); $i < $n; $i++) {
      $values_customers_group_id[$i + 1] = [
        'id' => $customers_group[$i]['id'],
        'text' => $customers_group[$i]['text']
      ];
    }

    return $values_customers_group_id;
  }
}

