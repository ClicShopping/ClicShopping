<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Tools\Backup\Sites\ClicShoppingAdmin\Pages\Home\Actions\Backup;

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\RateLimiter;
use ClicShopping\OM\Registry;

class RestoreNow extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Backup');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    // Rate limiting: 120 seconds between restore operations (more conservative than backup)
    $limiter = new RateLimiter(['restore' => 120]);
    $rate_check = $limiter->check('restore');
    if (!$rate_check['allowed']) {
      $CLICSHOPPING_MessageStack->add($rate_check['message'], 'warning');
      $this->app->redirect('Backup');
      return;
    }

    set_time_limit(0);
    ini_set('max_execution_time', 0);
    ini_set('memory_limit', '512M');

    $backup_directory = CLICSHOPPING::BASE_DIR . 'Work/Backups/';

    // Sanitize file parameter to prevent path traversal
    $raw_filename = $_GET['file'] ?? '';
    $read_from = basename($raw_filename);

    if (empty($read_from) || $read_from !== $raw_filename) {
      $CLICSHOPPING_MessageStack->add('Invalid backup file name', 'error');
      $this->app->redirect('Backup');
      return;
    }

    if (is_file($backup_directory . $read_from)) {
      $restore_file = $backup_directory . $read_from;
      $extension = substr($read_from, -3);

      if ($extension == 'sql' || $extension == '.gz' || $extension == 'zip') {
        switch ($extension) {
          case 'sql':
            $restore_from = $restore_file;
            $remove_raw = false;
            break;
          case '.gz':
            $restore_from = substr($restore_file, 0, -3);
            exec(LOCAL_EXE_GUNZIP . ' ' . $restore_file . ' -c > ' . $restore_from);
            $remove_raw = true;
            break;
          case 'zip':
            $restore_from = substr($restore_file, 0, -4);
            exec(LOCAL_EXE_UNZIP . ' ' . $restore_file . ' -d ' . $backup_directory);
            $remove_raw = true;
        }

        if (isset($restore_from) && is_file($restore_from) && filesize($restore_from) > 15000) {
          $fd = fopen($restore_from, 'rb');
          $restore_query = fread($fd, filesize($restore_from));
          fclose($fd);
        } else {
          $CLICSHOPPING_MessageStack->add('Restore file not found or invalid', 'error');
          $this->app->redirect('Backup');
          return;
        }
      } else {
        $CLICSHOPPING_MessageStack->add('Invalid backup file format', 'error');
        $this->app->redirect('Backup');
        return;
      }
    } else {
      $CLICSHOPPING_MessageStack->add('Backup file not found', 'error');
      $this->app->redirect('Backup');
      return;
    }

    if (isset($restore_query)) {
      $sql_array = [];
      $drop_table_names = [];
      $sql_length = \strlen($restore_query);
      $pos = strpos($restore_query, ';');

      if ($pos === false) {
        $CLICSHOPPING_MessageStack->add('Invalid backup file format - no SQL statements found', 'error');
        $this->app->redirect('Backup');
        return;
      }

      for ($i = $pos; $i < $sql_length; $i++) {
        if ($restore_query[0] == '#') {
          $restore_query = ltrim(substr($restore_query, strpos($restore_query, "\n")));
          $sql_length = \strlen($restore_query);
          $i = strpos($restore_query, ';') - 1;
          continue;
        }

        // Bounds check to prevent "Uninitialized string offset" warning
        if ($i + 1 >= $sql_length) {
          break;
        }

        if ($restore_query[$i + 1] == "\n") {
          for ($j = $i + 2; $j < $sql_length; $j++) {
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
                $restore_query = "{$query}{$restore_query}";
                $sql_length = \strlen($restore_query);
                $i = strpos($restore_query, ';') - 1;
                continue 2;
              }
              break;
            }
          }

          if (empty($next)) {
            $next = 'insert';
          }

          if (preg_match('/create/i', $next) || preg_match('/insert/i', $next) || preg_match('/drop t/i', $next)) {
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

      // Validate and execute SQL statements safely
      $allowed_statements = ['CREATE', 'DROP', 'INSERT', 'UPDATE', 'DELETE', 'ALTER', 'TRUNCATE'];
      
      for ($i = 0, $n = \count($sql_array); $i < $n; $i++) {
        // Prevent timeouts during large restores
        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $sql = trim($sql_array[$i]);
        if (empty($sql)) continue;

        // Extract statement type
        $stmt_type = strtoupper(trim(explode(' ', $sql)[0]));
        
        // Whitelist allowed SQL types
        if (!in_array($stmt_type, $allowed_statements)) {
          $CLICSHOPPING_MessageStack->add('Restore skipped: unsupported SQL statement type (' . $stmt_type . ')', 'warning');
          continue;
        }

        // Validate DROP TABLE statements to prevent SQL injection
        if ($stmt_type === 'DROP' || $stmt_type === 'DROP TABLE') {
          if (!preg_match('/^DROP\s+(TABLE\s+IF\s+EXISTS\s+)?[\w, ]+$/i', $sql)) {
            $CLICSHOPPING_MessageStack->add('Restore skipped: invalid DROP statement structure', 'warning');
            continue;
          }
        }

        try {
          $this->app->db->exec($sql);
        } catch (\Exception $e) {
          $CLICSHOPPING_MessageStack->add('Restore failed at statement ' . ($i + 1) . ': ' . $e->getMessage(), 'error');
          $this->app->redirect('Backup');
          return;
        }
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

      if (isset($remove_raw) && $remove_raw === true) {
        unlink($restore_from);
      }

      $CLICSHOPPING_MessageStack->add($this->app->getDef('success_database_restored'), 'success');
    }

    Cache::clear('configuration');

    $this->app->redirect('Backup');
  }
}
