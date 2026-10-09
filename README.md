# College Hostel Management System (PHP)

A complete hostel management portal for **YSR Engineering College of YVU, Proddatur** — public website + student portal + warden panel + admin panel — built in core PHP + MySQL (PDO), Bootstrap 5, running on XAMPP.

Anyone opening this repo should be able to install it, configure it, log in, and understand every module in ~15 minutes.

Live code repo: `https://github.com/sudharshanpoluru-oss/college_hostel_php`

---

## 1. What this project is

| Item | Detail |
|---|---|
| College | YSR Engineering College of YVU, Proddatur |
| Purpose | Manage rooms, allocations, students, attendance, mess, fees & payments, complaints, leaves, visitors, notices, events, discipline, medical, maintenance |
| Stack | PHP 8.x (no framework), MySQL/MariaDB (PDO), Bootstrap 5 + Bootstrap Icons, vanilla JS |
| Server | XAMPP on Windows (`C:\xampp\htdocs\hostel`), also works on any LAMP stack |
| Auth | Sessions + `password_hash()` / `password_verify()`, CSRF tokens, login-attempt throttling, remember-me |
| Payments | SBI Collect (manual ref), UPI (manual UTR), PhonePe PG (UAT test creds), Razorpay (test keys placeholder) |
| Email | Raw SMTP over SSL via `includes/mailer.php` (Gmail App Password) for forgot-password |

Entry point: `index.php` → redirects to `public/index.php` (public homepage).

---

## 2. Features (module-wise)

### Public website (`public/`)
- `index.php` — hero, stats (rooms/students), featured rooms, staff, testimonials, FAQ, events, notices, gallery
- `rooms.php` — room types, fees, availability
- `about.php`, `amenities.php`, `gallery.php`, `contact.php` — contact form saves to `contact_messages`

### Student portal (`student/` — role `student`)
- `dashboard.php` — room, dues, notices, attendance %
- `my-room.php`, `room-change.php`, `vacate.php` — requests → `room_change_requests`, `vacate_requests`
- `attendance.php`, `mess.php` (menu + ratings → `meal_ratings`), `leave.php` → `leaves`
- `complaints.php`, `maintenance.php` → `maintenance_requests`, `notices.php`, `notifications.php`, `events.php`, `emergency.php`
- Fees & payments:
  - `fees.php` — bill breakdown (days present × charge/day + electricity + mess), paid/due, history
  - `pay.php` — gateway chooser
  - `sbi-pay.php` — SBI Collect flow: open SBI Collect → pay → enter DU/CLRN ref (validated `/^[A-Z0-9]{6,20}$/`)
  - `upi-pay.php` — UPI + UTR submit
  - `phonepe-init.php` / `phonepe-callback.php` — PhonePe UAT
  - `receipt.php` — printable fee receipt (`#000123` format)
- `profile.php`, `notifications.php`

### Warden panel (`warden/` — role `warden`)
- `dashboard.php` — occupancy, today's attendance, pending leaves/complaints/maintenance
- `students.php`, `attendance.php`, `night-roll-call.php` → `night_roll_call`
- `leaves.php` (approve/reject), `complaints.php`, `maintenance.php`, `room-inspection.php` → `room_inspections`
- `discipline.php` → `discipline_records`, `medical.php` → `medical_records`, `emergency.php` → `emergency_reports`
- `visitors.php` → `visitor_logs`, `notices.php`, `notifications.php`, `daily-report.php` → `daily_reports`

### Admin panel (`admin/` — role `admin`)
- `dashboard.php`, `analytics.php`, `reports.php`, `search.php` (global)
- `students.php`, `student-timeline.php` → `student_timeline`, `vacate-requests.php`, `vacated-students.php` → `vacated_students`, `room-changes.php`
- `rooms.php`, `allocations.php`, `occupancy.php`, `room-maintenance-history.php`
- `fees.php` — create bills, record cash/cheque/DD, verify SBI/UPI refs
- `attendance.php`, `leaves.php`, `mess.php`, `notices.php`, `events.php`, `gallery.php`
- `complaints.php`, `maintenance.php`, `visitors.php`, `emergency.php`, `digital-id.php` → `student_digital_ids`
- `wardens.php` (+ `manage-staff.php`, `management_staff`), `notifications.php`, `backup.php` → `backup_history`, `profile.php`

