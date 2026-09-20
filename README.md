# AuraPay

**AuraPay** is an academic demonstration prototype of a cloud-based mobile payment application developed in support of a Master's thesis on the design, implementation, security, and testing of a modern payment platform.

The prototype models selected **account-to-account (A2A)**, consent, recipient-verification, QR-ready payment-request, risk, audit, authentication, and session-security processes using **synthetic data and simulated internal services**.

> **Scope notice:** AuraPay is not a bank, payment institution, electronic-money institution, PISP, AISP, card wallet, or production payment service. It does not hold real customer funds, execute real bank transfers, perform settlement, process real card data, or connect to production banking infrastructure.

**Snapshot reviewed:** 20 September 2026

---

## 1. Current prototype scope

The current root application implements:

- user registration and password authentication through Laravel Fortify;
- Passkey / WebAuthn registration and login support;
- database-backed authenticated sessions;
- a custom **5-minute idle-session timeout** for protected browser and API routes;
- one test wallet per registered user;
- simulated wallet top-up;
- account-information and transaction-history retrieval;
- consent creation, listing, expiry, replacement, and revocation;
- enforcement of `account_info` consent for wallet and transaction-information endpoints;
- enforcement of `payment_initiation` consent for A2A and QR payment operations;
- simulated A2A transfers between AuraPay test wallets;
- Verification of Payee (VoP) simulation;
- an encrypted, short-lived payment-confirmation token that binds the authenticated user, receiver, amount, and expiry time;
- mandatory `Idempotency-Key` protection for A2A payments;
- mandatory A2A `X-Correlation-ID` validation for end-to-end traceability;
- signed QR-ready payment requests using HMAC-SHA256;
- payment-request expiry and one-time status checks;
- rule-based transaction risk scoring;
- high-risk transaction flagging without executing the wallet transfer;
- role-based administrative read access to audit logs;
- persistent audit evidence for insufficient-funds A2A failures;
- database notifications for completed and flagged transactions;
- API request-format, response-format, and payload-size validation;
- API rate limiting;
- server-side payment validation;
- wallet ownership checks;
- transactional balance updates with database row locking;
- automated feature tests for selected security controls.

All users, wallets, balances, recipients, payment requests, transactions, and funds are demonstrational.

---

## 2. Technology stack

| Area | Technology |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Authentication | Laravel Fortify |
| Passkeys | Fortify Passkeys / WebAuthn with `@laravel/passkeys` |
| Frontend | Blade, JavaScript, Tailwind CSS |
| Asset build | Vite |
| Persistence | Eloquent ORM; SQLite for local/demo use |
| Sessions | Database-backed Laravel sessions |
| Testing | PHPUnit / Laravel test framework |
| Package management | Composer, npm |

---

## 3. Repository structure and source of truth

The **root Laravel application** is the current implementation.

The archive also contains:

```text
aurapay-complete/
```

This directory is an older prototype snapshot and should **not** be treated as the source of truth for the current version.

Use the root directories:

```text
app/
bootstrap/
config/
database/
resources/
routes/
tests/
```

for current functionality.

### Main components

| Component | Main implementation |
|---|---|
| Browser demo UI | `resources/views/aurapay/dashboard.blade.php` |
| Login UI | `resources/views/auth/login.blade.php` |
| Frontend API wiring | `resources/js/aurapay.js` |
| Authentication / Passkeys | Laravel Fortify, `FortifyServiceProvider`, `User` |
| Session idle timeout | `IdleSessionTimeout` middleware |
| API gateway controls | `ApiGatewayValidation` middleware |
| API throttling | `api-gateway` rate limiter |
| Wallet operations | `WalletController`, `WalletService` |
| A2A payments | `TransactionController`, `TransactionService` |
| Payment confirmation | `PaymentConfirmationService` |
| Consent management | `ConsentController`, `ConsentService`, `EnsureConsent` |
| Verification of Payee | `VopController`, `VopService` |
| QR-ready payment requests | `QrPaymentController`, `QrSignatureService` |
| Risk rules | `RiskScoringService` |
| Audit trail | `AuditService`, `AuditLog` |
| Notifications | `TransactionNotification` |
| Administrator authorization | `EnsureAdmin` |
| Persistence | Eloquent models + Laravel migrations |

---

## 4. High-level request flow

