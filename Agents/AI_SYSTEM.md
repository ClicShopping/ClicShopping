# AI_SYSTEM.md — ClicShopping AI v4.20+

> PHP framework agnostic AI system.
> Agent operational rules: `AGENTS.md` — Core architecture: `ARCHITECTURE.md`
> **Migration Notice (May 2026)**: The `Agents/` directory has been renamed to `CoreAI/` to establish consistent naming convention with the `*AI` suffix pattern (InterfacesAI, DomainsAI, RegistryAI, CoreAI). References to "Agents/" in this document now refer to `CoreAI/`. The old `Agents/` namespace is deprecated and will be removed in version 5.0. See `MIGRATION_GUIDE_COREAI.md` for details.
> **Validator Architecture (May 2026)**: Analytics validation logic extracted from Critics into `DomainsAI/Analytics/Validator/` to separate business logic from Actor-Critic infrastructure. Wrappers in `CoreAI/Orchestrator/SubActorCritic/Critics/` delegate to validators.

---

## 1. Positioning

The AI system is the **highest architectural priority** component of ClicShopping.
It is designed as an **agnostic** layer: its agents, interfaces and pipelines do not
do not depend on the underlying PHP framework and can evolve independently.

Location: `Core/ClicShopping/AI/`, organized by business domain.


### Directory organization
```
Core/ClicShopping/AI/
├── CoreAI/              ✅ Infrastructure + Orchestration
│   └── Orchestrator/
│       └── SubActorCritic/
│           └── Critics/  ✅ Minimal wrappers (delegate to validators)
├── Config/              ✅ Configuration
├── Dashboard/           ✅ Monitoring
├── DomainsAI/           ✅ Business domain (Query Types)
│   └── Analytics/
│       └── Validator/    ✅ Business logic validators (NEW May 2026)
├── Handler/             ✅ Handling (Error, Fallback, Query)
├── Helper/              ✅ Utilities
├── Infrastructure/      ✅ Technical Infrastructure
├── InterfacesAI/        ✅ Contrats
├── LoadBalancing/       ✅ Load balancing
├── Rag/                 ✅ RAG Manager
├── RegistryAI/          ✅ Registries (Actor, Critic)
├── Security/            ✅ Sécurity
├── Services/            ✅ Services (ActorCritic, Autonomous)
├── Tools/               ✅ Tools
└── Utils/               ✅ Utilities
```

---

## 2. Hybrid RAG architecture

The system combines three recovery modes depending on the detected intention:

| Type | Mechanism | Use cases |
|---|---|---|
| **Analytics** | Natural language → Generated SQL | KPIs, reports, aggregates |
| **Semantics** | Vector search `VECTOR(3072)` | Product similarity, recommendations |
| **Web** | External search | Real-time data outside the base |
| **Hybrid** | Weighted combination of all three | Complex multi-source queries |

The mode is automatically selected by the `QueryClassifier` before execution.

---
## 3. Multi-agent architecture

### Agents and responsibilities

| Component | Type | Role |
|---|---|---|
| `OrchestratorAgent` | Senior Agent | Task decomposition, inter-agent coordination |
| `ReasoningAgent` | Specialized agent | Multi-step reasoning (CoT / ToT / self-consistency modes) |
| `CorrectionAgent` | Specialized agent | Error detection/correction and response improvement |
| `ValidationAgent` | Specialized agent | Output validation and consistency |
| `AnalyticsAgent` | Domain Agent | SQL generation/execution from natural language |
| `SemanticAgent` | Domain Agent | Semantic search on vector tables |
| `WebSearchTool` + `WebSearchQueryExecutor` | Domain tools | External Data Recovery |
| `QueryClassifier` | Classify | Intent analysis and routing |
| `MemoryRetentionService` | Memory service | Short/medium/long term memory |

### Registration rule

Any new agent must register via the existing mechanism in `Core/ClicShopping/AI/`:
- If it's a core agent for all systems, it must be registered in `Core/ClicShopping/AI/CoreAI/` (formerly `Agents/`)
- If the agent is not "important" it must be implemented in `Core/ClicShopping/Apps/{Vendor}/{AppName}/`. In this case vendor is AI (example: SEO agent)
- Do not create an alternative agent register
- Do not instantiate agents directly from PHP Apps
- Go through the orchestrator for any inter-agent invocation
- Each agent must use the Actor interface for agentic approach `Core/ClicShopping/AI/InterfacesAI/ActorAgentInterface.php`

---

## 4. Validator Architecture (Analytics Domain)

