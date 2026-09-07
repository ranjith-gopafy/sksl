# SKSL Error Handling and Logging

**Project:** Sara Kinetic Sports Lab (SKSL)  
**Version:** 1.0  
**Status:** MVP Standard

---

## 1. Purpose

This document defines how application errors, payment failures, booking failures, security events, and operational events must be handled and logged.

The system must fail safely without exposing internal information to customers.

---

## 2. Core Principles

1. Never expose internal errors to users.
2. Log enough information to diagnose production issues.
3. Never log secrets.
4. Never trust browser error messages as authoritative business state.
5. Payment and booking state must remain internally consistent.
6. Expected business failures are not necessarily application errors.
7. Production logging must be safe for shared hosting.
8. Every important request/event should be traceable where practical.

---

## 3. Error Categories

### 3.1 Validation Error

Examples:

- Missing email
- Invalid date
- Invalid service ID
- Invalid password
- Invalid booking time

Response:

- HTTP 400 or 422 as appropriate
- Safe user-facing message
- No stack trace

### 3.2 Authentication Error

Examples:

- Invalid login
- Invalid/expired reset token
- Invalid admin OTP

Response:

- HTTP 401 where appropriate
- Avoid account enumeration
- Log security-relevant events without passwords/tokens

### 3.3 Authorization Error

Examples:

- Customer attempting admin endpoint
- Admin attempting unauthorized operation

Response:

- HTTP 403

Do not reveal unnecessary authorization details.

### 3.4 Not Found

Examples:

- Missing service
- Missing booking
- Invalid resource

Response:

- HTTP 404

### 3.5 Conflict / Business Rule Error

Examples:

- Slot became unavailable
- Closed date
- Duplicate operation
- Expired booking hold

Response:

- HTTP 409 where appropriate
- Explain the next safe action

### 3.6 Payment Error

Examples:

- Razorpay order creation failure
- Signature mismatch
- Payment failed
- Webhook verification failure
- Duplicate callback

Payment errors require special handling and must never blindly mark a booking as paid.

### 3.7 Infrastructure Error

Examples:

- Database unavailable
- SMTP unavailable
- Filesystem failure
- Unexpected PHP exception

Response:

> Something went wrong. Please try again later.

Log technical details privately.

---

## 4. API Error Response

Use a consistent JSON structure.

Example:

```json
{
  "success": false,
  "error": {
    "code": "SLOT_UNAVAILABLE",
    "message": "This time slot is no longer available."
  }
}
```

Do not return:

- SQL queries
- stack traces
- PHP warnings
- filesystem paths
- secret values
- database credentials
- raw third-party credentials

---

## 5. Application Error Codes

Use stable machine-readable codes.

Examples:

- `VALIDATION_ERROR`
- `AUTHENTICATION_REQUIRED`
- `INVALID_CREDENTIALS`
- `FORBIDDEN`
- `NOT_FOUND`
- `SERVICE_INACTIVE`
- `DATE_CLOSED`
- `SLOT_UNAVAILABLE`
- `HOLD_EXPIRED`
- `BOOKING_NOT_FOUND`
- `PAYMENT_FAILED`
- `PAYMENT_VERIFICATION_FAILED`
- `PAYMENT_PENDING`
- `DUPLICATE_REQUEST`
- `RATE_LIMITED`
- `INTERNAL_ERROR`

Frontend code should rely on error codes rather than parsing human-readable messages.

---

## 6. PHP Error Handling

Configure a centralized application error/exception handler.

Development:

- Detailed diagnostics may be enabled locally.

Production:

- `display_errors` must be disabled.
- Errors must be logged.
- Users receive safe generic messages.

Do not suppress errors with broad `@` operators as a substitute for proper handling.

Unexpected exceptions should be caught at application boundaries and converted to safe responses.

---

## 7. Database Error Handling

PDO must use exception-based error handling.

Database errors must:

1. Roll back the current transaction where applicable.
2. Be logged safely.
3. Return a generic application error.
4. Never expose SQL or database credentials.

Booking/payment transactions must not continue after an unrecoverable database error.

---

## 8. Booking Error Handling

Booking operations must be atomic.

If any required operation fails:

- Do not leave a partially created booking.
- Do not leave an inconsistent hold.
- Do not report confirmation when the transaction failed.

For capacity conflicts:

