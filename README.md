# AuraPay

**AuraPay** is an academic demonstration prototype of a cloud-based mobile payment application.  
It was developed to support a Master's thesis focused on the design, implementation, security, and testing of a modern payment platform.

The prototype models selected **account-to-account (A2A)** and open-banking-like processes using **synthetic data and simulated internal services**. Its purpose is to demonstrate how functional, security, privacy, resilience, and regulatory-derived requirements can be translated into software components, controls, and repeatable tests.

> **Scope notice:** AuraPay is not a bank, payment institution, electronic-money institution, PISP, AISP, card wallet, or production payment service. It does not hold real customer funds, execute real bank transfers, perform settlement, or process real card data.

---

## 1. Current prototype scope

The current codebase implements:

- user registration and password authentication;
- Laravel Fortify session-based authentication;
- Passkey/WebAuthn support and passkey registration;
- one test wallet per registered user;
- simulated wallet top-up;
- transaction history;
- consent creation, listing, and revocation API;
- `payment_initiation` consent enforcement;
- simulated A2A transfers between AuraPay test wallets;
- Verification of Payee (VoP) simulation;
- idempotency protection for A2A payments;
- signed payment requests intended for QR-based payment flows;
- payment-request expiration checks;
- rule-based transaction risk scoring;
- high-risk transaction flagging;
- role-based administrative access to audit logs;
- audit events for completed and flagged transactions;
- database notifications for completed and flagged transactions;
- API request-format and payload-size validation;
- API rate limiting;
- server-side validation of payment inputs;
- wallet ownership checks;
- transactional balance updates with database locking;
- automated feature tests for selected security controls.

All balances, users, recipients, transactions, and payment requests are demonstrational.

---

## 2. Technology stack

| Area | Technology |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Authentication | Laravel Fortify |
| Passkeys | Laravel Passkeys / WebAuthn |
| Frontend | Blade, JavaScript, Tailwind CSS |
| Asset build | Vite |
| Persistence | Eloquent ORM; SQLite for local/demo development |
| Testing | PHPUnit / Laravel test framework |
| Package management | Composer, npm |

---

## 3. Architecture

The root Laravel application is the current implementation.

> The archive also contains an `aurapay-complete/` directory representing an older prototype snapshot. The root `app/`, `routes/`, `resources/`, `database/`, and `tests/` directories should be treated as the source of truth for the current version.

### Main components

| Component | Implementation |
|---|---|
| Browser demo UI | `resources/views/aurapay/dashboard.blade.php` |
| Frontend API wiring | `resources/js/aurapay.js` |
| Authentication | Laravel Fortify + Passkeys |
| API gateway controls | `ApiGatewayValidation` middleware |
| API throttling | `api-gateway` rate limiter |
| Wallet/account operations | `WalletController`, `WalletService` |
| A2A payments | `TransactionController`, `TransactionService` |
| Consent management | `ConsentController`, `ConsentService`, `EnsureConsent` |
| Verification of Payee | `VopController`, `VopService` |
| QR-style payment requests | `QrPaymentController`, `QrSignatureService` |
| Risk rules | `RiskScoringService` |
| Audit trail | `AuditService`, `AuditLog` |
| Notifications | `TransactionNotification` |
| Administrator authorization | `EnsureAdmin` |
| Persistence | Eloquent models + Laravel migrations |

### Request flow

```text
Browser / Demo UI
        |
        v
Laravel session authentication
        |
        v
API Gateway Validation
(JSON / Accept / payload limit)
        |
        v
Rate Limiter
        |
        v
Authorization / Consent
        |
        +-------------------------+
        |                         |
        v                         v
Wallet / Consent             Payment Flow
                                  |
                    +-------------+-------------+
                    |             |             |
                    v             v             v
                   VoP        Risk Rules    QR Signature
                    |             |             |
                    +-------------+-------------+
                                  |
                                  v
                         Transaction Service
                                  |
                                  v
                          Database Transaction
                                  |
                    +-------------+-------------+
                    |                           |
                    v                           v
                Audit Log                 Notification
```

---