### Purpose

Separates business validation logic from Actor-Critic infrastructure:
- **Business logic**: `DomainsAI/Analytics/Validator/` (testable independently)
- **Infrastructure**: `CoreAI/Orchestrator/SubActorCritic/Critics/` (minimal wrappers)

### Validators

| Validator | Responsibility | Location |
|---|---|---|
| **SqlQualityValidator** | SQL quality (SELECT *, LIMIT, WHERE) | `DomainsAI/Analytics/Validator/` |
| **SqlSecurityValidator** | Security (dangerous patterns, injection) | `DomainsAI/Analytics/Validator/` |
| **SqlPerformanceValidator** | Performance (indexes, joins) | `DomainsAI/Analytics/Validator/` |
| **SchemaValidator** | Schema (tables, columns) | `DomainsAI/Analytics/Validator/` |
| **AnalyticsQualityEvaluator** | Orchestrates all validators | `DomainsAI/Analytics/Validator/` |

### Wrappers (CoreAI)

| Wrapper | Delegates To | Location |
|---|---|---|
| **SqlQualityCriticWrapper** | SqlQualityValidator | `CoreAI/Orchestrator/SubActorCritic/Critics/` |
| **AnalyticsCriticWrapper** | AnalyticsQualityEvaluator | `CoreAI/Orchestrator/SubActorCritic/Critics/` |

### Rules

```
✓ Use validators directly for business logic validation
✓ Use wrappers only within Actor-Critic pattern
✓ Validators have no dependency on CriticAgentInterface
✓ Wrappers contain no business logic (pure delegation)

✗ Do not add business logic to wrappers
✗ Do not couple validators to Actor-Critic infrastructure
✗ Do not bypass validators in wrappers
```

### Example Usage

```php
// Direct validator usage (outside Actor-Critic)
$validator = new SqlQualityValidator();
$result = $validator->evaluateSqlQuality($sql);

// Wrapper usage (within Actor-Critic)
$wrapper = new SqlQualityCriticWrapper($validator);
$evaluation = $wrapper->evaluateAction($actionResult);
```

---

## 5. Reasoning (real state of the core)

The current core does not expose a single `ReasoningInterface` interface.
Reasoning is implemented in `ReasoningAgent` with configurable modes:
- `chain_of_thought`
- `tree_of_thought`
- `self_consistency`

The ReasoningAgent exists as a logic block; however, if ReasoningInterface is not yet present in the codebase, the Agent MUST implement the reasoning logic internally using CoT (Chain of Thought) before outputting PHP code.
These modes are orchestrated by `OrchestratorAgent` and used depending on the request context.

---

## 6. LLM Providers

Abstraction via **LLPhant** — never call LLM APIs directly.

### Supported Providers

| Provider | Configuration constant | Models |
|---|---|---|
| **OpenAI** | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY` | GPT-5.x, GPT-4.1, GPT-4o |
| **Anthropic** | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY_ANTHROPIC` | Claude Sonnet 3.5, Opus, Haiku |
| **Mistral** | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY_MISTRAL` | Mistral Large Latest |
| **Google Gemini** | `CLICSHOPPING_APP_CHATGPT_CH_API_KEY_GEMINI` | Gemini 2.5 Flash (planned) |
| **VoyageAI** | `CLICSHOPPING_APP_CHATGPT_RA_API_KEY_VOYAGE_AI` | Voyage embeddings |
| **LM Studio** | `CLICSHOPPING_APP_CHATGPT_LMSTUDIO_URL` | GPT-OSS, Qwen3, Phi-4 |
| **Ollama** | Local server (port 11434) | Mistral, Llama, etc. |

Note : The administrator select one provider and this provider is used in all system. But it's possible to use another provider is specific case. See the fuction Gpt::getGptResponse() for customization.

### Facade Class

**Location**: `Core/ClicShopping/Apps/Configuration/ChatGpt/Classes/ClicShoppingAdmin/Gpt.php`

The `Gpt` class is the main facade that manages all LLM interactions. It delegates to specialized SubGpt classes:
- `ConfigManager` - Configuration and status
- `ModelManager` - Model information and selection
- `ProviderManager` - Provider initialization (OpenAI, Anthropic, Mistral, LM Studio, Ollama)
- `ResponseProcessor` - Response generation and processing
- `DataManager` - Data persistence and analytics
- `UIGenerator` - UI component generation

### Usage

```php
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

