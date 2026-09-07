# SKSL — Booking Engine Specification

## 1. Purpose

The booking engine is the core business component of SKSL.

Its responsibilities are:

- generate valid slots
- determine availability
- enforce capacity
- prevent overlaps
- create temporary holds
- prevent race-condition overbooking
- preserve booking data integrity

---

## 2. Authoritative Inputs

The server must retrieve:

- service status
- service duration
- service capacity
- service price
- GST
- operating hours
- closed dates
- existing bookings
- active holds
- approved buffer rules
- approved advance-booking rules
- approved same-day cutoff

Never use browser values as authoritative.

---

## 3. Facility Hours

```text
Opening: 06:00
Closing: 22:00
Timezone: Asia/Kolkata
```

The exact last-slot calculation remains pending client confirmation.

---

## 4. Service Duration

Initial durations:

| Service | Duration |
|---|---:|
| Spa | 30 min |
| Sauna | 10 min |
| Steam | 10 min |
| Ice Bath | 10 min |
| Hot Bath | 10 min |
| Endless Pool | 30 min |
| Cycle | 15 min |
| Treadmill | 15 min |
| Walker | 15 min |
| Lap Pool | 45 min |

---

## 5. Capacity

| Service | Capacity |
|---|---:|
| Spa | 4 |
| Sauna | 4 |
| Steam | 8 |
| Ice Bath | 8 |
| Hot Bath | 4 |
| Endless Pool | 2 |
| Cycle | 1 |
| Treadmill | 1 |
| Walker | 1 |
| Lap Pool | 1 |

Ice Bath capacity is explicitly 8 simultaneous users, subject to the final operational buffer/cleaning rules.

---

## 6. Slot Generation

Slots must be generated dynamically.

Conceptually:

```text
opening time
   |
   +--> candidate start
   |
   +--> calculate end time
   |
   +--> validate operating boundary
   |
   +--> check availability
   |
   +--> next candidate
```

Do not store every possible slot permanently unless a later requirement justifies it.

---

## 7. Slot Duration

For a candidate:

```text
end_time = start_time + service_duration
```

If buffers are confirmed:

```text
occupied_start = start_time - checkin_buffer
occupied_end   = end_time + checkout/cleaning buffer
```

The exact buffer model must use the final client-approved rule.

---

## 8. Operating Boundary

A candidate slot is valid only if the service and required buffers comply with the facility's approved closing rule.

Do not simply assume:

```text
start_time < 22:00
```

is sufficient.

The service's effective occupied period must be evaluated.

---

## 9. Closed Dates

If the requested date exists in `closed_dates`:

```text
availability = none
```

This applies to all services.

---

## 10. Past Dates

Past dates must not be bookable.

The comparison must use the business timezone.

---

## 11. Same-Day Cutoff

The exact cutoff is pending client confirmation.

Once confirmed, server-side validation must enforce it.

Frontend should also hide unavailable options for UX, but backend enforcement remains mandatory.

---

## 12. Advance Booking Window

The exact window is pending client confirmation.

Once confirmed:

```text
booking_date <= today + maximum_advance_days
```

must be enforced server-side.

---

## 13. Overlap Logic

Two time ranges overlap when:

```text
existing_start < requested_end
AND
existing_end > requested_start
```

For buffer-aware booking, use the effective occupied intervals.

Adjacent slots should only be allowed when the approved buffer rules permit them.

---

## 14. Capacity Calculation

For a requested interval:

```text
active overlapping confirmed bookings
+
active non-expired holds
```

must be evaluated against service capacity.

Example:

```text
capacity = 4
active usage = 3
requested usage = 1
result = allowed
```

If usage is already 4:

```text
result = rejected
```

---

## 15. Statuses Affecting Availability

Confirmed bookings should affect availability.

Pending/temporary booking state should affect availability only when it represents an active valid hold.

Cancelled/expired bookings should not block capacity.

Completed bookings affect availability only according to their actual historical time interval; they should not be treated as future occupancy.

---

## 16. Temporary Hold

A hold lasts:

```text
10 minutes
```

The server must set:

```text
expires_at = current_server_time + 10 minutes
```

Do not accept `expires_at` from the browser.

---

## 17. Hold Ownership

A customer may only operate on their own hold.

Do not allow:

```text
Customer A -> hold belonging to Customer B
```

---

## 18. Concurrency

This is mandatory.

Unsafe pattern:

```text
SELECT available capacity
INSERT booking
```

with no transaction/locking strategy.

Two simultaneous requests can both observe availability and both insert.

The implementation must use a concurrency-safe strategy appropriate for MySQL.

Possible strategies include:

- transaction + row locking on a service/date resource
- a carefully designed capacity allocation record
- atomic conditional updates
- another proven transactional approach

The chosen approach must be documented and tested.

---

## 19. Recommended Concurrency Concept

A practical strategy is to serialize critical allocation for a service/date resource.

Conceptually:

```text
BEGIN TRANSACTION

Lock service/date allocation resource

Recalculate active bookings + active holds

IF capacity available:
    create hold
ELSE:
    reject

COMMIT
```

The exact schema/locking implementation must be chosen based on the final database design.

Do not claim concurrency safety without testing it.

---

## 20. Booking Hold Creation

Server sequence:

```text
Authenticate
   ↓
Validate request
   ↓
Load service
   ↓
Check active service
   ↓
Check date
   ↓
Check closed date
   ↓
Calculate slot
   ↓
Check operating boundary
   ↓
Begin transaction
   ↓
Lock allocation resource
   ↓
Recalculate availability
   ↓
Create hold
   ↓
Commit
```

---

## 21. Payment Relationship

A hold should be associated with the internal booking reference.

Payment must reference the internal booking/payment record.

Razorpay identifiers must never replace internal business identifiers.

---

## 22. Hold Expiry

Availability checks must treat:

```text
expires_at <= current_time
```

as expired.

Therefore the application remains correct even if cron has not yet run.

Cron later cleans up stale records.

---

## 23. Duplicate Requests

Repeated customer requests must not create multiple unintended holds.

Consider:

- browser double-click
- retry after network timeout
- Fetch retry
- page refresh
- payment callback retry

Use appropriate idempotency/state protection.

---

## 24. Price Snapshot

When the booking is created, snapshot:

- service name
- price
- GST percentage
- GST amount
- duration
- buffers
- convenience fee
- total

Historical booking records must remain unchanged if the service is edited later.

---

## 25. Booking State

Expected:

```text
pending
confirmed
cancelled
completed
expired
```

Payment:

```text
pending
paid
failed
refunded
```

Transitions must be controlled by backend services.

---

## 26. Security

The booking engine must reject:

- inactive services
- closed dates
- past dates
- invalid times
- outside-hours slots
- over-capacity requests
- unauthorized holds
- manipulated prices
- manipulated durations
- manipulated capacity
- manipulated totals
- requests for another user's booking

---

## 27. Testing

Mandatory:

- [ ] capacity 1
- [ ] capacity 2
- [ ] capacity 4
- [ ] capacity 8
- [ ] exact overlap
- [ ] partial overlap
- [ ] adjacent slots
- [ ] multiple services same day
- [ ] closed date
- [ ] past date
- [ ] same-day cutoff
- [ ] advance booking limit
- [ ] hold creation
- [ ] hold expiry
- [ ] payment success
- [ ] payment failure
- [ ] duplicate request
- [ ] concurrent booking
- [ ] database rollback

---

## 28. Pending Rules

The implementation must not finalize these until confirmed:

- arrival instruction
- check-in buffer
- checkout buffer
- cleaning buffer
- last slot
- advance booking period
- same-day cutoff
