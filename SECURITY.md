# Security

Authorization is server-side and layered. RBAC package installation is never
assumed to be sufficient — every policy is tested manually (spec #46).

## RBAC (spec #7)

- **Spatie Laravel Permission** — granular permissions and roles.
- **Filament Shield** — generates per-resource permissions and wires the panel.
- **Laravel Policies + Gates** — record-level and field-level checks.

Permissions are granular and verb-scoped, e.g.
`products.view/create/update/delete`, `sales.view/create/approve/cancel`,
`purchase.approve`, `inventory.adjust/transfer`, `accounting.post_journal`,
`accounting.reopen_period`, `reports.view/export`, `users.disable`.

Roles are **data, not code** — administrators create custom roles. Business logic
never checks a hard-coded role name (spec #7, #51); it checks permissions.

## Company isolation (spec #6)

A global Eloquent `CompanyScope` constrains every company-scoped query to the
authenticated user's authorized company, resolved server-side from
`user_company` membership. Client-supplied `company_id` is ignored. Cross-company
access is explicitly tested and must fail.

## Field-level / data-level security (spec #8)

Page-level permission is not enough. Sensitive fields — cost price, profit
margin, supplier cost, internal valuation, employee salary — are **omitted from
the query/serialized payload** for unauthorized users, not merely hidden with
CSS. Example configurable policy:

| Role | Sales | Selling price | Cost | Margin | Salary | Accounting |
|---|---|---|---|---|---|---|
| Sales Rep | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Accountant | ✓ | ✓ | ✓ | ✓ | ✗ | ✓ |
| HR | ✗ | ✗ | ✗ | ✗ | ✓ | restricted |
| Owner | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

Implemented via policy-gated attribute visibility on models + column selection in
Filament resources, so unauthorized data never leaves the server.

## Authentication (spec #42)

Laravel's built-in auth — bcrypt password hashing (`BCRYPT_ROUNDS`), secure
sessions, password-reset flow, account disabling, login throttling
(brute-force protection). No custom cryptography. `SESSION_SECURE_COOKIE=true`
behind HTTPS.

## Threat coverage (spec #41)

| Threat | Control |
|---|---|
| SQL injection | Eloquent/query builder parameterization; no raw string interpolation |
| XSS | Blade/Livewire auto-escaping; sanitized rich text |
| CSRF | Laravel CSRF middleware on stateful requests |
| Broken access control | Policies on every resource + tests |
| Privilege escalation | permission checks server-side; no client authority |
| IDOR | company scope + policy `authorize` on every record fetch |
| Mass assignment | explicit `$fillable`; sensitive fields guarded |
| Brute force | login rate limiting + account lockout |
| Weak password storage | bcrypt, never plaintext |
| Sensitive data exposure | field-level policies; secrets only in env |

## Secrets

All secrets via environment variables (`.env`, git-ignored). Nothing sensitive is
committed. Logs never contain passwords, tokens, or secrets (spec #56).

## Audit (spec #30)

Immutable audit log records user, action, entity, entity id, timestamp,
old/new values, IP, and context for: invoice create/cancel, stock adjustment,
purchase approval, permission changes, payment, journal posting, period
close/reopen, and configuration changes. Ordinary users cannot modify audit
records (append-only table + no update/delete code path).

## Health & logging (spec #56)

Structured logs for auth events, errors, and critical business events. Health
endpoints `/health` and `/health/database` for liveness/DB connectivity.
