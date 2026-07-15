# AKS — Compliance Audit Automation Platform
## Project Documentation

This document describes the architecture, database, security controls, AI features,
setup steps, and usage of the AKS platform, verified directly against the codebase
in this submission.

---

## 1. Overview

AKS is a PHP + MySQL web application for managing third-party/regulatory compliance
audits. It lets an admin onboard client **companies**, create **user/auditor**
accounts, run **compliance assessments** against regulatory **frameworks**, track
**control mappings**, surface **gap analysis**, manage an **audit checklist**,
collect **evidence documents**, and generate **PDF reports** — plus a built-in
AI Compliance Assistant with a lightweight RAG (retrieval-augmented generation)
layer over uploaded documents.

**Stack**
- Frontend: static HTML5 + vanilla CSS3 + vanilla JS (`css/style.css`, `js/app.js`)
- Backend: PHP (single-purpose REST-style endpoints under `backend/api/`)
- Database: MySQL/MariaDB (`mysqli`, prepared statements throughout)
- PDF generation: bundled third-party **FPDF** library (`backend/fpdf/`)
- AI provider: Google Gemini (optional), with an offline **mock** fallback mode

---

## 2. Architecture

```
Browser (HTML/CSS/JS)
   │  fetch() + JSON, CSRF token header
   ▼
backend/api/*.php  ──►  backend/config/*.php (session, CSRF, DB, response, AI, mail)
   │                              │
   ▼                              ▼
MySQL/MariaDB (`aks` db)     Local filesystem (backend/uploads/ evidence vault)
   │
   ▼ (optional)
Google Gemini API (AI Assistant), SMTP (OTP emails)
```

- **Presentation layer**: semantic HTML pages in the project root and `pages/`,
  themed via `css/style.css`, with all API calls, CSRF injection, and session/
  toast handling centralized in `js/app.js`.
- **Application layer**: each `backend/api/*.php` file is a single-purpose
  endpoint (one action per file — e.g. `login.php`, `risk_add.php`,
  `generate_report.php`). Shared logic (session/auth checks, CSRF checks,
  password rules, JSON response helpers, DB connection, AI/mail config)
  lives in `backend/config/`.
- **Storage layer**: relational data in MySQL (database name **`aks`**),
  uploaded evidence files on disk in `backend/uploads/`.

---

## 3. Folder Structure

```
AKS/
├── css/style.css                 # Design tokens, components, dark theme
├── js/app.js                     # fetch wrapper, CSRF injection, session/toast helpers
├── login.html                    # Login + email OTP verification screen
├── dashboard.html                # Main dashboard after login
├── pages/
│   ├── ai-assistant.html         # AI Compliance Assistant chat UI
│   ├── assessment.html           # Assessment / auto-mapping wizard
│   ├── audit-checklist.html      # Audit checklist tracker
│   ├── change-password.html      # Forced password change (first login)
│   ├── companies.html            # Company directory (CRUD, search, pagination)
│   ├── control-mapping.html      # Control-to-master-concept mapping view
│   ├── gap-analysis.html         # Compliance gap indicators
│   ├── reports.html              # PDF report downloads
│   ├── upload-documents.html     # Evidence upload + preview
│   └── users.html                # User/auditor directory, password reset
├── backend/
│   ├── api/                      # 50 single-purpose endpoint scripts
│   ├── config/
│   │   ├── database.php          # mysqli connection + auto column/table checks
│   │   ├── auth.php              # session hardening, login guards, password rules
│   │   ├── response.php          # send_success()/send_error() JSON helpers, CORS headers
│   │   ├── ai_config.php         # loads AI_PROVIDER/AI_API_KEY from backend/.env
│   │   ├── mailer.php            # SMTP/OTP email sending
│   │   └── GoogleAuthenticator.php # TOTP library (for authenticator-app MFA)
│   ├── fpdf/                      # Third-party FPDF PDF library (vendored, unmodified)
│   ├── uploads/                   # Evidence file storage (must be writable)
│   ├── schema.sql                 # Full DB schema + seed data
│   └── .env                       # AI provider + SMTP config (see §7 — do not commit real secrets)
```

---

## 4. Database Schema

`backend/schema.sql` creates a database named **`aks`** with **13 tables**.
Note: regulatory *frameworks* (PayNet TPA, BNM RMiT, MAS TRM, NACSA NC-II) are
**not** a separate table — `framework_id` is a plain integer convention seeded
directly into `controls` (1=PayNet TPA, 2=BNM RMiT, 3=MAS TRM, 4=NACSA NC-II).

