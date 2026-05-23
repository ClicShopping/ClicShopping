<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function is_null;

class Cron
{
  /**
   * @var mixed|null
   */
  private $Cronjob;

  public function __construct()
  {
    // $this->Cronjob = Registry::get('Cronjob');
  }

  /**
   * Retrieves a list of cron job records from the database, optionally filtered by ID
   * and/or paginated using start and limit parameters.
   *
   * @param array|null $data An optional associative array that may include 'start'
   *                         for the starting position and 'limit' for the number
   *                         of records to retrieve. Defaults to an empty array.
   * @param int|null $id Optional ID of the cron job to retrieve. If provided, it
   *                     filters the result to only include the specified cron job.
   *
   * @return array An array of cron job records retrieved from the database,
   *               including fields like cron_id, code, cycle, action, status,
   *               date_added, and date_modified.
   */
  public static function getCrons(?array $data = [],  int|null $id): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $limit = '';
    $where = '';

    if (!is_null($id)) {
      $where = 'where cron_id = ' . HTML::sanitize($_GET['cronId']);
    }

    if (isset($data['start']) || isset($data['limit'])) {
      if ($data['start'] < 0) {
        $data['start'] = 0;
      }

      if ($data['limit'] < 1) {
        $data['limit'] = 20;
      }

      $limit = 'limit ' . (int)$data['start'] . "," . (int)$data['limit'];
    }

    $Qcron = $CLICSHOPPING_Db->prepare('select SQL_CALC_FOUND_ROWS cron_id,
                                                                      code,
                                                                      cycle,
                                                                      action,
                                                                      status,
                                                                      description,
                                                                      date_added,
                                                                      date_modified
                                             from :table_cron
                                             ' . $where . '    
                                             ' . $limit . '
                                          ');

    $Qcron->execute();

    $cron_array = $Qcron->fetchAll();

    return $cron_array;
  }

  /**
   * Retrieves the cron ID associated with the given code.
   *
   * @param string $code The code used to identify the cron entry.
   * @return int The ID of the cron entry.
   */
  public static function getCronCode(string $code): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcronCode = $CLICSHOPPING_Db->prepare('select cron_id
                                              from :table_cron
                                              where code = :code
                                             ');

    $QcronCode->bindValue(':code', $code);
    $QcronCode->execute();

    $result = $QcronCode->valueInt('cron_id');

    return $result;
  }

  /**
   * Updates the date_modified field of a specific cron entry in the database.
   *
   * @param int $cron_id The ID of the cron entry to be updated.
   * @return void This method does not return a value.
   */
  public static function updateCron(int $cron_id): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $sql_data_array = ['date_modified' => 'now()'];

    $update_array = ['cron_id' => $cron_id];

    $CLICSHOPPING_Db->save('cron', $sql_data_array, $update_array);
  }

  /**
   * Retrieves the total number of crons from the database.
   *
   * @return int The total count of crons.
   */
  public static function getTotalCrons(): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qquery = $CLICSHOPPING_Db->prepare('select count(*) as total 
                                           from :table_cron
                                          ');
    $Qquery->execute();

    return $Qquery->valueInt('total');
  }

  /**
   * Updates the status of a cron job based on the provided status.
   *
   * @param int $cron_id The ID of the cron job to update.
   * @param int $status The current status of the cron job (0 or 1).
   * @return mixed Returns the result of the database save operation on success, or -1 if the status is invalid.
   */
  public static function getCronjobStatus(int $cron_id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status === 0) {
      return $CLICSHOPPING_Db->save('cron',
        ['status' => 1,
          'date_modified' => 'now()'
        ],
        ['cron_id' => (int)$cron_id]
      );
    } elseif ($status === 1) {
      return $CLICSHOPPING_Db->save('cron',
        ['status' => 0,
          'date_modified' => 'now()'
        ],
        ['cron_id' => (int)$cron_id]
      );
    } else {
      return -1;
    }
  }
}
