<?php
/**
 * HTTP-Accessible Password Migration Script
 * Migrates users from 'salt' algorithm to 'bcrypt'
 * 
 * SECURITY WARNINGS:
 * 1. This file should be DELETED after migration is complete
 * 2. Access should be restricted by IP or authentication
 * 3. Run this script only once during maintenance window
 * 4. Backup database before running
 * 
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * 
 * Date: 2026-04-28
 * Security Fix: Migrate from 'salt' to 'bcrypt'
 */

// ============================================================================
// SECURITY CONFIGURATION - MODIFY THESE VALUES
// ============================================================================

// Set a secret token to prevent unauthorized access
// Generate with: php -r "echo bin2hex(random_bytes(32));"
define('MIGRATION_SECRET_TOKEN', 'CHANGE_THIS_TO_RANDOM_64_CHAR_STRING');

// Allowed IP addresses (empty array = allow all - NOT RECOMMENDED)
define('ALLOWED_IPS', [
    '127.0.0.1',
    '::1',
    // Add your server IP here
    // '192.168.1.100',
]);

// Enable/disable actual migration (set to false for dry-run)
define('ENABLE_MIGRATION', false); // Change to true to enable actual migration

// ============================================================================
// DO NOT MODIFY BELOW THIS LINE
// ============================================================================

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Hash;

// Initialize ClicShopping
define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', __DIR__ . '/Core/ClicShopping/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('Shop');

// ============================================================================
// SECURITY CHECKS
// ============================================================================

/**
 * Check if access is authorized
 */
function checkAuthorization(): array
{
    $errors = [];
    
    // Check secret token
    $providedToken = $_GET['token'] ?? '';
    if ($providedToken !== MIGRATION_SECRET_TOKEN) {
        $errors[] = 'Invalid or missing security token';
    }
    
    if (MIGRATION_SECRET_TOKEN === 'CHANGE_THIS_TO_RANDOM_64_CHAR_STRING') {
        $errors[] = 'Security token not configured. Edit this file and set MIGRATION_SECRET_TOKEN.';
    }
    
    // Check IP address
    if (!empty(ALLOWED_IPS)) {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!in_array($clientIp, ALLOWED_IPS, true)) {
            $errors[] = "Access denied from IP: {$clientIp}";
        }
    }
    
    // Check if migration is enabled
    if (!ENABLE_MIGRATION && ($_GET['action'] ?? '') === 'migrate') {
        $errors[] = 'Migration is disabled. Set ENABLE_MIGRATION to true in this file.';
    }
    
    return $errors;
}

/**
 * Output HTML header
 */
function outputHeader(string $title): void
{
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #34495e;
            margin-top: 30px;
        }
        .error {
            background: #fee;
            border-left: 4px solid #c00;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning {
            background: #ffc;
            border-left: 4px solid #fa0;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success {
            background: #efe;
            border-left: 4px solid #0c0;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #3498db;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px 5px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-success:hover {
            background: #229954;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: #ecf0f1;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #3498db;
        }
        .stat-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: "Courier New", monospace;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">';
}

/**
 * Output HTML footer
 */
function outputFooter(): void
{
    echo '
    </div>
    <div style="text-align: center; margin-top: 20px; color: #7f8c8d;">
        <p>ClicShopping AI™ - Password Migration Tool</p>
        <p style="font-size: 12px;">⚠️ Delete this file after migration is complete</p>
    </div>
</body>
</html>';
}

/**
 * Analyze affected users
 */
