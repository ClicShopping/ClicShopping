<?php
/**
 * Complete Cache Clearing Script - COMPREHENSIVE VERSION
 * 
 * Clears ALL caches for Pure LLM Mode:
 * 1. Intent cache files (filesystem)
 * 2. RAG file cache
 * 3. Conversation memory (database)
 * 4. RAG interactions (database)
 * 5. Database query cache
 * 6. Translation cache
 * 7. Classification cache
 * 8. Symfony cache
 * 9. OpCache (PHP bytecode)
 * 10. Session cache
 * 
 * Usage: php clear_all_caches_complete.php
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', __DIR__ . '/Core/ClicShopping/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('Shop');

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "🗑️  COMPLETE CACHE CLEARING - Pure LLM Mode\n";
echo str_repeat("=", 80) . "\n\n";

$totalCleared = 0;
$errors = [];

/**
 * Recursively delete files under a directory.
 * Keeps directories and skips excluded basenames.
 */
function deleteFilesRecursive(string $dir, array $exclude = []): int
{
  if (!is_dir($dir)) {
    return 0;
  }

  $deleted = 0;
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );

  foreach ($it as $file) {
    if ($file->isFile()) {
      $base = $file->getBasename();
      if (in_array($base, $exclude, true)) {
        continue;
      }
      if (@unlink($file->getPathname())) {
        $deleted++;
      }
    }
  }

  return $deleted;
}

// ========================================
// 1. CLEAR INTENT CACHE FILES (CRITICAL)
// ========================================
echo "1. Clearing Intent cache files (CRITICAL for Pure LLM)...\n";

// Clear new intent cache location (Rag/Intent/)
$intentCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Intent/';
if (is_dir($intentCacheDir)) {
  $intentFiles = glob($intentCacheDir . 'intent_*.cache');
  if ($intentFiles) {
    $cleared = 0;
    foreach ($intentFiles as $file) {
      if (unlink($file)) {
        $cleared++;
      } else {
        $errors[] = "Failed to delete intent cache: " . basename($file);
      }
    }
    echo "   ✅ Cleared {$cleared} intent cache files from Rag/Intent/\n";
    $totalCleared += $cleared;
  } else {
    echo "   ℹ️  No intent cache files found in Rag/Intent/\n";
  }
} else {
  echo "   ℹ️  Intent cache directory not found (Rag/Intent/)\n";
}

// Clear old intent cache location (root cache directory)
$oldIntentCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/';
$oldIntentFiles = glob($oldIntentCacheDir . 'intent_*.cache');

if ($oldIntentFiles) {
  $cleared = 0;
  foreach ($oldIntentFiles as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old intent cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} old intent cache files from root cache directory\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  No old intent cache files found in root directory\n";
}

// ========================================
// 2. CLEAR RAG FILE CACHE
// ========================================
echo "\n2. Clearing RAG file cache (recursive)...\n";
$ragCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/';
if (is_dir($ragCacheDir)) {
  $cleared = deleteFilesRecursive($ragCacheDir, ['.htaccess', 'index.php']);
  echo "   ✅ Cleared {$cleared} files from RAG cache (recursive)\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  RAG cache directory not found\n";
}

// ========================================
// 3. CLEAR CONVERSATION MEMORY (DATABASE)
// ========================================
echo "\n3. Clearing conversation memory from database...\n";
try {
  $db = Registry::get('Db');
  $prefix = CLICSHOPPING::getConfig('db_table_prefix');
  
  // Queries to clear (revenue-related queries that were misclassified)
  $queriesToClear = [
    'Revenue of the month',
    'Revenue this month',
    'Monthly revenue',
    'Sales of the month',
    'Chiffre d\'affaires du mois',
    'Umsatz des Monats',
  ];
  
  $memoryDeleted = 0;
  foreach ($queriesToClear as $query) {
    try {
      $sql = "DELETE FROM {$prefix}rag_conversation_memory_embedding 
              WHERE user_message LIKE :query";
      
      $stmt = $db->prepare($sql);
      $stmt->execute([':query' => '%' . $query . '%']);
      
      $rowsDeleted = $stmt->rowCount();
      if ($rowsDeleted > 0) {
        $memoryDeleted += $rowsDeleted;
      }
    } catch (\Exception $e) {
      $errors[] = "Failed to delete memory for: \"{$query}\" - " . $e->getMessage();
    }
  }
  
  if ($memoryDeleted > 0) {
    echo "   ✅ Cleared {$memoryDeleted} conversation memory entries\n";
    $totalCleared += $memoryDeleted;
  } else {
    echo "   ℹ️  No conversation memory entries found\n";
  }
  
} catch (Exception $e) {
  echo "   ❌ Error: {$e->getMessage()}\n";
  $errors[] = "Conversation memory clear failed: {$e->getMessage()}";
}

