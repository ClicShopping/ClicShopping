# ARCHITECTURE.md — ClicShopping AI v4.20+

> Core architecture of the PHP framework.
> Agent rules: `AGENTS.md` | AI: `AI_SYSTEM.md` | DB: `DATABASE.md` | Security: `SECURITY.md`

---

## 1. Overview

ClicShopping AI is organized into two application sites (`Shop` and `ClicShoppingAdmin`)
sharing a common core. The AI ​​layer is agnostic and organized to use the business domain approach — see `AI_SYSTEM.md`.

```
AGENTS.md ← operational rules for LLM agents
ARCHITECTURE.md ← framework core, bootstrap, hooks, templates, namespaces
AI_SYSTEM.md ← agents, RAG, LLM providers, embeddings, architecture directories
DATABASE.md ← MariaDB, SQL schema, routing, migrations
SECURITY.md ← 10 layers of security, guardrails, GDPR
```

---

## 2. Bootstrap and routing

Initialization flow — DO NOT tamper with:

```
index.php 
→ CLICSHOPPING::initialize() 
→ Site determination (Shop | ClicShoppingAdmin) 
→ Core services: Db, Session, Language 
→ setPage() — controller resolution via URL parameter 
→ Controller execution (implements PagesInterface)
```

Application Structure

```
clicshoppingAI/
├── ClicShoppingAdmin/        # Administrative backend interface
├── Core/                     # Core application framework
├── api/                      # REST API endpoints
├── unit_test/                # Comprehensive testing suite
├── install/                  # Installation and setup scripts
├── docs/                     # Documentation about ClicShopping
├── ext/                      # Asset - javascript for all application...
├── sources/                  # front office templates and assets for templates
├── sql_upgrade/                  # sql update for human to update only sql change inside the db
├── composer.json             # PHP dependencies
└── index.php                 # Main application entry point

```

Structure of a controller page:
```
Core/ClicShopping/Sites/{Site}/Pages/{PageName}/
├── {PageName}.php # Implements PagesInterface
└── Actions/ # Page actions (POST, processing)
```

---

## 3. Service Registry

Central Registry Pattern — uniform access to all services.

```php
$db = \ClicShopping\OM\Registry::get('Db');
\ClicShopping\OM\Registry::set('MyService', new MyService());
```

Core services available:

| Key | Role |
|---|---|
| `Db` | DB service based on `\ClicShopping\OM\Db` (extends `PDO`) |
| `Session` | Redis / Database / File |
| `Language` | Multilingual support |
| `Cookies` | Cookie management |
| `Hooks` | Events system |
| `Service` | Modular container |
| `Template` | Front-office rendering (Shop) |
| `TemplateAdmin` | Back-office rendering (ClicShoppingAdmin) |

> **Registry vs DI:** ClicShopping uses the Registry as a locator service.
> Do not create an alternative DI container — use `Registry::set/get`.
> STRICT ENFORCEMENT: Direct instantiation of Core services (e.g., $db = new Db()) is PROHIBITED. You MUST use Registry::get('Db'), Registry::get('Language'), etc. No exceptions for 'quick fixes'.
---

## 4. Hooks system

Primary scalability mechanism. Always evaluate hooks before any other approach.

```php
// Core/ClicShopping/Apps/{Vendor}/{App}/Module/Hooks/{Site}/{HookName}/{HookName}.php
namespace ClicShopping\Apps\Vendor\App\Module\Hooks\Shop\MyHook;

class MyHook
{ 
public function execute(): string 
{ 
return '<!-- injected content -->'; 
}
}
```

Rules:
- Registration via existing mechanism included — no manual call - Core/ClicShopping/OM/Hooks.php
- Do not short-circuit the hook loader
- Document the extension points used in the commit

Discover the hooks available in a scope:
```bash
grep -r "Hooks" Core/ClicShopping/Sites/{Site}/ --include="*.php" -l
```

---
## 5. Namespaces and autoload

