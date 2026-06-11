# AI_ARCHITECTURE.md — ClicShopping AI v4.30+

---

## 1. Core AI Directory Structure

**Do not change `Core/ClicShopping/AI/` without human coder permission.**

```
Core/ClicShopping/AI/
├── CoreAI/              ✅ Infrastructure + Orchestration
│   └── Orchestrator/
│       └── SubActorCritic/
│           └── Critics/  ✅ Minimal wrappers (delegate to validators)
├── Config/              ✅ Configuration
├── Dashboard/           ✅ Monitoring
├── DomainsAI/           ✅ Business domain query types
│   ├── Shared/          ✅ Cross-query-type components (Embedding, Entity, Patterns) — ex-`CoreAI`, renamed June 2026
│   ├── Analytics/
│   │   └── Validator/   ✅ Business logic validators (added May 2026)
│   └── WebSearch/
│       └── Providers/   ✅ Built-in agnostic providers Mode A/B/C/E (added May 2026)
├── Handler/             ✅ Error, fallback, query handling
├── Helper/              ✅ Utilities
├── Infrastructure/      ✅ Technical infrastructure
├── InterfacesAI/        ✅ Contracts (ActorAgentInterface, etc.)
├── LoadBalancing/       ✅ Load balancing
├── Rag/                 ✅ RAG Manager
├── RegistryAI/          ✅ Actor, Critic and WebSearch engine registries
└── Security/            ✅ Guardrails and security
```

### Migration Notice (May 2026)

The `Agents/` directory was renamed to `CoreAI/` to align with the `*AI` suffix naming pattern
(InterfacesAI, DomainsAI, RegistryAI, CoreAI). The old `Agents/` namespace is deprecated
and will be removed in version 5.0.

### Multi-Domain App Structure

Domain-specific agents live outside the core AI layer:

```
Core/ClicShopping/Apps/AI/{DomainName}/
├── Ecommerce/    ← Active domain (analytics, SEO, web search patterns)
├── HR/           ← Future domain
├── Finance/      ← Future domain
└── Trading/      ← Future domain
```

---

## 2. Agent Registration

```php
// ✓ Correct — via Orchestrator
$orchestrator = new OrchestratorAgent();
$result = $orchestrator->execute($task);

// ✗ Prohibited — direct instantiation from Apps
$agent = new AnalyticsAgent();
$result = $agent->execute($query);
```

| Agent type | Registration location |
|---|---|
| Core infrastructure agents | `Core/ClicShopping/AI/CoreAI/` |
| Domain-specific agents | `Core/ClicShopping/Apps/AI/{Domain}/` |

Rules:
- All agents implement `ActorAgentInterface` (`Core/ClicShopping/AI/InterfacesAI/`)
- Inter-agent communication via Orchestrator only
- No direct agent instantiation from business code

---

## 3. OrchestratorAgent — Specialized Components

Following a single-responsibility refactoring (2026-04-30), OrchestratorAgent
delegates to four specialized components:

| Component | Location | Responsibility |
|---|---|---|
| **DomainRouter** | `DomainsAI/DomainRouter.php` | Routes queries to the right domain (semantic, analytics, hybrid, web) |
| **QueryProcessor** | `Handler/Query/QueryProcessor.php` | Query processing with retry logic and parallel execution |
| **HybridQueryHandler** | `DomainsAI/Hybrid/Handler/HybridQueryHandler.php` | Handles hybrid queries (analytics + semantic + web) |
| **PerformanceTracker** | `Infrastructure/Monitoring/PerformanceTracker.php` | Query-level performance monitoring with markers |

Integration pattern:

```php
// OrchestratorAgent initialization
$this->domainRouter       = new DomainRouter($debug);
$this->queryProcessor     = new QueryProcessor($contextManager, $queryAnalyzer, ...);
$this->hybridQueryHandler = new HybridQueryHandler($planner, $executor, ...);
$this->performanceTracker = new PerformanceTracker($collector, $debug);

// Usage — delegation pattern
$domain      = $this->domainRouter->getDomainForIntent($intentType, $context);
$result      = $this->queryProcessor->processWithRetry($query, $options, $callback);
$hybridResult = $this->hybridQueryHandler->handleHybridQuery($query, ...);
$this->performanceTracker->startTracking();
```

