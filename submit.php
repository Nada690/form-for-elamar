<?php
/**
 * submit.php - Secure Data Ingestion Endpoint for Shores Coastal Projects
 * تم تحديثه:
 * 1. دمج كود الدولة مع رقم الهاتف بصيغة [كود الدولة][رقم الهاتف] (+2010xxxxxxxx)
 * 2. التحقق الدقيق من الرقم القومي (14 رقماً بالضبط) وجواز السفر (6-9 أحرف وأرقام)
 * 3. المسمى الحرفي: العنوان كما هو مسجل في إثبات الهوية الشخصية
 * 4. خيارات مكان التوثيق صافية بدون إيموجي (القاهرة / الإسكندرية)
 * 5. دعم حقلي الملاحظات: ملاحظات عامة + خطة بديلة واستثناءات (كلاهما اختياري)
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'message' => 'طريقة الطلب غير مسموح بها (POST Only)'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();
require_once __DIR__ . '/db.php';

function cleanInput($val) {
    if (!isset($val)) return '';
    return trim(htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'));
}

$rawInput = file_get_contents('php://input');
$rawInput = preg_replace('/^\xEF\xBB\xBF/', '', $rawInput);
$jsonData = json_decode($rawInput, true);

$data = (!empty($_POST)) ? $_POST : (is_array($jsonData) ? $jsonData : []);
$lang = cleanInput($data['lang'] ?? 'ar');

/**
 * تنسيق وتنظيف أرقام الهواتف وحفظها كأرقام فقط (Digits Only) بدون علامة +
 * الأرقام المصرية (+20): تُخزن كـ 20 متبوعة بـ 10 أرقام (مثال: 201012345678)
 * الأرقام الدولية: تُخزن ككود الدولة + الرقم بدون + (مثال: 966501234567)
 */
function sanitizePhoneNumber($code, $num) {
    $codeDigits = preg_replace('/[^\d]/', '', (string)$code);
    $numDigits = preg_replace('/[^\d]/', '', (string)$num);

    if (strpos($numDigits, '00') === 0) {
        $numDigits = substr($numDigits, 2);
    }

    if (!empty($codeDigits) && strpos($numDigits, $codeDigits) === 0) {
        $numDigits = substr($numDigits, strlen($codeDigits));
    }

    $numDigits = ltrim($numDigits, '0');

    if ($codeDigits === '20' || empty($codeDigits)) {
        return '20' . $numDigits;
    }

    return $codeDigits . $numDigits;
}

// 1. أطراف العقد والعملاء (دعم بنية clients_json الديناميكية والمصفوفات العادية)
$clientsParsed = [];
if (!empty($data['clients_json'])) {
    $rawString = is_string($data['clients_json']) ? trim($data['clients_json']) : '';
    $rawString = preg_replace('/^\xEF\xBB\xBF/', '', $rawString);
    $rawC = is_array($data['clients_json']) ? $data['clients_json'] : json_decode($rawString, true);
    if (!is_array($rawC) && is_string($rawString)) {
        $rawC = json_decode(stripslashes($rawString), true);
    }
    if (is_array($rawC)) {
        $clientsParsed = $rawC;
    }
} elseif (!empty($data['clients']) && is_array($data['clients'])) {
    $clientsParsed = $data['clients'];
}

$namesArr = [];
$phonesArr = [];
$emailsArr = [];
$idNumbersArr = [];
$idTypesArr = [];
$addressParts = [];
$residenceList = [];
$idType = cleanInput($data['id_type'] ?? 'national_id');

if (!empty($clientsParsed)) {
    foreach ($clientsParsed as $cIdx => $c) {
        if (!empty($c['names']) && is_array($c['names'])) {
            foreach ($c['names'] as $n) {
                $cn = cleanInput($n);
                if (!empty($cn)) $namesArr[] = $cn;
            }
        } else {
            $cName = cleanInput($c['fullName'] ?? $c['name'] ?? '');
            if (!empty($cName)) {
                $parts = explode(' ، ', $cName);
                foreach ($parts as $p) {
                    $cp = cleanInput($p);
                    if (!empty($cp)) $namesArr[] = $cp;
                }
            }
        }

        // Phones
        if (!empty($c['phones']) && is_array($c['phones'])) {
            foreach ($c['phones'] as $p) {
                $pNum = is_array($p) ? ($p['formatted'] ?? $p['number'] ?? '') : (string)$p;
                $pCode = is_array($p) ? ($p['code'] ?? '+20') : '+20';
                $cleanNum = cleanInput($pNum);
                if (!empty($cleanNum)) {
                    $formattedPhone = sanitizePhoneNumber($pCode, $cleanNum);
                    if (!empty($formattedPhone)) {
                        $phonesArr[] = $formattedPhone;
                    }
                }
            }
        }

        // Emails
        if (!empty($c['emails']) && is_array($c['emails'])) {
            foreach ($c['emails'] as $em) {
                $cleanEm = cleanInput($em);
                if (!empty($cleanEm)) $emailsArr[] = $cleanEm;
            }
        }

        // ID Number & Type
        $cIdNum = cleanInput($c['nationalId'] ?? $c['idNumber'] ?? $c['id_number'] ?? '');
        if (!empty($cIdNum)) $idNumbersArr[] = $cIdNum;

        // Partner National IDs if present
        if (!empty($c['partnerNids']) && is_array($c['partnerNids'])) {
            foreach ($c['partnerNids'] as $pnid) {
                $cleanPnid = cleanInput($pnid);
                if (!empty($cleanPnid) && !in_array($cleanPnid, $idNumbersArr)) {
                    $idNumbersArr[] = $cleanPnid;
                    $idTypesArr[] = 'national_id';
                }
            }
        }

        $cType = cleanInput($c['idType'] ?? 'national_id');
        $idTypesArr[] = $cType;
        $idType = $cType;

        // Residence
        $cRes = cleanInput($c['residenceCountry'] ?? $c['residence_country'] ?? '');
        if (!empty($cRes)) $residenceList[] = $cRes;

        // Addresses
        $cIdAddr = cleanInput($c['idAddress'] ?? $c['id_address'] ?? '');
        $cCorrAddr = cleanInput($c['correspondenceAddress'] ?? $c['correspondence_address'] ?? '');
        $pLabel = (count($clientsParsed) > 1) ? ("طرف " . ($cIdx + 1) . " ({$cName}): ") : "";
        if (!empty($cIdAddr)) $addressParts[] = $pLabel . "العنوان في الهوية: " . $cIdAddr;
        if (!empty($cCorrAddr)) $addressParts[] = $pLabel . "عنوان المراسلات: " . $cCorrAddr;
    }
}

