# AKS – Compliance Audit Automation Platform

A PHP + MySQL web app for managing compliance audits: companies, users,
assessments, control mapping, gap analysis, audit checklists, document
uploads, and PDF report generation.

I checked the project end to end:
- All PHP files (excluding the third-party FPDF library) pass `php -l`
  with no syntax errors.
- `backend/schema.sql` was run against a real MySQL/MariaDB server and
  creates all 11 tables and seed data with no errors.
- Database name changed from `compliance_audit_db` to **`aks`** in both
  `backend/schema.sql` and `backend/config/database.php`.
- Removed junk files (`.DS_Store`, `__MACOSX`, `.git`, leftover sample
  upload `.docx` files) that had nothing to do with the running app.

## Requirements

- PHP 7.4+ (with the `mysqli` extension enabled)
- MySQL or MariaDB
- A local server stack: XAMPP / WAMP / MAMP, or PHP's built-in server

## Step-by-step setup

### 1. Install a local server stack
If you don't already have one, install **XAMPP** (Windows/Mac/Linux):
https://www.apachefriends.org — it bundles Apache, PHP, and MySQL.

### 2. Copy the project into your server's web folder
- XAMPP on Windows: copy the `AKS` folder into `C:\xampp\htdocs\`
- XAMPP on Mac/Linux: copy it into `/Applications/XAMPP/htdocs/` or `/opt/lampp/htdocs/`

So you end up with `htdocs/AKS/...`.

### 3. Start Apache and MySQL
Open the XAMPP Control Panel and click **Start** next to both Apache and MySQL.

### 4. Create the database
1. Open `http://localhost/phpmyadmin` in your browser.
2. Click the **SQL** tab.
3. Open `AKS/backend/schema.sql` in a text editor, copy all of it, paste it
   into the SQL box, and click **Go**.
   - This creates a database called **`aks`** with all 11 tables and seeds
     the standard compliance controls and mappings.
4. Confirm in the left sidebar that the `aks` database now appears with its
   tables.

### 5. Check the database connection settings
Open `AKS/backend/config/database.php` and confirm it matches your local
MySQL setup (defaults shown below match a stock XAMPP install, so usually
no changes are needed):

```php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "aks";
```

If your MySQL has a root password set, update `$DB_PASS` accordingly.

### 6. Create your first admin login
The schema doesn't seed an admin account, so generate one:
1. Visit `http://localhost/AKS/backend/generate_hash.php` in your browser
   (or `backend/api/generate_hash.php`) — it will print a hashed password
   for a password you choose by editing the file, or print a sample hash.
2. In phpMyAdmin, open the `admins` table and insert a row manually with
   your name, email, and the generated password hash.

   Alternatively, run this SQL in phpMyAdmin's SQL tab (replace the email
   and hash with your own — generate the hash first):
   ```sql
   INSERT INTO admins (name, email, password)
   VALUES ('Admin', 'admin@example.com', '<paste hashed password here>');
   ```

### 7. Open the app
Go to:
```
http://localhost/AKS/login.html
```
Log in with the admin email/password you created in step 6.

### 8. Using the app
- **Dashboard** (`dashboard.html`) – overview after login.
- **pages/companies.html** – manage client companies.
- **pages/users.html** – manage user accounts.
- **pages/assessment.html** – run compliance assessments.
- **pages/control-mapping.html** – map controls to master concepts.
- **pages/gap-analysis.html** – view compliance gaps.
- **pages/audit-checklist.html** – audit checklist tracking.
- **pages/upload-documents.html** – upload supporting evidence documents.
- **pages/reports.html** – generate PDF compliance reports (powered by the
  bundled FPDF library in `backend/fpdf/`).
- **pages/change-password.html** – change your password (required on first
  login if a temporary password was issued).

## Notes
- `Access-Control-Allow-Origin: *` is set in `backend/config/response.php`
  for development convenience. Before deploying this anywhere public,
  restrict it to your actual frontend's origin.
- Uploaded documents are saved to `backend/uploads/` — make sure this
  folder is writable by your web server.
