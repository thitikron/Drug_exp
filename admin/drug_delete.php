<?php
/**
 * admin/drug_delete.php – ลบรายการยา (soft delete)
 */
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['ok'=>false]); exit; }

DB::local()->query("UPDATE drug_expiry SET is_active=0 WHERE id=$id");
echo json_encode(['ok' => true]);
