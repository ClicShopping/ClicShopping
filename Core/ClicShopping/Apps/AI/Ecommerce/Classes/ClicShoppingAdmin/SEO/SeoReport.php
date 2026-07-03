<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

  use ClicShopping\Apps\Marketing\SEO\SEO as SEOApp;
  use ClicShopping\OM\Cache;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\Registry;
  use GuzzleHttp\Client as GuzzleClient;

  class SeoReport
  {
    /**
     * Thin-content thresholds (statistically derived from search-engine
     * indexation studies — pages under 50 words rarely rank, 150 is the
     * lower bound for any meaningful SEO analysis, 300+ is the target for
     * product pages).  Used by getSiteMeta() to flag the report and by
     * calculateSeoScore() to cap the headline score accordingly.
     */
    public const THIN_CONTENT_CRITICAL_WORDS = 50;   // below: critical, score capped at 40
    public const THIN_CONTENT_WARNING_WORDS  = 150;  // below: warning,  score capped at 70
    public const THIN_CONTENT_TARGET_WORDS   = 300;  // recommended optimum for product pages
    public const THIN_CONTENT_CRITICAL_CAP   = 40;   // max seo_score when wordcount < CRITICAL_WORDS
    public const THIN_CONTENT_WARNING_CAP    = 70;   // max seo_score when wordcount < WARNING_WORDS

    public mixed     $app;
    protected string $urlSite  = '';
    protected string $linkUrl  = '';
    protected ?float $start    = null;
    protected ?float $end      = null;
    protected array  $css      = [];
    protected array  $js       = [];
    protected object $cache;
    private $cacheDirectory;

    public function __construct(string $linkUrl = '', string $urlSite = '')
    {
      $this->linkUrl  = $linkUrl;
      $this->urlSite  = $urlSite;

      if (!Registry::exists('SEO')) {
        Registry::set('SEO', new SEOApp());
      }

      $this->app = Registry::get('SEO');
      $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/seo');

      // Vérification et création du répertoire de cache avant d'instancier OM\Cache
      $this->ensureCacheDirectoryExists();

      $this->cache = new Cache('SEO', 'SEO');
      $this->cacheDirectory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/ClicShopping/Work/Cache/SEO/';

      // Nettoyage automatique (1 chance sur 50)
      if (random_int(1, 50) === 1) {
        $this->purgeOldCache(7);
      }
    }

    /**
     * Vérifie si le répertoire existe, sinon le crée
     */
    private function ensureCacheDirectoryExists(): void
    {
      $directory = $this->cacheDirectory;

      if (is_dir($directory)) {
        if (!is_writable($directory)) {
          @chmod($directory, 0775);
        }
        return;
      }

      $ancestor = rtrim($directory, '/');
      while ($ancestor !== '' && $ancestor !== dirname($ancestor) && !is_dir($ancestor)) {
        $ancestor = dirname($ancestor);
      }

      if (!is_dir($ancestor) || !is_writable($ancestor)) {
        error_log("[WARNING] SEO Report: Cannot create cache directory (permission denied): " . $directory . " — cache disabled for this request.");
        return;
      }

      if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        error_log("[WARNING] SEO Report: Directory creation failed: " . $directory);
      }
    }

    /**
     * Purge les fichiers de cache obsolètes
     */
    public function purgeOldCache(int $days = 7): int
    {
      $directory = $this->cacheDirectory;
      $count = 0;
      $expire = time() - ($days * 86400);

      if (is_dir($directory) && is_writable($directory)) {
        $files = glob($directory . 'seo_report_*');
        if ($files) {
          foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $expire) {
              if (unlink($file)) $count++;
            }
          }
        }
      }
      return $count;
    }

    public function getSeoReport(): string {
      $data = $this->getSeoData();
      if (!($data['isAlive'] ?? false)) return '<div class="alert alert-danger">URL Inaccessible</div>';
      return "<h3>Score SEO : {$data['seo_score']}/100</h3>";
    }

    /**
     * Récupération des données avec gestion du cache
     */
    public function getSeoData(bool $forceRefresh = false, string $entityType = ''): array
    {
      $cacheKey = 'seo_report_' . md5($this->linkUrl);

      if ($forceRefresh === false && $this->cache->exists($cacheKey)) {
        return $this->cache->get($cacheKey);
      }

      try {
        $isAlive = $this->isAlive();
        if (!$isAlive['STATUS']) {
          return ['isAlive' => false, 'url' => $this->linkUrl, 'http_code' => $isAlive['HTTP_CODE']];
        }

        $this->start   = microtime(true);
        $grabbedHTML   = $this->grabHTML($this->linkUrl);
        $this->end     = microtime(true);

        if ($grabbedHTML === false) return ['isAlive' => false];

        $report                 = $this->getSiteMeta($grabbedHTML);
        $report['isAlive']      = true;
        $report['url']          = $this->linkUrl;
        $report['generated_at'] = date('c');
        $report['seo_score']    = $this->calculateSeoScore($report);

        $this->cache->save($cacheKey, $report, 1440); // 24h

        return $report;
      } catch (\Throwable $e) {
        return ['isAlive' => false, 'error' => $e->getMessage()];
      }
    }

    private function isAlive(): array {
      try {
        $client = new GuzzleClient(['timeout' => 10, 'connect_timeout' => 5]);
        $response = $client->request('GET', $this->linkUrl, [
          'http_errors' => false,
          'allow_redirects' => ['max' => 5, 'track_redirects' => true],
        ]);
        $code = $response->getStatusCode();
        return ['HTTP_CODE' => $code, 'STATUS' => ($code >= 200 && $code < 400)];
      } catch (\Throwable $e) {
        return ['HTTP_CODE' => 0, 'STATUS' => false];
      }
    }

    private function grabHTML(string $url) { return HTTP::getResponse(['url' => $url]); }

    private function getSiteMeta(string $grabbedHTML): array
    {
      $html = new \DOMDocument();
      libxml_use_internal_errors(true);
      $html->loadHTML('<?xml encoding="utf-8" ?>' . $grabbedHTML);
      libxml_use_internal_errors(false);

      $xpath = new \DOMXPath($html);
      $report = [];

      foreach ($xpath->query('//title') as $tit) {
        $report['titletext'] = $tit->textContent;
      }

      foreach ($xpath->query('//meta') as $meta) {
        $name = $meta->getAttribute('name') ?: $meta->getAttribute('property');
        if ($name) $report[strtolower($name)] = $meta->getAttribute('content');
      }

      $onlyText = $this->stripHtmlTags($grabbedHTML);
      if (!empty($onlyText)) {
        $words = preg_split('/[\s,.:;!?"()]+/u', mb_strtolower($onlyText), -1, PREG_SPLIT_NO_EMPTY);
        $grammar = $this->grammar();
        $counts = [];
        foreach ($words as $w) {
          $w = preg_replace('/[^\p{L}\p{N}\-]/u', '', $w);
          if (mb_strlen($w) > 2 && !in_array($w, $grammar)) {
            $counts[$w] = ($counts[$w] ?? 0) + 1;
          }
        }
        arsort($counts);
        $report['wordcountmax'] = array_slice($counts, 0, 8, true);
      }

      foreach (['h1', 'h2', 'h3'] as $h) {
        foreach ($xpath->query("//$h") as $node) $report[$h][] = trim($node->textContent);
      }

      $imgs = $xpath->query('//img');
      $alts = 0;
      foreach ($imgs as $i) if (!empty(trim($i->getAttribute('alt')))) $alts++;
      $report['images'] = ['totImgs' => $imgs->length, 'totAlts' => $alts, 'diff' => $imgs->length - $alts];

      $report['internal_links'] = self::countInternalLinks($grabbedHTML, (string)parse_url($this->linkUrl, PHP_URL_HOST));

      $report['googleanalytics'] = (str_contains($grabbedHTML, 'gtag(') || str_contains($grabbedHTML, 'google-analytics.com'));
      $report['pageloadtime']    = $this->getPageLoadTime();
      $report['flashtest']       = ($xpath->query('//embed|//object')->length > 0);
      $report['frametest']       = ($xpath->query('//frameset|//iframe')->length > 0);

      // FAQ presence detection — looks for the FAQ module's hallmark
      // markers in the rendered HTML:
      //   - itemtype="https://schema.org/FAQPage" (structured-data anchor
      //     emitted by sources/template/.../content/products_info_description_faq.php)
      //   - class="modulesProductsInfoFaq" (the front-office wrapper)
      // Detected through string scan (cheap) and counted in faq.questions
      // through schema.org Question itemtype, which gives a more reliable
      // signal than counting accordion items.
      $hasFaqPage  = str_contains($grabbedHTML, 'schema.org/FAQPage')
                  || str_contains($grabbedHTML, 'modulesProductsInfoFaq');
      $questionCount = 0;
      if ($hasFaqPage) {
        $questionCount = substr_count($grabbedHTML, 'schema.org/Question');
      }
      $report['faq'] = [
        'detected'  => $hasFaqPage,
        'questions' => $questionCount,
        // The FAQ module renders only when ≥ 2 entries are present; mirror
        // that threshold here so the SEO score only credits real FAQs.
        'valid'     => $hasFaqPage && $questionCount >= 2,
      ];

      // Schema.org JSON-LD detection.  Parse every <script type="application/ld+json">
      // block, extract the @type values, and flag the block as valid only when
      // the JSON parses cleanly.  This populates the array consumed by both
      // ProductsSerp::renderSchemaBadge() (visual badge) and the schema bonus
      // in calculateSeoScore() (+5 pts).
      $schemaDetected = false;
      $schemaTypes    = [];
      $schemaValid    = true;
      $ldNodes        = $xpath->query('//script[@type="application/ld+json"]');
      foreach ($ldNodes as $node) {
        $raw = trim($node->textContent ?? '');
        if ($raw === '') {
          continue;
        }
        $schemaDetected = true;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
          $schemaValid = false;
          continue;
        }
        // The block may be a single object or a @graph array.
        $candidates = isset($decoded['@graph']) && is_array($decoded['@graph'])
          ? $decoded['@graph']
          : [$decoded];
        foreach ($candidates as $entry) {
          if (is_array($entry) && !empty($entry['@type'])) {
            $type = $entry['@type'];
            if (is_array($type)) {
              foreach ($type as $t) { $schemaTypes[] = (string)$t; }
            } else {
              $schemaTypes[] = (string)$type;
            }
          }
        }
      }
      $report['schema_org'] = [
        'detected' => $schemaDetected,
        'types'    => array_values(array_unique($schemaTypes)),
        'valid'    => $schemaValid,
      ];

      // ── Body word count + thin-content warning ─────────────────────────
      // Thin content is a Google E-E-A-T signal: pages with fewer than
      // ~150 words rarely rank, and pages with < 50 words are typically
      // treated as no-value placeholders.  We surface this BEFORE the SEO
      // optimization so the admin knows the source description is too
      // short for meaningful SEO work, and the score is capped accordingly.
      //
      // Thresholds (statistically motivated):
      //   < 50  words  → critical (score capped at 40)
      //   < 150 words  → warning  (score capped at 70)
      //   ≥ 300 words  → optimal  (no cap)
      $bodyText  = $this->stripHtmlTags($grabbedHTML);
      $wordCount = $bodyText !== ''
        ? (int)str_word_count(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $bodyText))
        : 0;
      $report['wordcount_body'] = (int)$wordCount;

      // Normalised level enum: 'critical' | 'warning' | 'ok'
      if ($wordCount < self::THIN_CONTENT_CRITICAL_WORDS) {
        $report['thin_content']       = true;
        $report['thin_content_level'] = 'critical';
        $report['thin_content_msg']   = (string)$this->app->getDef(
          'text_seo_thin_content_critical',
          ['wordCount' => $wordCount]
        );
      } elseif ($wordCount < self::THIN_CONTENT_WARNING_WORDS) {
        $report['thin_content']       = true;
        $report['thin_content_level'] = 'warning';
        $report['thin_content_msg']   = (string)$this->app->getDef(
          'text_seo_thin_content_warning',
          ['wordCount' => $wordCount]
        );
      } else {
        $report['thin_content']       = false;
        $report['thin_content_level'] = 'ok';
        $report['thin_content_msg']   = '';
      }

      return $report;
    }

    /**
     * Evaluate thin-content level for a given source text (typically the
     * products_description from the database, not the full rendered page).
     *
     * Returns the same structured verdict shape used by getSiteMeta() so
     * the SEO pipeline can override the page-level evaluation when the
     * actual SOURCE description is too short to optimize meaningfully —
     * which is the real signal admins act on, not the page total
     * (header/footer/menu would otherwise mask short descriptions).
     *
     * @return array{
     *   word_count:int,
     *   level:string,
     *   thin_content:bool,
     *   message:string
     * }
     */
    public function evaluateSourceThinContent(string $sourceText): array
    {
      $cleanText = trim(strip_tags($sourceText));
      $wordCount = $cleanText !== ''
        ? (int)str_word_count(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $cleanText))
        : 0;

      if ($wordCount < self::THIN_CONTENT_CRITICAL_WORDS) {
        return [
          'word_count'   => $wordCount,
          'level'        => 'critical',
          'thin_content' => true,
          'message'      => (string)$this->app->getDef(
            'text_seo_thin_content_critical',
            ['wordCount' => $wordCount]
          ),
        ];
      }
      if ($wordCount < self::THIN_CONTENT_WARNING_WORDS) {
        return [
          'word_count'   => $wordCount,
          'level'        => 'warning',
          'thin_content' => true,
          'message'      => (string)$this->app->getDef(
            'text_seo_thin_content_warning',
            ['wordCount' => $wordCount]
          ),
        ];
      }
      return [
        'word_count'   => $wordCount,
        'level'        => 'ok',
        'thin_content' => false,
        'message'      => '',
      ];
    }

    private function stripHtmlTags(string $s): string {
      $s = preg_replace('@<(head|style|script|noscript)[^>]*?>.*?</\1>@siu', '', $s);
      return trim(strip_tags(html_entity_decode($s)));
    }

    public function grammar(): array {
      if (!Registry::exists('Hooks')) {
        return [];
      }
      $hooks = Registry::get('Hooks');
      $res = $hooks->call('SEO', 'SeoReportGrammar');
      return is_array($res) ? $res : [];
    }

    private function getPageLoadTime(): float {
      return ($this->start && $this->end) ? round($this->end - $this->start, 3) : 0.0;
    }

    /**
     * Count internal links: <a href> that are root-relative or point at $shopHost.
     * Excludes external hosts, pure anchors (#...), mailto:/tel:, and javascript:.
     */
    public static function countInternalLinks(string $html, string $shopHost): int
    {
      if ($html === '') {
        return 0;
      }
      if (!preg_match_all('/<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
        return 0;
      }
      $host = strtolower($shopHost);
      $count = 0;
      foreach ($m[1] as $href) {
        $href = trim($href);
        if ($href === '' || $href[0] === '#'
          || stripos($href, 'mailto:') === 0
          || stripos($href, 'tel:') === 0
          || stripos($href, 'javascript:') === 0) {
          continue;
        }
        if ($href[0] === '/') {            // root-relative → internal
          $count++;
          continue;
        }
        $h = strtolower((string)parse_url($href, PHP_URL_HOST));
        if ($h !== '' && ($h === $host || str_ends_with($h, '.' . $host))) {
          $count++;
        }
      }
      return $count;
    }

    public function calculateSeoScore(array $report): int
    {
      // Re-weighted to make space for FAQ scoring (10 pts) while keeping the
      // historical "no-FAQ ceiling" near 80, so Phase 2 reports a meaningful
      // headroom (the optimization without a FAQ caps at ~80) and Phase 3
      // delivers a visible lift (+10) once the FAQ block is rendered on the
      // public-front page.
      //
      // Breakdown (total 100):
      //   title          15
      //   description    15
      //   h1             10
      //   images (alt)   5-10
      //   pageloadtime   10
      //   flashtest      5
      //   frametest      5
      //   analytics      5
      //   FAQ            10  ← new
      //   schema.org JSON-LD 5  ← surfaced if present in HTML
      //   subtotal       80-90 without FAQ; up to 100 with FAQ + schema
      $score = 0;
      if (!empty($report['titletext']))   $score += 15;
      if (!empty($report['description'])) $score += 15;
      if (!empty($report['h1']))          $score += 10;

      if ($report['images']['totImgs'] > 0) {
        $score += ($report['images']['diff'] === 0) ? 10 : 5;
      } else { $score += 10; }

      if (($report['pageloadtime'] ?? 5) < 2.5) $score += 10;
      if (!($report['flashtest'] ?? true))      $score += 5;
      if (!($report['frametest'] ?? true))      $score += 5;
      if ($report['googleanalytics'] ?? false)  $score += 5;

      // FAQ block (10 pts) — credited only when the FAQ module rendered at
      // least 2 questions (matches the module's own gating in
      // pi_products_info_description_faq.php).  This makes Phase 3 ≈ +10
      // points over Phase 2 once the FAQ is saved and the page is re-crawled.
      if (($report['faq']['valid'] ?? false) === true) {
        $score += 10;
      }

      // Schema.org JSON-LD (5 pts) — quick credit for rich-results
      // eligibility.  Lazy detection via the schema_org block populated
      // upstream by getSeoData() (already present in older code paths).
      if (($report['schema_org']['detected'] ?? false) === true
          && ($report['schema_org']['valid'] ?? true) === true) {
        $score += 5;
      }

      $score = min(100, $score);

      // Thin-content cap: degrade the headline score when the page body
      // is too short to support meaningful SEO.  Google penalises thin
      // pages aggressively, so a 90/100 on a 30-word page would be
      // misleading.  Hard-cap to reflect statistical reality.  The cap
      // values mirror the thresholds declared at the top of the class.
      $level = $report['thin_content_level'] ?? 'ok';
      if ($level === 'critical') {
        $score = min($score, self::THIN_CONTENT_CRITICAL_CAP);
      } elseif ($level === 'warning') {
        $score = min($score, self::THIN_CONTENT_WARNING_CAP);
      }

      return (int)$score;
    }

    public function serializeForEmbedding(array $report): string {
      $parts = [
        "URL: " . ($report['url'] ?? 'N/A'),
        "Score: " . ($report['seo_score'] ?? 0) . "/100",
        "Title: " . ($report['titletext'] ?? 'N/A'),
        "Desc: " . ($report['description'] ?? 'N/A'),
        "Performance: " . ($report['pageloadtime'] ?? 0) . "s"
      ];
      return implode("\n", $parts);
    }
  }