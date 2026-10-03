# Custograde Entities

Project memory for backend entities. One entry per entity with requirement traceability.

## Institution - 2026-10-01 11:00:00 - Req: ACD-01, AUT-04, BIL-01

### Fields
- name: string - institution display name
- type: string - Secondary School, University, College, Training Institute, Examination Body, Other
- email: string unique - tenant contact email
- phone: string nullable
- owner_id: FK users.id nullable - institution admin owner
- status: string default active

### Files Generated/Updated
- [x] Migration: `database/migrations/2026_10_01_104752_create_institutions_table.php`
- [x] Model: `app/Models/Institution.php`
- [ ] Repository Interface / Repository / Service Interface / Service - land with the full entity scaffold (Role entity next)
- [x] Provider: `app/Providers/AuthServiceProvider.php` + registered in `bootstrap/providers.php` (AuthServiceInterface binding)

### API Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/register` | Create institution + admin user, issue verification code |

### Test Results
- AuthTest register test: institution + admin created, code issued

### SOLID Compliance Checklist
- [x] Single Responsibility
- [x] Open/Closed (interface binding)
- [x] Liskov Substitution
- [x] Interface Segregation
- [x] Dependency Inversion (controller depends on AuthServiceInterface)

---

## User (auth extension) - 2026-10-01 11:00:00 - Req: AUT-01, AUT-02, AUT-04, AUT-05

### Fields
- name, email unique, password hashed (stock)
- institution_id: FK institutions.id nullable
- role: string default institution_admin (catalogue in `User::ROLES`, full Role entity next)
- phone: string nullable
- is_active: boolean default true
- email_verified_at: datetime nullable (stock)

### Files Generated/Updated
- [x] Migration: `database/migrations/2026_10_01_104753_add_institution_role_phone_status_to_users_table.php`
- [x] Model: `app/Models/User.php` (HasApiTokens, institution relation, ROLES catalogue)
- [x] Requests: `LoginRequest`, `RegisterInstitutionRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `SendVerificationCodeRequest`, `VerifyCodeRequest`
- [x] Resource: `app/Http/Resources/UserResource.php` (wrap disabled, institution embedded)
- [x] Service: `app/Services/AuthService.php` + `app/Services/Contracts/AuthServiceInterface.php`
- [x] Controller: `app/Http/Controllers/Api/AuthController.php`, `DashboardController.php`
- [x] API Routes: `routes/api/v1/auth.php` registered in `routes/api.php`
- [x] Mail: `app/Mail/VerificationCodeMail.php` + text view

### API Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/register` | Institution + admin signup, 201 + verification challenge |
| POST | `/api/v1/auth/login` | Email credentials, 401 invalid, 403 unverified/deactivated, 200 token |
| POST | `/api/v1/auth/verify/send` | Re-issue 6-digit code (throttled) |
| POST | `/api/v1/auth/verify` | Confirm code, mark verified, issue token |
| POST | `/api/v1/auth/forgot-password` | Send reset link (throttled) |
| POST | `/api/v1/auth/reset-password` | Reset with token |
| POST | `/api/v1/auth/logout` | Revoke current token (sanctum) |
| GET | `/api/v1/auth/me` | Current user + institution (sanctum) |
| GET | `/api/v1/dashboard/summary` | Institution + stat counts (sanctum) |

### Test Results
- AuthTest: 6 passed, 47 assertions (register, duplicate guard, consent guard, login gate, wrong code, me/logout/dashboard, guest guards)

### SOLID Compliance Checklist
- [x] Single Responsibility
- [x] Open/Closed
- [x] Liskov Substitution
- [x] Interface Segregation
- [x] Dependency Inversion

---

## VerificationCode - 2026-10-01 11:00:00 - Req: AUT-01, AUT-02

### Fields
- email: string indexed
- purpose: string (email_verification)
- code_hash: string (6-digit code, hashed)
- expires_at: datetime (15 min TTL)

### Files Generated/Updated
- [x] Migration: `database/migrations/2026_10_01_104754_create_verification_codes_table.php`
- [x] Model: `app/Models/VerificationCode.php`