| Table | Purpose | Key fields |
|---|---|---|
| `admins` | Platform admin accounts | `admin_id` PK, `email` (unique), `password` (bcrypt) |
| `login_attempts` | Failed-login tracking for lockout | `email` (indexed), `ip_address`, `attempt_time` |
| `companies` | Client organizations under audit | `company_id` PK, `company_name`, `registration_number`, `industry` |
| `users` | Company-side users/auditors | `user_id` PK, `company_id` FK, `role`, `first_login` |
| `activity_logs` | Audit trail of platform actions | `actor_type` enum(admin/user), `action`, `ip_address` |
| `documents` | Uploaded evidence | `company_id` FK, `file_path`, `framework`, `control_code`, `extracted_text` (LONGTEXT for RAG) |
| `assessments` | Company compliance runs | `company_id` FK, `current_framework_id`, `target_framework_id`, `compliance_percentage` |
| `controls` | Individual framework requirements | `control_code`, `control_name`, `description`, `framework_id` |
| `control_mappings` | Cross-framework control equivalence | `control_id` FK, `master_control_id` |
| `assessment_controls` | Per-control result within an assessment | `assessment_id` FK, `control_id` FK, `status` (Matched/Missing) |
| `audits` | Audit runs tied to a document | `company_id` FK, `document_id` FK, `progress`, `status` |
| `audit_checklist` | Checklist items per audit | `audit_id` FK, `checklist_item`, `is_completed` |
| `risks` | Risk register | `company_id` FK, `likelihood`, `impact`, `risk_score`, `mitigation_strategy` |

Seed data includes one default admin account and an initial taxonomy of
compliance controls mapped across the four frameworks above, grouped into
master concepts (Governance & Risk Management, Access Control & Identity,
Training & Awareness, Network Security, Incident Response).

---

## 5. Security Hardening

Verified directly in code:

1. **SQL injection** — all queries use `mysqli` prepared statements; no
   string-concatenated SQL was found in the API layer.
2. **XSS** — inputs are checked against a shared `has_html_tags()` rejection
   filter server-side; the frontend escapes dynamic content via a shared
   `escapeHtml()` helper before inserting it into the DOM.
3. **CSRF** — state-changing endpoints validate a per-session CSRF token
   (`backend/api/csrf_token.php` issues it); `js/app.js` attaches it as an
   `X-CSRF-Token` header on every relevant fetch call automatically.
4. **Session/cookie hardening** (`backend/config/auth.php`) —
   - Cookies set `HttpOnly`, `SameSite=Lax`, and `Secure` when served over HTTPS.
   - `session.use_strict_mode` enabled; ID regenerated on login.
   - Idle session timeout after **15 minutes** (900 seconds), enforced on every
     `start_secure_session()` call.
5. **Brute-force lockout** — failed attempts are logged to `login_attempts`
   (indexed by email) to support a rolling lockout window.
6. **Password policy** — `is_password_strong()` requires 8+ characters with
   upper, lower, digit, and symbol; `generate_temp_password()` produces
   compliant temporary passwords for admin-issued resets.
7. **Multi-factor auth** — email OTP flow (`resend_otp.php`, `verify_otp.php`)
   plus an optional TOTP/Google-Authenticator path (`GoogleAuthenticator.php`,
   `mfa_setup.php`, `mfa_enable.php`, `mfa_disable.php`, `verify_google_mfa.php`).
8. **CORS** — `backend/config/response.php` currently sets
   `Access-Control-Allow-Origin: *` for local development. **Restrict this to
   your actual frontend origin before any public/deployed use.**

⚠️ **Secrets note**: `backend/.env` in this submission is set to
`AI_PROVIDER="mock"` with an empty `AI_API_KEY` (safe), but the file also
contains SMTP credentials in cleartext. Before sharing or committing this
project anywhere public, remove real credentials from `.env`, rotate any
password that was ever committed, and keep `.env` out of version control
(add it to `.gitignore`).

---

## 6. AI Compliance Assistant & RAG Pipeline

`backend/api/ai_chat.php` implements a lightweight retrieval-augmented flow:

1. **Ingestion** — on upload (`upload_document.php`), evidence files
   (`.txt`, `.pdf`, `.docx`, `.xlsx`) are parsed with pure-PHP extractors:
   PDF text streams are decoded directly, DOCX/XLSX are read via `ZipArchive`
   and stripped of XML tags, TXT is read as-is. Extracted text is stored in
   `documents.extracted_text`.
2. **Backfill** — any previously uploaded file missing extracted text is
   re-indexed the next time a user opens the AI Assistant.
3. **Retrieval** — user queries are scored against stored document text by
   keyword occurrence; regular users only retrieve matches from their own
   company's documents.
4. **Generation** — matched snippets are injected into the prompt sent to
   Gemini when `AI_PROVIDER="gemini"` and a key is set; otherwise the app
   runs in **mock mode** (as currently configured), appending matched
   snippets directly to a canned advisory response so the feature still
   works fully offline for demos/grading without any API key.

---

## 7. Installation & Setup

**Requirements**: PHP 7.4+ (8.x recommended) with `mysqli`, `zip`, `curl`
extensions; MySQL/MariaDB; Apache (via XAMPP/WAMP/MAMP is easiest).

