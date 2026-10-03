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

---

## ADR-004 - The provider key lives only in the AI service

**Date:** 2026-10-03
**Status:** Accepted
**Req:** AIG-10, CON-03

### Context
AIG-10 requires that only the data needed for grading reaches the model, and
only through a backend proxy. It does not say where the provider credential is
held, so the choice was open.

### Decision
The provider key is read from the environment by the Python service only.
Laravel holds no provider key at all. It reaches the service over HTTP at
`AI_SERVICE_URL` and optionally presents a shared `AI_INTERNAL_KEY`. No browser
code calls a model or the AI service directly.

### Rationale
- The customers of this product are schools and examination bodies holding
  candidate names and scripts. A credential sitting in the same database as
  that data turns a single dump into a model account compromise.
- The Python service is the only component whose job is to talk to a model, so
  it is the only component that needs the credential.
- Making Laravel a plain HTTP client means the AI service can be moved, replaced
  or scaled without touching the API layer.

### Consequences
- A model outage is now a network hop rather than a missing constant, so
  `GET /api/v1/ai/health` exists and the frontend states availability plainly
  rather than letting a teacher discover it mid-paper.
- The shared key is empty by default, which disables the check on the Python
  side. That is a developer-machine state and it is reported by the health
  endpoint so it cannot be an unnoticed assumption in a deployment.
- Both Custograde and Custosell currently draw on one OpenRouter free-tier key,
  so their rate limits are shared. Separate keys before production.

---

## ADR-005 - Marks arrive as mixed types and are normalised at the point of use

**Date:** 2026-10-03
**Status:** Accepted
**Req:** MRK-01, AIG-01

### Context
MySQL decimal columns are cast in Eloquent. A `decimal:2` cast serialises as a
JSON string, while a resource that casts to float on the way out serialises as a
number. The same logical field, `max_mark`, does both depending on which
endpoint delivered it: `ExamQuestionResource` emits a string, while the question
nested inside `ScriptAnswerResource` emits a number.

### Decision
Frontend types for marks and confidences accept `number | string`, and the
`markValue` and `confidenceValue` helpers normalise once at the point of use.

### Rationale
- Narrowing the type to one form would have been a guess, and `../contract_check.py`
  exists precisely because a wrong guess is a silent runtime bug: a form that
  treats a string as a number writes NaN into a mark.
- The alternative, forcing every resource to cast consistently, is a breaking
  change to every existing consumer for a cosmetic gain.

### Consequences
- `contract_check.py` verifies field names and value types against the running
  API, so a drift is caught rather than discovered in a marking session.
- An absent mark is null and never zero. "Not marked yet" and "marked zero" are
  different facts in an examination, and collapsing them is how a candidate
  loses a mark.

---

## ADR-006 - Authorisation is a capability matrix, and tenant scope is not authorisation

**Date:** 2026-10-03
**Status:** Accepted
**Req:** SEC-07, GOV-03

### Context
Every controller in the API asked one question about a request: is this row
visible to the caller's institution. None of them asked whether the caller was
allowed to do the thing. `users.role` existed as a string, was validated against
a catalogue, and was then never read again.

The consequence was not subtle. Any authenticated user inside an institution
could release that institution's results, reopen an approved script, or decide
marks. An `auditor` and a `scanning_operator` had exactly the same power as an
examination officer.

### Decision
Authorisation is a `Capability` matrix in `app/Support/Capability.php`, applied
per route through `RequireCapability` middleware. It is deliberately separate
from `visibleTo`, which continues to answer only the tenancy question.

Rules the matrix follows:
1. Every grant is deliberate, so adding a role never silently inherits anything.
2. An unrecognised role is granted nothing. A typo in a role column fails
   closed, and the test suite asserts a role named `wizard` can do nothing at
   all, including reads.
3. Read is separated from write throughout, so `VIEW_RESULTS` and
   `RELEASE_RESULTS` are different capabilities.