// Fallback to flat form arrays if clients_json was not provided
if (empty($namesArr)) {
    if (!empty($data['names']) && is_array($data['names'])) {
        foreach ($data['names'] as $n) {
            $c = cleanInput($n);
            if (!empty($c)) $namesArr[] = $c;
        }
    } elseif (!empty($data['name'])) {
        $namesArr[] = cleanInput($data['name']);
    }
}

if (empty($phonesArr)) {
    if (!empty($data['phone_numbers']) && is_array($data['phone_numbers'])) {
        $codes = $data['phone_codes'] ?? [];
        foreach ($data['phone_numbers'] as $idx => $num) {
            $cleanNum = cleanInput($num);
            if (!empty($cleanNum)) {
                $code = cleanInput($codes[$idx] ?? '+20');
                $formattedPhone = sanitizePhoneNumber($code, $cleanNum);
                if (!empty($formattedPhone)) {
                    $phonesArr[] = $formattedPhone;
                }
            }
        }
    } elseif (!empty($data['phones']) && is_array($data['phones'])) {
        foreach ($data['phones'] as $p) {
            $cleanNum = cleanInput($p);
            if (!empty($cleanNum)) {
                $phonesArr[] = sanitizePhoneNumber('+20', $cleanNum);
            }
        }
    } elseif (!empty($data['phone'])) {
        $cleanNum = cleanInput($data['phone']);
        $pCode = cleanInput($data['phone_code'] ?? '+20');
        $phonesArr[] = sanitizePhoneNumber($pCode, $cleanNum);
    }
}

if (empty($emailsArr)) {
    if (!empty($data['emails']) && is_array($data['emails'])) {
        foreach ($data['emails'] as $em) {
            $c = cleanInput($em);
            if (!empty($c)) $emailsArr[] = $c;
        }
    } elseif (!empty($data['email'])) {
        $emailsArr[] = cleanInput($data['email']);
    }
}

if (empty($idNumbersArr)) {
    if (!empty($data['id_numbers']) && is_array($data['id_numbers'])) {
        foreach ($data['id_numbers'] as $idn) {
            $c = cleanInput($idn);
            if (!empty($c)) $idNumbersArr[] = $c;
        }
    } elseif (!empty($data['id_number'])) {
        $idNumbersArr[] = cleanInput($data['id_number']);
    }
}

$nameCombined = implode(' ، ', $namesArr);
$phoneCombined = implode(' ، ', $phonesArr);
$email = implode(' ، ', $emailsArr);
$id_type = !empty($idTypesArr) ? implode(' ، ', $idTypesArr) : $idType;
$id_number = implode(' ، ', $idNumbersArr);

$residence_country = cleanInput($data['residence_country'] ?? '');
if (!empty($residenceList)) {
    $residence_country = in_array('خارج جمهورية مصر العربية', $residenceList) ? 'خارج جمهورية مصر العربية' : $residenceList[0];
}
if (empty($residence_country) || $residence_country === 'inside_egypt') {
    $residence_country = 'داخل جمهورية مصر العربية';
} elseif ($residence_country === 'outside_egypt') {
    $residence_country = 'خارج جمهورية مصر العربية';
}

$id_address = cleanInput($data['id_address'] ?? '');
$correspondence_address = cleanInput($data['correspondence_address'] ?? '');

if (empty($addressParts)) {
    if (!empty($id_address)) {
        $addressParts[] = "العنوان في الهوية الشخصية: " . $id_address;
    }
    if (!empty($correspondence_address)) {
        $addressParts[] = "عنوان المراسلات: " . $correspondence_address;
    }
    if (empty($addressParts) && !empty($data['addresses']) && is_array($data['addresses'])) {
        foreach ($data['addresses'] as $addr) {
            $c = cleanInput($addr);
            if (!empty($c)) $addressParts[] = $c;
        }
    }
}
$addressCombined = implode(" \n---\n ", $addressParts);
if (empty($id_address) && !empty($addressParts)) {
    $id_address = $addressParts[0];
}
if (empty($correspondence_address) && !empty($addressParts)) {
    $correspondence_address = $addressParts[count($addressParts) - 1];
}

// 7. بيانات الوحدة ونظام التعاقد
$unit_code = cleanInput($data['unit_code'] ?? $data['eoi'] ?? $data['eoi_number'] ?? '');
$unit_price = !empty($data['unit_price']) ? floatval(str_replace(',', '', $data['unit_price'])) : null;
$rawPeriod = cleanInput($data['period'] ?? $data['payment_period'] ?? '');
$payment_period = $rawPeriod;


// حالة سداد المقدم + تاريخ الدفع
$paid_5_percent = cleanInput($data['paid_5_percent'] ?? $data['paid_october_advance'] ?? '');
$payment_date = cleanInput($data['payment_date'] ?? '');

