# 💊 ระบบติดตามวันหมดอายุยา – Drug Expiry Notification

> รพ.แม่แตง | HOSxP Integration + MOPH Notify LINE Alert + ปฏิทินไทย

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![HOSxP](https://img.shields.io/badge/HOSxP-PCU-green)
![MOPH Notify](https://img.shields.io/badge/MOPH-Notify-red)

---

## ✨ Features

| ฟีเจอร์ | รายละเอียด |
|---------|-----------|
| 🗓️ ปฏิทินไทย | แสดงวันหมดอายุในปฏิทิน พ.ศ. รองรับทุกอุปกรณ์ |
| 🔍 ค้นหายา | Autocomplete จากรายการยาใน HOSxP (drugitems) |
| 🔐 Admin | เภสัชกร login เพิ่ม/แก้ไข/ลบ รายการยาหมดอายุ |
| 📊 Dashboard | สรุปสถานะ: หมดอายุแล้ว / ≤7 วัน / ≤30 วัน |
| 🔔 LINE แจ้งเตือน | ส่ง Flex Message ผ่าน MOPH Notify อัตโนมัติ |
| 🎨 Color Coding | 🔴 หมดแล้ว · 🟠 ≤7 วัน · 🟡 ≤30 วัน · 🟢 ปลอดภัย |

---

## 📁 โครงสร้างไฟล์

```
Drug_exp/
├── index.php                   ปฏิทินหน้าหลัก (public)
├── config.php                  ⚙️ ตั้งค่าระบบ (ห้าม push ขึ้น GitHub)
├── config.example.php          Template config
├── db.php                      Database helper
├── setup.php                   สร้างตาราง (รันครั้งเดียว แล้วลบทิ้ง)
├── admin/
│   ├── login.php               หน้า login
│   ├── logout.php
│   ├── auth.php                ตรวจสอบ session
│   ├── index.php               Dashboard รายการยาทั้งหมด
│   ├── drug_form.php           ฟอร์มเพิ่ม/แก้ไขยา
│   └── drug_delete.php         ลบรายการ (AJAX)
├── api/
│   ├── search_drug.php         ค้นหายาจาก HOSxP (JSON)
│   └── calendar_data.php       ข้อมูลปฏิทิน (JSON)
├── notify/
│   └── drug_expiry_notify.php  สคริปต์แจ้งเตือน MOPH Notify
└── logs/                       Log files (auto-created)
```

---

## 🚀 วิธีติดตั้ง

### 1. วางไฟล์
```
C:\xampp\htdocs\Drug_exp\
```

### 2. ตั้งค่า config.php
```bash
# คัดลอก config ตัวอย่าง
copy config.example.php config.php
```
แก้ไข `config.php`:
- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` → ข้อมูล HOSxP
- `CLIENT_KEY`, `SECRET_KEY` → ได้จาก MOPH Notify portal
- `HOSPITAL_NAME`, `HOSPITAL_LOGO_URL` → ข้อมูลโรงพยาบาล

### 3. สร้างตาราง
เปิดเบราว์เซอร์:
```
http://localhost/Drug_exp/setup.php
```
> ⚠️ **ลบ setup.php หลังจาก setup เสร็จ!**

### 4. Login ครั้งแรก
```
URL:      http://localhost/Drug_exp/admin/login.php
Username: admin
Password: pharma1234
```
> แนะนำให้เปลี่ยนรหัสผ่านหลัง login ครั้งแรก

---

## ⏰ ตั้งเวลาแจ้งเตือน (Windows Task Scheduler)

```
Program: C:\xampp\php\php.exe
Arguments: C:\xampp\htdocs\Drug_exp\notify\drug_expiry_notify.php
Schedule: ทุกวัน เวลา 08:00
```

### ทดสอบ
```bash
# ทดสอบทั่วไป
php notify/drug_expiry_notify.php --test

# ทดสอบ threshold ที่ต้องการ
php notify/drug_expiry_notify.php --test --days=7
php notify/drug_expiry_notify.php --test --days=30
```

---

## 📊 ตาราง Database ที่สร้างใหม่

### `drug_expiry`
| Column | Type | คำอธิบาย |
|--------|------|---------|
| id | INT | Primary key |
| drug_code | VARCHAR(20) | รหัสยาจาก HOSxP |
| drug_name | VARCHAR(255) | ชื่อยา |
| dosage_form | VARCHAR(100) | รูปแบบยา |
| lot_number | VARCHAR(50) | หมายเลข Lot |
| expiry_date | DATE | **วันหมดอายุ** |
| quantity | DECIMAL | จำนวนคงเหลือ |
| unit | VARCHAR(50) | หน่วย (เม็ด/ขวด) |
| storage_location | VARCHAR(150) | ตำแหน่งจัดเก็บ |
| notified_days | TEXT | JSON วันที่แจ้งแล้ว |
| created_by | VARCHAR(100) | ผู้เพิ่มข้อมูล |

### `drug_expiry_users`
| Column | Type | คำอธิบาย |
|--------|------|---------|
| username | VARCHAR(50) | ชื่อผู้ใช้ (unique) |
| password | VARCHAR(255) | bcrypt hash |
| role | ENUM | admin / pharmacist |

---

## 🔍 HOSxP Query ที่ใช้

```sql
SELECT
    icode AS drug_code,
    dosageform,
    CONCAT(name, ' ', strength) AS drug_name
FROM drugitems
WHERE istatus = 'Y'
  AND name LIKE '%keyword%'
LIMIT 20
```

---

## 📱 LINE Flex Message Preview

ระบบส่งแจ้งเตือน 4 รอบตาม threshold:
- `🔴` หมดอายุวันนี้
- `🟠` อีก 1 วัน
- `🟡` อีก 7 วัน
- `🔵` อีก 30 วัน

แต่ละรอบจะแจ้งเตือน **ครั้งเดียวต่อรายการ** (บันทึก `notified_days` ไว้)

---

## 🔐 Security Notes

1. **`config.php` อยู่ใน `.gitignore`** ไม่ push รหัสผ่านขึ้น GitHub
2. ลบ `setup.php` หลัง setup
3. ระบบใช้ `password_hash()` / `password_verify()` (bcrypt)
4. Session-based authentication

---

## 📝 Changelog

- v1.0.0 – ระบบปฏิทินยาหมดอายุ + MOPH Notify + Admin Panel

---

*พัฒนาโดย ฝ่ายเภสัชกรรม รพ.แม่แตง*