Documentation:
- Current state: `Core/ClicShopping/AI/ARCHITECTURE_EVOLUTION.md`

---

## 4. Validator Architecture (Analytics Domain)

Added May 2026 — separates business validation logic from Actor-Critic infrastructure.

| Layer | Location | Role |
|---|---|---|
| **Business validators** | `DomainsAI/Analytics/Validator/` | Testable independently, no Actor-Critic dependency |
| **Infrastructure wrappers** | `CoreAI/Orchestrator/SubActorCritic/Critics/` | Minimal delegation to validators |

### Validators

| Validator | Responsibility |
|---|---|
| `SqlQualityValidator` | SQL quality (SELECT *, LIMIT, WHERE) |
| `SqlSecurityValidator` | Security (dangerous patterns, injection) |
| `SqlPerformanceValidator` | Performance (indexes, joins) |
| `SchemaValidator` | Schema (tables, columns) |
| `AnalyticsQualityEvaluator` | Orchestrates all validators |

### Wrappers

| Wrapper | Delegates To |
|---|---|
| `SqlQualityCriticWrapper` | `SqlQualityValidator` |
| `AnalyticsCriticWrapper` | `AnalyticsQualityEvaluator` |

```php
// Direct validator usage (outside Actor-Critic)
$validator = new SqlQualityValidator();
$result = $validator->evaluateSqlQuality($sql);

// Wrapper usage (within Actor-Critic)
$wrapper = new SqlQualityCriticWrapper($validator);
$evaluation = $wrapper->evaluateAction($actionResult);
```

Rules:
```
✓ Use validators directly for business logic validation
✓ Use wrappers only within Actor-Critic pattern
✓ Validators have no dependency on CriticAgentInterface
✓ Wrappers contain no business logic (pure delegation)

✗ Do not add business logic to wrappers
✗ Do not couple validators to Actor-Critic infrastructure
✗ Do not bypass validators in wrappers
```

---

## 5. Domain-Agnostic Architecture

**CRITICAL**: `Core/ClicShopping/AI/` MUST NOT contain any domain-specific keyword,
commercial brand or marketplace-specific code. Two complementary mechanisms enforce
this rule depending on the kind of asset being declared:

### 5.1 — Keywords (`DomainKeywordsLoader`)

For per-domain keyword lists used by the deprecated pattern fallbacks:

```
❌ PROHIBITED: Hardcoded keywords in Core AI
$keywords = ['amazon', 'ebay', 'linkedin', 'bloomberg'];

✅ REQUIRED: Dynamic loading from domain configuration
$keywords = (new DomainKeywordsLoader())->loadWebSearchKeywords('Ecommerce');
```

Keyword file location:
`Apps/AI/{Domain}/Classes/ClicShoppingAdmin/Patterns/HybridPreFilter.php`

### 5.2 — Engines, Providers and SiteRouters (`WebSearchEngineRegistry`)

For domain-specific WebSearch components (engines that call SerpAPI with a
brand-specific protocol, downstream routing of `target_site → modes`, ...):

```
❌ PROHIBITED: Brand-specific engine class in Core
class AmazonShoppingEngine implements WebSearchInterface { ... }  // in DomainsAI/

❌ PROHIBITED: Brand-specific mode/site checks in Core
if ($targetSite === 'amazon.fr') return ['mode_d_amazon_shopping', ...];

✅ REQUIRED: Domain App registers via WebSearchEngineRegistry
// Apps/AI/Ecommerce/.../WebSearch/Registration/WebSearchRegistration.php
final class WebSearchRegistration {
  public static function register(WebSearchEngineRegistry $r): void {
    $r->registerProvider(new AmazonShoppingProvider());
    $r->registerSiteRouter(new AmazonSiteRouter());
  }
}
```

Component file locations (single template, used by every domain):

```
Apps/AI/{Domain}/Classes/ClicShoppingAdmin/WebSearch/
├── Engines/{Brand}Engine.php          implements WebSearchInterface
├── Providers/{Brand}Provider.php      implements WebSearchEngineProviderInterface
├── SiteRouters/{Brand}SiteRouter.php  implements SiteRouterInterface  (optional)
└── Registration/WebSearchRegistration.php  — auto-discovered by the registry
```

