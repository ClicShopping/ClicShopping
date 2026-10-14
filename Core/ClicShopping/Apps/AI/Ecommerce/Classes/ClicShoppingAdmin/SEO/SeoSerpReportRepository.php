<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

  use ClicShopping\OM\Registry;
  use ClicShopping\OM\CLICSHOPPING;

  /**
   * Class SeoSerpReportRepository
   * * Handles the persistence and retrieval of SEO Audit and SERP (Search Engine Results Page) reports.
   * This repository manages data transformations (JSON encoding) between the application and the database.
   * * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO
   */
  class SeoSerpReportRepository
  {
    /** @var mixed The database connection instance */
    private mixed $db;

    /**
     * SeoSerpReportRepository constructor.
     */
    public function __construct()
    {
      $this->db = Registry::get('Db');
    }

    /**
     * Insert a new SEO/SERP report into the database.
     *
     * Accepted keys in $report: entity_type (string), entity_id (int), language_id (int),
     * url (string), serp_source (string), serp_query (string), serp_data (array),
     * seo_before (array), seo_after (array), proposed_changes (array), audit_result (array),
     * summary (string), seo_score_before (int), seo_score_after (int),
     * status (string), triggered_by (string), pipeline_metrics (array).
     *
     * @param array<string, mixed> $report The report data structure.
     * @return int The ID of the newly inserted report.
     */
    public function insert(array $report): int
    {
      $data = [
        'entity_type'      => $report['entity_type']      ?? '',
        'entity_id'        => (int)($report['entity_id']  ?? 0),
        'language_id'      => (int)($report['language_id'] ?? 0),
        'url'              => $report['url']               ?? '',
        'serp_source'      => $report['serp_source']       ?? '',
        'serp_query'       => $report['serp_query']        ?? '',
        'serp_data'        => $this->json($report['serp_data']        ?? []),
        'seo_before'       => $this->json($report['seo_before']       ?? []),
        'seo_after'        => $this->json($report['seo_after']        ?? []),
        'proposed_changes' => $this->json($report['proposed_changes'] ?? []),
        'audit_result'     => $this->json($report['audit_result']     ?? []),
        'summary'          => $report['summary']           ?? '',
        'seo_score_before' => (int)($report['seo_score_before'] ?? 0),
        'seo_score_after'  => (int)($report['seo_score_after']  ?? 0),
        'status'           => $report['status']            ?? '',
        'triggered_by'     => $report['triggered_by']      ?? '',
        'pipeline_metrics' => $this->json($report['pipeline_metrics'] ?? []),
        'created_at'       => date('Y-m-d H:i:s'),
        'updated_at'       => date('Y-m-d H:i:s'),
      ];

      $this->db->save('seo_serp_reports', $data);

      return (int)$this->db->lastInsertId();
    }

    /**
     * Set `status` on the most recent report row per language for the entity.
     * Used by Accept ('accepted') and Reject ('rejected'). Returns rows updated.
     */
    public function markLatestStatus(string $entityType, int $entityId, string $status): int
    {
      $Q = $this->db->prepare(
        'SELECT MAX(id) AS id FROM :table_seo_serp_reports
         WHERE entity_type = :entity_type AND entity_id = :entity_id
         GROUP BY language_id'
      );
      $Q->bindValue(':entity_type', $entityType);
      $Q->bindInt(':entity_id', $entityId);
      $Q->execute();
      $rows = $Q->fetchAll() ?: [];

      $updated = 0;
      foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
          continue;
        }
        $U = $this->db->prepare(
          'UPDATE :table_seo_serp_reports SET status = :status, updated_at = NOW() WHERE id = :id'
        );
        $U->bindValue(':status', $status);
        $U->bindInt(':id', $id);
        $U->execute();
        $updated++;
      }

      return $updated;
    }

    /** Delete every report row for the entity (all languages). Returns rows deleted. */
    public function deleteForEntity(string $entityType, int $entityId): int
    {
      $stmt = $this->db->prepare(
        'DELETE FROM :table_seo_serp_reports WHERE entity_type = :entity_type AND entity_id = :entity_id'
      );
      $stmt->bindValue(':entity_type', $entityType);
      $stmt->bindInt(':entity_id', $entityId);
      $stmt->execute();

      return (int)$stmt->rowCount();
    }

    /**
     * Encodes an array into a JSON string with specific flags for database storage.
     *
     * @param array $data The data to encode.
     * @return string The JSON encoded string.
     */
    private function json(array $data): string
    {
      return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Retrieves the most recent report for a specific entity and language.
     *
     * @param string $entityType The entity type (e.g., 'product')
     * @param int $entityId The entity ID
     * @param int $languageId The language ID
     * @return array|null The report data row or null if not found.
     */
    public function getLatestReport(string $entityType, int $entityId, int $languageId): ?array
    {
      $Qcheck = $this->db->prepare('SHOW COLUMNS FROM :table_seo_serp_reports LIKE :column');
      $Qcheck->bindValue(':column', 'entity_type');
      $Qcheck->execute();

      if (!$Qcheck->fetch()) {
        return null;
      }

      $stmt = $this->db->prepare('SELECT *
                                FROM :table_seo_serp_reports
                                WHERE entity_type = :entity_type
                                  AND entity_id = :entity_id
                                  AND language_id = :language_id
                                ORDER BY created_at DESC
                                ');

      $stmt->bindValue(':entity_type', $entityType);
      $stmt->bindInt(':entity_id', $entityId);
      $stmt->bindInt(':language_id', $languageId);
      $stmt->execute();

      $row = $stmt->fetch();

      return $row ?: null;
    }
  }