```text
Browser / Demo UI
        |
        v
Fortify authentication
        |
        v
Laravel database-backed session
        |
        v
5-minute idle-session timeout
        |
        v
API Gateway Validation
(JSON / Accept / 50 KiB payload limit)
        |
        v
API Rate Limiter
        |
        v
Authorization / Consent
        |
        +------------------------------------+
        |                                    |
        v                                    v
Account information                     Payment flow
                                             |
                              +--------------+--------------+
                              |              |              |
                              v              v              v
                             VoP       Confirmation      Risk rules
                              |           token              |
                              +--------------+--------------+
                                             |
                                             v
                                     TransactionService
                                             |
                                             v
                                      DB transaction
                                             |
                              +--------------+--------------+
                              |                             |
                              v                             v
                          Audit trail                  Notification
```

---

## 5. Authentication and Passkeys

AuraPay uses Laravel Fortify with the Laravel `web` guard.

### Configured controls

- password authentication;
- login throttling: **5 attempts per minute** per normalized email/IP key;
- Passkey / WebAuthn support;
- passkey throttling: **10 attempts per minute**;
- Fortify two-factor authentication feature enabled in configuration;
- database-backed authenticated sessions;
- authenticated access to the dashboard and AuraPay API routes.

The `User` model implements:

```text
Laravel\Fortify\Contracts\PasskeyUser
```

and uses:

```text
Laravel\Fortify\PasskeyAuthenticatable
```

### Passkey browser wiring

The current login page contains:

```html
id="passkey-login"
```

and the dashboard contains the Passkey registration control used by:

```text
resources/js/aurapay.js
```

The frontend calls the Laravel Passkeys client for registration and login.

### Codespaces / HTTPS requirement

Passkey relying-party configuration derives its host and allowed origin from:

```text
APP_URL
```

When testing in GitHub Codespaces, `APP_URL` must match the actual HTTPS forwarded-port URL opened in the browser.

Example:

```env
APP_URL=https://<your-codespace-host>.app.github.dev
```

The application contains Codespaces-specific URL handling that forces the configured GitHub development URL to HTTPS.

---

## 6. Session security and 5-minute idle timeout

AuraPay contains a custom:

```text
App\Http\Middleware\IdleSessionTimeout
```

middleware.

It is registered as:

```text
idle.timeout
```

and applied to both:

- the authenticated dashboard;
- the protected AuraPay API route group.

### Idle-timeout rule

The middleware stores:

```text
aurapay_last_activity
```

in the authenticated session.

The maximum inactivity period is:

```text
300 seconds = 5 minutes
```

The timeout condition is:

```text
idle time >= 300 seconds
```

When the threshold is reached, AuraPay:

1. logs the user out;
2. invalidates the current session;
3. regenerates the CSRF token.

### Browser behavior

A timed-out browser request is redirected to:

```text
/login?reason=idle-timeout
```

The login page displays a message informing the user that the session expired after five minutes of inactivity.

### API behavior

A timed-out API request receives:

```json
{
  "message": "Session expired after 5 minutes of inactivity.",
  "code": "SESSION_IDLE_TIMEOUT"
}
```

with:

```text
401 Unauthorized
```

### Important distinction

Laravel's general session configuration still contains:

```env
SESSION_LIFETIME=120
```

The **5-minute AuraPay idle timeout is a separate, stricter application control** implemented by `IdleSessionTimeout`.

The current session configuration also uses:

- database session storage by default;
- HTTP-only session cookies by default;
- `SameSite=lax` by default;
- JSON session serialization.

For HTTPS deployments, configure the secure-cookie setting appropriately, for example:

```env
SESSION_SECURE_COOKIE=true
```

---

## 7. API security controls

All AuraPay API endpoints are grouped behind:

```text
web
auth
idle.timeout
api.gateway
throttle:api-gateway
```

### API gateway validation

`ApiGatewayValidation` enforces:

- JSON bodies for non-safe methods such as POST, PUT, and PATCH;
- JSON response negotiation through the `Accept` header;
- maximum request payload size of **50 KiB**.

Possible gateway responses include:

```text
406 Not Acceptable
413 Payload Too Large
415 Unsupported Media Type
```

### API rate limiting

The named `api-gateway` limiter allows:

```text
60 requests per minute
```

The limiter key uses:

- authenticated user ID when available;
- IP address otherwise.

Requests above the limit return:

