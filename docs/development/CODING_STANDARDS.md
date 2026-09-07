# SKSL — Coding Standards

## 1. Purpose

These standards keep the PHP MVC application secure, readable, consistent, and maintainable.

Antigravity must follow these standards for all generated code.

---

# 2. General Principles

Prefer:

- simple code
- clear naming
- small functions
- single responsibility
- reusable services
- explicit validation
- secure defaults
- minimal dependencies

Avoid:

- giant controllers
- duplicated logic
- hidden global state
- unnecessary abstractions
- framework-like complexity
- copy-paste business rules

---

# 3. PHP Standards

Use modern PHP supported by the production Hostinger environment.

Before relying on a language feature, confirm the production PHP version.

Use:

- strict comparisons where appropriate
- typed parameters/returns where practical
- clear exception handling
- small methods
- dependency injection where useful

Do not suppress errors using `@`.

Do not use dangerous dynamic execution such as `eval()`.

---

# 4. Naming

Classes:

```text
BookingService
PaymentService
AvailabilityService
```

Methods:

```text
createHold()
verifyPayment()
getAvailableSlots()
```

Variables:

```text
$bookingId
$serviceId
$bookingDate
```

Database fields should use one consistent naming convention.

---

# 5. MVC Rules

Controllers:

- receive request
- validate/normalize input
- authenticate/authorize
- call service
- return response

Services:

- contain business logic
- coordinate models/repositories
- perform important calculations

Models/repositories:

- contain database operations

Views:

- presentation only
- no SQL
- no payment processing
- no business-critical calculations

---

# 6. Database Rules

Always use PDO prepared statements.

Bad:

```php
$sql = "SELECT * FROM users WHERE email = '$email'";
```

Good:

```php
$stmt = $pdo->prepare(
    'SELECT * FROM users WHERE email = :email'
);
$stmt->execute(['email' => $email]);
```

Never build SQL with untrusted input.

Dynamic ORDER BY fields must use an allow-list.

---

# 7. Transactions

Use transactions around operations that must succeed or fail together.

Examples:

- booking hold creation
- payment confirmation
- webhook reconciliation
- booking/payment status transition

Always rollback on failure.

---

# 8. Validation

Client-side validation is for UX.

Server-side validation is authoritative.

Validate:

- type
- length
- format
- allowed values
- range
- ownership
- business rules

Never trust hidden form fields.

---

# 9. Output Escaping

Escape output based on context.

For HTML text, use appropriate HTML escaping.

Never render customer-provided text as raw HTML without an explicit, reviewed sanitization strategy.

---

# 10. Authentication

Passwords:

```php
password_hash()
password_verify()
```

Never:

- MD5 passwords
- SHA1 passwords
- plain-text passwords
- custom password hashing

Regenerate session ID after authentication.

---

# 11. Sessions

Configure secure cookies.

Production should use:

- HttpOnly
- Secure
- SameSite

Do not store unnecessary sensitive data in sessions.

---

# 12. CSRF

All state-changing session-authenticated requests require CSRF protection unless a clearly documented alternative architecture is used.

Never use GET for destructive operations.

---

# 13. Authorization

Authentication answers:

> Who are you?

Authorization answers:

> Are you allowed to do this?

Every protected operation must check authorization.

For customer resources:

```text
requested booking.user_id === authenticated user ID
```

Never rely on a URL ID alone.

---

# 14. Booking Logic

Never trust frontend:

- price
- GST
- duration
- capacity
- total
- booking status

Always retrieve authoritative service configuration from the database.

Booking availability must be rechecked immediately before creating the hold/transaction.

---

# 15. Payment Logic

Payment code must be isolated in a service.

Never mark a booking paid from frontend data alone.

Verify Razorpay:

- signature
- order
- payment
- amount
- currency
- booking relationship

Webhook handling must be idempotent.

---

# 16. Idempotency

Operations receiving repeated gateway events must produce the same final business state.

Use:

- unique gateway identifiers
- database constraints
- state checks
- transactions

Do not solve idempotency only with frontend JavaScript.

---

# 17. Money

Do not use floating-point arithmetic for authoritative currency calculations.

Use decimal-compatible database fields.

Normalize amounts before sending them to Razorpay.

Keep:

- base amount
- GST
- convenience fee
- total

explicitly stored where required for historical integrity.

---

# 18. Time

Use one documented timezone strategy.

Business timezone:

```text
Asia/Kolkata
```

Do not rely on browser timezone for authoritative booking decisions.

Validate all booking dates/times server-side.

---

# 19. API Responses

Use consistent JSON structures.

Success:

```json
{
  "success": true,
  "message": "...",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "...",
  "errors": {}
}
```

Do not expose internal exception details.

---

# 20. Exceptions

Use exceptions for unexpected failures.

Catch exceptions at appropriate application boundaries.

Log technical details safely.

Return user-friendly messages.

Never expose:

- stack traces
- SQL statements
- credentials
- server paths

---

# 21. File Uploads

Validate:

- actual MIME
- extension
- size
- image content
- filename

Generate a random stored filename.

Never trust the original filename.

Uploaded files must not become executable scripts.

---

# 22. Email

Keep SMTP configuration outside source code.

Email service should not contain business decisions.

Do not send passwords or OTP values in logs.

Admin/customer emails should be generated from trusted server-side data.

---

# 23. Logging

Use structured, useful logs.

Safe examples:

```text
booking_reference
event_type
status
timestamp
```

Never log:

```text
password
OTP
API secret
SMTP password
DB password
```

---

# 24. Comments

Comment:

- why something is unusual
- security-sensitive reasoning
- concurrency strategy
- business-rule exceptions

Do not comment obvious code.

---

# 25. Configuration

Use configuration files that read environment variables.

Never hard-code:

- DB password
- Razorpay secret
- SMTP password
- application secret

---

# 26. File Organization

Keep related responsibilities together.

Recommended:

```text
app/
├── controllers/
├── models/
├── services/
├── middleware/
├── helpers/
└── views/
```

Do not create arbitrary top-level directories without documenting their purpose.

---

# 27. Frontend JavaScript

Use modular files.

Avoid one giant `app.js`.

JavaScript should:

- call APIs
- manage UI state
- show validation
- handle loading/errors
- update UI

JavaScript must never become the authority for:

- price
- capacity
- booking confirmation
- payment verification

---

# 28. Tailwind

Use the build/CLI workflow.

Do not use Tailwind CDN in production.

Keep reusable UI patterns consistent.

Avoid excessive inline styles.

---

# 29. Dependency Rules

Before adding a Composer package:

1. Confirm it is necessary.
2. Check maintenance/reputation.
3. Check compatibility.
4. Document why it is required.

Do not add dependencies for trivial functionality that can safely be implemented with native PHP.

---

# 30. Git

Never commit:

- `.env`
- credentials
- production dumps
- private keys
- secrets

Use meaningful commit messages.

Do not rewrite shared history or perform destructive Git commands without approval.

---

# 31. Definition of Good Code

Good SKSL code is:

- secure
- understandable
- testable
- maintainable
- appropriately simple
- explicit about business rules
- resistant to malformed input
- safe under repeated requests
- safe under concurrent booking attempts
