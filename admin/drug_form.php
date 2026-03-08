<?php
/**
 * admin/drug_form.php – ฟอร์มเพิ่ม / แก้ไขรายการยาหมดอายุ
 */
require_once __DIR__ . '/auth.php';

$db   = DB::local();
$id   = (int)($_GET['id'] ?? 0);
$mode = $id ? 'edit' : 'add';
$row  = [];

if ($mode === 'edit') {
    $r = $db->query("SELECT * FROM drug_expiry WHERE id=$id AND is_active=1");
    $row = $r ? ($r->fetch_assoc() ?? []) : [];
    if (!$row) { header('Location: index.php'); exit; }
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $drug_code  = trim($_POST['drug_code'] ?? '');
    $drug_name  = trim($_POST['drug_name'] ?? '');
    $dosage_form= trim($_POST['dosage_form'] ?? '');
    $lot_number = trim($_POST['lot_number'] ?? '');
    $expiry_date= trim($_POST['expiry_date'] ?? '');
    $quantity   = (float)($_POST['quantity'] ?? 0);
    $unit       = trim($_POST['unit'] ?? '');
    $storage    = trim($_POST['storage_location'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if (!$drug_name) $errors[] = 'กรุณาระบุชื่อยา';
    if (!$expiry_date || !strtotime($expiry_date)) $errors[] = 'กรุณาระบุวันหมดอายุให้ถูกต้อง';

    if (empty($errors)) {
        $dc = $db->real_escape_string($drug_code);
        $dn = $db->real_escape_string($drug_name);
        $df = $db->real_escape_string($dosage_form);
        $ln = $db->real_escape_string($lot_number);
        $ed = $db->real_escape_string($expiry_date);
        $un = $db->real_escape_string($unit);
        $sl = $db->real_escape_string($storage);
        $nt = $db->real_escape_string($notes);
        $cb = $db->real_escape_string($_SESSION['dname'] ?? 'admin');

        if ($mode === 'add') {
            $db->query("INSERT INTO drug_expiry
                (drug_code,drug_name,dosage_form,lot_number,expiry_date,quantity,unit,storage_location,notes,created_by)
                VALUES ('$dc','$dn','$df','$ln','$ed',$quantity,'$un','$sl','$nt','$cb')");
            $success = 'เพิ่มรายการสำเร็จ';
            $row = [];
        } else {
            $db->query("UPDATE drug_expiry SET
                drug_code='$dc', drug_name='$dn', dosage_form='$df',
                lot_number='$ln', expiry_date='$ed', quantity=$quantity,
                unit='$un', storage_location='$sl', notes='$nt',
                notified_days=NULL
                WHERE id=$id");
            $success = 'แก้ไขรายการสำเร็จ';
            $row = compact('drug_code','drug_name','dosage_form','lot_number','expiry_date','quantity','unit','storage_location','notes');
        }
    }
}

$v = fn(string $k) => htmlspecialchars($row[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $mode==='add'?'เพิ่มรายการยา':'แก้ไขรายการยา' ?> – <?= APP_TITLE ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--teal:#0d9488;--bg:#f0fdf9;--card:#fff;--border:#e2e8f0;--text:#1e293b;--sub:#64748b}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--text)}
.topbar{background:var(--teal);color:#fff;padding:.75rem 1.5rem;display:flex;align-items:center;gap:.8rem}
.topbar h1{flex:1;font-size:1.05rem;font-weight:700}
.topbar a{color:#fff;text-decoration:none;font-size:.85rem;background:rgba(255,255,255,.2);padding:.3rem .75rem;border-radius:16px}
.wrap{max-width:680px;margin:1.5rem auto;padding:0 1rem}
.card{background:var(--card);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:1.8rem}
.card h2{font-size:1.2rem;font-weight:800;color:var(--teal);margin-bottom:1.4rem;display:flex;align-items:center;gap:.5rem}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.field{margin-bottom:1rem}
.field label{display:block;font-size:.85rem;font-weight:600;color:#374151;margin-bottom:.4rem}
.field input,.field textarea,.field select{width:100%;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.95rem;font-family:'Sarabun',sans-serif;outline:none;transition:.2s;background:#fff}
.field input:focus,.field textarea:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(13,148,136,.12)}
.field textarea{resize:vertical;min-height:70px}
.field .hint{font-size:.75rem;color:var(--sub);margin-top:.25rem}
.error{background:#fee2e2;border-left:4px solid #dc2626;color:#7f1d1d;padding:.7rem 1rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem}
.success{background:#d1fae5;border-left:4px solid #16a34a;color:#065f46;padding:.7rem 1rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem}
.actions{display:flex;gap:.8rem;margin-top:1.2rem;flex-wrap:wrap}
.btn-save{background:var(--teal);color:#fff;border:none;padding:.75rem 1.5rem;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:'Sarabun',sans-serif}
.btn-save:hover{background:#0f766e}
.btn-cancel{background:#f1f5f9;color:var(--sub);text-decoration:none;padding:.75rem 1.5rem;border-radius:10px;font-size:.95rem;font-weight:600}
/* Autocomplete */
.ac-wrap{position:relative}
.ac-list{position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid var(--teal);border-radius:0 0 8px 8px;max-height:220px;overflow-y:auto;z-index:10;display:none;box-shadow:0 4px 16px rgba(0,0,0,.12)}
.ac-item{padding:.5rem .9rem;cursor:pointer;font-size:.88rem;border-bottom:1px solid #f1f5f9}
.ac-item:hover{background:#f0fdf4}
.ac-item .df{font-size:.75rem;color:var(--sub)}
@media(max-width:520px){.row2{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="topbar">
  <h1>💊 <?= $mode==='add'?'เพิ่มรายการยาหมดอายุ':'แก้ไขรายการยา' ?></h1>
  <a href="index.php">← กลับ</a>
</div>

<div class="wrap">
  <div class="card">
    <h2><?= $mode==='add'?'➕ เพิ่มรายการใหม่':'✏️ แก้ไขรายการ' ?></h2>

    <?php foreach ($errors as $e): ?>
      <div class="error">⚠️ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php if ($success): ?>
      <div class="success">✅ <?= $success ?> <a href="index.php" style="color:#065f46;font-weight:700">ดูรายการทั้งหมด →</a></div>
    <?php endif; ?>

    <form method="POST" id="drugForm">
      <!-- ─── ชื่อยา (autocomplete จาก HOSxP) ─── -->
      <div class="field">
        <label>ชื่อยา <span style="color:#dc2626">*</span></label>
        <div class="ac-wrap">
          <input type="text" id="drugSearch" name="drug_name" value="<?= $v('drug_name') ?>"
                 placeholder="พิมพ์ชื่อยาเพื่อค้นหา..." autocomplete="off" required>
          <input type="hidden" id="drugCode" name="drug_code" value="<?= $v('drug_code') ?>">
          <div class="ac-list" id="acList"></div>
        </div>
        <div class="hint">ค้นหาจากรายการยาใน HOSxP หรือพิมพ์เองได้</div>
      </div>

      <div class="row2">
        <div class="field">
          <label>รูปแบบยา</label>
          <input type="text" name="dosage_form" id="dosageForm" value="<?= $v('dosage_form') ?>" placeholder="เช่น Tablet, Capsule">
        </div>
        <div class="field">
          <label>หมายเลข Lot</label>
          <input type="text" name="lot_number" value="<?= $v('lot_number') ?>" placeholder="เช่น LOT2024001">
        </div>
      </div>

      <div class="field">
        <label>วันหมดอายุ <span style="color:#dc2626">*</span></label>
        <input type="date" name="expiry_date" value="<?= $v('expiry_date') ?>" required min="<?= date('Y-m-d') ?>">
        <div class="hint">วันที่หมดอายุ (ค.ศ.)</div>
      </div>

      <div class="row2">
        <div class="field">
          <label>จำนวน</label>
          <input type="number" name="quantity" value="<?= htmlspecialchars($row['quantity'] ?? '') ?>" min="0" step="0.01" placeholder="0">
        </div>
        <div class="field">
          <label>หน่วย</label>
          <input type="text" name="unit" value="<?= $v('unit') ?>" placeholder="เช่น เม็ด, ขวด, กล่อง">
        </div>
      </div>

      <div class="field">
        <label>ตำแหน่งจัดเก็บ</label>
        <input type="text" name="storage_location" value="<?= $v('storage_location') ?>" placeholder="เช่น ชั้น A-3, ตู้เย็น 1">
      </div>

      <div class="field">
        <label>หมายเหตุ</label>
        <textarea name="notes" placeholder="ข้อมูลเพิ่มเติม..."><?= $v('notes') ?></textarea>
      </div>

      <div class="actions">
        <button type="submit" class="btn-save">💾 บันทึก</button>
        <a class="btn-cancel" href="index.php">ยกเลิก</a>
      </div>
    </form>
  </div>
</div>

<script>
let acTimer;
const input    = document.getElementById('drugSearch');
const codeInput= document.getElementById('drugCode');
const dfInput  = document.getElementById('dosageForm');
const acList   = document.getElementById('acList');

input.addEventListener('input', () => {
  clearTimeout(acTimer);
  const q = input.value.trim();
  if (q.length < 2) { acList.style.display='none'; return; }
  acTimer = setTimeout(() => searchDrug(q), 300);
});

async function searchDrug(q) {
  try {
    const r = await fetch('../api/search_drug.php?q=' + encodeURIComponent(q));
    const data = await r.json();
    if (!data.length) { acList.style.display='none'; return; }
    acList.innerHTML = data.map(d =>
      `<div class="ac-item" onclick="selectDrug(${JSON.stringify(d)})">
        <div>${d.drug_name}</div>
        <div class="df">${d.dosage_form || ''}</div>
      </div>`
    ).join('');
    acList.style.display='block';
  } catch(e) { console.error(e); }
}

function selectDrug(d) {
  input.value    = d.drug_name;
  codeInput.value= d.drug_code || '';
  dfInput.value  = d.dosage_form || '';
  acList.style.display='none';
}

document.addEventListener('click', e => {
  if (!e.target.closest('.ac-wrap')) acList.style.display='none';
});
</script>
</body>
</html>