1. **Install a local stack** — e.g. [XAMPP](https://www.apachefriends.org).
2. **Place the project** in your web root so you get `htdocs/AKS/...`.
3. **Start Apache and MySQL** from the XAMPP control panel.
4. **Create the database** — open `http://localhost/phpmyadmin`, go to the
   SQL tab, paste in the full contents of `backend/schema.sql`, and run it.
   This creates the `aks` database, all 13 tables, and seed control data.
5. **Configure the DB connection** — check `backend/config/database.php`
   matches your local MySQL credentials (defaults match a stock XAMPP setup:
   host `localhost`, user `root`, empty password, db `aks`).
6. **Configure `.env`** in `backend/` for the AI Assistant and OTP email:
   ```env
   AI_PROVIDER="mock"        # or "gemini" if you have a Gemini API key
   AI_API_KEY=""
   SMTP_HOST="smtp.gmail.com"
   SMTP_PORT=587
   SMTP_USER=""
   SMTP_PASS=""
   SMTP_FROM=""
   SMTP_FROM_NAME="Compliance Audit Platform"
   ```
   Leaving SMTP blank falls back to PHP's native `mail()`; leaving
   `AI_PROVIDER="mock"` runs the AI Assistant fully offline.
7. **Make `backend/uploads/` writable** by the web server.
8. **Log in** — a seeded admin account exists
   (`admin@complianceaudit.com` / `admin123` per the seed data), or generate
   a fresh hash via `backend/generate_hash.php` and insert a row into `admins`.
9. **Open the app** at `http://localhost/AKS/login.html`.

---

## 8. Feature Guide

| Page | Purpose |
|---|---|
| `dashboard.html` | Compliance progress overview, alerts, quick actions |
| `pages/companies.html` | Add/search/paginate client companies |
| `pages/users.html` | Manage user/auditor accounts, force password resets |
| `pages/assessment.html` | Run an assessment, trigger auto control-mapping |
| `pages/control-mapping.html` | Review how controls map across frameworks |
| `pages/gap-analysis.html` | View missing/matched controls per assessment |
| `pages/audit-checklist.html` | Track audit checklist completion with progress bars |
| `pages/upload-documents.html` | Upload evidence (≤10MB), preview, search |
| `pages/ai-assistant.html` | Chat with the AI Compliance Assistant, RAG-backed |
| `pages/reports.html` | Generate/download PDF compliance & audit reports |
| `pages/change-password.html` | Forced on first login with a temporary password |

**Admin capabilities**: company/user management, password resets, and a
full activity log (`activity_logs`) of logins, lockouts, company changes,
uploads, and logouts for auditor review.

---

## 9. API Surface (`backend/api/`, 50 endpoints)

Grouped by domain:

- **Auth/session**: `login.php`, `user_login.php`, `logout.php`,
  `csrf_token.php`, `resend_otp.php`, `verify_otp.php`,
  `mfa_setup.php` / `mfa_enable.php` / `mfa_disable.php` / `verify_google_mfa.php`,
  `change_password.php`, `reset_password.php`, `admin_change_password.php`
- **Companies**: `company_add.php`, `company_list.php`, `company_update.php`, `company_delete.php`
- **Users**: `user_add.php`, `user_list.php`, `user_update.php`, `user_delete.php`
- **Risks**: `risk_add.php`, `risk_list.php`, `risk_update.php`, `risk_delete.php`
- **Assessments/controls**: `create_assessment.php`, `assessment_list.php`,
  `update_assessment_control.php`, `control_list.php`, `run_mapping.php`,
  `add_control_description.php`, `update_db_controls.php`
- **Audits**: `create_audit.php`, `get_audit_progress.php`, `update_checklist.php`, `audit_readiness.php`
- **Documents**: `upload_document.php`, `document_list.php`, `document_delete.php`
- **Reporting (PDF, via FPDF)**: `generate_report.php`, `audit_readiness_report.php`,
  `compliance_report.php`, `compliance_coverage_report.php`, `control_mapping_report.php`,
  `coverage_report.php`, `gap_analysis.php`, `get_compliance.php`
- **AI Assistant**: `ai_chat.php`
- **Ops/misc**: `activity_logs.php`, `debug_db.php`, `generate_hash.php`, `test_fpdf.php`

All endpoints return a consistent JSON envelope: `{ "success": bool, "message": str, "data": any }`.

---

## 10. Known Limitations / Suggested Next Steps

1. Set `Access-Control-Allow-Origin` to a specific origin before any
   non-local deployment.
2. Remove real SMTP credentials from `backend/.env` before sharing this
   codebase publicly (e.g. GitHub) and add `.env` to `.gitignore`.
3. The RAG search is keyword-occurrence based; a vector-embedding index
   (e.g. pgvector or a SQLite vector extension) would scale better and
   improve retrieval quality.
4. No OCR — image evidence (screenshots, scanned docs) isn't text-extracted.
   Integrating a PHP OCR wrapper (e.g. Tesseract) would close this gap.
5. Consider webhook/notification support so external auditors are alerted
   when a company's compliance percentage changes.

---

*This documentation was generated from a direct review of the codebase
(schema, config, and API files) to ensure it reflects the app as submitted,
rather than restating prior documentation as-is.*
