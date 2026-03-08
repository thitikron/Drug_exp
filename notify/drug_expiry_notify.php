<?php
/**
 * =========================================================
 *  Drug Expiry Notification – MOPH Notify (LINE Flex)
 *  C:\xampp\htdocs\Drug_exp\notify\drug_expiry_notify.php
 *  รพ.แม่แตง
 * =========================================================
 *
 *  รันด้วย Windows Task Scheduler หรือ Cron ทุกวัน 08:00
 *  php drug_expiry_notify.php
 *  php drug_expiry_notify.php --test
 *  php drug_expiry_notify.php --test --days=7
 *
 *  เกณฑ์แจ้งเตือน: 30, 7, 1 วัน ก่อนหมดอายุ + หมดอายุวันนี้
 * =========================================================
 */
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// ─── รับ args ─────────────────────────────────────────────
$is_test  = in_array('--test', $argv ?? []);
$force_days = null;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) $force_days = (int)$m[1];
}

if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0755, true);
define('LOG_FILE', LOG_DIR . 'drug_expiry_notify.log');

write_log("========== START | " . date('Y-m-d H:i:s') . ($is_test?' [TEST]':'') . " ==========");

$today  = date('Y-m-d');
$db     = DB::local();

// ─── กำหนด threshold ──────────────────────────────────────
$thresholds = $force_days !== null ? [$force_days] : NOTIFY_DAYS;

// เพิ่ม 0 (หมดอายุวันนี้)
if (!in_array(0, $thresholds)) array_unshift($thresholds, 0);

write_log("Thresholds: " . implode(', ', $thresholds) . " วัน");