4. Deciding a mark, amending an approved mark, locking, moderating, compiling
   and releasing are six separate capabilities, not one "marking" permission.

### Rationale
- Splitting `DECIDE_MARKS` from `AMEND_APPROVED_MARKS` is what stops a marker
  quietly rewriting approved work, which is the failure a marking system cannot
  afford and which no amount of audit logging would prevent.
- Keeping the matrix in one readable file means the answer to "who may release
  results" is a single lookup rather than a hunt through controllers. Each entry
  carries the reason it is granted, because a bare list of role names gets
  edited by someone who cannot see the reasoning.
- A route that forgets the middleware fails closed by default, since a role with
  no capability cannot do anything.

### Consequences
- `institution_admin` is granted the full marking path, including releasing.
  Self-registration lands on that role, and with no user management there is no
  way to grant a different one, so denying it would leave a fresh account unable
  to finish an examination. This is recorded as a known narrowing, not defended:
  once role assignment exists, that grant should be tightened.
- `moderator` cannot decide a mark, only moderate, so a moderation decision
  stays distinguishable from a marking decision as REV-09 requires.
- `auditor` appears in no write capability anywhere in the file, which is the
  point of an audit role and is easy to verify by reading one entry.
- Two of the three separations SEC-07 names are still unimplementable. Appeals
  (APL) and sheet generation (SHT) do not exist, so "a marker shall not approve
  an appeal on their own script" has nothing to gate. The appeal rule will need a
  per-record check rather than a role capability, because it depends on who
  marked the row.

---

## ADR-007 - Sheet codes are short and signed, not encrypted

**Date:** 2026-10-03
**Status:** Accepted
**Req:** SHT-02, SHT-08, IDN-02

### Context
SHT-02 requires the code printed on an answer sheet to contain "only an opaque
script identifier with a check value and signature", and forbids embedding
personal data. It does not require confidentiality.

The first implementation encrypted the payload with `Crypt::encryptString` as
well as signing it, reasoning that hiding the script numbering was worth having.

### Decision
The payload is the script's existing opaque code, the page number, the page
total, and a twelve character HMAC. No encryption.

### Rationale
This was found by measuring rather than by reading. A QR symbol spreads its
payload across its module count, and a 220 character encrypted payload needs
roughly a hundred modules across. Printed at 16mm and scanned at 300dpi, which
is ordinary A4 handling with a phone, that symbol was **unreadable**. The short
signed payload decodes at the same size.

The original code was correct against SHT-02 and broken against SHT-08, and it
failed silently: the value still decoded in memory and the PDF still rendered,
so nothing short of printing it and reading the pixels back would have caught it
before an examination day.

The lesson is recorded because it is easy to repeat. A QR has a module budget,
and spending it on confidentiality trades away the thing the code exists to do.
SHT-02 wanted opacity and authenticity, both of which the HMAC provides, since
the script code is already unguessable.

### Consequences
- `AnswerSheetTest::test_the_encoded_qr_is_readable_at_the_size_it_is_printed`
  renders the real payload at 16mm, downsamples it to 300dpi, and reads the
  pixels back. Lengthening the payload fails that test. Verified by injecting a
  220 character payload and watching it fail.
- The payload names a script code rather than a numeric id, so resolving it to a
  script is a tenant-scoped query performed by the caller that holds a user,
  rather than something the code service can do on its own.
- Sheet codes are guessable by anyone holding a photograph, since they are
  derived from a printed code rather than a secret. That is acceptable: the code
  resolves only within one institution, and the signature still rejects codes we
  never issued.
- `scripts.original_path`, `original_hash` and `original_name` became nullable,
  because issuing a sheet creates a script row before any paper exists. Filling
  them with placeholders was rejected: a placeholder path is a lie later code
  cannot distinguish from a real file, and would surface as a signed image URL
  that 404s at the worst moment.

---

## ADR-008 - A repeated "print the class" skips candidates, and never rotates a live code

