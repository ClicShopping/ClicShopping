# DATABASE.md — ClicShopping AI v4.20+

> Database layer: engine, schema, SQL routing, vector embeddings, migrations.
> Agent operational rules: `AGENTS.md` — AI Embeddings: `AI_SYSTEM.md`

---

## 1. Engine required

**MariaDB ≥ 11.7 — required.**

MySQL 9.x is **incompatible** with the project's native vector features.

| Feature | Requirement |
|---|---|
| Vector type | MariaDB native `VECTOR` |
| Vector index | `VECTOR INDEX` native MariaDB |
| JSON | JSON `metadata` column (v4.11+) |
| Dimensions | Respect the existing dimensions per table (3072) |

Cloud or PaaS environments: explicitly check the MariaDB version before any deployment.
If in doubt, query `SELECT VERSION();` and validate ≥ 11.7.

---

## 2. Database Access — Paradigm by Directory

Two paradigms coexist. They are **mutually exclusive per file** — never mix them.

| Directory | Paradigm | Reason |
|---|---|---|
| `Core/ClicShopping/AI/` | **Doctrine ORM only** | Agnostic layer — must stay framework-independent |
| Everywhere else | **`Registry::get('Db')` only** | Framework layer — single managed connection |

```
PARADIGM SHIFT — CRITICAL RULE:
  Inside  Core/ClicShopping/AI/ → Doctrine ORM only
  Outside Core/ClicShopping/AI/ → Registry::get('Db') only
  NEVER mix both paradigms in the same file.
  If a script moves data from AI/ to Core, use Registry for the final insertion.
```

### Registry::get('Db') — Usage Examples

Direct PDO connection (`new \PDO(...)`) is **forbidden** everywhere outside the AI layer.

```php
// Correct read access
$db = \ClicShopping\OM\Registry::get('Db');
$result = $db->query('SELECT ...');

// Forbidden
$pdo = new \PDO('mysql:host=...'); // parallel connection outside Registry
```

Update (save with condition):
```php
$sql_data_array = [
    'parent_id'  => (int)$new_parent_id,
    'sort_order' => (int)$sort_order,
];
$update_sql_data = ['last_modified' => 'now()'];
$sql_data_array = array_merge($sql_data_array, $update_sql_data);
$this->app->db->save('categories', $sql_data_array, ['categories_id' => (int)$categories_id]);
```

Insert (save without condition):
```php
$sql_data_array = [
    'parent_id'    => (int)$new_parent_id,
    'sort_order'   => (int)$sort_order,
    'last_modified' => 'now()',
];
$this->app->db->save('categories', $sql_data_array);
```

### Doctrine ORM — Core/ClicShopping/AI/ only

Follow the Doctrine ORM conventions as defined in the AI directory.
Do not use `Registry::get('Db')` anywhere inside `Core/ClicShopping/AI/`.

---

## 3. SQL Routing — Which file, where

Five distinct slots with non-interchangeable roles:

| Location | Role | Agent access |
|---|---|---|
| `Core/ClicShopping/Schema/MariaDb/` | Canonical schema of all tables — source of truth | Read only |
| `install/Db/*.sql` | Initial seed data for fresh installation | Read only |
| `Core/ClicShopping/Apps/{Vendor}/{AppName}/Sql/MariaDb/` | App SQL (CREATE, INSERT) activated when App is enabled | **Writing** |
| `Core/ClicShopping/Custom/Schema/` | Additional tables for Custom overloads (*.txt files) | **Writing** |
| `sql_upgrade/` | Migration guide for end user — documentation only | Read only |

### Decision rule

```
New table for an App    → Core/ClicShopping/Apps/{Vendor}/{AppName}/Sql/MariaDb/
New table for overload  → Core/ClicShopping/Custom/Schema/
Read existing schema    → Core/ClicShopping/Schema/MariaDb/ (never modify)
Version migration notes → sql_upgrade/ (documentation only — never write code here)
```

**Never write application SQL in `sql_upgrade/`.** This directory is a documentary guide
for the user to understand changes between versions — it is not executed automatically.

---

## 4. SQL Scripts of an App — Structure

```
Core/ClicShopping/Apps/{Vendor}/{AppName}/Sql/MariaDb/
├── MariaDb.php      # Main installation/migration script run when App is activated
├── *.sql            # Targeted migrations (if necessary)
└── *.php            # Utility install/repair scripts targeted to the App
```

