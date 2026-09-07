# SKSL — Git Workflow

## Purpose

This document defines the Git workflow for SKSL development.

The goal is to keep the codebase recoverable, reviewable, and safe while multiple changes are made.

---

## 1. Repository Rules

The Git repository is the source-control history for the application.

Do not commit:

- `.env`
- API secrets
- SMTP credentials
- database passwords
- private keys
- production database dumps
- customer personal-data exports
- payment exports
- temporary files
- local IDE files
- generated logs

---

## 2. Required `.gitignore`

At minimum, review exclusion of:

```text
.env
.env.*
!.env.example

/vendor/

storage/logs/*
storage/invoices/*
storage/uploads/*

*.log
*.sql
*.zip
*.bak

.DS_Store
Thumbs.db

.vscode/
.idea/
```

Adjust this to the actual project structure.

Do not ignore files that are required to reproduce the application.

---

## 3. Branches

Recommended:

```text
main
develop
feature/*
fix/*
hotfix/*
```

### main

Production-ready code only.

### develop

Integration branch for completed development work.

### feature/*

New functionality.

Examples:

```text
feature/customer-auth
feature/booking-engine
feature/razorpay-payment
feature/admin-services
```

### fix/*

Non-production bug fixes.

### hotfix/*

Urgent production fixes.

---

## 4. Commit Messages

Use clear messages.

Examples:

```text
feat: add customer registration
feat: implement dynamic availability
fix: prevent duplicate booking hold
fix: verify Razorpay webhook signature
security: harden admin OTP rate limiting
docs: update pending client decisions
test: add concurrent booking coverage
```

Avoid:

```text
update
changes
final
done
test
asdf
```

---

## 5. Commit Size

Prefer focused commits.

Good:

```text
feat: add booking hold service
test: add booking hold expiry tests
```

Avoid putting unrelated features into one huge commit.

---

## 6. Before Commit

Run:

- syntax checks
- relevant tests
- security checks
- application smoke test

Review:

```text
git status
git diff
```

Never commit code you have not reviewed.

---

## 7. Before Push

Confirm:

- no `.env`
- no secrets
- no customer data
- no payment credentials
- no debug files
- no database dumps

Review staged files:

```text
git diff --cached
```

---

## 8. Pulling Changes

Before starting work:

```text
git pull
```

Resolve conflicts carefully.

Never blindly accept both versions of code involving:

- payment logic
- booking capacity
- authentication
- authorization
- database migrations

Review these manually.

---

## 9. Database Changes

Database changes must be committed together with reproducible schema/migration files.

Example:

```text
database/
├── schema/
└── migrations/
```

A code change that requires a database change must clearly document that dependency.

---

## 10. Production Deployment

Never deploy unreviewed development code directly to production.

Recommended:

```text
feature
   |
   v
develop
   |
   v
Testing
   |
   v
Production-ready commit
   |
   v
main
   |
   v
Hostinger
```

---

## 11. Rollback

Every production release should be traceable to a Git commit.

If a release fails:

1. Identify release commit.
2. Stop further deployment.
3. Roll back code if necessary.
4. Check database compatibility.
5. Verify booking/payment integrity.
6. Test production.
7. Document the incident.

Do not automatically roll back database data unless the impact is understood.

---

## 12. Git Safety

Never use destructive commands casually.

Examples requiring explicit approval:

```text
git reset --hard
git clean -fd
git push --force
```

Never rewrite shared history without approval.

---

## 13. AI Development Rule

Antigravity must not:

- delete working code without reason
- rewrite unrelated files
- change architecture silently
- create secrets
- commit `.env`
- modify production configuration accidentally
- run destructive Git commands without approval

Before large refactoring, explain:

- what changes
- why
- affected files
- risk
- rollback approach

---

## 14. Documentation

When behavior changes, update the relevant `/docs` file.

Documentation changes should be committed with the related implementation when practical.
