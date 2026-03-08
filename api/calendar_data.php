<?php
/**
 * api/calendar_data.php – ส่งข้อมูลยาหมดอายุ (JSON) สำหรับ Calendar
 * GET ?year=2568&month=6  (พ.ศ. หรือ ค.ศ. ก็ได้)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

// รองรับ พ.ศ.
if ($year > 2400) $year -= 543;

$start = sprintf('%04d-%02d-01', $year, $month);
$end   = sprintf('%04d-%02d-%02d', $year, $month,
         cal_days_in_month(CAL_GREGORIAN, $month, $year));

$db  = DB::local();
$res = $db->query("
    SELECT id, drug_code, drug_name, dosage_form, lot_number,
           expiry_date, quantity, unit, storage_location, notes
    FROM drug_expiry
    WHERE is_active = 1
      AND expiry_date BETWEEN '$start' AND '$end'
    ORDER BY expiry_date ASC
");

$result = [];
while ($r = $res->fetch_assoc()) {
    $result[$r['expiry_date']][] = $r;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
