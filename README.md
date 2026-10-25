# 📚 MEOWMEOW Library — Study Room Booking System

ระบบจองห้องอ่านหนังสือ/ห้องประชุมในห้องสมุด แบบ **Full Stack** พัฒนาด้วย **PHP + MySQL**
รองรับผู้ใช้ 3 ระดับ (Student / Admin / Super Admin) พร้อมระบบจองแบบกันเวลาชนกัน,
blacklist อัตโนมัติ, การยืนยันตัวตนนักศึกษา และการแจ้งเตือน

A full-stack library study-room booking system built with vanilla PHP and MySQL,
with three role levels and server-side booking logic (stored procedures & triggers).

---

## ✨ ฟีเจอร์หลัก (Features)

**Student**
- สมัครสมาชิก / เข้าสู่ระบบ (รหัสผ่านเข้ารหัสด้วย `password_hash` / bcrypt)
- จองห้อง เลือกวันและช่วงเวลา (กันการจองซ้อนเวลาอัตโนมัติ)
- จำกัดการจองไม่เกิน 2 ครั้ง/วัน
- ดูประวัติการจอง / ยกเลิกการจอง (ยกเลิกครบ 3 ครั้ง → blacklist อัตโนมัติ)
- แก้ไขโปรไฟล์ + อัปโหลดรูป

**Admin**
- จัดการห้อง (เพิ่ม / แก้ไข / ลบ / เปิด-ปิดสถานะห้อง)
- จัดการการจองของนักศึกษา
- ยืนยันตัวตน (verify) นักศึกษา
- จัดการ blacklist
- บันทึกประวัติการทำงานของแอดมิน (admin history)

**Super Admin**
- จัดการบัญชีแอดมิน (เพิ่ม / แก้ไข / ลบ)
- ตั้งค่าระบบ (system config), รายงาน, activity log

---

## 🧱 Tech Stack

| ส่วน | เทคโนโลยี |
|------|-----------|
| Frontend | HTML, CSS (vanilla) |
| Backend | PHP (mysqli, prepared statements) |
| Database | MySQL / MariaDB |
| ตรรกะฝั่ง DB | Stored Procedures, Triggers, Foreign Keys |
| Dev Server | XAMPP / MAMP (Apache + MySQL) |

---

## 📁 โครงสร้างโปรเจค

```
library-study-room-booking/
├── database/
│   └── library_room_booking.sql   # โครงสร้างตาราง + procedure + trigger + ข้อมูลตัวอย่าง
├── admin/                         # หน้าและลอจิกฝั่งแอดมิน
├── superadmin/                    # หน้าและลอจิกฝั่ง super admin
├── student/                       # หน้าและลอจิกฝั่งนักศึกษา
├── uploads/                       # ที่เก็บรูปโปรไฟล์ที่ผู้ใช้อัป (รันไทม์)
├── db.php                         # การเชื่อมต่อฐานข้อมูล
├── security_helpers.php           # ฟังก์ชันกลาง: hash รหัสผ่าน + ตรวจไฟล์อัปโหลด
├── login.php / login_handler.php  # เข้าสู่ระบบ
├── signup.php / signup_handler.php# สมัครสมาชิก
└── style.css / Logo.png           # ธีมหลัก
```

---

## 🚀 วิธีติดตั้งและรัน (Setup)

> ต้องมี **XAMPP** หรือ **MAMP** (Apache + MySQL/MariaDB + PHP 8+)

1. **วางโปรเจค** ไว้ในโฟลเดอร์เว็บเซิร์ฟเวอร์ โดยตั้งชื่อโฟลเดอร์ว่า `librarystudyroombooking`
   (โค้ดบางส่วนอ้าง path `/librarystudyroombooking/...` — ถ้าใช้ชื่ออื่นต้องแก้ path ในไฟล์ redirect)
   - XAMPP: `htdocs/librarystudyroombooking`
   - MAMP: `htdocs/librarystudyroombooking`

2. **สร้างฐานข้อมูล** — เปิด phpMyAdmin → สร้าง database ชื่อ `library_room_booking`
   แล้ว **Import** ไฟล์ `database/library_room_booking.sql`

