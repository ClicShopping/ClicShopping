# AGENTS.md — ClicShopping AI v4.20+

This repository contains **ClicShopping AI™** - a powerful, open-source e-commerce platform designed for B2B, B2C, and B2B-B2C businesses, enhanced with advanced Agentic and Generative AI capabilities.

# Product

## Purpose
- Modern e-commerce platform with AI-powered features
- Integration with GPT, Ollama, and Anthropic AI models
- RAG-powered Business Intelligence for data analysis and insights
- Comprehensive testing and development environment

## Key AI Features
- **Generative AI Integration**: GPT API, Ollama, and Anthropic ... support
- **RAG Business Intelligence**: Vector embeddings, semantic search, Web search, Hybridation, natural language to SQL
- **AI-Powered Content**: Product descriptions, SEO optimization, review analysis ...
- **Predictive Analytics**: Real-time safety stock prediction
- **Product Cocpit Ai Analysis**: Marketing product analysis
- **MCP Server**: Model Context Protocol for agentic e-commerce approach

## Core E-commerce Features
- Multi-template responsive design with Bootstrap 5.x
- Dynamic pricing with customer groups and special prices
- Multi-currency and multi-language support
- Advanced security with GDPR compliance, AES encryption, 2FA
- Performance optimization with OpCache, Memcached, Redis

## Target Audience
Shop owner, E-commerce developers, AI engineers, and businesses looking to integrate advanced AI capabilities into their online stores.
This project **is not** osCommerce nor a legacy ClicShopping V2/V3 fork.

---

## Absolute rule

```
CRITICAL : Before any code generation, you MUST run a 'Constraint Check' phase: compare your plan against the PROHIBITED sections of AGENTS.md and the PARADIGM SHIFT of DATABASE.md. If a violation is found, auto-correct your plan before writing code.
PROHIBITED: Generating code without reading existing files in the target scope.
PROHIBITED: assuming a structure without verification in the actual repository.
PROHIBITED: Change the core system OM directory without human permission.
IMPORTANT: Self-Correction Protocol: Before delivering any code, the Agent MUST verify its output against the Absolute Prohibitions list of ALL six .md files. If a conflict is found, the Agent MUST prioritize AGENTS.md and SECURITY.md
IMPORTANT : All the process in AI directory are in english. 
```

Before any code: read existing files → check hooks → minimal modification.

---

## Reference documentation

All technical documentation is in the `Agents/` directory.

| File | Content |
|---|---|
| `Agents/ARCHITECTURE.md` | Bootstrap, routing, Registry, hooks, namespaces, Custom/, cache |
| `Agents/AI_SYSTEM.md` | Agents, RAG, LLM providers, reasoning, embeddings, MCP (detailed) |
| `Agents/DATABASE.md` | MariaDB 11.7+, SQL schema, SQL file routing, migrations |
| `Agents/SECURITY.md` | 10 security layers, AI guardrails, rate limiting, GDPR |
| `Agents/TEMPLATES.md` | Front-office vs back-office rendering, helpers, SEO, i18n |

**Quick AI Overview:** See "AI Architecture — Multi-Domain, Multi-LLM, Agnostic" section below for core principles.
**Complete AI Details:** See `Agents/AI_SYSTEM.md` for full technical specifications.

---

## AI Architecture — Multi-Domain, Multi-LLM, Agnostic

### Philosophy

The AI system is the **highest architectural priority** and is designed as a **framework-agnostic layer**.
It can evolve independently of the PHP framework and be reused in other contexts.

### Core Principles

```
1. Domain-Driven Design — AI organized by business domain, not by technical layer
2. Multi-LLM Support — Provider abstraction via LLPhant (OpenAI, Anthropic, Mistral, Ollama, etc.)
3. Agnostic Layer — Core/ClicShopping/AI/ has minimal dependencies on the framework
4. Multi-Agent System — Orchestration, reasoning, validation, correction agents
5. Hybrid RAG — Analytics (SQL), Semantic (vectors), Web search, combined modes
```

### Location and Organization

```
Core/ClicShopping/AI/
├── Agents/ ← Core agents (Orchestrator, Reasoning, Correction, Validation)
├── Services/ ← AI services (Memory, Classification, Embeddings)
├── Tools/ ← Domain tools (WebSearch, Analytics, Semantic)
├── InterfacesAI/ ← Contracts (ActorAgentInterface, etc.)
└── Config/ ← AI configuration

do not change this directory without permission : Core/ClicShopping/AI/

Core/ClicShopping/Apps/AI/{DomainName}/ ← Domain-specific agents
├── SEO/ ← SEO optimization agent
├── Analytics/ ← Business analytics agent
└── ... ← Other domain agents
```

