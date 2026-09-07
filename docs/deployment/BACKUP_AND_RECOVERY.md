# SKSL Backup and Recovery

**Project:** Sara Kinetic Sports Lab (SKSL)  
**Version:** 1.0  
**Status:** MVP Standard

---

## 1. Purpose

This document defines minimum backup and recovery practices for SKSL production data and application files.

The most important assets are:

- Customer accounts
- Bookings
- Payment records
- Service configuration
- Closed dates
- Application configuration
- Uploaded service images

---

## 2. Backup Principles

1. Production data must be backed up regularly.
2. Database backups must be protected from public access.
3. Backups must not contain credentials in publicly accessible locations.
4. A backup is useful only if it can be restored.
5. Before risky deployment/database changes, create a fresh backup.
6. Do not store the only copy of a backup on the same server.
7. Payment and booking data receive highest recovery priority.

---

## 3. What to Back Up

### Database

Back up the complete production database, including:

- users
- admins
- admin_otps
- services
- closed_dates
- bookings
- booking_holds
- payments
- password_resets
- email_logs, if implemented

### Application

Back up:

- Application source code
- Composer configuration/lock files
- Tailwind/package configuration
- Required migration files
- Uploaded service images
- Other approved persistent assets

Do **not** back up or redistribute secrets unnecessarily.

---

## 4. What Must Not Be Public

Never place backups inside a publicly accessible web directory.

Avoid locations such as:

```text
public_html/backups/
public_html/*.sql
public_html/.env
```

If a hosting-specific backup location must be used, ensure direct HTTP access is blocked.

---

## 5. Backup Types

### Full Database Backup

A complete SQL/database dump.

Recommended for:

- Regular scheduled backup
- Pre-deployment backup
- Pre-migration backup

### File Backup

A copy of application files and persistent uploads.

### Hosting-Level Backup

Use Hostinger's available backup facilities where appropriate.

Do not depend exclusively on a single backup mechanism.

---

## 6. Suggested MVP Backup Schedule

The exact schedule should be confirmed against the Hostinger plan and operational requirements.

Recommended baseline:

- Database: daily
- Application/uploads: daily or according to change frequency
- Pre-deployment: manual backup
- Pre-database migration: manual backup

For a higher-traffic production system, increase frequency based on acceptable data-loss tolerance.

---

## 7. Retention

Keep multiple historical backup points rather than only the latest backup.

Example baseline:

- Daily backups: retain at least 7 days
- Weekly backups: retain at least 4 weeks
- Pre-deployment backups: retain until deployment is verified

Adjust based on storage limits and business requirements.

---

## 8. Backup Security

Protect backups with:

- Restricted filesystem access
- Strong hosting/account credentials
- Separate storage where possible
- Encryption where supported
- No public URLs

Never commit backup files to Git.

---

## 9. Recovery Priority

### Priority 1 — Database

Restore:

- Customers
- Bookings
- Payments
- Services
- Operational configuration

### Priority 2 — Application

Restore application code matching the database version.

### Priority 3 — Uploaded Assets

Restore service images and persistent files.

---

## 10. Recovery Procedure

### Step 1 — Identify Incident

Determine:

- What failed
- When it failed
- Whether data was modified
- Whether payment processing is affected
- Whether the application should be temporarily disabled

### Step 2 — Preserve Evidence

Before changing data:

- Save relevant logs.
- Record timestamps.
- Identify affected booking/payment references.
- Avoid destructive troubleshooting.

### Step 3 — Stop Further Damage

If necessary:

- Disable affected feature.
- Temporarily restrict booking/payment access.
- Prevent repeated faulty operations.

### Step 4 — Select Backup

Choose the latest known-good backup that meets business recovery needs.

### Step 5 — Restore to Safe Environment

Where practical, restore to a temporary/staging database first.

Verify:

- Row counts
- Booking records
- Payment records
- Service configuration
- Recent transactions

### Step 6 — Production Restore

Restore only after validation.

### Step 7 — Application Verification

Run:

- Login
- Service listing
- Availability
- Booking lookup
- Admin access
- Payment-state checks

### Step 8 — Reconcile Payments

If a restore may have affected payment records, compare application records with Razorpay records/webhook history before declaring the system fully recovered.

---

## 11. Payment Recovery

Payment data requires special caution.

Never assume:

> User paid = application record must already be correct.

After an incident:

- Check Razorpay order/payment identifiers.
- Check webhook events where available.
- Check internal payment records.
- Check booking status.
- Avoid creating duplicate payment records.
- Reconcile before manually changing financial state.

---

## 12. Recovery Testing

A backup strategy is incomplete until restoration has been tested.

Periodically test:

- Database restore
- File restore
- Application startup against restored database
- Booking lookup
- Admin access
- Payment reconciliation process

Do not test destructive recovery operations directly against production.

---

## 13. Backup Before Deployment

Before:

- Database migration
- Large application refactor
- Payment changes
- Booking engine changes
- Production configuration changes with significant risk

Create a fresh backup.

Record:

```text
Backup timestamp:
Deployment version:
Migration/version:
Purpose:
Restore location:
Verification status:
```

---

## 14. Disaster Recovery Documentation

Maintain a short incident record containing:

- Incident date/time
- Cause
- Affected systems
- Affected bookings/payments
- Backup selected
- Restore time
- Validation performed
- Customer/business impact
- Corrective action

---

## 15. AI Agent Rules

Antigravity must never:

- Delete production data as a troubleshooting shortcut.
- Drop production tables without explicit approval.
- Overwrite production backups.
- Assume a backup is valid without restoration/testing evidence.
- Modify payment records casually.

Before risky DB work, the agent must state the backup requirement.

---

## 16. Acceptance Criteria

Backup/recovery is complete when:

- Production database backups exist.
- Backups are protected.
- Backups are not publicly accessible.
- Pre-deployment backups are part of the release process.
- Restore procedure is documented.
- Payment reconciliation is included.
- At least one restoration test has been successfully performed.