if ($paid_5_percent === 'لا') {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'paid_5_percent',
        'message' => ($lang === 'en')
            ? 'Contract registration cannot proceed without completing the down payment.'
            : 'عفواً، لا يمكن استكمال تسجيل التعاقد وحجز الوحدة إلا بعد استكمال سداد قيمة المقدم.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($paid_5_percent !== 'نعم') {
    $payment_date = null;
}

// مكان توثيق العقد الشرطي (نص صافي: القاهرة / الإسكندرية / خارج مصر)
$contract_location = cleanInput($data['contract_location'] ?? '');
if ($residence_country === 'خارج جمهورية مصر العربية') {
    $contract_location = 'خارج جمهورية مصر العربية';
}

// المكان الأقرب لتوقيع العقد (حقل جديد)
$preferred_signing_location = cleanInput($data['preferred_signing_location'] ?? '');

// الدولة والمحافظة للمراسلات
$correspondence_country = cleanInput($data['correspondence_country'] ?? 'مصر');
$correspondence_city = cleanInput($data['correspondence_city'] ?? '');

// تفاصيل الوحدة والشيكات
$unit_area = cleanInput($data['unit_area'] ?? '');
$unit_floor = cleanInput($data['unit_floor'] ?? '');
$cheques_count = !empty($data['cheques_count']) ? intval($data['cheques_count']) : null;

// 8. حقلي الملاحظات (كلاهما اختياري)
$general_notes = cleanInput($data['general_notes'] ?? '');
$notes = cleanInput($data['notes'] ?? '');

// تفاصيل المشروع والمصدر
$project = cleanInput($data['project'] ?? 'شورز — Shores (الساحل الشمالي)');
$governorate = cleanInput($data['governorate'] ?? ($contract_location ?: 'الساحل'));
$source = cleanInput($data['source'] ?? 'مباشر');

// ── الربط التلقائي بموظف المبيعات (Sales ID & Referral Code) ──
$sales_id = null;

// 1. قراءة كود الموظف من البيانات المستلمة (POST و GET مع دعم sales_ref و slug و ref)
$salesCode = trim(
    $data['sales_ref'] ?? 
    $data['slug'] ?? 
    $data['ref'] ?? 
    $_POST['sales_ref'] ?? 
    $_POST['slug'] ?? 
    $_POST['ref'] ?? 
    $_GET['slug'] ?? 
    $_GET['ref'] ?? 
    ''
);

