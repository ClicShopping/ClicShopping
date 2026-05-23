<?php
/**
 * AJAX Endpoint: Reset Cache
 * Handles cache reset requests from the Dashboard
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * @date 2025-11-17
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Cache as OMCache;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\AI\Infrastructure\Cache\TranslationCache;
use ClicShopping\AI\Infrastructure\Cache\ClassificationCache;

// Bootstrap
define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . '/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');

spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');
AdministratorAdmin::hasUserAccess();

// Set JSON header
header('Content-Type: application/json');

try {
  // Get request data
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);
  
  if (!$data || !isset($data['cache_types']) || !is_array($data['cache_types'])) {
    echo json_encode([
      'success' => false,
      'message' => 'Invalid request data'
    ]);
    exit;
  }
  
  $cacheTypes = $data['cache_types'];
  $results = [];
  $errors = [];
  
  // ============================================================================
  // 1. Reset Translation Cache
  // ============================================================================
  if (in_array('translations', $cacheTypes)) {
    try {
      $translationCache = new TranslationCache();
      
      // Count files before
      $cacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Translation/';
      $filesBefore = 0;
      
      if (is_dir($cacheDir)) {
        $iterator = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
          if ($file->isFile()) {
            $filesBefore++;
          }
        }
      }
      
      // Flush translation cache
      $translationCache->clearCache();
      
      // Count files after
      $filesAfter = 0;
      if (is_dir($cacheDir)) {
        $iterator = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
          if ($file->isFile()) {
            $filesAfter++;
          }
        }
      }
      
      $results['translations'] = $filesBefore - $filesAfter;
      
      error_log("Cache Reset: Translation cache flushed - {$results['translations']} files deleted");
      
    } catch (Exception $e) {
      $errors[] = "Translation cache: " . $e->getMessage();
      error_log("Cache Reset Error (translations): " . $e->getMessage());
    }
  }
  
  // ============================================================================
  // 3. Reset Classification Cache
  // ============================================================================
  if (in_array('classification', $cacheTypes)) {
    try {
      $classificationCache = new ClassificationCache();
      
      // Get stats before
      $statsBefore = $classificationCache->getStatistics();
      $filesBefore = $statsBefore['file_count'] ?? 0;
      
      // Flush classification cache
      $classificationCache->clearCache();
      
      // Get stats after
      $statsAfter = $classificationCache->getStatistics();
      $filesAfter = $statsAfter['file_count'] ?? 0;
      
      $results['classification'] = $filesBefore - $filesAfter;
      
      error_log("Cache Reset: Classification cache flushed - {$results['classification']} files deleted");
      
    } catch (Exception $e) {
      $errors[] = "Classification cache: " . $e->getMessage();
      error_log("Cache Reset Error (classification): " . $e->getMessage());
    }
  }
  
  // ============================================================================
  // 3. Reset Database Query Cache
  // ============================================================================
  if (in_array('database', $cacheTypes)) {
    try {
      $db = Registry::get('Db');
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      // Count entries before
      $result = $db->query("SELECT COUNT(*) as count FROM {$prefix}rag_query_cache");
      $entriesBefore = $result->fetch()['count'] ?? 0;
      
      // Truncate table
      $db->query("TRUNCATE TABLE {$prefix}rag_query_cache");
      
      // Count entries after
      $result = $db->query("SELECT COUNT(*) as count FROM {$prefix}rag_query_cache");
      $entriesAfter = $result->fetch()['count'] ?? 0;
      
      $results['database'] = $entriesBefore - $entriesAfter;
      
      error_log("Cache Reset: Database cache flushed - {$results['database']} entries deleted");
      
    } catch (Exception $e) {
      $errors[] = "Database cache: " . $e->getMessage();
      error_log("Cache Reset Error (database): " . $e->getMessage());
    }
  }
  
  // ============================================================================
  // 4. Reset Schema Query Cache (TASK 5 - ITEM 1)
  // ============================================================================
  if (in_array('schema', $cacheTypes)) {
    try {
      // Count schema cache files before (new location)
      $schemaCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/SchemaQuery/';
      $filesBefore = 0;
      
      if (is_dir($schemaCacheDir)) {
        $files = glob($schemaCacheDir . '*.cache');
        $filesBefore = count($files);
        
        // Delete schema cache files
        foreach ($files as $file) {
          @unlink($file);
        }
      }
      
      // Also check old location (root cache directory with Rag_SchemaQuery_ prefix)
      $oldCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/';
      if (is_dir($oldCacheDir)) {
        $oldFiles = glob($oldCacheDir . 'Rag_SchemaQuery_*.cache');
        $filesBefore += count($oldFiles);
        
        // Delete old schema cache files
        foreach ($oldFiles as $file) {
          @unlink($file);
        }
      }
      
      // Count files after
      $filesAfter = 0;
      if (is_dir($schemaCacheDir)) {
        $files = glob($schemaCacheDir . '*.cache');
        $filesAfter = count($files);
      }
      if (is_dir($oldCacheDir)) {
        $oldFiles = glob($oldCacheDir . 'Rag_SchemaQuery_*.cache');
        $filesAfter += count($oldFiles);
      }
      
      $results['schema'] = $filesBefore - $filesAfter;
      
      error_log("Cache Reset: Schema query cache flushed - {$results['schema']} files deleted");
      
    } catch (Exception $e) {
      $errors[] = "Schema cache: " . $e->getMessage();
      error_log("Cache Reset Error (schema): " . $e->getMessage());
    }
  }
  
  // ============================================================================
  // 5. Reset Intent Classification Cache (TASK 5.1.7.6)
  // ============================================================================
  if (in_array('intent', $cacheTypes)) {
    try {
      // Count intent cache files before (new location - all files)
      $intentCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Intent/';
      $filesBefore = 0;
      
      if (is_dir($intentCacheDir)) {
        $files = glob($intentCacheDir . '*.cache');
        $filesBefore = count($files);
        
        // Delete all intent cache files
        foreach ($files as $file) {
          @unlink($file);
        }
      }
      
      // Also check old location (root cache directory with Rag_Intent_ prefix)
      $oldCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/';
      if (is_dir($oldCacheDir)) {
        $oldFiles = glob($oldCacheDir . 'Rag_Intent_*.cache');
        $filesBefore += count($oldFiles);
        
        // Delete old intent cache files
        foreach ($oldFiles as $file) {
          @unlink($file);
        }
      }
      
      // Count files after
      $filesAfter = 0;
      if (is_dir($intentCacheDir)) {
        $files = glob($intentCacheDir . '*.cache');
        $filesAfter = count($files);
      }
      if (is_dir($oldCacheDir)) {
        $oldFiles = glob($oldCacheDir . 'Rag_Intent_*.cache');
        $filesAfter += count($oldFiles);
      }
      
      $results['intent'] = $filesBefore - $filesAfter;
      
      error_log("Cache Reset: Intent classification cache flushed - {$results['intent']} files deleted");
      
    } catch (Exception $e) {
      $errors[] = "Intent cache: " . $e->getMessage();
      error_log("Cache Reset Error (intent): " . $e->getMessage());
    }
  }
  
  // ============================================================================
  // 6. Reset Ambiguity Cache (TASK 4 - Cache Migration)
  // ============================================================================
  if (in_array('ambiguity', $cacheTypes)) {
    try {
      // Count ambiguity cache files before (new location - all files)
      $ambiguityCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Ambiguity/';
      $filesBefore = 0;
      
      if (is_dir($ambiguityCacheDir)) {
        $files = glob($ambiguityCacheDir . '*.cache');
        $filesBefore = count($files);
        
        // Delete all ambiguity cache files
        foreach ($files as $file) {
          @unlink($file);
        }
      }
      
      // Also check old location (root cache directory with Rag_Ambiguity_ prefix)
      $oldCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/';
      if (is_dir($oldCacheDir)) {
        $oldFiles = glob($oldCacheDir . 'Rag_Ambiguity_*.cache');
        $filesBefore += count($oldFiles);
        
        // Delete old ambiguity cache files
        foreach ($oldFiles as $file) {
          @unlink($file);
        }
      }
      
      // Also check very old location (ambiguity_optimizer directory)
      $veryOldCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/ambiguity_optimizer/';
      if (is_dir($veryOldCacheDir)) {
        $veryOldFiles = glob($veryOldCacheDir . '*.cache');
        $filesBefore += count($veryOldFiles);
        
        // Delete very old ambiguity cache files
        foreach ($veryOldFiles as $file) {
          @unlink($file);
        }
      }
      
      // Count files after
      $filesAfter = 0;
      if (is_dir($ambiguityCacheDir)) {
        $files = glob($ambiguityCacheDir . '*.cache');
        $filesAfter = count($files);
      }
      if (is_dir($oldCacheDir)) {
        $oldFiles = glob($oldCacheDir . 'Rag_Ambiguity_*.cache');
        $filesAfter += count($oldFiles);
      }
      if (is_dir($veryOldCacheDir)) {
        $veryOldFiles = glob($veryOldCacheDir . '*.cache');
        $filesAfter += count($veryOldFiles);
      }
      
      $results['ambiguity'] = $filesBefore - $filesAfter;
      
      error_log("Cache Reset: Ambiguity cache flushed - {$results['ambiguity']} files deleted");
      
    } catch (Exception $e) {
      $errors[] = "Ambiguity cache: " . $e->getMessage();
      error_log("Cache Reset Error (ambiguity): " . $e->getMessage());
    }
  }
  
  // ============================================================================
  // 7. Reset Translation Ambiguity Cache
  // ============================================================================
  if (in_array('translation_ambiguity', $cacheTypes)) {
    try {
      // Count translation ambiguity cache files before (new location)
      $translationAmbiguityCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Translation/';
      $filesBefore = 0;
      
      if (is_dir($translationAmbiguityCacheDir)) {
        $files = glob($translationAmbiguityCacheDir . 'translation_ambiguity_*.cache');
        $filesBefore = count($files);
        
        // Delete translation ambiguity cache files
        foreach ($files as $file) {
          @unlink($file);
        }
      }
      
      // Also check old location (root cache directory)
      $oldCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/';
      if (is_dir($oldCacheDir)) {
        $oldFiles = glob($oldCacheDir . 'translation_ambiguity_*.cache');
        $filesBefore += count($oldFiles);
        
        // Delete old translation ambiguity cache files
        foreach ($oldFiles as $file) {
          @unlink($file);
        }
      }
      
      // Count files after
      $filesAfter = 0;
      if (is_dir($translationAmbiguityCacheDir)) {
        $files = glob($translationAmbiguityCacheDir . 'translation_ambiguity_*.cache');
        $filesAfter = count($files);
      }
      if (is_dir($oldCacheDir)) {
        $oldFiles = glob($oldCacheDir . 'translation_ambiguity_*.cache');
        $filesAfter += count($oldFiles);
      }
      
      $results['translation_ambiguity'] = $filesBefore - $filesAfter;
      
      error_log("Cache Reset: Translation ambiguity cache flushed - {$results['translation_ambiguity']} files deleted");
      
    } catch (Exception $e) {
      $errors[] = "Translation ambiguity cache: " . $e->getMessage();
      error_log("Cache Reset Error (translation_ambiguity): " . $e->getMessage());
    }
  }
  
  // ============================================================================
  // 8. Reset Context Cache
  // ============================================================================
  if (in_array('context', $cacheTypes)) {
    try {
      // Count context cache files before (new location)
      $contextCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Context/';
      $filesBefore = 0;
      
      if (is_dir($contextCacheDir)) {
        $files = glob($contextCacheDir . '*.cache');
        $filesBefore = count($files);
        
        // Delete all context cache files
        foreach ($files as $file) {
          @unlink($file);
        }
      }
      
      // Also check old location (root cache directory with Rag_Context_ or context_ prefix)
      $oldCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/';
      if (is_dir($oldCacheDir)) {
        $oldFiles = array_merge(
          glob($oldCacheDir . 'Rag_Context_*.cache'),
          glob($oldCacheDir . 'context_*.cache')
        );
        $filesBefore += count($oldFiles);
        
        // Delete old context cache files
        foreach ($oldFiles as $file) {
          @unlink($file);
        }
      }
      
      // Count files after
      $filesAfter = 0;
      if (is_dir($contextCacheDir)) {
        $files = glob($contextCacheDir . '*.cache');
        $filesAfter = count($files);
      }
      if (is_dir($oldCacheDir)) {
        $oldFiles = array_merge(
          glob($oldCacheDir . 'Rag_Context_*.cache'),
          glob($oldCacheDir . 'context_*.cache')
        );
        $filesAfter += count($oldFiles);
      }
      
      $results['context'] = $filesBefore - $filesAfter;
      
      error_log("Cache Reset: Context cache flushed - {$results['context']} files deleted");
      
    } catch (Exception $e) {
      $errors[] = "Context cache: " . $e->getMessage();
      error_log("Cache Reset Error (context): " . $e->getMessage());
    }
  }

  // ============================================================================
  // 8.5. Reset Memory Cache (file-based)
  // ============================================================================
  if (in_array('memory', $cacheTypes)) {
    try {
      $memoryCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Memory/';
      $filesBefore = 0;

      if (is_dir($memoryCacheDir)) {
        $files = glob($memoryCacheDir . '*.json');
        $filesBefore = count($files);

        foreach ($files as $file) {
          @unlink($file);
        }
      }

      $filesAfter = is_dir($memoryCacheDir) ? count(glob($memoryCacheDir . '*.json')) : 0;
      $results['memory'] = $filesBefore - $filesAfter;

      error_log("Cache Reset: Memory cache flushed - {$results['memory']} files deleted");

    } catch (Exception $e) {
      $errors[] = "Memory cache: " . $e->getMessage();
      error_log("Cache Reset Error (memory): " . $e->getMessage());
    }
  }
  
  // ============================================================================
  // 9. Reset Embedding Cache
  // ============================================================================
  if (in_array('embedding', $cacheTypes)) {
    try {
      // Count embedding cache files before (new location)
      $embeddingCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Embedding/';
      $filesBefore = 0;
      
      if (is_dir($embeddingCacheDir)) {
        $files = glob($embeddingCacheDir . '*.cache');
        $filesBefore = count($files);
        
        // Delete all embedding cache files
        foreach ($files as $file) {
          @unlink($file);
        }
      }
      
      // Also check old location (root cache directory with Rag_Embedding_ or embedding_ prefix)
      $oldCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/';
      if (is_dir($oldCacheDir)) {
        $oldFiles = array_merge(
          glob($oldCacheDir . 'Rag_Embedding_*.cache'),
          glob($oldCacheDir . 'embedding_*.cache')
        );
        // Exclude embedding_search files
        $oldFiles = array_filter($oldFiles, function($file) {
          return strpos(basename($file), 'embedding_search_') !== 0;
        });
        $filesBefore += count($oldFiles);
        
        // Delete old embedding cache files
        foreach ($oldFiles as $file) {
          @unlink($file);
        }
      }
      
      // Count files after
      $filesAfter = 0;
      if (is_dir($embeddingCacheDir)) {
        $files = glob($embeddingCacheDir . '*.cache');
        $filesAfter = count($files);
      }
      if (is_dir($oldCacheDir)) {
        $oldFiles = array_merge(
          glob($oldCacheDir . 'Rag_Embedding_*.cache'),
          glob($oldCacheDir . 'embedding_*.cache')
        );
        $oldFiles = array_filter($oldFiles, function($file) {
          return strpos(basename($file), 'embedding_search_') !== 0;
        });
        $filesAfter += count($oldFiles);
      }
      
      $results['embedding'] = $filesBefore - $filesAfter;
      
      error_log("Cache Reset: Embedding cache flushed - {$results['embedding']} files deleted");
      
    } catch (Exception $e) {
      $errors[] = "Embedding cache: " . $e->getMessage();
      error_log("Cache Reset Error (embedding): " . $e->getMessage());
    }
  }
  
  // ============================================================================
  // 10. Reset EmbeddingSearch Cache
  // ============================================================================
  if (in_array('embedding_search', $cacheTypes)) {
    try {
      // Count embedding search cache files before (new location)
      $embeddingSearchCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/EmbeddingSearch/';
      $filesBefore = 0;
      
      if (is_dir($embeddingSearchCacheDir)) {
        $files = glob($embeddingSearchCacheDir . '*.cache');
        $filesBefore = count($files);
        
        // Delete all embedding search cache files
        foreach ($files as $file) {
          @unlink($file);
        }
      }
      
      // Also check old locations
      $oldCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/';
      if (is_dir($oldCacheDir)) {
        $oldFiles = array_merge(
          glob($oldCacheDir . 'Rag_EmbeddingSearch_*.cache'),
          glob($oldCacheDir . 'EmbeddingSearch_*.cache'),
          glob($oldCacheDir . 'embedding_search_*.cache')
        );
        $filesBefore += count($oldFiles);
        
        // Delete old embedding search cache files
        foreach ($oldFiles as $file) {
          @unlink($file);
        }
      }
      
      // Also check old embedding_search directory
      $oldEmbeddingSearchDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/embedding_search/';
      if (is_dir($oldEmbeddingSearchDir)) {
        $oldDirFiles = glob($oldEmbeddingSearchDir . '*.cache');
        $filesBefore += count($oldDirFiles);
        
        // Delete files from old directory
        foreach ($oldDirFiles as $file) {
          @unlink($file);
        }
      }
      
      // Count files after
      $filesAfter = 0;
      if (is_dir($embeddingSearchCacheDir)) {
        $files = glob($embeddingSearchCacheDir . '*.cache');
        $filesAfter = count($files);
      }
      if (is_dir($oldCacheDir)) {
        $oldFiles = array_merge(
          glob($oldCacheDir . 'Rag_EmbeddingSearch_*.cache'),
          glob($oldCacheDir . 'EmbeddingSearch_*.cache'),
          glob($oldCacheDir . 'embedding_search_*.cache')
        );
        $filesAfter += count($oldFiles);
      }
      if (is_dir($oldEmbeddingSearchDir)) {
        $oldDirFiles = glob($oldEmbeddingSearchDir . '*.cache');
        $filesAfter += count($oldDirFiles);
      }
      
      $results['embedding_search'] = $filesBefore - $filesAfter;
      
      error_log("Cache Reset: EmbeddingSearch cache flushed - {$results['embedding_search']} files deleted");
      
    } catch (Exception $e) {
      $errors[] = "EmbeddingSearch cache: " . $e->getMessage();
      error_log("Cache Reset Error (embedding_search): " . $e->getMessage());
    }
  }
  
  // ============================================================================
  // 11. Reset Hybrid Query Cache (TASK 8: Multi-temporal query caching)
  // ============================================================================
  if (in_array('hybrid', $cacheTypes)) {
    try {
      // Count hybrid cache files before
      $hybridCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Hybrid/';
      $filesBefore = 0;
      
      if (is_dir($hybridCacheDir)) {
        $files = glob($hybridCacheDir . '*.cache');
        $filesBefore = count($files);
        
        // Delete all hybrid cache files
        foreach ($files as $file) {
          @unlink($file);
        }
      }
      
      // Count files after
      $filesAfter = 0;
      if (is_dir($hybridCacheDir)) {
        $files = glob($hybridCacheDir . '*.cache');
        $filesAfter = count($files);
      }
      
      $results['hybrid'] = $filesBefore - $filesAfter;
      
      error_log("Cache Reset: Hybrid query cache flushed - {$results['hybrid']} files deleted");
      
    } catch (Exception $e) {
      $errors[] = "Hybrid cache: " . $e->getMessage();
      error_log("Cache Reset Error (hybrid): " . $e->getMessage());
    }

    // ============================================================================
    // 12. Reset Semantic Query Cache (TASK 8: Multi-temporal query caching)
    // ============================================================================
    if (in_array('semantic', $cacheTypes)) {
      try {
        // Count hybrid cache files before
        $semanticCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Semantic/';
        $filesBefore = 0;

        if (is_dir($semanticCacheDir)) {
          $files = glob($semanticCacheDir . '*.cache');
          $filesBefore = count($files);

          // Delete all hybrid cache files
          foreach ($files as $file) {
            @unlink($file);
          }
        }

        // Count files after
        $filesAfter = 0;
        if (is_dir($semanticCacheDir)) {
          $files = glob($semanticCacheDir . '*.cache');
          $filesAfter = count($files);
        }

        // Also clear old semantic cache files from root cache directory
        $oldCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/';
        if (is_dir($oldCacheDir)) {
          $oldFiles = glob($oldCacheDir . 'semantic_*.cache');
          $filesBefore += count($oldFiles);
          foreach ($oldFiles as $file) {
            @unlink($file);
          }

          $filesAfter += count(glob($oldCacheDir . 'semantic_*.cache'));
        }

        $results['semantic'] = $filesBefore - $filesAfter;

        error_log("Cache Reset: Semantic query cache flushed - {$results['semantic']} files deleted");

      } catch (Exception $e) {
        $errors[] = "Semantic cache: " . $e->getMessage();
        error_log("Cache Reset Error (semantic): " . $e->getMessage());
      }
    }

    // ============================================================================
    // 13. Reset Embeddings Cache (PHASE 2 - NewVector cache)
    // ============================================================================
    if (in_array('embeddings', $cacheTypes)) {
      try {
        $embeddingsCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Embeddings/';
        $filesBefore = 0;

        if (is_dir($embeddingsCacheDir)) {
          $files = glob($embeddingsCacheDir . '*.cache');
          $filesBefore = count($files);
          foreach ($files as $file) {
            @unlink($file);
          }
        }

        // Also clear legacy embedding cache directory
        $legacyEmbeddingDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Embedding/';
        if (is_dir($legacyEmbeddingDir)) {
          $legacyFiles = glob($legacyEmbeddingDir . '*.cache');
          $filesBefore += count($legacyFiles);
          foreach ($legacyFiles as $file) {
            @unlink($file);
          }
        }

        // Also clear old embedding cache files from root cache directory (excluding embedding_search)
        $oldCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/';
        if (is_dir($oldCacheDir)) {
          $oldFiles = array_merge(
            glob($oldCacheDir . 'Rag_Embedding_*.cache'),
            glob($oldCacheDir . 'embedding_*.cache')
          );
          $oldFiles = array_filter($oldFiles, function($file) {
            return strpos(basename($file), 'embedding_search_') !== 0;
          });
          $filesBefore += count($oldFiles);
          foreach ($oldFiles as $file) {
            @unlink($file);
          }
        }

        $filesAfter = is_dir($embeddingsCacheDir) ? count(glob($embeddingsCacheDir . '*.cache')) : 0;
        $filesAfter += is_dir($legacyEmbeddingDir) ? count(glob($legacyEmbeddingDir . '*.cache')) : 0;
        if (is_dir($oldCacheDir)) {
          $oldFilesAfter = array_merge(
            glob($oldCacheDir . 'Rag_Embedding_*.cache'),
            glob($oldCacheDir . 'embedding_*.cache')
          );
          $oldFilesAfter = array_filter($oldFilesAfter, function($file) {
            return strpos(basename($file), 'embedding_search_') !== 0;
          });
          $filesAfter += count($oldFilesAfter);
        }

        $results['embeddings'] = $filesBefore - $filesAfter;

        error_log("Cache Reset: Embeddings cache flushed - {$results['embeddings']} files deleted");
      } catch (Exception $e) {
        $errors[] = "Embeddings cache: " . $e->getMessage();
      }
    }

    // ============================================================================
    // 14. Reset SQL Query Cache (PHASE 4)
    // ============================================================================
    if (in_array('sql', $cacheTypes)) {
      try {
        $sqlCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/SQL/';
        $filesBefore = 0;

        if (is_dir($sqlCacheDir)) {
          $files = glob($sqlCacheDir . '*.cache');
          $filesBefore = count($files);
          foreach ($files as $file) {
            @unlink($file);
          }
        }

        $filesAfter = is_dir($sqlCacheDir) ? count(glob($sqlCacheDir . '*.cache')) : 0;
        $results['sql'] = $filesBefore - $filesAfter;

        error_log("Cache Reset: SQL query cache flushed - {$results['sql']} files deleted");
      } catch (Exception $e) {
        $errors[] = "SQL cache: " . $e->getMessage();
      }
    }

    // ============================================================================
    // 15. Reset Security Cache
    // ============================================================================
    if (in_array('security', $cacheTypes)) {
      try {
        $securityCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Security/';
        $filesBefore = 0;

        if (is_dir($securityCacheDir)) {
          $files = glob($securityCacheDir . '*.cache');
          $filesBefore = count($files);
          foreach ($files as $file) {
            @unlink($file);
          }
        }

        $filesAfter = is_dir($securityCacheDir) ? count(glob($securityCacheDir . '*.cache')) : 0;
        $results['security'] = $filesBefore - $filesAfter;

        error_log("Cache Reset: Security cache flushed - {$results['security']} files deleted");
      } catch (Exception $e) {
        $errors[] = "Security cache: " . $e->getMessage();
      }
    }

    // ============================================================================
    // 16. Reset Reputation Cache
    // ============================================================================
    if (in_array('reputation', $cacheTypes)) {
      try {
        $reputationCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Reputation/';
        $filesBefore = 0;

        if (is_dir($reputationCacheDir)) {
          $files = glob($reputationCacheDir . '*.cache');
          $filesBefore = count($files);
          foreach ($files as $file) {
            @unlink($file);
          }
        }

        $filesAfter = is_dir($reputationCacheDir) ? count(glob($reputationCacheDir . '*.cache')) : 0;
        $results['reputation'] = $filesBefore - $filesAfter;

        error_log("Cache Reset: Reputation cache flushed - {$results['reputation']} files deleted");
      } catch (Exception $e) {
        $errors[] = "Reputation cache: " . $e->getMessage();
      }
    }

    // ============================================================================
    // 17. Reset Config Cache
    // ============================================================================
    if (in_array('config', $cacheTypes)) {
      try {
        $configCacheDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/Config/';
        $filesBefore = 0;

        if (is_dir($configCacheDir)) {
          $files = glob($configCacheDir . '*.cache');
          $filesBefore = count($files);
          foreach ($files as $file) {
            @unlink($file);
          }
        }

        $filesAfter = is_dir($configCacheDir) ? count(glob($configCacheDir . '*.cache')) : 0;
        $results['config'] = $filesBefore - $filesAfter;

        error_log("Cache Reset: Config cache flushed - {$results['config']} files deleted");
      } catch (Exception $e) {
        $errors[] = "Config cache: " . $e->getMessage();
      }
    }
  }
  
  // ============================================================================
  // Return Response
  // ============================================================================
  if (empty($errors)) {
    echo json_encode([
      'success' => true,
      'message' => 'Cache reset successfully',
      'details' => $results
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'Some caches could not be reset',
      'details' => $results,
      'errors' => $errors
    ]);
  }
  
} catch (Exception $e) {
  error_log("Cache Reset Fatal Error: " . $e->getMessage());
  echo json_encode([
    'success' => false,
    'message' => 'Fatal error: ' . $e->getMessage()
  ]);
}
