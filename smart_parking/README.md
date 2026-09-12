# Smart Vehicle Parking Management System

Number Plate Recognition · QR Code Entry/Exit · Online Reservation

A full-stack parking management system built for a stock WAMP install
(Apache + MySQL/MariaDB + PHP 8). PHP/MySQL/Bootstrap 5/JS handle the
web app; a separate Python script handles license-plate recognition at
the entry gate.

This system has been syntax-checked (`php -l`) across every file and
functionally tested end-to-end against a live MySQL/MariaDB database
(registration, login, booking, slot-conflict prevention, vehicle
entry, QR exit, fee calculation, cancellation, and the cron auto-expiry
job all run correctly).

## 1. Requirements

- WAMP (or any Apache + PHP 8 + MySQL/MariaDB stack) — WAMPSERVER works
  out of the box, no extra PHP extensions needed beyond the defaults.
- A modern browser (the entry/exit QR scanner needs camera access, so
  use `localhost` or HTTPS — browsers block camera access on plain
  HTTP non-localhost addresses).
- Python 3.10+ only if you want to run the live plate-recognition
  camera script (`python/`). The web app and admin panel work fully
  without it — you can always type a plate number in manually via
  the QR scanner's "manual token" box or by hitting `api/entry.php`
  yourself for testing.

## 2. Installation

1. **Copy the project folder.** Place this entire folder inside your
   WAMP `www` directory so the path is:
   `C:\wamp64\www\smart_parking\` (keep the folder name `smart_parking`
   — it must match the path in `BASE_URL` below, or update that
   constant to match whatever folder name you use).

2. **Import the database.** Open phpMyAdmin
   (`http://localhost/phpmyadmin`), create nothing manually — just go
   to the **Import** tab and import `database/schema.sql`. It creates
   the `smart_parking_db` database itself, all 10 tables, and seeds:
   - 1 admin account (see credentials below)
   - 20 sample parking slots across Zone A and Zone B
   - default system settings (grace period, hourly rate, currency, etc.)

3. **Check `config/config.php`.** The defaults already match a stock
   WAMP install (`DB_HOST=localhost`, `DB_USER=root`, `DB_PASS=''`).
   If you set a MySQL root password in WAMP, update `DB_PASS` here.
   Also confirm `BASE_URL` matches your actual folder name/URL.

4. **Set a real device key before going live.** `DEVICE_API_KEY` in
   `config/config.php` ships with a placeholder value
   (`CHANGE_THIS_TO_A_RANDOM_SECRET_BEFORE_DEPLOYMENT`). Change it to
   any random string, and put the *same* string in
   `python/plate_recognition.py`'s `DEVICE_API_KEY` constant. This key
   is what lets the unattended entry camera talk to the API without a
   human login.

5. **Visit the site.** `http://localhost/smart_parking/user/login.php`
   for the user side, `http://localhost/smart_parking/admin/login.php`
   for the admin panel.

That's it — no Composer, no `npm install`, no extra PHP extensions.
Bootstrap, Chart.js, the QR rendering library, and the QR *scanner*
library are all loaded from a CDN in the browser, so nothing needs to
be installed server-side for those.

## 3. Default login

| Role  | Username/Email      | Password    |
|-------|----------------------|-------------|
| Admin | `admin`              | `Admin@123` |

**Change this password immediately** after your first admin login (or
update the `admins` table directly) — it's a well-known default seeded
purely so the system is usable out of the box.

Regular users register themselves at `user/register.php`.

## 4. How the system actually works

### Slot status model
Each parking slot has **one** status at a time: `available`,
`reserved`, `occupied`, or `maintenance`. Booking a slot instantly and
exclusively flips it to `reserved` for every user looking at the
system — there's no separate per-date calendar, which is what makes
"no two people can ever book the same slot for the same time" trivial
to guarantee rather than something that needs careful date-range
overlap logic.

### Conflict prevention (defense in depth)
1. **Application-level lock:** booking runs inside a database
   transaction that does `SELECT ... FOR UPDATE` on the slot row
   before allowing the booking, so two simultaneous requests can't
   both see the slot as available.
2. **Database-level lock (the real backstop):** the `reservations`
   table has a generated column (`active_lock`) that's `NULL` for
   inactive bookings and a composite `slot+date+time` key for
   active ones, with a `UNIQUE` index on it. Even if the
   application-level lock were somehow bypassed, MySQL itself will
   reject a genuine duplicate booking with a 1062 "duplicate key"
   error, which the code catches and turns into a friendly message.

### Booking → Entry → Exit lifecycle
1. User books a slot online → slot becomes `reserved`, a QR code
   token is issued and shown to the user (rendered as a real QR image
   client-side via the `qrcodejs` library — no server-side image
   library needed).
