<?php
/**
 * admin/auth.php – ตรวจสอบ session ก่อนทุกหน้า admin
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if (empty($_SESSION['uid'])) {
    header('Location: login.php');
    exit;
}
