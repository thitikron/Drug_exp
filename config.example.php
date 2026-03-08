<?php
/**
 * config.example.php
 * คัดลอกเป็น config.php แล้วแก้ไขค่าให้ตรงกับระบบของคุณ
 * cp config.example.php config.php
 */
date_default_timezone_set('Asia/Bangkok');

// ─── HOSxP Database ───────────────────────────────────────
define('DB_HOST',  'YOUR_DB_HOST');    // เช่น 10.0.0.19
define('DB_PORT',  '3306');
define('DB_USER',  'YOUR_DB_USER');    // เช่น mth_hosxp
define('DB_PASS',  'YOUR_DB_PASS');
define('DB_NAME',  'YOUR_DB_NAME');    // เช่น maetaeng

// ─── MOPH Notify ─────────────────────────────────────────
define('CLIENT_KEY',    'YOUR_CLIENT_KEY');
define('SECRET_KEY',    'YOUR_SECRET_KEY');
define('MOPH_ENDPOINT', 'https://morpromt2f.moph.go.th/api/notify/send');

// ─── Hospital ────────────────────────────────────────────
define('HOSPITAL_NAME',     'ชื่อโรงพยาบาล');
define('HOSPITAL_LOGO_URL', 'https://your-hospital.go.th/logo.png');

// ─── App ─────────────────────────────────────────────────
define('APP_TITLE', 'ระบบติดตามวันหมดอายุยา');
define('APP_URL',   'http://localhost/Drug_exp');

// ─── Notification Thresholds ─────────────────────────────
define('NOTIFY_DAYS', [30, 7, 1]);

// ─── Log Directory ───────────────────────────────────────
define('LOG_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR);
