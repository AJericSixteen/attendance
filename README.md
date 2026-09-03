# Attendance System

A plain PHP + MySQL attendance system. Admins create teacher accounts; teachers create subjects and take attendance by scanning student QR codes (or entering them manually), then export attendance to Excel.

## Requirements

- [Laragon](https://laragon.org/) (or any Apache/Nginx + PHP + MySQL stack)
- PHP 8.1+
- MySQL 8.x
- [Composer](https://getcomposer.org/) (needed once, to install the `vendor/` folder — it's gitignored and not committed to the repo)

## Setup — step by step

### 1. Get the project into your web root

Place (or `git clone`) the project so it lives at:

```
C:\laragon\www\attendance
```

### 2. Install PHP dependencies (rebuilds `vendor/`)

The `vendor/` folder (PhpSpreadsheet, used for the Excel export) is gitignored, so it won't exist after a fresh clone. Open a terminal in the project folder and run:

```bash
cd C:\laragon\www\attendance
composer install
```

This reads `composer.json` / `composer.lock` and downloads everything into `vendor/`.

### 3. Create the database

Open a terminal and import the schema (this creates the `attendance_system` database, its tables, and a default admin account):

```bash
mysql -u root -p < database.sql
```

- If your MySQL root user has no password, drop `-p` (or press Enter when prompted).
- Using Laragon's bundled MySQL client instead of a system one, e.g.:
  ```bash
  "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -p < database.sql
  ```

### 4. Configure the app

Copy [.env.example](.env.example) to `.env` and fill in your MySQL credentials:

```bash
cp .env.example .env
```

```env
DB_HOST=localhost
DB_NAME=attendance_system
DB_USER=root
DB_PASS=        # set this to your MySQL root password
DB_CHARSET=utf8mb4

DIFY_API_KEY=            # optional, powers the "Help Assistant" chat widget
DIFY_API_BASE_URL=https://api.dify.ai/v1
```

`.env` is gitignored — it holds real credentials and is never committed. `config/db.php` and `config/dify.php` just read from it.

The `DIFY_API_KEY` is optional: it enables the floating help-chat widget (bottom-right on every page) so teachers can ask questions about the system. Leave it blank to disable the chatbot. See [dify/attendance-help-assistant.yml](dify/attendance-help-assistant.yml) for a ready-to-import Dify app pre-loaded with this system's teacher workflows.

### 5. Start Laragon (Apache + MySQL)

Make sure both Apache and MySQL are running in Laragon.

### 6. Open the app

Visit the site in your browser, e.g.:

```
http://attendance.test/
```

or

```
http://localhost/attendance/
```

### 7. Log in

Default admin account (change the password after logging in — there's no UI for changing the admin's own password yet, so update it directly in the `users` table if needed):

```
Username: admin
Password: admin123
```

From there:
- **Admin** creates teacher accounts (Teacher Accounts page → "+ New Teacher"). Clicking a teacher card lets you change their password, deactivate, or (once deactivated) permanently delete the account.
- **Teacher** logs in, creates subjects ("+ New Subject" — Subject Code, Subject Name, Section), then clicks a subject card to open the QR/manual attendance scanner and export attendance to Excel.

## Re-running setup later (e.g. after a fresh `git pull`)

Only a few things are ever missing after a fresh checkout — repeat steps 2, 3, and 4:

```bash
cd C:\laragon\www\attendance
composer install
mysql -u root -p < database.sql
cp .env.example .env   # then fill in your credentials
```

`database.sql` uses `CREATE DATABASE IF NOT EXISTS` and `INSERT ... ON DUPLICATE KEY UPDATE`, so re-running it against an existing database will not wipe your data — it only creates what's missing.