### Notes
- Codes are single-use: consumed on successful verify, superseded on re-issue.
- Mail driver `log` in local dev: codes land in `storage/logs/laravel.log`.
- Issuing is skipped entirely while `AUTH_REQUIRE_EMAIL_VERIFICATION=false`
  (the current default). See the Account Types entry below.

---

## Account Types (personal vs institutional) - 2026-10-01 14:00:00 - Req: AUT-01, AUT-04, AUT-05, AUT-06

### The Decision
A user registers with exactly one of two account types on a single endpoint.
A **personal** account is an individual teacher with no school or employer: no
`institutions` row is created, `institution_id` stays NULL and the user is
scoped to their own records. An **institutional** account creates the
institution and issues `institution_admin`. Both are issued `teacher` and
`institution_admin` respectively from `User::ROLE_FOR_ACCOUNT_TYPE`, so the
frontend never picks a role.

### Fields Added
- `users.account_type`: string, default `institutional`, indexed - `personal` or `institutional`

### Files Generated/Updated
- [x] Migration: `database/migrations/2026_10_01_150000_add_account_type_to_users_table.php` (backfills rows with a NULL `institution_id` as `personal`)
- [x] Model: `app/Models/User.php` (`ACCOUNT_TYPES`, `ROLE_FOR_ACCOUNT_TYPE`, `isPersonal()`)
- [x] Request: `app/Http/Requests/RegisterRequest.php` (replaces `RegisterInstitutionRequest`; institution fields required only when `account_type` is `institutional`)
- [x] Service: `app/Services/AuthService.php` `register()` + `requiresEmailVerification()`, interface updated
- [x] Controller: `app/Http/Controllers/Api/AuthController.php`
- [x] Resource: `app/Http/Resources/UserResource.php` (adds `account_type`)
- [x] Factory/Seeder: `UserFactory::personal()` / `::institutional()`, `DemoSeeder` gains `teacher@custograde.test`

### API Contract
`POST /api/v1/auth/register`

| Field | Personal | Institutional |
|-------|----------|---------------|
| `account_type` | required, `personal` | required, `institutional` |
| `first_name`, `last_name` | required | required |
| `email` | required, unique across both types | required, unique across both types |
| `phone` | nullable | nullable |
| `password`, `password_confirmation` | required, min 6, confirmed | required, min 6, confirmed |
| `privacy_consent` | required, accepted | required, accepted |
| `institution_name`, `institution_type` | ignored (nullable) | required |

Response: `user`, `requires_email_verification`, `email`, and `token` (present only when verification is disabled).

### Email Verification Flag
`AUTH_REQUIRE_EMAIL_VERIFICATION` (config `auth.require_email_verification`, default **false**)
- `false`: registration mints no code, returns a working token, login skips the verified-email gate. `POST /auth/verify/send` answers 422 "Email verification is not enabled on this installation."
- `true`: registration mints a 6-digit code and returns `token: null`; login answers 403 with `requires_email_verification` until confirmed.
- The verify endpoints stay registered either way, so the flag can be flipped without a migration or route redeploy.

### Test Results
- `AccountTypeRegistrationTest`: 10 passed (both account types, unknown account_type creates nothing, cross-type email uniqueness, personal verify/login/me/dashboard, consent + password mismatch rejection, tenant scoping)
- `EmailVerificationDisabledTest`: 6 passed (token issued, no code minted, unverified login allowed, verify/send refused, inactive guard)
- `AuthTest`: 6 passed (institutional path unchanged, flag switched on per-test)
- Full suite: 24 passed, 152 assertions
- Live smoke against MySQL: both types register, `/me` and `/dashboard/summary` return `institution: null` for personal accounts without erroring, all 422 messages correct

### SOLID Compliance Checklist
- [x] Single Responsibility (`RegisterRequest` owns conditional validation; `AuthService::register` owns persistence)
- [x] Open/Closed (new account types need a `ROLE_FOR_ACCOUNT_TYPE` entry, not a controller change)
- [x] Liskov Substitution
- [x] Interface Segregation
- [x] Dependency Inversion (controller depends on `AuthServiceInterface`)