### Multi-LLM Provider Support

The system supports multiple LLM providers simultaneously via **LLPhant abstraction**:

| Provider | Use Case | Configuration |
|---|---|---|
| **OpenAI (GPT-4/5)** | Generation, reasoning, embeddings | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY` |
| **Anthropic (Claude)** | Generation, reasoning | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY_ANTHROPIC` |
| **Mistral** | Generation | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY_MISTRAL` |
| **VoyageAI** | Embeddings only | `CLICSHOPPING_APP_CHATGPT_RA_API_KEY_VOYAGE_AI` |
| **Ollama / LmStudio** | Local/Private LLM | Local server configuration |

**Rules:**
- NEVER call LLM APIs directly — always via LLPhant
- Provider selection is configured via admin interface, not hardcoded
- API keys via configuration constants only
- Support for multiple providers in parallel (e.g., OpenAI for generation, VoyageAI for embeddings)
- For the languages, it's important to use the function getDef('');
- All the comment inside the classes must be in English and respect the PSR standardization.
- 
### Multi-Agent Architecture

```
OrchestratorAgent (Senior)
├── Task decomposition
├── Inter-agent coordination
├── Context management
└── Response synthesis

ReasoningAgent (Specialized)
├── Chain of Thought (CoT)
├── Tree of Thought (ToT)
└── Self-consistency modes

CorrectionAgent (Specialized)
├── Error detection
├── Response improvement
└── Pattern learning

ValidationAgent (Specialized)
├── Output validation
└── Consistency checks

Domain Agents (Business)
├── AnalyticsAgent → SQL generation from natural language
├── SemanticAgent → Vector search on embeddings
├── SEOAgent → Content optimization
└── ... → Other domain-specific agents
```

### Hybrid RAG Modes

The system automatically selects the best retrieval mode based on query intent:

| Mode | Mechanism | Example Query |
|---|---|---|
| **Analytics** | NL → SQL generation | "What are the top 10 products by revenue?" |
| **Semantic** | Vector search (3072 dims) | "Find products similar to this one" |
| **Web** | External search | "Latest trends in e-commerce 2026" |
| **Hybrid** | Weighted combination | "Compare our sales with market trends" |

Selection is handled by `QueryClassifier` before execution.

### Database Paradigm — Critical Distinction

```
Core/ClicShopping/AI/ → Doctrine ORM ONLY (agnostic layer)
Everywhere else → Registry::get('Db') ONLY (framework layer)

NEVER mix both paradigms in the same file.
```

This separation ensures the AI layer remains portable and framework-independent.

### Agent Registration and Invocation

```php
// ✓ Correct — via Orchestrator
$orchestrator = new OrchestratorAgent();
$result = $orchestrator->execute($task);

