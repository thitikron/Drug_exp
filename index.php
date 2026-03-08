<?php
/**
 * index.php – ปฏิทินแสดงวันหมดอายุยา (Thai Buddhist Calendar)
 */
require_once __DIR__ . '/config.php';

$thaiMonths = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$thaiDays   = ['อา','จ','อ','พ','พฤ','ศ','ส'];

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$prevM = $month - 1 ?: 12;
$prevY = $month - 1 ? $year : $year - 1;
$nextM = $month + 1 > 12 ? 1 : $month + 1;
$nextY = $month + 1 > 12 ? $year + 1 : $year;

$thaiYear = $year + 543;

// ─── ดึงข้อมูลยาเดือนนี้ ──────────────────────────────────
require_once __DIR__ . '/db.php';
$start = sprintf('%04d-%02d-01', $year, $month);
$end   = sprintf('%04d-%02d-%02d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));

$db = DB::local();
$res = $db->query("
    SELECT id, drug_name, dosage_form, lot_number, expiry_date, quantity, unit, storage_location
    FROM drug_expiry
    WHERE is_active = 1
      AND expiry_date BETWEEN '$start' AND '$end'
    ORDER BY expiry_date ASC
");

$drugsByDate = [];
$today = date('Y-m-d');
while ($r = $res->fetch_assoc()) {
    $d = $r['expiry_date'];
    $drugsByDate[$d][] = $r;
}

// ─── สถิติ sidebar ────────────────────────────────────────
$statRes = $db->query("
    SELECT
      SUM(CASE WHEN expiry_date < '$today'                            THEN 1 ELSE 0 END) AS expired,
      SUM(CASE WHEN expiry_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)   THEN 1 ELSE 0 END) AS week,
      SUM(CASE WHEN expiry_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 30 DAY)  THEN 1 ELSE 0 END) AS month
    FROM drug_expiry WHERE is_active = 1
");
$stat = $statRes->fetch_assoc();

// ─── upcoming list ────────────────────────────────────────
$upRes = $db->query("
    SELECT drug_name, dosage_form, expiry_date, quantity, unit, storage_location
    FROM drug_expiry
    WHERE is_active = 1
      AND expiry_date >= '$today'
    ORDER BY expiry_date ASC
    LIMIT 10
");
$upcoming = [];
while ($r = $upRes->fetch_assoc()) $upcoming[] = $r;

function badgeColor(string $expiry, string $today): string {
    $diff = (strtotime($expiry) - strtotime($today)) / 86400;
    if ($diff < 0)  return 'badge-expired';
    if ($diff <= 7) return 'badge-danger';
    if ($diff <= 30) return 'badge-warn';
    return 'badge-ok';
}
function diffLabel(string $expiry, string $today): string {
    $diff = (int)round((strtotime($expiry) - strtotime($today)) / 86400);
    if ($diff < 0)  return 'หมดอายุแล้ว ' . abs($diff) . ' วัน';
    if ($diff === 0) return 'หมดอายุวันนี้!';
    return 'อีก ' . $diff . ' วัน';
}
function thaiDate(string $d): string {
    $m = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
          'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $ts = strtotime($d);
    return date('j',$ts).' '.$m[(int)date('n',$ts)].' '.((int)date('Y',$ts)+543);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_TITLE ?> – <?= HOSPITAL_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,300;0,400;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
<style>
:root{
  --teal:#0d9488;--teal-dk:#0f766e;--teal-lt:#ccfbf1;
  --expired:#dc2626;--danger:#ea580c;--warn:#d97706;--ok:#16a34a;
  --bg:#f0fdf9;--card:#fff;--border:#e2e8f0;
  --text:#1e293b;--sub:#64748b;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* ─── Topbar ─── */
.topbar{background:var(--teal);color:#fff;padding:.75rem 1.5rem;display:flex;align-items:center;gap:1rem;box-shadow:0 2px 8px rgba(13,148,136,.3)}
.topbar img{height:36px;border-radius:4px;object-fit:contain}
.topbar h1{font-size:1.1rem;font-weight:700;flex:1}
.topbar a{color:#fff;text-decoration:none;font-size:.9rem;background:rgba(255,255,255,.2);padding:.3rem .8rem;border-radius:20px;transition:.2s}
.topbar a:hover{background:rgba(255,255,255,.35)}

/* ─── Layout ─── */
.layout{display:flex;gap:1.2rem;padding:1.2rem;max-width:1280px;margin:0 auto}
.cal-wrap{flex:1;min-width:0}
.sidebar{width:300px;flex-shrink:0}

/* ─── Calendar Card ─── */
.cal-card{background:var(--card);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.08);overflow:hidden}
.cal-header{background:var(--teal);color:#fff;padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between}
.cal-header .month-title{font-size:1.4rem;font-weight:800}
.cal-header .year-sub{font-size:.85rem;opacity:.85;margin-top:.1rem}
.nav-btn{background:rgba(255,255,255,.2);border:none;color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:.2s}
.nav-btn:hover{background:rgba(255,255,255,.4)}

.cal-grid{display:grid;grid-template-columns:repeat(7,1fr)}
.day-header{text-align:center;padding:.6rem .2rem;font-size:.8rem;font-weight:700;color:var(--sub);background:#f8fafc}
.day-header:first-child{color:#dc2626}
.day-header:last-child{color:#2563eb}

.day-cell{min-height:90px;padding:.4rem;border-right:1px solid var(--border);border-bottom:1px solid var(--border);cursor:pointer;transition:.15s;position:relative}
.day-cell:hover{background:#f0fdf4}
.day-cell.other-month{opacity:.35;pointer-events:none}
.day-cell.today .day-num{background:var(--teal);color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center}
.day-cell:nth-child(7n+1) .day-num{color:#dc2626}
.day-cell:nth-child(7n) .day-num{color:#2563eb}
.day-num{font-size:.95rem;font-weight:600;width:26px;height:26px;display:flex;align-items:center;justify-content:center;margin-bottom:.25rem}
.thai-date{font-size:.65rem;color:var(--sub);margin-bottom:.2rem}

/* ─── Drug Badges ─── */
.drug-badge{font-size:.65rem;padding:.15rem .4rem;border-radius:4px;margin:.1rem 0;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;max-width:100%}
.badge-expired{background:#fee2e2;color:#7f1d1d}
.badge-danger {background:#ffedd5;color:#7c2d12}
.badge-warn   {background:#fef3c7;color:#78350f}
.badge-ok     {background:#dcfce7;color:#14532d}
.more-badge{font-size:.62rem;color:var(--sub);padding:.1rem .3rem}

/* ─── Legend ─── */
.legend{display:flex;gap:.8rem;padding:.8rem 1.2rem;flex-wrap:wrap;background:#f8fafc;border-top:1px solid var(--border)}
.legend-item{display:flex;align-items:center;gap:.35rem;font-size:.8rem;color:var(--sub)}
.legend-dot{width:10px;height:10px;border-radius:2px}

/* ─── Sidebar ─── */
.s-card{background:var(--card);border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);padding:1.2rem;margin-bottom:1rem}
.s-card h3{font-size:.95rem;font-weight:700;color:var(--teal);margin-bottom:.9rem;display:flex;align-items:center;gap:.4rem}
.stat-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;margin-bottom:.5rem}
.stat-box{text-align:center;padding:.7rem .3rem;border-radius:10px}
.stat-box.red  {background:#fee2e2}
.stat-box.orange{background:#ffedd5}
.stat-box.yellow{background:#fef3c7}
.stat-box .num{font-size:1.6rem;font-weight:800;line-height:1}
.stat-box .lbl{font-size:.7rem;color:var(--sub);margin-top:.2rem}
.stat-box.red .num{color:#dc2626}
.stat-box.orange .num{color:#ea580c}
.stat-box.yellow .num{color:#d97706}

.up-item{padding:.6rem 0;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:.2rem}
.up-item:last-child{border-bottom:none}
.up-drug{font-size:.88rem;font-weight:600;color:var(--text)}
.up-meta{font-size:.75rem;color:var(--sub);display:flex;gap:.5rem;flex-wrap:wrap}
.up-tag{padding:.1rem .45rem;border-radius:12px;font-size:.7rem;font-weight:700}

/* ─── Modal ─── */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;padding:1rem}
.modal-bg.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:1.5rem;max-width:500px;width:100%;max-height:85vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.2)}
.modal h2{font-size:1.1rem;font-weight:700;color:var(--teal);margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center}
.modal-close{background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--sub);line-height:1}
.m-drug{padding:.75rem;background:#f8fafc;border-radius:10px;margin-bottom:.6rem;border-left:4px solid var(--teal)}
.m-drug-name{font-weight:700;font-size:.95rem}
.m-drug-meta{font-size:.8rem;color:var(--sub);margin-top:.3rem;display:flex;gap:.7rem;flex-wrap:wrap}
.m-badge{display:inline-block;padding:.2rem .6rem;border-radius:6px;font-size:.78rem;font-weight:700;margin-top:.35rem}

/* ─── Mobile ─── */
@media(max-width:768px){
  .layout{flex-direction:column;padding:.8rem}
  .sidebar{width:100%}
  .day-cell{min-height:70px;padding:.3rem}
  .drug-badge{font-size:.6rem}
  .day-num{font-size:.85rem}
  .thai-date{display:none}
  .topbar h1{font-size:.95rem}
}
@media(max-width:480px){
  .day-cell{min-height:55px}
  .drug-badge{display:none}
  .has-drug::after{content:'●';font-size:.5rem;color:var(--expired);position:absolute;bottom:3px;right:3px}
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <img src="<?= HOSPITAL_LOGO_URL ?>" alt="logo" onerror="this.style.display='none'">
  <h1>💊 <?= APP_TITLE ?></h1>
  <a href="admin/login.php">⚙️ จัดการ</a>
</div>

<div class="layout">
  <!-- ─── Calendar ─── -->
  <div class="cal-wrap">
    <div class="cal-card">
      <div class="cal-header">
        <a class="nav-btn" href="?year=<?= $prevY ?>&month=<?= $prevM ?>">‹</a>
        <div style="text-align:center">
          <div class="month-title"><?= $thaiMonths[$month] ?></div>
          <div class="year-sub">พ.ศ. <?= $thaiYear ?> (ค.ศ. <?= $year ?>)</div>
        </div>
        <a class="nav-btn" href="?year=<?= $nextY ?>&month=<?= $nextM ?>">›</a>
      </div>

      <div class="cal-grid">
        <?php foreach ($thaiDays as $d): ?>
          <div class="day-header"><?= $d ?></div>
        <?php endforeach; ?>

        <?php
        $firstDay = (int)date('w', strtotime("$year-$month-01"));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $daysInPrev  = cal_days_in_month(CAL_GREGORIAN, $prevM, $prevY);

        // Prev month tail
        for ($i = $firstDay - 1; $i >= 0; $i--):
            $d = $daysInPrev - $i;
        ?>
          <div class="day-cell other-month">
            <div class="day-num"><?= $d ?></div>
          </div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $daysInMonth; $d++):
            $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isToday  = ($dateStr === $today);
            $drugs    = $drugsByDate[$dateStr] ?? [];
            $hasDrug  = !empty($drugs);
            $thaiD    = $d . ' ' . ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][$month];
        ?>
          <div class="day-cell <?= $isToday ? 'today' : '' ?> <?= $hasDrug ? 'has-drug' : '' ?>"
               <?php if ($hasDrug): ?>
               onclick="openModal('<?= $dateStr ?>','<?= $thaiD ?> <?= $thaiYear ?>')"
               <?php endif; ?>>
            <div class="day-num"><?= $d ?></div>
            <div class="thai-date"><?= $thaiD ?></div>
            <?php
            $show = 0;
            foreach ($drugs as $drug):
                if ($show >= 2): ?>
                  <div class="more-badge">+<?= count($drugs) - 2 ?> รายการ</div>
                <?php break; endif;
                $bc = badgeColor($drug['expiry_date'], $today);
            ?>
              <div class="drug-badge <?= $bc ?>" title="<?= htmlspecialchars($drug['drug_name']) ?>">
                <?= mb_substr(htmlspecialchars($drug['drug_name']), 0, 18) ?>…
              </div>
            <?php $show++; endforeach; ?>
          </div>
        <?php endfor; ?>

        <?php
        $total = $firstDay + $daysInMonth;
        $remaining = $total % 7 ? 7 - ($total % 7) : 0;
        for ($d = 1; $d <= $remaining; $d++): ?>
          <div class="day-cell other-month">
            <div class="day-num"><?= $d ?></div>
          </div>
        <?php endfor; ?>
      </div>

      <div class="legend">
        <div class="legend-item"><div class="legend-dot" style="background:#dc2626"></div>หมดอายุแล้ว</div>
        <div class="legend-item"><div class="legend-dot" style="background:#ea580c"></div>≤ 7 วัน</div>
        <div class="legend-item"><div class="legend-dot" style="background:#d97706"></div>≤ 30 วัน</div>
        <div class="legend-item"><div class="legend-dot" style="background:#16a34a"></div>ปลอดภัย</div>
      </div>
    </div>
  </div>

  <!-- ─── Sidebar ─── -->
  <div class="sidebar">
    <div class="s-card">
      <h3>📊 สรุปสถานะ</h3>
      <div class="stat-grid">
        <div class="stat-box red">
          <div class="num"><?= $stat['expired'] ?? 0 ?></div>
          <div class="lbl">หมดอายุแล้ว</div>
        </div>
        <div class="stat-box orange">
          <div class="num"><?= $stat['week'] ?? 0 ?></div>
          <div class="lbl">≤ 7 วัน</div>
        </div>
        <div class="stat-box yellow">
          <div class="num"><?= $stat['month'] ?? 0 ?></div>
          <div class="lbl">≤ 30 วัน</div>
        </div>
      </div>
    </div>

    <div class="s-card">
      <h3>⏰ กำลังจะหมดอายุ</h3>
      <?php if (empty($upcoming)): ?>
        <p style="color:var(--sub);font-size:.85rem">✅ ไม่มีรายการยาในขณะนี้</p>
      <?php endif; ?>
      <?php foreach ($upcoming as $d): 
        $bc = badgeColor($d['expiry_date'], $today);
        $colors = ['badge-expired'=>'#dc2626','badge-danger'=>'#ea580c','badge-warn'=>'#d97706','badge-ok'=>'#16a34a'];
        $col = $colors[$bc] ?? '#64748b';
      ?>
        <div class="up-item">
          <div class="up-drug"><?= htmlspecialchars($d['drug_name']) ?></div>
          <div class="up-meta">
            <span>📦 <?= $d['quantity'] ?> <?= htmlspecialchars($d['unit']) ?></span>
            <?php if ($d['storage_location']): ?>
              <span>📍 <?= htmlspecialchars($d['storage_location']) ?></span>
            <?php endif; ?>
          </div>
          <div>
            <span class="up-tag" style="background:<?= $col ?>22;color:<?= $col ?>">
              📅 <?= thaiDate($d['expiry_date']) ?> — <?= diffLabel($d['expiry_date'], $today) ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="s-card" style="background:var(--teal);color:#fff">
      <h3 style="color:#fff">📅 วันนี้</h3>
      <div style="font-size:1.05rem;font-weight:700"><?= thaiDate($today) ?></div>
      <div style="font-size:.85rem;opacity:.85;margin-top:.3rem"><?= date('l') ?></div>
    </div>
  </div>
</div>

<!-- ─── Modal ─── -->
<div class="modal-bg" id="modalBg" onclick="closeModal(event)">
  <div class="modal" id="modalBox">
    <h2>
      <span id="modalTitle">รายการยา</span>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </h2>
    <div id="modalContent"></div>
  </div>
</div>

<script>
const drugData = <?= json_encode($drugsByDate, JSON_UNESCAPED_UNICODE) ?>;
const today = '<?= $today ?>';

function diffLabel(exp) {
  const diff = Math.round((new Date(exp) - new Date(today)) / 86400000);
  if (diff < 0)  return `หมดอายุแล้ว ${Math.abs(diff)} วัน`;
  if (diff === 0) return 'หมดอายุวันนี้!';
  return `อีก ${diff} วัน`;
}
function badgeClass(exp) {
  const diff = Math.round((new Date(exp) - new Date(today)) / 86400000);
  if (diff < 0)  return 'badge-expired';
  if (diff <= 7) return 'badge-danger';
  if (diff <= 30) return 'badge-warn';
  return 'badge-ok';
}

function openModal(dateStr, dateLabel) {
  const drugs = drugData[dateStr] || [];
  document.getElementById('modalTitle').textContent = '💊 ' + dateLabel;
  let html = '';
  drugs.forEach(d => {
    const bc = badgeClass(d.expiry_date);
    const colors = {'badge-expired':'#dc2626','badge-danger':'#ea580c','badge-warn':'#d97706','badge-ok':'#16a34a'};
    const col = colors[bc] || '#64748b';
    html += `<div class="m-drug">
      <div class="m-drug-name">${d.drug_name}</div>
      <div class="m-drug-meta">
        ${d.dosage_form ? `<span>💊 ${d.dosage_form}</span>` : ''}
        ${d.lot_number  ? `<span>Lot: ${d.lot_number}</span>` : ''}
        ${d.quantity    ? `<span>📦 ${d.quantity} ${d.unit}</span>` : ''}
        ${d.storage_location ? `<span>📍 ${d.storage_location}</span>` : ''}
      </div>
      <span class="m-badge" style="background:${col}22;color:${col}">
        ⏰ ${diffLabel(d.expiry_date)}
      </span>
    </div>`;
  });
  document.getElementById('modalContent').innerHTML = html;
  document.getElementById('modalBg').classList.add('open');
}

function closeModal(e) {
  if (!e || e.target === document.getElementById('modalBg'))
    document.getElementById('modalBg').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>
