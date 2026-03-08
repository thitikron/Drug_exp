<?php
/**
 * api/search_drug.php – ค้นหาชื่อยาจาก HOSxP
 * GET ?q=keyword
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

// Convert query to TIS-620 for search
$q_tis = iconv('utf-8', 'tis-620//IGNORE', $q);
$q_esc = DB::hosxp()->real_escape_string($q_tis);

// ─── Query ตาม spec ที่กำหนด ───────────────────────────────
$sql = "SELECT
    icode AS drug_code,
    dosageform,
    CONCAT(name, ' ', strength) AS drug_name
FROM drugitems
WHERE istatus = 'Y'
  AND (name LIKE '%{$q_esc}%' OR CONCAT(name,' ',strength) LIKE '%{$q_esc}%')
LIMIT 20";

$res  = DB::hosxp()->query($sql);
$data = [];
while ($r = $res->fetch_assoc()) {
    $data[] = [
        'drug_code'   => DB::conv((string)$r['drug_code']),
        'drug_name'   => DB::conv((string)$r['drug_name']),
        'dosage_form' => DB::conv((string)$r['dosageform']),
    ];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
