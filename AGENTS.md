# AGENTS.md — ClicShopping AI v4.20+

This repository contains **ClicShopping AI™** — a powerful, open-source e-commerce platform
designed for B2B, B2C, and B2B-B2C businesses, enhanced with advanced Agentic and Generative AI capabilities.

> **This file defines the operational rules for all AI agents.**
> Technical details live in the dedicated sub-files listed below.

---

## Product Overview

- Modern e-commerce platform with AI-powered features (GPT, Ollama, Anthropic)
- RAG-powered Business Intelligence: vector embeddings, semantic search, NL-to-SQL
- MCP Server for agentic e-commerce approach
- Multi-currency, multi-language, GDPR compliance, AES encryption, 2FA
- This project **is not** osCommerce nor a legacy ClicShopping V2/V3 fork.

---

## Reference Documentation

All technical documentation lives in the `Agents/` directory.
Seven files — all must be checked for the Self-Correction Protocol.

| File | Content |
|---|---|
| `Agents/ARCHITECTURE.md` | Bootstrap, routing, Registry, hooks, namespaces, Custom/, cache |
| `Agents/AI_SYSTEM.md` | Agents, RAG, LLM providers, reasoning, memory, embeddings, MCP |
| `Agents/AI_ARCHITECTURE.md` | AI directory structure, OrchestratorAgent components, domain-agnostic patterns |
| `Agents/DATABASE.md` | MariaDB 11.7+, SQL schema, SQL file routing, migrations |
| `Agents/SECURITY.md` | 10 security layers, AI guardrails, rate limiting, GDPR |
| `Agents/TEMPLATES.md` | Front-office vs back-office rendering, helpers, SEO, i18n |

---

## Agent Workflow — Mandatory Steps

```
BEFORE ANY CODE GENERATION:
1. Constraint Check: compare your plan against all PROHIBITED sections
   in AGENTS.md, DATABASE.md (PARADIGM SHIFT), SECURITY.md, and ARCHITECTURE.md.
   Auto-correct your plan if a violation is found before writing any code.
2. Read existing files in the target scope — never assume structure.
3. Check hooks before any other approach (see Scalability Priority Order below).

BEFORE DELIVERY:
Self-Correction Protocol: verify output against the Absolute Prohibitions list
in ALL SEVEN .md files. If a conflict is found, prioritize AGENTS.md and SECURITY.md.
```

---

## Scalability Priority Order

```
1. Existing Hook         → always check first → Core/ClicShopping/Apps/*/Module/Hooks
2. Module                → Core/ClicShopping/Apps/*/Module/
3. New App               → Core/ClicShopping/Apps/{Vendor}/{AppName}/
4. Core/ClicShopping/Custom/ → override OM/, Apps, or Sites without modifying them directly
5. Core/ClicShopping/OM/ direct → PROHIBITED without human coder agreement
6. If you cannot list the directory content in real-time, YOU MUST ASK the user
   to provide the file list of Core/ClicShopping/Apps/ or Custom/ before
   suggesting any file creation, to avoid path collisions.
```

---

## Stack — Critical Constraints

| Constraint | Rule |
|---|---|
| **PHP** | ≥ 8.4 — `public private(set)` on critical service properties |
| **MariaDB** | ≥ 11.7 — MySQL incompatible with `VECTOR(3072)` |
| **LLPhant** | Only access layer to LLMs — no direct API call. Check version in `composer.json` |
| **Autoload** | `CLICSHOPPING::autoload` + Composer vendor (`Core/ClicShopping/External/vendor`) — no alternative |
| **DB in `AI/`** | `Core/ClicShopping/AI/` → Doctrine ORM only |
| **DB elsewhere** | `Registry::get('Db')` only — NEVER mix both paradigms in the same file |
| **Sessions** | 4 backends with automatic fallback (Database, File, Memcached, Redis) |
| **Cache** | 5-tier architecture (OpCache, Static, Memcached, Redis, APCu) |

For AI-specific rules (LLPhant, agents, RAG, guardrails) → `AI_SYSTEM.md`.
For DB paradigm details and code examples → `DATABASE.md`.
For cache and session details → `ARCHITECTURE.md`.

---

## Language & Code Standards

```
- All comments inside Core/ClicShopping/AI/ classes must be in English
- All class comments must respect PSR standardization
- No visible hardcoded string in PHP or templates — always use getDef('')
- Minimum language compatibility: EN + FR
```

---

## Configuration Constants — Naming Convention

All configuration constants follow the pattern:

```
CLICSHOPPING_APP_{VENDOR}_{APP}_{SCOPE}_{KEY}
```

Examples:
- `CLICSHOPPING_APP_CHATGPT_CH_API_KEY` — ChatGpt app, channel scope, API key
- `CLICSHOPPING_APP_CHATGPT_RA_API_KEY_VOYAGE_AI` — ChatGpt app, RAG scope, VoyageAI key
- `CLICSHOPPING_APP_API_AI_RATE_LIMIT_WINDOW` — API/AI app, rate limit window

Rule: never invent a constant name. Verify existing constants in the App's
configuration files before creating a new one.

---
## Absolute Prohibitions

```
✗ Generating code without reading existing files in the target scope
✗ Assuming a structure without verification in the actual repository
✗ Modifying Core/ClicShopping/OM/ without human coder consent
✗ Modifying Core/ClicShopping/Work/ or Core/ClicShopping/External/vendor/
✗ Writing application SQL in sql_upgrade/ (documentation only — not executed)
✗ Business logic or DB access in templates (see TEMPLATES.md)
✗ Hardcoded channel identifiers (B2B/B2C must be dynamic, never hardcoded)
✗ Hardcoded API keys or LLM provider selection
✗ Direct LLM API calls without LLPhant abstraction
✗ Direct agent instantiation from business code (use Orchestrator)
✗ Bypassing AI guardrails (prompt injection detection, rate limiting)
✗ Direct PDO connection outside Registry::get('Db')
  (exception: Core/ClicShopping/AI/ uses Doctrine ORM)
✗ Mixing Doctrine ORM and Registry Db in the same file
✗ Modifying embedding table structure or vector dimensions without agreement
✗ Creating alternative memory, agent registry, or embeddings pipeline
✗ MySQL 9.x (incompatible with VECTOR type)
✗ MariaDB < 11.7 (missing native VECTOR support)
✗ Creating alternative autoload, DI container, or cache mechanism
✗ Using pattern-based detection as PRIMARY method (Pure LLM Mode required)
✗ Placing domain-specific patterns in the agnostic layer (DomainsAI/)
✗ Placing agnostic patterns in domain-specific layer (Apps/AI/{Domain}/)
✗ Mixing multilingual keywords in agnostic pattern internal processing (English-only)
✗ Legacy osCommerce / ClicShopping V2/V3 patterns
✗ Inventing configuration constant names — always verify existing constants first
```

---

## External References

- Wiki: https://github.com/ClicShopping/ClicShopping/wiki
- DeepWiki: https://deepwiki.com/ClicShopping/ClicShopping
- Forum: https://www.clicshopping.org
- Documentation: docs/documentation.md (root of the application)
