# ARCHITECTURE.md — ClicShopping AI v4.30+

> Core architecture of the PHP framework.
> Agent rules: `AGENTS.md` | AI concepts: `AI_SYSTEM.md` | AI directories: `AI_ARCHITECTURE.md`
> DB: `DATABASE.md` | Security: `SECURITY.md` | Templates: `TEMPLATES.md`

---

## 1. Overview

ClicShopping AI is organized into two application sites (`Shop` and `ClicShoppingAdmin`)
sharing a common core. The AI layer is agnostic and organized by business domain — see `AI_SYSTEM.md`
for AI concepts and `AI_ARCHITECTURE.md` for directory structure.

```
AGENTS.md          ← operational rules for LLM agents
ARCHITECTURE.md    ← framework core: bootstrap, hooks, templates, namespaces
AI_SYSTEM.md       ← agents, RAG, LLM providers, memory, embeddings, MCP
AI_ARCHITECTURE.md ← AI directory structure, orchestrator components, patterns
DATABASE.md        ← MariaDB, SQL schema, routing, migrations
SECURITY.md        ← 10 security layers, guardrails, GDPR
TEMPLATES.md       ← front-office vs back-office rendering, SEO, i18n
```

---

## 2. Bootstrap and Routing

Initialization flow — DO NOT tamper with:

```
index.php
→ CLICSHOPPING::initialize()
→ Site determination (Shop | ClicShoppingAdmin)
→ Core services: Db, Session, Language
→ setPage() — controller resolution via URL parameter
→ Controller execution (implements PagesInterface)
```

### Application Structure

```
clicshoppingAI/
├── ClicShoppingAdmin/   # Administrative backend interface
├── Core/                # Core application framework
├── api/                 # REST API endpoints
├── unit_test/           # Comprehensive testing suite
├── install/             # Installation and setup scripts
├── docs/                # Documentation about ClicShopping
├── ext/                 # JavaScript assets for all applications
├── sources/             # Front-office templates and assets
├── sql_upgrade/         # SQL migration guide for humans (documentation only)
├── composer.json        # PHP dependencies
└── index.php            # Main application entry point
```

### Controller Page Structure

```
Core/ClicShopping/Sites/{Site}/Pages/{PageName}/
├── {PageName}.php   # Implements PagesInterface
└── Actions/         # Page actions (POST, processing)
```

---

## 3. Service Registry

Central Registry Pattern — uniform access to all services.

```php
use ClicShopping\OM\Registry;
use ClicShopping\OM\MyService;

Registry::set('MyService', new MyService());
$CLICSHOPPING_MyService = Registry::get('MyService');
```

**STRICT ENFORCEMENT**: Direct instantiation of Core services (e.g., `$db = new Db()`) is PROHIBITED.
Always use `Registry::get('Db')`, `Registry::get('Language')`, etc. No exceptions for "quick fixes".

### Core Services

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

> **Registry vs DI:** ClicShopping uses the Registry as a service locator.
> Do not create an alternative DI container — use `Registry::set/get`.

---

## 4. Hooks System

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
- Registration via existing mechanism — no manual call (`Core/ClicShopping/OM/Hooks.php`)
- Do not short-circuit the hook loader
- Document the extension points used in the commit

Discover hooks available in a scope:
```bash
grep -r "Hooks" Core/ClicShopping/Sites/{Site}/ --include="*.php" -l
```

---

## 5. Namespaces and Autoload

```
ClicShopping\OM\               → Core/ClicShopping/OM/
ClicShopping\Apps\{Vendor}\{App}\ → Core/ClicShopping/Apps/{Vendor}/{App}/
ClicShopping\Custom\           → Core/ClicShopping/Custom/
```

Class loading: `CLICSHOPPING::autoload` (core) + Composer for `External/vendor`.
Never create an alternative autoload mechanism.

---

## 6. Templates — Front-office vs Back-office

Complete documentation: **`TEMPLATES.md`**

| Aspect | Shop (Front office) | ClicShoppingAdmin (Back-office) |
|---|---|---|
| Service Registry | `Template` | `TemplateAdmin` |
| Resolution | App → global theme (fallback) | App only — no fallback |
| Cache | Yes — catalog pages | No — fresh data |
| SEO | Applicable | Not applicable |

Business logic lives in `Sites/*/Pages/`; HTML templates live in `sources/`.

---