// ========================================
// 4. CLEAR RAG INTERACTIONS (DATABASE)
// ========================================
echo "\n4. Clearing RAG interactions from database...\n";
try {
  $db = Registry::get('Db');
  $prefix = CLICSHOPPING::getConfig('db_table_prefix');
  
  $interactionsDeleted = 0;
  foreach ($queriesToClear as $query) {
    try {
      $sql = "DELETE FROM {$prefix}rag_interactions 
              WHERE question LIKE :query";
      
      $stmt = $db->prepare($sql);
      $stmt->execute([':query' => '%' . $query . '%']);
      
      $rowsDeleted = $stmt->rowCount();
      if ($rowsDeleted > 0) {
        $interactionsDeleted += $rowsDeleted;
      }
    } catch (\Exception $e) {
      $errors[] = "Failed to delete interaction for: \"{$query}\" - " . $e->getMessage();
    }
  }
  
  if ($interactionsDeleted > 0) {
    echo "   ✅ Cleared {$interactionsDeleted} RAG interactions\n";
    $totalCleared += $interactionsDeleted;
  } else {
    echo "   ℹ️  No RAG interactions found\n";
  }
  
} catch (Exception $e) {
  echo "   ❌ Error: {$e->getMessage()}\n";
  $errors[] = "RAG interactions clear failed: {$e->getMessage()}";
}

// ========================================
// 5. CLEAR DATABASE QUERY CACHE
// ========================================
// ⚠️ THE TRAP OF THIS REPOSITORY — read before removing anything here.
// rag_query_cache is keyed on the RAW user question, so a single stale row replays a whole
// answer built with the previous prompt, schema window or schema store, and no file cache
// shows it. It bit us again on 2026-08-10: the row held the very question being measured.
// A count is printed per table on purpose: "cleared" without a number cannot be trusted.
echo "\n5. Clearing database query caches (rag_query_cache, rag_calculator_cache)...\n";
try {
  $db = Registry::get('Db');
  $prefix = CLICSHOPPING::getConfig('db_table_prefix');

  // A full DELETE covers every cache_key: translation, classification, ambiguity, intent…
  // Listing them one by one after it deleted nothing and only pretended to work.
  foreach (['rag_query_cache', 'rag_calculator_cache'] as $cacheTable) {
    try {
      $stmt = $db->prepare("DELETE FROM {$prefix}{$cacheTable}");
      $stmt->execute();
      $rows = $stmt->rowCount();
      echo "   ✅ {$cacheTable}: {$rows} row(s) deleted\n";
      $totalCleared += $rows;
    } catch (\Exception $e) {
      echo "   ❌ {$cacheTable}: {$e->getMessage()}\n";
      $errors[] = "{$cacheTable} clear failed: {$e->getMessage()}";
    }
  }

  // NOT a cache: rag_schema_embedding is DERIVED data and must be REBUILT, never emptied.
  // Deleting it silently degrades retrieval; the operator gate is the way back.
  try {
    $Q = $db->query("SELECT COUNT(*) AS c FROM {$prefix}rag_schema_embedding");
    $stored = $Q->fetch()['c'] ?? 0;
    echo "   ℹ️  rag_schema_embedding: {$stored} row(s) LEFT IN PLACE (derived, not a cache).\n";
    echo "       After changing a table comment or the embedded-text rules, rebuild it:\n";
    echo "       php unit_test/2026_07_26/schema_embeddings_sync.php --sync\n";
  } catch (\Exception $e) {
    echo "   ⚠️  rag_schema_embedding unreadable: {$e->getMessage()}\n";
  }

} catch (Exception $e) {
  echo "   ❌ Error: {$e->getMessage()}\n";
  $errors[] = "Database cache clear failed: {$e->getMessage()}";
}

