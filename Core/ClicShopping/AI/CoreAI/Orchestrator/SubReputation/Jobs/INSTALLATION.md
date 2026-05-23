# Installation Guide for Reputation Update Job System

## Overview

This guide provides instructions for installing and configuring the async reputation update job system.

## Prerequisites

- PHP 8.4+
- MariaDB 11.x or MySQL 8.x
- ClicShopping AI platform installed
- Database access with CREATE TABLE privileges

## Installation Steps

### Step 1: Create Database Table

The reputation update queue requires a database table to store job information.

**Option A: Using Migration Script**

```bash
php sql/2026_02_04_apply_reputation_update_queue_table.php
```

**Option B: Manual SQL Execution**

If the migration script fails, you can manually execute the SQL:

```sql
CREATE TABLE IF NOT EXISTS :table_rag_agent_reputation_update_queue (
    queue_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    critic_id VARCHAR(100) NOT NULL,
    evaluation_id VARCHAR(36) NOT NULL,
    outcome_data JSON NOT NULL,
    status ENUM('pending', 'processing', 'retrying', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    next_retry_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    INDEX idx_critic_id (critic_id),
    INDEX idx_evaluation_id (evaluation_id),
    INDEX idx_status (status),
    INDEX idx_next_retry_at (next_retry_at),
    INDEX idx_created_at (created_at),
    INDEX idx_status_retry (status, next_retry_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Note:** Replace `clic_` with your actual table prefix.

### Step 2: Add Cronjob Entry

The job queue is processed by a cronjob that runs every 5 minutes.

**Option A: Using Migration Script**

```bash
php sql/2026_02_04_apply_reputation_queue_cron.php
```

**Option B: Manual SQL Execution**

```sql
INSERT INTO clic_cron (code, title, description, minute, hour, day, month, day_of_week, status, date_added, date_modified)
VALUES (
    'reputation_queue',
    'Process Reputation Update Queue',
    'Processes pending reputation update jobs with retry logic and exponential backoff',
    '*/5',  -- Every 5 minutes
    '*',    -- Every hour
    '*',    -- Every day
    '*',    -- Every month
    '*',    -- Every day of week
    1,      -- Active
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    minute = VALUES(minute),
    status = VALUES(status),
    date_modified = NOW();
```

**Note:** Replace `clic_` with your actual table prefix.

### Step 3: Verify Installation

**Check Table Creation:**

```sql
SHOW TABLES LIKE '%rag_agent_reputation_update_queue';
DESCRIBE :table__rag_agent_reputation_update_queue;
```

**Check Cronjob:**

```sql
SELECT * FROM :table_cron WHERE code = 'reputation_queue';
```

**Check Indexes:**

```sql
SHOW INDEX FROM :table_rag_agent_reputation_update_queue;
```

### Step 4: Test the System

**Test Job Queuing:**

```php
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Jobs\ReputationJobDispatcher;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\EvaluationOutcome;

$dispatcher = new ReputationJobDispatcher();

// Create test outcome
$outcome = new EvaluationOutcome();
$outcome->evaluationId = 'test-eval-' . uniqid();
$outcome->criticId = 'test-critic-' . uniqid();
$outcome->criticScore = 0.85;
$outcome->consensusScore = 0.80;
$outcome->withinThreshold = true;
$outcome->alignmentDelta = 0.05;
$outcome->feedbackAccepted = true;
$outcome->evaluatedAt = new DateTime();

// Queue the job
$queueId = $dispatcher->dispatch('test-critic', $outcome);

echo "Job queued with ID: $queueId\n";

// Check queue statistics
$stats = $dispatcher->getStatistics();
print_r($stats);
```

**Test Job Processing:**

```bash
# Manually trigger the cronjob
php -r "require 'Core/ClicShopping/AI/Agents/Orchestrator/SubReputation/Module/Hooks/ClicShoppingAdmin/Cronjob/ProcessReputationQueue.php'; (new ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Module\Hooks\ClicShoppingAdmin\Cronjob\ProcessReputationQueue())->execute();"
```

## Configuration

### Adjust Cronjob Frequency

To change how often the queue is processed:

```sql
UPDATE clic_cron 
SET minute = '*/2'  -- Every 2 minutes
WHERE code = 'reputation_queue';
```

### Adjust Batch Size

Edit `ProcessReputationQueue.php` and change the batch size:

```php
// Process up to 100 jobs per run (default is 50)
$results = $this->jobQueue->processPending(100);
```

### Adjust Retry Settings

Edit `UpdateReputationJob.php` to change retry behavior:

```php
private int $maxAttempts = 3;        // Number of retry attempts
private int $backoffSeconds = 60;    // Initial backoff delay
```

## Monitoring

### Check Queue Status

```sql
SELECT status, COUNT(*) as count 
FROM clic_rag_agent_reputation_update_queue 
GROUP BY status;
```

### View Failed Jobs

```sql
SELECT * FROM clic_rag_agent_reputation_update_queue 
WHERE status = 'failed' 
ORDER BY failed_at DESC 
LIMIT 50;
```

### View Recent Jobs

```sql
SELECT * FROM clic_rag_agent_reputation_update_queue 
ORDER BY created_at DESC 
LIMIT 50;
```

### Check Alerts

```sql
SELECT * FROM clic_rag_agent_reputation_alerts 
WHERE alert_type = 'job_failure' 
ORDER BY created_at DESC 
LIMIT 50;
```

## Troubleshooting

### Jobs Not Processing

1. **Check cronjob is active:**
   ```sql
   SELECT * FROM clic_cron WHERE code = 'reputation_queue';
   ```

2. **Check for pending jobs:**
   ```sql
   SELECT COUNT(*) FROM clic_rag_agent_reputation_update_queue WHERE status = 'pending';
   ```

3. **Manually trigger processing:**
   ```bash
   php -r "require 'Core/ClicShopping/AI/Agents/Orchestrator/SubReputation/Module/Hooks/ClicShoppingAdmin/Cronjob/ProcessReputationQueue.php'; (new ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Module\Hooks\ClicShoppingAdmin\Cronjob\ProcessReputationQueue())->execute();"
   ```

### High Failure Rate

1. **Check error messages:**
   ```sql
   SELECT error_message, COUNT(*) as count 
   FROM clic_rag_agent_reputation_update_queue 
   WHERE status = 'failed' 
   GROUP BY error_message;
   ```

2. **Retry failed jobs:**
   ```php
   $dispatcher = new ReputationJobDispatcher();
   $count = $dispatcher->retryAllFailedJobs();
   echo "Retried $count jobs\n";
   ```

### Queue Backlog

If jobs are backing up:

1. **Increase batch size** (see Configuration above)
2. **Increase cronjob frequency** (see Configuration above)
3. **Check for blocking issues** in error logs

## Maintenance

### Clean Up Old Jobs

```php
$dispatcher = new ReputationJobDispatcher();
$deleted = $dispatcher->cleanupOldJobs(7); // Delete jobs older than 7 days
echo "Deleted $deleted old jobs\n";
```

### Manual Cleanup

```sql
DELETE FROM clic_rag_agent_reputation_update_queue 
WHERE status = 'completed' 
AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## Uninstallation

To remove the reputation update job system:

```sql
-- Drop the table
DROP TABLE IF NOT EXISTS clic_rag_agent_reputation_update_queue;

-- Remove the cronjob
DELETE FROM clic_cron WHERE code = 'reputation_queue';
```

## Support

For issues or questions:
1. Check the error logs in `Core/ClicShopping/Work/Log/`
2. Review the README.md in the Jobs directory
3. Check the design document for requirements and architecture details

## Requirements

This implementation satisfies:
- **Requirement 15.1**: Asynchronous reputation updates
- **Requirement 15.3**: Batch reputation updates for efficiency