```text
429 Too Many Requests
```

---

## 8. Authorization and least privilege

### Wallet ownership

A user cannot read or top up another user's wallet.

`WalletController` verifies:

```text
wallet.user_id == authenticated_user.id
```

Unauthorized access returns:

```text
403 Forbidden
```

### Administrator authorization

The endpoint:

```text
GET /api/admin/audit-logs
```

is protected by:

```text
admin
```

middleware.

Only a user whose database role is:

```text
admin
```

is allowed to read the audit-log endpoint.

### Role mass-assignment protection

The `role` field is intentionally not included in the user's mass-assignable registration fields.

### Consent ownership

A consent can only be revoked by the user who owns it.

---

## 9. Consent management

AuraPay supports two consent scopes:

```text
account_info
payment_initiation
```

The API supports:

- listing the authenticated user's consents;
- granting consent;
- revoking consent;
- checking expiration.

### Default lifetime

If the caller does not provide `ttl_minutes`, the current implementation uses:

```text
43200 minutes = 30 days
```

### One active consent per scope

When a new consent is granted, existing live consents for the same user and scope are marked:

```text
revoked
```

before the new consent is created.

### `account_info` enforcement

The following routes require active:

```text
account_info
```

consent:

```text
GET /api/wallets/{wallet}
GET /api/transactions
```

### `payment_initiation` enforcement

The following routes require active:

```text
payment_initiation
```

consent:

```text
POST /api/transactions/a2a
POST /api/qr
POST /api/qr/{reference}/pay
```

Missing or expired required consent results in:

```text
403 Forbidden
```

---

## 10. Simulated A2A payment flow

The A2A endpoint models a transfer between two internal AuraPay test wallets.

### Payment payload

The request body contains:

```json
{
  "receiver_wallet_id": 2,
  "receiver_name": "Demo Receiver",
  "amount": 25.00,
  "confirmation_token": "<server-issued-confirmation-token>"
}
```

The request also requires:

```http
Idempotency-Key: <client-generated-key>
X-Correlation-ID: <UUID>
```

### Server-side checks

The current A2A flow performs the following checks:

1. authenticated user;
2. five-minute idle-session state;
3. active `payment_initiation` consent;
4. JSON/API gateway validation;
5. `Idempotency-Key` presence and maximum length;
6. `X-Correlation-ID` presence and UUID format;
7. receiver-wallet existence;
8. receiver-name presence;
9. amount precision and range;
10. sender-wallet existence;
11. prevention of self-transfer;
12. active sender and receiver wallets;
13. idempotent replay lookup;
14. server-side exact VoP verification;
15. encrypted confirmation-token verification;
16. rule-based risk scoring;
17. simulated balance availability;
18. atomic wallet transfer.

A normal successful transaction returns:

```text
201 Created
```

A high-risk transaction is held as:

```text
flagged
```

and returns:

```text
202 Accepted
```

---

## 11. Verification of Payee (VoP)

VoP is implemented as a deterministic academic prototype.

The provided receiver name is normalized and compared with the name of the user who owns the receiver wallet.

Possible statuses are:

```text
match
close_match
no_match
unavailable
```

For actual A2A execution, the current backend requires:

```text
match
```

Any non-exact result stops the transfer.

This is a **local simulation** and is not connected to a production European VoP service.

---

## 12. Dynamic payment confirmation

After successful VoP, `VopController` creates a short-lived confirmation token through:

```text
PaymentConfirmationService
```

The token is encrypted using Laravel's `Crypt` service and binds:

- authenticated user ID;
- receiver wallet ID;
- payment amount;
- expiry timestamp.

The token expires after:

```text
5 minutes
```

Before executing the A2A payment, the backend decrypts and verifies the token again.

Changing the:

- authenticated user;
- receiver wallet;
- amount;
- or using the token after expiry

causes confirmation validation to fail.

This provides a prototype-level server-side confirmation artifact that binds the payment confirmation to the **specific amount and payee**.

---

## 13. Idempotency, transaction uniqueness, and replay protection

Every A2A payment requires:

```text
Idempotency-Key
```

with a maximum length of:

```text
100 characters
```

The implementation uses two protection layers.

### Application-level check

AuraPay looks up the combination:

```text
sender_wallet_id + idempotency_key
```

before creating a new payment.

If the same key is reused for the same receiver and amount, the existing transaction is returned instead of executing another transfer.