// ========================================
// 6. CLEAR TRANSLATION CACHE (FILE-BASED)
// ========================================
echo "\n6. Clearing translation cache files...\n";
$translationCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Translation/';
if (is_dir($translationCacheDir)) {
  $files = glob($translationCacheDir . '*.json');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete translation cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} translation cache files\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Translation cache directory not found\n";
}

// ========================================
// 7. CLEAR CLASSIFICATION CACHE (FILE-BASED) - NEW
// ========================================
echo "\n7. Clearing classification cache files...\n";
$classificationCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Classification/';
if (is_dir($classificationCacheDir)) {
  $files = glob($classificationCacheDir . '*.json');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete classification cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} classification cache files\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Classification cache directory not found\n";
}

// ========================================
// 7.5. CLEAR MEMORY STORAGE CACHE (FILE-BASED) - TASK 5.1.7.4
// ========================================
echo "\n7.5. Clearing memory storage cache files...\n";
$memoryCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Memory/';
if (is_dir($memoryCacheDir)) {
  $files = glob($memoryCacheDir . '*.json');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete memory storage cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} memory storage cache files\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Memory storage cache directory not found\n";
}

// ========================================
// 7.6. CLEAR SCHEMA QUERY CACHE (FILE-BASED) - TASK 5 - ITEM 1
// ========================================
echo "\n7.6. Clearing schema query cache files...\n";
$schemaCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/SchemaQuery/';
if (is_dir($schemaCacheDir)) {
  $files = glob($schemaCacheDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete schema query cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} schema query cache files from Rag/SchemaQuery/\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Schema cache directory not found\n";
}

// Also clear old schema cache files from root cache directory
$oldSchemaCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/';
$oldSchemaFiles = glob($oldSchemaCacheDir . 'Rag_SchemaQuery_*.cache');
if ($oldSchemaFiles) {
  $cleared = 0;
  foreach ($oldSchemaFiles as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old schema cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} old schema cache files from root cache directory\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  No old schema cache files found in root directory\n";
}

// ========================================
// 7.7. CLEAR AMBIGUITY CACHE (FILE-BASED) - NEW LOCATION
// ========================================
echo "\n7.7. Clearing ambiguity cache files...\n";
$ambiguityCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Ambiguity/';
if (is_dir($ambiguityCacheDir)) {
  $files = glob($ambiguityCacheDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete ambiguity cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} ambiguity cache files from Rag/Ambiguity/\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Ambiguity cache directory not found (Rag/Ambiguity/)\n";
}

// Clear old ambiguity cache location (root cache directory with Rag/ambiguity_optimizer prefix)
$generalCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/';
$oldAmbiguityFiles = glob($generalCacheDir . 'Rag_Ambiguity_*.cache');
if ($oldAmbiguityFiles) {
  $cleared = 0;
  foreach ($oldAmbiguityFiles as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old ambiguity cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} old ambiguity cache files from root cache directory\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  No old ambiguity cache files found in root directory\n";
}

// Clear old ambiguity cache subdirectory location
$oldAmbiguityDir = __DIR__ . '/Core/ClicShopping/Work/Cache/ambiguity_optimizer/';
if (is_dir($oldAmbiguityDir)) {
  $files = glob($oldAmbiguityDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old ambiguity cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} old ambiguity cache files from ambiguity_optimizer/ subdirectory\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  No old ambiguity_optimizer/ subdirectory found\n";
}

