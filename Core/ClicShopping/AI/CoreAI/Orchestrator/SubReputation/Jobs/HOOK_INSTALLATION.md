# ProcessReputationQueue Hook Installation Guide

## Overview

The `ProcessReputationQueue` hook integrates the asynchronous reputation update job system with ClicShopping's cronjob framework. This hook processes the job queue every 5 minutes, handling reputation updates with retry logic and exponential backoff.

## Architecture

```
User Action (Evaluation)
    ↓
EvaluationMonitor.recordEvaluation()
    ↓
JobQueue.push(UpdateReputationJob)
    ↓
Database: rag_agent_reputation_update_queue
    ↓
[Every 5 minutes]
    ↓
ProcessReputationQueue Hook (Cronjob)
    ↓
JobQueue.processPending(50)
    ↓
UpdateReputationJob.handle()
    ↓
ReputationTracker.updateReputation()
```

## Components

### 1. Hook File
**Location:** `Core/ClicShopping/Apps/Configuration/ChatGpt/Module/Hooks/ClicShoppingAdmin/Cronjob/ProcessReputationQueue.php`

**Purpose:** Cronjob hook that processes the reputation update queue

**Features:**
- Processes up to 50 jobs per run
- Logs processing results
- Monitors queue health
- Alerts on backlog or high failure rate
- Automatic cleanup of old jobs

### 2. Job Queue System
**Location:** `Core/ClicShopping/AI/Agents/Orchestrator/SubReputation/Jobs/`

**Components:**
- `JobQueue.php` - Queue management
- `UpdateReputationJob.php` - Job execution
- `ReputationJobDispatcher.php` - Job dispatching

### 3. Database Table
**Table:** `rag_agent_reputation_update_queue`

**Purpose:** Stores queued reputation update jobs with retry state

## Installation Steps

### Step 1: Verify Prerequisites

Ensure the following are installed:

```bash
# Check if queue table exists
php sql/verify_reputation_queue_hook.php
```

If the table doesn't exist, create it:

```bash
php sql/2026_02_04_apply_reputation_update_queue_table.php
```

### Step 2: Add Cronjob Entry

The cronjob entry should already exist. Verify it:

```bash
php sql/verify_reputation_queue_hook.php
```

If missing, create it:

```bash
php sql/2026_02_04_apply_reputation_queue_cron.php
```

### Step 3: Verify Hook Installation

The hook file should be in place. Verify the complete installation:

```bash
php sql/verify_reputation_queue_hook.php
```

Expected output:
```
========================================
Reputation Queue Hook Verification
========================================

1. Checking hook file...
   ✓ Hook file exists

2. Checking JobQueue class...
   ✓ JobQueue class exists

3. Checking database table...
   ✓ Queue table exists: clic_rag_agent_reputation_update_queue
   ✓ All required columns present
   ✓ All required indexes present

4. Checking cronjob entry...
   ✓ Cronjob entry exists
   - ID: 123
   - Title: Process Reputation Update Queue
   - Schedule: */5 * * * *
   - Status: Active ✓

5. Checking queue statistics...
   ℹ Queue is empty (no jobs yet)

6. Testing hook instantiation...
   ✓ Hook class can be loaded
   ✓ Hook can be instantiated

========================================
Verification Summary
========================================

✓ All checks passed! The reputation queue hook is properly installed.
```

### Step 4: Enable Cronjob (if needed)

If the cronjob is inactive, enable it:

**Via Admin Panel:**
1. Go to **Tools > Cronjob**
2. Find `reputation_queue`
3. Enable it

**Via SQL:**
```sql
UPDATE :table_cron 
SET status = 1 
WHERE code = 'reputation_queue';
```

**Via PHP:**
```php
use ClicShopping\OM\Registry;

$db = Registry::get('Db');
$db->query("UPDATE " . DB_TABLE_PREFIX . "cron SET status = 1 WHERE code = 'reputation_queue'");
```

## Configuration

### Adjust Processing Frequency

Default: Every 5 minutes (`*/5 * * * *`)

To change:

```sql
UPDATE :table_cron 
SET minute = '*/2'  -- Every 2 minutes
WHERE code = 'reputation_queue';
```

### Adjust Batch Size

Default: 50 jobs per run

To change, edit `ProcessReputationQueue.php`:

```php
// Line ~70
$batchSize = 100; // Process up to 100 jobs per run
```

### Adjust Cleanup Period

Default: Keep jobs for 7 days

To change, edit `ProcessReputationQueue.php`:

```php
// Line ~90
$deleted = $this->jobQueue->cleanupOldJobs(14); // Keep for 14 days
```

## Monitoring

### Check Queue Status

```bash
# View queue statistics
php -r "
require 'Core/ClicShopping/OM/CLICSHOPPING.php';
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Jobs\JobQueue;
CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('Shop');
\$queue = new JobQueue();
print_r(\$queue->getStatistics());
"
```

### Monitor Logs

```bash
# Watch processing logs
tail -f /path/to/error.log | grep ProcessReputationQueue

# Check for errors
grep "ProcessReputationQueue.*error" /path/to/error.log

# Check for warnings
grep "ProcessReputationQueue.*WARNING" /path/to/error.log
```