---

## Marking chain - 2026-10-03 07:30:00 - Req: EXM-01, EXM-02, STU-01, STU-03, CAP-01, CAP-07, IDN-02, IDN-05, OCR-01, OCR-04, OCR-07, AIG-01, AIG-03, AIG-11, REV-01, REV-02, REV-03, REV-04, REV-07, REV-08, REV-12, REV-13, MRK-01, MRK-02, MRK-03, MRK-04, MRK-08, MRK-09

### The vertical slice that works end to end

Verified live against a running API, a queue worker, the Python service and a
real model. A scan of a handwritten script goes in and a released result comes
out:

| Q | Handwriting on the page | What the model did |
|---|--------------------------|--------------------|
| 1 | `3x = 18 therefore x = 6` | read it, suggested 4/4 at 0.95 confidence |
| 2 | `(x - 2)(x - 3)` | read it, suggested 4/4 at 0.95 confidence |
| 3 | a hand-drawn graph | read as `non_text`, proposed **nothing** |

Q3 is the load-bearing case. A graph cannot be graded from text, so the model
declined to guess and the answer was routed to a human, who marked it by hand.
That is OCR-07 and BR-02 working together.

Before a human acted, every `mark` column was NULL. After approval the script
totalled 10 of 13 and graded A at 76.92 percent.

### Fields

- `exams`: title, type, exam_date, duration_minutes, total_marks, question_count, grading_scheme_id, blind_marking, results_visible_to_students, status
- `exam_questions`: number, prompt, kind, max_mark, granularity, model_answer, guide_points (JSON), options (JSON), answer_key (JSON)
- `students`: reg_no unique per institution, first_name, last_name, class_name, status
- `exam_enrolments`: exam_id, student_id, unique pair
- `scripts`: code unique and opaque, status, student_id, original_path, original_hash, mime_type, page_count, expected_page_count, total_mark, max_mark, annotations, locked_by, locked_at, flagged_by, flag_note
- `script_answers`: machine_text, corrected_text, transcription_confidence, content_type, truncated, suggested_mark, suggestion_confidence, suggestion_rationale, matched_points, mark, mark_source, approved_by, approved_at, reason, feedback
- `mark_events`: append-only, previous_value, new_value, source, reason, actor_id
- `results`: total_mark, max_mark, percentage, grading_scheme_id, grade, is_pass, status, release_status, version

### The human-in-the-loop rules, made structural

- `ScriptAnswer::isDecided()` requires **both** `mark` and `approved_by`. A
  value with no person attached does not count as decided, so nothing can
  become final without an authenticated human action (BR-02).
- Exactly one route in the application writes a mark:
  `POST /api/v1/scripts/{id}/answers/{answer}/mark`. The pipeline writes only
  `suggested_mark`.
- `machine_text` is never overwritten. A teacher correction sits in
  `corrected_text` beside it, so a disputed result can show what the system saw
  and what the teacher changed (OCR-04).
- A mark already approved is never overwritten by a later suggestion (EXM-07).
- Locking is refused, in order, while a question is unmarked, while a page is
  missing, or while a flag is open, and the message names the outstanding
  question numbers (REV-08, MRK-02).
- `mark_events` is append-only and written in the same transaction as the mark,
  so the trail cannot disagree with what it describes (REV-13).
- `original_path` plus `original_hash` is written once. Annotations live in
  their own column, so nothing writes back over the scan a candidate handed in
  (CAP-07).