// ========================================
// 7.8. CLEAR TRANSLATION AMBIGUITY CACHE (FILE-BASED)
// ========================================
echo "\n7.8. Clearing translation ambiguity cache files...\n";
$translationAmbiguityCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Translation/';
if (is_dir($translationAmbiguityCacheDir)) {
  $files = glob($translationAmbiguityCacheDir . 'translation_ambiguity_*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete translation ambiguity cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} translation ambiguity cache files from Rag/Translation/\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Translation ambiguity cache directory not found (Rag/Translation/)\n";
}

// Also clear old translation ambiguity cache files from root cache directory
$oldTranslationAmbiguityFiles = glob($generalCacheDir . 'translation_ambiguity_*.cache');
if ($oldTranslationAmbiguityFiles) {
  $cleared = 0;
  foreach ($oldTranslationAmbiguityFiles as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old translation ambiguity cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} old translation ambiguity cache files from root cache directory\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  No old translation ambiguity cache files found in root directory\n";
}

// ========================================
// 7.9. CLEAR CONTEXT CACHE (FILE-BASED)
// ========================================
echo "\n7.9. Clearing context cache files...\n";
$contextCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Context/';
if (is_dir($contextCacheDir)) {
  $files = glob($contextCacheDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete context cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} context cache files from Context/\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Context cache directory not found (Context/)\n";
}

// Also clear old context cache files from root cache directory
$oldContextFiles = glob($generalCacheDir . 'context_*.cache');
if ($oldContextFiles) {
  $cleared = 0;
  foreach ($oldContextFiles as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old context cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} old context cache files from root cache directory\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  No old context cache files found in root directory\n";
}

// ========================================
// 7.10. CLEAR EMBEDDING CACHE (FILE-BASED) - PHASE 2 OPTIMIZATION
// ========================================
echo "\n7.10. Clearing embedding cache files (Phase 2 - NewVector cache)...\n";

// NEW: Clear Embeddings cache (Phase 2 - NewVector::createEmbedding cache)
$embeddingsCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Embeddings/';
if (is_dir($embeddingsCacheDir)) {
  $files = glob($embeddingsCacheDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete embeddings cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} embeddings cache files from Rag/Embeddings/ (Phase 2)\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Embeddings cache directory not found (Rag/Embeddings/) - Phase 2 not yet implemented\n";
}

// OLD: Clear Embedding cache (legacy location)
$embeddingCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Embedding/';
if (is_dir($embeddingCacheDir)) {
  $files = glob($embeddingCacheDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete embedding cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} embedding cache files from Rag/Embedding/ (legacy)\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Embedding cache directory not found (Rag/Embedding/)\n";
}

// Also clear old embedding cache files from root cache directory (excluding embedding_search)
$oldEmbeddingFiles = glob($generalCacheDir . 'embedding_*.cache');
if ($oldEmbeddingFiles) {
  // Filter out embedding_search files
  $oldEmbeddingFiles = array_filter($oldEmbeddingFiles, function($file) {
    return strpos(basename($file), 'embedding_search_') !== 0;
  });
  
  $cleared = 0;
  foreach ($oldEmbeddingFiles as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old embedding cache: " . basename($file);
    }
  }
  if ($cleared > 0) {
    echo "   ✅ Cleared {$cleared} old embedding cache files from root cache directory\n";
    $totalCleared += $cleared;
  }
} else {
  echo "   ℹ️  No old embedding cache files found in root directory\n";
}

// ========================================
// 7.11. CLEAR SEMANTIC SEARCH CACHE (FILE-BASED) - PHASE 3 OPTIMIZATION
// ========================================
echo "\n7.11. Clearing semantic search cache files (Phase 3 - SemanticQueryExecutor cache)...\n";

// NEW: Clear Semantic cache (Phase 3 - SemanticQueryExecutor cache)
$semanticCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Semantic/';
if (is_dir($semanticCacheDir)) {
  $files = glob($semanticCacheDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete semantic cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} semantic search cache files from Rag/Semantic/ (Phase 3)\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Semantic cache directory not found (Rag/Semantic/) - Phase 3 not yet implemented\n";
}

// Also clear old semantic cache files from root cache directory
$oldSemanticFiles = glob($generalCacheDir . 'semantic_*.cache');
if ($oldSemanticFiles) {
  $cleared = 0;
  foreach ($oldSemanticFiles as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old semantic cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} old semantic cache files from root cache directory\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  No old semantic cache files found in root directory\n";
}

