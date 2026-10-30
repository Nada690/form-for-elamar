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

if (!function_exists('getUnitDetailsHelper')) {
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
}

if (!function_exists('extractAllPartnerNames')) {
    /**
     * دالة تفكيك واستخراج جميع أسماء الشركاء للوحدة الواحدة بدقة
     */
    function extractAllPartnerNames($rawString): array {
        if (empty($rawString)) return [];
        $normalized = str_replace(["\r\n", "\r", "\n", '/', '&', '،', ','], '###', (string)$rawString);
        $normalized = preg_replace('/\s+-\s+/', '###', $normalized);
        $parts = explode('###', $normalized);
        $names = [];
        foreach ($parts as $p) {
            $clean = trim($p);
            if ($clean !== '' && mb_strlen($clean) >= 3) {
                $names[] = $clean;
            }
        }
        return array_values(array_unique($names));
    }
}

if (!function_exists('resolvePartnerDetailsHelper')) {
    /**
     * دالة ذكية لتحديد الاسم الأساسي وربط كل شريك برقمه القومي بدقة
     * إذا كان للشريك رقم قومي في السجلات يتم إرفاقه، وإن لم يكن له رقم قومي يترك فارغاً ليُعرض اسمه فقط
     */
    function resolvePartnerDetailsHelper($primaryNid, array $allUnitNames, array $unitNids, array $definitiveNamesByNid = []): array {
        $primaryNidStr = (string)$primaryNid;
        $unitNids = array_values(array_unique(array_filter($unitNids, fn($n) => (string)$n !== '')));

        // 1. تحديد الاسم الأساسي للعميل صاحب الرقم القومي المدخل
        $primaryName = '';
        if (!empty($definitiveNamesByNid[$primaryNidStr])) {
            $defName = $definitiveNamesByNid[$primaryNidStr];
            foreach ($allUnitNames as $an) {
                if (mb_strpos($an, $defName) !== false || mb_strpos($defName, $an) !== false) {
                    $primaryName = $an;
                    break;
                }
            }
        }

        // إذا لم يُعثر على اسم مؤكد بالبحث المباشر، تحقق إن كان الشركاء الآخرون قد حجزوا أسماءهم
        if (empty($primaryName) && count($unitNids) > 1 && count($allUnitNames) > 1) {
            $claimedByOthers = [];
            foreach ($unitNids as $onid) {
                if ((string)$onid === $primaryNidStr) continue;
                if (!empty($definitiveNamesByNid[(string)$onid])) {
                    $odef = $definitiveNamesByNid[(string)$onid];
                    foreach ($allUnitNames as $an) {
                        if (mb_strpos($an, $odef) !== false || mb_strpos($odef, $an) !== false) {
                            $claimedByOthers[] = $an;
                            break;
                        }
                    }
                }
            }
            $unclaimed = array_values(array_diff($allUnitNames, $claimedByOthers));
            if (count($unclaimed) === 1) {
                $primaryName = $unclaimed[0];
            }
        }

        // مطابقة بالترتيب إن لم يُحدد الاسم بعد
        if (empty($primaryName)) {
            $pIdx = array_search($primaryNidStr, $unitNids);
            if ($pIdx !== false && isset($allUnitNames[$pIdx])) {
                $primaryName = $allUnitNames[$pIdx];
            } else {
                $primaryName = $allUnitNames[0] ?? '';
            }
        }

        // أسماء الشركاء (جميع الأسماء باستثناء الاسم الأساسي)
        $partnerNames = array_values(array_filter($allUnitNames, fn($n) => $n !== $primaryName));

        // الأرقام القومية للشركاء (جميع الأرقام القومية المسجلة للوحدة باستثناء رقم العميل الأساسي)
        $otherNids = array_values(array_filter($unitNids, fn($n) => (string)$n !== $primaryNidStr));

        $partnerDetails = [];
        $assignedNids = [];

        // الخطوة أ: مطابقة الشركاء الذين لهم اسم مؤكد مسجل
        foreach ($partnerNames as $pName) {
            $matchedNid = '';
            foreach ($otherNids as $onid) {
                $onidStr = (string)$onid;
                if (in_array($onidStr, $assignedNids)) continue;
                if (!empty($definitiveNamesByNid[$onidStr])) {
                    $odef = $definitiveNamesByNid[$onidStr];
                    if (mb_strpos($pName, $odef) !== false || mb_strpos($odef, $pName) !== false) {
                        $matchedNid = $onidStr;
                        $assignedNids[] = $onidStr;
                        break;
                    }
                }
            }
            $partnerDetails[$pName] = $matchedNid;
        }

        // الخطوة ب: مطابقة الأرقام القومية المتبقية بالترتيب على الشركاء الذين لم يُحدد رقمهم بعد
        $remainingNids = array_values(array_diff($otherNids, $assignedNids));
        $remIdx = 0;
        foreach ($partnerDetails as $pName => $nid) {
            if (empty($nid) && isset($remainingNids[$remIdx])) {
                $partnerDetails[$pName] = $remainingNids[$remIdx];
                $remIdx++;
            }
        }

        $result = [];
        foreach ($partnerDetails as $pName => $nid) {
            $result[] = [
                'name'        => $pName,
                'national_id' => !empty($nid) ? (string)$nid : ''
            ];
        }

        return [
            'primary_name'    => $primaryName,
            'partner_details' => $result
        ];
    }
}