// ✗ Prohibited — direct instantiation from Apps
$agent = new AnalyticsAgent();
$result = $agent->execute($query);
```

**Rules:**
- Core agents register in `Core/ClicShopping/AI/`
- Domain agents register in `Core/ClicShopping/Apps/AI/{Domain}/`
- All agents implement `ActorAgentInterface`
- Inter-agent communication via Orchestrator only
- No direct agent instantiation from business code

### AI Security (Guardrails)

Every LLM interaction passes through security layers:

```
User Input
→ Prompt Injection Detection (pattern scanning)
→ Obfuscation Detection (encoding, homoglyphs)
→ Threat Scoring (threshold validation)
→ Rate Limiting (900s window, 20 requests max)
→ LLM Processing (via LLPhant)
→ Output Validation (ValidationAgent)
→ Audit Logging (clic_rag_security_events)
```

**NEVER bypass guardrails**, even for testing. Use dedicated staging environments.

### Memory System

Three-tier memory architecture:

| Tier | Scope | Storage | Use Case |
|---|---|---|---|
| **Short-term** | Current conversation | Session/Redis | Context continuity |
| **Medium-term** | User session history | Database | Personalization |
| **Long-term** | System knowledge | Vector embeddings | Pattern learning |

Managed by `MemoryRetentionService` — do not create alternative memory mechanisms.

### Embeddings Pipeline

```
Entity (Product, Category, etc.)
→ Text Extraction (content + metadata)
→ Chunking (128 tokens per chunk)
→ Embedding Generation (via LLPhant + provider)
→ Vector Storage (VECTOR(3072) in MariaDB 11.7+)
→ Vector Index (native MariaDB VECTOR INDEX)
```

**11 embedding tables** for different entities (products, categories, reviews, etc.)
See `AI_SYSTEM.md` §6 and `DATABASE.md` §6 for complete list.

### AI Development Checklist

```
[ ] LLM calls via LLPhant only — no direct API calls
[ ] Agent registered in Core/ClicShopping/AI/ or Apps/AI/{Domain}/
[ ] Agent implements ActorAgentInterface
[ ] Database access via Doctrine ORM (inside AI/) or Registry (outside AI/)
[ ] API keys via configuration constants — never hardcoded
[ ] Guardrails not bypassed
[ ] Provider selection configurable via admin
[ ] Memory management via MemoryRetentionService
[ ] Embeddings via existing pipeline — no custom generation
[ ] Security events logged to clic_rag_security_events
```

### References

- Complete AI architecture: `AI_SYSTEM.md`
- Vector database and embeddings: `DATABASE.md` §6
- AI security and guardrails: `SECURITY.md` §5
- DeepWiki AI: https://deepwiki.com/ClicShopping/ClicShopping/5-ai-integration-and-rag-system

---

## Scalability Priority Order

```
1. Existing Hook → always check first → Core/ClicShopping/Apps/*/Module/Hooks
2. Module → Core/ClicShopping/Apps/*/Module/
3. New App → Core/ClicShopping/Apps/{Vendor}/{AppName}/
4. Core/ClicShopping/Custom/ → override core without modifying Core/ClicShopping/OM/
5. Core/ClicShopping/OM/ direct → PROHIBITED without agreement from the human coder
6. IF you cannot list the directory content in real-time, YOU MUST ASK the user to provide the file list of Core/ClicShopping/Apps/ or Custom/ before suggesting any file creation to avoid path collisions
```

---

## Stack — critical constraints

- **PHP ≥ 8.4** — `public private(set)` on critical service properties
- **MariaDB ≥ 11.7** — MySQL incompatible with `VECTOR(3072)`
- **LLPhant** — only access layer to LLMs, no direct API call. for version look composer.json inside the root
- **Autoload**: `CLICSHOPPING::autoload` + Composer vendor (`Core/ClicShopping/External/vendor`) — no alternative autoload mechanism. use composer.json in the root
- **Database Access**:
  - `Core/ClicShopping/AI/` → Doctrine ORM only (agnostic layer)
  - Everywhere else → `Registry::get('Db')` only
  - NEVER mix both paradigms in the same file
- **Sessions**: 4 backends with automatic fallback (Database, File, Memcached, Redis) — see ARCHITECTURE.md §10
- **Cache**: 5-tier architecture (OpCache, Static, Memcached, Redis, APCu) — see ARCHITECTURE.md §9

---

## Absolute prohibitions

```
✗ Legacy osCommerce / ClicShopping V2/V3
✗ Modify Core/ClicShopping/OM/ without the human coder consent. Core of the application
✗ Modify Core/ClicShopping/Work/ or Core/ClicShopping/External/vendor/
✗ Application SQL in sql_upgrade/ - just the sql must be updated for the user (migration)
✗ Business logic or DB access in templates (see TEMPLATES.md)
✗ Hardcoded channel identifiers (B2B/B2C must be dynamic, never hardcoded)
✗ Hardcoded API keys or LLM provider selection
✗ Direct LLM API calls without LLPhant abstraction
✗ Direct agent instantiation from business code (use Orchestrator)
✗ Bypass AI guardrails (prompt injection detection, rate limiting)
✗ Direct PDO connection outside Registry::get('Db') (except Core/ClicShopping/AI/ which uses Doctrine)
✗ Mix Doctrine ORM and Registry Db in the same file
✗ Modify embedding table structure or vector dimensions without agreement
✗ Create alternative memory, agent registry, or embeddings pipeline
✗ MySQL 9.x (incompatible with VECTOR type)
✗ MariaDB < 11.7 (missing native VECTOR support)
✗ Create alternative autoload, DI container, or cache mechanism
✗ Use pattern-based detection as PRIMARY method (Pure LLM Mode required)
✗ Place domain-specific patterns in agnostic layer (DomainsAI/)
✗ Place agnostic patterns in domain-specific layer (Apps/AI/{Domain}/)
✗ Mix multilingual keywords in agnostic pattern internal processing (English-only)
```

---

## External references

- Wiki: https://github.com/ClicShopping/ClicShopping/wiki
- DeepWiki: https://deepwiki.com/ClicShopping/ClicShopping
- Forum: https://www.clicshopping.org
- Documentation: docs/documentation.md - root of the application