# SKSL — System Architecture

## 1. Purpose

This document defines the technical architecture for the SKSL Online Booking & Payment MVP.

The architecture must remain lightweight, secure, maintainable, and suitable for PHP/MySQL deployment on Hostinger shared hosting.

The SKSL MVP Development Plan and Master Development Instruction remain the primary sources of truth.

---

## 2. Architecture Goals

The system must provide:

- Clear separation of concerns
- Secure customer/admin authentication
- Reliable booking availability
- Capacity protection under concurrent requests
- Secure Razorpay payment processing
- Idempotent webhook processing
- Reliable invoice generation
- Reliable email notifications
- Simple deployment on shared hosting
- Minimal external dependencies
- Maintainable code for a small development team

---

## 3. High-Level Architecture

```text
Customer Browser
      |
      | HTTPS
      v
Public Web Application
      |
      v
PHP Front Controller / Router
      |
      +-------------------------+
      |                         |
      v                         v
Customer Controllers       Admin Controllers
      |                         |
      v                         v
Application Services       Admin Services
      |                         |
      +-------------+-----------+
                    |
                    v
              Data / Models
                    |
                    v
                 MySQL
                    |
        +-----------+-----------+
        |           |           |
        v           v           v
    Razorpay     SMTP/Email    mPDF
```

External payment webhook:

```text
Razorpay
   |
   | HTTPS POST
   v
Webhook Endpoint
   |
   v
Signature Verification
   |
   v
Idempotency Check
   |
   v
Payment / Booking Transaction
   |
   v
MySQL
```

---

## 4. Application Layers

### Presentation Layer

Contains:

- HTML views
- Tailwind CSS
- Vanilla JavaScript
- Fetch/AJAX

Responsibilities:

- Display UI
- Collect user input
- Provide client-side UX validation
- Display server responses

It must NOT contain authoritative business rules.

---

### Routing Layer

Responsible for mapping HTTP requests to controllers.

Examples:

```text
GET  /services
GET  /availability
POST /api/register
POST /api/login
POST /api/bookings/hold
POST /api/payment/create-order
POST /api/payment/verify
POST /api/payment/webhook
```

Admin routes are isolated under `/admin`.

---

### Controller Layer

Controllers should:

1. Validate request method.
2. Authenticate user where required.
3. Validate/normalize input.
4. Call the appropriate service.
5. Return a safe response.

Controllers should remain thin.

Do not put complex booking/payment calculations directly into controllers.

---

### Service Layer

Business logic belongs here.

Examples:

- `BookingService`
- `AvailabilityService`
- `PaymentService`
- `InvoiceService`
- `EmailService`
- `AuthService`
- `AdminOtpService`
- `ServiceManagementService`

A service should have one clear responsibility.

---

### Model / Repository Layer

Responsible for database operations.

Use:

- PDO
- prepared statements
- transactions where required

Do not allow views or JavaScript to execute database queries.

---

### Middleware

Examples:

- Customer authentication
- Admin authentication
- CSRF validation
- Request validation
- Rate limiting where applicable

Authorization must be enforced server-side.

---

## 5. Customer Request Flow

Example:

```text
Customer
  |
  | POST /api/bookings/hold
  v
Router
  |
  v
Customer Auth Middleware
  |
  v
Booking Controller
  |
  v
Booking Service
  |
  +--> Validate service
  +--> Validate date
  +--> Validate time
  +--> Check closed date
  +--> Check operating hours
  +--> Check service status
  +--> Recalculate duration/price
  +--> Check capacity
  +--> Create temporary hold
  |
  v
MySQL Transaction
  |
  v
JSON response
```

---

## 6. Admin Request Flow

```text
Admin Browser
    |
    v
/admin/
    |
    v
Admin Authentication
    |
    v
Admin Middleware
    |
    v
Admin Controller
    |
    v
Admin Service
    |
    v
Model/Repository
    |
    v
MySQL
```

Every admin API must verify an authenticated admin session.

---

## 7. Booking Architecture

The booking engine is the most sensitive business component.

Availability must be calculated from:

- Service configuration
- Operating hours
- Closed dates
- Existing active bookings
- Existing active holds
- Service duration
- Approved buffer rules
- Capacity

The exact buffer and last-slot rules remain pending client confirmation.

---

## 8. Booking State Flow

Normal payment flow:

```text
Customer selects slot
        |
        v
Availability validation
        |
        v
Temporary hold
        |
        v
Pending payment
        |
        +------ payment failed ------> Hold released
        |
        +------ hold expires -------> Hold expired
        |
        v
Payment verified
        |
        v
Booking confirmed
        |
        v
Invoice + email
```

