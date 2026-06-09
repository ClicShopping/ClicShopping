# AI_SYSTEM.md — ClicShopping AI v4.30+


---

## 1. Positioning

The AI system is the **highest architectural priority** component of ClicShopping.
It is designed as an **agnostic layer**: agents, interfaces and pipelines do not depend on
the underlying PHP framework and can evolve independently.

Location: `Core/ClicShopping/AI/`, organized by business domain.

### Core Principles

```
1. Domain-Driven Design  — AI organized by business domain, not by technical layer
2. Multi-LLM Support     — Provider abstraction via LLPhant (OpenAI, Anthropic, Mistral, Ollama…)
3. Agnostic Layer        — Core/ClicShopping/AI/ has minimal dependencies on the framework
4. Multi-Agent System    — Orchestration, reasoning, validation, correction agents
5. Hybrid RAG            — Analytics (SQL), Semantic (vectors), Web search, combined modes
6. Pure LLM Mode         — LLM is the primary method; patterns are fallback only
```

### Database Paradigm

```
Core/ClicShopping/AI/ → Doctrine ORM ONLY  (agnostic layer)
Everywhere else       → Registry::get('Db') ONLY (framework layer)

NEVER mix both paradigms in the same file.
```

See `DATABASE.md` §2 for the complete rule, rationale, and code examples.

---

## 2. Hybrid RAG Architecture

The system combines three retrieval modes based on detected intent:

| Mode | Mechanism | Example Query |
|---|---|---|
| **Analytics** | Natural language → Generated SQL | "What are the top 10 products by revenue?" |
| **Semantic** | Vector search `VECTOR(3072)` | "Find products similar to this one" |
| **Web** | External search | "Latest trends in e-commerce 2026" |
| **Hybrid** | Weighted combination | "Compare our sales with market trends" |

Mode is automatically selected by `QueryClassifier` before execution.

---

## 3. Multi-Agent Architecture

### Agents and Responsibilities

| Component | Type | Role |
|---|---|---|
| `OrchestratorAgent` | Senior Agent | Task decomposition, inter-agent coordination, response synthesis |
| `ReasoningAgent` | Specialized agent | Multi-step reasoning (CoT / ToT / self-consistency) |
| `CorrectionAgent` | Specialized agent | Error detection/correction and response improvement |
| `ValidationAgent` | Specialized agent | Output validation and consistency |
| `AnalyticsAgent` | Domain Agent | SQL generation/execution from natural language |
| `SemanticAgent` | Domain Agent | Semantic search on vector tables |
| `WebSearchTool` + `WebSearchQueryExecutor` | Domain tools | External data retrieval |
| `QueryClassifier` | Classifier | Intent analysis and RAG mode routing |
| `MemoryRetentionService` | Memory service | Short/medium/long-term memory management |

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

CorrectionAgent / ValidationAgent (Specialized)
└── Error detection, improvement, consistency checks

Domain Agents (Business)
├── AnalyticsAgent → SQL generation from natural language
├── SemanticAgent  → Vector search on embeddings
├── SEOAgent       → Content optimization
└── ...            → Other domain-specific agents
```

### Agent Registration

For directory locations, registration rules, and the Orchestrator invocation pattern,
see **`AI_ARCHITECTURE.md` §2**. Summary:

- Core agents → `Core/ClicShopping/AI/CoreAI/`
- Domain agents → `Core/ClicShopping/Apps/AI/{Domain}/`
- All agents implement `ActorAgentInterface`
- All inter-agent calls go through the Orchestrator — no direct instantiation

---

## 4. Reasoning

Reasoning is implemented in `ReasoningAgent` with three configurable modes:
- `chain_of_thought`
- `tree_of_thought`
- `self_consistency`

These modes are orchestrated by `OrchestratorAgent` depending on request context.

> If `ReasoningInterface` is not yet present in the codebase, the Agent MUST implement
> reasoning logic internally using CoT before outputting PHP code.

---

## 5. LLM Providers

Abstraction via **LLPhant** — never call LLM APIs directly.

### Supported Providers

| Provider | Configuration Constant | Models |
|---|---|---|
| **OpenAI** | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY` | GPT-5.x, GPT-4.1, GPT-4o |
| **Anthropic** | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY_ANTHROPIC` | Claude Sonnet 3.5, Opus, Haiku |
| **Mistral** | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY_MISTRAL` | Mistral Large Latest |
| **Google Gemini** | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY_GEMINI` | Gemini 2.5 Flash (planned) |
| **VoyageAI** | `CLICSHOPPING_APP_CHATGPT_RA_API_KEY_VOYAGE_AI` | Voyage embeddings |
| **LM Studio** | `CLICSHOPPING_APP_CHATGPT_LMSTUDIO_URL` | GPT-OSS, Qwen3, Phi-4 |
| **Ollama** | Local server (port 11434) | Mistral, Llama, etc. |

The administrator selects one provider for the whole system via the admin interface.
A second provider can be used in specific cases — see `Gpt::getGptResponse()` for customization.

### Facade Class

**Location**: `Core/ClicShopping/Apps/Configuration/ChatGpt/Classes/ClicShoppingAdmin/Gpt.php`

The `Gpt` class is the main facade managing all LLM interactions. It delegates to:
- `ConfigManager` — Configuration and status
- `ModelManager` — Model information and selection
- `ProviderManager` — Provider initialization
- `ResponseProcessor` — Response generation and processing
- `DataManager` — Data persistence and analytics
- `UIGenerator` — UI component generation

```php
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

