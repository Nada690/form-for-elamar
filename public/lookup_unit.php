<?php
/**
 * lookup_unit.php - البحث عن كود الوحدة والشركاء والمساحة والدور بالرقم القومي
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

// 1. تنظيف الرقم: إزالة المسافات والشرطات وتحويل الأرقام العربية إلى إنجليزية
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

function getUnitDetails($unitCode) {
    $floor = 'الدور الأرضي';
    $area = '125 م²';

    if (preg_match('/-(\d)(\d{2})$/', $unitCode, $m)) {
        $floorNum = intval($m[1]);
        $floorNames = [
            0 => 'الدور الأرضي',
            1 => 'الدور الأول',
            2 => 'الدور الثاني',
            3 => 'الدور الثالث',
            4 => 'الدور الرابع',
            5 => 'الدور الخامس',
            6 => 'الدور السادس'
        ];
        $floor = $floorNames[$floorNum] ?? ("الدور " . $floorNum);
    } elseif (preg_match('/-(\d+)$/', $unitCode, $m)) {
        $floor = "الدور " . substr($m[1], 0, 1);
    }

    return ['floor' => $floor, 'area' => $area];
}

$clientEntries = $db[$cleanId];
$firstEntry = $clientEntries[0];
$clientName = $firstEntry['name'];

$unitsList = [];
$unitCodes = [];

foreach ($clientEntries as $entry) {
    $uCode = $entry['unit'];
    if (in_array($uCode, $unitCodes)) continue;
    $unitCodes[] = $uCode;

    $uDetails = getUnitDetails($uCode);

    // البحث عن شركاء مسجلين على نفس الوحدة
    $partners = [];
    foreach ($db as $otherId => $otherEntries) {
        if ($otherId === $cleanId) continue;
        foreach ($otherEntries as $oe) {
            if ($oe['unit'] === $uCode && !in_array($oe['name'], $partners)) {
                $partners[] = $oe['name'];
            }
        }
    }

    $unitsList[] = [
        'code'      => $uCode,
        'floor'     => $uDetails['floor'],
        'area'      => $uDetails['area'],
        'partners'  => $partners
    ];
}

echo json_encode([
    'found'       => true,
    'unit_code'   => $unitsList[0]['code'] ?? '',
    'unit'        => $unitsList[0]['code'] ?? '',
    'name'        => $clientName,
    'unit_area'   => $unitsList[0]['area'] ?? '',
    'unit_floor'  => $unitsList[0]['floor'] ?? '',
    'units_info'  => $unitsList,
    'units'       => $unitCodes,
    'total_units' => count($unitsList),
    'partners'    => $unitsList[0]['partners'] ?? []
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