## 4. Authentication and session security

AuraPay uses the Laravel `web` guard and Fortify.

Configured authentication-related controls include:

- password authentication;
- login throttling: **5 attempts per minute** per email/IP key;
- Passkey/WebAuthn support;
- passkey throttling: **10 attempts per minute**;
- two-factor authentication support in Fortify configuration;
- database-backed sessions;
- authenticated access to the dashboard and API routes.

Passkey configuration derives the relying-party ID and allowed origin from `APP_URL`.

### Passkey note

Passkey registration is wired to the dashboard through:

```text
resources/js/aurapay.js
resources/views/aurapay/dashboard.blade.php
```

The JavaScript passkey login handler expects a login button with:

```html
id="passkey-login"
```

In the supplied snapshot, the visible **Login with Passkey** button in `resources/views/auth/login.blade.php` does not currently contain this ID. Therefore, passkey registration is present, but the custom login button requires this small markup correction before the complete browser-based passkey login flow can be considered wired end-to-end.

---

## 5. API security controls

All AuraPay API endpoints are grouped behind:

```text
web
auth
api.gateway
throttle:api-gateway
```

### API gateway validation

`ApiGatewayValidation` enforces:

- JSON request bodies for POST/PUT/PATCH operations;
- `Accept: application/json`;
- a maximum request payload of **50 KiB**.

Possible responses include:

```text
406 Not Acceptable
413 Payload Too Large
415 Unsupported Media Type
```

### Rate limiting

The API gateway limiter allows:

```text
60 requests per minute
```

The key is based on the authenticated user ID, or the IP address for an unauthenticated request.

Requests above the limit return:

```text
429 Too Many Requests
```

---

## 6. Authorization

### Wallet ownership

A user cannot read or top up another user's wallet.  
`WalletController` explicitly verifies that:

```text
wallet.user_id == authenticated_user.id
```

Unauthorized access returns:

```text
403 Forbidden
```

### Administrative access

`/api/admin/audit-logs` is protected by the `admin` middleware.

Only users with:

```text
role = admin
```

can access the endpoint.

### Consent ownership

A consent can only be revoked by the user who owns it.

---

## 7. Consent management

AuraPay supports the following consent scopes:

```text
account_info
payment_initiation
```

The API supports:

- listing consents;
- granting consent;
- revoking consent;
- optional consent expiration.

The current protected payment routes require an active:

```text
payment_initiation
```

consent.

A payment attempt without this consent returns:

```text
403 Forbidden
```

### Current consent limitation

`account_info` can be created, but the current wallet and transaction-information endpoints do not enforce it through `EnsureConsent`.

Also, when `ttl_minutes` is omitted, the current controller passes `null` to `ConsentService`; consequently the created consent may have no expiration time. This should be aligned with the thesis requirement if mandatory consent expiry is required.

---

## 8. Simulated A2A payments

The A2A endpoint models a transfer between two AuraPay test wallets.

Required input:

```json
{
  "receiver_wallet_id": 2,
  "receiver_name": "Demo Receiver",
  "amount": 25.00
}
```

The request must also contain:

```http
Idempotency-Key: <client-generated-key>
```

The service verifies:

1. authenticated sender;
2. active payment consent;
3. request format and input data;
4. sender wallet existence;
5. receiver wallet existence;
6. sender and receiver are different wallets;
7. both wallets are active;
8. exact Verification of Payee match;
9. idempotency state;
10. risk score;
11. sufficient simulated balance;
12. atomic balance transfer.

Successful transactions return:

```text
201 Created
```

High-risk transactions are flagged and return:

```text
202 Accepted
```

---

## 9. Verification of Payee (VoP)

VoP is implemented as a deterministic prototype service.

The provided recipient name is normalized and compared with the name of the user who owns the receiver wallet.

Possible results:

```text
match
close_match
no_match
unavailable
```

For A2A transfer execution, the current implementation requires:

```text
match
```

Any non-exact result stops the transfer with a validation response.

This is a simulation of the recipient-verification step and is not connected to a production VoP service.

---

## 10. Idempotency and replay protection

