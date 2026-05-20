<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
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
     * @param array $report {
     * The report data structure.
     * @var string $entity_type      The type of entity (e.g., 'product', 'category')
     * @var int    $entity_id        The unique identifier of the entity
     * @var int    $language_id      The language ID for the report
     * @var string $url              The analyzed URL
     * @var string $serp_source      Source of the SERP data (e.g., 'Google', 'Bing')
     * @var string $serp_query       The search query used for analysis
     * @var array  $serp_data        Raw SERP results data
     * @var array  $seo_before       SEO metadata state before AI optimization
     * @var array  $seo_after        SEO metadata state after AI optimization
     * @var array  $proposed_changes List of optimizations suggested by the AI
     * @var array  $audit_result     Detailed audit metrics and findings
     * @var string $summary          Brief text summary of the SEO analysis
     * @var int    $seo_score_before SEO score before optimization (0-100)
     * @var int    $seo_score_after  SEO score after optimization (0-100)
     * @var string $status           Report status (e.g., 'processed', 'error')
     * @var string $triggered_by     Trigger source (e.g., 'manual', 'api', 'webhook')
     * }
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