The response includes:

```http
X-Idempotent-Replay: true
```

If the same key is reused for a different receiver or amount, AuraPay returns:

```text
409 Conflict
```

### Database uniqueness

The database migration also defines a unique constraint on:

```text
sender_wallet_id + idempotency_key
```

Transaction references are UUIDs with a database unique constraint.

These controls protect the A2A prototype against accidental duplicate submission and repeated processing of the same logical payment.

---

## 14. Correlation IDs and end-to-end traceability

A new A2A payment requires:

```http
X-Correlation-ID: <UUID>
```

The backend rejects:

- a missing correlation ID;
- a value that is not a valid UUID.

The correlation ID is used for traceability and is:

- returned in A2A responses;
- stored in transaction metadata;
- included in completed-transaction audit context;
- included in persistent insufficient-funds failure audit context.

This allows a client request, resulting transaction, response, and relevant audit evidence to be correlated during testing and analysis.

---

## 15. QR-ready signed payment requests

The prototype implements a signed payment-request flow intended for QR-based interaction.

A payment request contains:

- UUID reference;
- receiver wallet;
- optional fixed amount;
- optional note;
- status;
- expiration time;
- HMAC-SHA256 signature.

The signature covers:

```text
reference
receiver_wallet_id
amount
expires_at
```

Verification uses:

```text
hash_equals()
```

for constant-time comparison of the expected and supplied signature values.

### QR request behavior

Invalid signatures return:

```text
403 Forbidden
```

Expired requests return:

```text
410 Gone
```

A payment request whose status is no longer:

```text
pending
```

is rejected.

After successful QR payment, the payment request is marked:

```text
completed
```

### QR scope clarification

The current UI exposes a signed **reference + signature** but does not create a graphical QR image and does not perform camera-based QR scanning.

The current feature should therefore be described as:

> **signed QR-ready payment request flow**

rather than a complete graphical QR generation and scanning subsystem.

---

## 16. QR secret configuration

`QrSignatureService` requires:

```env
AURAPAY_QR_SECRET=<secret-value>
```

The variable name is already present in the supplied:

```text
.env.example
```

Generate a development secret, for example:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Add the generated value only to the real `.env` file:

```env
AURAPAY_QR_SECRET=<generated-value>
```

Never commit the real secret.

---

## 17. Rule-based risk scoring

`RiskScoringService` implements transparent and deterministic prototype rules.

| Rule | Risk score |
|---|---:|
| Amount >= 1000 | +30 |
| Amount > 3x sender's recent completed-payment average | +25 |
| At least 5 recent transactions within 60 minutes | +25 |
| QR payment | +10 |
| Transaction between 00:00 and 05:00 | +10 |

The final score is capped at:

```text
100
```

### Risk levels

```text
0-29    low
30-59   medium
60-100  high
```

When the result is:

```text
high
```

the transaction is marked:

```text
flagged
```

and the wallet transfer is not executed.

This is an explainable academic rule set and is **not** a production fraud-detection or machine-learning model.

---

## 18. Transaction consistency and wallet locking

`TransactionService` performs payment processing inside a database transaction.

`WalletService` uses:

```text
lockForUpdate()
```

when modifying wallet rows.

The design target is:

```text
debit sender
+
credit receiver
=
one atomic transfer
```

If the sender has insufficient simulated funds, an:

```text
InsufficientFundsException
```

is raised and the financial database transaction is rolled back.

---

## 19. Audit trail, persistence, and current integrity boundary

AuraPay records audit events through:

```text
AuditService
AuditLog
```

An audit record can contain:

- related transaction ID;
- actor user ID;
- action;
- description;
- structured context;
- timestamps.

Transaction-related audit actions include:

```text
transaction.completed
transaction.flagged
transaction.failed
```

### Persistent failed-payment evidence

For A2A insufficient-funds failures, the current implementation catches the exception **outside** the rolled-back payment database transaction and writes a separate:

```text
transaction.failed
```

audit event.

This prevents the failure evidence itself from being rolled back together with the failed financial operation.

The failure context currently contains:

- failure reason;
- receiver wallet ID;
- amount;
- correlation ID.

### Audit access

Audit logs are exposed through a read endpoint protected by the `admin` role.

There is no public API route for editing or deleting audit records.

### Audit integrity clarification