---

## 3. Tech stack & key files

```
index.php                  → redirect to public/
public/                    → public website
auth/                      → login, register, logout, forgot/change password
  login.php                → role-aware login (admin/warden/student)
  register.php             → student self-registration
  forgot-password.php      → SMTP mail + password_resets tokens
config/
  config.php               → BASE_URL, SITE_NAME, DB, Razorpay/PhonePe/SMTP constants
  database.php             → PDO singleton db()
  functions.php            → sanitize(), redirect(), alerts, getTotal(), activity log
  security.php             → CSRF, login-attempt throttle
includes/
  session.php              → bootstrap (config+db+functions+security+auth helpers)
  header/footer.php        → public layout
  admin-header/footer.php, student-header/footer.php, warden-header/footer.php
  mailer.php               → sendMail() via ssl://smtp.gmail.com:465
student/ warden/ admin/    → role panels (see §2)
assets/css/style.css       → theme + hero + cards
assets/js/script.js
sql/database.sql           → base schema + seed
sql/migration.sql          → wardens, leaves, vacate, room-change, +extra tables
sql/migration_v2.sql       → events, FAQ, testimonials, medical, discipline, visitors, etc.
sql/migration_v3.sql       → billing columns (bill_month/year, charge_per_day, mess/electricity)
uploads/                   → room photos + student photos (git-ignored)
backups/                   → DB dumps from admin/backup.php (git-ignored)
```

Database tables (~40): `users`, `students`, `wardens`, `rooms`, `room_allocations`, `fees`, `attendance`, `leaves`, `complaints`, `complaint_logs`, `maintenance_requests`, `room_maintenance_history`, `room_inspections`, `room_change_requests`, `vacate_requests`, `vacated_students`, `mess_menu`, `meal_ratings`, `notices`, `notifications`, `hostel_events`, `gallery`, `testimonials`, `faq`, `management_staff`, `contact_messages`, `visitor_logs`, `discipline_records`, `medical_records`, `emergency_reports`, `emergency_logs`, `night_roll_call`, `daily_reports`, `student_digital_ids`, `student_timeline`, `student_feedback`, `lost_found`, `inventory`, `activity_log`, `audit_logs`, `login_attempts`, `password_resets`, `backup_history`.

---

## 4. Requirements

- PHP >= 8.0 with `pdo_mysql`, `openssl`, `mbstring`
- MySQL >= 5.7 / MariaDB >= 10.4 (phpMyAdmin recommended)
- Apache (XAMPP) or any PHP server
- Modern browser

---

## 5. Installation (XAMPP, from scratch)

```powershell
# 1. Clone / copy project
git clone https://github.com/sudharshanpoluru-oss/college_hostel_php.git C:\xampp\htdocs\hostel

# 2. Start Apache + MySQL in XAMPP Control Panel

# 3. Create database (phpMyAdmin → SQL tab, run in ORDER):
#   sql/database.sql
#   sql/migration.sql
#   sql/migration_v2.sql
#   sql/migration_v3.sql
```

Or via CLI:

```powershell
mysql -u root -e "SOURCE C:/xampp/htdocs/hostel/sql/database.sql"
mysql -u root hostel_db -e "SOURCE C:/xampp/htdocs/hostel/sql/migration.sql"
mysql -u root hostel_db -e "SOURCE C:/xampp/htdocs/hostel/sql/migration_v2.sql"
mysql -u root hostel_db -e "SOURCE C:/xampp/htdocs/hostel/sql/migration_v3.sql"
```

```powershell
# 4. Writable folders
# Ensure these exist and are writable:
#   hostel/uploads/
#   hostel/backups/
```

```powershell
# 5. Open in browser
# http://localhost/hostel
# Public site → http://localhost/hostel/public/index.php
```

---

## 6. Configuration (`config/config.php`)

```php
define('BASE_URL', 'http://localhost/hostel');  // change for production, e.g. https://hostel.yourdomain.in
define('SITE_NAME', 'YSR Engineering College of YVU - Hostel');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default empty
define('DB_NAME', 'hostel_db');
```

