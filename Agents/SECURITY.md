# SECURITY.md — Platform security rules

> **Scope: the e-commerce platform.** Everything under `Core/ClicShopping/` EXCEPT
> `Core/ClicShopping/AI/`, whose own rules live in `AI_SECURITY.md`.
> The two frameworks are separate: an AI guardrail never replaces a platform check, and a platform
> escape never sanitises a prompt.
>
> ⚠️ **This file is a STARTING POINT.** It states only the rules already verified in the code. It is not an exhaustive audit — extend it as ground is covered, never invent a rule to
> fill a gap. An unverified claim here is worse than an absent one, because `AGENTS.md` gives this
> file winning priority in a conflict.

---

## 1. Absolute Prohibitions

```
✗ Interpolating any user input into SQL — Registry::get('Db') with placeholders only
✗ Direct PDO connection outside Registry::get('Db')
✗ Echoing a value from the database or the request without HTML::output()/outputProtected()
✗ Business logic or DB access in templates (see TEMPLATES.md)
✗ Hardcoding a secret, an API key or an encryption key in the code
✗ Writing an inline `on*` handler — HTML::button() strips them (sanitizeHtmlAttributes)
✗ Accepting a POST that carries no valid `formid` token
✗ Exposing an admin ajax endpoint without AdministratorAdmin::hasUserAccess()
✗ Logging a password, a token or a decrypted value
```

## 2. Escaping — `OM/HTML.php`

| Method | Does | Use for |
|---|---|---|
| `output()` | `htmlspecialchars` with `ENT_QUOTES \| ENT_HTML5`, optional translation table | any value rendered into HTML |
| `outputProtected()` | same, no translation table | the same, when no substitution is wanted |
| `sanitize()` | replaces `<`, `>`, `&lt;`, `&gt;`, `%3c`, `%2f` and runs of spaces with `_` | a value used as an identifier, never as displayed text |
| `sanitizeUrl()` | rejects the `javascript:` protocol | every url reaching `HTML::link()` — applied automatically |
| `sanitizeHtmlAttributes()` | strips inline `on*` event handlers | applied automatically by the attribute-taking helpers |

`sanitize()` is **not** an HTML escape: it mangles legitimate text and, on SQL fragments, silently
truncates comparison operators. Never use it to make a value safe to display.

## 3. CSRF

Every form built by `HTML::form()` carries `formid`, holding `$_SESSION['sessiontoken']`
(`OM/HTML.php:371`). An action processing a POST must verify it. A POST without a valid token is
not a degraded case to work around: reject it.

## 4. Cryptography — `OM/Hash.php`

- **Passwords**: `encrypt()` / `verify()`, with `needsRehash()` and `migratePassword()` for the
  legacy hashes. Never compare hashes by hand, never re-implement the algorithm choice.
- **Data at rest**: AES-256-CBC (`encryptDatatext()` / `encryptEmail()` and their decrypt
  counterparts), random IV per value.
- **Key**: resolved from `data_encryption` in `Conf/global.php`, generated at install. Check with
  `isEncryptionKeyConfigured()` before relying on encryption. A `LEGACY_EMPTY_KEY` fallback exists
  on **decryption only**, to read values written before a key was configured — never write with it.
- **Randomness**: `getRandomInt()`, `getRandomString()`, `getRandomBytes()`. Never `rand()`,
  `mt_rand()` or `uniqid()` for anything a user must not predict.

## 5. Rate limiting — `OM/RateLimiter.php`

`check()` / `record()` for windowed limits, `checkAttempts()` / `recordAttempt()` for
attempt counters (login, password reset). `reset()` on success.

⚠️ Not to be confused with `ActionRecorder`, which requires an installed `ar_*` module and silently
does nothing without one. Rate limiting must fail **closed** and say so — a limiter that fails in
silence is not a limiter.

## 6. Authorisation

Admin pages and **every admin ajax endpoint** gate on
`AdministratorAdmin::hasUserAccess()`. An endpoint reachable without it is a hole regardless of how
unlikely the url looks.

## 7. GDPR

`Apps/Customers/Gdpr/` owns consent, export and erasure. A new feature storing personal data
declares itself there rather than growing its own mechanism.

---

## To be covered

Sections this file does not yet describe, listed so the gap is visible rather than assumed closed:
session handling and fixation, file upload validation, the payment paths (which are fail-closed by
design — see the OrderTotal and Stripe rules), HTTP security headers, and the audit tables
referenced by `DATABASE.md`.
