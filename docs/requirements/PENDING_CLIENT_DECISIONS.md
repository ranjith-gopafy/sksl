# SKSL — Pending Client Decisions

## Purpose
Business rules below are not finalized. Developers must not silently invent answers.

When resolved:
1. Record the decision and date.
2. Update affected technical documentation.
3. Update implementation if necessary.
4. Mark the item resolved.

## 1. Arrival Time
Confirm whether customers should arrive 10 minutes early and whether this is informational only or part of slot calculation.

**Status:** Pending

## 2. Check-in / Checkout / Cleaning Buffer
A previous working idea was 5-minute check-in + service + 5-minute checkout, but this is not final.

Confirm:
- check-in buffer
- checkout buffer
- cleaning buffer
- whether buffers differ by service

**Status:** Pending

## 3. Facility Closing / Last Slot
Facility hours are 06:00 AM–10:00 PM.

Confirm whether the service plus all buffers must finish by 10 PM and the exact latest start time for each service.

**Status:** Pending

## 4. Advance Booking Period
Confirm how far into the future customers may book.

**Status:** Pending

## 5. Same-Day Cutoff
Confirm how late same-day bookings are allowed.

**Status:** Pending

## 6. Cancellation
Confirm whether cancellation is allowed, deadline, refund eligibility, and gateway/convenience-fee treatment.

**Status:** Pending

## 7. Rescheduling
Confirm whether customers may reschedule, limits, deadlines, date changes, and price differences.

**Status:** Pending

## 8. Refund
Confirm full/partial/no-refund rules and who performs refunds.

**Status:** Pending

## 9. Convenience Fee
Confirm whether there is one, amount/type, who absorbs it, GST treatment, and refund treatment.

**Status:** Pending

## 10. Invoice vs Receipt
Confirm whether PDF is a formal tax invoice or receipt. Collect approved business/legal/tax details and invoice numbering requirements.

**Status:** Pending

## 11. Customer Profile
Current fields: full name, email, mobile, password, confirm password.

Confirm any additional fields.

**Status:** Pending

## 12. Safety Declaration
Confirm whether acceptance is required before payment and provide approved wording.

**Status:** Pending

## 13. Terms & Policies
Client must provide approved:
- Terms & Conditions
- Privacy Policy
- Cancellation/Refund Policy
- Safety/facility rules

**Status:** Pending

## 14. Admin Email
Provide the notification email address.

**Status:** Pending

## 15. SMTP
Provide SMTP provider, host, port, encryption, sender email/name. Credentials must be supplied securely and stored only in environment configuration.

**Status:** Pending

## 16. Ice Bath
Capacity is 8 simultaneous bookings. Confirm whether cleaning/shower/buffer rules affect effective availability.

**Status:** Pending

## Decision Log

| ID | Decision | Status |
|---|---|---|
| DC-001 | GST fixed at 18% | Confirmed |
| DC-002 | One service per booking | Confirmed |
| DC-003 | Facility hours 6 AM–10 PM | Confirmed |
| DC-004 | Temporary hold 10 minutes | Confirmed |
| DC-005 | Admin email OTP, 6 digits, 5 minutes | Confirmed |
| DC-006 | Customer email/password, no mobile OTP | Confirmed |
| DC-007 | Closed date applies to all services | Confirmed |
| DC-008 | Automated reminders excluded | Confirmed |
| DC-009 | Convenience fee | Pending |
| DC-010 | Arrival/buffer logic | Pending |
