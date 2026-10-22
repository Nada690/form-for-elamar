<?php
/**
 * lookup_unit.php - البحث عن كود الوحدة واسم العميل بالرقم القومي أو جواز السفر
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$rawId = trim((string)($_GET['national_id'] ?? $_POST['national_id'] ?? $_GET['id'] ?? ''));

// 1. تنظيف الرقم: إزالة المسافات والشرطات وتحويل الأرقام العربية (٠١٢٣) إلى إنجليزية
$arabicDigits = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
$englishDigits = ['0','1','2','3','4','5','6','7','8','9'];
$cleanId = str_replace($arabicDigits, $englishDigits, $rawId);
$cleanId = strtoupper(preg_replace('/[\s\-_–—]/u', '', $cleanId));

if (empty($cleanId)) {
    echo json_encode([
        'found' => false,
        'message' => 'الرقم غير محدد'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$lookupFile = __DIR__ . '/customer_units_lookup.json';
if (!file_exists($lookupFile)) {
    echo json_encode([
        'found' => false,
        'message' => 'ملف البيانات غير موجود'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = json_decode(file_get_contents($lookupFile), true);
if (!is_array($db) || !isset($db[$cleanId])) {
    echo json_encode([
        'found' => false,
        'message' => 'الرقم ده مش موجود'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$matches = $db[$cleanId];
$first = $matches[0];
$units = array_values(array_unique(array_column($matches, 'unit')));

echo json_encode([
    'found' => true,
    'unit_code' => $first['unit'],
    'unit' => $first['unit'],
    'name' => $first['name'],
    'units' => $units,
    'total_units' => count($units)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