The current snapshot does **not** implement:

- a cryptographic hash chain between audit records;
- digital signatures over audit records;
- WORM / immutable external audit storage;
- a database uniqueness constraint for every audit event.

Therefore, the current audit trail should be described as **application-generated, relationally traceable, role-protected, and persistent for the covered failure scenario**, but **not as cryptographically tamper-evident or immutable**.

### Correlation note

A2A correlation IDs are stored in transaction metadata and in the completed/failed audit contexts.

For high-risk `transaction.flagged` records, the current source should be rechecked before claiming that the correlation ID is present in the audit `context` itself; the transaction still carries its correlation ID in `metadata`.

---

## 20. Notifications and data minimization

AuraPay creates database notifications for:

```text
transaction.completed
transaction.flagged
```

The stored notification payload is deliberately minimal:

```text
transaction_id
status
risk_level
```

It does not include:

- payment amount;
- counterparty name;
- bank credentials;
- authentication secrets.

No notification is currently generated for:

```text
transaction.failed
```

---

## 21. Data model

Main domain and infrastructure tables include:

```text
users
wallets
consents
payment_requests
transactions
audit_logs
notifications
passkeys
sessions
```

### Main relationships

```text
User
 ├── Wallet
 ├── Consents
 ├── Passkeys
 └── Notifications

Wallet
 ├── Sent Transactions
 ├── Received Transactions
 └── Payment Requests

PaymentRequest
 └── Transaction

Transaction
 └── Audit Logs
```

The prototype does not require real:

- identity documents;
- PAN;
- CVV;
- bank credentials;
- biometric templates.

---

## 22. API endpoints

All AuraPay endpoints in the API group require an authenticated Laravel session and are subject to the five-minute idle timeout, API gateway checks, and API rate limiting.

| Method | Endpoint | Additional control |
|---|---|---|
| GET | `/api/wallets/{wallet}` | `account_info` consent + wallet ownership |
| POST | `/api/wallets/{wallet}/top-up` | wallet ownership; simulated operation |
| GET | `/api/transactions` | `account_info` consent |
| GET | `/api/admin/audit-logs` | admin role |
| GET | `/api/consents` | authenticated user |
| POST | `/api/consents` | validated consent scope |
| DELETE | `/api/consents/{consent}` | consent ownership |
| POST | `/api/vop/check` | receiver/name/amount validation; returns confirmation token on exact match |
| GET | `/api/qr/{reference}?signature=...` | valid signature + active, unexpired request |
| POST | `/api/transactions/a2a` | `payment_initiation` consent + idempotency + correlation ID + VoP + confirmation token |
| POST | `/api/qr` | `payment_initiation` consent |
| POST | `/api/qr/{reference}/pay` | `payment_initiation` consent + valid signature |

---

## 23. Automated security tests present in the repository

The current:

```text
tests/Feature/SecurityControlsTest.php
```

contains tests for:

- denial of access to another user's wallet;
- payment rejection without `payment_initiation` consent;
- A2A duplicate prevention through idempotency;
- exact VoP success and confirmation-token issuance;
- invalid QR signature rejection;
- denial of audit-log access to non-admin users;
- successful audit-log access for admins;
- API rate limiting;
- mandatory `account_info` consent;
- expired `account_info` consent rejection;
- rejection when confirmed payment parameters are changed;
- persistent audit evidence after an insufficient-funds payment failure;
- logout after more than five minutes of inactivity;
- session validity before five minutes;
- expiry at the five-minute boundary.

Run the full suite in the project environment:

```bash
php artisan test
```

or:

```bash
composer test
```

Useful additional checks:

```bash
php artisan route:list
php artisan migrate:status
composer audit
```

---

## 24. Local installation

### Requirements

- PHP 8.3 or newer;
- Composer;
- Node.js and npm;
- SQLite or another Laravel-supported relational database.

### Clone the repository

```bash
git clone https://github.com/VickyT0/AuraPay_.git
cd AuraPay_
```

### Install dependencies

```bash
composer install
npm install
```

### Create the environment file

```bash
cp .env.example .env
php artisan key:generate
```

### SQLite configuration

The demo is designed to work with SQLite:

```env
DB_CONNECTION=sqlite
```

If required:

```bash
touch database/database.sqlite
```

### Configure QR signing

Generate a secret:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Add it to `.env`:

