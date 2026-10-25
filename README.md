# 📚 MEOWMEOW Library — Study Room Booking System

A full-stack web application for booking study rooms and meeting rooms in a library.
Built with **vanilla PHP and MySQL**, it supports three user roles (Student, Admin, Super Admin)
and enforces its booking rules inside the database using **stored procedures and triggers**.

---

## ✨ Features

### Student
- Sign up and log in (passwords hashed with `password_hash` / bcrypt)
- Book a room by choosing a date and one or more time slots (overlapping bookings are rejected automatically)
- Limit of 2 bookings per day
- View booking history and cancel bookings (3 cancellations → automatic blacklist)
- Edit profile and upload a profile picture
- In-app notifications for confirmed / cancelled reservations

### Admin
- Manage rooms: add, edit, delete, and toggle availability
- Manage student bookings and view booking history
- Verify newly registered students (students cannot log in until verified)
- Manage the blacklist (restore users or re-blacklist them, with full history kept)
- Every admin action is recorded in an admin history log

### Super Admin
- Manage admin accounts (add, edit, delete)
- System configuration, reports, and a user activity log
- Blacklist and reservation overview

---

## 🧱 Tech Stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML, CSS (vanilla), a little JavaScript (`fetch` / JSON) |
| Backend | PHP 8 (`mysqli`, prepared statements) |
| Database | MySQL / MariaDB |
| Database logic | Stored procedures, triggers, foreign keys |
| Dev server | XAMPP or MAMP (Apache + MySQL + PHP) |

---

## 🗂️ How It Works

```
Browser ──► PHP pages (student/ admin/ superadmin/) ──► MySQL
                                                        ├─ tables (8)
                                                        ├─ stored procedures  (create / cancel reservation, notifications)
                                                        └─ triggers           (no double booking, auto-blacklist)
```

**Booking flow**
1. A student picks a room, a date, and time slots.
2. `sp_CreateReservation` creates the reservation (and rejects it if the student already has 2 bookings today).
3. Each time slot is inserted into the `book` table. The trigger `prevent_double_booking` rejects any slot that overlaps an existing booking for the same room and date.
4. `sp_SendNotification` writes a notification for the student.

**Cancellation and blacklist flow**
1. Cancelling a slot calls `sp_CancelReservation`. When the last slot is removed, the reservation becomes `cancelled`.
2. The trigger `tr_UpdateCancelCount` increases the student's cancellation count.
3. When the count reaches 3, the trigger `tr_AutoBlacklist` sets the student's status to `blacklisted` and adds a row to the `blacklist` table.
4. Restoring a student (from either the Student Management or Blacklist page) closes the blacklist row (kept as history) and resets the cancellation count to 0.

**Roles and access**
- Login redirects each user to their own area based on role.
- The database connection also switches MySQL user by role (`student`, `admin`, `super admin`).
- Every page checks the session role; pages for a role are not accessible to other roles.

---

## 🗄️ Database

Database name: `library_room_booking`

| Table | Purpose |
|-------|---------|
| `user` | Accounts for all roles (role, status, verify status, cancellation count, profile image) |
| `room` | Rooms with capacity, equipment, and availability |
| `reservation` | A booking made by a user for a room |
| `book` | The individual time slots that belong to a reservation |
| `blacklist` | Blacklist entries with reason, status (`active` / `inactive`), and dates |
| `notification` | Messages sent to users |
| `admin_history` | Audit log of admin / super admin actions |
| `system_config` | System settings (maintenance mode, max booking hours, etc.) |

**Stored procedures:** `sp_CreateReservation`, `sp_CancelReservation`, `sp_SendNotification`, `sp_AdminHistory_Update`

**Triggers:** `prevent_double_booking`, `tr_UpdateCancelCount`, `tr_AutoBlacklist`

---

## 📁 Project Structure

```
library-study-room-booking/
├── database/
│   └── library_room_booking.sql    # Schema + procedures + triggers + sample data
├── student/                        # Student pages (home, history, profile, contact, ...)
├── admin/                          # Admin pages (rooms, bookings, verification, blacklist, ...)
├── superadmin/                     # Super admin pages (staff, reports, system, activity, ...)
├── uploads/                        # Profile pictures uploaded at runtime
├── db.php                          # Database connection
├── security_helpers.php            # Password hashing + safe image upload helpers
├── login.php / login_handler.php   # Login
├── signup.php / signup_handler.php # Student registration
├── auth_protect.php                # Session / role guard
└── style.css, signup.css, Logo.png # Shared styling and assets
```

---

## 🚀 Getting Started

**Requirements:** XAMPP or MAMP (Apache + MySQL/MariaDB + PHP 8+)

1. **Place the project** in your web server folder and name the folder exactly `librarystudyroombooking`
   (the code uses absolute paths such as `/librarystudyroombooking/login.php`).
   - XAMPP: `C:\xampp\htdocs\librarystudyroombooking`
   - MAMP: `htdocs/librarystudyroombooking`

2. **Create the database.** Open phpMyAdmin, create a database named `library_room_booking`,
   then **Import** `database/library_room_booking.sql`.

3. **Configure database users.** The app connects with a MySQL user that depends on the role:

   | When | MySQL user | Password |
   |------|-----------|----------|
   | Before login | `root` | `root` |
   | Student | `student` | `stu123` |
   | Admin | `admin` | `ad123` |
   | Super admin | `super admin` | `superad123` |

   Either create these MySQL users and grant them privileges on `library_room_booking`,
   or simply edit `$user` and `$pass` in the `db.php` files (root, `student/`, `admin/`, `superadmin/`)
   to use your own MySQL account.

4. **Open the app:** `http://localhost/librarystudyroombooking/login.php`

---

## 🔑 Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| Super Admin | `admin@example.com` | `admin123` |
| Admin | `test@gmail.com` | `test123` |
| Student | Register on the Signup page, then log in as an admin and verify the account | — |

> - New students start as `pending` and must be verified by an admin before they can log in.
> - The Signup page only creates **student** accounts. Admin accounts are created by a Super Admin.

---

## 🔒 Security

- Passwords use `password_hash()` / `password_verify()`. Accounts that still have an older SHA-256 / MD5 hash are upgraded automatically on their next login.
- Image uploads are validated by their real file content (JPG/PNG, max 2 MB) and renamed by the server; `uploads/.htaccess` blocks script execution in the upload folder.
- Prepared statements are used for queries that include user input.
- Role checks on every protected page; profile updates always use the user id from the session.
- Session id is regenerated on login.

---

## ⚠️ Known Limitations

- The credentials in `db.php` are **local development defaults**, not for production. For a real deployment, move them to environment variables or a config file kept out of version control.
- The **DOWNLOAD** buttons on some Super Admin pages (`download_*.php`) and the delete button in `delete_admin.php` are not implemented yet.
- The project path `/librarystudyroombooking/` is hard-coded in redirects.
- `display_errors` is enabled in several pages for debugging; turn it off in production.
- There is no CSRF protection yet.

---

## 🛠️ Changelog (GitHub version)

- Fixed the auto-blacklist trigger, which called a missing procedure and caused an error on the 3rd cancellation
- Restoring a user now resets the cancellation count and keeps blacklist history instead of deleting it
- Fixed `sp_SendNotification` being called with the wrong number of parameters on cancellation
- Unified password hashing (previously a mix of SHA-256, MD5, and bcrypt that broke login after a password change)
- Closed security holes: self-registration as admin, uploading `.php` files, changing another user's password, SQL injection in the blacklist export

---

## 📝 License

Educational project.