The Core registry (`Core/ClicShopping/AI/RegistryAI/WebSearchEngineRegistry.php`)
scans `Apps/AI/*` on its first instantiation and invokes every domain's
`WebSearchRegistration::register()`. No file inside `Core/` needs to change when
a new domain (HR, CRM, Finance, Trading, ...) is added.

### Built-in agnostic engines (allowed in Core)

| Mode | Identifier | Engine | Reason it stays in Core |
|------|-----------|--------|--------------------------|
| A | `mode_a_ai_overview` | `GoogleAIOverviewEngine` | Public Google/SerpAPI protocol, no merchant brand |
| B | `mode_b_google_shopping` | `GoogleShoppingEngine` | Public Google/SerpAPI protocol, no merchant brand |
| C | `mode_c_rag_websearch` | `RagWebSearchEngine` | Generic site-filtered Google search |
| E | `mode_e_google_trends` | `GoogleTrendsEngine` | Public Google/SerpAPI protocol |

Mode D is **always domain-registered**. The Ecommerce App ships
`mode_d_amazon_shopping` via `AmazonShoppingProvider`.

### 5.3 — Language prompt files (`Agents/` vs `{domain}/`)

RAG/agent **prompts are language files**, never hardcoded strings (no heredoc or inline prompt
text in classes — always `getDef()`). A third agnostic-vs-domain split applies, mirroring
5.1/5.2:

| Kind | Location | Content |
|------|----------|---------|
| **Agnostic** (any domain) | `ClicShoppingAdmin/Core/languages/{lang}/Agents/rag_*.txt` | Instructions/logic with NO domain entities, schema or few-shot examples (critic selection, weight bounds, anomaly detection, …) |
| **Domain-specific** | `ClicShoppingAdmin/Core/languages/{lang}/{domain}/rag_*.txt` (e.g. `ecommerce/`) | NL-to-SQL with the real schema, entity extraction, domain few-shot examples |

**Decision rule**: if a prompt would read identically for Finance/HR/CRM (only generic
instructions), it belongs in `Agents/`. If it embeds the domain's entities/schema/examples it
stays in `{domain}/`. When a prompt is *mostly* agnostic with a few domain examples, split the
agnostic skeleton into `Agents/` and keep only the examples in `{domain}/`.

**Goal**: adding a domain (e.g. `finance/`) must require providing **only** the domain-specific
prompts; the agnostic ones in `Agents/` are inherited without recopy.

Loading — use the symmetric `DomainConfig` helpers:

```php
// domain prompt (derives {domain} from CLICSHOPPING_APP_CHATGPT_RA_ACTIVITIES → ecommerce/)
DomainConfig::loadLanguageFile('rag_analytics_agent');
// agnostic prompt — loads the Agents/ group (prefixes 'Agents/' instead of '{domain}/')
DomainConfig::loadAgnosticLanguageFile('rag_adaptive_weighting');
```

`loadAgnosticLanguageFile()` is the counterpart of `loadLanguageFile()` and is the API for any
agnostic file (it centralizes the `'Agents/'` literal; equivalent to a direct
`$this->language->loadDefinitions('Agents/<file>', 'en', null, 'ClicShoppingAdmin')`).

**Mixed file (skeleton + domain examples)** — a class that needs both layers loads them with
two calls, so every definition key (and its variables) ends up available together:

```php
DomainConfig::loadAgnosticLanguageFile('rag_xxx_skeleton'); // agnostic instructions/logic
DomainConfig::loadLanguageFile('rag_xxx_examples');         // domain entities/schema/few-shot
```

`'Agents'` is not a registered site, so `Language::loadDefinitions()` does not strip it as a
site prefix → it resolves to `…/languages/{lang}/Agents/{file}.txt`.

**Interpolation**: `getDef('text_key', ['var' => $value])` substitutes `{{var}}` placeholders
in the value (prefer over `sprintf %s` for multi-variable lines). Prompts load in English by
design (internal, not user-facing) but EN **and** FR must both be translated.