Writing rules:
- Use `CREATE TABLE IF NOT EXISTS` — never `CREATE TABLE` alone
- Encapsulate destructive deletions in explicitly dedicated scripts
- Backwards-compatible schema — no `DROP COLUMN` without explicit migration
- Table prefix: `clic_` (project convention)
- Encoding: `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`

Minimal creation example:
```sql
CREATE TABLE IF NOT EXISTS `clic_my_feature` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label`         VARCHAR(255) NOT NULL DEFAULT '',
  `status`        TINYINT(1) NOT NULL DEFAULT 1,
  `date_added`    DATETIME NOT NULL,
  `date_modified` DATETIME,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## 5. Canonical Schema — Core/ClicShopping/Schema/MariaDb/

This directory contains the complete definition of all tables for a fresh installation.
It is the **source of truth** for the overall schema.

Agent rules:
- Read before creating a new table (avoid duplicates or naming conflicts)
- Check existing table structure before writing queries
- Never modify these files without human coder consent

---

## 6. AI Embedding Tables

These tables are managed by the AI pipeline — do not modify them manually.

| Table | Entity |
|---|---|
| `clic_products_embedding` | Products |
| `clic_categories_embedding` | Categories |
| `clic_reviews_embedding` | Customer reviews |
| `clic_reviews_sentiment_embedding` | Review sentiment |
| `clic_orders_embedding` | Orders |
| `clic_pages_manager_embedding` | CMS pages |
| `clic_manufacturers_embedding` | Manufacturers |
| `clic_suppliers_embedding` | Suppliers |
| `clic_return_orders_embedding` | Order returns |
| `clic_conversation_memory_embedding` | Conversational memory |
| `clic_correction_pattern_embedding` | Correction patterns |

### Common Structure

```sql
CREATE TABLE `clic_*_embedding` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_id`   INT UNSIGNED NOT NULL,          -- FK to source table
  `embedding`   VECTOR(3072) NOT NULL,           -- semantic vector
  `content`     TEXT NOT NULL,                  -- indexed original text
  `metadata`    JSON,                           -- enrichment (v4.11+)
  `chunknumber` INT UNSIGNED NOT NULL DEFAULT 0, -- chunk index (128 tokens/chunk)
  `date_modified` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  VECTOR INDEX `vec_idx` (`embedding`)
) ENGINE=InnoDB;
```

Embedding rules:
- Vector dimensions: fixed at 3072 — do not modify without human coder agreement
- Generation via existing crons — do not recreate this pipeline
- `metadata` JSON is optional but must remain present in the schema

---

## 7. Migrations and Schema Updates

### General rules

- Any schema change must be **backwards compatible**
- Never delete a column without checking all dependencies
- Never rename an existing table or column without a migration script
- Prefer `ADD COLUMN` + data migration rather than destructive `MODIFY COLUMN`

### sql_upgrade/ — correct usage

`sql_upgrade/` contains **documentary** text files listing SQL changes between two versions.
This is not a directory of automatically executable scripts.

Expected use:
- User reads `sql_upgrade/updateX_XX.txt` to understand which ALTER TABLEs to apply manually
- The agent must **never** generate a file in this directory

---

## 8. Security and Monitoring Tables

These tables are managed by the security and monitoring layers — do not modify them:

| Table | Role |
|---|---|
| `clic_api_rate_limit` | Tracking requests by identifier + timestamp |
| `clic_api_failed_attempts` | Failed login attempts |
| `clic_rag_security_events` | AI security event audit |
| `clic_mcp_performance_history` | MCP metrics (latency, uptime, errors) |

---

## 9. Absolute Prohibitions

```
✗ Direct PDO connection outside Registry::get('Db') (except Core/ClicShopping/AI/)
✗ Mixing Doctrine ORM and Registry::get('Db') in the same file
✗ MySQL 9.x (VECTOR incompatible)
✗ MariaDB < 11.7
✗ Automatic DROP TABLE outside an explicit maintenance script
✗ Application SQL in sql_upgrade/
✗ Modifying *_embedding table structure without human coder agreement
✗ Modifying existing vector dimensions without human coder agreement
✗ CREATE TABLE without IF NOT EXISTS
✗ Schema not backwards compatible
✗ Table prefix different from clic_
```

---

## 10. References

- Architecture framework: `ARCHITECTURE.md`
- AI embedding pipeline: `AI_SYSTEM.md` §7
- Security audit tables: `SECURITY.md` §5
- DeepWiki DB: https://deepwiki.com/ClicShopping/ClicShopping/2.2-database-schema-and-version-migrations
- DB architecture: https://github.com/ClicShopping/ClicShopping/wiki/Tech-Database