Later operational states may include:

```text
confirmed -> completed
confirmed -> cancelled
```

Exact cancellation/refund behavior is pending.

---

## 9. Payment Architecture

Browser payment result is never authoritative.

The server must:

1. Calculate expected amount.
2. Create Razorpay order.
3. Store order relationship.
4. Receive payment information.
5. Verify signature.
6. Verify amount/currency/order relationship.
7. Process webhook.
8. Ensure idempotency.
9. Update payment state.
10. Confirm booking only when payment requirements are satisfied.

---

## 10. Idempotency

Payment and webhook processing must be safe to repeat.

Example:

```text
Webhook #1
   |
   +--> payment pending -> paid
   +--> booking pending -> confirmed

Webhook #2 (duplicate)
   |
   +--> detect already processed
   +--> do not create duplicate booking
   +--> do not create duplicate payment
   +--> do not generate duplicate invoice
   +--> do not send duplicate confirmation
```

Idempotency must be implemented at the database/application level, not assumed from frontend behavior.

---

## 11. Invoice Architecture

Invoice generation should occur only after the booking/payment state is sufficiently confirmed according to the approved payment flow.

```text
Confirmed Payment
       |
       v
InvoiceService
       |
       v
mPDF
       |
       v
Protected Invoice Storage
       |
       +--> Email attachment
```

Invoice files must not be publicly guessable.

---

## 12. Email Architecture

Use PHPMailer + SMTP.

Recommended flow:

```text
Booking confirmed
      |
      v
EmailService
      |
      +--> Generate/locate invoice
      |
      v
SMTP
      |
      v
Customer
```

Admin notification can use the same email service.

Email failure must not roll back a successful payment merely because SMTP is temporarily unavailable.

The system should record/log the failure safely and allow operational retry if such functionality is later implemented.

---

## 13. Storage Architecture

Recommended:

```text
storage/
├── invoices/
├── uploads/
└── logs/
```

Sensitive generated files should not be directly publicly accessible.

If files must be served through the application, verify authorization before download.

---

## 14. Configuration Architecture

Configuration should be separated from business code.

Example:

```text
config/
├── app.php
├── database.php
├── mail.php
└── razorpay.php
```

Secrets should come from environment variables.

Example:

```text
.env
.env.example
```

Never hard-code credentials.

---

## 15. Error Handling

Use centralized application error handling where practical.

Development:

- Detailed internal logging

Production:

- Safe user-facing messages
- No stack traces
- No SQL errors
- No filesystem paths
- No credentials

---

## 16. Time Handling

Business timezone:

```text
Asia/Kolkata
```

All booking calculations must use a consistent timezone strategy.

Do not mix server timezone, browser timezone, and database timezone casually.

Normalize incoming booking date/time before availability calculations.

Document the chosen database timestamp strategy before implementation.

---

## 17. Security Boundaries

### Public

- Home
- Service listing
- Login
- Registration
- Password reset

### Authenticated customer

- Profile
- Availability
- Holds
- Bookings
- Payment initiation
- Own invoice access

### Authenticated admin

- Dashboard
- Bookings
- Services
- Closed dates

### Internal

- Configuration
- Logs
- Environment secrets
- Database credentials
- Invoice storage where protected

---

## 18. Deployment Architecture

```text
Git Repository
      |
      v
Hostinger
      |
      +--> Public web root
      |
      +--> PHP application
      |
      +--> MySQL
      |
      +--> Cron
      |
      +--> SMTP
      |
      +--> Razorpay webhook
```

Production deployment must ensure sensitive directories/files are not publicly exposed.

---

## 19. Cron Architecture

Hostinger Cron Jobs will be used for maintenance tasks such as:

```text
cron/expire-holds.php
```

The task should:

- Find expired active holds
- Mark/release them
- Keep availability accurate
- Log safe operational information

The application should still check expiry during availability/booking operations; cron must not be the only mechanism preventing stale holds.

---

## 20. Architecture Rules

- Controllers remain thin.
- Business logic belongs in services.
- Database logic belongs in models/repositories.
- Views never contain database queries.
- JavaScript never determines authoritative price or availability.
- Payment confirmation never depends only on browser callbacks.
- Webhooks are idempotent.
- Booking capacity is protected by database-level/concurrency-aware logic.
- Secrets never enter source control.
- Sensitive files are protected.
- All major architectural changes must be documented.
