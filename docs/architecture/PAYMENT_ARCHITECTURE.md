# SKSL — Razorpay Payment Architecture

## 1. Purpose

Define the secure payment architecture for SKSL.

The application must treat payment processing as a server-side integrity problem.

The browser is untrusted.

---

## 2. Core Rule

Never trust the browser for:

- price
- GST
- convenience fee
- total amount
- service duration
- service identity without server validation
- payment success
- payment status
- booking confirmation

The server must calculate and verify all financial values.

---

## 3. Payment Components

```text
Customer Browser
      |
      v
SKSL Backend
      |
      +--> MySQL
      |
      +--> Razorpay Orders API
      |
      +--> Razorpay Checkout
      |
      +--> Razorpay Webhook
      |
      +--> Invoice Service
      |
      +--> Email Service
```

---

## 4. Recommended Payment Sequence

### Step 1 — Select Service

Customer selects:

- service
- date
- start time

Server receives these identifiers.

---

### Step 2 — Revalidate

Server checks:

- customer authentication
- service exists
- service is active
- date is valid
- date is not closed
- time is valid
- operating hours
- service duration
- buffer rules
- capacity
- existing bookings
- active holds

---

### Step 3 — Create Hold

Create a temporary hold for 10 minutes.

The hold must be created using concurrency-safe database logic.

Store:

- booking reference
- user
- service
- date
- start time
- end time
- expiry

---

### Step 4 — Calculate Amount

Server retrieves current service configuration.

Example:

```text
Base amount
+ GST
+ convenience fee
= Total
```

GST is currently 18%.

Convenience fee treatment remains pending client confirmation.

Do not accept a client-provided total as authoritative.

---

### Step 5 — Create Razorpay Order

The server creates the Razorpay order.

The order amount must be generated from the server-calculated total.

Store the Razorpay order ID against the internal booking/payment record.

---

### Step 6 — Checkout

The browser receives only the information required to open Razorpay Checkout.

Never send the Razorpay secret key to the browser.

---

### Step 7 — Browser Callback

The browser may send:

- Razorpay order ID
- Razorpay payment ID
- Razorpay signature

The server must verify these values.

The browser response is NOT itself proof of successful payment.

---

## 5. Server-Side Verification

Verify:

1. Signature
2. Razorpay order ID
3. Razorpay payment ID
4. Expected booking
5. Expected amount
6. Expected currency
7. Payment state

Only after successful verification should the application move toward confirmation.

---

## 6. Webhook

Razorpay webhook is an independent server-to-server payment signal.

Webhook endpoint must:

1. Receive raw request body.
2. Verify webhook signature using the configured webhook secret.
3. Parse event safely.
4. Identify payment/order.
5. Find the internal booking/payment.
6. Check current state.
7. Apply an idempotent state transition.
8. Return an appropriate success response.

Never trust an unsigned webhook.

---

## 7. Idempotency

Duplicate events are expected in real integrations.

Example:

```text
Webhook A -> paid
Webhook A again -> already processed
Browser callback -> already processed
```

The final state must remain correct.

Do not:

- create another payment record unnecessarily
- create another booking
- create another invoice
- send another email

Use database constraints and explicit state checks.

---

## 8. Race Conditions

Potential race:

```text
Customer A -> payment callback
Customer B -> webhook
Customer C -> duplicate callback
```

Payment/booking transitions must be transaction-safe.

Use row locking or another appropriate concurrency strategy.

The exact implementation must be validated against the MySQL version and schema.

---

## 9. Payment Failure

If payment fails:

- payment status becomes `failed`
- booking is not confirmed
- active hold should be released according to the application flow
- customer receives a safe failure message

Do not mark payment as paid based on frontend state.

---

## 10. Hold Expiry

A hold expires after 10 minutes.

Availability checks must treat an expired hold as inactive even if the cron job has not yet processed it.

Cron is cleanup/maintenance, not the only source of correctness.

---

## 11. Booking Confirmation

Confirmation should occur only when the server has sufficient verified evidence of successful payment according to the approved integration.

Recommended state:

```text
pending
  |
  v
payment verified
  |
  v
confirmed
```

Then:

```text
confirmed
   |
   +--> invoice
   |
   +--> customer email
   |
   +--> admin notification
```

---

## 12. Amount Integrity

Example:

```text
Service price = ₹999.00
GST = ₹179.82
Convenience fee = ₹X
Total = ₹1,178.82 + fee
```

The exact convenience fee remains pending.

The backend must calculate the final amount using decimal arithmetic suitable for currency.

Razorpay amount should be converted to the gateway's required smallest unit only after the server has finalized the rupee amount.

---

## 13. Duplicate Payment Protection

Use appropriate uniqueness and state checks for:

- Razorpay order ID
- Razorpay payment ID
- Internal booking reference

A duplicate gateway event must never create a duplicate business transaction.

---

## 14. Refunds

Refund automation is not fully defined in V1.

Do not implement refund logic until the client confirms:

- cancellation window
- refund eligibility
- full/partial refund
- gateway/convenience fee
- who initiates refund
- rescheduling interaction

---

## 15. Payment Logging

Safe logs may include:

- internal booking reference
- Razorpay order ID
- Razorpay payment ID
- event type
- processing status
- timestamp

Never log:

- secret keys
- webhook secret
- customer passwords
- admin OTP values
- SMTP password

Avoid storing unnecessary raw gateway payloads if they contain sensitive data.

---

## 16. Failure Scenarios

The implementation must handle:

- Razorpay order creation failure
- Checkout abandoned
- Payment failed
- Invalid signature
- Wrong order ID
- Wrong amount
- Wrong currency
- Webhook delayed
- Webhook duplicated
- Browser callback duplicated
- Webhook arrives before browser callback
- Browser callback arrives before webhook
- Database failure during confirmation
- Email failure after successful payment

Payment success must never depend on email success.

---

## 17. Security Requirements

- Razorpay secret key server-side only
- HTTPS production
- Webhook signature verification
- Server-side amount verification
- Server-side booking relationship verification
- Idempotent processing
- Database transactions
- Authorization
- Safe error responses
- No payment secrets in Git

---

## 18. Testing Requirements

Before production:

- [ ] Successful payment
- [ ] Failed payment
- [ ] Abandoned checkout
- [ ] Invalid signature
- [ ] Modified amount
- [ ] Wrong order
- [ ] Duplicate callback
- [ ] Duplicate webhook
- [ ] Webhook-before-callback
- [ ] Callback-before-webhook
- [ ] Concurrent payment attempts
- [ ] Hold expiry
- [ ] Database failure simulation where practical
- [ ] Email failure after payment
