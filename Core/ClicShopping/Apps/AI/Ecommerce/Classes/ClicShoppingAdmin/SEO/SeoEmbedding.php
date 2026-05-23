<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
  use ClicShopping\AI\Rag\MultiDBRAGManager;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoReport;
  class SeoEmbedding
  {
    private mixed               $db;
    private ?MultiDBRAGManager  $ragManager  = null;
    private string              $dbTableFull;
    private string              $prefix;

    /**
     * @param string $dbTable  Nom de la table embedding passé depuis le hook
     *                         (ex: 'categories_seo_embedding').
     *                         Le préfixe ClicShopping est appliqué automatiquement.
     */
    public function __construct(string $dbTable)
    {
      $this->db      = Registry::get('Db');
      $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
      // Avoid double prefix if already provided
      if (str_starts_with($dbTable, $this->prefix)) {
        $this->dbTableFull = $dbTable;
      } else {
        $this->dbTableFull = $this->prefix . $dbTable;
      }
    }

    // ============================================================
    // POINT D'ENTRÉE PRINCIPAL
    // ============================================================

    /**
     * Orchestre la décision : analyse initiale OU optimisation.
     */
    public function process(
      int    $entityId,
      int    $languageId,
      string $url,
      string $baseUrl,
      string $pageType    = 'category',
      string $triggeredBy = 'manual'
    ): array {
      $existing = $this->getLatestReport($entityId, $languageId);

      if ($existing === null) {
        return $this->runInitialAnalysis($entityId, $languageId, $url, $baseUrl, $pageType, $triggeredBy);
      }

      return $this->runOptimizationCycle($entityId, $languageId, $url, $baseUrl, $pageType, $triggeredBy, $existing);
    }

    // ============================================================
    // LECTURE DB
    // ============================================================

    /**
     * Récupère le rapport le plus récent pour une entité/langue.
     * Retourne null si aucun enregistrement → déclenchera l'analyse initiale.
     *
     * FIX SQL : la table ne peut pas être un paramètre PDO bindé.
     * On utilise $this->dbTableFull interpolé dans la chaîne,
     * et on retire la syntaxe invalide ':table_xxx'.
     */
    public function getLatestReport(int $entityId, int $languageId): ?array
    {
      $stmt = $this->db->prepare('SELECT id, 
                                         content, 
                                         type, 
                                         sourcetype, 
                                         sourcename, 
                                         date_modified, 
                                         metadata
                                 FROM ' . $this->dbTableFull . '
                                 WHERE  entity_id   = :entity_id
                                   AND  language_id = :language_id
                                 ORDER BY date_modified DESC
                                 LIMIT 1'
                                );

      $stmt->bindInt(':entity_id',   $entityId);
      $stmt->bindInt(':language_id', $languageId);
      $stmt->execute();

      $row = $stmt->fetch();

      return $row ?: null;
    }

    private function runInitialAnalysis(
      int    $entityId,
      int    $languageId,
      string $url,
      string $baseUrl,
      string $pageType,
      string $triggeredBy
    ): array {
      $seoReport = new SeoReport($url, $baseUrl);
      $data      = $seoReport->getSeoData();

      if (!($data['isAlive'] ?? false)) {
        return [
          'success' => false,
          'mode'    => 'initial',
          'error'   => 'Page inaccessible : ' . ($data['error'] ?? 'HTTP ' . ($data['http_code'] ?? '?')),
        ];
      }

      $sourceThin = $this->evaluateSourceThinForEntity($entityId, $languageId, $pageType, $seoReport);
      if ($sourceThin !== null) {
        $pageLevel = $data['thin_content_level'] ?? 'ok';
        if ($this->thinSeverity($sourceThin['level']) > $this->thinSeverity($pageLevel)) {
          $data['thin_content']       = $sourceThin['thin_content'];
          $data['thin_content_level'] = $sourceThin['level'];
          $data['thin_content_msg']   = $sourceThin['message'];
          // Reapply cap on the seo_score with the worse level.
          if ($sourceThin['level'] === 'critical') {
            $data['seo_score'] = min((int)($data['seo_score'] ?? 0), SeoReport::THIN_CONTENT_CRITICAL_CAP);
          } elseif ($sourceThin['level'] === 'warning') {
            $data['seo_score'] = min((int)($data['seo_score'] ?? 0), SeoReport::THIN_CONTENT_WARNING_CAP);
          }
        }
        $data['source_wordcount'] = $sourceThin['word_count'];
      }

      $textForEmbedding = $seoReport->serializeForEmbedding($data);

      $metadata = [
        'page_type'        => $pageType,
        'url'              => $url,
        'seo_score_before' => $data['seo_score'] ?? 0,
        'seo_score_after'  => null,
        'status'           => 'initial',
        'triggered_by'     => $triggeredBy,
        'report_raw'       => $this->filterRawReport($data),
        'serp_data'        => null,
        'suggestions'      => null,
        'audit_result'     => null,
      ];

      $id = $this->storeEmbedding(
        content:    $textForEmbedding,
        type:       'initial_report',
        sourcetype: $triggeredBy,
        sourcename: 'SeoReport',
        entityType: $pageType,
        entityId:   $entityId,
        languageId: $languageId,
        metadata:   $metadata
      );

      $reportHtml = '';
      if (method_exists($seoReport, 'getHTMLReport')) {
        $reportHtml = $seoReport->getHTMLReport($data);
      } elseif (method_exists($seoReport, 'getSeoReport')) {
        $reportHtml = $seoReport->getSeoReport();
      }

      return [
        'success'             => true,
        'mode'                => 'initial',
        'embedding_id'        => $id,
        'seo_score'           => $data['seo_score']           ?? 0,
        'report'              => $reportHtml,
        'message'             => 'Analyse initiale effectuée. Score SEO : ' . ($data['seo_score'] ?? 0) . '/100.',
        'thin_content'        => (bool)($data['thin_content']       ?? false),
        'thin_content_level'  => (string)($data['thin_content_level'] ?? 'ok'),
        'thin_content_msg'    => (string)($data['thin_content_msg']   ?? ''),
        'wordcount_body'      => (int)($data['wordcount_body']        ?? 0),
        'source_wordcount'    => (int)($data['source_wordcount']      ?? 0),
      ];
    }

    // ============================================================
    // ANALYSE INITIALE — aucun historique trouvé
    // ============================================================

    private function filterRawReport(array $data): array
    {
      return array_diff_key($data, array_flip(['wordCount', 'generated_at']));
    }

    /**
     * Read the source description directly from the database (not from the
     * crawled front-office HTML) and evaluate its thin-content level.
     *
     * The page crawl mixes header / menu / footer / structured-data into the
     * word count, which hides short product descriptions behind page
     * chrome.  The DB read gives us the actual editable content the admin
     * needs to expand — far more actionable.
     *
     * Returns null when the entity type is unknown or the row is missing.
     *
     * @return array{word_count:int, level:string, thin_content:bool, message:string}|null
     */
    private function evaluateSourceThinForEntity(
      int       $entityId,
      int       $languageId,
      string    $pageType,
      SeoReport $seoReport
    ): ?array {
      $table  = match ($pageType) {
        'product'  => 'products_description',
        'category' => 'categories_description',
        default    => null,
      };
      $column = match ($pageType) {
        'product'  => 'products_description',
        'category' => 'categories_description',
        default    => null,
      };
      $idCol  = match ($pageType) {
        'product'  => 'products_id',
        'category' => 'categories_id',
        default    => null,
      };
      if ($table === null || $column === null || $idCol === null) {
        return null;
      }

      try {
        $stmt = $this->db->prepare(
          'SELECT ' . $column . ' AS source_text
             FROM :table_' . $table . '
            WHERE ' . $idCol . ' = :eid
              AND language_id = :lid
            LIMIT 1'
        );
        $stmt->bindInt(':eid', $entityId);
        $stmt->bindInt(':lid', $languageId);
        $stmt->execute();
        $row = $stmt->fetch();
      } catch (\Throwable $e) {
        return null;
      }

      if (!$row || empty($row['source_text'])) {
        return null;
      }

      return $seoReport->evaluateSourceThinContent((string)$row['source_text']);
    }

    /**
     * Numeric severity for thin-content level so callers can pick the
     * "worse" of two evaluations (page-level vs source-level).
     */
    private function thinSeverity(string $level): int
    {
      return match ($level) {
        'critical' => 2,
        'warning'  => 1,
        default    => 0,
      };
    }

    // ============================================================
    // CYCLE D'OPTIMISATION — historique existant
    // ============================================================

    /**
     * Insère un embedding via le pipeline AI (RAG / addDocument).
     * Retourne l'ID de la ligne insérée.
     */
    private function storeEmbedding(
      string $content,
      string $type,
      string $sourcetype,
      string $sourcename,
      string $entityType,
      int    $entityId,
      int    $languageId,
      array  $metadata
    ): int {

      // On exclut report_raw du metadata passé à addDocument :
      // il est très lourd (tout le DOM parsé) et peut dépasser les limites
      // de sérialisation JSON de MariaDBVectorStore.
      // Il reste disponible dans $metadata pour d'autres usages si nécessaire.
      $metadataForDoc = array_diff_key($metadata, array_flip(['report_raw']));

      $ok = $this->getRagManager()->addDocument(
        content:    $content,
        tableName:  $this->dbTableFull,
        type:       $type,
        sourceType: $sourcetype,
        sourceName: $sourcename,
        entityType: $entityType,
        entityId:   $entityId,
        languageId: $languageId,
        metadata:   $metadataForDoc
      );

      if (!$ok) {
        // Récupérer le vrai message d'erreur depuis le security log
        throw new \RuntimeException(
          'Failed to store SEO embedding via AI pipeline. ' .
          'Table: ' . $this->dbTableFull . ' | ' .
          'Check security log for actual exception.'
        );
      }

      $latest = $this->getLatestReport($entityId, $languageId);

      return (int) ($latest['id'] ?? 0);
    }

    // ============================================================
    // STOCKAGE EMBEDDING — pipeline RAG
    // ============================================================

    /**
     * @return MultiDBRAGManager
     * @throws \Exception
     */
    private function getRagManager(): MultiDBRAGManager
    {
      if ($this->ragManager === null) {
        Gpt::getEnvironment();
        $this->ragManager = new MultiDBRAGManager(null, []);
      }

      return $this->ragManager;
    }

    private function runOptimizationCycle(
      int    $entityId,
      int    $languageId,
      string $url,
      string $baseUrl,
      string $pageType,
      string $triggeredBy,
      array  $previousRecord
    ): array {
      $seoReport = new SeoReport($url, $baseUrl);
      $dataNow   = $seoReport->getSeoData();

      if (!($dataNow['isAlive'] ?? false)) {
        return [
          'success' => false,
          'mode'    => 'optimization',
          'error'   => 'Page inaccessible lors du re-crawl.',
        ];
      }

      $prevMeta    = json_decode($previousRecord['metadata'] ?? '{}', true);
      $scoreBefore = (int) ($prevMeta['seo_score_before'] ?? 0);
      $scoreNow    = (int) ($dataNow['seo_score'] ?? 0);

      $suggestions = $this->buildAiSuggestions($dataNow, $prevMeta);
      $auditResult = $this->buildAuditResult($scoreBefore, $scoreNow, $dataNow, $prevMeta);

      $embeddingText = $seoReport->serializeForEmbedding($dataNow)
        . "\n\nSuggestions:\n" . $this->serializeSuggestions($suggestions)
        . "\nAudit:\n" . ($auditResult['summary'] ?? '');

      $metadata = [
        'page_type'        => $pageType,
        'url'              => $url,
        'seo_score_before' => $scoreBefore,
        'seo_score_after'  => $scoreNow,
        'status'           => $auditResult['improved'] ? 'applied' : 'completed',
        'triggered_by'     => $triggeredBy,
        'report_raw'       => $this->filterRawReport($dataNow),
        'serp_data'        => null,
        'suggestions'      => $suggestions,
        'audit_result'     => $auditResult,
      ];

      $id = $this->storeEmbedding(
        content:    $embeddingText,
        type:       'optimized_report',
        sourcetype: $triggeredBy,
        sourcename: 'AgentSeo',
        entityType: $pageType,
        entityId:   $entityId,
        languageId: $languageId,
        metadata:   $metadata
      );

      $reportHtml = '';
      if (method_exists($seoReport, 'getHTMLReport')) {
        $reportHtml = $seoReport->getHTMLReport($dataNow);
      } elseif (method_exists($seoReport, 'getSeoReport')) {
        $reportHtml = $seoReport->getSeoReport();
      }

      return [
        'success'        => true,
        'mode'           => 'optimization',
        'embedding_id'   => $id,
        'seo_score_prev' => $scoreBefore,
        'seo_score_now'  => $scoreNow,
        'improved'       => $auditResult['improved'],
        'suggestions'    => $suggestions,
        'audit_summary'  => $auditResult['summary'],
        'report'         => $reportHtml,
        'message'        => $auditResult['summary'],
      ];
    }

    // ============================================================
    // SUGGESTIONS IA (stub → AgentSeo)
    // ============================================================

    private function buildAiSuggestions(array $current, array $prevMeta): array
    {
      $suggestions = [];

      if (empty($current['titletext'])) {
        $suggestions['title'] = '[À générer par AgentSeo] — Titre manquant';
      } elseif (\strlen($current['titletext']) < 30) {
        $suggestions['title'] = '[À optimiser] — Titre trop court (' . \strlen($current['titletext']) . ' car.)';
      }

      if (empty($current['description'])) {
        $suggestions['description'] = '[À générer par AgentSeo] — Description manquante';
      } elseif (\strlen($current['description']) < 120 || \strlen($current['description']) > 160) {
        $suggestions['description'] = '[À optimiser] — Description : ' . \strlen($current['description']) . ' car. (idéal 120-160)';
      }

      if (empty($current['h1'])) {
        $suggestions['h1'] = '[À créer] — Balise H1 absente';
      }

      if (empty($current['h2'])) {
        $suggestions['h2'] = '[Recommandé] — Aucune balise H2 détectée';
      }

      if (($current['googleanalytics'] ?? false) === false) {
        $suggestions['analytics'] = 'Intégrer Google Analytics (GA4) ou GTM';
      }

      if (($current['images']['diff'] ?? 0) > 0) {
        $suggestions['images_alt'] = $current['images']['diff'] . ' image(s) sans attribut ALT';
      }

      if (!empty($current['css']['cssNotMinFiles'])) {
        $suggestions['css_minify'] = count($current['css']['cssNotMinFiles']) . ' fichier(s) CSS à minifier';
      }

      if (!empty($current['js']['jsNotMinFiles'])) {
        $suggestions['js_minify'] = count($current['js']['jsNotMinFiles']) . ' fichier(s) JS à minifier';
      }

      if (($current['pageloadtime'] ?? 0) > 3) {
        $suggestions['performance'] = 'Temps de chargement élevé : ' . round($current['pageloadtime'], 2) . 's (seuil : 3s)';
      }

      return $suggestions;
    }

    // ============================================================
    // AUDIT COMPARATIF (stub → AgentAuditSeo)
    // ============================================================

    private function buildAuditResult(int $scoreBefore, int $scoreNow, array $current, array $prevMeta): array
    {
      $delta    = $scoreNow - $scoreBefore;
      $improved = $delta > 0;

      if ($improved) {
        $summary = sprintf(
          'Score SEO amélioré : %d → %d (+%d pts). La page progresse.',
          $scoreBefore, $scoreNow, $delta
        );
      } elseif ($delta === 0) {
        $summary = sprintf(
          'Score SEO stable : %d/100. Aucune régression, mais des optimisations restent possibles.',
          $scoreNow
        );
      } else {
        $summary = sprintf(
          'Score SEO en baisse : %d → %d (%d pts). Analyse des régressions recommandée.',
          $scoreBefore, $scoreNow, $delta
        );
      }

      $prevRaw         = $prevMeta['report_raw'] ?? [];
      $changesDetected = [];

      if (($prevRaw['titletext'] ?? '') !== ($current['titletext'] ?? '')) {
        $changesDetected[] = 'title';
      }
      if (($prevRaw['description'] ?? '') !== ($current['description'] ?? '')) {
        $changesDetected[] = 'description';
      }
      if (($prevRaw['h1'][0] ?? '') !== ($current['h1'][0] ?? '')) {
        $changesDetected[] = 'h1';
      }

      return [
        'improved'        => $improved,
        'delta'           => $delta,
        'score_before'    => $scoreBefore,
        'score_after'     => $scoreNow,
        'summary'         => $summary,
        'changes_applied' => $changesDetected,
      ];
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function serializeSuggestions(array $suggestions): string
    {
      if (empty($suggestions)) {
        return 'Aucune suggestion.';
      }

      $lines = [];
      foreach ($suggestions as $key => $value) {
        $lines[] = strtoupper($key) . ': ' . $value;
      }

      return implode("\n", $lines);
    }

    /**
     * Persist an optimized_report entry produced by Phase 2 of the new
     * 3-button workflow (SeoMultilingualOrchestrator → SeoAgenticPipeline).
     *
     * The agentic pipeline writes its own audit row in products_seo_serp_report
     * but does NOT touch products_seo_embedding.  Phase 2 needs a per-language
     * trace in the embedding history so the display hook (ProductsSerp) can:
     *   - mark Phase 2 as completed (getLatestReport != initial_report),
     *   - render the per-language history table.
     *
     * This method goes through the same MultiDBRAGManager pipeline as the
     * existing initial / cycle writers — it does NOT bypass the RAG layer,
     * so the VECTOR(3072) column is populated normally.
     *
     * @param int    $entityId    Product / category primary key.
     * @param int    $languageId  Target language id (the row written is this language).
     * @param string $pageType    'product' | 'category' | ...
     * @param string $url         Public-front URL of the entity for this language.
     * @param int    $scoreBefore SEO score captured before the run.
     * @param int    $scoreAfter  SEO score captured after the run.
     * @param array  $appliedFields Subset of SeoEntityAdapter::normalizeChanges()
     *                             that was actually saved to products_description.
     * @param array  $auditResult Audit payload produced by SeoAuditAgent (or compatible shape).
     * @param string $triggeredBy 'manual' | 'ajax' | 'cron' | ...
     * @param string $sourceName  Identifier for the writer: defaults to 'SeoMultilingualOrchestrator'.
     * @return int  Newly inserted row id, or 0 on failure.
     */
    public function recordOptimizedReport(
      int    $entityId,
      int    $languageId,
      string $pageType,
      string $url,
      int    $scoreBefore,
      int    $scoreAfter,
      array  $appliedFields,
      array  $auditResult = [],
      string $triggeredBy = 'manual',
      string $sourceName  = 'SeoMultilingualOrchestrator',
      string $type        = 'optimized_report',
      array  $benchmark   = []
    ): int {
      // Build the textual payload that the RAG layer will embed.  We embed
      // the applied SEO fields so semantic search over the history makes
      // sense (matching titles / descriptions / keywords).
      $contentLines = [];
      foreach ($appliedFields as $key => $value) {
        if ($value === '' || $value === null || is_array($value)) {
          continue;
        }
        $contentLines[] = strtoupper((string)$key) . ': ' . (string)$value;
      }
      if (!empty($auditResult['summary'])) {
        $contentLines[] = 'AUDIT: ' . (string)$auditResult['summary'];
      }
      $content = implode("\n", $contentLines);
      if ($content === '') {
        // Nothing meaningful to embed → do not pollute the history.
        return 0;
      }

      $improved = (bool)($auditResult['improved'] ?? ($scoreAfter > $scoreBefore));
      $status   = $improved ? 'applied' : 'completed';

      $metadata = [
        'page_type'        => $pageType,
        'url'              => $url,
        'seo_score_before' => $scoreBefore,
        'seo_score_after'  => $scoreAfter,
        'status'           => $status,
        'triggered_by'     => $triggeredBy,
        'report_raw'       => null,
        'serp_data'        => null,
        'suggestions'      => $appliedFields,
        'audit_result'     => $auditResult,
        // Benchmark verdict + breakdown so the history modal can render
        // a side-by-side source-vs-generated comparison table.
        'benchmark'        => $benchmark,
      ];

      try {
        return $this->storeEmbedding(
          content:    $content,
          type:       $type,
          sourcetype: $triggeredBy,
          sourcename: $sourceName,
          entityType: $pageType,
          entityId:   $entityId,
          languageId: $languageId,
          metadata:   $metadata
        );
      } catch (\Throwable $e) {
        error_log('[SeoEmbedding] recordOptimizedReport failed: ' . $e->getMessage());
        return 0;
      }
    }

    /**
     * Récupère l'historique complet pour une entité/langue.
     *
     * FIX SQL : même correction que getLatestReport — table interpolée,
     * pas de ':table_xxx'. bindValue pour :lim car bindInt n'existe pas toujours.
     */
    public function getHistory(int $entityId, int $languageId, int $limit = 10): array
    {
      $stmt = $this->db->prepare('SELECT id, 
                                         type, 
                                         sourcename, 
                                         date_modified, 
                                         metadata
                                 FROM ' . $this->dbTableFull . '
                                 WHERE  entity_id   = :entity_id
                                   AND  language_id = :language_id
                                 ORDER BY date_modified DESC
                                 LIMIT :lim'
                                );

      $stmt->bindInt(':entity_id',   $entityId);
      $stmt->bindInt(':language_id', $languageId);
      $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
      $stmt->execute();

      return $stmt->fetchAll() ?: [];
    }
  }