```
ClicShopping\OM\ → Core/ClicShopping/OM/
ClicShopping\Apps\{Vendor}\{App}\ → Core/ClicShopping/Apps/{Vendor}/{App}/
ClicShopping\Custom\ → Core/ClicShopping/Custom/
```

Class loading is handled by `CLICSHOPPING::autoload` (core) and Composer for `External/vendor`.
Never create an alternative autoload mechanism.

---

## 6. Templates — Front-office vs Back-office

Complete documentation: **`TEMPLATES.md`**

Summary of key points for navigating ARCHITECTURE.md:

| Appearance | Shop (Front office) | ClicShoppingAdmin (Back-office) |
|---|---|---|
| Service Registry | `Template` | `TemplateAdmin` |
| Resolution | App → global theme (fallback) | App only — no fallback |
| Cache | Yes — catalog pages | No — fresh data |
| SEO | Review | Not applicable |

The business logic for FrontOffice are (`Sites/*/Pages/`) and contain orchestration logic. the html templates are (`sources/`) and contain the visual logic and modules
See `TEMPLATES.md` for structures, helpers, SEO, i18n and complete checklist.

---

## 7. Languages — Layer Resolution

| Layer | Path | Scope |
|---|---|---|
| **App / Module** | `Core/ClicShopping/Apps/*/languages/{lang}/` | High priority for Apps (Shop and Admin) |
| **Admin core** | `ClicShoppingAdmin/Core/languages/{lang}/` | Back office global labels |
| **Overall / Theme** | `sources/languages/{lang}/` | Transversal texts and front-office fallback |

Rules:
- No visible hardcoded string in PHP or templates
- Minimum compatibility: EN + FR
- Format consistent with existing files in target scope

---

## 8. Custom/ — Override core

`Core/ClicShopping/Custom/` allows you to override `OM/` without modifying it directly.

### Scalability Priority Order

```
1. Existing hook → priority solution
2. Module → Core/ClicShopping/Apps/*/Module/, self-content
3. New App → Core/ClicShopping/Apps/{Vendor}/{AppName}/
4. Custom/ → override core, if 1-3 impossible
5. OM/ direct → PROHIBITED without agreement from the human coder
```

### Structure

```
Core/ClicShopping/Custom/
├── OM/ # Overloading kernel classes (extends required)
├── Conf/ # Custom configuration
├── Sites/ # Overload bootstrap Shop or Admin
└── Schema/ # Additional tables (*.txt files)
```

A db schema example used during the installation:
```
api_ip_id int(11) not_null auto_increment comment(Primary key - unique identifier for each API IP whitelist entry)
api_id int(11) not_null comment(FK to api table - API configuration this IP is allowed for)
ip varchar(40) not_null comment(Whitelisted IP address - IPv4 or IPv6 format)
comment varchar(255) default null comment(Optional description of this IP whitelist entry)
--
primary api_ip_id
idx_api_ip_id api_ip_id
##
engine innodb
character_set utf8mb4
collate utf8mb4_unicode_ci
comment IP address whitelist for API access control and security
```



### Example

```php
namespace ClicShopping\Custom\OM;

class Http extends \ClicShopping\OM\Http
{ 
public private(set) string $status = 'idle'; //PHP 8.4 

public function get(string $url, array $options = []): string 
{ 
return parent::get($url, $options); 
}
}

// Registration
\ClicShopping\OM\Registry::set('Http', new \ClicShopping\Custom\OM\Http());
```

Custom Rules/:
- `extends` required — never copy and paste core code
- Namespace: `ClicShopping\Custom\{Subspace}\{Class}`
- Do not break backward compatibility of existing modules

---

## 9. Cache — 5-tier architecture

| Tier | Technology | Scope |
|---|---|---|
| 1 | OpCache | Bytecode PHP |
| 2 | Static cache | Pre-rendered Shop catalog pages |
| 3 | Memcached | Multi-server distributed cache |
| 4 | Redis | Sessions + application data |
| 5 | APCu | User space cache |

Do not introduce a sixth mechanism without explicit agreement.

---

## 10. Sessions

