# AI_GOVERNANCE_DECLARATION.md

# AI Governance Declaration

**System:** ClicShopping AI
**Instrument:** Regulation (EU) 2024/1689 (Artificial Intelligence Act)
**Companion document:** [AI_ACT_COMPLIANCE.md](AI_ACT_COMPLIANCE.md)
**Last updated:** 2026-09-20

This document is the single place where an auditor reads the three declarations the AI Act asks a
deployer to hold: **who owns the system**, **what risk and autonomy it carries**, and **what its
agents are allowed to do**. It declares the state of the code as it is, not as it is intended to
become. Every claim below is anchored to a class and a line, listed in § *Anchors*, and
`unit_test/2026_09_20/aiact5_declaration_guards_test.php` fails if a named guard leaves the code or
moves away from the line cited here.

---

## 1. System inventory and owner

| Item | Declaration |
|---|---|
| System name | ClicShopping AI — AI-assisted e-commerce management platform |
| Role under the AI Act | **Deployer**, not provider. The platform develops, trains and modifies no model; it is an interface to external or local providers (see `AI_ACT_COMPLIANCE.md` § *AI Provider*) |
| Accountable person | Read from `CLICSHOPPING_APP_CHATGPT_ASY_AI_ACT_RESPONSIBLE`, declared per installation. **Not written into this document**, and never derived from `STORE_NAME` — the shop name is not the accountable deployer |
| Purpose | AI-assisted content generation and analytical assistance for e-commerce management: product descriptions and enhancement, FAQ, SEO content, reports, product creation and administrative writing assistance (`AI_ACT_COMPLIANCE.md` § *Purpose*, § *Scope*) |
| Deployment scope | Single-tenant installation, back-office administrators and, where the shop enables it, front-office visitors. Availability of each feature depends on the installed applications and configuration |
| Risk level | **Not high-risk.** The intended purpose falls outside Annex III of Regulation (EU) 2024/1689 — no biometric identification, recruitment, education assessment, creditworthiness, law enforcement, migration, judicial support, nor access to essential services (`AI_ACT_COMPLIANCE.md` § *High-Risk AI Systems*). Extending the platform into such a use case brings additional obligations |

The accountable person is **mandatory**. While the constant is empty, the back-office dashboard
raises a red alert on every page (`CheckAPI.php`), because an unnamed deployer satisfies neither
Article 26 nor this declaration.

---

## 2. Risk and autonomy classification

Autonomy is declared **before** any assessment, per execution path, using the HITL / HOTL / HOOTL
scale.

| Path | Autonomy | What that means in the code |
|---|---|---|
| Analytical, semantic and web chat | **HOTL — conditional** | The answer is generated and rendered without prior human validation. The only gate is machine: `ValidationGate` returns `pass`, `annotate` or `regenerate` from a quality score. A human reads afterwards |
| CockpitAI self-optimisation, with `CLICSHOPPING_APP_ECOMMERCE_CAI_AUTO_MODE = True` | **HOOTL at execution — inside a declared envelope** | The cron writes promotions, featured and favorites with no per-action confirmation (`CockpitAIOrchestrator.php`). It runs at all only because an administrator moved the switch off its `False` default, it stays inside the thresholds set in `Config/CAI/Params/`, and every row it writes is visible and reversible (§ 2.1) |
| Objective loop (`ObjectiveExecutor`) | **Out of scope — dormant** | No caller, and off by default (`ObjectiveExecutorConfig::isEnabled()`). Objectives accumulate as `pending`; none was ever `approved`, `active` or `completed` |

**Relevant harm classes:** *Technical* — a wrong analytical answer presented as certain — and
*Operational* — a catalogue write nobody confirmed. **Societal and Systemic are not relevant**: no
decision is taken about a person.

The HOOTL path raises the requirement on authority, incident handling and separation of duties.
That is what § 3 declares.

---

## 3. Agent authority declaration

What each agent may **consult**, **decide**, **execute**, and what it must **escalate**. An empty
*execute* cell is a statement, not an omission: the agent writes nothing.

| Agent | Consult | Decide | Execute | Escalate |
|---|---|---|---|---|
| Analytics agent (chat) | Database schema and business tables, in read-only SQL it generates | Which tables, which SQL, how to interpret the rows | Nothing. No write path | An ambiguous question — a missing period, an unresolved reference — goes back to the user as a clarification question (`ClarificationHelper::generateClarificationQuestion()`) rather than being guessed. A question the planner cannot honour is refused by name, not approximated (`AnalyticsAgent::analysisPlanRefusal()`), and a partial run says which parts were not measured (`ResultFormatter::joinTextResponses()`) |
| Semantic agent (chat) | Vector stores and the RAG corpus | Which documents ground the answer | Nothing | Same clarification path; a query without grounding is refused rather than answered |
| Web search agent | External search providers, through the engine registry | Which engine and which query | Nothing in the catalogue | — |
| Critics / validation | The answer produced and its quality score | `pass`, `annotate` or `regenerate` (`ValidationGate`) | Annotates or re-runs generation | Nothing is escalated to a human: the verdict is machine-final, the human reads after delivery. **This is the declared limit of the HOTL path** |
| Objective creation (`AnalyticsObjectiveRunner`) | Its own analysis results | Whether to propose an optimisation objective | Writes a `pending` objective row | Creation is denied unless the agent is declared in `rag_agent_autonomous_config` (`AutonomousConfig::canAgentCreateObjectives()`), and denied again by `AuthorizationManager::verifyObjectiveCreationAuth()`, which audits the refusal |
| Objective execution (`ObjectiveExecutor`) | — | — | **Nothing — dormant and off by default** | — |
| CockpitAI (HOOTL at execution) | Product data, sales history, its own action log | Which marketing action to apply to which product | Writes `products_specials`, `products_featured`, `products_favorites` — and **only** those three (`ActionExecutor::getTableName()`) | A row the automation did not create belongs to the administrator and is never rewritten (`ActionExecutor::isAutomationOwned()`); unknown authorship is treated as the administrator's. Every action is logged, and an administrator revokes one by token (`CockpitAIRevocation::revoke()`) |

### What the platform does **not** guarantee

State the absence, so that no one reads a mechanism into a silence:

* **No approval workflow.** No agent action waits for a human to approve it.
* **No agent-suspension threshold.** Nothing suspends a misbehaving agent.
* **No quota of active objectives.** The status is never reached.
* **No human escalation on a poor answer.** `regenerate` re-runs the machine; it alerts nobody.

These are not configurable because they are not implemented. The measurement behind this paragraph
is in `docs/architecture/AI_SECURITY-notes.md` § `GOV-AUTO1`.

### What the platform does guarantee

* **An entry gate on every query.** `SecurityOrchestrator::validateQuery()` runs before any agent.
* **One autonomy control.** An agent may be forbidden to create optimisation objectives, per agent;
  an agent not declared is denied.
* **Autonomous execution off.** `ObjectiveExecutorConfig` defaults to OFF and has no caller.
* **A retention window on AI journals**, off by default, set by the operator
  (`AiDataRetention`, `CLICSHOPPING_APP_CHATGPT_ASY_DATA_RETENTION_DAYS`).
* **A named accountable person**, or a standing alert until there is one.