// ========================================
// 7.12. CLEAR EMBEDDINGSEARCH CACHE (FILE-BASED)
// ========================================
echo "\n7.12. Clearing EmbeddingSearch cache files...\n";
$embeddingSearchCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/EmbeddingSearch/';
if (is_dir($embeddingSearchCacheDir)) {
  $files = glob($embeddingSearchCacheDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete EmbeddingSearch cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} EmbeddingSearch cache files from EmbeddingSearch/\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  EmbeddingSearch cache directory not found (EmbeddingSearch/)\n";
}

// Also clear old embedding_search cache files from root cache directory
$oldEmbeddingSearchFiles = glob($generalCacheDir . 'embedding_search_*.cache');
if ($oldEmbeddingSearchFiles) {
  $cleared = 0;
  foreach ($oldEmbeddingSearchFiles as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old embedding_search cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} old embedding_search cache files from root cache directory\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  No old embedding_search cache files found in root directory\n";
}

// Also clear old EmbeddingSearch cache files from root cache directory
$oldEmbeddingSearchFiles2 = glob($generalCacheDir . 'EmbeddingSearch_*.cache');
if ($oldEmbeddingSearchFiles2) {
  $cleared = 0;
  foreach ($oldEmbeddingSearchFiles2 as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old EmbeddingSearch cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} old EmbeddingSearch cache files from root cache directory\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  No old EmbeddingSearch cache files found in root directory\n";
}

// Also check old embedding_search directory
$oldEmbeddingSearchDir = __DIR__ . '/Core/ClicShopping/Work/Cache/embedding_search/';
if (is_dir($oldEmbeddingSearchDir)) {
  $files = glob($oldEmbeddingSearchDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete old embedding_search directory cache: " . basename($file);
    }
  }
  if ($cleared > 0) {
    echo "   ✅ Cleared {$cleared} cache files from old embedding_search/ directory\n";
    $totalCleared += $cleared;
  }
} else {
  echo "   ℹ️  No old embedding_search/ directory found\n";
}

// ========================================
// 7.13. CLEAR SECURITY ANALYSIS CACHE (FILE-BASED) - NEW
// ========================================
echo "\n7.13. Clearing security analysis cache files...\n";
$securityCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Security/';
if (is_dir($securityCacheDir)) {
  $files = glob($securityCacheDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete security cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} security analysis cache files from Rag/Security/\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Security cache directory not found (Rag/Security/)\n";
}

// ========================================
// 7.14. CLEAR TECHNICAL CONFIG CACHE (FILE-BASED) - NEW
// ========================================
echo "\n7.14. Clearing technical config cache files...\n";
$configCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/Config/';
if (is_dir($configCacheDir)) {
  $files = glob($configCacheDir . '*.cache');
  $cleared = 0;
  foreach ($files as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete config cache: " . basename($file);
    }
  }
  echo "   ✅ Cleared {$cleared} technical config cache files from Rag/Config/\n";
  $totalCleared += $cleared;
} else {
  echo "   ℹ️  Config cache directory not found (Rag/Config/)\n";
}

// ========================================
// 8. CLEAR GENERAL CACHE DIRECTORIES
// ========================================
echo "\n8. Clearing general cache directories...\n";
$generalCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/Rag/';
if (is_dir($generalCacheDir)) {
  $subdirs = ['Ambiguity', 'Classification', 'Context', 'Embeddings', 'Hybrid', 'Intent', 'SchemaQuery', 'Security', 'Semantic', 'SQL', 'Translation', 'Reputation'];
  foreach ($subdirs as $subdir) {
    $path = $generalCacheDir . $subdir . '/';
    if (is_dir($path)) {
      $cleared = deleteFilesRecursive($path, ['.htaccess', 'index.php']);
      if ($cleared > 0) {
        echo "   ✅ Cleared {$cleared} files from {$subdir}/\n";
        $totalCleared += $cleared;
      }
    }
  }
  
  // Clear any remaining .cache files in root cache directory
  $cacheFiles = glob( __DIR__ . '/Core/ClicShopping/Work/' . '*.cache');
  if ($cacheFiles) {
    $cleared = 0;
    foreach ($cacheFiles as $file) {
      if (unlink($file)) {
        $cleared++;
      }
    }
    if ($cleared > 0) {
      echo "   ✅ Cleared {$cleared} additional cache files\n";
      $totalCleared += $cleared;
    }
  }

  $cacheFiles = glob($generalCacheDir . '*.json');
  if ($cacheFiles) {
    $cleared = 0;
    foreach ($cacheFiles as $file) {
      if (unlink($file)) {
        $cleared++;
      }
    }
    if ($cleared > 0) {
      echo "   ✅ Cleared {$cleared} additional json files\n";
      $totalCleared += $cleared;
    }
  }

    
} else {
  echo "   ⚠️  General cache directory not found\n";
}