⚠️ Editing these `.txt` requires a cache + DB purge — see **ARCHITECTURE.md §7.1/§7.2** for the
parser rules (`=` handling, single space before `=`) and the mandatory purge procedure.

**Reference implementation**: `…/SubActorCritic/WeightingEngine/LLMPromptBuilder.php` is fully
externalized into `Agents/rag_adaptive_weighting.txt` (49 keys, EN+FR).

---

## 6. Pattern Organization

Patterns are **DEPRECATED** and serve as **FALLBACK ONLY** when LLM fails or is unavailable.
The PRIMARY method is **Pure LLM Mode** via LLPhant.

### Pattern Status

| Pattern Class | Location | Type | Status | Purpose |
|---|---|---|---|---|
| `IntentDetectionPatterns` | `DomainsAI/WebSearch/Patterns/` | Agnostic | @deprecated (fallback) | Intent routing when LLM fails |
| `WebSearchPatterns` | `DomainsAI/WebSearch/Patterns/` | Agnostic | @deprecated (fallback) | Generic web search keywords |
| `WebSearchPostFilter` | `DomainsAI/WebSearch/Patterns/` | Agnostic | @deprecated (fallback) | Post-filtering patterns |
| `WebSearchPatterns` | `Apps/AI/Ecommerce/.../Patterns/` | Domain-specific | Active | Price extraction & comparison |
| `AnalyticsPatterns` | `Apps/AI/Ecommerce/.../Patterns/` | Domain-specific | Active | Ecommerce analytics patterns |
| `GuardrailsPattern` | `Apps/AI/Ecommerce/.../Patterns/` | Domain-specific | Active | Table filtering for security |

> There are **two different `WebSearchPatterns` classes** — they cannot be merged:
> 1. **Agnostic** (`DomainsAI/WebSearch/Patterns/`) — generic keyword patterns
> 2. **Domain-specific** (`Apps/AI/Ecommerce/.../Patterns/`) — price extraction (Ecommerce logic)

### Pattern Directory Rules

```
Core/ClicShopping/AI/DomainsAI/{Domain}/Patterns/   ← AGNOSTIC LAYER
  Purpose:  Generic patterns reusable across ALL domains
  Rules:    English-only, no domain-specific logic, @deprecated

Core/ClicShopping/Apps/AI/{Domain}/Classes/.../Patterns/   ← DOMAIN-SPECIFIC LAYER
  Purpose:  Business logic specific to one domain
  Rules:    Can contain domain-specific logic and dependencies
```

```
PRIMARY METHOD: LLM via LLPhant (Pure LLM Mode)
├── Intent detection: IntentRouter with LLPhant
├── Classification: ClassificationEngine with LLPhant
├── Analysis: UnifiedQueryAnalyzer with LLPhant
└── Entity extraction: LLM-based extraction

FALLBACK ONLY: Pattern-based detection
├── Used ONLY when LLM fails or is unavailable
├── Lower confidence scores (0.5–0.7 vs 0.8–0.95 for LLM)
├── Logged as fallback for monitoring
└── Scheduled for removal when LLM reliability reaches 99.9%
```

### Pattern Development Checklist

```
[ ] Determine if pattern is agnostic or domain-specific
[ ] Place in correct directory (DomainsAI/ vs Apps/AI/{Domain}/)
[ ] English-only keywords for internal processing
[ ] Mark agnostic patterns as @deprecated (Pure LLM Mode)
[ ] Document fallback-only nature in comments
[ ] No domain-specific logic in agnostic patterns
[ ] Test pattern works across multiple domains (if agnostic)
```

---

## 7. References

| Subject | File |
|---|---|
| Agent operational rules | `AGENTS.md` |
| AI concepts, RAG, LLM, memory, embeddings | `AI_SYSTEM.md` |
| Framework architecture, hooks, registry | `ARCHITECTURE.md` |
| Database, SQL, vector tables | `DATABASE.md` |
| Security, guardrails, GDPR | `SECURITY.md` |
| DeepWiki AI | https://deepwiki.com/ClicShopping/ClicShopping/5-ai-integration-and-rag-system |
