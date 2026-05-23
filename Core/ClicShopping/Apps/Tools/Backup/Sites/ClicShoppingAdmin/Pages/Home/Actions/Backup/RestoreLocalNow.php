<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Backup\Sites\ClicShoppingAdmin\Pages\Home\Actions\Backup;

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Upload;

class RestoreLocalNow extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Backup');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    set_time_limit(0);

    $sql_file = new Upload('sql_file', CLICSHOPPING::BASE_DIR . 'Work/Backups/');

    if ($sql_file->check() && $sql_file->save()) {
      $restore_query = fread(fopen(CLICSHOPPING::BASE_DIR . 'Work/Backups/' . $sql_file->getFilename(), 'r'), filesize(CLICSHOPPING::getConfig('dir_root', 'ClicShoppingAdmin') . 'Core/backups/' . $sql_file->getFilename()));
      $read_from = CLICSHOPPING::BASE_DIR . 'Work/Backups/' . $sql_file->getFilename();
    }

    if (isset($restore_query)) {
      $sql_array = [];
      $drop_table_names = [];
      $sql_length = \strlen($restore_query);
      $pos = strpos($restore_query, ';');

      for ($i = $pos; $i < $sql_length; $i++) {
        if ($restore_query[0] == '#') {
          $restore_query = ltrim(substr($restore_query, strpos($restore_query, "\n")));
          $sql_length = \strlen($restore_query);
          $i = strpos($restore_query, ';') - 1;
          continue;
        }

        if ($restore_query[($i + 1)] == "\n") {
          for ($j = ($i + 2); $j < $sql_length; $j++) {
            if (trim($restore_query[$j]) != '') {
              $next = substr($restore_query, $j, 6);

              if ($next[0] == '#') {
// find out where the break position is so we can remove this line (#comment line)
                for ($k = $j; $k < $sql_length; $k++) {
                  if ($restore_query[$k] == "\n") break;
                }
                $query = substr($restore_query, 0, $i + 1);
                $restore_query = substr($restore_query, $k);
// join the query before the comment appeared, with the rest of the dump
                $restore_query = $query . $restore_query;
                $sql_length = \strlen($restore_query);
                $i = strpos($restore_query, ';') - 1;
                continue 2;
              }
              break;
            }
          }

          if (empty($next)) { // get the last insert query
            $next = 'insert';
          }

          if ((preg_match('/create/i', $next)) || (preg_match('/insert/i', $next)) || (preg_match('/drop t/i', $next))) {
            $query = substr($restore_query, 0, $i);

            $next = '';
            $sql_array[] = $query;
            $restore_query = ltrim(substr($restore_query, $i + 1));
            $sql_length = \strlen($restore_query);
            $i = strpos($restore_query, ';') - 1;

            if (preg_match('/^create*/i', $query)) {
              $table_name = trim(substr($query, stripos($query, 'table ') + 6));
              $table_name = substr($table_name, 0, strpos($table_name, ' '));

              $drop_table_names[] = $table_name;
            }
          }
        }
      }

      $this->app->db->exec('drop table if exists ' . implode(', ', $drop_table_names));

      for ($i = 0, $n = \count($sql_array); $i < $n; $i++) {
        $this->app->db->exec($sql_array[$i]);
      }

      session_write_close();

      $this->app->db->delete('whos_online');
      $this->app->db->delete('sessions');

      $this->app->db->delete('configuration', ['configuration_key' => 'DB_LAST_RESTORE']);

      $this->app->db->save('configuration', [
          'configuration_title' => 'Last Database Restore',
          'configuration_key' => 'DB_LAST_RESTORE',
          'configuration_value' => $read_from,
          'configuration_description' => 'Last database restore file',
          'configuration_group_id' => '6',
          'date_added' => 'now()'
        ]
      );

      $CLICSHOPPING_MessageStack->add($this->app->getDef('success_database_restored'), 'success');
    }

    Cache::clear('configuration');

    $this->app->redirect('Backup');
  }
}