## 7. Languages — Layer Resolution

| Layer | Path | Scope |
|---|---|---|
| **App / Module** | `Core/ClicShopping/Apps/*/languages/{lang}/` | High priority for Apps (Shop and Admin) |
| **Admin core** | `ClicShoppingAdmin/Core/languages/{lang}/` | Back-office global labels |
| **Overall / Theme** | `sources/languages/{lang}/` | Transversal texts and front-office fallback |

Rules:
- No visible hardcoded string in PHP or templates — always use `getDef('')`
- Minimum compatibility: EN + FR
- Format consistent with existing files in target scope

**AI prompt sub-layers** (under *Admin core*): RAG/agent prompts live in
`ClicShoppingAdmin/Core/languages/{lang}/{domain}/rag_*.txt` (domain-specific) and
`ClicShoppingAdmin/Core/languages/{lang}/Agents/rag_*.txt` (domain-agnostic). See
**AI_ARCHITECTURE.md §5.3** for the split rule and the `{{var}}` interpolation.
---

## 8. Custom/ — Override Core

`Core/ClicShopping/Custom/` allows overriding `OM/`, 'Apps' ... without modifying it directly.

### Structure

```
Core/ClicShopping/Custom/
├── OM/     # Overloading kernel classes (extends required)
├── Conf/   # Custom configuration
├── Sites/  # Overload bootstrap Shop or Admin
└── Schema/ # Additional tables (*.txt files)
```

### Example

```php
namespace ClicShopping\Custom\OM;

class Http extends \ClicShopping\OM\Http
{
    public private(set) string $status = 'idle'; // PHP 8.4

    public function get(string $url, array $options = []): string
    {
        return parent::get($url, $options);
    }
}

// Registration
use ClicShopping\OM\Registry;
Registry::set('Test', new Test());
$CLICSHOPPING_Test = Registry::get('Test');
```

Custom/ rules:
- `extends` required — never copy and paste core code
- Namespace: `ClicShopping\Custom\{Subspace}\{Class}`
- Do not break backward compatibility of existing modules

### Schema File Example for example.txt inside Schema directory

```
api_ip_id int(11) not_null auto_increment comment(Primary key)
api_id int(11) not_null comment(FK to api table)
ip varchar(40) not_null comment(Whitelisted IP address)
comment varchar(255) default null comment(Optional description)
--
primary api_ip_id
idx_api_ip_id api_ip_id
##
engine innodb
character_set utf8mb4
collate utf8mb4_unicode_ci
comment IP address whitelist for API access control
```

---

## 9. Cache — 5-Tier Architecture

| Tier | Technology | Scope |
|---|---|---|
| 1 | OpCache | PHP bytecode |
| 2 | Static cache | Pre-rendered Shop catalog pages |
| 3 | Memcached | Multi-server distributed cache |
| 4 | Redis | Sessions + application data |
| 5 | APCu | User space cache |

Do not introduce a sixth mechanism without explicit agreement.

---

## 10. Sessions

Four backends with automatic fallback:
1. **Database** — persistent, table storage
2. **File** — native PHP fallback
3. **Memcached** — optional, distributed cache, TTL = `session.gc_maxlifetime`
4. **Redis** — optional, `localhost:6379`, prefix `sess_`, TTL = `session.gc_maxlifetime`

---

## 11. Cross-References

| Subject | File |
|---|---|
| Agent operational rules | `AGENTS.md` |
| AI system: agents, RAG, LLM, memory, embeddings | `AI_SYSTEM.md` |
| AI directory structure, orchestrator, patterns | `AI_ARCHITECTURE.md` |
| Database, SQL, embeddings schema | `DATABASE.md` |
| Security, guardrails, GDPR | `SECURITY.md` |
| Templates, rendering, SEO, i18n | `TEMPLATES.md` |
| Official Wiki | https://github.com/ClicShopping/ClicShopping/wiki |
| DeepWiki | https://deepwiki.com/ClicShopping/ClicShopping |
| Tech Framework | https://github.com/ClicShopping/ClicShopping/wiki/Tech--Framework |
| Modern App Architecture | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Modern-App-Architecture |
| Tech Configuration | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Configuration |
| Tech Database | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Database |
| Tech Registry | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Registry |
| Tech Hooks | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Hooks |
| Tech Cache | https://github.com/ClicShopping/ClicShopping/wiki/Tech-Cache |