foreach ($thresholds as $days) {
    $targetDate = $days === 0
        ? $today
        : date('Y-m-d', strtotime("+{$days} day", strtotime($today)));

    write_log("--- ตรวจวันที่: $targetDate (อีก $days วัน) ---");

    // ─── ดึงรายการยาที่หมดอายุในวันนั้น ──────────────────
    $td = $db->real_escape_string($targetDate);
    $res = $db->query("
        SELECT id, drug_name, dosage_form, lot_number,
               quantity, unit, storage_location, expiry_date, notified_days
        FROM drug_expiry
        WHERE is_active = 1 AND expiry_date = '$td'
        ORDER BY drug_name ASC
    ");

    $drugs = [];
    while ($r = $res->fetch_assoc()) {
        // ตรวจว่าเคยแจ้งเตือน $days วัน ไปแล้วหรือยัง
        $notified = json_decode($r['notified_days'] ?? '[]', true) ?: [];
        if (!$is_test && in_array($days, $notified)) {
            write_log("  ข้าม (เคยแจ้งแล้ว): " . $r['drug_name']);
            continue;
        }
        $drugs[] = $r;
    }

    if (empty($drugs)) {
        write_log("  ไม่มีรายการ");
        continue;
    }

    write_log("  พบ " . count($drugs) . " รายการ");

    // ─── สร้าง Flex Message ───────────────────────────────
    $messages = build_flex_message($drugs, $days, $targetDate);
    send_notify($messages);

    // ─── อัปเดต notified_days ────────────────────────────
    if (!$is_test) {
        foreach ($drugs as $d) {
            $notified = json_decode($d['notified_days'] ?? '[]', true) ?: [];
            if (!in_array($days, $notified)) {
                $notified[] = $days;
                $nj = $db->real_escape_string(json_encode($notified));
                $db->query("UPDATE drug_expiry SET notified_days='$nj' WHERE id=" . (int)$d['id']);
            }
        }
    }
}

write_log("========== END ==========\n");
exit(0);

// =========================================================
// สร้าง Flex Message
// =========================================================
function build_flex_message(array $drugs, int $days, string $targetDate): array
{
    // ─── ธีมสี ───────────────────────────────────────────
    if ($days === 0) {
        $emoji = '🔴'; $label = 'หมดอายุวันนี้!';
        $hd = '#7F1D1D'; $bg = '#FEE2E2';
    } elseif ($days <= 7) {
        $emoji = '🟠'; $label = "อีก {$days} วัน จะหมดอายุ";
        $hd = '#7C2D12'; $bg = '#FFEDD5';
    } elseif ($days <= 30) {
        $emoji = '🟡'; $label = "อีก {$days} วัน จะหมดอายุ";
        $hd = '#78350F'; $bg = '#FEF3C7';
    } else {
        $emoji = '🔵'; $label = "อีก {$days} วัน จะหมดอายุ";
        $hd = '#1E3A5F'; $bg = '#DBEAFE';
    }

    $count     = count($drugs);
    $thaiDate  = date_thai($targetDate);

    // ─── สร้าง items ─────────────────────────────────────
    $items = [];
    $items[] = [
        "type" => "box", "layout" => "vertical",
        "backgroundColor" => $bg, "cornerRadius" => "10px",
        "paddingAll" => "md", "margin" => "none",
        "contents" => [
            txt("{$emoji} {$label}", "md", $hd, 0, "center", true, true),
            txt("รวม {$count} รายการ  |  {$thaiDate}", "sm", $hd, 0, "center", false, false),
        ]
    ];
    $items[] = ["type" => "separator", "margin" => "md"];

    // ─── แถวหัวตาราง ─────────────────────────────────────
    $items[] = [
        "type" => "box", "layout" => "horizontal", "margin" => "sm",
        "contents" => [
            txt("ชื่อยา",       "xs", "#888888", 4),
            txt("จำนวน",       "xs", "#888888", 2, "center"),
            txt("ตำแหน่ง",     "xs", "#888888", 2, "end"),
        ]
    ];
    $items[] = ["type" => "separator", "margin" => "xs"];

    // ─── แถวยา ────────────────────────────────────────────
    foreach ($drugs as $i => $d) {
        $bg2 = $i % 2 === 0 ? "#FFFFFF" : "#F9FAFB";
        $qty = $d['quantity'] ? $d['quantity'] . ' ' . $d['unit'] : '–';
        $loc = $d['storage_location'] ?: '–';
        $items[] = [
            "type" => "box", "layout" => "horizontal",
            "backgroundColor" => $bg2,
            "paddingTop" => "xs", "paddingBottom" => "xs",
            "paddingStart" => "xs", "paddingEnd" => "xs",
            "contents" => [
                [
                    "type"    => "box", "layout" => "vertical", "flex" => 4,
                    "contents" => [
                        txt($d['drug_name'],     "xs", "#1A237E", 0, "start", true),
                        txt($d['dosage_form'] ?: '', "xs", "#888888", 0, "start", false),
                    ]
                ],
                txt($qty,  "xs", "#374151", 2, "center"),
                txt($loc,  "xs", "#374151", 2, "end",   true),
            ]
        ];
    }
    $items[] = ["type" => "separator", "margin" => "sm"];
    $items[] = [
        "type" => "box", "layout" => "horizontal", "margin" => "sm",
        "contents" => [
            txt("📅 " . date_thai(date('Y-m-d')), "xs", "#777777", 1),
            txt("⏰ " . date("H:i น."),           "xs", "#777777", 1, "end"),
        ]
    ];

    // ─── Bubble ──────────────────────────────────────────
    $bubble = [
        "type" => "bubble", "size" => "giga",
        "header" => [
            "type" => "box", "layout" => "vertical", "paddingAll" => "0px",
            "contents" => [[
                "type" => "image", "url" => HOSPITAL_LOGO_URL,
                "size" => "full", "aspectMode" => "cover", "aspectRatio" => "3250:750",
            ]],
        ],
        "body" => [
            "type" => "box", "layout" => "vertical", "paddingAll" => "md",
            "contents" => $items,
        ],
    ];

    return [[
        "type"    => "flex",
        "altText" => "{$emoji} แจ้งเตือนยาหมดอายุ {$count} รายการ | {$thaiDate}",
        "contents" => $bubble,
    ]];
}

// =========================================================
// Helper: สร้าง text element สำหรับ LINE Flex
// =========================================================
function txt(
    string $text,  string $size  = "sm",  string $color = "#333333",
    int    $flex   = 0,          string $align = "start",
    bool   $wrap   = false,      bool   $bold  = false
): array {
    $el = ["type"=>"text","text"=>$text,"size"=>$size,"color"=>$color,"align"=>$align,"wrap"=>$wrap];
    if ($flex > 0)  $el["flex"]   = $flex;
    if ($bold)      $el["weight"] = "bold";
    return $el;
}

// =========================================================
// ส่ง MOPH Notify
// =========================================================
function send_notify(array $messages): void
{
    $payload = json_encode(['messages' => $messages], JSON_UNESCAPED_UNICODE);
    write_log("Payload size: " . number_format(strlen($payload)) . " bytes");

    $ch = curl_init(MOPH_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'client-key: '     . CLIENT_KEY,
            'secret-key: '     . SECRET_KEY,
            'Content-Length: ' . strlen($payload),
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        write_log("❌ cURL Error: " . curl_error($ch));
    } else {
        write_log("HTTP {$code} | " . $response);
        write_log($code === 200 ? "✅ ส่งสำเร็จ!" : "⚠️ ส่งไม่สำเร็จ");
    }
    curl_close($ch);
}

// =========================================================
// Utilities
// =========================================================
function date_thai(string $d): string {
    $m = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.',
          'ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $ts = strtotime($d);
    return date('j',$ts).' '.$m[(int)date('n',$ts)].' '.((int)date('Y',$ts)+543);
}

function write_log(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}