function analyzeUsers(): array
{
    $db = Registry::get('Db');
    
    $results = [
        'customers' => [],
        'administrators' => [],
        'total' => 0
    ];
    
    // Check customers table
    try {
        $query = "
            SELECT 
                customers_id as id,
                customers_email_address as email,
                customers_password as password,
                date_account_created as created,
                'customer' as type
            FROM 
                :table_customers
            WHERE 
                customers_password REGEXP '^[A-F0-9]{32}:[A-F0-9]{2}$'
            ORDER BY 
                date_account_created DESC
        ";
        
        $result = $db->query($query);
        $results['customers'] = $result->fetchAll();
    } catch (Exception $e) {
        $results['customers_error'] = $e->getMessage();
    }
    
    // Check administrators table
    try {
        $query = "
            SELECT 
                id,
                user_name as email,
                user_password as password,
                date_added as created,
                'administrator' as type
            FROM 
                :table_administrators
            WHERE 
                user_password REGEXP '^[A-F0-9]{32}:[A-F0-9]{2}$'
            ORDER BY 
                date_added DESC
        ";
        
        $result = $db->query($query);
        $results['administrators'] = $result->fetchAll();
    } catch (Exception $e) {
        $results['administrators_error'] = $e->getMessage();
    }
    
    $results['total'] = count($results['customers']) + count($results['administrators']);
    
    return $results;
}

/**
 * Perform migration
 */