**Date:** 2026-10-03
**Status:** Accepted
**Req:** SHT-05, SHT-06

### Context
SHT-05 asks for sheets for a whole class in one asynchronous batch with progress
reporting and a single download. It says nothing about what happens when someone
presses the button twice, which is the likeliest thing a user does.

### Decision
A candidate who already holds a current sheet is counted as `skipped` and their
existing code is left alone. A second run is refused outright while one is in
flight, with 409.

### Rationale
Rotating codes on a repeat run would invalidate every sheet already printed and
sitting in a box at the school. Nothing would report an error: the new sheets
would be valid, the old ones would simply stop resolving, and the failure would
surface as unidentified scans days later during marking.

Two related decisions in the same area, for the same reason:

- A candidate who fails is recorded by name and the batch still completes. A
  class where twenty-nine of thirty sheets printed is far more useful than an
  error page and nothing at all.
- The archive path is written only after every candidate is accounted for, and a
  run that printed nothing produces no archive. A partial ZIP opens cleanly and
  looks complete, and a teacher would print it believing the class was covered.

### Consequences
- A second run reports 0 processed and N skipped. That is the honest answer, and
  the UI has to present it as "these were already issued" rather than as a
  failure, or officers will read a successful action as a broken one.
- Progress counts skipped candidates as done, because they are genuinely dealt
  with. Counting them as outstanding would leave a batch looking permanently
  stuck for a class that was mostly issued already.
- Entries are named `<reg_no>-<code>.pdf` so sheets come off the printer in a
  usable order and a candidate can be found without opening thirty files.
- `test_running_twice_skips_candidates_who_already_have_a_sheet` asserts the
  codes are byte for byte unchanged after a second run, which is the actual
  requirement. Verified to fail when the skip logic is disabled.

### A separate trap worth recording

A long-running `queue:work` holds an autoloader snapshot from when it started, so
after `composer require` it keeps using the old class map until restarted. The
symptom is genuinely misleading: the HTTP request returns 202, the batch row is
created, and then every candidate fails *inside the worker* with a class-not-found
error, while the identical code works in tests and in tinker. Answer sheet batch
printing hit this exactly. The README now says to restart the worker after any
Composer change, and to suspect it first for that symptom.

---

## ADR-009 - Identification crops to where the code is, and runs one attempt inline

**Date:** 2026-10-03
**Status:** Accepted
**Req:** IDN-02, IDN-05, CAP-01

### Context
Sheets had carried a machine-readable code since SHT-01, but nothing read it.
Identification was a person choosing a candidate from a dropdown, which is fine
for one paper and hopeless for thirty. This makes the scan identify itself.

### Decision
`ScanIdentifier` decodes the code off the stored image and `capture()` attaches
the scan to the row the issued sheet already created. Two measurements shaped it.

Decoding the whole page does not work. The code is 16mm on a 210mm sheet, so it
covers about 120 pixels of a 1654 pixel wide page, and the detector reported
"could not find enough finder patterns" after 14 seconds and 300MB. Enlarging the
page first was worse: a 3x upscale asked for a 34 megapixel buffer and exhausted
a 1GB limit outright. Cropping to the region the code occupies decoded it exactly,
in 1.3 seconds and 82MB.

The capture path then makes exactly one attempt: our own template, one region,
one variant. That reads a code in about 1.4 seconds and, more importantly, gives
up quickly instead of making an operator watch a spinner on a bad scan. The
thorough search, 1.6s on success and 17.6s when nothing on the page reads, is
available for a deeper pass afterwards.

### Rationale
- Identification happens **before** a row is created. Issuing a sheet already made
  one, so identifying afterwards would leave two rows claiming one paper and
  results would have no way to choose between them.
- The signature is verified before any lookup, so a random string that happens to
  scan, or another school's code, never reaches the database.
- A match is scoped to this examination, to codes with no file yet, and to sheets
  that are still current. A superseded code therefore cannot attach, which is what
  makes a reissue mean anything.
