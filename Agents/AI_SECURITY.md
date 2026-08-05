# AI_SECURITY.md — AI framework security rules

> **Scope: `Core/ClicShopping/AI/` only.** The platform's own rules live in `SECURITY.md`.
> The two frameworks are separate and neither substitutes for the other: a guardrail judges a
> *prompt*, an escape protects a *page*. A query cleared by the guardrails still gets every platform
> check on the way to the database.
>
> ⚠️ **This file is a STARTING POINT.** It states only what is verified in
> `Core/ClicShopping/AI/Security/` (10 classes, ~5 700 lines). It is not an exhaustive audit —
> extend it as ground is covered, never invent a rule to fill a gap.

---

## 1. Absolute Prohibitions

```
✗ Bypassing the guardrails — every user query goes through SecurityOrchestrator::validateQuery()
✗ Calling an LLM outside the Gpt facade / LLPhant abstraction
✗ Instantiating an agent directly from business code (go through the Orchestrator)
✗ Executing a self-optimization objective WITHOUT a passing critic gate
✗ Using pattern-based detection as the PRIMARY defense (Pure LLM Mode is the design)
✗ Building SQL from an LLM answer without SqlSecurityValidator
✗ Putting prompt text in a class — prompts are .txt definitions (see AI_ARCHITECTURE.md §5.3)
✗ Logging a raw user query with its personal data into the application log
```

## 2. The validation pipeline — `AI/Security/SecurityOrchestrator.php`

`validateQuery()` is the single entry point, called **after** input validation and **before** the
orchestrator:

```
STEP 0   ObfuscationPreprocessor::preprocess()   normalises the query, returns the obfuscations
                                                 found and a confidence boost
PRIMARY  SemanticSecurityAnalyzer::analyze()     LLM judgement: is_malicious, threat_score,
                                                 threat_type, reasoning
BOOST    an obfuscated query has its threat score raised, and is flagged malicious once the
         threshold is reached even when the LLM did not say so
VERDICT  blocked when is_malicious AND threat_score >= threat_threshold
```

**Pure LLM Mode**: the LLM is the primary defense; the pattern fallback is optional and disabled by
default. Processing is always in English internally, whatever the user's language.

⚠️ **The pipeline is fail-OPEN when the LLM is unavailable**: `validateQuery()` returns
"not blocked" with `detection_method = llm_error`. That is a deliberate availability choice, not an
oversight — but it means an LLM outage removes the defense. Never widen that path, and never treat
a `llm_error` verdict as a clean bill of health.

## 3. The other layers

| Class | Role |
|---|---|
| `InputValidator` | shape and size of the input, before any LLM sees it |
| `LlmGuardrails` | the guardrail rules themselves |
| `LlmResponseEvaluator` | judges what comes BACK from the LLM, not only what goes in |
| `DbSecurity` | the database surface an AI query may touch |
| `RateLimit` | per-user quotas on the AI path, distinct from `OM/RateLimiter` |
| `SecurityLogger` | the security channel — layer performance, obfuscation, fallback usage |
| `SecurityAlerter` | escalation when the logger records a real threat |
| `SqlSecurityValidator` (`DomainsAI/Analytics/Validator/`) | the generated SQL, before execution |

`SecurityLogger` writes to the **security** channel. An application error on the AI path goes to
`logApplicationError()` instead — do not mix the two, or a crash reads as an attack.

## 4. Self-optimization

The objective loop (`ObjectiveExecutor`) must gate every proposal through the actor-critic consensus
before applying it. A rejected proposal is never applied. Analyse the gate result; never bypass it.
This is what keeps the system from modifying itself unsupervised.

---

## To be covered

Gaps listed so they are visible: the exact threat taxonomy and thresholds, the MCP permission
surface (`McpPermissions`), what `DbSecurity` actually forbids, PII handling in embeddings, and the
retention of the security tables.