```env
AURAPAY_QR_SECRET=<generated-value>
```

### Run migrations

```bash
php artisan migrate
```

Optional demonstration data:

```bash
php artisan db:seed
```

### Build frontend assets

```bash
npm run build
```

### Start Laravel

```bash
php artisan serve
```

For active frontend development:

```bash
npm run dev
```

---

## 25. Demonstration users

`DatabaseSeeder` creates two synthetic demonstration users and corresponding EUR wallets.

The seeded credentials are intended only for the local academic prototype.

For a cleaner demonstration, a new synthetic test user can also be created through the registration flow.

Never reuse demonstration credentials in a real system.

---

## 26. GitHub Codespaces

AuraPay can be developed and demonstrated entirely in GitHub Codespaces.

Recommended sequence:

```bash
composer install
npm install
php artisan migrate
npm run build
php artisan test
php artisan serve --host=0.0.0.0
```

Open the forwarded Laravel port through the Codespaces **Ports** panel.

For Passkeys, set:

```env
APP_URL=https://<actual-forwarded-codespace-host>
```

then clear cached configuration:

```bash
php artisan optimize:clear
```

If the Codespace host changes, the WebAuthn origin / relying-party context changes as well, so a previously registered passkey may no longer match the new development host.

---

## 27. Security-control status matrix

| Control | Current status | Main implementation / evidence |
|---|---|---|
| Authenticated API access | Implemented | Fortify + Laravel session + `auth` |
| Database-backed sessions | Implemented | `sessions` table + session config |
| 5-minute idle timeout | Implemented | `IdleSessionTimeout` + feature tests |
| Session invalidation on timeout | Implemented | logout + invalidate + CSRF regeneration |
| Login rate limiting | Implemented | Fortify `login` limiter |
| Passkey rate limiting | Implemented | Fortify `passkeys` limiter |
| Passkey browser login | Implemented in current UI wiring | `passkey-login` + `aurapay.js` |
| API rate limiting | Implemented | `api-gateway` limiter |
| JSON/content validation | Implemented | `ApiGatewayValidation` |
| Request-size limit | Implemented | 50 KiB |
| Wallet object authorization | Implemented | `WalletController` |
| Admin audit-log authorization | Implemented | `EnsureAdmin` |
| `account_info` consent | Implemented | route middleware + tests |
| `payment_initiation` consent | Implemented | route middleware + tests |
| Default consent expiry | Implemented | 30 days |
| Exact VoP before A2A | Implemented | `VopService` |
| Dynamic amount/payee confirmation | Implemented | encrypted 5-minute confirmation token |
| A2A idempotency | Implemented | controller lookup + DB unique constraint |
| A2A correlation ID | Implemented | UUID validation + metadata/response |
| Transaction-reference uniqueness | Implemented | unique UUID column |
| QR-request integrity | Implemented | HMAC-SHA256 |
| QR expiry | Implemented | `PaymentRequest::isExpired()` |
| Risk scoring | Implemented | `RiskScoringService` |
| High-risk hold | Implemented | `flagged` status; no wallet transfer |
| Atomic wallet transfer | Implemented | DB transactions + row locking |
| Audit completed transaction | Implemented | `AuditService` |
| Audit flagged transaction | Implemented | `AuditService` |
| Persistent insufficient-funds failure audit | Implemented | audit outside rolled-back payment transaction + test |
| Database notifications | Implemented | completed / flagged |
| Cryptographic audit-log hash chain | Not implemented | no previous-hash / record-hash fields |
| Immutable/WORM audit storage | Not implemented | normal relational database storage |
| Unique constraint per audit event | Not implemented | no audit-event uniqueness migration |
| Graphical QR generation/scanning | Not implemented | signed QR-ready reference only |
| Production bank / payment API | Not implemented | internal simulation |
| External-service circuit breaker | Not applicable to current local-only payment adapter | no production external-bank dependency |

---

## 28. Known prototype limitations

AuraPay should not be described as a production-ready payment platform.

Current boundaries include:

