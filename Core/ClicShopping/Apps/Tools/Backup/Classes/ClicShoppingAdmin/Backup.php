<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Backup\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use function defined;
use function strlen;

class Backup
{
  /**
   * Creates a backup of the current database and stores it in the specified backup directory.
   * The backup process includes the structure and content of all tables in the database.
   * Optionally compresses the resulting file and provides a download option if requested.
   * 
   * Enhanced features:
   * - Preserves column comments from INFORMATION_SCHEMA.COLUMNS
   * - Preserves table comments from INFORMATION_SCHEMA.TABLES
   * - Supports VECTOR column types with proper index definitions
   * - Handles vector data serialization correctly
   * - Generates backup statistics and validation report
   * 
   * Restore: Comments are automatically restored when the backup SQL file is executed,
   * as they are included in the CREATE TABLE statements.
   *
   * @return void
   */
  public static function backupNow(): void
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Db = Registry::get('Db');

    set_time_limit(0);
    ini_set('max_execution_time', 0);
    ini_set('memory_limit', '512M');

    $backup_directory = CLICSHOPPING::BASE_DIR . 'Work/Backups/';
    $backup_file = 'db_' . CLICSHOPPING::getConfig('db_database') . '-' . date('YmdHis') . '.sql';

    // Initialize backup statistics
    $backup_stats = [
      'tables_backed_up' => 0,
      'tables_with_comments' => 0,
      'columns_with_comments' => 0,
      'vector_columns' => 0,
      'total_rows' => 0,
      'start_time' => microtime(true)
    ];

    $fp = fopen($backup_directory . $backup_file, 'w');

    $schema = '# ClicShopping, E-Commerce Solutions' . "\n" .
      '# https://www.clicshopping.org' . "\n" .
      '#' . "\n" .
      '# Database Backup For ' . STORE_NAME . "\n" .
      '# Copyright (c) ' . date('Y') . ' ' . STORE_OWNER . "\n" .
      '#' . "\n" .
      '# Database: ' . CLICSHOPPING::getConfig('db_database') . "\n" .
      '# Database Server: ' . CLICSHOPPING::getConfig('db_server') . "\n" .
      '#' . "\n" .
      '# Backup Date: ' . date('m/d/Y H:i:s') . "\n\n";
    fputs($fp, $schema);

    $Qtables = $CLICSHOPPING_Db->get(['INFORMATION_SCHEMA.TABLES t',
      'INFORMATION_SCHEMA.COLLATION_CHARACTER_SET_APPLICABILITY ccsa'
    ],
      ['t.TABLE_NAME',
        't.ENGINE',
        't.TABLE_COLLATION',
        't.TABLE_COMMENT',
        'ccsa.CHARACTER_SET_NAME'
      ],
      ['t.TABLE_SCHEMA' => CLICSHOPPING::getConfig('db_database'),
        't.TABLE_COLLATION' => [
          'rel' => 'ccsa.COLLATION_NAME'
        ]
      ], null, null, null,
      ['prefix_tables' => false]
    );

