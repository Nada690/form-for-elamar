<?php
/**
 * lookup_unit.php - البحث عن كافة الوحدات المسجلة للعميل والشركاء بالرقم القومي (MySQL Prepared Statements + Fallback)
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

function getUnitDetailsHelper($unitCode) {
    $floor = 'الدور الأرضي';
    $area = '125 م²';
    $fNames = [
        0 => 'الدور الأرضي',
        1 => 'الدور الأول',
        2 => 'الدور الثاني',
        3 => 'الدور الثالث',
        4 => 'الدور الرابع',
        5 => 'الدور الخامس',
        6 => 'الدور السادس'
    ];

    if (preg_match('/[-_](\d)(\d{1,2})$/', $unitCode, $m)) {
        $floorNum = intval($m[1]);
        $floor = $fNames[$floorNum] ?? ("الدور " . $floorNum);
    } elseif (preg_match('/[-_](\d+)$/', $unitCode, $m)) {
        $floorNum = intval(substr($m[1], 0, 1));
        $floor = $fNames[$floorNum] ?? ("الدور " . $floorNum);
    } elseif (preg_match('/(\d+)/', $unitCode, $m)) {
        $floorNum = intval(substr($m[1], 0, 1));
        $floor = $fNames[$floorNum] ?? ("الدور " . $floorNum);
    }

    return ['floor' => $floor, 'area' => $area];
}

$clientName = '';
$unitsList = [];
$unitCodes = [];
$foundInDb = false;

// 1. محاولة الجلب من قاعدة بيانات MySQL عبر Prepared Statements
if (file_exists(__DIR__ . '/db.php')) {
    try {
        require_once __DIR__ . '/db.php';
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT unit_code, client_name, unit_area, unit_floor 
                                   FROM customer_units 
                                   WHERE national_id = :nid 
                                   ORDER BY id ASC");
            $stmt->execute([':nid' => $cleanId]);
            $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($dbRows)) {
                $foundInDb = true;
                $clientName = $dbRows[0]['client_name'];

                // جلب الشركاء لكل وحدة
                $partnerStmt = $pdo->prepare("SELECT DISTINCT client_name 
                                              FROM customer_units 
                                              WHERE unit_code = :uc 
                                                AND national_id != :nid");

                foreach ($dbRows as $row) {
                    $uCode = trim($row['unit_code']);
                    if (in_array($uCode, $unitCodes)) continue;
                    $unitCodes[] = $uCode;

                    $partners = [];
                    try {
                        $partnerStmt->execute([':uc' => $uCode, ':nid' => $cleanId]);
                        $partners = $partnerStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    } catch (Exception $pe) {}

                    $uDetails = getUnitDetailsHelper($uCode);
                    $floor = !empty($row['unit_floor']) ? $row['unit_floor'] : $uDetails['floor'];
                    $area = !empty($row['unit_area']) ? $row['unit_area'] : $uDetails['area'];

                    $unitsList[] = [
                        'code'     => $uCode,
                        'floor'    => $floor,
                        'area'     => $area,
                        'partners' => $partners
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Fallback to JSON below
    }
}

// 2. البديل الآمن: الجلب من ملف JSON (لبيئات السيرفرات الساكنة مثل Vercel أو عند انقطاع MySQL)
if (!$foundInDb) {
    $lookupFile = __DIR__ . '/customer_units_lookup.json';
    if (file_exists($lookupFile)) {
        $db = json_decode(file_get_contents($lookupFile), true);
        if (is_array($db) && isset($db[$cleanId])) {
            $clientEntries = $db[$cleanId];
            $clientName = $clientEntries[0]['name'] ?? '';

            foreach ($clientEntries as $entry) {
                $uCode = trim($entry['unit'] ?? '');
                if (empty($uCode) || in_array($uCode, $unitCodes)) continue;
                $unitCodes[] = $uCode;

                $uDetails = getUnitDetailsHelper($uCode);

                // الشركاء
                $partners = [];
                foreach ($db as $otherId => $otherEntries) {
                    if ($otherId === $cleanId) continue;
                    foreach ($otherEntries as $oe) {
                        if (($oe['unit'] ?? '') === $uCode && !in_array($oe['name'], $partners)) {
                            $partners[] = $oe['name'];
                        }
                    }
                }

                $unitsList[] = [
                    'code'     => $uCode,
                    'floor'    => $uDetails['floor'],
                    'area'     => $uDetails['area'],
                    'partners' => $partners
                ];
            }
        }
    }
}

if (empty($unitsList)) {
    echo json_encode([
        'found'   => false,
        'message' => 'لا توجد وحدات مسجلة لهذا الرقم القومي في قاعدة البيانات'
    ], JSON_UNESCAPED_UNICODE);
    exit;
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
