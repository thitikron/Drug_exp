<?php
/**
 * admin/login.php – หน้า Login สำหรับเภสัชกร
 */
require_once __DIR__ . '/../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../db.php';
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u && $p) {
        $ue  = DB::esc($u);
        $res = DB::local()->query("SELECT id, password, display_name, role FROM drug_expiry_users WHERE username='$ue' AND is_active=1");
        $row = $res ? $res->fetch_assoc() : null;
        if ($row && password_verify($p, $row['password'])) {
            $_SESSION['uid']   = $row['id'];
            $_SESSION['uname'] = $u;
            $_SESSION['dname'] = $row['display_name'];
            $_SESSION['role']  = $row['role'];
            DB::local()->query("UPDATE drug_expiry_users SET last_login=NOW() WHERE id=" . (int)$row['id']);
            header('Location: index.php');
            exit;
        }
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    } else {
        $error = 'กรุณากรอกข้อมูลให้ครบ';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เข้าสู่ระบบ – <?= APP_TITLE ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sarabun',sans-serif;min-height:100vh;background:linear-gradient(135deg,#0d9488 0%,#065f46 100%);display:flex;align-items:center;justify-content:center;padding:1rem}
.card{background:#fff;border-radius:20px;padding:2.5rem 2rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.logo-wrap{text-align:center;margin-bottom:1.5rem}
.logo-wrap img{height:64px;border-radius:10px;object-fit:contain}
.logo-wrap h1{font-size:1.3rem;font-weight:800;color:#0d9488;margin-top:.7rem}
.logo-wrap p{font-size:.85rem;color:#64748b;margin-top:.2rem}
.field{margin-bottom:1rem}
.field label{display:block;font-size:.85rem;font-weight:600;color:#374151;margin-bottom:.4rem}
.field input{width:100%;padding:.7rem 1rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:1rem;font-family:'Sarabun',sans-serif;outline:none;transition:.2s}
.field input:focus{border-color:#0d9488;box-shadow:0 0 0 3px rgba(13,148,136,.15)}
.btn{width:100%;background:#0d9488;color:#fff;border:none;padding:.85rem;border-radius:10px;font-size:1rem;font-weight:700;font-family:'Sarabun',sans-serif;cursor:pointer;transition:.2s;margin-top:.5rem}
.btn:hover{background:#0f766e}
.error{background:#fee2e2;border:1px solid #fca5a5;color:#7f1d1d;padding:.7rem 1rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem}
.back{display:block;text-align:center;margin-top:1rem;color:#64748b;font-size:.85rem;text-decoration:none}
.back:hover{color:#0d9488}
</style>
</head>
<body>
<div class="card">
  <div class="logo-wrap">
    <img src="<?= HOSPITAL_LOGO_URL ?>" alt="logo" onerror="this.style.display='none'">
    <h1>💊 จัดการยาหมดอายุ</h1>
    <p><?= HOSPITAL_NAME ?> – เฉพาะเจ้าหน้าที่เภสัช</p>
  </div>

  <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <div class="field">
      <label>ชื่อผู้ใช้</label>
      <input type="text" name="username" placeholder="username" autofocus required>
    </div>
    <div class="field">
      <label>รหัสผ่าน</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn">🔐 เข้าสู่ระบบ</button>
  </form>
  <a class="back" href="../index.php">← กลับหน้าปฏิทิน</a>
</div>
</body>
</html>