- Results are versioned. A correction writes version 2 and keeps version 1
  (MRK-08).

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/ai/health` | AI service reachability and provider readiness (ADM-03) |
| GET/POST | `/api/v1/exams` | List and create examinations |
| GET/PUT/DELETE | `/api/v1/exams/{id}` | One examination with its questions |
| POST | `/api/v1/exams/{id}/questions` | Add a question with its marking guide |
| PUT/DELETE | `/api/v1/exams/{id}/questions/{question}` | Edit or remove a question |
| PUT | `/api/v1/exams/{id}/status` | Lifecycle transition (EXM-06) |
| POST | `/api/v1/exams/{id}/recalculate-totals` | Recompute paper totals |
| GET/POST | `/api/v1/students` | Roster (STU-01) |
| POST/DELETE | `/api/v1/exams/{id}/students` | Enrol and unenrol (STU-03) |
| GET | `/api/v1/exams/{id}/scripts` | Scripts for an examination |
| POST | `/api/v1/exams/{id}/scripts` | Upload a scan and queue it for reading (CAP-01) |
| GET | `/api/v1/scripts/{id}` | One script with its answers |
| GET | `/api/v1/scripts/{id}/image` | Short-lived signed URL for the scan (ARC-04) |
| POST | `/api/v1/scripts/{id}/reprocess` | Re-run the pipeline after a failure |
| POST | `/api/v1/scripts/{id}/answers/{answer}/mark` | **The only route that writes a mark** (REV-01) |
| PUT | `/api/v1/scripts/{id}/answers/{answer}/transcription` | Correct the reading (OCR-04) |
| POST | `/api/v1/scripts/{id}/lock` | Approve and lock (REV-08) |
| POST | `/api/v1/scripts/{id}/unlock` | Reopen, with a reason (REV-08) |
| POST | `/api/v1/scripts/{id}/flag` | Flag for investigation (REV-14) |
| GET | `/api/v1/exams/{id}/results` | Compiled results |
| GET | `/api/v1/exams/{id}/results/statistics` | Class statistics (MRK-03) |
| POST | `/api/v1/exams/{id}/results/compile` | Compile from approved scripts (MRK-01) |
| POST | `/api/v1/exams/{id}/results/release` | Release to candidates (MRK-09) |
| POST | `/api/v1/exams/{id}/results/withhold` | Withdraw a release, deleting nothing |

### Provider key isolation

The model credentials live only in `AI_Service/.env`. Laravel never holds a
provider key and the browser never reaches a model directly, so a tenant database
dump cannot leak one and no client can call a model at all (AIG-10). Laravel
reaches the service over HTTP with an optional `X-Internal-Key`.

### Failure states

| Failure | Behaviour |
|---------|-----------|
| AI unreachable | Job requeues with backoff. Manual marking unaffected (AIG-11) |
| Scan rejected by the AI | Non-retryable. Script returns to `uploaded` for a teacher |
| Exceeds max attempts | `failed()` hook leaves the script actionable rather than in `failed_jobs` |
| Question not read | Stays `pending` with no mark, never scored zero |
| Graph or diagram | `non_text`, routed to a human, confidence capped at 0.3 |
| Another school's script | 404, identical to a script that does not exist |
| No grading scheme | Compilation refuses and says why, rather than producing an ungraded result |

### Test Results

- `ScriptCaptureTest`: 4 passed (capture, unidentified paper kept, no path leak, cross-tenant refusal)
- `ScriptPipelineTest`: 6 passed (pipeline never writes a mark, decisions, variance, granularity, totals)
- `ScriptApprovalTest`: 9 passed (lock gate, graph by hand then approve, missing page, flag, locked rejects change, reopen needs reason, blind marking)
- `AcademicStructureTestCase` plus three suites: 23 passed (ACD-02 to ACD-05, tenant isolation)
- Existing auth suites: unchanged
- Full suite: 69 passed, 470 assertions
- `../contract_check.py`: every field the frontend types claim verified against the live API
- `../e2e_check.py`: real scan to released result, all checks passed

### SOLID Compliance Checklist
- [x] Single Responsibility (MarkingService decides, ScriptPipelineService proposes, ScriptCaptureService stores)
- [x] Open/Closed (AiServiceInterface hides the provider; a new transcriber changes no caller)
- [x] Liskov Substitution (fake AI in tests satisfies the real contract)
- [x] Interface Segregation (repositories, services and the AI client are separate contracts)
- [x] Dependency Inversion (controllers and jobs depend on interfaces, bound in bootstrap/providers.php)