function performMigration(): array
{
    $db = Registry::get('Db');
    
    $results = [
        'customers' => ['success' => 0, 'failed' => 0],
        'administrators' => ['success' => 0, 'failed' => 0],
        'errors' => []
    ];
    
    // Migrate customers
    try {
        $query = "
            UPDATE :table_customers
            SET 
                customers_password = '',
                date_account_last_modified = NOW()
            WHERE 
                customers_password REGEXP '^[A-F0-9]{32}:[A-F0-9]{2}$'
        ";
        
        $db->query($query);
        $results['customers']['success'] = $db->rowCount();
    } catch (Exception $e) {
        $results['customers']['failed'] = 1;
        $results['errors'][] = 'Customers migration error: ' . $e->getMessage();
    }
    
    // Migrate administrators
    try {
        $query = "
            UPDATE :table_administrators
            SET 
                user_password = '',
                date_modified = NOW()
            WHERE 
                user_password REGEXP '^[A-F0-9]{32}:[A-F0-9]{2}$'
        ";
        
        $db->query($query);
        $results['administrators']['success'] = $db->rowCount();
    } catch (Exception $e) {
        $results['administrators']['failed'] = 1;
        $results['errors'][] = 'Administrators migration error: ' . $e->getMessage();
    }
    
    return $results;
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

// Check authorization
$authErrors = checkAuthorization();

outputHeader('Password Migration Tool');

echo '<h1>🔐 Password Migration Tool</h1>';
echo '<p><strong>Date:</strong> ' . date('Y-m-d H:i:s') . '</p>';
echo '<p><strong>Server:</strong> ' . ($_SERVER['SERVER_NAME'] ?? 'Unknown') . '</p>';
echo '<p><strong>Client IP:</strong> ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . '</p>';

// Display authorization errors
if (!empty($authErrors)) {
    echo '<div class="error">';
    echo '<h2>❌ Access Denied</h2>';
    echo '<ul>';
    foreach ($authErrors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul>';
    echo '<h3>How to access this tool:</h3>';
    echo '<ol>';
    echo '<li>Edit this file and set <code>MIGRATION_SECRET_TOKEN</code> to a random 64-character string</li>';
    echo '<li>Add your IP address to <code>ALLOWED_IPS</code> array</li>';
    echo '<li>Access this page with: <code>?token=YOUR_SECRET_TOKEN</code></li>';
    echo '<li>Set <code>ENABLE_MIGRATION</code> to <code>true</code> when ready to migrate</li>';
    echo '</ol>';
    echo '<h3>Generate a secure token:</h3>';
    echo '<pre>php -r "echo bin2hex(random_bytes(32));"</pre>';
    echo '</div>';
    outputFooter();
    exit;
}

// Get action
$action = $_GET['action'] ?? 'analyze';

// ============================================================================
// ANALYZE MODE
// ============================================================================

if ($action === 'analyze') {
    echo '<div class="info">';
    echo '<h2>📊 Analysis Mode</h2>';
    echo '<p>This mode identifies users with insecure \'salt\' algorithm passwords.</p>';
    echo '<p><strong>No changes will be made to the database.</strong></p>';
    echo '</div>';
    
    $analysis = analyzeUsers();
    
    // Display statistics
    echo '<h2>Statistics</h2>';
    echo '<div class="stats">';
    echo '<div class="stat-card">';
    echo '<div class="stat-number">' . $analysis['total'] . '</div>';
    echo '<div class="stat-label">Total Affected Users</div>';
    echo '</div>';
    echo '<div class="stat-card">';
    echo '<div class="stat-number">' . count($analysis['customers']) . '</div>';
    echo '<div class="stat-label">Customers</div>';
    echo '</div>';
    echo '<div class="stat-card">';
    echo '<div class="stat-number">' . count($analysis['administrators']) . '</div>';
    echo '<div class="stat-label">Administrators</div>';
    echo '</div>';
    echo '</div>';
    
    if ($analysis['total'] === 0) {
        echo '<div class="success">';
        echo '<h2>✅ No Migration Needed</h2>';
        echo '<p>No users found with insecure \'salt\' algorithm passwords.</p>';
        echo '<p><strong>Action:</strong> You can safely delete this file.</p>';
        echo '</div>';
    } else {
        echo '<div class="warning">';
        echo '<h2>⚠️ Migration Required</h2>';
        echo '<p>Found <strong>' . $analysis['total'] . '</strong> users with insecure passwords.</p>';
        echo '<p>These users will need to reset their passwords after migration.</p>';
        echo '</div>';
        
        // Display affected customers
        if (!empty($analysis['customers'])) {
            echo '<h2>Affected Customers (' . count($analysis['customers']) . ')</h2>';
            echo '<table>';
            echo '<thead><tr><th>ID</th><th>Email</th><th>Account Created</th><th>Hash Preview</th></tr></thead>';
            echo '<tbody>';
            $displayLimit = 50;
            $displayed = 0;
            foreach ($analysis['customers'] as $user) {
                if ($displayed >= $displayLimit) {
                    echo '<tr><td colspan="4"><em>... and ' . (count($analysis['customers']) - $displayLimit) . ' more customers</em></td></tr>';
                    break;
                }
                echo '<tr>';
                echo '<td>' . htmlspecialchars($user['id']) . '</td>';
                echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                echo '<td>' . htmlspecialchars($user['created']) . '</td>';
                echo '<td><code>' . htmlspecialchars(substr($user['password'], 0, 20)) . '...</code></td>';
                echo '</tr>';
                $displayed++;
            }
            echo '</tbody>';
            echo '</table>';
        }
        
        // Display affected administrators
        if (!empty($analysis['administrators'])) {
            echo '<h2>Affected Administrators (' . count($analysis['administrators']) . ')</h2>';
            echo '<table>';
            echo '<thead><tr><th>ID</th><th>Username</th><th>Account Created</th><th>Hash Preview</th></tr></thead>';
            echo '<tbody>';
            foreach ($analysis['administrators'] as $user) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($user['id']) . '</td>';
                echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                echo '<td>' . htmlspecialchars($user['created']) . '</td>';
                echo '<td><code>' . htmlspecialchars(substr($user['password'], 0, 20)) . '...</code></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }
        
        // Migration instructions
        echo '<h2>Migration Instructions</h2>';
        echo '<div class="info">';
        echo '<h3>Before Migration:</h3>';
        echo '<ol>';
        echo '<li><strong>Backup your database</strong> - This is critical!</li>';
        echo '<li>Schedule maintenance window (users will need to reset passwords)</li>';
        echo '<li>Prepare email notification for affected users</li>';
        echo '<li>Set <code>ENABLE_MIGRATION</code> to <code>true</code> in this file</li>';
        echo '</ol>';
        echo '<h3>What Migration Does:</h3>';
        echo '<ul>';
        echo '<li>Sets password field to empty string for affected users</li>';
        echo '<li>Updates last modified timestamp</li>';
        echo '<li>Forces password reset on next login</li>';
        echo '<li>New passwords will use secure bcrypt algorithm</li>';
        echo '</ul>';
        echo '</div>';
        
        if (!ENABLE_MIGRATION) {
            echo '<div class="warning">';
            echo '<h3>⚠️ Migration is Currently Disabled</h3>';
            echo '<p>To enable migration:</p>';
            echo '<ol>';
            echo '<li>Edit this file</li>';
            echo '<li>Change <code>ENABLE_MIGRATION</code> from <code>false</code> to <code>true</code></li>';
            echo '<li>Save the file</li>';
            echo '<li>Reload this page and click "Start Migration"</li>';
            echo '</ol>';
            echo '</div>';
        } else {
            echo '<div style="margin: 30px 0; text-align: center;">';
            echo '<a href="?token=' . urlencode(MIGRATION_SECRET_TOKEN) . '&action=confirm" class="btn btn-danger">Start Migration</a>';
            echo '</div>';
        }
    }
}

// ============================================================================
// CONFIRM MODE
// ============================================================================

elseif ($action === 'confirm') {
    $analysis = analyzeUsers();
    
    echo '<div class="warning">';
    echo '<h2>⚠️ Confirm Migration</h2>';
    echo '<p><strong>You are about to migrate ' . $analysis['total'] . ' user passwords.</strong></p>';
    echo '<p>This action will:</p>';
    echo '<ul>';
    echo '<li>Clear passwords for ' . count($analysis['customers']) . ' customers</li>';
    echo '<li>Clear passwords for ' . count($analysis['administrators']) . ' administrators</li>';
    echo '<li>Force all affected users to reset their passwords</li>';
    echo '</ul>';
    echo '<p><strong>Have you backed up your database?</strong></p>';
    echo '</div>';
    
    echo '<div style="margin: 30px 0; text-align: center;">';
    echo '<a href="?token=' . urlencode(MIGRATION_SECRET_TOKEN) . '&action=analyze" class="btn">← Back to Analysis</a>';
    echo '<a href="?token=' . urlencode(MIGRATION_SECRET_TOKEN) . '&action=migrate" class="btn btn-danger">Yes, Migrate Now</a>';
    echo '</div>';
}

// ============================================================================
// MIGRATE MODE
// ============================================================================

elseif ($action === 'migrate') {
    echo '<div class="info">';
    echo '<h2>🚀 Starting Migration...</h2>';
    echo '</div>';
    
    $migrationResults = performMigration();
    
    if (!empty($migrationResults['errors'])) {
        echo '<div class="error">';
        echo '<h2>❌ Migration Errors</h2>';
        echo '<ul>';
        foreach ($migrationResults['errors'] as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    $totalMigrated = $migrationResults['customers']['success'] + $migrationResults['administrators']['success'];
    $totalFailed = $migrationResults['customers']['failed'] + $migrationResults['administrators']['failed'];
    
    if ($totalMigrated > 0) {
        echo '<div class="success">';
        echo '<h2>✅ Migration Complete</h2>';
        echo '<p><strong>' . $totalMigrated . '</strong> users successfully migrated.</p>';
        echo '</div>';
    }
    
    echo '<h2>Migration Results</h2>';
    echo '<div class="stats">';
    echo '<div class="stat-card">';
    echo '<div class="stat-number">' . $totalMigrated . '</div>';
    echo '<div class="stat-label">Successfully Migrated</div>';
    echo '</div>';
    echo '<div class="stat-card">';
    echo '<div class="stat-number">' . $migrationResults['customers']['success'] . '</div>';
    echo '<div class="stat-label">Customers</div>';
    echo '</div>';
    echo '<div class="stat-card">';
    echo '<div class="stat-number">' . $migrationResults['administrators']['success'] . '</div>';
    echo '<div class="stat-label">Administrators</div>';
    echo '</div>';
    echo '</div>';
    
    echo '<h2>Next Steps</h2>';
    echo '<div class="info">';
    echo '<ol>';
    echo '<li><strong>Verify migration:</strong> Run analysis again to confirm no users remain</li>';
    echo '<li><strong>Notify users:</strong> Send email to affected users about password reset</li>';
    echo '<li><strong>Monitor:</strong> Track password reset completion rate</li>';
    echo '<li><strong>Clean up:</strong> Delete this file after confirming migration success</li>';
    echo '</ol>';
    echo '</div>';
    
    echo '<div style="margin: 30px 0; text-align: center;">';
    echo '<a href="?token=' . urlencode(MIGRATION_SECRET_TOKEN) . '&action=analyze" class="btn btn-success">Verify Migration</a>';
    echo '</div>';
}

outputFooter();