// ========================================
// 8.5. CLEAR ALL FILES IN Work/Cache (ROOT + SUBDIRS)
// ========================================
echo "\n8.5. Clearing ALL files in Work/Cache (recursive)...\n";
$workCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/';
if (is_dir($workCacheDir)) {
  $cleared = deleteFilesRecursive($workCacheDir, ['.htaccess', 'index.php']);
  echo "   ✅ Cleared {$cleared} files from Work/Cache (recursive)\n";
  $totalCleared += $cleared;
} else {
  echo "   ⚠️  Work/Cache directory not found\n";
}

// ========================================
// 9. CLEAR SYMFONY CACHE
// ========================================
echo "\n9. Checking for Symfony cache...\n";
$symfonyCache = __DIR__ . '/var/cache/';
if (is_dir($symfonyCache)) {
  $cleared = 0;
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($symfonyCache, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );
  
  foreach ($iterator as $file) {
    if ($file->isFile()) {
      if (unlink($file->getPathname())) {
        $cleared++;
      }
    }
  }
  
  if ($cleared > 0) {
    echo "   ✅ Cleared {$cleared} Symfony cache files\n";
    $totalCleared += $cleared;
  }
} else {
  echo "   ℹ️  Symfony cache not found\n";
}

// ========================================
// 10. CLEAR OPCACHE (PHP BYTECODE)
// ========================================
echo "\n10. Clearing OpCache...\n";
if (function_exists('opcache_reset')) {
  if (opcache_reset()) {
    echo "   ✅ OpCache cleared\n";
    $totalCleared++;
  } else {
    echo "   ⚠️  OpCache reset failed\n";
    $errors[] = "OpCache reset failed";
  }
} else {
  echo "   ℹ️  OpCache not enabled\n";
}

// ========================================
// 11. CLEAR PHP SESSION CACHE
// ========================================
echo "\n11. Clearing PHP session cache...\n";
if (session_status() === PHP_SESSION_ACTIVE) {
  session_destroy();
  echo "   ✅ Session destroyed\n";
  $totalCleared++;
} else {
  echo "   ℹ️  No active session\n";
}