Every A2A transfer requires an `Idempotency-Key`.

The implementation provides two layers of protection:

1. application-level lookup by sender wallet + idempotency key;
2. database unique constraint on:

```text
sender_wallet_id + idempotency_key
```

If the same key is reused for the same receiver and amount, AuraPay returns the existing transaction and adds:

```http
X-Idempotent-Replay: true
```

If the same key is reused for a different amount or receiver, the API returns:

```text
409 Conflict
```

The maximum idempotency-key length is **100 characters**.

---

## 11. QR-style payment requests

The prototype implements a signed payment-request flow intended to support QR-based interaction.

A payment request contains:

- UUID reference;
- receiver wallet;
- optional fixed amount;
- optional note;
- status;
- expiry time;
- HMAC-SHA256 signature.

The signature protects:

```text
reference
receiver_wallet_id
amount
expires_at
```

Verification uses `hash_equals()` to avoid ordinary string-comparison timing differences.

Invalid signatures return:

```text
403 Forbidden
```

Expired payment requests return:

```text
410 Gone
```

Used or inactive requests are rejected.

### QR scope clarification

The current UI exposes the **reference and signature**, but does not render a graphical QR image.  
The current implementation should therefore be described as a **signed QR-ready payment request**, rather than a complete visual QR-code generation/scanning subsystem.

---

## 12. QR secret configuration

`QrSignatureService` requires:

```env
AURAPAY_QR_SECRET=<secret-value>
```

This value is currently required by the code but is not present in the supplied `.env.example`.

Generate a development secret, for example:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Then add the generated value to `.env`:

```env
AURAPAY_QR_SECRET=your_generated_secret
```

For repository hygiene, also add the variable name with an empty value to `.env.example`:

```env
AURAPAY_QR_SECRET=
```

Never commit the real secret.

---

## 13. Rule-based risk scoring

`RiskScoringService` implements transparent prototype rules.

### Current rules

| Rule | Score |
|---|---:|
| Amount >= 1000 | +30 |
| Amount > 3x sender's recent completed-payment average | +25 |
| At least 5 recent transactions within 60 minutes | +25 |
| QR payment | +10 |
| Transaction between 00:00 and 05:00 | +10 |

The final score is capped at **100**.

### Risk levels

```text
0-29   low
30-59  medium
60-100 high
```

A high-risk transaction is marked:

```text
flagged
```

and the wallet transfer is not executed.

This model is deliberately explainable and deterministic. It is not a production fraud-detection or machine-learning model.

---

## 14. Transaction consistency

`TransactionService` executes payment processing inside a database transaction.

`WalletService` uses:

```text
lockForUpdate()
```

when debiting or crediting wallet rows.

The intended result is an all-or-nothing transfer:

```text
debit sender
+
credit receiver
=
single atomic operation
```

If the sender has insufficient funds, an `InsufficientFundsException` is raised and the database transaction is rolled back.

---

## 15. Audit trail and notifications

Audit records currently include:

- transaction reference through the relationship;
- actor user ID;
- action;
- description;
- structured context.

Current transaction audit actions include:

```text
transaction.completed
transaction.flagged
transaction.failed
```

Database notifications are generated for:

```text
transaction.completed
transaction.flagged
```

The notification payload is deliberately minimal:

```text
transaction_id
status
risk_level
```

It does not include amount or counterparty details.

### Audit limitation

Because payment processing is wrapped in a database transaction and an insufficient-funds exception is re-thrown, failed-operation records created inside the same transaction may be rolled back together with the failed payment. Persistent audit evidence for failed transfers should therefore be reviewed if the thesis requires every failed attempt to remain recorded.

---

## 16. Data model

Main domain tables:

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

### Key relationships

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

## 17. API endpoints

All endpoints below require an authenticated Laravel session.