$clientName = '';
$allExtractedNames = [];
$allPartnerDetails = [];
$primaryClientName = '';
$unitsList = [];
$unitCodes = [];
$foundInDb = false;

// 1. محاولة الجلب من قاعدة بيانات MySQL عبر Prepared Statements
try {
    $db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
    if (!empty($db_url)) {
        $db_parts = parse_url($db_url);
        $db_host = $db_parts['host'] ?? '127.0.0.1';
        $db_port = $db_parts['port'] ?? 3306;
        $db_user = $db_parts['user'] ?? 'root';
        $db_pass = $db_parts['pass'] ?? '';
        $db_name = ltrim($db_parts['path'] ?? 'elamar_db', '/');
    } else {
        $db_host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
        $db_port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';
        $db_name = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'elamar_db';
        $db_user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
        $db_pass = getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');
    }

    $pdo = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    $stmt = $pdo->prepare("SELECT unit_code, client_name, unit_area, unit_floor, national_id 
                           FROM customer_units 
                           WHERE national_id = :nid 
                           ORDER BY id ASC");
            $stmt->execute([':nid' => $cleanId]);
            $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($dbRows)) {
                $foundInDb = true;

                // استعلام فحص كافة الشركاء المسجلين لنفس كود الوحدة في customer_units
                $partnerStmt = $pdo->prepare("SELECT DISTINCT client_name, national_id 
                                              FROM customer_units 
                                              WHERE unit_code = :uc");

                // استعلام فحص العملاء المسجلين في جدول clients لنفس الوحدة
                $clientPartnerStmt = null;
                try {
                    $clientPartnerStmt = $pdo->prepare("SELECT DISTINCT name, id_number 
                                                        FROM clients 
                                                        WHERE eoi_number = :uc");
                } catch (Exception $ce) {}

                // جلب الأسماء المنفردة لكل رقم قومي للمطابقة الذكية
                $knownSingleStmt = $pdo->prepare("SELECT client_name FROM customer_units WHERE national_id = :nid LIMIT 5");

                foreach ($dbRows as $row) {
                    $uCode = trim($row['unit_code']);
                    if (in_array($uCode, $unitCodes)) continue;
                    $unitCodes[] = $uCode;

                    $unitRawNames = [];
                    $unitNids = [];

                    try {
                        $partnerStmt->execute([':uc' => $uCode]);
                        $otherCuRows = $partnerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        foreach ($otherCuRows as $ocr) {
                            $unitRawNames[] = $ocr['client_name'];
                            if (!empty($ocr['national_id'])) {
                                $unitNids[] = (string)$ocr['national_id'];
                            }
                        }
                    } catch (Exception $pe) {}

                    if ($clientPartnerStmt) {
                        try {
                            $clientPartnerStmt->execute([':uc' => $uCode]);
                            $otherCliRows = $clientPartnerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            foreach ($otherCliRows as $ocr) {
                                $unitRawNames[] = $ocr['name'];
                                if (!empty($ocr['id_number'])) {
                                    $unitNids[] = (string)$ocr['id_number'];
                                }
                            }
                        } catch (Exception $pe2) {}
                    }

                    $unitRawNames[] = $row['client_name'];
                    if (!in_array($cleanId, $unitNids)) {
                        $unitNids[] = $cleanId;
                    }

                    // استخراج جميع الأسماء الفريدة
                    $unitAllNames = [];
                    foreach ($unitRawNames as $urn) {
                        foreach (extractAllPartnerNames($urn) as $en) {
                            if (!in_array($en, $unitAllNames)) {
                                $unitAllNames[] = $en;
                            }
                        }
                    }

                    // جلب الأسماء المؤكدة للأرقام القومية المرتبطة بهذه الوحدة
                    $defNames = [];
                    foreach (array_unique($unitNids) as $unid) {
                        try {
                            $knownSingleStmt->execute([':nid' => $unid]);
                            $cuNames = $knownSingleStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                            foreach ($cuNames as $cun) {
                                $ex = extractAllPartnerNames($cun);
                                if (count($ex) === 1) {
                                    $defNames[(string)$unid] = $ex[0];
                                    break;
                                }
                            }
                        } catch (Exception $kse) {}
                    }

                    $resolved = resolvePartnerDetailsHelper($cleanId, $unitAllNames, $unitNids, $defNames);
                    $uPrimaryName = $resolved['primary_name'];
                    $uPartnerDetails = $resolved['partner_details'];
                    $uPartnerNames = array_column($uPartnerDetails, 'name');

                    if (empty($primaryClientName)) {
                        $primaryClientName = $uPrimaryName;
                    }

                    foreach ($unitAllNames as $pName) {
                        if (!in_array($pName, $allExtractedNames)) {
                            $allExtractedNames[] = $pName;
                        }
                    }

                    foreach ($uPartnerDetails as $pd) {
                        $foundExisting = false;
                        foreach ($allPartnerDetails as &$exPd) {
                            if ($exPd['name'] === $pd['name']) {
                                if (empty($exPd['national_id']) && !empty($pd['national_id'])) {
                                    $exPd['national_id'] = $pd['national_id'];
                                }
                                $foundExisting = true;
                                break;
                            }
                        }
                        if (!$foundExisting) {
                            $allPartnerDetails[] = $pd;
                        }
                    }

                    $uDetails = getUnitDetailsHelper($uCode);
                    $floor = !empty($row['unit_floor']) ? $row['unit_floor'] : $uDetails['floor'];
                    $area = !empty($row['unit_area']) ? $row['unit_area'] : $uDetails['area'];

                    $unitsList[] = [
                        'code'            => $uCode,
                        'floor'           => $floor,
                        'area'            => $area,
                        'primary_name'    => $uPrimaryName,
                        'partners'        => $uPartnerNames,
                        'partner_details' => $uPartnerDetails,
                        'partners_text'   => implode(' ، ', $uPartnerNames)
                    ];
                }

                $clientName = !empty($allExtractedNames) ? implode(' ، ', $allExtractedNames) : $dbRows[0]['client_name'];
            }
    } catch (Throwable $e) {
        // Fallback to JSON below
    }

// 2. البديل الآمن: الجلب من ملف JSON (لبيئات السيرفرات الساكنة مثل Vercel أو عند انقطاع MySQL)
if (!$foundInDb) {
    $lookupFile = __DIR__ . '/customer_units_lookup.json';
    if (file_exists($lookupFile)) {
        $db = json_decode(file_get_contents($lookupFile), true);
        if (is_array($db) && isset($db[$cleanId])) {
            $clientEntries = $db[$cleanId];

            // استخراج الأسماء المنفردة المؤكدة للأرقام القومية
            $defNames = [];
            foreach ($db as $dId => $dEntries) {
                $dIdStr = (string)$dId;
                foreach ($dEntries as $de) {
                    $ex = extractAllPartnerNames($de['name'] ?? '');
                    if (count($ex) === 1) {
                        $defNames[$dIdStr] = $ex[0];
                        break;
                    }
                }
            }

            foreach ($clientEntries as $entry) {
                $uCode = trim($entry['unit'] ?? '');
                if (empty($uCode) || in_array($uCode, $unitCodes)) continue;
                $unitCodes[] = $uCode;

                $uDetails = getUnitDetailsHelper($uCode);

                // استخراج جميع الأسماء والأرقام القومية المرتبطة بهذه الوحدة
                $unitRawNames = [];
                $unitNids = [];

                foreach ($db as $otherId => $otherEntries) {
                    $otherIdStr = (string)$otherId;
                    foreach ($otherEntries as $oe) {
                        if (($oe['unit'] ?? '') === $uCode) {
                            $unitRawNames[] = $oe['name'] ?? '';
                            $unitNids[] = $otherIdStr;
                        }
                    }
                }

                $unitRawNames[] = $entry['name'] ?? '';
                if (!in_array($cleanId, $unitNids)) {
                    $unitNids[] = $cleanId;
                }

                $unitAllNames = [];
                foreach ($unitRawNames as $urn) {
                    foreach (extractAllPartnerNames($urn) as $en) {
                        if (!in_array($en, $unitAllNames)) {
                            $unitAllNames[] = $en;
                        }
                    }
                }

                $resolved = resolvePartnerDetailsHelper($cleanId, $unitAllNames, $unitNids, $defNames);
                $uPrimaryName = $resolved['primary_name'];
                $uPartnerDetails = $resolved['partner_details'];
                $uPartnerNames = array_column($uPartnerDetails, 'name');

                if (empty($primaryClientName)) {
                    $primaryClientName = $uPrimaryName;
                }

                foreach ($unitAllNames as $pName) {
                    if (!in_array($pName, $allExtractedNames)) {
                        $allExtractedNames[] = $pName;
                    }
                }

                foreach ($uPartnerDetails as $pd) {
                    $foundExisting = false;
                    foreach ($allPartnerDetails as &$exPd) {
                        if ($exPd['name'] === $pd['name']) {
                            if (empty($exPd['national_id']) && !empty($pd['national_id'])) {
                                $exPd['national_id'] = $pd['national_id'];
                            }
                            $foundExisting = true;
                            break;
                        }
                    }
                    if (!$foundExisting) {
                        $allPartnerDetails[] = $pd;
                    }
                }

                $unitsList[] = [
                    'code'            => $uCode,
                    'floor'           => $uDetails['floor'],
                    'area'            => $uDetails['area'],
                    'primary_name'    => $uPrimaryName,
                    'partners'        => $uPartnerNames,
                    'partner_details' => $uPartnerDetails,
                    'partners_text'   => implode(' ، ', $uPartnerNames)
                ];
            }

            $clientName = !empty($allExtractedNames) ? implode(' ، ', $allExtractedNames) : ($clientEntries[0]['name'] ?? '');
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

if (empty($primaryClientName)) {
    $primaryClientName = $allExtractedNames[0] ?? $clientName;
}

$partnerNamesOnly = array_values(array_filter($allExtractedNames, fn($n) => $n !== $primaryClientName));

// ── فحص منع إعادة التسجيل: هل تم تسجيل واستيفاء بيانات هذا العقد مسبقاً؟ ──
$alreadyRegistered = false;
$registeredClientInfo = null;

if (!empty($pdo)) {
    try {
        // 1. فحص بجدول clients بواسطة الرقم القومي
        $checkStmt = $pdo->prepare("SELECT id, name, eoi_number, created_at FROM clients WHERE id_number = :nid ORDER BY id DESC LIMIT 1");
        $checkStmt->execute([':nid' => $cleanId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // 2. إذا لم يعثر عليه بالرقم القومي، فحص بأكواد الوحدات التابعة له
        if (!$existing && !empty($unitCodes)) {
            $inClause = implode(',', array_fill(0, count($unitCodes), '?'));
            $checkUnitStmt = $pdo->prepare("SELECT id, name, eoi_number, created_at FROM clients WHERE eoi_number IN ($inClause) ORDER BY id DESC LIMIT 1");
            $checkUnitStmt->execute($unitCodes);
            $existing = $checkUnitStmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($existing) {
            $alreadyRegistered = true;
            $registeredClientInfo = [
                'id'         => $existing['id'],
                'name'       => $existing['name'],
                'unit_code'  => $existing['eoi_number'],
                'created_at' => $existing['created_at']
            ];
        }
    } catch (Exception $e) {}
}

echo json_encode([
    'found'              => true,
    'already_registered' => $alreadyRegistered,
    'registered_info'    => $registeredClientInfo,
    'unit_code'          => $unitsList[0]['code'] ?? '',
    'unit'               => $unitsList[0]['code'] ?? '',
    'name'               => $clientName,
    'primary_name'       => $primaryClientName,
    'partner_names'      => $partnerNamesOnly,
    'partner_details'    => $allPartnerDetails,
    'unit_area'          => $unitsList[0]['area'] ?? '',
    'unit_floor'         => $unitsList[0]['floor'] ?? '',
    'units_info'         => $unitsList,
    'units'              => $unitCodes,
    'total_units'        => count($unitsList),
    'partners'           => $allExtractedNames,
    'has_partners'       => (count($allPartnerDetails) > 0),
    'partners_text'      => implode(' ، ', $partnerNamesOnly)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