Payments (test defaults — replace before going live):

```php
define('RAZORPAY_KEY_ID', 'rzp_test_YOUR_KEY_ID');
define('RAZORPAY_KEY_SECRET', 'YOUR_KEY_SECRET');
define('PHONEPE_MERCHANT_ID', 'PGTESTPAYUAT86');   // UAT sandbox
define('PHONEPE_SALT_KEY', '96434309-7796-489d-8924-ab56988a6076');
define('PHONEPE_SALT_INDEX', 1);
define('PHONEPE_ENV', 'UAT'); // 'UAT' or 'PROD'
```

Email (forgot-password). Create a Gmail App Password at https://myaccount.google.com/apppasswords :

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'yourgmail@gmail.com');
define('SMTP_PASS', 'your_app_password');
```

> Never commit real SMTP/app passwords. This repo ships placeholders (`mailer.php` skips sending when placeholders are detected).

---

## 7. Default login

Seeded by `sql/database.sql`:

| Role | Username | Email | Password |
|---|---|---|---|
| Admin | `admin` | `admin@hostel.com` | `password` |

- Admin → `http://localhost/hostel/auth/login.php?role=admin`
- Student → register at `/auth/register.php`, then login with `role=student`
- Warden → created by admin in `admin/wardens.php`, then login with `role=warden`

Change the admin password immediately after install (`auth/change-password.php` or admin profile).

---

## 8. Daily workflows

**Admit a student:** Admin → Students → Add → fill profile → allocate room in Allocations → fee bill auto/manual in Fees.
**Collect fee (cash):** Admin → Fees → record payment → receipt generated.
**Collect fee (SBI/UPI):** Student → Fees → Pay → SBI Collect/UPI → enter ref → Admin → Fees → verify → Paid/Partial.
**Attendance:** Warden → Attendance (day) + Night Roll Call (night).
**Leave:** Student → Leave → apply → Warden/Admin → approve/reject → timeline updated.
**Complaint:** Student → Complaints → raise → Warden/Admin → Working → Resolved.
**Vacate:** Student → Vacate → reason → Admin → approve → archived to `vacated_students`.
**Backup:** Admin → Backup → dump → stored in `backups/` + logged in `backup_history`.

---

## 9. Security notes

- Passwords hashed with `password_hash()` (bcrypt), verified with `password_verify()`
- All DB access via PDO prepared statements
- CSRF token (`requireCSRF()`) on POST forms, `sanitize()` on output, session regeneration on login
- Login throttling via `login_attempts`, password-reset tokens via `password_resets` (expiry)
- `uploads/*` and `backups/*` are git-ignored — never commit student photos or dumps

---

## 10. Troubleshooting

| Symptom | Fix |
|---|---|
| `Database connection failed` | Check MySQL running, `DB_*` in `config.php`, DB imported |
| Blank page / 500 | Enable `display_errors` in `php.ini`, check Apache `error.log` |
| `BASE_URL` wrong links | Set `BASE_URL` to exact host path, no trailing slash |
| Forgot-password mail not sent | Set real Gmail + App Password; placeholders silently skip |
| PhonePe fails | Still in `UAT` — use test creds; switch to `PROD` + live keys only when approved |
| `uploads/` images 404 | Create `uploads/` dir, check `UPLOAD_PATH` uses `DOCUMENT_ROOT/hostel/uploads/` |
| `due_amount` error | Re-run `migration_v3.sql` (generated column + billing fields) |

---

## 11. Scripts & files you can ignore

- `tmp-schema.php` — one-off debug (`SHOW COLUMNS FROM students`), safe to delete
- `.gitkeep` — placeholder, safe to delete
- `h.html` — removed (old prototype)

---

## 12. Contributing

1. Create a branch: `git checkout -b feat/<name>`
2. Keep SMTP/payment secrets out of commits
3. Test on XAMPP PHP 8 + MariaDB before PR
4. PR target: `main` on `college_hostel_php`

---

## 13. License & credits

- For academic/hostel use at YSR Engineering College of YVU. No license file yet — add MIT/GPL if open-sourcing.
- Built with PHP, MySQL, Bootstrap 5, Bootstrap Icons.