| Method | Endpoint | Additional control |
|---|---|---|
| GET | `/api/wallets/{wallet}` | wallet ownership |
| POST | `/api/wallets/{wallet}/top-up` | wallet ownership |
| GET | `/api/transactions` | authenticated user's wallet |
| GET | `/api/admin/audit-logs` | admin role |
| GET | `/api/consents` | authenticated user |
| POST | `/api/consents` | validated scope |
| DELETE | `/api/consents/{consent}` | consent ownership |
| POST | `/api/vop/check` | validated receiver |
| GET | `/api/qr/{reference}?signature=...` | valid signature + active request |
| POST | `/api/transactions/a2a` | `payment_initiation` consent + idempotency + exact VoP |
| POST | `/api/qr` | `payment_initiation` consent |
| POST | `/api/qr/{reference}/pay` | `payment_initiation` consent + valid signature |

---

## 18. Local installation

### Requirements

- PHP 8.3 or newer;
- Composer;
- Node.js and npm;
- SQLite or another Laravel-supported relational database.

### Clone and install

```bash
git clone https://github.com/VickyT0/AuraPay_.git
cd AuraPay_

composer install
npm install
```

Create the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

For local SQLite:

```env
DB_CONNECTION=sqlite
```

Create the database if necessary:

```bash
touch database/database.sqlite
```

Generate and configure a QR signing secret:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Add it to `.env`:

```env
AURAPAY_QR_SECRET=<generated-value>
```

Run migrations:

```bash
php artisan migrate
```

Optional demo data:

```bash
php artisan db:seed
```

Build frontend assets:

```bash
npm run build
```

Start Laravel:

```bash
php artisan serve
```

For active frontend development:

```bash
npm run dev
```

---

## 19. Demo users

`DatabaseSeeder` currently defines two synthetic demonstration users.

Because these credentials are stored in source code, they must be treated as **demo-only** and must never be reused for real systems.

Alternatively, create a new test user through the registration page.

---

## 20. GitHub Codespaces

AuraPay can be run entirely in GitHub Codespaces.

For WebAuthn/passkey testing, `APP_URL` must match the HTTPS URL opened in the browser.

Example:

```env
APP_URL=https://<your-codespace-host>.app.github.dev
```

The application contains Codespaces-specific URL handling that forces the configured GitHub development URL to HTTPS.

If the Codespace host changes, the WebAuthn relying-party origin also changes, so previously registered passkeys may no longer match the new environment.

---

## 21. Running the tests

After installing Composer dependencies:

```bash
php artisan test
```

You can also use:

```bash
composer test
```

The supplied feature test suite includes explicit checks for:

- wallet object-level authorization;
- mandatory payment consent;
- A2A idempotency;
- exact VoP match;
- rejection of an invalid QR signature;
- denial of audit-log access to non-admin users;
- successful audit-log access for admins;
- API rate limiting.

Useful additional checks:

```bash
php artisan route:list
php artisan migrate:status
composer audit
```

The supplied PHP source files pass PHP syntax validation in the reviewed archive. The full Laravel test suite should still be executed in the project environment after dependencies are installed.

---

## 22. Security controls implemented in code

| Control | Status | Main implementation |
|---|---|---|
| Authenticated API access | Implemented | Laravel session + `auth` |
| Login rate limiting | Implemented | Fortify limiter |
| API rate limiting | Implemented | `api-gateway` limiter |
| JSON/content validation | Implemented | `ApiGatewayValidation` |
| Request-size limit | Implemented | 50 KiB gateway limit |
| Wallet object authorization | Implemented | `WalletController` |
| Admin authorization | Implemented | `EnsureAdmin` |
| Payment consent | Implemented | `EnsureConsent` |
| Input validation | Implemented | Laravel request validation |
| Exact VoP before A2A | Implemented | `VopService` |
| A2A idempotency | Implemented | controller + DB constraint |
| QR-request integrity | Implemented | HMAC-SHA256 |
| QR expiry | Implemented | `PaymentRequest::isExpired()` |
| Risk scoring | Implemented | `RiskScoringService` |
| High-risk hold | Implemented | `flagged` transaction state |
| Atomic transfer | Implemented | DB transactions + row locking |
| Audit for completed/flagged payments | Implemented | `AuditService` |
| Minimal database notification | Implemented | `TransactionNotification` |
| Passkey registration | Implemented | Fortify/Passkeys + dashboard JS |
| Passkey custom login button | Needs small UI fix | missing `id="passkey-login"` |
| `account_info` consent enforcement | Not currently enforced | scope exists but is not middleware-protected |
| Signed dynamic confirmation binding amount + payee | Not separately implemented | payment validation/VoP exists, but no confirmation artifact |
| External bank / production API adapter | Not implemented | internal simulation only |
| Timeout / circuit-breaker integration | Not implemented | no external-bank integration exists |
| Graphical QR generation/scanning | Not implemented | signed QR-ready reference only |
| Persistent audit of rolled-back failures | Needs review | failed event may roll back with transaction |

