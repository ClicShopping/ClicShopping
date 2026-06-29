<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  declare(strict_types=1);

  namespace ClicShopping\Apps\AI\Ecommerce\Module\HeaderTags;

  use ClicShopping\OM\Cache as CacheApp;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;
  use ClicShopping\OM\Domains\HeaderTagsAbstract;
  use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
  use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\Llms\Llms;

  final class LlmsTxt extends HeaderTagsAbstract
  {
    private EcommerceApp $app;
    private Llms $llmsTxt;
    public string $group;

    /**
     * TTL du cache en MINUTES (Cache::exists() compare filemtime() en minutes,
     * pas en secondes — l'ancienne valeur "86400" était des secondes, soit ~1 440x trop grand).
     */
    private const int CACHE_TTL_MINUTES = 1440; // 24 h

    protected function init(): void
    {
      if (!Registry::exists('Ecommerce')) {
        Registry::set('Ecommerce', new EcommerceApp());
      }

      $this->app = Registry::get('Ecommerce');

      if (!Registry::exists('Llms')) {
        Registry::set('Llms', new Llms());
      }

      $this->llmsTxt = Registry::get('Llms');

      $this->group = 'header_tags';

      $this->app->loadDefinitions('Module/HeaderTags/llms_txt');

      $this->title       = $this->app->getDef('module_header_tags_llms_txt_title');
      $this->description = $this->app->getDef('module_header_tags_llms_txt_description');

      if (\defined('MODULE_HEADER_TAGS_LLMS_TXT_STATUS')) {
        $this->sort_order = (int)MODULE_HEADER_TAGS_LLMS_TXT_SORT_ORDER;
        $this->enabled    = (MODULE_HEADER_TAGS_LLMS_TXT_STATUS === 'True');
      }
    }

    public function isEnabled(): bool
    {
      return $this->enabled;
    }

    public function getOutput(): string
    {
      if (!$this->enabled) {
        return '';
      }

      $languageId = (int)Registry::get('Language')->getId();

      if (\defined('MODULE_HEADER_TAGS_LLMS_TXT_CACHE') && MODULE_HEADER_TAGS_LLMS_TXT_CACHE == 'True') {
        $cacheFull  = new CacheApp('llms_full_'  . $languageId);
        $cacheLight = new CacheApp('llms_light_' . $languageId);

        $ttl = (string)self::CACHE_TTL_MINUTES;

        // Si les deux caches sont encore valides, rien à faire.
        if ($cacheFull->exists($ttl) && $cacheLight->exists($ttl)) {
          return '';
        }
      }

      // Régénération du contenu
      $contentFull  = $this->llmsTxt->generateFull();
      $contentLight = $this->llmsTxt->generate();

      if (\defined('MODULE_HEADER_TAGS_LLMS_TXT_CACHE') && MODULE_HEADER_TAGS_LLMS_TXT_CACHE == 'True') {
        // Persistance en cache fichier (save() prend ($data, $metadata) — pas de clé ni de TTL)
        $cacheFull->save($contentFull);
        $cacheLight->save($contentLight);
      }

      // Écriture sur disque
      $this->writeFile('llms-full.txt', $contentFull);
      $this->writeFile('llms.txt',      $contentLight);

      return '';
    }

    /**
     * Écrit $content dans le fichier $filename à la racine du shop.
     * Crée le fichier s'il n'existe pas encore (tant que le répertoire est accessible).
     */
    public function writeFile(string $filename, string $content): void
    {
      $path = CLICSHOPPING::getConfig('dir_root', 'Shop') . $filename;

      if (is_file($path)) {
        // Fichier existant : mise à jour
        if (file_put_contents($path, $content, LOCK_EX) === false) {
          error_log('[LlmsTxt] Impossible d\'écrire dans ' . $filename);
        }
        return;
      }

      $dir = \dirname($path);

      if (is_dir($dir) && is_writable($dir)) {
        if (file_put_contents($path, $content, LOCK_EX) === false) {
          error_log('[LlmsTxt] Impossible de créer ' . $filename);
        }
      } else {
        error_log('[LlmsTxt] ' . $filename . ' absent et répertoire non accessible en écriture — vérifier les permissions (chmod)');
      }
    }

    public function install(): void
    {
      $this->app->db->save('configuration', [
        'configuration_title'       => 'Activer Llms Txt',
        'configuration_key'         => 'MODULE_HEADER_TAGS_LLMS_TXT_STATUS',
        'configuration_value'       => 'True',
        'configuration_description' => 'Génération automatique des fichiers llms.txt et llms-full.txt.',
        'configuration_group_id'    => '6',
        'sort_order'                => '10',
        'set_function'              => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added'                => 'now()'
      ]);

      $this->app->db->save('configuration', [
        'configuration_title'       => 'Do you want to activate the cache',
        'configuration_key'         => 'MODULE_HEADER_TAGS_LLMS_TXT_CACHE',
        'configuration_value'       => 'True',
        'configuration_description' => 'For test remove the cache, for production activate it.',
        'configuration_group_id'    => '6',
        'sort_order'                => '20',
        'set_function'              => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added'                => 'now()'
      ]);

      $this->app->db->save('configuration', [
        'configuration_title'       => 'Ordre de tri',
        'configuration_key'         => 'MODULE_HEADER_TAGS_LLMS_TXT_SORT_ORDER',
        'configuration_value'       => '200',
        'configuration_description' => "Ordre d'exécution du module.",
        'configuration_group_id'    => '6',
        'sort_order'                => '200',
        'date_added'                => 'now()'
      ]);
    }

    public function keys(): array
    {
      return [
        'MODULE_HEADER_TAGS_LLMS_TXT_STATUS',
        'MODULE_HEADER_TAGS_LLMS_TXT_CACHE',
        'MODULE_HEADER_TAGS_LLMS_TXT_SORT_ORDER',
      ];
    }
  }