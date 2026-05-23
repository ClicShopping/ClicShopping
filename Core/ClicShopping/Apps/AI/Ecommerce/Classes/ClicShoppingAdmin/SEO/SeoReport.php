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
    public mixed     $app;
    protected string $urlSite  = '';
    protected string $linkUrl  = '';
    protected ?float $start    = null;
    protected ?float $end      = null;
    protected array  $css      = [];
    protected array  $js       = [];
    protected object $cache;

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
      $directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/ClicShopping/Work/Log/Cache/SEO/';

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
      $directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/ClicShopping/Work/Log/Cache/SEO/';
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
    public function getSeoData(bool $forceRefresh = false): array
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

      $report['googleanalytics'] = (str_contains($grabbedHTML, 'gtag(') || str_contains($grabbedHTML, 'google-analytics.com'));
      $report['pageloadtime']    = $this->getPageLoadTime();
      $report['flashtest']       = ($xpath->query('//embed|//object')->length > 0);
      $report['frametest']       = ($xpath->query('//frameset|//iframe')->length > 0);

      return $report;
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

    public function calculateSeoScore(array $report): int
    {
      $score = 0;
      if (!empty($report['titletext']))   $score += 20;
      if (!empty($report['description'])) $score += 20;
      if (!empty($report['h1']))          $score += 15;

      if ($report['images']['totImgs'] > 0) {
        $score += ($report['images']['diff'] === 0) ? 10 : 5;
      } else { $score += 10; }

      if (($report['pageloadtime'] ?? 5) < 2.5) $score += 10;
      if (!($report['flashtest'] ?? true))      $score += 5;
      if (!($report['frametest'] ?? true))      $score += 5;
      if ($report['googleanalytics'] ?? false)  $score += 5;

      return min(100, $score);
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