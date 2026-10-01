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
