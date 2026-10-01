# Custograde Architecture Decision Records

One entry per decision that constrains future work. Newest last.

---

## ADR-001 - Registration is one endpoint branching on `account_type`

**Date:** 2026-10-01
**Status:** Accepted
**Req:** AUT-01, AUT-04, AUT-05, AUT-06

### Context
Accounts come in two shapes. A **personal** account is an individual teacher
with no school or employer behind them. An **institutional** account belongs to
a school, university or examination body. Custosell solves the same problem with
two endpoints and two clients (`/auth/register` and `/businesses/register`).

### Decision
A single `POST /api/v1/auth/register` takes a required `account_type` of
`personal` or `institutional`. `RegisterRequest` makes `institution_name` and
`institution_type` required only for institutional accounts and ignores them
otherwise. `AuthService::register()` skips institution creation entirely for
personal accounts and derives the role from `User::ROLE_FOR_ACCOUNT_TYPE`.

### Rationale
- One validation surface means one place to audit, and the chooser screen needs
  only one client.
- Custosell's two-endpoint split buys a clean validation shape but costs a
  second client, a second controller method and a divergent success contract.
- Deriving the role server-side from the account type removes a whole class of
  privilege bug: a client cannot ask to be an `institution_admin`.

### Consequences
- `RegisterInstitutionRequest` is deleted; `RegisterRequest` replaces it.
  `admin_first_name` / `admin_last_name` became `first_name` / `last_name`
  because both account types now use them. Breaking change, no production data.
- Adding a third account type is a `ROLE_FOR_ACCOUNT_TYPE` entry plus chooser
  copy, not a new endpoint.
- An unknown `account_type` is a 422 and creates nothing, so a malformed client
  cannot accidentally provision a tenant.

---

## ADR-002 - Personal accounts are self-tenants with a NULL `institution_id`

**Date:** 2026-10-01
**Status:** Accepted
**Req:** AUT-06

### Context
AUT-06 requires strict tenant isolation: no user may read or modify another
tenant's records. Every entity in the spec is institution-scoped, so a personal
account with no institution has no obvious home.

### Decision
A personal account gets **no** `institutions` row. `institution_id` stays NULL
and the user is their own tenant, reached through `User::isPersonal()`. No
hidden placeholder institution is created.

### Rationale
The alternative - an auto-created `Institution` with a "Personal Practice" type
- would make every tenant query work unchanged from day one, at the cost of one
row per solo teacher and, more seriously, of pretending a solo practice is an
institution. That fiction would leak into reporting and billing later, where it
is expensive to unwind.

### Consequences
- Every tenant-owned model needs a branch: institution scope for institutional
  users, user scope for personal ones. That branch is unavoidable either way;
  hiding it behind a fake institution only delays it.
- `institution_id` had to stay nullable, which it already was for the circular
  User/Institution dependency.
- `/dashboard/summary` returns `institution: null` for personal accounts instead
  of erroring, and the frontend hides the institution-only tiles.
- The frontend dashboard labels a personal account "Independent teacher" and
  drops the "Institution users" tile rather than showing a misleading zero.

---

## ADR-003 - Email verification is behind `AUTH_REQUIRE_EMAIL_VERIFICATION`, default off

**Date:** 2026-10-01
**Status:** Accepted
**Req:** AUT-01, AUT-09

### Context
Registration originally always minted a six-digit code and refused to issue a
token until the address was confirmed. That adds a step and a mail dependency to
every local run and pilot, and it is not required by the spec.

### Decision
`config('auth.require_email_verification')`, read from `AUTH_REQUIRE_EMAIL_VERIFICATION`
and defaulting to **false**.
- Off: no code is minted, registration returns a working token, login skips the
  verified-email gate, and `POST /auth/verify/send` answers 422 with
  "Email verification is not enabled on this installation."
- On: original behaviour, restored by flipping the flag.

### Rationale
The verification endpoints stay registered under both states so the flag can be
flipped with an `.env` change and a config cache clear - no migration, no route
redeploy. Refusing the code endpoint rather than silently succeeding means a
client cannot believe it sent a code that was never minted.

### Consequences
- `AuthService::issueVerificationCode()` returns false for email verification
  while the flag is off, so no code can be minted through any path.
- The test suite runs with the flag off (matching `.env`) and enables it
  explicitly in the tests that exercise the challenge, so both branches stay
  covered: `EmailVerificationDisabledTest` covers off, `AuthTest` covers on.
- Before a real production pilot this should be turned on. It is a config
  change, not a code change.