> The selected slot is no longer available. Please choose another time.

Concurrency errors must be handled as normal business conflicts where possible.

---

## 9. Payment Error Handling

Payment state must be authoritative on the server.

### Browser callback

Treat the callback as untrusted input.

### Signature verification failure

- Do not mark payment paid.
- Log the event.
- Return a safe failure/pending response as appropriate.

### Webhook failure

- Verify webhook signature.
- Process idempotently.
- Never process the same event twice.
- Do not create duplicate payment records.

### Payment succeeded but email failed

Payment and booking confirmation must remain successful.

Email failure must be logged separately.

Never reverse a valid payment solely because email delivery failed.

---

## 10. Email Error Handling

Email failures should not corrupt successful bookings.

Recommended sequence:

1. Confirm authoritative booking/payment state.
2. Attempt confirmation email.
3. Log success/failure.
4. Allow admin to diagnose/retry if such tooling is added later.

Do not expose SMTP credentials or detailed SMTP errors to customers.

---

## 11. Logging Levels

Use practical levels:

### INFO

Normal operational events.

Examples:

- User login success
- Booking created
- Payment verified
- Email sent

### WARNING

Unexpected but recoverable conditions.

Examples:

- Expired hold cleanup
- Retry required
- Duplicate webhook received

### ERROR

Application/infrastructure failures.

Examples:

- Database failure
- Invoice generation failure
- SMTP failure
- Unexpected exception

### SECURITY

Security-relevant events.

Examples:

- Repeated failed admin OTP attempts
- CSRF failure
- Rate-limit trigger
- Suspicious authorization attempt
- Payment signature mismatch

If the logging implementation uses standard PHP levels instead, map these concepts consistently.

---

## 12. Request / Correlation ID

Where practical, assign a unique request/correlation ID to each HTTP request.

Use it in:

- Application logs
- Payment processing logs
- Webhook logs
- Error reports

This makes it possible to trace one operation across multiple components.

Never expose internal correlation data unnecessarily to customers.

---

## 13. What Must Never Be Logged

Never log:

- Passwords
- Password reset tokens
- Admin OTP values
- Session IDs
- Razorpay secret key
- SMTP passwords
- Database passwords
- `.env` contents
- Full authorization headers
- Card numbers
- CVV
- Private API credentials

Avoid logging unnecessary personal information.

---

## 14. Payment Logging

Record safe identifiers such as:

- Internal booking ID
- Booking reference
- Internal payment ID
- Razorpay order ID
- Razorpay payment ID
- Event/webhook identifier where available
- Verification result
- Timestamp
- Error category

Do not store sensitive payment credentials.

---

## 15. Authentication Logging

Useful events:

- Login success/failure
- Password reset requested
- Password reset completed
- Admin OTP requested
- Admin OTP success/failure
- Rate-limit events

Do not store passwords, OTPs, or reset tokens.

---

## 16. Production vs Development

### Development

May include:

- Detailed exception information
- Debug logs
- SQL diagnostics when safe

### Production

Must include:

- Safe user messages
- Private technical logs
- Debug display disabled
- No secret leakage

Production should never rely on users sending screenshots of raw PHP errors for diagnosis.

---

## 17. Log Storage

Logs must be stored outside the public web root where possible.

If Hostinger/shared hosting constraints require another approach:

- Prevent direct web access.
- Use appropriate filesystem permissions.
- Keep log files protected.
- Avoid logging into publicly accessible directories.

Log rotation/retention should be implemented according to hosting capabilities.

---

## 18. Monitoring Priorities

Monitor at minimum:

- Database failures
- Payment verification failures
- Webhook failures
- Booking conflicts
- Invoice generation failures
- Email failures
- Admin authentication abuse
- Repeated application exceptions

The MVP does not require an external observability platform unless separately approved.

---

## 19. Error Handling Acceptance Criteria

The implementation is acceptable when:

- Production does not expose PHP errors.
- API responses use consistent error structures.
- Database exceptions are safely handled.
- Booking failures do not create inconsistent records.
- Payment verification failures never create false confirmations.
- Duplicate webhooks are safe.
- Email failures do not corrupt bookings.
- Logs contain useful diagnostic information.
- Logs contain no passwords, OTPs, tokens, or secrets.
- Security events can be distinguished from normal application errors.