    while ($Qtables->fetch()) {
      $table = $Qtables->value('TABLE_NAME');
      $tableComment = $Qtables->value('TABLE_COMMENT');
      
      $backup_stats['tables_backed_up']++;
      if (strlen($tableComment) > 0) {
        $backup_stats['tables_with_comments']++;
      }

      $schema = 'drop table if exists ' . $table . ';' . "\n" .
        'create table ' . $table . ' (' . "\n";

      $table_list = [];

      // Get column information including comments
      $Qfields = $CLICSHOPPING_Db->query("
        SELECT 
          COLUMN_NAME,
          COLUMN_TYPE,
          IS_NULLABLE,
          COLUMN_DEFAULT,
          EXTRA,
          COLUMN_COMMENT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '" . CLICSHOPPING::getConfig('db_database') . "'
          AND TABLE_NAME = '" . $table . "'
        ORDER BY ORDINAL_POSITION ASC
      ");

      while ($Qfields->fetch()) {
        $table_list[] = $Qfields->value('COLUMN_NAME');
        $columnType = $Qfields->value('COLUMN_TYPE');
        $columnComment = $Qfields->value('COLUMN_COMMENT');

        // Track statistics
        if (strlen($columnComment) > 0) {
          $backup_stats['columns_with_comments']++;
        }
        if (str_contains(strtolower($columnType), 'vector')) {
          $backup_stats['vector_columns']++;
        }

        $schema .= '  ' . $Qfields->value('COLUMN_NAME') . ' ' . $columnType;

        if (!\is_null($Qfields->value('COLUMN_DEFAULT'))) {
          $default_value = $Qfields->value('COLUMN_DEFAULT');
          
          // Check if column type is numeric (int, decimal, float, etc.)
          $is_numeric_type = preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|real)/i', $columnType);
          
          // Check if column type is date/time (datetime, date, time, timestamp, year)
          $is_datetime_type = preg_match('/^(datetime|date|time|timestamp|year)/i', $columnType);
          
          // Check if column type is ENUM or SET
          $is_enum_type = preg_match('/^(enum|set)/i', $columnType);
          
          // Check if column type is TEXT or BLOB (cannot have default values in MySQL)
          $is_text_blob_type = preg_match('/^(text|tinytext|mediumtext|longtext|blob|tinyblob|mediumblob|longblob)/i', $columnType);
          
          // Check if column type is VECTOR (cannot have empty string default)
          $is_vector_type = preg_match('/^vector/i', $columnType);
          
          // Check if default value is already quoted (starts and ends with single quote)
          $is_already_quoted = (strlen($default_value) >= 2 && 
                                substr($default_value, 0, 1) === "'" && 
                                substr($default_value, -1) === "'");
          
          // Skip empty string defaults for numeric types (invalid SQL)
          if ($is_numeric_type && $default_value === '') {
            // Don't add default clause - let MySQL use its default behavior
          } elseif ($is_datetime_type && $default_value === '') {
            // Don't add default clause for datetime types with empty string (invalid SQL)
          } elseif ($is_enum_type && $default_value === '') {
            // Don't add default clause for ENUM/SET with empty string (invalid unless '' is an allowed value)
          } elseif ($is_text_blob_type) {
            // TEXT/BLOB types cannot have default values in MySQL - skip entirely
          } elseif ($is_vector_type && $default_value === '') {
            // VECTOR types cannot have empty string default - skip
          } elseif ($is_numeric_type && is_numeric($default_value)) {
            // For numeric types with numeric defaults, don't quote
            $schema .= ' default ' . $default_value;
          } elseif (strtoupper($default_value) === 'CURRENT_TIMESTAMP' || strtoupper($default_value) === 'NULL') {
            // Special SQL keywords - don't quote
            $schema .= ' default ' . $default_value;
          } elseif ($is_already_quoted) {
            // Default value is already quoted (e.g., '_self') - use as-is
            // Extract the inner value, escape it, and re-quote
            $inner_value = substr($default_value, 1, -1);
            $escaped_value = addslashes($inner_value);
            $schema .= ' default \'' . $escaped_value . '\'';
          } else {
            // String defaults - quote them and escape
            $escaped_value = addslashes($default_value);
            $schema .= ' default \'' . $escaped_value . '\'';
          }
        }

        if ($Qfields->value('IS_NULLABLE') != 'YES') $schema .= ' not null';

        if (strlen($Qfields->value('EXTRA')) > 0) $schema .= ' ' . $Qfields->value('EXTRA');

        // Add COMMENT clause if column has a comment
        if (strlen($columnComment) > 0) {
          $comment = addslashes($columnComment);
          $schema .= ' COMMENT \'' . $comment . '\'';
        }

        $schema .= ',' . "\n";
      }

      $schema = preg_replace("/,\n$/", '', $schema);

      // add the keys
      $index = [];

      $Qkeys = $CLICSHOPPING_Db->query('show keys from ' . $table);

      while ($Qkeys->fetch()) {
        $kname = $Qkeys->value('Key_name');

        if (!isset($index[$kname])) {
          $index[$kname] = array('unique' => $Qkeys->valueInt('Non_unique') === 0,
            'fulltext' => ($Qkeys->value('Index_type') == 'FULLTEXT' ? '1' : '0'),
            'vector' => ($Qkeys->value('Index_type') == 'VECTOR' ? '1' : '0'),
            'columns' => array());
        }

        $index[$kname]['columns'][] = $Qkeys->value('Column_name');
      }

      foreach ($index as $kname => $info) {
        $schema .= ',' . "\n";

        $columns = implode(', ', $info['columns']);

        if ($kname == 'PRIMARY') {
          $schema .= '  PRIMARY KEY (' . $columns . ')';
        } elseif ($info['fulltext'] == '1') {
          $schema .= '  FULLTEXT ' . $kname . ' (' . $columns . ')';
        } elseif ($info['vector'] == '1') {
          $schema .= '  VECTOR INDEX ' . $kname . ' (' . $columns . ')';
        } elseif ($info['unique']) {
          $schema .= '  UNIQUE ' . $kname . ' (' . $columns . ')';
        } else {
          $schema .= '  KEY ' . $kname . ' (' . $columns . ')';
        }
      }

      $schema .= "\n" . ') ENGINE=' . $Qtables->value('ENGINE') . ' CHARACTER SET ' . $Qtables->value('CHARACTER_SET_NAME') . ' COLLATE ' . $Qtables->value('TABLE_COLLATION');
      
      // Add table COMMENT if present
      if (strlen($tableComment) > 0) {
        $escapedTableComment = addslashes($tableComment);
        $schema .= ' COMMENT=\'' . $escapedTableComment . '\'';
      }
      
      $schema .= ';' . "\n\n";

      fputs($fp, $schema);

      // dump the data
      if (($table != CLICSHOPPING::getConfig('db_table_prefix') . 'sessions') && ($table != CLICSHOPPING::getConfig('db_table_prefix') . 'whos_online')) {
        // Get column types to handle VECTOR columns specially
        $columnTypes = [];
        $Qfields->execute();
        while ($Qfields->fetch()) {
          $columnTypes[$Qfields->value('COLUMN_NAME')] = $Qfields->value('COLUMN_TYPE');
        }
        
        $Qrows = $CLICSHOPPING_Db->get($table, $table_list, null, null, null, null, ['prefix_tables' => false]);
        
        $row_count = 0;
        while ($Qrows->fetch()) {
          set_time_limit(0);
          ini_set('max_execution_time', 0);
          $row_count++;
          $schema = 'insert into ' . $table . ' (' . implode(', ', $table_list) . ') values (';

          foreach ($table_list as $i) {
            if (!$Qrows->hasValue($i)) {
              $schema .= 'NULL, ';
            } elseif (!\is_null($Qrows->value($i))) {
              $row = $Qrows->value($i);
              
              // Check if this is a VECTOR column
              if (isset($columnTypes[$i]) && strpos(strtolower($columnTypes[$i]), 'vector') !== false) {
                // VECTOR data is already in the correct format from MariaDB
                // Just escape it properly
                $row = addslashes($row);
              } else {
                $row = addslashes($row);
                $row = preg_replace("/\n#/", "\n" . '\#', $row);
              }

              $schema .= '\'' . $row . '\', ';
            } else {
              $schema .= '\'\', ';
            }
          }

          $schema = preg_replace('/, $/', '', $schema) . ');' . "\n";
          fputs($fp, $schema);
        }
        
        $backup_stats['total_rows'] += $row_count;
      }
    }

