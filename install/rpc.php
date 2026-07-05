<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Db;
use ClicShopping\OM\HTTP;

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

require_once('Core/OM.php');

$dir_fs_www_root = __DIR__;

$result = [
  'status' => '-100',
  'message' => 'noActionError'
];

if (isset($_GET['action']) && !empty($_GET['action'])) {
  switch ($_GET['action']) {
    case 'httpsCheck':
      if (isset($_GET['subaction']) && ($_GET['subaction'] == 'do')) {
        if ((isset($_SERVER['HTTPS']) && (mb_strtolower($_SERVER['HTTPS']) == 'on')) || (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 443))) {
          $result['status'] = '1';
          $result['message'] = 'success';
        }
      } else {
        $url = 'https://' . $_SERVER['HTTP_HOST'];

        if (isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI'])) {
          $url .= $_SERVER['REQUEST_URI'];
        } else {
          $url .= $_SERVER['SCRIPT_FILENAME'];
        }

        $url .= '&subaction=do';

// errors are silenced to not log failed connection checks
        $response = @HTTP::getResponse([
          'url' => $url,
          'verify_ssl' => false
        ]);

        if (!empty($response)) {
          $response = json_decode($response, true);

          if (\is_array($response) && isset($response['status']) && ($response['status'] == '1')) {
            $result['status'] = '1';
            $result['message'] = 'success';
          }
        }
      }

      break;

    case 'dbCheck':
      try {
        // Override SQL mode init command for MariaDB 11.x compatibility
        $driverOptions = [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'];

        $CLICSHOPPING_Db = Db::initialize(
          $_POST['server'] ?? '',
          $_POST['username'] ?? '',
          $_POST['password'] ?? '',
          $_POST['name'] ?? '',
          null,
          $driverOptions,
          ['log_errors' => false]
        );

        $result['status'] = '1';
        $result['message'] = 'success';
      } catch (\Exception $e) {
        $result['status'] = $e->getCode();
        $result['message'] = $e->getMessage();

        if (($e->getCode() == '1049') && isset($_GET['createDb']) && ($_GET['createDb'] == 'true')) {
          try {
            // Create the database with the same safe driver options
            $driverOptions = [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'];
            $CLICSHOPPING_Db = Db::initialize(
              $_POST['server'],
              $_POST['username'],
              $_POST['password'],
              '',
              null,
              $driverOptions,
              ['log_errors' => false]
            );

            $CLICSHOPPING_Db->exec('create database ' . Db::prepareIdentifier($_POST['name']) . ' character set utf8mb4 collate utf8mb4_unicode_ci');

            $result['status'] = '1';
            $result['message'] = 'success';
          } catch (\Exception $e2) {
            $result['status'] = $e2->getCode();
            $result['message'] = $e2->getMessage();
          }
        }
      }

      break;

    case 'dbImport':
      try {
        // ✅ SECURITY FIX: Validate table prefix before use
        $prefix = $_POST['prefix'] ?? '';
        $prefix = trim($prefix);
        
        // Validate prefix format: only alphanumeric and underscore
        if ($prefix !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
          throw new \Exception('Invalid table prefix format. Only alphanumeric characters and underscores are allowed.');
        }
        
        // Limit length (MySQL identifier limit is 64 chars, leave room for table name)
        if (strlen($prefix) > 20) {
          throw new \Exception('Table prefix too long. Maximum 20 characters allowed.');
        }
        
        // Ensure it doesn't start with a number (MySQL requirement)
        if ($prefix !== '' && preg_match('/^[0-9]/', $prefix)) {
          throw new \Exception('Table prefix cannot start with a number.');
        }
        
        // Ensure it ends with underscore for clarity (if not empty)
        if ($prefix !== '' && !str_ends_with($prefix, '_')) {
          $prefix .= '_';
        }
        
        // Use safe init command during import as well
        $driverOptions = [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'];
        $CLICSHOPPING_Db = Db::initialize(
          $_POST['server'] ?? '',
          $_POST['username'] ?? '',
          $_POST['password'] ?? '',
          $_POST['name'] ?? '',
          null,
          $driverOptions
        );
        $CLICSHOPPING_Db->setTablePrefix('');

        $CLICSHOPPING_Db->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (glob(CLICSHOPPING::BASE_DIR . 'Schema/MariaDb/*.txt') as $f) {
          $schema = $CLICSHOPPING_Db->getSchemaFromFile($f);

          // ✅ Use validated prefix
          $sql = $CLICSHOPPING_Db->getSqlFromSchema($schema, $prefix);

          // ✅ Use prepareIdentifier for table name
          $tableName = Db::prepareIdentifier($prefix . basename($f, '.txt'));
          $CLICSHOPPING_Db->exec('DROP TABLE IF EXISTS ' . $tableName);

          $CLICSHOPPING_Db->exec($sql);
        }
        
        if ($language == 'french') {
          $CLICSHOPPING_Db->importSQL($dir_fs_www_root . '/Db/clicshopping.sql', $prefix);
        } else {
          $CLICSHOPPING_Db->importSQL($dir_fs_www_root . '/Db/clicshopping_en.sql', $prefix);
        }

        $CLICSHOPPING_Db->exec('SET FOREIGN_KEY_CHECKS = 1');

        $result['status'] = '1';
        $result['message'] = 'success';
      } catch (\Exception $e) {
        $result['status'] = $e->getCode();
        $result['message'] = $e->getMessage();
      }

      break;
  }
}

echo json_encode($result);
