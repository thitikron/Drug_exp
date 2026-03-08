<?php
/**
 * admin/index.php – Dashboard เภสัชกร
 */
require_once __DIR__ . '/auth.php';

$today = date('Y-m-d');
$db = DB::local();

// ─── Filter ───────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = "is_active = 1";
if ($filter === 'expired') $where .= " AND expiry_date < '$today'";
elseif ($filter === 'week') $where .= " AND expiry_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)";
elseif ($filter === 'month') $where .= " AND expiry_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 30 DAY)";

if ($search) {
    $se = $db->real_escape_string($search);
    $where .= " AND (drug_name LIKE '%$se%' OR lot_number LIKE '%$se%' OR storage_location LIKE '%$se%')";
}

$rows = [];
$res = $db->query("SELECT * FROM drug_expiry WHERE $where ORDER BY expiry_date ASC");
while ($r = $res->fetch_assoc()) $rows[] = $r;

// ─── Stats ────────────────────────────────────────────────
$sRes = $db->query("
  SELECT
    COUNT(*) AS total,
    SUM(expiry_date < '$today') AS expired,
    SUM(expiry_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)) AS week,
    SUM(expiry_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 30 DAY)) AS month_
  FROM drug_expiry WHERE is_active=1
");
$s = $sRes->fetch_assoc();