    // Calculate backup statistics
    $backup_stats['end_time'] = microtime(true);
    $backup_stats['duration_seconds'] = round($backup_stats['end_time'] - $backup_stats['start_time'], 2);
    
    // Write backup statistics as SQL comments at the end of the file
    $stats_comment = "\n\n" .
      "# ============================================\n" .
      "# Backup Statistics\n" .
      "# ============================================\n" .
      "# Tables backed up: " . $backup_stats['tables_backed_up'] . "\n" .
      "# Tables with comments: " . $backup_stats['tables_with_comments'] . "\n" .
      "# Columns with comments: " . $backup_stats['columns_with_comments'] . "\n" .
      "# VECTOR columns: " . $backup_stats['vector_columns'] . "\n" .
      "# Total rows: " . $backup_stats['total_rows'] . "\n" .
      "# Duration: " . $backup_stats['duration_seconds'] . " seconds\n" .
      "# Completed: " . date('Y-m-d H:i:s') . "\n" .
      "# ============================================\n";
    
    fputs($fp, $stats_comment);

    fclose($fp);
    
    // Log backup statistics
    $log_message = sprintf(
      "Database backup completed: %d tables (%d with comments), %d columns with comments, %d VECTOR columns, %d total rows, %.2f seconds",
      $backup_stats['tables_backed_up'],
      $backup_stats['tables_with_comments'],
      $backup_stats['columns_with_comments'],
      $backup_stats['vector_columns'],
      $backup_stats['total_rows'],
      $backup_stats['duration_seconds']
    );
    