// 2. البحث عن الموظف المسؤول في جدول users بواسطة كود الإحالة (referral_code)
if (!empty($salesCode)) {
    try {
        $uStmt = $pdo->prepare("SELECT id FROM users 
                                WHERE (referral_code = :c OR referral_code = :c2) 
                                  AND active = 1 
                                LIMIT 1");
        $uStmt->execute([':c' => $salesCode, ':c2' => $salesCode]);
        $uRow = $uStmt->fetch();
        if ($uRow) {
            $sales_id = (int)$uRow['id'];
        } else {
            // فحص جدول sales_employees للتوافق العكسي في حال كان الكود مسجلاً به
            try {
                $seStmt = $pdo->prepare("SELECT id, email FROM sales_employees WHERE slug = :s AND active = 1 LIMIT 1");
                $seStmt->execute([':s' => $salesCode]);
                $seRow = $seStmt->fetch();
                if ($seRow) {
                    $uByEmail = $pdo->prepare("SELECT id FROM users WHERE email = :e AND active = 1 LIMIT 1");
                    $uByEmail->execute([':e' => $seRow['email']]);
                    $matchedU = $uByEmail->fetch();
                    if ($matchedU) {
                        $sales_id = (int)$matchedU['id'];
                    }
                }
            } catch (PDOException $e2) {}
        }
    } catch (PDOException $e) {
        $sales_id = null;
    }
}

// 3. في حال تم تمرير sales_id صريح وتم التحقق من كونه موظف مبيعات نشط (وليس الأدمن)
if (!$sales_id && !empty($data['sales_id']) && is_numeric($data['sales_id'])) {
    $candId = (int)$data['sales_id'];
    try {
        $chkStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'Sales' AND active = 1 LIMIT 1");
        $chkStmt->execute([':id' => $candId]);
        if ($chkStmt->fetch()) {
            $sales_id = $candId;
        }
    } catch (PDOException $e) {}
}

// 4. إلغاء أي Fallback يربط العميل بالأدمن:
// إذا لم يتطابق أي كود موظف مبيعات، يظل sales_id = NULL
// (تم استبعاد تعيين $_SESSION['auth_user_id'] نهائياً لمنع إسناد العملاء لحساب الأدمن)

// ── التحقق من صحة المدخلات (Strict Validation) ──
$errors = [];

// اسم العميل (باللغة العربية فقط)
if (empty($nameCombined) || mb_strlen($nameCombined) < 2) {
    $errors[] = ($lang === 'en') ? 'Client name as in National ID is required.' : 'اسم العميل كما هو في البطاقة الشخصية مطلوب.';
} else {
    foreach ($namesArr as $singleName) {
        if (!preg_match('/^[\x{0621}-\x{064A}\x{064B}-\x{0652}\s]+$/u', trim($singleName))) {
            $errors[] = ($lang === 'en') ? 'Client name must be written in Arabic characters only.' : 'يجب كتابة اسم العميل بالحروف العربية فقط.';
            break;
        }
    }
}

// رقم الهاتف - التحقق الصارم بحسب الشروط (حد أقصى رقمين + منع التكرار)
if (empty($phoneCombined) || empty($phonesArr)) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'phone',
        'message' => ($lang === 'en') ? 'A valid contact phone number is required.' : 'رقم الهاتف والتواصل مطلوب وبصيغة صحيحة.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (count($phonesArr) > 2) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'phone',
        'message' => ($lang === 'en') ? 'Maximum 2 phone numbers allowed.' : 'يُسمح بإدخال رقمين هاتف فقط كحد أقصى.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (count($phonesArr) === 2 && $phonesArr[0] === $phonesArr[1]) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'phone',
        'message' => ($lang === 'en') ? 'Duplicate phone numbers detected.' : 'رقم الهاتف مكرر، يجب أن يكون الرقم الإضافي مختلفاً عن الرقم الأساسي.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
foreach ($phonesArr as $phItem) {
    if (!preg_match('/^\d+$/', $phItem)) {
        http_response_code(422);
        echo json_encode([
            'status'  => 'error',
            'field'   => 'phone',
            'message' => ($lang === 'en') ? "Phone number ({$phItem}) must contain digits only." : "رقم الهاتف ({$phItem}) يجب أن يحتوي على أرقام فقط."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // مصري: 20 متبوعة بـ 10 أو 11 أو 12 أو 15 و 8 أرقام (12 رقم إجمالي)
    if (strpos($phItem, '20') === 0 && strlen($phItem) <= 12) {
        if (!preg_match('/^20(10|11|12|15)\d{8}$/', $phItem)) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'field'   => 'phone',
                'message' => ($lang === 'en') 
                    ? "Invalid Egyptian phone number ({$phItem}). It must start with 10, 11, 12, or 15 and consist of 10 digits without leading 0." 
                    : "رقم الهاتف المصري ({$phItem}) غير صحيح. يجب أن يبدأ بـ 10 أو 11 أو 12 أو 15 ويتكون من 10 أرقام بدون كتابة صفر في البداية."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        // دولي: 8 إلى 16 رقماً
        if (strlen($phItem) < 8 || strlen($phItem) > 16) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'field'   => 'phone',
                'message' => ($lang === 'en') ? "International phone number ({$phItem}) length is invalid." : "طول رقم الهاتف الدولي ({$phItem}) غير صحيح."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// البريد الإلكتروني (حد أقصى 2 إيميل + منع التكرار)
if (empty($emailsArr)) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'email',
        'message' => ($lang === 'en') ? 'A valid email address for correspondence is required.' : 'يرجى إدخال بريد إلكتروني صحيح ومتاح للمراسلات الرسمية (example@domain.com).'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (count($emailsArr) > 2) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'email',
        'message' => ($lang === 'en') ? 'Maximum 2 email addresses allowed.' : 'يُسمح بإدخال إيميلين فقط كحد أقصى.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (count($emailsArr) === 2 && strtolower(trim($emailsArr[0])) === strtolower(trim($emailsArr[1]))) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'email',
        'message' => ($lang === 'en') ? 'Duplicate email addresses detected.' : 'البريد الإلكتروني مكرر، يرجى كتابة بريد إضافي مختلف.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
foreach ($emailsArr as $emItem) {
    if (!filter_var($emItem, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode([
            'status'  => 'error',
            'field'   => 'email',
            'message' => ($lang === 'en') ? "The email address ({$emItem}) is invalid." : "البريد الإلكتروني ({$emItem}) غير صحيح، يرجى كتابته بصيغة صحيحة."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// التحقق من رقم الهوية حسب نوع الوثيقة
if (empty($idNumbersArr)) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'idNumber',
        'message' => ($lang === 'en') ? 'National ID or Passport number is required.' : 'رقم البطاقة الشخصية أو جواز السفر مطلوب.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
foreach ($idNumbersArr as $idx => $idItem) {
    $itemType = $idTypesArr[$idx] ?? $idType;
    if ($itemType === 'both') {
        // دعم اختيار "كلاهما": قد يحتوي على رقم قومي ورقم جواز
        $cleanDigits = preg_replace('/[^\d]/', '', $idItem);
        $cleanAlnum = preg_replace('/[^A-Za-z0-9]/', '', $idItem);
        if (strlen($cleanDigits) < 14 && strlen($cleanAlnum) < 6) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'field'   => 'idNumber',
                'message' => ($lang === 'en') ? "Valid National ID or Passport number ({$idItem}) is required." : "رقم إثبات الهوية ({$idItem}) غير صحيح، يرجى كتابة الرقم القومي أو رقم جواز السفر بشكل سليم."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } elseif ($itemType === 'national_id') {
        // 14 رقماً بالضبط، أو رقم جواز سفر ساري (6-15) للعملاء
        if (!preg_match('/^\d{14}$/', $idItem) && !preg_match('/^[A-Za-z0-9]{6,15}$/', $idItem)) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'field'   => 'idNumber',
                'message' => ($lang === 'en') ? "National ID ({$idItem}) must be exactly 14 digits or a valid passport number." : "رقم البطاقة الشخصية ({$idItem}) يجب أن يتكون من 14 رقماً أو رقم جواز سفر سليم."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        // جواز السفر: 6 إلى 15 حرفاً ورقماً
        if (!preg_match('/^[A-Za-z0-9]{6,15}$/', $idItem) && !preg_match('/^\d{14}$/', $idItem)) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'field'   => 'idNumber',
                'message' => ($lang === 'en') ? "Passport number ({$idItem}) must be 6 to 15 alphanumeric characters." : "رقم جواز السفر ({$idItem}) يجب أن يتكون من 6 إلى 15 حرفاً ورقماً."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// العناوين
if (empty($id_address)) {
    $errors[] = ($lang === 'en') ? 'Address as registered in Personal ID is required.' : 'العنوان كما هو مسجل في إثبات الهوية الشخصية مطلوب.';
}
if (empty($correspondence_address)) {
    $errors[] = ($lang === 'en') ? 'Approved correspondence address is required.' : 'العنوان المعتمد بالتفصيل للمراسلات مطلوب.';
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

// ── التحقق من خطة السداد والنسبة (Payment Plan & Final Rate Validation) ──
$paymentPlans = [
    '7 Years'  => ['5% - 5%'],
    '8 Years'  => ['5% - 7%'],
    '9 Years'  => ['5% - 12%', '5% - 10%'],
    '10 Years' => ['10% - 10%', '5% - 10%']
];

$periodAliases = [
    '7 Years' => '7 Years', '7 سنوات' => '7 Years',
    '8 Years' => '8 Years', '8 سنوات' => '8 Years',
    '9 Years' => '9 Years', '9 سنوات' => '9 Years',
    '10 Years' => '10 Years', '10 سنوات' => '10 Years',
];

// ── جلب الوحدات الرسمية للعميل من قاعدة البيانات (Prepared Statements) للتحقق الصارم ──
$primaryIdNum = $idNumbersArr[0] ?? '';
$cleanCheckId = strtoupper(preg_replace('/[\s\-_–—]/u', '', $primaryIdNum));
$allowedUnitsMap = []; // code => ['area' => ..., 'floor' => ...]

if (!empty($cleanCheckId)) {
    // 1. فحص جدول customer_units في MySQL بواسطة Prepared Statement
    if (isset($pdo)) {
        try {
            $chkStmt = $pdo->prepare("SELECT unit_code, client_name, unit_area, unit_floor FROM customer_units WHERE national_id = :nid");
            $chkStmt->execute([':nid' => $cleanCheckId]);
            $dbRows = $chkStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($dbRows as $r) {
                $uc = trim($r['unit_code']);
                $uDetails = getUnitDetailsHelper($uc);
                $allowedUnitsMap[$uc] = [
                    'area'  => !empty($r['unit_area']) ? $r['unit_area'] : $uDetails['area'],
                    'floor' => !empty($r['unit_floor']) ? $r['unit_floor'] : $uDetails['floor']
                ];
            }
        } catch (Exception $e) {}
    }

    // 2. بديل آمن: customer_units_lookup.json إذا لم يتم العثور في الداتابيز
    if (empty($allowedUnitsMap)) {
        $lookupFile = __DIR__ . '/customer_units_lookup.json';
        if (file_exists($lookupFile)) {
            $jsonDb = json_decode(file_get_contents($lookupFile), true);
            if (isset($jsonDb[$cleanCheckId])) {
                foreach ($jsonDb[$cleanCheckId] as $entry) {
                    $uc = trim($entry['unit'] ?? '');
                    if ($uc) {
                        $uDetails = getUnitDetailsHelper($uc);
                        $allowedUnitsMap[$uc] = [
                            'area'  => $uDetails['area'],
                            'floor' => $uDetails['floor']
                        ];
                    }
                }
            }
        }
    }
}

// ── استخراج ومعالجة الوحدات المشمولة في العقد ──
$unitsList = [];

// أ. النظام الموحد الجديد: unit_codes[] مع units_included[]
if (!empty($data['unit_codes']) && is_array($data['unit_codes'])) {
    $rawUnitCodes  = $data['unit_codes'];
    $includedFlags = $data['units_included'] ?? [];
    $unitPaids     = $data['unit_paid_5_percent'] ?? [];
    $unitDates     = $data['unit_payment_dates'] ?? [];
    $unitPeriods   = $data['unit_payment_periods'] ?? [];
    $unitRates     = $data['unit_final_rates'] ?? [];
    $unitCheques   = $data['unit_cheques_counts'] ?? [];
    $unitNotes     = $data['unit_notes'] ?? [];
    $unitPrices    = $data['unit_prices'] ?? [];
    $unitMaints    = $data['unit_maintenances'] ?? [];

    foreach ($rawUnitCodes as $idx => $rawCode) {
        $cCode = cleanInput($rawCode);
        if (empty($cCode)) continue;

        // التحقق من حالة التضمين (هل الوحدة مشمولة في العقد؟)
        $isIncluded = true;
        if (isset($includedFlags[$idx])) {
            $val = $includedFlags[$idx];
            $isIncluded = ($val === '1' || $val === 1 || $val === true || $val === 'true' || $val === 'on');
        }
        if (!$isIncluded) {
            continue; // تم استبعاد هذه الوحدة بواسطة العميل
        }

        // التحقق الإلزامي: هل الوحدة مسجلة رسمياً لهذا الرقم القومي في الداتابيز؟
        if (!empty($allowedUnitsMap) && !isset($allowedUnitsMap[$cCode])) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'field'   => 'unit_code',
                'message' => ($lang === 'en')
                    ? "Unit ({$cCode}) is not registered to this National ID in the database."
                    : "عفواً، الوحدة ({$cCode}) غير مسجلة لهذا الرقم القومي في قاعدة البيانات."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // جلب المساحة والدور حصراً من السيرفر / الداتابيز وليس من الـ POST
        $officialArea = $allowedUnitsMap[$cCode]['area'] ?? getUnitDetailsHelper($cCode)['area'];
        $officialFloor = $allowedUnitsMap[$cCode]['floor'] ?? getUnitDetailsHelper($cCode)['floor'];

        $uPaid = cleanInput($unitPaids[$idx] ?? 'نعم');
        $uDate = cleanInput($unitDates[$idx] ?? date('Y-m-d'));
        $uPeriodRaw = cleanInput($unitPeriods[$idx] ?? '');
        $uPeriod = $periodAliases[$uPeriodRaw] ?? $uPeriodRaw;
        $uRate = cleanInput($unitRates[$idx] ?? '');
        $uCheque = cleanInput($unitCheques[$idx] ?? '');
        $uNote = cleanInput($unitNotes[$idx] ?? '');

        // استخراج إجمالي سعر الوحدة ووديعة الصيانة (توتال اليونت والصيانة)
        $uPriceRaw = cleanInput($unitPrices[$idx] ?? $data['unit_price'] ?? '');
        $uPrice = !empty($uPriceRaw) ? floatval(str_replace(',', '', $uPriceRaw)) : null;

        $uMaintRaw = cleanInput($unitMaints[$idx] ?? $data['maintenance_deposit'] ?? '');
        $uMaint = !empty($uMaintRaw) ? floatval(str_replace(',', '', $uMaintRaw)) : null;
        if ($uMaint === null && $uPrice !== null && $uPrice > 0) {
            $uMaint = round($uPrice * 0.10, 2);
        }

        if ($uPaid === 'لا') {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'field'   => 'unit_paid_5_percent',
                'message' => ($lang === 'en')
                    ? "Down payment is required for unit ({$cCode}) to proceed with contract registration."
                    : "عفواً، لا يمكن استكمال تسجيل الوحدة ({$cCode}) إلا بعد استكمال سداد قيمة المقدم الخاص بها."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (empty($uPeriod) || !isset($paymentPlans[$uPeriod])) {
            $errors[] = ($lang === 'en')
                ? "Payment period is required for unit ({$cCode})."
                : "يرجى تحديد فترة السداد للوحدة ({$cCode}).";
        }

        if (isset($paymentPlans[$uPeriod])) {
            $pAllowed = $paymentPlans[$uPeriod];
            if (count($pAllowed) === 1) {
                $uRate = $pAllowed[0];
            } elseif (empty($uRate) || !in_array($uRate, $pAllowed, true)) {
                $uRate = $pAllowed[0];
            }
        }

        if (empty($uCheque) && !empty($uPeriod)) {
            if (preg_match('/(\d+)/', $uPeriod, $ym)) {
                $uCheque = intval($ym[1]) * 4;
            }
        }

        $unitsList[] = [
            'code'                => $cCode,
            'period'              => $uPeriod,
            'price'               => $uPrice,
            'maintenance_deposit' => $uMaint,
            'rate'                => $uRate,
            'paid'                => $uPaid,
            'payment_date'        => ($uPaid === 'نعم') ? $uDate : null,
            'notes'               => !empty($uNote) ? $uNote : $notes,
            'area'                => $officialArea,
            'floor'               => $officialFloor,
            'cheques_count'       => $uCheque
        ];
    }
}

// ب. التوافق العكسي: إذا تم الإرسال بالنظام القديم unit_code + extra_unit_codes
if (empty($unitsList) && !empty($unit_code)) {
    $period = $periodAliases[$rawPeriod] ?? $rawPeriod;
    $finalRate = $data['final_rate'] ?? '';
    if (isset($paymentPlans[$period]) && count($paymentPlans[$period]) === 1) {
        $finalRate = $paymentPlans[$period][0];
    }

    $uDetails = getUnitDetailsHelper($unit_code);
    $unitsList[] = [
        'code'          => $unit_code,
        'period'        => $period,
        'price'         => $unit_price,
        'rate'          => $finalRate,
        'paid'          => $paid_5_percent,
        'payment_date'  => $payment_date,
        'notes'         => $notes,
        'area'          => $allowedUnitsMap[$unit_code]['area'] ?? $uDetails['area'],
        'floor'         => $allowedUnitsMap[$unit_code]['floor'] ?? $uDetails['floor'],
        'cheques_count' => $cheques_count
    ];

    if (!empty($data['extra_unit_codes']) && is_array($data['extra_unit_codes'])) {
        $extraPeriods = $data['extra_payment_periods'] ?? [];
        $extraPrices  = $data['extra_unit_prices'] ?? [];
        $extraRates   = $data['extra_final_rates'] ?? [];
        $extraNotes   = $data['extra_notes'] ?? [];
        $extraPaids   = $data['extra_paid_5_percent'] ?? [];
        $extraDates   = $data['extra_payment_dates'] ?? [];
        $extraAreas   = $data['extra_unit_areas'] ?? [];
        $extraFloors  = $data['extra_unit_floors'] ?? [];
        $extraCheques = $data['extra_cheques_counts'] ?? [];

        foreach ($data['extra_unit_codes'] as $idx => $extraCodeRaw) {
            $cleanExtraCode = cleanInput($extraCodeRaw);
            if (!empty($cleanExtraCode)) {
                $ePeriodRaw = cleanInput($extraPeriods[$idx] ?? '') ?: $rawPeriod;
                $ePeriod = $periodAliases[$ePeriodRaw] ?? $ePeriodRaw;
                if (empty($ePeriod) || !isset($paymentPlans[$ePeriod])) {
                    $ePeriod = $period;
                }
                $ePrice  = !empty($extraPrices[$idx]) ? floatval(str_replace(',', '', $extraPrices[$idx])) : null;
                $eRate   = cleanInput($extraRates[$idx] ?? '');
                $eNote   = cleanInput($extraNotes[$idx] ?? '');
                $ePaid   = cleanInput($extraPaids[$idx] ?? '') ?: $paid_5_percent;
                $eDate   = cleanInput($extraDates[$idx] ?? '') ?: $payment_date;
                $eCheque = cleanInput($extraCheques[$idx] ?? '');

                if (empty($eCheque) && !empty($ePeriod)) {
                    if (preg_match('/(\d+)/', $ePeriod, $ym)) {
                        $eCheque = intval($ym[1]) * 4;
                    }
                }

                if ($ePaid === 'لا') {
                    http_response_code(422);
                    echo json_encode([
                        'status'  => 'error',
                        'field'   => 'extra_paid_5_percent',
                        'message' => ($lang === 'en')
                            ? "Registration cannot proceed for extra unit ({$cleanExtraCode}) without completing its down payment."
                            : "عفواً، لا يمكن استكمال تسجيل الوحدة الإضافية ({$cleanExtraCode}) إلا بعد استكمال سداد قيمة المقدم."
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $uDetailsExtra = getUnitDetailsHelper($cleanExtraCode);
                $unitsList[] = [
                    'code'          => $cleanExtraCode,
                    'period'        => $ePeriod,
                    'price'         => $ePrice,
                    'rate'          => $eRate ?: $finalRate,
                    'paid'          => $ePaid,
                    'payment_date'  => ($ePaid === 'نعم') ? $eDate : null,
                    'notes'         => !empty($eNote) ? $eNote : $notes,
                    'area'          => $allowedUnitsMap[$cleanExtraCode]['area'] ?? $uDetailsExtra['area'],
                    'floor'         => $allowedUnitsMap[$cleanExtraCode]['floor'] ?? $uDetailsExtra['floor'],
                    'cheques_count' => $eCheque ?: $cheques_count
                ];
            }
        }
    }
}

// التحقق من وجود وحدة واحدة على الأقل مشمولة في العقد
if (empty($unitsList)) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'unit_codes',
        'message' => ($lang === 'en')
            ? 'At least one unit must be included in the contract to proceed.'
            : 'يجب تضمين وحدة واحدة على الأقل في العقد لإتمام التعاقد.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// مزامنة المتغيرات الرئيسية للتوافق الكامل
$unit_code       = $unitsList[0]['code'];
$unit_area       = $unitsList[0]['area'];
$unit_floor      = $unitsList[0]['floor'];
$payment_period  = $unitsList[0]['period'];
$period          = $unitsList[0]['period'];
$finalRate       = $unitsList[0]['rate'];
$paid_5_percent  = $unitsList[0]['paid'];
$payment_date    = $unitsList[0]['payment_date'];
$cheques_count   = $unitsList[0]['cheques_count'];

// تاريخ الدفع إذا تم استكمال المقدم
if ($paid_5_percent === 'نعم' && empty($payment_date)) {
    $errors[] = ($lang === 'en') ? 'Payment date is required when down payment is completed.' : 'يرجى تحديد تاريخ استكمال سداد المقدم.';
}

// مكان توثيق العقد للعملاء داخل مصر (القاهرة أو الإسكندرية صافية)
if ($residence_country === 'داخل جمهورية مصر العربية' && empty($contract_location)) {
    $errors[] = ($lang === 'en') ? 'Please select the contract signing location (Cairo or Alexandria).' : 'يرجى تحديد المكان الأقرب لتوثيق العقد (القاهرة أو الإسكندرية).';
}

// ── معالجة المرفقات ──
$uploadedFiles = [];
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

if (!empty($_FILES['attachments'])) {
    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'bmp', 'tiff'];
    $fileCount = is_array($_FILES['attachments']['name']) ? count($_FILES['attachments']['name']) : 1;
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = is_array($_FILES['attachments']['name']) ? $_FILES['attachments']['name'][$i] : $_FILES['attachments']['name'];
        $tmpName  = is_array($_FILES['attachments']['tmp_name']) ? $_FILES['attachments']['tmp_name'][$i] : $_FILES['attachments']['tmp_name'];
        $fileSize = is_array($_FILES['attachments']['size']) ? $_FILES['attachments']['size'][$i] : $_FILES['attachments']['size'];
        $fileErr  = is_array($_FILES['attachments']['error']) ? $_FILES['attachments']['error'][$i] : $_FILES['attachments']['error'];

        if ($fileErr === UPLOAD_ERR_OK && is_uploaded_file($tmpName)) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExts) && $fileSize <= 25 * 1024 * 1024) {
                $cleanBaseName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
                $newFileName = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $cleanBaseName . '.' . $ext;
                $targetPath = $uploadDir . $newFileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $uploadedFiles[] = 'uploads/' . $newFileName;
                }
            }
        }
    }
}

// التحقق من رفع المرفقات الإلزامية
if (count($uploadedFiles) === 0) {
    if ($residence_country === 'خارج جمهورية مصر العربية') {
        $errors[] = ($lang === 'en') 
            ? 'Mandatory documents required: Please attach your valid passport and residence permit.' 
            : 'المستندات المطلوبة إلزامية: يُرجى رفع صورة جواز السفر الساري + الإقامة.';
    } else {
        $errors[] = ($lang === 'en') 
            ? 'Mandatory documents required: Please attach your National ID card (both sides).' 
            : 'المستندات المطلوبة إلزامية: يُرجى رفع صورة بطاقة الرقم القومي (الوجهين).';
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'errors' => $errors,
        'message' => implode(' | ', $errors)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$attachmentsJson = json_encode($uploadedFiles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// ── الحفظ في قاعدة البيانات ──
try {
    // 1. التحقق من عدم تكرار كود الوحدة داخل نفس الاستمارة
    $seenCodes = [];
    foreach ($unitsList as $u) {
        $uUpper = strtoupper($u['code']);
        if (isset($seenCodes[$uUpper])) {
            http_response_code(409);
            $duplicateMsg = ($lang === 'en')
                ? "Duplicate unit code within the form: {$u['code']}."
                : "تم تكرار كود الوحدة ({$u['code']}) أكثر من مرة في نفس الاستمارة.";
            echo json_encode([
                'status'  => 'error',
                'field'   => 'unitCode',
                'message' => $duplicateMsg
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $seenCodes[$uUpper] = true;
    }

    // 1. التحقق من منع إعادة تسجيل العميل لنفس الرقم القومي منعاً للتكرار
    $checkIdStmt = $pdo->prepare("SELECT id, name, eoi_number, created_at FROM clients WHERE id_number = :id_number LIMIT 1");
    $checkIdStmt->execute([':id_number' => $id_number]);
    $existingClient = $checkIdStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingClient) {
        http_response_code(409);
        $dupMsg = ($lang === 'en')
            ? "Sorry, this form has already been registered and submitted previously. Duplicate submissions are not allowed."
            : "عفواً، تم تسجيل وإرسال هذا النموذج مسبقاً ولا يمكن تكرار الإرسال.";
        echo json_encode([
            'status'  => 'error',
            'field'   => 'idNumber',
            'message' => $dupMsg
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. التحقق من عدم تكرار أي كود وحدة مسجل مسبقاً في قاعدة البيانات
    $checkStmt = $pdo->prepare("SELECT id FROM clients WHERE eoi_number = :eoi_number LIMIT 1");
    foreach ($unitsList as $u) {
        $checkStmt->execute([':eoi_number' => $u['code']]);
        if ($checkStmt->fetch()) {
            http_response_code(409);
            $duplicateMsg = ($lang === 'en')
                ? "The Unit Code ({$u['code']}) is already registered. Please enter a different unit code."
                : "عفواً، كود الوحدة ({$u['code']}) مسجل مسبقاً، يرجى التأكد وإدخال كود آخر.";
            echo json_encode([
                'status'  => 'error',
                'field'   => 'unitCode',
                'message' => $duplicateMsg
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // 3. الحفظ داخل Transaction لضمان حفظ كل الوحدات بأمان تام
    $pdo->beginTransaction();

    $sql = "INSERT INTO clients 
            (name, phone, email, id_type, id_number, residence_country, governorate, source, project, contract_location, preferred_signing_location, unit_type, eoi_number, unit_area, unit_floor, unit_price, maintenance_deposit, payment_period, period, final_rate, cheques_count, paid_5_percent, payment_date, id_address, correspondence_address, correspondence_country, correspondence_city, addresses, general_notes, notes, attachments, lang, sales_id) 
            VALUES 
            (:name, :phone, :email, :id_type, :id_number, :residence_country, :governorate, :source, :project, :contract_location, :preferred_signing_location, :unit_type, :eoi_number, :unit_area, :unit_floor, :unit_price, :maintenance_deposit, :payment_period, :period, :final_rate, :cheques_count, :paid_5_percent, :payment_date, :id_address, :correspondence_address, :correspondence_country, :correspondence_city, :addresses, :general_notes, :notes, :attachments, :lang, :sales_id)";
            
    $stmt = $pdo->prepare($sql);
    $insertedIds = [];
    $allSavedCodes = [];

    foreach ($unitsList as $u) {
        $uPeriod      = $u['period'] ?? $period;
        $uRate        = $u['rate'] ?? $finalRate;
        $uNotes       = $u['notes'] ?? $notes;
        $uPaid        = $u['paid'] ?? $paid_5_percent;
        $uPaymentDate = $u['payment_date'] ?? $payment_date;
        $uArea        = $u['area'] ?? $unit_area;
        $uFloor       = $u['floor'] ?? $unit_floor;
        $uCheques     = $u['cheques_count'] ?? $cheques_count;
        $uPrice       = $u['price'] ?? null;
        $uMaint       = $u['maintenance_deposit'] ?? null;

        if (!empty($uMaint) && strpos($uNotes, 'وديعة الصيانة') === false) {
            $uNotes = trim($uNotes . "\nوديعة الصيانة: " . number_format($uMaint) . ' EGP');
        }

        $stmt->execute([
            ':name'                       => $nameCombined,
            ':phone'                      => $phoneCombined,
            ':email'                      => $email,
            ':id_type'                    => $id_type,
            ':id_number'                  => $id_number,
            ':residence_country'          => $residence_country,
            ':governorate'                => $governorate,
            ':source'                     => $source,
            ':project'                    => $project,
            ':contract_location'          => $contract_location,
            ':preferred_signing_location' => $preferred_signing_location,
            ':unit_type'                  => '',
            ':eoi_number'                 => $u['code'],
            ':unit_area'                  => $uArea,
            ':unit_floor'                 => $uFloor,
            ':unit_price'                 => $uPrice,
            ':maintenance_deposit'        => $uMaint,
            ':payment_period'             => $uPeriod,
            ':period'                     => $uPeriod,
            ':final_rate'                 => $uRate,
            ':cheques_count'              => $uCheques,
            ':paid_5_percent'             => $uPaid,
            ':payment_date'               => $uPaymentDate,
            ':id_address'                 => $id_address,
            ':correspondence_address'     => $correspondence_address,
            ':correspondence_country'     => $correspondence_country,
            ':correspondence_city'        => $correspondence_city,
            ':addresses'                  => $addressCombined,
            ':general_notes'              => $general_notes,
            ':notes'                      => $uNotes,
            ':attachments'                => $attachmentsJson,
            ':lang'                       => $lang,
            ':sales_id'                   => $sales_id
        ]);
        $insertedIds[] = (int)$pdo->lastInsertId();
        $allSavedCodes[] = $u['code'];
    }

    $pdo->commit();

    echo json_encode([
        'status'     => 'success',
        'message'    => 'تم استيفاء وتسجيل بيانات العقد والوحدات بنجاح',
        'client_id'  => $insertedIds[0] ?? null,
        'client_ids' => $insertedIds,
        'unit_codes' => implode(' ، ', $allSavedCodes)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
        http_response_code(409);
        $duplicateMsg = ($lang === 'en')
            ? "One of the Unit Codes is already registered."
            : "عفواً، أحد أكواد الوحدات مسجل مسبقاً.";
        echo json_encode([
            'status'  => 'error',
            'field'   => 'unitCode',
            'message' => $duplicateMsg
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    error_log("Insert Failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'حدث خطأ داخلي أثناء حفظ البيانات، يرجى إعادة المحاولة.'
    ], JSON_UNESCAPED_UNICODE);
}