Four backends with automatic fallback:
1 **Database** — persistent, table storage
2 **File** — native PHP fallback
3 **Memcached** — option to be activated - distributed cache, TTL = `session.gc_maxlifetime`
4 **Redis** — option to be activated - `localhost:6379`, prefix `sess_`, TTL = `session.gc_maxlifetime`


---

## 11. OrchestratorAgent Architecture (2026-04-30)

### Component Extraction

OrchestratorAgent delegates to specialized components following single-responsibility principle:

| Component | Location | Responsibility |
|-----------|----------|----------------|
| **DomainRouter** | `DomainsAI/DomainRouter.php` | Routes queries to appropriate domains (semantic, analytics, hybrid, web) |
| **QueryProcessor** | `Handler/Query/QueryProcessor.php` | Query processing with retry logic and parallel execution |
| **HybridQueryHandler** | `DomainsAI/Hybrid/Handler/HybridQueryHandler.php` | Handles hybrid queries (analytics + semantic + web) |
| **PerformanceTracker** | `Infrastructure/Monitoring/PerformanceTracker.php` | Query-level performance monitoring with markers |

### Domain-Agnostic Architecture

**CRITICAL**: Core AI (`Core/ClicShopping/AI/`) MUST NOT contain domain-specific keywords.

```
❌ PROHIBITED: Hardcoded keywords in Core AI
$keywords = ['amazon', 'ebay', 'linkedin', 'bloomberg'];

✅ REQUIRED: Dynamic loading from domain configuration
$keywords = DomainKeywordsLoader::loadWebSearchKeywords('Ecommerce');
```

**Keyword Location**: `Apps/AI/{Domain}/Classes/.../Patterns/HybridPreFilter.php`

**Supported Domains**: Ecommerce (active), HR (future), Finance (future), Trading (future)

### Integration Pattern

```php
// OrchestratorAgent initialization
$this->domainRouter = new DomainRouter($debug);
$this->queryProcessor = new QueryProcessor($contextManager, $queryAnalyzer, ...);
$this->hybridQueryHandler = new HybridQueryHandler($planner, $executor, ...);
$this->performanceTracker = new PerformanceTracker($collector, $debug);

// Usage - delegation pattern
$domain = $this->domainRouter->getDomainForIntent($intentType, $context);
$result = $this->queryProcessor->processWithRetry($query, $options, $callback);
$hybridResult = $this->hybridQueryHandler->handleHybridQuery($query, ...);
$this->performanceTracker->startTracking();
```

### Documentation

- **Overview**: `Core/ClicShopping/AI/ORCHESTRATOR_REFACTORING_2026_04_30.md`
- **Migration**: `Core/ClicShopping/AI/ORCHESTRATOR_MIGRATION_GUIDE.md`
- **Class Index**: `Core/ClicShopping/AI/REFACTORING_CLASS_INDEX.md`
- **Spec**: `.kiro/specs/orchestrator-agent-refactoring/`

---

## 12. Cross-references

| Subject                                               | File |
|-------------------------------------------------------|---|
| Agent operational rules                               | `AGENTS.md` |
| AI system, agents, RAG, LLM, architecture directories | `AI_SYSTEM.md` |
| Database, SQL, embeddings                             | `DATABASE.md` |
| Security, guardrails, GDPR                            | `SECURITY.md` |
| Templates, rendering, SEO, i18n                       | `TEMPLATES.md` |
| Official Wiki                                         | https://github.com/ClicShopping/ClicShopping/wiki |
| DeepWiki                                              | https://deepwiki.com/ClicShopping/ClicShopping |
| Tech Framework - Core framework architecture          | https://github.com/ClicShopping/ClicShopping/wiki/Tech--Framework |
| Modern App Architecture - Modern development patterns | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Modern-App-Architecture |
| Tech Configuration - Configuration management system  | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Configuration |
| Tech Database - Database layer and ORM                | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Database |
| Tech Registry - Service locator pattern               | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Registry |
| Tech Hooks - Hook system architecture (technical)     | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Hooks |
| Tech Cache - Multi-layer caching system               | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Cache  |