// Main method — handles all providers automatically
$response = Gpt::getGptResponse(
    $question,    // Prompt
    $maxtoken,    // Max tokens (optional)
    $temperature, // Temperature (optional)
    $engine,      // Model name (optional, uses default if null)
    $max          // Max responses (optional, default: 1)
);

// Check GPT status
if (Gpt::checkGptStatus()) { /* GPT is enabled and configured */ }

// Get available models
$models = Gpt::getGptModel();

// Get model context length
$contextLength = Gpt::getModelContextLength('gpt-4.1-mini'); // 64000
```

Rules:
```
✓ Always use Gpt::getGptResponse() for LLM calls
✓ API keys via configuration constants only
✓ Provider selection configured via admin interface
✓ LLPhant abstraction handles all provider differences

✗ Never call LLM APIs directly (OpenAI, Anthropic, etc.)
✗ Never hardcode API keys in code
✗ Never bypass LLPhant abstraction
✗ Never use models below 16K context for RAG BI
```

---

## 6. Memory System

Three-tier memory architecture managed by `MemoryRetentionService`:

| Tier | Scope | Storage | Use Case |
|---|---|---|---|
| **Short-term** | Current conversation | Session / Redis | Context continuity |
| **Medium-term** | User session history | Database | Personalization |
| **Long-term** | System knowledge | Vector embeddings | Pattern learning |

Do not create alternative memory mechanisms.

---

## 7. Embedding Pipeline

```
Entity (Product, Category, etc.)
→ Text Extraction (content + metadata)
→ Chunking (128 tokens per chunk)
→ Embedding Generation (via LLPhant + provider)
→ Vector Storage (VECTOR(3072) in MariaDB 11.7+)
→ Vector Index (native MariaDB VECTOR INDEX)
```

> **Data transformation to VECTOR(3072) is strictly delegated to LLPhant.**
> The Agent MUST NOT attempt to generate vector arrays manually in PHP.

### Embedding Tables

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

### Common Table Structure

| Column | Type | Role |
|---|---|---|
| `embedding` | `VECTOR(3072)` | Semantic vector |
| `entity_id` | INT | FK to source table |
| `content` | TEXT | Original indexed text |
| `metadata` | JSON | Contextual enrichment (v4.11+) |
| `chunknumber` | INT | Sequential chunk index (128 tokens/chunk) |
| `date_modified` | DATETIME | Last updated timestamp |

Pipeline rules:
- Embedding generation managed by **existing crons** — do not recreate this mechanism
- Vector dimensions fixed at 3072 — do not modify without human coder agreement
- MariaDB ≥ 11.7 required for native `VECTOR INDEX`
- Do not modify embedding table structure without agreement

---

## 8. AI Security — Guardrails

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

Rate limiting constants:

| Constant | Value | Role |
|---|---|---|
| `CLICSHOPPING_APP_API_AI_RATE_LIMIT_WINDOW` | 900s | Time window |
| `CLICSHOPPING_APP_API_AI_MAX_REQUEST_PER_WINDOW` | 20 | Max queries per identifier |
| `CLICSHOPPING_APP_API_AI_MAX_LOGIN_ATTEMPTS` | 5 | Attempts before lock |
| `CLICSHOPPING_APP_API_AI_ACCOUNT_LOCK_DURATION` | 1800s | Lockdown duration |

Related tables:
- `clic_api_rate_limit` — tracking requests by identifier + timestamp
- `clic_api_failed_attempts` — tracking failed attempts
- `clic_rag_security_events` — auditing AI security events

---

## 9. MCP — Model Context Protocol

ClicShopping exposes an MCP server for agentic commerce (port 3001 by default, configurable via admin).

Roles:
- Health monitoring of the AI system
- Performance metrics (latency, error rate, uptime)
- Integration with external agents via standardized MCP protocol

Storage table: `clic_mcp_performance_history`

Do not modify the MCP protocol without human coder agreement.

---

## 10. AI Development Checklist

```
[ ] LLM calls via Gpt::getGptResponse() / LLPhant only — no direct API calls
[ ] Agent registered in Core/ClicShopping/AI/CoreAI/ or Apps/AI/{Domain}/
[ ] Agent implements ActorAgentInterface
[ ] DB access: Doctrine ORM inside AI/ — Registry::get('Db') outside AI/
[ ] API keys via configuration constants — never hardcoded
[ ] Constant name verified against existing constants (see AGENTS.md naming convention)
[ ] Guardrails not bypassed
[ ] Provider selection configurable via admin
[ ] Memory management via MemoryRetentionService
[ ] Embeddings via existing pipeline — no custom generation
[ ] Security events logged to clic_rag_security_events
[ ] Validators used for business logic (DomainsAI/Analytics/Validator/)
[ ] Wrappers remain minimal (CoreAI/Orchestrator/SubActorCritic/Critics/)
[ ] Vector transformation delegated to LLPhant — no manual array generation
```

---

## 11. References

| Subject | File |
|---|---|
| Agent operational rules | `AGENTS.md` |
| Directory structure, orchestrator, patterns | `AI_ARCHITECTURE.md` |
| DB paradigm and code examples | `DATABASE.md` §2 |
| Vector database and embeddings schema | `DATABASE.md` §6 |
| AI security and guardrails | `SECURITY.md` §5 |
| Framework architecture | `ARCHITECTURE.md` |
| DeepWiki AI | https://deepwiki.com/ClicShopping/ClicShopping/5-ai-integration-and-rag-system |