- payments occur only between internal synthetic wallets;
- there is no real bank, PSP, card network, or settlement connection;
- VoP is a local deterministic simulation;
- QR requests are signed, but no graphical QR generation or camera scanning is implemented;
- risk scoring is a simple deterministic ruleset;
- audit logs are not cryptographically chained or stored in immutable external storage;
- the audit table does not enforce a uniqueness key per logical audit event;
- correlation-ID propagation is strongest in the current A2A path and is not yet a universal requirement for every API operation;
- QR payment execution does not currently use the same confirmation-token/idempotency model as A2A;
- Fortify two-factor functionality is enabled in configuration, but the current custom demo UI is primarily focused on password and Passkey flows;
- external-service resilience controls such as circuit breakers are not meaningful until a real external adapter exists;
- automated tests cover selected security controls, not every regulatory or thesis requirement.

These limitations intentionally preserve the distinction between an **academic demonstration prototype** and a production financial system.

---

## 29. Regulatory and standards context

AuraPay is designed with reference to selected principles and requirements discussed in the thesis, including:

- PSD2 / strong customer authentication concepts;
- Verification of Payee requirements and concepts;
- GDPR data-minimization and privacy-by-design principles;
- DORA resilience and ICT-risk concepts;
- Bulgarian payment and AML-related legal context;
- FIDO2 / WebAuthn;
- NIST digital-identity guidance;
- OWASP API Security and MASVS;
- PCI DSS scope-minimization principles;
- ISO/IEC information-security principles.

These sources are **requirements and design inputs**.

AuraPay does **not** claim:

- regulatory authorization;
- payment-institution licensing;
- PCI DSS certification;
- ISO certification;
- full PSD2, DORA, GDPR, AML, or other regulatory compliance.

A real deployment would require separate legal, organizational, contractual, operational, security, privacy, and conformity assessments.

---

## 30. Thesis traceability

The prototype supports traceability in the form:

```text
Requirement
    ↓
Threat / risk
    ↓
Control
    ↓
Code component
    ↓
Test
    ↓
Evidence
```

Examples:

| Requirement area | Code control | Existing verification |
|---|---|---|
| Object authorization | wallet ownership check | BOLA-style wallet test |
| Account-information consent | `EnsureConsent` | required + expired consent tests |
| Payment consent | `EnsureConsent` | missing-payment-consent test |
| Duplicate protection | idempotency key + DB uniqueness | duplicate-payment test |
| Recipient verification | `VopService` | exact-match test |
| Amount/payee confirmation | encrypted confirmation token | changed-parameter rejection test |
| Request traceability | `X-Correlation-ID` | transaction metadata / response / audit context |
| QR integrity | HMAC signature | invalid-signature test |
| Least privilege | `EnsureAdmin` | admin / non-admin tests |
| API abuse protection | rate limiter | 61-request rate-limit test |
| Failed-operation auditability | separate persistent failure audit | persistent-audit test |
| Session inactivity | 5-minute idle middleware | before/at/after timeout tests |

This mapping provides a practical foundation for the formal requirements and traceability matrices in the Master's thesis.

---

## 31. Repository hygiene

Do not commit runtime secrets or disposable local artifacts.

At minimum, exclude:

```text
.env
vendor/
node_modules/
database/database.sqlite
storage/logs/*.log
.phpunit.result.cache
```

The reviewed snapshot contains local runtime artifacts such as:

```text
database/database.sqlite
storage/logs/laravel.log
.phpunit.result.cache
```

These may be useful in a private backup but should normally not be committed to a public repository.

The reviewed snapshot does not contain a root `.gitignore`. Before publishing or cleaning the repository, add or restore an appropriate Laravel `.gitignore`.

The following files/directories are documentation or older snapshots rather than runtime dependencies:

```text
AGENTS.md
RM-OLD.md
aurapay-complete/
```

Remove them only if they are no longer useful for project history or documentation.

---

## 32. Academic purpose

AuraPay is an engineering and research artifact.

Its purpose is to demonstrate how requirements derived from the analysis of mobile-payment systems can be transformed into:

- a defined system boundary;
- executable software architecture;
- explicit security controls;
- consent and authentication mechanisms;
- transaction-integrity controls;
- a threat and risk model;
- testable acceptance criteria;
- repeatable test scenarios;
- traceable technical evidence.

The project prioritizes:

- security-by-design;
- testability;
- traceability;
- explicit control behavior;
- explainable risk rules;
- clear separation between implemented controls and prototype limitations.

---

## Repository

**Project:** AuraPay — Cloud-Based Mobile Payment Application Prototype  
**Repository:** `VickyT0/AuraPay_`  
**Purpose:** Master's thesis demonstration prototype
