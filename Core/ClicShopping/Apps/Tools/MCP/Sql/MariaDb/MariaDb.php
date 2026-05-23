<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\MCP\MCP\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the Customers module by loading
   * language definitions and performing database setup operations.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_MCP = Registry::get('MCP');
    $CLICSHOPPING_MCP->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries for the administrator menu related to customer management.
   *
   * Checks if specific menu entries associated with customer-related functionality
   * already exist in the `administrator_menu` table. If not, adds the entries along
   * with their descriptions for all supported languages. Clears the administrator menu cache upon completion.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_MCP = Registry::get('MCP');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_mcp']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Tools\MCP&MCP',
        'image' => '',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_tools_mcp'
      ];

      $insert_sql_data = ['parent_id' => 163];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_MCP->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      Cache::clear('menu-administrator');
    }
}

  /**
   * Installs the database table for customers if it does not already exist.
   *
   * Checks the database for the presence of the customers table. If it does not exist,
   * the method creates the table with the specified schema, defining various customer-related
   * fields, constraints, and default values.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_mcp"');

    if ($Qcheck->fetch() === false) {
    $sql = <<<EOD
CREATE TABLE :table_mcp (
  `mcp_id` int(11) NOT NULL,
  `username` varchar(64) NOT NULL,
  `mcp_key` text NOT NULL,
  `status` tinyint(1) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime DEFAULT NULL,
  `select_data` tinyint(1) NOT NULL,
  `update_data` tinyint(1) NOT NULL,
  `create_data` tinyint(1) NOT NULL,
  `delete_data` tinyint(1) NOT NULL,
  `create_db` tinyint(1) NOT NULL,
  `server_host` varchar(255) NOT NULL DEFAULT 'localhost',
  `server_port` int(6) NOT NULL DEFAULT 3001,
  `ssl_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `alert_threshold` int(6) DEFAULT 20,
  `latency_threshold` int(6) DEFAULT 1000,
  `downtime_threshold` int(6) DEFAULT 300,
  `data_retention` int(3) DEFAULT 7,
  `alert_notification` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO :table_mcp  VALUES (NULL, 'RagBI', 'd0a36b839700b60727fe13998e22aa0af197c61d8b371e26114c133ca51c4864bd0da73ad6d1e5090b02b55cff42b8a0cd23866e64e78fc8884eb6228d32f5e9d76bed468869dd89ee6bb8a3208c5077e88560d0bc238f67cfc732efcf5313a0cb361e297c29c8d82d050d770ed7dee972af6445e801fa9af12e3d478bf5346a', '1', '2025-10-05 13:02:04.000000', '1', '0', '0', '0', '0');
ALTER TABLE :table_mcp  ADD PRIMARY KEY (`mcp_id`),
ADD KEY `idx_mcp_id` (`mcp_id`);

ALTER TABLE :table_mcp MODIFY `mcp_id` int(11) NOT NULL AUTO_INCREMENT;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_mcp_ip"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_mcp_ip (
  `mcp_ip_id` int(11) NOT NULL,
  `mcp_id` int(11) NOT NULL,
  `ip` varchar(40) NOT NULL,
  `comment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO :table_mcp_ip VALUES(1, 1, '127.0.0.1', 'localhost');

ALTER TABLE :table_mcp_ip  ADD PRIMARY KEY (`mcp_ip_id`),  ADD KEY `idx_mcp_ip_id` (`mcp_ip_id`);
ALTER TABLE :table_mcp_ip MODIFY `mcp_ip_id` int(11) NOT NULL AUTO_INCREMENT;

EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_mcp_session"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_mcp_session (
  `mcp_session_id` int(11) NOT NULL,
  `mcp_id` int(11) NOT NULL,
  `session_id` varchar(32) NOT NULL,
  `ip` varchar(40) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE :table_mcp_session ADD PRIMARY KEY (`mcp_session_id`);
ALTER TABLE :table_mcp_session  MODIFY `mcp_session_id` int(11) NOT NULL AUTO_INCREMENT;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_mcp_failed_attempts"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_mcp_failed_attempts (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `attempts` int(11) DEFAULT NULL,
  `last_attempt` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE :table_mcp_failed_attempts  ADD PRIMARY KEY (`id`),  ADD KEY `idx_identifier` (`identifier`);
ALTER TABLE :table_mcp_failed_attempts  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_mcp_rate_limit"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
        CREATE TABLE :table_mcp_rate_limit (
          `id` int(11) NOT NULL,
          `identifier` varchar(255) DEFAULT NULL,
          `timestamp` int(11) DEFAULT NULL,
          `ip` varchar(45) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        ALTER TABLE :table_mcp_rate_limit
          ADD PRIMARY KEY (`id`),
          ADD KEY `idx_identifier_timestamp` (`identifier`,`timestamp`),
          ADD KEY `idx_timestamp` (`timestamp`);
        
        ALTER TABLE :table_mcp_rate_limit
          MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
        EOD;

      $CLICSHOPPING_Db->exec($sql);
    }


    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_mcp_alerts"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
CREATE TABLE :table_mcp_alerts (
  `id` int(11) NOT NULL,
  `mcp_id` int(11) NOT NULL,
  `alert_type` varchar(32) NOT NULL,
  `message` text NOT NULL,
  `alert_timestamp` datetime NOT NULL,
  `severity_level` int(11) NOT NULL DEFAULT 1,
  `context` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE :table_mcp_alerts
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alert_timestamp` (`alert_timestamp`),
  ADD KEY `idx_severity` (`severity_level`),
  ADD KEY `idx_mcp_id` (`mcp_id`);

ALTER TABLE :table_mcp_alerts MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_mcp_performance_history"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
       CREATE TABLE :table_mcp_performance_history (
          `id` int(11) NOT NULL,
          `timestamp` int(11) NOT NULL,
          `request_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
          `average_latency` decimal(10,2) NOT NULL DEFAULT 0.00,
          `error_frequency` decimal(5,2) NOT NULL DEFAULT 0.00,
          `uptime_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
          `total_requests` int(11) NOT NULL DEFAULT 0,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        ALTER TABLE :table_mcp_performance_history
        ADD PRIMARY KEY (`id`),
        ADD KEY `idx_timestamp` (`timestamp`),
        ADD KEY `idx_created_at` (`created_at`);
        
        ALTER TABLE :table_mcp_performance_history MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}