# SKSL — Development Workflow

## 1. Purpose

This is the standard workflow Antigravity/developers should follow while building SKSL.

The project must be developed incrementally rather than generated as one uncontrolled code dump.

---

## 2. Before Coding

Always:

1. Read the Master Development Instruction.
2. Read the MVP Development Plan.
3. Read relevant documentation.
4. Inspect existing files.
5. Check current Git status.
6. Identify existing implementation.
7. Identify unresolved business rules.
8. Identify dependencies.
9. Plan the smallest safe implementation.

Do not overwrite existing work blindly.

---

## 3. Implementation Cycle

Use:

```text
Understand
   ↓
Plan
   ↓
Implement
   ↓
Test
   ↓
Review
   ↓
Document
   ↓
Commit
```

Repeat for each feature.

---

## 4. Phase Order

Recommended implementation sequence:

### Phase 1 — Foundation

- project structure
- Composer
- configuration
- environment handling
- database connection
- routing
- error handling
- logging
- base MVC components

### Phase 2 — UI Foundation

- layout
- header/footer
- responsive design
- Tailwind build
- reusable UI components

### Phase 3 — Customer Authentication

- registration
- login
- logout
- sessions
- password reset
- profile

### Phase 4 — Admin Authentication

- admin login
- OTP request
- OTP verification
- rate limiting
- admin session

### Phase 5 — Service Management

- service listing
- admin CRUD
- status
- image upload
- price
- duration
- capacity

### Phase 6 — Booking Engine

- date selection
- closed dates
- slot generation
- duration
- buffers after approval
- capacity
- overlap
- temporary holds
- concurrency protection

### Phase 7 — Payment

- Razorpay order
- checkout
- signature verification
- webhook
- idempotency
- payment states

### Phase 8 — Invoice/Email

- PDF
- customer confirmation
- admin notification
- safe email failure handling

### Phase 9 — Dashboards

- customer dashboard
- admin dashboard
- booking management
- service management
- closed dates
- statistics

### Phase 10 — Security/Testing

- security checklist
- concurrency tests
- payment tests
- regression
- UAT

### Phase 11 — Deployment

- Hostinger
- MySQL
- `.env`
- SSL
- SMTP
- Razorpay webhook
- cron
- production smoke test

---

## 5. Feature Completion Requirements

A feature is not complete merely because the UI works.

For each feature:

### Backend

- [ ] Validation
- [ ] Authorization
- [ ] Error handling
- [ ] Database integrity
- [ ] Security

### Frontend

- [ ] UI
- [ ] Loading state
- [ ] Error state
- [ ] Success state
- [ ] Mobile responsiveness

### Testing

- [ ] Happy path
- [ ] Invalid input
- [ ] Unauthorized access
- [ ] Edge cases

### Documentation

- [ ] Relevant documentation updated

---

## 6. Change Impact Review

Before modifying a core feature, identify dependencies.

Example:

Changing service duration may affect:

- availability
- booking end time
- overlap
- capacity
- payment display
- invoice
- historical booking logic

Do not change one component without reviewing dependent components.

---

## 7. Business Rule Changes

If a requirement is ambiguous:

Do not guess.

Use:

```text
docs/requirements/PENDING_CLIENT_DECISIONS.md
```

If a temporary assumption is unavoidable, document it clearly.

---

## 8. Security Review

Before completing a feature ask:

- Can an unauthenticated user access it?
- Can another customer access it?
- Can input cause SQL injection?
- Can output cause XSS?
- Is CSRF protected?
- Can the browser manipulate authoritative values?
- Can repeated requests cause duplicate records?
- Can concurrent requests break integrity?
- Are secrets exposed?

---

## 9. Testing After Each Phase

Do not wait until the end to discover architectural bugs.

After each phase:

- run relevant tests
- inspect logs
- verify database state
- test error paths
- test unauthorized paths

---

## 10. AI Agent Rule

Antigravity should work in small, reviewable steps.

Before a large operation, report:

```text
Goal
Files affected
Database changes
Security considerations
Tests to run
Potential risks
```

After implementation, report:

```text
Implemented
Files changed
Database changes
Tests performed
Known issues
Next step
```

---

## 11. No Silent Refactoring

Do not refactor unrelated code merely because it could be cleaner.

If refactoring is necessary:

1. Explain why.
2. Identify affected modules.
3. Test before/after.
4. Keep scope controlled.

---

## 12. Definition of Complete

A feature is complete when:

- implementation works
- security is reviewed
- tests pass
- documentation is updated
- no known critical issue remains
- Git state is clean/understood