---

## 23. Known prototype limitations

The current code should **not** be described as a production-ready payment platform.

Important boundaries include:

- A2A transfers occur only between AuraPay's internal test wallets;
- no real bank or payment-service API is connected;
- VoP is a local deterministic simulation;
- QR requests are signed, but no graphical QR encoding/scanning layer is currently included;
- `account_info` consent is modeled but not enforced on account-information endpoints;
- consent expiry is optional in the current implementation;
- a separate cryptographic confirmation object binding amount and payee is not implemented;
- external-service resilience mechanisms such as timeout policy and circuit breaker are not yet present because there is no real external-bank adapter;
- passkey registration is wired, but the custom passkey login button needs the expected HTML ID;
- automated tests cover selected controls rather than every thesis requirement.

These limitations are intentional to document the difference between the **demonstration prototype** and a production financial system.

---

## 24. Regulatory and standards context

AuraPay is designed with reference to selected requirements and principles from:

- PSD2 and Strong Customer Authentication concepts;
- Regulation (EU) 2024/886 and Verification of Payee;
- GDPR;
- DORA;
- Bulgarian payment and AML legislation;
- FIDO2 / WebAuthn;
- NIST digital identity guidance;
- OWASP API Security and MASVS;
- PCI DSS principles;
- ISO/IEC information-security principles.

These sources are used as **requirements and design inputs**.

AuraPay does **not** claim:

- regulatory authorization;
- payment-institution licensing;
- PCI DSS certification;
- ISO certification;
- complete PSD2/DORA/GDPR compliance.

A real deployment would require a separate legal, organizational, contractual, operational, security, and conformity assessment.

---

## 25. Thesis traceability

The project is intended to support traceability from requirement to evidence:

```text
Requirement
    ↓
Risk / threat
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
| Object authorization | wallet ownership check | wallet BOLA feature test |
| Payment consent | `EnsureConsent` | missing-consent feature test |
| Duplicate protection | idempotency key + DB uniqueness | duplicate-payment feature test |
| Recipient verification | `VopService` | exact-match feature test |
| QR integrity | HMAC signature | invalid-signature feature test |
| Least privilege | `EnsureAdmin` | admin/non-admin tests |
| API abuse protection | rate limiter | 61-request rate-limit test |

This mapping provides a foundation for the formal traceability matrix used in the Master's thesis.

---

## 26. Repository hygiene

Do not commit runtime secrets or local-development artifacts.

At minimum exclude:

```text
.env
vendor/
node_modules/
database/database.sqlite
storage/logs/*.log
.phpunit.result.cache
```

The reviewed archive contains local runtime artifacts such as the SQLite database, Laravel log, and PHPUnit cache. These are useful for a local snapshot but should normally not be committed to a public source repository.

---

## 27. Academic purpose

AuraPay is an engineering and research artifact.

Its purpose is to demonstrate that requirements derived from the analysis of mobile-payment systems can be transformed into:

- a defined system boundary;
- an executable software architecture;
- explicit security controls;
- a threat and risk model;
- testable acceptance criteria;
- repeatable test scenarios;
- traceable technical evidence.

The project prioritizes **testability, traceability, explainable controls, security-by-design, and explicit scope limitations** over production-scale payment integration.

---

## Repository

**Project:** AuraPay — Cloud-Based Mobile Payment Application Prototype  
**Repository:** `VickyT0/AuraPay_`
