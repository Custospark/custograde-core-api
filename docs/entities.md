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
