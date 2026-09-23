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

### 2.1 What "human in the loop" means here

The HITL / HOTL / HOOTL scale reads autonomy **at the instant of the action**. That is not where
this platform puts the human. The operator is in the loop **before** any run, by configuration, and
**after** every run, by inspection and reversal. The system is autonomous **inside an envelope the
operator declared**, and has no behaviour outside it.

| Where the human acts | Mechanism | Anchors |
|---|---|---|
| **Before — authorisation** | Every AI capability is a back-office switch. Nothing self-enables, and turning one off removes the behaviour rather than warning about it | `CLICSHOPPING_APP_CHATGPT_ASY_ACTOR_SYSTEM_STATUS`, `..._ASY_VALIDATION_GATE_STATUS`, `..._ASY_WEBSEARCH_GLOBAL_STATUS`, `CLICSHOPPING_APP_CHATGPT_AC_STATUS` and one switch per critic, `CLICSHOPPING_APP_ECOMMERCE_CAI_AUTO_MODE` (**default `False`**); in code `ActorCriticConfig::isEnabled()`, `AgentActivationConfig::isAgentEnabled()`, `ObjectiveExecutorConfig::isEnabled()` |
| **Before — envelope** | The freedom left to an enabled automation is itself set by hand: thresholds, discount steps, windows, margin floor, concurrency | `Module/ClicShoppingAdmin/Config/CAI/Params/` — `t_low`, `t_high`, `promo_p1`…`promo_p4`, `margin_rate`, `promo_window_days`, `max_concurrent_analyses` |
| **Before — reach** | What the AI may call, and who may call the AI | `..._ASY_OUTBOUND_MODE` + `..._ASY_OUTBOUND_ALLOWED_HOSTS` (`OutboundPolicy::assertAllowed()`); `CLICSHOPPING_APP_API_AI_STATUS` gates every API endpoint; the MCP server answers only on its declared host, port and token |
| **During — the machine hands back** | The system does not only execute: it stops and returns the question, or names what it could not do. Two channels today — an **ambiguous** question comes back as a clarification with options, and an **unanswerable** one is declared, never guessed | `OrchestratorAgent.php:898` (`clarification_needed`), `ClarifyBeforeSplitStage.php:92`, `ClarificationHelper::generateClarificationQuestion()`; `AnalysisPlanValidator` records each `unsatisfiable` element with its reason, `AnalyticsAgent::analysisPlanRefusal()` renders it (`text_analysis_plan_refused_details`), `ResultFormatter::joinTextResponses()` names the parts not measured (`text_partial_report_notice`) |
| **After — inspection** | Three back-office dashboards read the engine's own output, each for a different reader | `dashboard_manager.php` (business), `dashboard_developper.php` (technical), `dashboard_data_scientist.php` (agents, critics, evaluations, alerts) |
| **After — reversal** | Every automated write lands in a table that already has its ordinary administration screen, and is revocable by token | `Apps/Marketing/Specials`, `Apps/Marketing/Featured`; `CockpitAIRevocation::revoke()`; a row the automation did not create is never rewritten (`ActionExecutor::isAutomationOwned()`) |

The *during* row is a real human-in-the-loop channel, and it is **deliberately narrow today**:
ambiguity and unanswerability only. It covers what the current development objectives cover, and it
grows with them. It is not a per-action approval workflow and does not claim to be one — what it
guarantees is that the system asks or declares instead of inventing.

So where § 2.2 declares **HOOTL**, it means *no confirmation is requested per action*. It does not
mean *outside human control*: a switch that removes the behaviour, a threshold that bounds it, a
dashboard that shows it and a screen that undoes it are human control, exercised once ex ante
rather than once per action.

This is a design choice, declared as such. Asking the operator to confirm each action would put a
human in the loop of every cron tick; the platform trades that for an envelope the operator sets,
reads and reverses. The residual risk of the trade is stated in § *What the platform does not
guarantee*, and is not softened by this paragraph.

**GDPR.** No automated write decides anything about a person: CockpitAI writes product rows and
only product rows (`ActionExecutor::getTableName()`). Article 22 GDPR — automated individual
decision-making — is therefore not engaged. It is the same fact that makes the *Societal* and
*Systemic* harm classes irrelevant below.

### 2.2 Declaration per execution path

Autonomy is declared **before** any assessment, per execution path, and is read with § 2.1.

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

These four are absent at the level of the **individual action**; none of them is a switch that was
left off. The control the platform does offer is the one described in § 2.1 — enable, bound,
inspect, revoke — and it is a different control, not the same one under another name. The
measurement behind this paragraph is in `docs/architecture/AI_SECURITY-notes.md` § `GOV-AUTO1`.

### What the platform does guarantee

* **An entry gate on every query.** `SecurityOrchestrator::validateQuery()` runs before any agent.
* **An authorisation policy on every outbound call.** `OutboundPolicy::assertAllowed()` decides
  before the connection is built, at all fifteen exits of the AI layer — LLM chat, embeddings and
  web search. Three regimes, chosen by the operator: `open` (the default, unchanged behaviour),
  `sovereign` (loopback and private networks only, so a self-hosted model runs and nothing leaves
  the site) and `allowlist` (declared hosts only). A refusal is journalled and raises
  `OutboundBlockedException`, never a silent failure: "the operator forbade it" and "the provider
  did not answer" call for opposite reactions.
* **A switch on every AI capability**, off or bounded until an administrator sets it, and removing
  the behaviour when turned off (§ 2.1). Autonomous catalogue writes default to `False`.
* **One autonomy control.** An agent may be forbidden to create optimisation objectives, per agent;
  an agent not declared is denied.
* **Autonomous execution off.** `ObjectiveExecutorConfig` defaults to OFF and has no caller.
* **A retention window on AI journals**, shipped on at 90 days and set by the operator
  (`AiDataRetention`, `CLICSHOPPING_APP_CHATGPT_ASY_DATA_RETENTION_DAYS`). It still deletes nothing
  until the `ai_data_retention` cron row is enabled.
* **A separate window on the measurement corpus** — the questions users asked, the answers and the
  executed SQL. It is the only AI journal carrying what a person wrote, so the operator sets its
  own window (`..._ASY_DATA_RETENTION_CORPUS_DAYS`, **default `0` = kept for ever**): enabling the
  journal purge never deletes it as a side effect.
* **A separate window on the agent objective queue** — its success criteria store the question the
  user asked, in plain text, so the operator sets its own window
  (`..._ASY_DATA_RETENTION_OBJECTIVES_DAYS`, **default `0` = kept for ever**).
* **A named accountable person**, or a standing alert until there is one.

