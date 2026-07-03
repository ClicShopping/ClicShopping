<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

  use ClicShopping\OM\Registry;

  /**
   * Class SeoActionLogRepository
   *
   * Append-only audit trail of the manual SEO actions performed by an
   * administrator on an entity (accept / reject / revert). One row per action,
   * so the history modal can show a chronological, attributable trace ("who did
   * what, when"). This is deliberately NOT stored in products_seo_embedding:
   * that table carries a NOT NULL VECTOR(3072) meant for RAG search, and action
   * entries have no content to embed.
   *
   * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO
   */
  class SeoActionLogRepository
  {
    /** @var mixed The database connection instance */
    private mixed $db;

    public function __construct()
    {
      $this->db = Registry::get('Db');
    }

    /**
     * Append one action to the trail.
     *
     * @param string               $entityType e.g. 'product'
     * @param int                  $entityId
     * @param int                  $languageId 0 = the action applies to every language (revert)
     * @param string               $action     accepted | rejected | reverted | optimized
     * @param int|null             $adminId    administrator id (0/null when unknown)
     * @param string               $adminName  administrator username (display)
     * @param array<string, mixed> $metadata   optional context (scores, mode, …)
     * @return int The id of the inserted row.
     */
    public function record(
      string $entityType,
      int    $entityId,
      int    $languageId,
      string $action,
      ?int   $adminId,
      string $adminName,
      array  $metadata = []
    ): int {
      $data = [
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'language_id' => $languageId,
        'action'      => $action,
        'admin_id'    => (int)($adminId ?? 0),
        'admin_name'  => $adminName,
        'metadata'    => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'date_added'  => date('Y-m-d H:i:s'),
      ];

      $this->db->save('seo_product_action_log', $data);

      return (int)$this->db->lastInsertId();
    }

    /**
     * Every action recorded for an entity (all languages), newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getForEntity(string $entityType, int $entityId, int $limit = 20): array
    {
      $stmt = $this->db->prepare(
        'SELECT id, entity_type, entity_id, language_id, action, admin_id, admin_name, metadata, date_added
           FROM :table_seo_product_action_log
          WHERE entity_type = :entity_type
            AND entity_id   = :entity_id
          ORDER BY date_added DESC, id DESC
          LIMIT :lim'
      );

      $stmt->bindValue(':entity_type', $entityType);
      $stmt->bindInt(':entity_id', $entityId);
      $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
      $stmt->execute();

      return $stmt->fetchAll() ?: [];
    }
  }