// Main method - handles all providers automatically
$response = Gpt::getGptResponse(
    $question,      // Prompt
    $maxtoken,      // Max tokens (optional)
    $temperature,   // Temperature (optional)
    $engine,        // Model name (optional, uses default if null)
    $max            // Max responses (optional, default: 1)
);

// Check GPT status
if (Gpt::checkGptStatus()) {
    // GPT is enabled and configured
}

// Get available models
$models = Gpt::getGptModel();

// Get model context length
$contextLength = Gpt::getModelContextLength('gpt-4.1-mini'); // 64000
```

### Rules

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
## 7. Embedding pipeline

### Embedding tables (real state of the schema)

E-commerce core (3072):
- `clic_products_embedding`
- `clic_categories_embedding`
- `clic_reviews_embedding`
- `clic_reviews_sentiment_embedding`
- `clic_orders_embedding`
- `clic_pages_manager_embedding`
- `clic_manufacturers_embedding`
- `clic_suppliers_embedding`
- `clic_return_orders_embedding`

Additional memory/security tables:
- `clic_conversation_memory_embedding` (3072)
- `clic_correction_pattern_embedding` (3072)

### Common table structure

| Column | Type | Role                                    |
|---|---|-----------------------------------------|
| `embedding` | `VECTOR(3072)`  | Semantic vector                         |
| `entity_id` | INT | Link to source table                    |
| `content` | TEXT | Original text indexed                   |
| `metadata` | JSON | Contextual enrichment (added v4.11)     |
| `chunknumber` | INT | Sequential chunk index within the document (chunk size: 128 tokens) |
| `date_modified` | DATETIME | Last Updated Timestamp                  |

### Pipeline rules

- The generation of embeddings is managed by **existing crons** — do not recreate this mechanism
- The vector dimensions are driven by the existing diagram (mostly 3072)
- MariaDB ≥ 11.7 is imperative for native `VECTOR INDEX`
- Do not modify the structure of the embedding tables without agreement from the human coder.

---

## 8. AI Security (Guardrails)

The system includes a security layer dedicated to LLM interactions.

Active mechanisms:
- **Prompt injection detection** — security checks applied via existing AI/API components before full execution
- **Obfuscation scan** — detection of encoded evasion attempts
- **Rate limiting AI** — window 900s, 20 requests max per identifier
- **Audit** — `clic_rag_security_events` table for traceability

Constants:
```
CLICSHOPPING_APP_API_AI_RATE_LIMIT_WINDOW = 900
CLICSHOPPING_APP_API_AI_MAX_REQUEST_PER_WINDOW = 20
CLICSHOPPING_APP_API_AI_MAX_LOGIN_ATTEMPTS = 5
CLICSHOPPING_APP_API_AI_ACCOUNT_LOCK_DURATION = 1800
```

**Do not bypass guardrails in application code**; For testing, use the planned configurations and a dedicated environment.

---

## 9. MCP — Model Context Protocol

ClicShopping exposes an MCP server for agentic commerce (port 3001 by default, can be modified inside the administration).

Roles:
- Health monitoring of the AI system
- Performance metrics (latency, error rate, uptime)
- Integration with external agents via standardized MCP protocol

Storage table: `clic_mcp_performance_history`
Do not modify the MCP protocol without agreement from the human coder.

---

## 10. AI development rules

```
✓ Always go through LLPhant for LLM calls
✓ Leverage `ReasoningAgent` and existing orchestration
✓ Register new agents via Core/ClicShopping/AI/
✓ API keys via configuration constants only
✓ Respect existing guardrails
✓ Use validators for business logic validation (DomainsAI/Analytics/Validator/)
✓ Keep wrappers minimal (CoreAI/Orchestrator/SubActorCritic/Critics/)

✗ Direct calls to LLM APIs (OpenAI, Anthropic, etc.)
✗ Recreate the embeddings pipeline or associated crons
✗ Modify the structure of *_embedding tables without agreement
✗ Edit existing vector dimensions without proprietary validation
✗ Bypass guardrails for testing
✗ Instantiate agents directly from PHP Apps
✗ Add business logic to Actor-Critic wrappers
✗ Couple validators to Actor-Critic infrastructure
✗ WARNING: Data transformation to Vector(3072) is strictly delegated to LLPhant. The Agent MUST NOT attempt to generate vector arrays manually in PHP
```

---

## 11. References

- Architecture framework: `ARCHITECTURE.md`
- Vector database: `DATABASE.md`
- Guardrails security: `SECURITY.md`
- DeepWiki AI section: https://deepwiki.com/ClicShopping/ClicShopping/5-ai-integration-and-rag-system