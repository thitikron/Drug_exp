<?php
/**
 * setup.php – รันครั้งเดียวเพื่อสร้างตาราง
 * เปิด: http://localhost/Drug_exp/setup.php
 * ลบไฟล์นี้หลังจาก setup เสร็จ!
 */
require_once __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
if ($conn->connect_error) die('DB Error: ' . $conn->connect_error);
$conn->set_charset('utf8mb4');
$conn->query("SET NAMES utf8mb4");

$msgs = [];
$errors = [];

// ─── 1) drug_expiry ──────────────────────────────────────
$sql = "CREATE TABLE IF NOT EXISTS `drug_expiry` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `drug_code`        VARCHAR(20)  NOT NULL DEFAULT '',
  `drug_name`        VARCHAR(255) NOT NULL,
  `dosage_form`      VARCHAR(100) DEFAULT '',
  `lot_number`       VARCHAR(50)  DEFAULT '',
  `expiry_date`      DATE         NOT NULL,
  `quantity`         DECIMAL(10,2) DEFAULT 0,
  `unit`             VARCHAR(50)  DEFAULT '',
  `storage_location` VARCHAR(150) DEFAULT '',
  `notes`            TEXT,
  `notified_days`    TEXT         COMMENT 'JSON array เช่น [30,7]',
  `created_by`       VARCHAR(100) DEFAULT '',
  `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_expiry` (`expiry_date`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if ($conn->query($sql)) $msgs[] = '✅ สร้างตาราง drug_expiry สำเร็จ';
else $errors[] = '❌ drug_expiry: ' . $conn->error;

// ─── 2) drug_expiry_users ────────────────────────────────
$sql = "CREATE TABLE IF NOT EXISTS `drug_expiry_users` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50)  NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(100) DEFAULT '',
  `role`         ENUM('admin','pharmacist') DEFAULT 'pharmacist',
  `last_login`   DATETIME NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `is_active`    TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if ($conn->query($sql)) $msgs[] = '✅ สร้างตาราง drug_expiry_users สำเร็จ';
else $errors[] = '❌ drug_expiry_users: ' . $conn->error;

// ─── 3) Default admin ────────────────────────────────────
$hash = password_hash('pharma1234', PASSWORD_BCRYPT);
$sql  = "INSERT IGNORE INTO `drug_expiry_users`
         (username, password, display_name, role)
         VALUES ('admin', '$hash', 'ผู้ดูแลระบบ', 'admin')";
if ($conn->query($sql) && $conn->affected_rows > 0)
    $msgs[] = '✅ สร้าง admin สำเร็จ (username: admin / password: pharma1234)';
else
    $msgs[] = 'ℹ️ admin มีอยู่แล้ว ข้าม';

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Setup – <?= APP_TITLE ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body{font-family:'Sarabun',sans-serif;background:#f0fdf4;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}
  .card{background:#fff;border-radius:16px;padding:2rem 2.5rem;max-width:520px;width:100%;box-shadow:0 4px 20px rgba(0,0,0,.1)}
  h1{color:#0d9488;font-size:1.6rem;margin:0 0 1rem}
  .msg{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:.6rem 1rem;border-radius:8px;margin:.4rem 0;font-size:.95rem}
  .err{background:#fee2e2;border:1px solid #fca5a5;color:#7f1d1d;padding:.6rem 1rem;border-radius:8px;margin:.4rem 0;font-size:.95rem}
  .btn{display:inline-block;background:#0d9488;color:#fff;padding:.7rem 1.5rem;border-radius:8px;text-decoration:none;font-weight:600;margin-top:1.2rem}
  .warn{background:#fef3c7;border:1px solid #fcd34d;color:#78350f;padding:.8rem 1rem;border-radius:8px;margin-top:1rem;font-size:.9rem}
</style>
</head>
<body>
<div class="card">
  <h1>⚙️ Setup – <?= APP_TITLE ?></h1>
  <?php foreach ($msgs as $m): ?>
    <div class="msg"><?= $m ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $e): ?>
    <div class="err"><?= $e ?></div>
  <?php endforeach; ?>
  <div class="warn">⚠️ <strong>ลบไฟล์นี้ (setup.php) หลังจาก setup เสร็จแล้ว!</strong></div>
  <a class="btn" href="admin/login.php">เข้าสู่ระบบ →</a>
</div>
</body>
</html>
