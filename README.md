# CARE Group Medical Services

Doctor search and appointment booking portal (PHP + MySQL) with three roles: patient, doctor and admin.

## Setup (XAMPP)

1. Copy this `php-project` folder into `C:\xampp\htdocs\` (rename it `care-group` if you like).
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Open phpMyAdmin (`http://localhost/phpmyadmin`) and import the database:
   - **New install:** import `database/schema.sql`.
   - **Already have the old database with data:** import `database/update_part1.sql` instead. It keeps your data.
4. If your MySQL user or password is not `root` / empty, edit the settings at the top of `db.php`.

## Where each role logs in

| Role    | Page                     | Notes                                              |
|---------|--------------------------|----------------------------------------------------|
| Patient | `login.php`              | Patients create their own account on `register.php` |
| Doctor  | `login.php`              | Accounts are created by the admin                  |
| Admin   | `admin/login.php`        | Not linked from the public site. Locks for 15 minutes after 5 wrong passwords |

Default admin: **admin / admin123**. Change it after the first sign-in.

## Folder structure

```
php-project/
├── admin/             Admin-only pages (separate login)
├── assets/css/        theme.css  - design system (dark glass, neon accents)
├── assets/js/         app.js     - page transitions, parallax, 3D tilt, counters, toasts
├── database/          schema.sql and update scripts
├── includes/          functions.php, header.php, footer.php (shared by every page)
├── db.php             Database connection and secure session
└── *.php              Public pages and dashboards
```

## Security features

- Passwords stored with `password_hash()` and checked with `password_verify()` only
- CSRF token on every login and sign-up form
- Session ID regenerated at login; HttpOnly, SameSite session cookie
- Login lock-out after repeated wrong passwords (separate limits for public and admin)
- All database queries use prepared statements; all output is escaped with `h()`
- Database errors are logged, never shown to visitors
