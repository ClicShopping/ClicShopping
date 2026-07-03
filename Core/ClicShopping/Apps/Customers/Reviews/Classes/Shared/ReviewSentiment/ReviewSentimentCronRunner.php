<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment;

use ClicShopping\AI\Security\RateLimit;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Common\CronLogger;
use ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\ReviewSentimentGenerator;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;
use ClicShopping\OM\HTML;
use function count;

/**
 * ReviewSentimentCronRunner — daily auto-generation of product review sentiment.
 *
 * Native Cronjob path: the thin `Module/Hooks/{Shop,ClicShoppingAdmin}/Cronjob/Process`
 * hooks (registered in the Reviews app clicshopping.json) delegate here. The whole
 * generation is shared with the manual admin trigger through
 * {@see ReviewSentimentGenerator} so both paths behave identically.
 *
 * Policy (mirrors the SEO cron): per product, if the analysis is judged reliable
 * (critic + anti-hallucination fidelity) it is AUTO-ACCEPTED (sentiment_approved=1);
 * otherwise the product id + reason is collected into an administrator note and the
 * run is reported as `partial`.
 *
 * Registered as clic_cron.code = 'productReviewSentiment'.
 */
class ReviewSentimentCronRunner
{
  private const CRON_CODE  = 'productReviewSentiment';
  private const BATCH_SIZE = 30;

  private mixed $db;

  public function __construct()
  {
    // Own a fresh Reviews App instance for a public db handle. The 'Reviews'
    // Registry key is NOT reused: in the Shop context it holds Classes\Shop\
    // ReviewsClass (private $db), which would be inaccessible here.
    $this->db = (new ReviewsApp())->db;
  }

  /**
   * Entry point invoked by the Cronjob/Process hooks.
   *
   * @return bool|null false when opted-out / provider offline, null otherwise.
   */
  public function run(): bool|null
  {
    if (!Gpt::checkGptStatus()) {
      return false;
    }

    // Only act when this specific cron is registered AND enabled.
    $cronId = Cronjob::getCronCode(self::CRON_CODE);
    if (empty($cronId) || !$this->isEnabled($cronId)) {
      return false;
    }

    // Admin "Run now" targets a specific cronId; the scheduler tick has none.
    if (isset($_GET['cronId'])) {
      $requested = HTML::sanitize($_GET['cronId']);
      if (!is_numeric($requested) || (int)$requested !== $cronId) {
        return null;
      }
    }

    Cronjob::updateCron($cronId);
    $this->generate();

    return null;
  }

  /**
   * Selects targets, generates per product, auto-accepts the reliable ones,
   * collects a note for the rest and writes a unified cron_log row.
   */
  private function generate(): void
  {
    $logger = new CronLogger('other', self::CRON_CODE);
    $logger->start();

    // Daily lock: even though Process fires on every scheduler tick, run once/day.
    $lock = new RateLimit('review_sentiment_cron_lock', 1, 86400);
    if (!$lock->checkLimit('global')) {
      $logger->finish('skipped', ['error_messages' => 'Daily lock active — sentiment cron already ran today.']);
      return;
    }

    $targets   = $this->fetchTargets(self::BATCH_SIZE);
    $generator = new ReviewSentimentGenerator();

    $success = 0;
    $failed  = [];

    foreach ($targets as $target) {
      $productId = (int)$target['products_id'];
      $anchor    = (int)$target['anchor'];

      try {
        $result = $generator->generateForProduct($productId, $anchor, 'cron');

        if ($result === null) {
          continue; // below threshold (race) — skip silently
        }

        if ($result['reliable']) {
          $this->autoAccept($result['sentiment_id']);
          $success++;
        } else {
          $failed[] = ['id' => $productId, 'reason' => 'verdict ' . $result['verdict']];
        }
      } catch (\Throwable $e) {
        $failed[] = ['id' => $productId, 'reason' => $e->getMessage()];
      }
    }

    $status = $failed === [] ? 'completed' : 'partial';
    $logger->finish($status, [
      'targets_found'     => count($targets),
      'targets_processed' => $success + count($failed),
      'success_count'     => $success,
      'failure_count'     => count($failed),
      'metadata'          => json_encode(['failed_products' => $failed], JSON_UNESCAPED_UNICODE),
      'error_messages'    => $failed === [] ? null : $this->buildAdminNote($failed),
    ]);
  }

  /**
   * Products with enough approved reviews whose analysis is missing or stale
   * (older than the most recent review). Anchor reuses the existing sentiment
   * row's reviews_id when present to avoid duplicate parents.
   *
   * @return array<int,array{products_id:int,anchor:int}>
   */
  private function fetchTargets(int $limit): array
  {
    $Q = $this->db->prepare('SELECT r.products_id,
                                    COALESCE(MAX(rs.reviews_id), MIN(r.reviews_id)) AS anchor
                             FROM :table_reviews r
                             LEFT JOIN :table_reviews_sentiment rs ON rs.products_id = r.products_id
                             WHERE r.status = 1
                             GROUP BY r.products_id
                             HAVING COUNT(DISTINCT r.reviews_id) >= :min
                                AND (MAX(rs.id) IS NULL
                                     OR COALESCE(MAX(rs.date_modified), MAX(rs.date_added)) < MAX(r.date_added))
                             ORDER BY MAX(r.date_added) DESC
                             LIMIT :limit');
    $Q->bindInt(':min', ReviewSentimentGenerator::MIN_REVIEWS);
    $Q->bindInt(':limit', $limit);
    $Q->execute();

    $targets = [];
    foreach ($Q->fetchAll() as $row) {
      $targets[] = ['products_id' => (int)$row['products_id'], 'anchor' => (int)$row['anchor']];
    }

    return $targets;
  }

  private function autoAccept(int $sentimentId): void
  {
    $this->db->save('reviews_sentiment', ['sentiment_approved' => 1], ['id' => $sentimentId]);
  }

  private function isEnabled(int $cronId): bool
  {
    $Q = $this->db->get('cron', 'status', ['cron_id' => $cronId]);

    return (int)$Q->valueInt('status') === 1;
  }

  /**
   * @param array<int,array{id:int,reason:string}> $failed
   */
  private function buildAdminNote(array $failed): string
  {
    $parts = [];
    foreach ($failed as $fp) {
      $parts[] = 'product ' . $fp['id'] . ': ' . $fp['reason'];
    }

    return count($failed) . ' product(s) need review — ' . implode(' | ', $parts);
  }
}
