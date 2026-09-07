# SKSL Database Migration Strategy

**Project:** Sara Kinetic Sports Lab (SKSL)  
**Version:** 1.0  
**Status:** MVP Standard

---

## 1. Purpose

This document defines how SKSL database schema changes must be created, tested, reviewed, deployed, and rolled back.

The goal is to prevent uncontrolled production database changes and protect booking/payment data.

---

## 2. Core Rules

1. Every schema change must be documented.
2. Never manually change production tables without a documented reason and approval.
3. Test migrations against a database representative of production.
4. Prefer additive changes before destructive changes.
5. Never delete historical booking/payment data as part of routine schema maintenance.
6. Database changes must be compatible with the application version being deployed.
7. Back up production before risky schema changes.
8. Migration scripts must be repeatable or explicitly marked as one-time migrations.
9. Do not mix unrelated schema changes into one migration.
10. Payment and booking tables require extra review.

---

## 3. Migration File Naming

Use an ordered, timestamped naming convention.

Example:

```text
database/migrations/
├── 20260907120000_create_users.sql
├── 20260907120100_create_services.sql
├── 20260907120200_create_bookings.sql
└── 20260907120300_add_booking_reference_index.sql
```

If the implementation uses PHP migration classes instead of SQL files, follow the framework/library convention consistently.

Do not rename an already-applied production migration.

---

## 4. Initial Schema

The approved MVP schema includes the core entities:

- users
- admins
- admin_otps
- services
- closed_dates
- bookings
- booking_holds
- payments
- password_resets
- optional email_logs

Refer to `DATABASE_SCHEMA.md` as the authoritative logical schema definition.

---

## 5. Development Migration Workflow

For every schema change:

### Step 1 — Identify Need

Document:

- Why the change is needed
- Which table(s) are affected
- Which application feature requires it
- Whether existing data is affected

### Step 2 — Create Migration

Create a new migration file.

Example:

```sql
ALTER TABLE services
ADD COLUMN image_path VARCHAR(500) NULL;
```

### Step 3 — Test Locally

Test:

- Fresh database setup
- Migration execution
- Existing application functionality
- Relevant CRUD operations
- Booking/payment behavior if affected

### Step 4 — Review

Check:

- Indexes
- Foreign keys
- Nullability
- Defaults
- Data types
- Query performance
- Historical data preservation
- Rollback implications

### Step 5 — Commit

Commit migration and related application code together when they form one deployable feature.

---

## 6. Production Deployment

Recommended order for backward-compatible changes:

1. Backup database.
2. Put application into an appropriate maintenance/deployment state if required.
3. Apply migration.
4. Verify schema.
5. Deploy compatible application code.
6. Run smoke tests.
7. Monitor logs.

For changes requiring application and schema compatibility, use an expand → migrate → contract approach.

---

## 7. Expand → Migrate → Contract

For risky changes:

### Expand

Add the new structure without removing the old structure.

### Migrate

Update application/data gradually.

### Contract

Only after the application no longer depends on the old structure, remove the obsolete structure.

This reduces deployment risk.

---

## 8. Destructive Changes

Destructive changes include:

- Dropping columns
- Dropping tables
- Removing indexes required by old code
- Changing data types in ways that may truncate data
- Deleting historical records

These require explicit review.

Never remove booking/payment history simply because a service or feature was changed.

---

## 9. Data Migrations

A data migration changes existing records rather than only changing schema.

Examples:

- Backfilling a new column
- Converting stored values
- Creating booking references for legacy records

Before running:

- Back up data.
- Determine affected row count.
- Test on a copy.
- Make the operation idempotent where practical.
- Record what was changed.

---

## 10. Foreign Keys

Use foreign keys where they improve data integrity.

Important relationships include:

- booking → user
- booking → service
- payment → booking
- booking_hold → booking where applicable
- admin_otp → admin where applicable

Foreign-key deletion behavior must be carefully selected.

Do not use cascading deletes in a way that can accidentally remove financial or historical booking records.

---

## 11. Index Review

Review indexes for:

- Email lookups
- Booking reference
- Booking date
- Service/date availability
- Booking status
- Payment identifiers
- OTP expiry
- Password reset expiry
- Closed dates

Do not create unnecessary indexes without considering write cost.

---

## 12. Booking/Payment Tables

Extra caution is required for:

- `bookings`
- `booking_holds`
- `payments`

Schema changes to these tables must be reviewed for:

- Transaction behavior
- Concurrency
- Unique constraints
- Historical snapshots
- Payment reconciliation
- Idempotency

---

## 13. Rollback Strategy

A migration rollback must be planned before production execution.

For simple additive changes, rollback may be straightforward.

For data transformations, rollback may be impossible without restoring from backup.

Do not claim a migration is reversible when data loss can occur.

---

## 14. Production Verification

After migration:

- Confirm tables/columns exist.
- Confirm indexes exist.
- Run application health check.
- Test customer login.
- Test service listing.
- Test availability.
- Test booking creation in test/safe mode as appropriate.
- Verify admin access.
- Verify payment configuration is unaffected.
- Review logs.

---

## 15. Migration Documentation

Each migration should record:

- Migration name
- Date
- Purpose
- Tables affected
- Data impact
- Rollback approach
- Related feature
- Deployment status

Example:

```text
Migration: 20260907120300_add_booking_reference_index
Purpose: Improve booking lookup performance
Tables: bookings
Data impact: None
Rollback: Remove index
Related feature: Admin booking search
```

---

## 16. AI Agent Rules

Antigravity must:

- Never modify production schema directly without approval.
- Explain schema impact before risky changes.
- Create migration files rather than undocumented SQL edits.
- Test migrations locally.
- Report affected tables.
- Report whether existing data is preserved.
- Report rollback limitations.
- Update relevant documentation after schema changes.

---

## 17. Acceptance Criteria

Database migration management is complete when:

- Schema changes are versioned.
- Production changes are documented.
- Backups precede risky migrations.
- Booking/payment history is protected.
- Migrations are tested locally.
- Rollback implications are known.
- AI-assisted schema changes are reviewed.