- A page that reads as nothing returns null rather than raising. "We could not
  read this one" is an ordinary outcome that routes the paper to the exception
  queue; an exception here would lose a real script (IDN-05).

### Consequences
- A scan that cannot be identified still uploads, keeps any operator-chosen
  candidate, and is flagged. Nothing is refused.
- A candidate whose sheet was issued is never asked about at upload time, which is
  the point of the feature. Verified live: a scan attached to the issued row and
  resolved to the right candidate with no operator input.
- Only raster images are attempted. A PDF upload stays unidentified rather than
  failing, because decoding one means rasterising it, which is page assembly
  (IDN-01) and a separate piece of work.
- `ScanIdentificationTest` draws a real QR onto a real 200dpi A4 canvas at the
  template's position and size, then uploads those bytes. Nothing is stubbed,
  because the whole risk is in the image handling and a mock would pass happily
  while the detector read nothing.

---

## ADR-010 - Roles are assigned by rank, and a generated password is not an emailed one

**Date:** 2026-10-03
**Status:** Accepted
**Req:** AUT-05, SEC-07

### Context
The capability matrix from ADR-006 was unusable in practice. Roles could be
validated but never assigned, so every account that existed was an
`institution_admin` created by registration. That is why ADR-006 had to grant
institution administrators the full marking path: denying it left a school
unable to finish an examination, because there was no way to give them a
different role.

### Decision
An institution can add staff, change their role and deactivate them, guarded by a
privilege ranking rather than a capability check.

### Rationale
The first attempt expressed the rule as "you may not grant a role you do not hold"
and checked it with `Capability::allows($actorRole, $targetRole)`. That is
meaningless: a role is not a capability, so the check always failed and no
staff could be added at all. The rule needed authority, which is what `ROLE_RANK`
now expresses. An actor may hand out a role only at or below their own.

Four guards, each closing a specific escalation:
- **Rank.** An officer may appoint teachers and moderators, not administrators.
- **`system_admin` is unassignable inside a tenant.** It answers to nobody there,
  so granting it from inside one creates an account with no accountable owner.
- **Nobody changes their own role.** Otherwise a moderator promotes themselves and
  the audit trail becomes a record of decisions nobody had to justify.
- **The last administrator is protected.** An institution that cannot manage staff
  has no way back in short of a database console.

A generated password is returned once and flagged `must_change_password`, rather
than chosen by an administrator and emailed. A password an administrator picks is
a shared secret from the day it is sent, and often survives in the mail thread for
years.

### Consequences
- Deactivation, not deletion. A mark somebody approved is part of the record of
  how that mark came about, and deleting the person leaves an audit trail pointing
  at nobody (SEC-06).
- The lockout guard is currently **unreachable through the API**, because the
  acting administrator always counts as a remaining administrator. It is kept
  because the guard becomes live the moment staff management is widened to a
  lesser role. It is extracted as `Capability::wouldLockOutInstitution` and tested
  as a pure rule, rather than a test pretending an unreachable path is covered.
- **`institution_admin` still holds the marking path, deliberately.** Narrowing it
  is now *possible*, which was the point of this work, but doing it today would mean
  a brand new institution must register, appoint a marker, and only then mark
  their first paper. The benefit is theoretical while appeals do not exist, and the
  cost lands on every first-day user. The trigger for narrowing is the arrival of
  appeals (APL) or sheet generation (SHT), where SEC-07's other two separations
  become real.

### A bug this work exposed

Ordering the staff list by `last_name` passed on SQLite and failed on MySQL with
"Unknown column". The `users` table has one `name` column, not first and last, and
the first implementation also *set* `first_name` and `last_name`, which mass
assignment dropped silently: staff were being created with no name at all and no
error anywhere. Both are fixed, and there is now a test asserting the name is
actually stored on the row, plus one asserting the list arrives ordered. Worth
recording as a class of bug: SQLite is permissive in ways MySQL is not, so a green
test suite is not proof a query runs on the production engine.