2. At the gate, the Python script detects the plate and calls
   `api/entry.php`. If there's a matching unexpired reservation, that
   slot is used and flips to `occupied`; otherwise the system treats
   it as a walk-in and assigns the next available slot (issuing a
   fresh QR for it too, so every vehicle — reserved or walk-in — can
   exit the same way).
3. On exit, staff scan the QR with the camera on the **admin** panel's
   "Scan Exit" page (`admin/scan_exit.php`, using the `html5-qrcode`
   library). The system computes the duration, calculates the fee
   (1-hour minimum, then billed per hour), records the payment, frees
   the slot, and prints a receipt on screen.
4. If a reservation is never used at all, `cron/expire_bookings.php`
   (see below) automatically expires it after the configured grace
   period and frees the slot.

### Why QR generation has no server-side dependency
Rather than relying on PHP's GD library or a Composer package to draw
QR code images (which may not be installed on every WAMP setup), the
backend only ever stores an opaque random token. The actual QR
**image** is drawn in the browser using the `qrcodejs` CDN library
(for showing/downloading a code) and read back using `html5-qrcode`
(for scanning). This keeps the PHP side dependency-free while still
fully satisfying "QR code generation" and "QR code scanning."

### Reports: PDF/Excel export
Rather than bundling a heavyweight, hard-to-verify PDF library, "PDF
export" is implemented via the browser's native Print dialog
(`window.print()` on a print-optimized layout in
`admin/reports.php` — File → Print → Save as PDF works in every
browser, with zero server dependencies). "Excel export" streams a real
CSV file (`admin/export_csv.php`), which Excel opens natively. Both
approaches are common, robust, real-world patterns for exactly this
kind of report.

## 5. Scheduling the auto-expiry job

`cron/expire_bookings.php` finds confirmed reservations whose grace
period has passed (the driver never showed up) and releases the slot.
It's a CLI script — set it up in **Windows Task Scheduler** to run
every 1–5 minutes:

- Program: `C:\wamp64\bin\php\phpX.Y.Z\php.exe` (match your installed
  PHP version's folder)
- Arguments: `"C:\wamp64\www\smart_parking\cron\expire_bookings.php"`

You can also just run it manually any time from a command prompt to
test it.

## 6. Running the plate-recognition camera script (optional)

```
cd python
pip install -r requirements.txt
python plate_recognition.py
```

Before running it, edit the `CONFIG` block at the top of
`plate_recognition.py`:
- `API_BASE_URL` → your site's URL (e.g. `http://localhost/smart_parking/`)
- `DEVICE_API_KEY` → must exactly match `config/config.php`
- `CAMERA_SOURCE` → `0` for a USB webcam, or an RTSP/HTTP URL for an
  IP camera

The script needs a YOLOv8 license-plate-detector model file at
`python/models/license_plate_detector.pt` for real plate localization
— see `python/models/PUT_MODEL_HERE.txt` for where to find one. If
that file is missing, the script still runs in a lower-accuracy
fallback mode (OCR-ing a fixed region of the frame) so you can demo
the full entry→exit pipeline without training or downloading a custom
model first.

## 7. Project structure

```
smart_parking/
├── database/schema.sql        Full MySQL schema + seed data
├── config/                    DB connection + global settings
├── includes/                  Shared PHP helpers (auth, CSRF, QR, etc.)
├── assets/                    CSS + JS shared by user-facing pages
├── user/                      Register, login, booking, dashboard, profile
├── admin/                     Admin login, dashboard, full CRUD, reports
├── api/                       AJAX/JSON endpoints used by both panels
│                              and the entry/exit hardware flow
├── python/                    YOLOv8 + EasyOCR entry camera script
├── cron/                      Scheduled auto-expiry job
├── uploads/, logs/            Runtime-only folders (kept empty on purpose)
```

## 8. Security notes

- All database queries use prepared statements (no SQL injection
  surface).
- Output is HTML-escaped everywhere (`e()` helper) to prevent XSS.
- Every state-changing form/AJAX call is protected by a CSRF token.
- Passwords are hashed with bcrypt (`password_hash`/`password_verify`).
- Sessions regenerate their ID on login to prevent session fixation.
- The entry/exit hardware endpoints are gated by a separate device key
  (never a user/admin session), and the exit endpoint additionally
  requires a logged-in admin session, since a human needs to be
  physically present scanning the QR code.

## 9. If something doesn't look right

- **Blank page / 500 error:** check `logs/php_errors.log` first —
  `display_errors` is intentionally turned off (it's a security best
  practice), so runtime errors go there instead of on-screen.
- **"Could not connect to database":** double check `DB_PASS` in
  `config/config.php` matches your MySQL root password (empty by
  default on WAMP).
- **Camera scanner won't open:** browsers only allow camera access on
  `localhost` or HTTPS — `http://127.0.0.1` or `http://localhost`
  works, but accessing the site via a LAN IP address over plain HTTP
  will be blocked by the browser itself, not the app.