### View Failed Jobs

```sql
SELECT * FROM :table_rag_agent_reputation_update_queue 
WHERE status = 'failed' 
ORDER BY failed_at DESC 
LIMIT 50;
```

### Check Processing History

```sql
SELECT 
    DATE(completed_at) as date,
    COUNT(*) as jobs_completed,
    AVG(attempts) as avg_attempts
FROM :table_rag_agent_reputation_update_queue 
WHERE status = 'completed'
GROUP BY DATE(completed_at)
ORDER BY date DESC
LIMIT 30;
```

## Troubleshooting

### Jobs Not Processing

**Symptoms:** Queue is growing but jobs aren't being processed

**Solutions:**

1. Check if cronjob is active:
   ```sql
   SELECT * FROM :table_cron WHERE code = 'reputation_queue';
   ```

2. Check for errors in logs:
   ```bash
   grep "ProcessReputationQueue" /path/to/error.log | tail -50
   ```

3. Manually trigger processing:
   ```php
   use ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\Cronjob\ProcessReputationQueue;
   $hook = new ProcessReputationQueue();
   $hook->execute();
   ```

### High Failure Rate

**Symptoms:** Many jobs in 'failed' status

**Solutions:**

1. Check error messages:
   ```sql
   SELECT error_message, COUNT(*) as count 
   FROM :table_rag_agent_reputation_update_queue 
   WHERE status = 'failed' 
   GROUP BY error_message;
   ```

2. Retry failed jobs:
   ```php
   use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Jobs\ReputationJobDispatcher;
   $dispatcher = new ReputationJobDispatcher();
   $count = $dispatcher->retryAllFailedJobs();
   echo "Retried $count jobs\n";
   ```

### Queue Backlog

**Symptoms:** Pending jobs > 100

**Solutions:**

1. Increase batch size (see Configuration above)
2. Increase processing frequency (see Configuration above)
3. Check for blocking issues in error logs

### Hook Not Found

**Symptoms:** Class not found errors

**Solutions:**

1. Verify file exists:
   ```bash
   ls -la Core/ClicShopping/Apps/Configuration/ChatGpt/Module/Hooks/ClicShoppingAdmin/Cronjob/ProcessReputationQueue.php
   ```

2. Clear autoload cache:
   ```bash
   php clear_all_caches_complete.php
   ```

3. Verify namespace is correct in file

## Testing

### Manual Test

```php
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Jobs\ReputationJobDispatcher;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\EvaluationOutcome;

// Create test job
$dispatcher = new ReputationJobDispatcher();

$outcome = new EvaluationOutcome();
$outcome->evaluationId = 'test-' . uniqid();
$outcome->criticId = 'test-critic';
$outcome->criticScore = 0.85;
$outcome->consensusScore = 0.80;
$outcome->withinThreshold = true;
$outcome->alignmentDelta = 0.05;
$outcome->feedbackAccepted = true;
$outcome->evaluatedAt = new DateTime();

// Queue the job
$queueId = $dispatcher->dispatch('test-critic', $outcome);
echo "Job queued: $queueId\n";

// Process the queue
use ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\Cronjob\ProcessReputationQueue;
$hook = new ProcessReputationQueue();
$hook->execute();

// Check results
$stats = $dispatcher->getStatistics();
print_r($stats);
```

## Integration with EvaluationMonitor

The hook works seamlessly with `EvaluationMonitor`:

```php
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\EvaluationMonitor;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationTracker;

// Create monitor with async enabled
$tracker = new ReputationTracker();
$monitor = new EvaluationMonitor($tracker, asyncEnabled: true);

// Record evaluation (automatically queues job)
$monitor->recordEvaluation(
    evaluationId: 'eval-123',
    criticId: 'critic-456',
    criticScore: 0.85,
    consensusScore: 0.80,
    withinThreshold: true,
    alignmentDelta: 0.05,
    feedbackAccepted: true
);

// Job is now in queue and will be processed by the hook
```

## Performance

### Throughput

- **Batch size:** 50 jobs per run
- **Frequency:** Every 5 minutes
- **Throughput:** 600 jobs/hour
- **Daily capacity:** 14,400 jobs/day

### Latency

- **Average:** 2.5 minutes (half the cron interval)
- **Maximum:** 5 minutes (full cron interval)
- **With retries:** Up to 7 minutes (including backoff)

### Optimization

To increase throughput:

1. **Increase batch size:** Process more jobs per run
2. **Increase frequency:** Run more often (e.g., every 2 minutes)
3. **Add workers:** Run multiple instances (requires queue locking)

## Requirements

This implementation satisfies:
- **Requirement 15.1:** Asynchronous reputation updates
- **Requirement 15.3:** Batch reputation updates for efficiency

## Related Documentation

- [Job System README](README.md)
- [Job Installation Guide](INSTALLATION.md)
- [Reputation System Overview](../README.md)
- [Batch Processing Guide](../Batch/README.md)

## Support

For issues or questions:
1. Run verification script: `php sql/verify_reputation_queue_hook.php`
2. Check error logs: `grep ProcessReputationQueue /path/to/error.log`
3. Review queue statistics in database
4. Test manual execution to isolate issues
