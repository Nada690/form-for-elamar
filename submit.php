<?php
/**
 * submit.php - Secure Data Ingestion Endpoint for Shores Coastal Projects
 * يدعم كود الدولة للهواتف المتعددة، البريد الإلكتروني الحصري لـ Gmail، الشركاء، العناوين، والمرفقات
 */
// CORS Headers (تسمح لفرونت إند Vercel بالتواصل مع الباك إند بسلاسة)
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

require_once __DIR__ . '/db.php';

function cleanInput($val) {
    if (!isset($val)) return '';
    return trim(htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'));
}

$rawInput = file_get_contents('php://input');
$rawInput = preg_replace('/^\xEF\xBB\xBF/', '', $rawInput);
$jsonData = json_decode($rawInput, true);

$data = (!empty($_POST)) ? $_POST : (is_array($jsonData) ? $jsonData : []);

// 1. أطراف العقد (الاسم كما هو بالعقد + الشركاء)
$namesArr = [];
if (!empty($data['names']) && is_array($data['names'])) {
    foreach ($data['names'] as $n) {
        $c = cleanInput($n);
        if (!empty($c)) $namesArr[] = $c;
    }
} elseif (!empty($data['name'])) {
    $namesArr[] = cleanInput($data['name']);
}
$nameCombined = implode(' ، ', $namesArr);

// 2. الهواتف مع كود الدولة
$phonesArr = [];
if (!empty($data['phone_numbers']) && is_array($data['phone_numbers'])) {
    $codes = $data['phone_codes'] ?? [];
    foreach ($data['phone_numbers'] as $idx => $num) {
        $cleanNum = cleanInput($num);
        if (!empty($cleanNum)) {
            $code = cleanInput($codes[$idx] ?? '+20');
            // إزالة الصفر الأول إن وجد إذا أضيف بعد كود الدولة
            $cleanNumFormatted = ltrim($cleanNum, '0');
            $phonesArr[] = $code . ' ' . $cleanNumFormatted;
        }
    }
} elseif (!empty($data['phones']) && is_array($data['phones'])) {
    foreach ($data['phones'] as $p) {
        $c = cleanInput($p);
        if (!empty($c)) $phonesArr[] = $c;
    }
} elseif (!empty($data['phone'])) {
    $phonesArr[] = cleanInput($data['phone']);
}
$phoneCombined = implode(' ، ', $phonesArr);

// 3. البريد الإلكتروني حصرياً Gmail
$email = cleanInput($data['email'] ?? '');

// 4. إثبات الهوية
$id_type = cleanInput($data['id_type'] ?? 'national_id');
$id_number = cleanInput($data['id_number'] ?? '');

// 5. العناوين
$addressesArr = [];
if (!empty($data['addresses']) && is_array($data['addresses'])) {
    foreach ($data['addresses'] as $addr) {
        $c = cleanInput($addr);
        if (!empty($c)) $addressesArr[] = $c;
    }
} elseif (!empty($data['address'])) {
$addressCombined = implode(" \n---\n ", $addressesArr);

// 5.5. ملاحظات إضافية (الحد الأقصى 250 حرف)
$notes = cleanInput($data['notes'] ?? '');
$maxNotesLength = 250;
if (mb_strlen($notes) > $maxNotesLength) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'notes',
        'message' => ($lang === 'en') 
            ? "Notes cannot exceed {$maxNotesLength} characters." 
            : "الملاحظات لا يمكن أن تتجاوز {$maxNotesLength} حرفاً."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 6. تفاصيل المشروع (Shores Only)
$project = cleanInput($data['project'] ?? 'شورز — Shores (الساحل الشمالي)');
if (empty($project)) {
    $project = 'شورز — Shores (الساحل الشمالي)';
}
$unit_code = cleanInput($data['unit_code'] ?? $data['eoi'] ?? $data['eoi_number'] ?? '');
$contract_location = cleanInput($data['contract_location'] ?? '');
$payment_period = cleanInput($data['payment_period'] ?? '');
$paid_5_percent = cleanInput($data['paid_5_percent'] ?? '');
$lang = cleanInput($data['lang'] ?? 'ar');
$governorate = cleanInput($data['governorate'] ?? $contract_location ?: 'الساحل');
$source = cleanInput($data['source'] ?? 'مباشر');

// 7. Sales ID (من الفورم إذا كان مرتبط بموظف Sales)
$sales_id = null;
if (!empty($data['sales_id'])) {
    $sales_id = (int)$data['sales_id'];
    // التحقق من أن sales_id صحيح وموجود
    try {
        $checkSales = $pdo->prepare("SELECT id FROM sales_employees WHERE id = :id AND active = 1 LIMIT 1");
        $checkSales->execute([':id' => $sales_id]);
        if (!$checkSales->fetch()) {
            $sales_id = null; // إذا لم يكن Sales موجوداً أو غير نشط
        }
    } catch (PDOException $e) {
        $sales_id = null;
    }
}

// ── التحقق من صحة البيانات ──
$errors = [];

if (empty($nameCombined) || mb_strlen($nameCombined) < 2) {
    $errors[] = ($lang === 'en') ? 'Client name as in contract is required.' : 'اسم العميل كما هو مدون في العقد مطلوب.';
}

if (empty($phoneCombined)) {
    $errors[] = ($lang === 'en') ? 'At least one valid phone number is required.' : 'رقم الهاتف مطلوب.';
}

// فحص أن البريد حصرياً Gmail
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/i', $email)) {
    http_response_code(422);
    echo json_encode([
        'status'  => 'error',
        'field'   => 'email',
        'message' => ($lang === 'en') ? 'A valid Gmail address (@gmail.com) is required.' : 'البريد الإلكتروني مخصص حصرياً لـ Gmail ويجب أن ينتهي بـ @gmail.com'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($id_number)) {
    $errors[] = ($lang === 'en') ? 'National ID or Passport number is required.' : 'إثبات الهوية (الرقم القومي أو جواز السفر) مطلوب.';
}

if (empty($unit_code)) {
    $errors[] = ($lang === 'en') ? 'Unit code is required (e.g. nn-13).' : 'كود الوحدة مطلوب (مثال: nn-13).';
}

if (empty($contract_location)) {
    $errors[] = ($lang === 'en') ? 'Contract location (Cairo or Alexandria) is required.' : 'مكان التعاقد (القاهرة أو الإسكندرية) مطلوب.';
}

if (empty($payment_period)) {
    $errors[] = ($lang === 'en') ? 'Payment period is required.' : 'يرجى تحديد فترة السداد.';
}

if (empty($paid_5_percent)) {
    $errors[] = ($lang === 'en') ? 'Please answer if the 5% has been paid.' : 'يرجى تحديد ما إذا كان تم دفع نسبة الـ 5%.';
}

if (empty($addressCombined)) {
    $errors[] = ($lang === 'en') ? 'Detailed client address is required.' : 'عنوان العميل بالتفصيل مطلوب.';
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

// ── معالجة رفع المرفقات المتعددة (PDF وكافة صيغ الصور) ──
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
$attachmentsJson = json_encode($uploadedFiles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// ── الحفظ في قاعدة البيانات ──
try {
    // التحقق من كود الوحدة
    $checkStmt = $pdo->prepare("SELECT id FROM clients WHERE eoi_number = :eoi_number LIMIT 1");
    $checkStmt->execute([':eoi_number' => $unit_code]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        $duplicateMsg = ($lang === 'en')
            ? "The Unit Code ($unit_code) is already registered. Please enter a different unit code."
            : "عفواً، كود الوحدة ($unit_code) مسجل مسبقاً، يرجى التأكد وإدخال كود آخر.";
        echo json_encode([
            'status'  => 'error',
            'field'   => 'unitCode',
            'message' => $duplicateMsg
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "INSERT INTO clients 
            (name, phone, email, id_type, id_number, governorate, source, project, contract_location, unit_type, eoi_number, payment_period, paid_5_percent, addresses, notes, attachments, lang, sales_id) 
            VALUES 
            (:name, :phone, :email, :id_type, :id_number, :governorate, :source, :project, :contract_location, :unit_type, :eoi_number, :payment_period, :paid_5_percent, :addresses, :notes, :attachments, :lang, :sales_id)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name'              => $nameCombined,
        ':phone'             => $phoneCombined,
        ':email'             => $email,
        ':id_type'           => $id_type,
        ':id_number'         => $id_number,
        ':governorate'       => $governorate,
        ':source'            => $source,
        ':project'           => $project,
        ':contract_location' => $contract_location,
        ':unit_type'         => '',
        ':eoi_number'        => $unit_code,
        ':payment_period'    => $payment_period,
        ':paid_5_percent'    => $paid_5_percent,
        ':addresses'         => $addressCombined,
        ':notes'             => $notes,
        ':attachments'       => $attachmentsJson,
        ':lang'              => $lang,
        ':sales_id'          => $sales_id
    ]);

    $clientId = $pdo->lastInsertId();

    echo json_encode([
        'status'    => 'success',
        'message'   => 'تم استيفاء وتسجيل بيانات العميل والوحدة بنجاح',
        'client_id' => $clientId
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
        http_response_code(409);
        $duplicateMsg = ($lang === 'en')
            ? "The Unit Code ($unit_code) is already registered."
            : "عفواً، كود الوحدة ($unit_code) مسجل مسبقاً.";
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