// ========================================
// 12. CLEAR LANGUAGE CACHE AND DATABASE - CRITICAL
// ========================================
echo "\n12. Clearing language cache and database synchronization...\n";
try {
  // Clear language cache files. Real name is `languages-defs-{group}-lang{id}.cache` (HYPHENS):
  // the former `languages_*` / `lang_*` globs never matched a single file.
  $languageCacheDir = __DIR__ . '/Core/ClicShopping/Work/Cache/';
  $languageFiles = glob($languageCacheDir . 'languages-defs-*.cache');
  $languageFiles = array_merge($languageFiles, glob($languageCacheDir . 'languages-*.cache'));
  $languageFiles = array_unique($languageFiles);

  $cleared = 0;
  foreach ($languageFiles as $file) {
    if (unlink($file)) {
      $cleared++;
    } else {
      $errors[] = "Failed to delete language cache: " . basename($file);
    }
  }
  
  if ($cleared > 0) {
    echo "   ✅ Cleared {$cleared} language cache files\n";
    $totalCleared += $cleared;
  } else {
    echo "   ℹ️  No language cache files found\n";
  }
  
  // Clear language database entries (prompts and translations)
  $db = Registry::get('Db');
  $prefix = CLICSHOPPING::getConfig('db_table_prefix');
  
  // Check if languages_definitions table exists
  try {
    $checkTable = $db->query("SHOW TABLES LIKE '{$prefix}languages_definitions'");
    if ($checkTable->rowCount() > 0) {
      // Drop ONLY the freshness stamps, never the definitions themselves.
      // Language::getDefinitions() re-reads a .txt when the group is empty OR its stamp is older
      // than the file; removing the stamp forces that re-read, so every key the file defines is
      // rebuilt while DB-only overrides survive. Deleting definition rows instead is data loss:
      // the surviving stamp then tells the loader the group is current and the file is NEVER
      // re-read (this is what the former `definition_key LIKE '%prompt%'` delete did).
      $stampKey = '__source_file_mtime__';

      $countStmt = $db->prepare("SELECT COUNT(*) as count FROM {$prefix}languages_definitions WHERE definition_key = :key");
      $countStmt->execute([':key' => $stampKey]);
      $stampCount = (int)$countStmt->fetch()['count'];

      if ($stampCount > 0) {
        $deleteStmt = $db->prepare("DELETE FROM {$prefix}languages_definitions WHERE definition_key = :key");
        $deleteStmt->execute([':key' => $stampKey]);
        echo "   ✅ Cleared {$stampCount} language source stamps from database (definitions rebuild on next load)\n";
        $totalCleared += $stampCount;
      } else {
        echo "   ℹ️  No language source stamps found in database\n";
      }
    } else {
      echo "   ℹ️  Language definitions table not found\n";
    }
  } catch (\Exception $e) {
    echo "   ⚠️  Could not clear language database entries: " . $e->getMessage() . "\n";
    $errors[] = "Language DB clear failed: " . $e->getMessage();
  }
  
  echo "   💡 Language cache and DB synchronization completed\n";
  
} catch (\Exception $e) {
  echo "   ❌ Error: {$e->getMessage()}\n";
  $errors[] = "Language cache clear failed: {$e->getMessage()}";
}

// ========================================
// SUMMARY
// ========================================
echo "\n";
echo str_repeat("=", 80) . "\n";
echo "[info] CACHE CLEARING SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "Total items cleared: {$totalCleared}\n";

if (!empty($errors)) {
  echo "\n⚠️  ERRORS ENCOUNTERED:\n";
  foreach ($errors as $error) {
    echo "  ❌ {$error}\n";
  }
  echo "\n";
}

if (count($errors) === 0) {
  echo "\n✅ ALL CACHES CLEARED SUCCESSFULLY!\n";
  echo "\n";
  echo "📝 NEXT STEPS:\n";
  echo "   1. Test \"Revenue of the month\" in chat interface\n";
  echo "   2. Expected: SQL query from Analytics (not RAG response)\n";
  echo "   3. Check logs: tail -f " . __DIR__ . "/Core/ClicShopping/Work/Log/errors-" . date('Ymd') . ".txt\n";
  echo "\n";
  echo "🔍 WHAT TO LOOK FOR IN LOGS:\n";
  echo "   ✅ \"⚡ FAST PATH DISABLED: Pure LLM mode active\"\n";
  echo "   ✅ \"Intent Type: analytics\" (not semantic)\n";
  echo "   ✅ \"confidence: 0.95\" (not 0.50)\n";
  echo "   ✅ \"Agent Used: analytics_agent\" (not semantic_agent)\n";
  echo "   ❌ \"✅ CACHE HIT\" should NOT appear (cache cleared)\n";
  echo "\n";
  echo "💡 WHY THIS FIXES THE ISSUE:\n";
  echo "   - Old cache had: semantic classification with confidence 0.50\n";
  echo "   - New LLM prompt returns: analytics classification with confidence 0.95\n";
  echo "   - Fast path disabled: All queries go through LLM (Pure LLM Mode)\n";
  echo "   - Cache cleared: Forces fresh classification\n";
  echo "\n";
} else {
  echo "\n⚠️  SOME ERRORS OCCURRED - Please review above\n\n";
}

exit(0);