function thaiDateShort(string $d): string {
    $m = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $ts = strtotime($d);
    return date('j',$ts).' '.$m[(int)date('n',$ts)].' '.((int)date('Y',$ts)+543);
}
function daysLeft(string $exp, string $today): int {
    return (int)round((strtotime($exp) - strtotime($today)) / 86400);
}
function statusBadge(int $diff): string {
    if ($diff < 0)  return '<span class="tag red">หมดอายุแล้ว</span>';
    if ($diff === 0) return '<span class="tag red">หมดอายุวันนี้!</span>';
    if ($diff <= 7)  return '<span class="tag orange">อีก '.$diff.' วัน</span>';
    if ($diff <= 30) return '<span class="tag yellow">อีก '.$diff.' วัน</span>';
    return '<span class="tag green">อีก '.$diff.' วัน</span>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>จัดการยา – <?= APP_TITLE ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--teal:#0d9488;--bg:#f0fdf9;--card:#fff;--border:#e2e8f0;--text:#1e293b;--sub:#64748b}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--text)}
.topbar{background:var(--teal);color:#fff;padding:.75rem 1.5rem;display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}
.topbar h1{flex:1;font-size:1.05rem;font-weight:700}
.topbar a{color:#fff;text-decoration:none;font-size:.85rem;background:rgba(255,255,255,.2);padding:.3rem .75rem;border-radius:16px}
.topbar a:hover{background:rgba(255,255,255,.35)}
.topbar .user{font-size:.85rem;opacity:.8}

.wrap{max-width:1200px;margin:0 auto;padding:1.2rem}
.stats{display:flex;gap:.8rem;margin-bottom:1.2rem;flex-wrap:wrap}
.stat{background:var(--card);border-radius:12px;padding:.9rem 1.2rem;flex:1;min-width:120px;box-shadow:0 1px 6px rgba(0,0,0,.07);display:flex;gap:.7rem;align-items:center;cursor:pointer;border:2px solid transparent;text-decoration:none;color:inherit;transition:.15s}
.stat:hover,.stat.active{border-color:var(--teal)}
.stat .ico{font-size:1.6rem}
.stat .num{font-size:1.5rem;font-weight:800;line-height:1}
.stat .lbl{font-size:.78rem;color:var(--sub)}
.stat.red .num{color:#dc2626}
.stat.orange .num{color:#ea580c}
.stat.yellow .num{color:#d97706}
.stat.teal .num{color:var(--teal)}

.toolbar{display:flex;gap:.7rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center}
.toolbar input{flex:1;min-width:200px;padding:.55rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-family:'Sarabun',sans-serif;font-size:.95rem;outline:none}
.toolbar input:focus{border-color:var(--teal)}
.btn-add{background:var(--teal);color:#fff;text-decoration:none;padding:.55rem 1.1rem;border-radius:8px;font-weight:700;font-size:.9rem;white-space:nowrap}
.btn-add:hover{background:#0f766e}

.table-wrap{background:var(--card);border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:700px}
th{background:#f8fafc;padding:.65rem 1rem;text-align:left;font-size:.82rem;font-weight:700;color:var(--sub);border-bottom:2px solid var(--border)}
td{padding:.65rem 1rem;font-size:.88rem;border-bottom:1px solid var(--border);vertical-align:middle}
tr:hover td{background:#f0fdf4}
.tag{display:inline-block;padding:.15rem .55rem;border-radius:12px;font-size:.75rem;font-weight:700}
.tag.red{background:#fee2e2;color:#7f1d1d}
.tag.orange{background:#ffedd5;color:#7c2d12}
.tag.yellow{background:#fef3c7;color:#78350f}
.tag.green{background:#dcfce7;color:#14532d}
.action-btns{display:flex;gap:.4rem}
.btn-edit{background:#eff6ff;color:#1d4ed8;border:none;padding:.3rem .7rem;border-radius:6px;cursor:pointer;font-size:.8rem;text-decoration:none;font-family:'Sarabun',sans-serif}
.btn-del{background:#fef2f2;color:#dc2626;border:none;padding:.3rem .7rem;border-radius:6px;cursor:pointer;font-size:.8rem;font-family:'Sarabun',sans-serif}
.empty{text-align:center;padding:3rem;color:var(--sub)}
.count-info{font-size:.85rem;color:var(--sub);margin-bottom:.5rem}
</style>
</head>
<body>
<div class="topbar">
  <h1>💊 จัดการรายการยาหมดอายุ – <?= HOSPITAL_NAME ?></h1>
  <span class="user">👤 <?= htmlspecialchars($_SESSION['dname']) ?></span>
  <a href="../index.php">🗓️ ปฏิทิน</a>
  <a href="logout.php">ออกจากระบบ</a>
</div>

<div class="wrap">
  <!-- Stats -->
  <div class="stats">
    <a class="stat teal <?= $filter==='all'?'active':'' ?>" href="?filter=all">
      <div class="ico">📦</div>
      <div><div class="num"><?= $s['total'] ?></div><div class="lbl">รายการทั้งหมด</div></div>
    </a>
    <a class="stat red <?= $filter==='expired'?'active':'' ?>" href="?filter=expired">
      <div class="ico">🔴</div>
      <div><div class="num"><?= $s['expired'] ?></div><div class="lbl">หมดอายุแล้ว</div></div>
    </a>
    <a class="stat orange <?= $filter==='week'?'active':'' ?>" href="?filter=week">
      <div class="ico">🟠</div>
      <div><div class="num"><?= $s['week'] ?></div><div class="lbl">≤ 7 วัน</div></div>
    </a>
    <a class="stat yellow <?= $filter==='month'?'active':'' ?>" href="?filter=month">
      <div class="ico">🟡</div>
      <div><div class="num"><?= $s['month_'] ?></div><div class="lbl">≤ 30 วัน</div></div>
    </a>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <form method="GET" style="display:flex;gap:.6rem;flex:1;flex-wrap:wrap">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 ค้นหาชื่อยา, Lot, ตำแหน่ง...">
      <button type="submit" style="background:var(--teal);color:#fff;border:none;padding:.55rem 1rem;border-radius:8px;cursor:pointer;font-family:'Sarabun',sans-serif;font-weight:600">ค้นหา</button>
    </form>
    <a class="btn-add" href="drug_form.php">+ เพิ่มรายการยา</a>
  </div>

  <div class="count-info">แสดง <?= count($rows) ?> รายการ</div>

  <!-- Table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>ชื่อยา</th>
          <th>รูปแบบยา</th>
          <th>Lot No.</th>
          <th>จำนวน</th>
          <th>ตำแหน่ง</th>
          <th>วันหมดอายุ</th>
          <th>สถานะ</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="empty">ไม่มีข้อมูล</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $i => $r):
          $diff = daysLeft($r['expiry_date'], $today);
        ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($r['drug_name']) ?></strong></td>
            <td><?= htmlspecialchars($r['dosage_form']) ?></td>
            <td><?= htmlspecialchars($r['lot_number']) ?: '–' ?></td>
            <td><?= $r['quantity'] ?> <?= htmlspecialchars($r['unit']) ?></td>
            <td><?= htmlspecialchars($r['storage_location']) ?: '–' ?></td>
            <td><?= thaiDateShort($r['expiry_date']) ?></td>
            <td><?= statusBadge($diff) ?></td>
            <td>
              <div class="action-btns">
                <a class="btn-edit" href="drug_form.php?id=<?= $r['id'] ?>">✏️ แก้ไข</a>
                <button class="btn-del" onclick="confirmDelete(<?= $r['id'] ?>, '<?= addslashes($r['drug_name']) ?>')">🗑️ ลบ</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function confirmDelete(id, name) {
  if (confirm('ยืนยันลบ: ' + name + ' ?')) {
    fetch('drug_delete.php?id=' + id, {method:'POST'})
      .then(r => r.json())
      .then(d => { if (d.ok) location.reload(); else alert('เกิดข้อผิดพลาด'); });
  }
}
</script>
</body>
</html>