    // Log to file if ErrorHandler is available
    if (Registry::exists('ErrorHandler')) {
      Registry::get('ErrorHandler')->log($log_message, 'backup');
    } else {
      // Fallback: log to error_log
      error_log('[Backup] ' . $log_message);
    }

    if (isset($_POST['compress'])) {
      $compress = $_POST['compress'];
    } else {
      $compress = 'gzip';
    }

    if (!defined('LOCAL_EXE_GZIP')) {
      define('LOCAL_EXE_GZIP', 'gzip');
    }

    if (isset($_POST['download'])) {
      switch ($compress) {
        case 'gzip':
          exec(LOCAL_EXE_GZIP . ' ' . $backup_directory . $backup_file);
          $backup_file .= '.gz';
          break;
        case 'zip':
          exec(LOCAL_EXE_ZIP . ' -j ' . $backup_directory . $backup_file . '.zip ' . $backup_directory . $backup_file);
          unlink($backup_directory . $backup_file);
          $backup_file .= '.zip';
      }

      header('Content-type: application/x-octet-stream');
      header('Content-disposition: attachment; filename=' . $backup_file);

      readfile($backup_directory . $backup_file);
      unlink($backup_directory . $backup_file);

      exit;
    } else {
      switch ($compress) {
        case 'gzip':
          exec(LOCAL_EXE_GZIP . ' ' . $backup_directory . $backup_file);
          break;
        case 'zip':
          exec(LOCAL_EXE_ZIP . ' -j ' . $backup_directory . $backup_file . '.zip ' . $backup_directory . $backup_file);
          unlink($backup_directory . $backup_file);
      }

      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_database_saved'), 'success');
    }
  }

  /**
   * Extract backup statistics from a backup file
   *
   * @param string $backup_file Full path to backup file
   * @return array|null Array of statistics or null if not found
   */
  public static function getBackupStatistics(string $backup_file): ?array
  {
    if (!file_exists($backup_file)) {
      return null;
    }

    $stats = [
      'tables_backed_up' => 0,
      'tables_with_comments' => 0,
      'columns_with_comments' => 0,
      'vector_columns' => 0,
      'total_rows' => 0,
      'duration_seconds' => 0,
      'file_size' => filesize($backup_file),
      'compression_ratio' => 0
    ];

    // Read the last 2000 bytes of the file where statistics are written
    $fp = fopen($backup_file, 'r');
    if ($fp) {
      fseek($fp, -min(2000, filesize($backup_file)), SEEK_END);
      $content = fread($fp, 2000);
      fclose($fp);

      // Parse statistics from comments
      if (preg_match('/# Tables backed up: (\d+)/', $content, $matches)) {
        $stats['tables_backed_up'] = (int)$matches[1];
      }
      if (preg_match('/# Tables with comments: (\d+)/', $content, $matches)) {
        $stats['tables_with_comments'] = (int)$matches[1];
      }
      if (preg_match('/# Columns with comments: (\d+)/', $content, $matches)) {
        $stats['columns_with_comments'] = (int)$matches[1];
      }
      if (preg_match('/# VECTOR columns: (\d+)/', $content, $matches)) {
        $stats['vector_columns'] = (int)$matches[1];
      }
      if (preg_match('/# Total rows: (\d+)/', $content, $matches)) {
        $stats['total_rows'] = (int)$matches[1];
      }
      if (preg_match('/# Duration: ([\d.]+) seconds/', $content, $matches)) {
        $stats['duration_seconds'] = (float)$matches[1];
      }

      return $stats;
    }

    return null;
  }
}