3. **ตั้งค่าการเชื่อมต่อ** — ไฟล์ `db.php` (และใน `admin/`, `superadmin/`, `student/`)
   ค่าเริ่มต้นใช้ MySQL user ตาม role:
   | role | user | password |
   |------|------|----------|
   | (ก่อน login) | `root` | `root` |
   | student | `student` | `stu123` |
   | admin | `admin` | `ad123` |
   | super admin | `super admin` | `superad123` |

   > ⚠️ ค่าเหล่านี้เป็น MySQL user แยกตาม role หากเครื่องคุณใช้แค่ `root`
   > ให้แก้ `$user`/`$pass` ในไฟล์ `db.php` ทุกไฟล์ให้ชี้ไปที่ `root` ของคุณ
   > หรือสร้าง MySQL user ตามตารางด้านบนแล้ว `GRANT` สิทธิ์บน `library_room_booking`

4. **เปิดเว็บ** ที่ `http://localhost/librarystudyroombooking/login.php`

---

## 🔑 บัญชีทดลอง (Demo accounts)

| Role | Email | Password |
|------|-------|----------|
| Super Admin | `admin@example.com` | `admin123` |
| Admin | `test@gmail.com` | `test123` |
| Student | *สมัครใหม่ที่หน้า Signup แล้วให้ Admin กด verify* | — |

> - นักศึกษาที่สมัครใหม่จะมีสถานะ `pending` ต้องให้ Admin ยืนยันก่อนจึงจะ login ได้
> - หน้า Signup สมัครได้เฉพาะ **Student** บัญชี Admin สร้างได้จากหน้า Super Admin เท่านั้น

---

## 🔒 Security

- รหัสผ่านใช้ `password_hash()` / `password_verify()` — บัญชีเก่าที่เป็น SHA-256/MD5
  จะถูกอัปเกรดเป็น hash แบบใหม่อัตโนมัติเมื่อ login ครั้งแรก
- อัปโหลดรูปตรวจชนิดไฟล์จากเนื้อไฟล์จริง (JPG/PNG, ≤ 2MB) ตั้งชื่อไฟล์เอง
  และ `uploads/.htaccess` ห้ามรันสคริปต์ในโฟลเดอร์อัปโหลด
- ใช้ prepared statements กับ query ที่รับค่าจากผู้ใช้
- เช็คสิทธิ์ตาม role ทุกหน้า, การแก้โปรไฟล์ใช้ user id จาก session เท่านั้น
- `session_regenerate_id()` หลัง login

---

## ⚠️ หมายเหตุ / Known limitations

- รหัสผ่านใน `db.php` เป็นค่าสำหรับ **เครื่อง local เท่านั้น** ไม่ใช่รหัสจริงบน production
- ปุ่ม **DOWNLOAD** บางหน้าของ Super Admin (`download_*.php`) และปุ่มลบใน `delete_admin.php`
  ยังไม่ได้พัฒนา
- path `/librarystudyroombooking/` ถูกเขียนตายตัวในโค้ด ต้องตั้งชื่อโฟลเดอร์ตามนี้
- โฟลเดอร์ `uploads/` เก็บไว้เฉพาะ `default.jpg` — รูปที่ผู้ใช้อัปจริงจะไม่ถูกเก็บลง Git (ดู `.gitignore`)

---

## 🛠️ Changelog (GitHub version)

- แก้ trigger auto-blacklist ที่เรียก procedure ที่ไม่มีอยู่ ทำให้การยกเลิกครั้งที่ 3 error และข้อมูลการจองค้าง
- ปลด blacklist แล้วรีเซ็ตยอดยกเลิก, เก็บประวัติ blacklist แทนการลบ
- แก้การเรียก `sp_SendNotification` ผิดจำนวนพารามิเตอร์ตอนยกเลิกการจอง
- รวมวิธีเก็บรหัสผ่านเป็นแบบเดียว (เดิมใช้ปนกัน SHA-256 / MD5 / bcrypt ทำให้เปลี่ยนรหัสแล้ว login ไม่ได้)
- ปิดช่องโหว่: สมัครเป็น admin เองได้, อัปโหลดไฟล์ .php ได้, แก้รหัสผ่านคนอื่นได้, SQL injection

---

## 📝 License

โปรเจคเพื่อการศึกษา (Educational project)
