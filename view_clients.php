<?php
/**
 * view_clients.php - لوحة القيادة التنفيذية وإدارة عقود شورز الساحلية
 * نظام متكامل لإدارة ومراجعة وتعديل بيانات العملاء وتتبع موظفي المبيعات (Sales Tracking)
 * دعم كامل للغتين العربية والإنجليزية مع تبديل فوري عبر JavaScript، وميزات متقدمة
 */

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// تحديد اللغة
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar','en'])) {
    $_SESSION['admin_lang'] = $_GET['lang'];
}
$adminLang = $_SESSION['admin_lang'] ?? 'ar';

require_once __DIR__ . '/db.php';

// تسجيل الخروج
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: view_clients.php");
    exit;
}

// ── تسجيل الدخول الموحد (Unified RBAC Authentication) ──
$authError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    $inputEmail = trim($_POST['login_email'] ?? '');
    $inputPassword = (string)$_POST['login_password'];

    if (empty($inputEmail) || empty($inputPassword)) {
        $authError = ($adminLang === 'en') ? 'Please provide both email and password.' : 'يرجى إدخال البريد الإلكتروني وكلمة المرور.';
    } else {
        try {
            // 1. فحص جدول المستخدمين users أولاً (Email / Password)
            $userStmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $userStmt->execute([':email' => $inputEmail]);
            $user = $userStmt->fetch();

            if ($user && password_verify($inputPassword, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['auth_user_id']        = (int)$user['id'];
                $_SESSION['auth_user_name']      = $user['name'];
                $_SESSION['auth_user_email']     = $user['email'];
                $_SESSION['auth_user_role']      = $user['role']; // 'Admin', 'Manager', 'Sales'
                $_SESSION['auth_user_ref']       = $user['referral_code'];
                $_SESSION['auth_manager_id']     = $user['manager_id'];
                $_SESSION['is_authorized_admin'] = ($user['role'] === 'Admin');
                $_SESSION['admin_role']          = strtolower($user['role']);
                $_SESSION['admin_user']          = $user['name'];
                $_SESSION['admin_logged_time']   = time();
                header("Location: view_clients.php");
                exit;
            }

            // 2. فحص جدول admins للتوافق مع بيانات الأدمن القديمة (username أو email)
            $adminStmt = $pdo->prepare("SELECT * FROM admins WHERE username = :u LIMIT 1");
            $adminStmt->execute([':u' => $inputEmail]);
            $adminUser = $adminStmt->fetch();

            if ($adminUser && password_verify($inputPassword, $adminUser['password'])) {
                // مزامنة هذا الأدمن مع جدول users إن لم يكن موجوداً
                $checkUsr = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $checkUsr->execute([':email' => 'admin@elamar.com']);
                $existU = $checkUsr->fetch();
                $uid = $existU ? $existU['id'] : 1;

                session_regenerate_id(true);
                $_SESSION['auth_user_id']        = (int)$uid;
                $_SESSION['auth_user_name']      = $adminUser['username'];
                $_SESSION['auth_user_email']     = 'admin@elamar.com';
                $_SESSION['auth_user_role']      = 'Admin';
                $_SESSION['auth_user_ref']       = 'admin';
                $_SESSION['auth_manager_id']     = null;
                $_SESSION['is_authorized_admin'] = true;
                $_SESSION['admin_role']          = 'admin';
                $_SESSION['admin_user']          = $adminUser['username'];
                $_SESSION['admin_logged_time']   = time();
                header("Location: view_clients.php");
                exit;
            }

            // إذا فشل التحقق
            $authError = ($adminLang === 'en') 
                ? 'Invalid email or password. Please check your credentials.' 
                : 'البريد الإلكتروني أو كلمة المرور غير صحيحة. يرجى التحقق وإعادة المحاولة.';

        } catch (PDOException $e) {
            $authError = ($adminLang === 'en') ? 'Database verification failed: ' . $e->getMessage() : 'حدث خطأ أثناء التحقق من بيانات الدخول.';
        }
    }
}

// التحقق من تسجيل الدخول النشط
$isAuthenticated = !empty($_SESSION['auth_user_id']) || (!empty($_SESSION['is_authorized_admin']) && $_SESSION['is_authorized_admin'] === true);
$currentUserRole = $_SESSION['auth_user_role'] ?? (($_SESSION['admin_role'] ?? '') === 'admin' ? 'Admin' : 'Sales');
$currentUserId   = (int)($_SESSION['auth_user_id'] ?? 1);
$currentUserName = $_SESSION['auth_user_name'] ?? ($_SESSION['admin_user'] ?? 'المستخدم');
$currentUserRef  = $_SESSION['auth_user_ref'] ?? 'ref';

// ═══════════════════════════════════════════════════════════════
// صفحة تسجيل الدخول (إذا لم يكن مسجلاً)
// ═══════════════════════════════════════════════════════════════
if (!$isAuthenticated):
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تسجيل دخول المسؤول | مجموعة العمار</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
    body {
      background-color: #dbeafe !important;
      background-image: 
        radial-gradient(at 0% 0%, #bae6fd 0px, transparent 55%),
        radial-gradient(at 100% 0%, #bfdbfe 0px, transparent 50%),
        radial-gradient(at 50% 100%, #e0f2fe 0px, transparent 65%) !important;
      background-attachment: fixed !important;
      color: #000000 !important;
      font-weight: 700 !important;
    }
    html[dir="rtl"] { font-family: 'Cairo', sans-serif; }
    html[dir="ltr"] { font-family: 'Plus Jakarta Sans', sans-serif; }
    h1, h2, h3, h4, h5, h6, label, p, span, input, select, textarea, button, a, div {
      font-weight: 700;
    }
    label {
      color: #000000 !important;
      font-weight: 800 !important;
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden text-slate-950">
  
  <div class="fixed top-1/4 right-1/4 w-96 h-96 bg-sky-400/25 rounded-full blur-3xl pointer-events-none"></div>
  <div class="fixed bottom-1/4 left-1/4 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl pointer-events-none"></div>

  <div class="w-full max-w-md bg-white/95 border-2 border-sky-300 rounded-3xl p-8 shadow-2xl text-center relative overflow-hidden backdrop-blur-2xl z-10">
    <div class="h-2 bg-gradient-to-r from-sky-400 via-blue-500 to-sky-400 absolute top-0 left-0 right-0"></div>

    <div class="flex justify-end mb-2">
      <button onclick="toggleLoginLang()" class="text-xs font-black text-slate-950 hover:bg-sky-100 px-3 py-1 rounded-xl bg-white border-2 border-sky-300 shadow-xs cursor-pointer">
        🌐 <span id="loginLangBtnText">English</span>
      </button>
    </div>

    <div class="inline-block p-1.5 rounded-2xl bg-gradient-to-tr from-sky-400 via-blue-400 to-sky-200 mb-4 shadow-lg border border-sky-300">
      <div class="bg-white p-2.5 rounded-xl">
        <img src="logo.png" alt="Logo" class="h-12 w-auto mx-auto object-contain">
      </div>
    </div>

    <h2 class="text-2xl font-black text-slate-950 mb-1" id="loginHeading">
      بوابة تسجيل الدخول الموحدة
    </h2>
    <p class="text-xs text-sky-950 font-black mb-6" id="loginSubheading">
      منطقة آمنة لدخول موظفي المبيعات والمديرين والإدارة المركزية
    </p>

    <?php if ($authError): ?>
      <div class="bg-red-50 border-2 border-red-300 text-red-700 text-xs p-3 rounded-2xl mb-4 font-black text-right shadow-xs" id="loginErrorBox">
        ⚠️ <?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="view_clients.php" class="space-y-4">
      <div class="text-right" id="loginFormContainer">
        
        <label class="block text-xs font-black text-slate-950 mb-1.5" id="lblUsername">
          البريد الإلكتروني (Email):
        </label>
        <input 
          type="email" 
          name="login_email" 
          value="<?= htmlspecialchars($_POST['login_email'] ?? '') ?>"
          placeholder="example@elamar.com" 
          required 
          class="w-full px-4 py-3 rounded-xl bg-white border-2 border-sky-300 text-slate-950 placeholder-slate-400 text-sm focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-400/30 transition-all font-bold mb-3 dir-ltr text-right"
        >

        <label class="block text-xs font-black text-slate-950 mb-1.5" id="lblPassword">
          كلمة المرور:
        </label>
        <div class="relative">
          <input 
            type="password" 
            name="login_password" 
            id="adminPwdInput" 
            placeholder="••••••••" 
            required 
            autofocus
            class="w-full px-4 py-3 rounded-xl bg-white border-2 border-sky-300 text-slate-950 placeholder-slate-400 text-sm focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-400/30 transition-all font-mono font-bold"
          >
          <button 
            type="button" 
            onclick="const p = document.getElementById('adminPwdInput'); p.type = p.type === 'password' ? 'text' : 'password';" 
            class="absolute left-3 top-3.5 text-xs text-slate-500 hover:text-slate-950 cursor-pointer font-bold"
          >
            👁️
          </button>
        </div>
      </div>

      <button 
        type="submit" 
        id="loginSubmitBtn"
        class="w-full py-3.5 rounded-xl bg-gradient-to-r from-sky-400 to-blue-500 hover:from-sky-500 hover:to-blue-600 text-slate-950 font-black text-sm shadow-xl hover:shadow-sky-500/25 transition-all cursor-pointer mt-2 border-2 border-sky-400"
      >
        دخول لوحة التحكم
      </button>
    </form>

    <div class="mt-6 pt-5 border-t-2 border-sky-200 flex justify-center text-xs text-slate-700 font-bold">
      <a href="form.php" class="hover:text-sky-950 hover:underline transition-colors" id="loginBackForm">
        ← العودة لاستمارة التعاقد
      </a>
    </div>

  </div>

  <script>
    let isAr = true;
    function toggleLoginLang() {
      isAr = !isAr;
      const html = document.documentElement;
      html.dir = isAr ? 'rtl' : 'ltr';
      document.getElementById('loginLangBtnText').innerText = isAr ? 'English' : 'العربية';
      document.getElementById('loginHeading').innerText = isAr ? 'بوابة تسجيل الدخول الموحدة' : 'Unified Staff Portal';
      document.getElementById('loginSubheading').innerText = isAr ? 'منطقة آمنة لدخول موظفي المبيعات والمديرين والإدارة المركزية' : 'Secure Access for Sales Executives, Managers & Admins';
      document.getElementById('lblUsername').innerText = isAr ? 'البريد الإلكتروني (Email):' : 'Official Email Address:';
      document.getElementById('lblPassword').innerText = isAr ? 'كلمة المرور:' : 'Password:';
      document.getElementById('loginSubmitBtn').innerText = isAr ? 'دخول لوحة التحكم' : 'Sign In to Dashboard';
      document.getElementById('loginBackForm').innerText = isAr ? '← العودة لاستمارة التعاقد' : '← Back to Survey Form';
      document.getElementById('loginFormContainer').className = isAr ? 'text-right' : 'text-left';
    }
  </script>

</body>
</html>
<?php
    exit;
endif;

// ═══════════════════════════════════════════════════════════════
// منطقة المسؤول المصرح بها (Authorized Admin Dashboard)
// ═══════════════════════════════════════════════════════════════

header('Content-Type: text/html; charset=utf-8');

$notification = '';
$notificationType = 'success';

// دالة أمان للتحقق من صلاحية الوصول لسجل عميل معين
function checkClientAccess($pdo, $clientId, $userId, $userRole) {
    if ($userRole === 'Admin') return true;
    if ($userRole === 'Sales') {
        $st = $pdo->prepare("SELECT id FROM clients WHERE id = :cid AND sales_id = :uid LIMIT 1");
        $st->execute([':cid' => $clientId, ':uid' => $userId]);
        return (bool)$st->fetch();
    }
    if ($userRole === 'Manager') {
        $st = $pdo->prepare("SELECT c.id FROM clients c 
                             JOIN users u ON c.sales_id = u.id 
                             WHERE c.id = :cid AND u.manager_id = :mid LIMIT 1");
        $st->execute([':cid' => $clientId, ':mid' => $userId]);
        return (bool)$st->fetch();
    }
    return false;
}

// ── إجراء الحذف (Delete Action) مع الحماية الصارمة ──
if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['client_id'])) {
    $delId = (int)$_POST['client_id'];
    
    // التحقق من الصلاحية
    if (!checkClientAccess($pdo, $delId, $currentUserId, $currentUserRole)) {
        $notification = "غير مصرح لك بحذف هذا العميل، لا يقع ضمن صلاحياتك.";
        $notificationType = 'error';
    } else {
        try {
            $fileStmt = $pdo->prepare("SELECT attachments FROM clients WHERE id = :id");
            $fileStmt->execute([':id' => $delId]);
            $row = $fileStmt->fetch();
            if ($row && !empty($row['attachments'])) {
                $files = json_decode($row['attachments'], true);
                if (is_array($files)) {
                    foreach ($files as $f) {
                        $realPath = __DIR__ . '/' . $f;
                        if (file_exists($realPath) && is_file($realPath)) {
                            @unlink($realPath);
                        }
                    }
                }
            }

            $delStmt = $pdo->prepare("DELETE FROM clients WHERE id = :id");
            $delStmt->execute([':id' => $delId]);
            $notification = "تم حذف سجل العميل رقم #$delId بنجاح.";
        } catch (PDOException $e) {
            $notification = "فشل الحذف: " . $e->getMessage();
            $notificationType = 'error';
        }
    }
}

// ── إجراء التعديل (Edit Action) مع الحماية الصارمة ──
if (isset($_POST['action']) && $_POST['action'] === 'edit' && !empty($_POST['client_id'])) {
    $editId = (int)$_POST['client_id'];

    if (!checkClientAccess($pdo, $editId, $currentUserId, $currentUserRole)) {
        $notification = "غير مصرح لك بتعديل هذا العميل، لا يقع ضمن صلاحياتك.";
        $notificationType = 'error';
    } else {
        $editName          = trim($_POST['edit_name'] ?? '');
        $editPhone         = trim($_POST['edit_phone'] ?? '');
        $editEmail         = trim($_POST['edit_email'] ?? '');
        $editIdType        = trim($_POST['edit_id_type'] ?? 'national_id');
        $editIdNumber      = trim($_POST['edit_id_number'] ?? '');
        $editResidence     = trim($_POST['edit_residence_country'] ?? 'داخل جمهورية مصر العربية');
        $editUnitCode      = trim($_POST['edit_unit_code'] ?? '');
        $editUnitPrice     = !empty($_POST['edit_unit_price']) ? floatval(str_replace(',', '', $_POST['edit_unit_price'])) : null;
        $editMaintenance   = !empty($_POST['edit_maintenance_deposit']) ? floatval(str_replace(',', '', $_POST['edit_maintenance_deposit'])) : null;
        $editLocation      = trim($_POST['edit_contract_location'] ?? 'القاهرة');
        $editPayment       = trim($_POST['edit_payment_period'] ?? '7 Years');
        $editFinalRate     = trim($_POST['edit_final_rate'] ?? '');
        $editChequesCount  = trim($_POST['edit_cheques_count'] ?? '');
        $editPaid5         = trim($_POST['edit_paid_5_percent'] ?? 'لا');
        $editPaymentDate   = !empty($_POST['edit_payment_date']) ? trim($_POST['edit_payment_date']) : null;
        $editIdAddress     = trim($_POST['edit_id_address'] ?? '');
        $editCorrAddress   = trim($_POST['edit_correspondence_address'] ?? '');
        $editAddress       = trim($_POST['edit_addresses'] ?? '');
        $editGeneralNotes  = trim($_POST['edit_general_notes'] ?? '');
        $editNotes         = trim($_POST['edit_notes'] ?? '');
        $editSalesId       = !empty($_POST['edit_sales_id']) ? (int)$_POST['edit_sales_id'] : null;

        // لموظف Sales، يظل العميل دائماً تابعاً لحسابه
        if ($currentUserRole === 'Sales') {
            $editSalesId = $currentUserId;
        }

        if (empty($editAddress) && (!empty($editIdAddress) || !empty($editCorrAddress))) {
            $editAddress = "العنوان في الهوية: " . $editIdAddress . " \n---\n عنوان المراسلات: " . $editCorrAddress;
        }

    try {
        $chkStmt = $pdo->prepare("SELECT id FROM clients WHERE eoi_number = :code AND id != :id LIMIT 1");
        $chkStmt->execute([':code' => $editUnitCode, ':id' => $editId]);
        if ($chkStmt->fetch()) {
            $notification = "خطأ: كود الوحدة ($editUnitCode) مسجل بالفعل لعميل آخر.";
            $notificationType = 'error';
        } else {
            $currStmt = $pdo->prepare("SELECT attachments FROM clients WHERE id = :id");
            $currStmt->execute([':id' => $editId]);
            $currFiles = json_decode($currStmt->fetch()['attachments'] ?? '[]', true);
            if (!is_array($currFiles)) $currFiles = [];

            if (!empty($_POST['delete_attachments']) && is_array($_POST['delete_attachments'])) {
                foreach ($_POST['delete_attachments'] as $delFile) {
                    $key = array_search($delFile, $currFiles);
                    if ($key !== false) {
                        unset($currFiles[$key]);
                        $filePathOnDisk = __DIR__ . '/' . $delFile;
                        if (file_exists($filePathOnDisk) && is_file($filePathOnDisk)) {
                            @unlink($filePathOnDisk);
                        }
                    }
                }
                $currFiles = array_values($currFiles);
            }

            if (!empty($_FILES['new_attachments'])) {
                $uploadDir = __DIR__ . '/uploads/';
                $fileCount = is_array($_FILES['new_attachments']['name']) ? count($_FILES['new_attachments']['name']) : 1;
                for ($i = 0; $i < $fileCount; $i++) {
                    $fileName = is_array($_FILES['new_attachments']['name']) ? $_FILES['new_attachments']['name'][$i] : $_FILES['new_attachments']['name'];
                    $tmpName  = is_array($_FILES['new_attachments']['tmp_name']) ? $_FILES['new_attachments']['tmp_name'][$i] : $_FILES['new_attachments']['tmp_name'];
                    $fileErr  = is_array($_FILES['new_attachments']['error']) ? $_FILES['new_attachments']['error'][$i] : $_FILES['new_attachments']['error'];

                    if ($fileErr === UPLOAD_ERR_OK && is_uploaded_file($tmpName)) {
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $allowed = ['jpg','jpeg','png','webp','pdf'];
                        if (in_array($ext, $allowed)) {
                            $newFileName = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
                                $currFiles[] = 'uploads/' . $newFileName;
                            }
                        }
                    }
                }
            }

            $finalAttachmentsJson = json_encode(array_values($currFiles), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $updSql = "UPDATE clients SET 
                        name = :name, 
                        phone = :phone, 
                        email = :email, 
                        id_type = :id_type, 
                        id_number = :id_number, 
                        residence_country = :residence_country,
                        contract_location = :contract_location, 
                        eoi_number = :unit_code, 
                        unit_price = :unit_price,
                        maintenance_deposit = :maintenance_deposit,
                        payment_period = :payment_period, 
                        period = :period,
                        final_rate = :final_rate,
                        cheques_count = :cheques_count,
                        paid_5_percent = :paid_5_percent, 
                        payment_date = :payment_date,
                        id_address = :id_address,
                        correspondence_address = :correspondence_address,
                        addresses = :addresses,
                        general_notes = :general_notes,
                        notes = :notes,
                        attachments = :attachments,
                        sales_id = :sales_id
                       WHERE id = :id";
            $updStmt = $pdo->prepare($updSql);
            $updStmt->execute([
                ':name'                   => $editName,
                ':phone'                  => $editPhone,
                ':email'                  => $editEmail,
                ':id_type'                => $editIdType,
                ':id_number'              => $editIdNumber,
                ':residence_country'      => $editResidence,
                ':contract_location'      => $editLocation,
                ':unit_code'              => $editUnitCode,
                ':unit_price'             => $editUnitPrice,
                ':maintenance_deposit'    => $editMaintenance,
                ':payment_period'         => $editPayment,
                ':period'                 => $editPayment,
                ':final_rate'             => $editFinalRate,
                ':cheques_count'          => $editChequesCount,
                ':paid_5_percent'         => $editPaid5,
                ':payment_date'           => $editPaymentDate,
                ':id_address'             => $editIdAddress,
                ':correspondence_address' => $editCorrAddress,
                ':addresses'              => $editAddress,
                ':general_notes'          => $editGeneralNotes,
                ':notes'                  => $editNotes,
                ':attachments'            => $finalAttachmentsJson,
                ':sales_id'               => $editSalesId,
                ':id'                     => $editId
            ]);
            $notification = "تم تحديث وحفظ بيانات العقد رقم #$editId بنجاح.";
        }
    } catch (PDOException $e) {
        $notification = "فشل التحديث: " . $e->getMessage();
        $notificationType = 'error';
    }
  } // إغلاق else الخاصة بـ checkClientAccess
}

// ── دالة تقسيم الشركاء المتعددين للوحدة الواحدة ──
if (!function_exists('splitPartnerValues')) {
    function splitPartnerValues($val): array {
        if (empty($val)) return [];
        $normalized = str_replace(["\r\n", "\r", "\n", '،'], ',', (string)$val);
        $items = array_map('trim', explode(',', $normalized));
        return array_values(array_filter($items, fn($x) => $x !== ''));
    }
}

// ── دالة جلب العملاء الآخرين في نفس الوحدة والمشروع ──
if (!function_exists('getOtherClientsInSameUnit')) {
    /**
     * جلب كافة العملاء الآخرين المسجلين بنفس كود الوحدة مع مقارنة المشروع واستبعاد العميل المعروض
     * 
     * @param PDO $pdo كائن الاتصال بقاعدة البيانات
     * @param int $currentClientId معرف العميل الحالي لاستبعاده
     * @param string $unitCode كود الوحدة
     * @param string $project اسم المشروع لمطابقته ومنع تشابه الأكواد بين المشاريع المختلفة
     * @param array|null $prefetchedMap خريطة بيانات مسبقة التجهيز (Batch Prefetch) لتفادي استعلامات N+1
     * @return array مصفوفة العملاء الآخرين المسجلين في نفس الوحدة
     */
    function getOtherClientsInSameUnit(PDO $pdo, int $currentClientId, string $unitCode, string $project = '', ?array &$prefetchedMap = null): array {
        $unitCode = trim((string)$unitCode);
        if ($unitCode === '') {
            return [];
        }

        $projectClean = trim((string)$project);
        $mapKey = mb_strtolower($unitCode, 'UTF-8') . '|' . mb_strtolower($projectClean, 'UTF-8');
        $mapFallbackKey = mb_strtolower($unitCode, 'UTF-8');

        // 1. استخدام الخريطة المجهزة مسبقاً دفعة واحدة لتحقيق أعلى أداء وسرعة
        if ($prefetchedMap !== null) {
            $candidates = $prefetchedMap[$mapKey] ?? ($prefetchedMap[$mapFallbackKey] ?? []);
            $result = [];
            $seenIds = [];
            foreach ($candidates as $c) {
                $cId = (int)($c['id'] ?? 0);
                if ($cId === $currentClientId || isset($seenIds[$cId])) {
                    continue; // استبعاد العميل نفسه والتكرار
                }
                // مقارنة المشروع/العمارة لمنع ظهور عملاء من مشروع آخر بنفس كود الوحدة
                $cProj = trim((string)($c['project'] ?? ''));
                if ($projectClean !== '' && $cProj !== '' && mb_strtolower($projectClean, 'UTF-8') !== mb_strtolower($cProj, 'UTF-8')) {
                    continue;
                }
                $seenIds[$cId] = true;
                $result[] = $c;
            }
            return $result;
        }

        // 2. استعلام PDO Prepared Statement مباشر عند الاستدعاء الفردي
        try {
            $sql = "SELECT c.id, c.name, c.id_number, c.project, c.eoi_number 
                    FROM clients c 
                    WHERE c.eoi_number = :eoi 
                      AND c.id != :current_id";
            
            $params = [
                ':eoi'        => $unitCode,
                ':current_id' => $currentClientId
            ];

            if ($projectClean !== '') {
                $sql .= " AND (c.project = :proj OR c.project IS NULL OR c.project = '')";
                $params[':proj'] = $projectClean;
            }

            $sql .= " ORDER BY c.id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getOtherClientsInSameUnit: " . $e->getMessage());
            return [];
        }
    }
}

// ── تصدير CSV مع مراعاة الصلاحيات وتفكيك الشركاء إلى صفوف مستقلة ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=shores_contracts_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, [
        'رقم العقد (ID)', 
        'كود الوحدة', 
        'قيمة الوحدة (EGP)',
        'وديعة الصيانة (EGP)',
        'موظف المبيعات (Sales)', 
        'اسم العميل في البطاقة / الشركاء', 
        'مكان الإقامة الحالي',
        'رقم الهاتف والتواصل', 
        'البريد الإلكتروني', 
        'نوع الهوية', 
        'رقم الوثيقة', 
        'مكان توثيق العقد', 
        'فترة السداد', 
        'استكمال المقدم', 
        'تاريخ دفع المقدم',
        'العنوان في الهوية الشخصية', 
        'العنوان للمراسلات', 
        'ملاحظات عامة',
        'خطة بديلة واستثناءات', 
        'تاريخ التسجيل'
    ]);
    
    $exportWhere = [];
    $exportParams = [];
    if ($currentUserRole === 'Sales') {
        $exportWhere[] = "c.sales_id = :mysid";
        $exportParams[':mysid'] = $currentUserId;
    } elseif ($currentUserRole === 'Manager') {
        $exportWhere[] = "c.sales_id IN (SELECT id FROM users WHERE manager_id = :mymid)";
        $exportParams[':mymid'] = $currentUserId;
    }

    $exportSql = "SELECT 
        c.id, 
        c.eoi_number, 
        IFNULL(c.unit_price, '-') as unit_price,
        IFNULL(c.maintenance_deposit, '-') as maintenance_deposit,
        IFNULL(u.name, 'مباشر (بدون وسيط)') as sales_rep_name, 
        c.name, 
        IFNULL(c.residence_country, 'داخل جمهورية مصر العربية') as residence_country,
        c.phone, 
        c.email, 
        c.id_type, 
        c.id_number, 
        c.contract_location, 
        c.payment_period, 
        c.paid_5_percent, 
        IFNULL(c.payment_date, '-') as payment_date,
        IFNULL(c.id_address, c.addresses) as id_address, 
        IFNULL(c.correspondence_address, '-') as correspondence_address, 
        IFNULL(c.general_notes, '-') as general_notes,
        IFNULL(c.notes, '-') as notes, 
        c.created_at 
        FROM clients c 
        LEFT JOIN users u ON c.sales_id = u.id";
        
    if (!empty($exportWhere)) {
        $exportSql .= " WHERE " . implode(" AND ", $exportWhere);
    }
    $exportSql .= " ORDER BY c.id DESC";
        
    $exportStmt = $pdo->prepare($exportSql);
    $exportStmt->execute($exportParams);
        
    while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        $names   = splitPartnerValues((string)$row['name']);
        $phones  = splitPartnerValues((string)$row['phone']);
        $emails  = splitPartnerValues((string)($row['email'] ?? ''));
        $idNums  = splitPartnerValues((string)($row['id_number'] ?? ''));

        $pCount = max(count($names), count($phones), count($emails), 1);

        for ($i = 0; $i < $pCount; $i++) {
            $pName  = $names[$i] ?? ($names[0] ?? $row['name']);
            $pPhone = $phones[$i] ?? ($phones[0] ?? $row['phone']);
            $pEmail = $emails[$i] ?? ($emails[0] ?? $row['email']);
            $pIdNum = $idNums[$i] ?? ($idNums[0] ?? $row['id_number']);

            $idLabel = ($pCount > 1) ? $row['id'] . ' (شريك ' . ($i + 1) . ')' : $row['id'];
            
            // منع الاختصار العلمي (3E+13) في الإكسيل للأرقام الكبيرة والهواتف بصيغة نص صريح
            $cleanPhone = preg_replace('/\s+/', '', (string)$pPhone);
            $excelPhone = !empty($cleanPhone) ? '="' . $cleanPhone . '"' : '-';

            $cleanIdNum = preg_replace('/\s+/', '', (string)$pIdNum);
            $excelIdNum = !empty($cleanIdNum) ? '="' . $cleanIdNum . '"' : '-';

            // تصحيح التاريخ ليظهر بصيغة YYYY-MM-DD بدلاً من علامات ########
            $excelDate = !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : '-';

            fputcsv($output, [
                $idLabel,
                $row['eoi_number'],
                $row['unit_price'],
                $row['maintenance_deposit'],
                $row['sales_rep_name'],
                $pName,
                $row['residence_country'],
                $excelPhone,
                $pEmail,
                $row['id_type'],
                $excelIdNum,
                $row['contract_location'],
                $row['payment_period'],
                $row['paid_5_percent'],
                $row['payment_date'],
                $row['id_address'],
                $row['correspondence_address'],
                $row['general_notes'],
                $row['notes'],
                $excelDate
            ]);
        }
    }
    fclose($output);
    exit;
}

// ── استعلامات لوحة الإحصائيات وجدول العملاء وفق الصلاحيات الهرمية ──
try {
    // 1. تحديد قائمة السيلز المسموح بعرضها في الفلتر حسب الرتبة
    $salesList = [];
    if ($currentUserRole === 'Admin') {
        // الأدمن يرى جميع مسؤولي المبيعات
        $salesList = $pdo->query("SELECT id, name, referral_code, manager_id FROM users WHERE role = 'Sales' ORDER BY name ASC")->fetchAll();
    } elseif ($currentUserRole === 'Manager') {
        // المدير يرى موظفي فريقه فقط (الذين يشير manager_id لهم إليه)
        $smStmt = $pdo->prepare("SELECT id, name, referral_code, manager_id FROM users WHERE manager_id = :mid AND role = 'Sales' ORDER BY name ASC");
        $smStmt->execute([':mid' => $currentUserId]);
        $salesList = $smStmt->fetchAll();
    }

    // فلترة السيلز أو الفرع
    $filterSalesId = isset($_GET['sales_id']) && $_GET['sales_id'] !== '' ? (int)$_GET['sales_id'] : null;
    $filterLoc     = trim($_GET['location'] ?? '');

    $whereClauses = [];
    $params = [];

    // تطبيق فلتر الصلاحيات الصارم الأساسي (Mandatory RBAC Scoping)
    if ($currentUserRole === 'Sales') {
        // موظف المبيعات: يرى عملاءه فقط
        $whereClauses[] = "c.sales_id = :rbac_sales_id";
        $params[':rbac_sales_id'] = $currentUserId;
    } elseif ($currentUserRole === 'Manager') {
        // المدير: عملاء فريقه التابعين له فقط
        if ($filterSalesId !== null) {
            // تحقق من أن السيلز المحدد يتبع هذا المدير فعلاً لمنع التلاعب
            $validSalesIds = array_column($salesList, 'id');
            if (in_array($filterSalesId, $validSalesIds)) {
                $whereClauses[] = "c.sales_id = :chosen_sid";
                $params[':chosen_sid'] = $filterSalesId;
            } else {
                // إذا حاول التلاعب بـ ID موظف ليس في فريقه، نحصر البحث بفريقه فقط
                $whereClauses[] = "c.sales_id IN (SELECT id FROM users WHERE manager_id = :rbac_mgr_id)";
                $params[':rbac_mgr_id'] = $currentUserId;
            }
        } else {
            $whereClauses[] = "c.sales_id IN (SELECT id FROM users WHERE manager_id = :rbac_mgr_id)";
            $params[':rbac_mgr_id'] = $currentUserId;
        }
    } else {
        // الأدمن (Admin): صلاحية كاملة لرؤية الجميع مع إمكانية الفلترة
        if ($filterSalesId !== null) {
            $whereClauses[] = "c.sales_id = :chosen_sid";
            $params[':chosen_sid'] = $filterSalesId;
        }
    }

    if (!empty($filterLoc)) {
        $whereClauses[] = "c.contract_location = :loc";
        $params[':loc'] = $filterLoc;
    }

    $whereSql = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

    // إجمالي العملاء المصرح برؤيتهم
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM clients c" . $whereSql);
    $cntStmt->execute($params);
    $totalClients = $cntStmt->fetchColumn();

    // إحصائيات الدفع في النطاق المصرح به
    $paidStmt = $pdo->prepare("SELECT c.paid_5_percent, COUNT(*) as count FROM clients c" . $whereSql . " GROUP BY c.paid_5_percent");
    $paidStmt->execute($params);
    $paidStats = $paidStmt->fetchAll();

    // إحصائيات الفروع في النطاق المصرح به
    $locStmt = $pdo->prepare("SELECT c.contract_location, COUNT(*) as count FROM clients c" . $whereSql . " GROUP BY c.contract_location");
    $locStmt->execute($params);
    $locStats = $locStmt->fetchAll();

    // جلب جدول العملاء بالبيانات واسم السيلز من جدول users
    $sql = "SELECT c.*, u.name as sales_name, u.referral_code as sales_slug, u.role as sales_role 
            FROM clients c 
            LEFT JOIN users u ON c.sales_id = u.id" . $whereSql . " ORDER BY c.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

    // ── تجهيز خريطة العملاء المشتركين في نفس الوحدات مسبقاً (Single Batch Prefetch) لتفادي استعلامات N+1 ──
    $unitsOtherClientsMap = [];
    $allEoiNumbers = array_values(array_filter(array_unique(array_map('trim', array_column($clients, 'eoi_number')))));

    if (!empty($allEoiNumbers)) {
        $placeholders = implode(',', array_fill(0, count($allEoiNumbers), '?'));
        // جلب جميع العملاء المسجلين بهذه الوحدات (الاسم والرقم القومي والمشروع) بغض النظر عن السيلز
        // وفق قرار الصلاحيات المعتمد مع الإدارة
        $batchStmt = $pdo->prepare("
            SELECT c.id, c.name, c.id_number, c.project, c.eoi_number 
            FROM clients c 
            WHERE c.eoi_number IN ($placeholders)
            ORDER BY c.id ASC
        ");
        $batchStmt->execute($allEoiNumbers);
        $allBatchRows = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allBatchRows as $bRow) {
            $bUnit = trim((string)$bRow['eoi_number']);
            $bProj = trim((string)($bRow['project'] ?? ''));
            $kFull = mb_strtolower($bUnit, 'UTF-8') . '|' . mb_strtolower($bProj, 'UTF-8');
            $kUnitOnly = mb_strtolower($bUnit, 'UTF-8');

            $unitsOtherClientsMap[$kFull][] = $bRow;
            if ($kFull !== $kUnitOnly) {
                $unitsOtherClientsMap[$kUnitOnly][] = $bRow;
            }
        }
    }

} catch (PDOException $e) {
    die("خطأ في جلب تقارير العملاء: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>لوحة التحكم وإدارة عقود شورز الساحلية | مجموعة العمار</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    * { 
      transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    body {
      background-color: #dbeafe !important;
      background-image: 
        radial-gradient(at 0% 0%, #bae6fd 0px, transparent 55%),
        radial-gradient(at 100% 0%, #bfdbfe 0px, transparent 50%),
        radial-gradient(at 50% 100%, #e0f2fe 0px, transparent 65%) !important;
      background-attachment: fixed !important;
      color: #000000 !important;
      font-weight: 700 !important;
    }
    html[dir="rtl"] { font-family: 'Cairo', sans-serif; }
    html[dir="ltr"] { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'Plus Jakarta Sans', monospace; }
    
    h1, h2, h3, h4, h5, h6, label, p, span, input, select, textarea, button, a, div, td, th {
      font-weight: 700;
    }
    label {
      color: #000000 !important;
      font-weight: 800 !important;
    }

    #clientsTable, #clientsTable th, #clientsTable td, #clientsTable tr,
    #clientsTable span, #clientsTable div, #clientsTable a, #clientsTable button, #clientsTable p {
      color: #000000 !important;
      font-weight: 800 !important;
    }

    /* Sticky Table Header & Modern Alternating Zebra Striping */
    #tableScrollContainer {
      overflow-x: auto;
      overflow-y: auto;
      max-height: 76vh;
      position: relative;
      border-radius: 1.25rem;
    }
    #clientsTable {
      border-collapse: separate !important;
      border-spacing: 0 !important;
      width: 100%;
    }
    #clientsTable thead th {
      position: sticky !important;
      top: 0 !important;
      z-index: 20 !important;
      background-color: #dbeafe !important;
      border-bottom: 2.5px solid #0284c7 !important;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
    }
    #clientsTable tbody tr:nth-child(even) {
      background-color: #ffffff !important;
    }
    #clientsTable tbody tr:nth-child(odd) {
      background-color: #f8fafc !important;
    }
    #clientsTable tbody tr:hover {
      background-color: #e0f2fe !important;
      transition: background-color 0.15s ease-in-out;
    }
    #clientsTable td {
      border-bottom: 1px solid #e2e8f0;
      vertical-align: middle;
    }

    .glass-card {
      background: rgba(255, 255, 255, 0.96) !important;
      backdrop-filter: blur(28px) !important;
      -webkit-backdrop-filter: blur(28px) !important;
      border: 2px solid #7dd3fc !important;
      box-shadow: 0 20px 50px -10px rgba(2, 132, 199, 0.15), 0 8px 24px rgba(0, 0, 0, 0.04) !important;
    }

    .glass-input, input, select, textarea {
      background: #ffffff !important;
      border: 2px solid #7dd3fc !important;
      color: #000000 !important;
      font-weight: 700 !important;
    }
    .glass-input:focus, input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: #0284c7 !important;
      box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.35) !important;
    }

    /* =====================================================================
       PRINT STYLES: FULL TABLE EXCLUSIVE SHEET (LANDSCAPE MODE)
       ===================================================================== */
    @page {
      size: landscape;
      margin: 6mm 6mm 8mm 6mm;
    }

    @media print {
      /* Force pure clean white background and black text */
      *, *::before, *::after {
        box-shadow: none !important;
        text-shadow: none !important;
      }

      html, body {
        background: #ffffff !important;
        background-color: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        min-width: 0 !important;
        font-size: 7.5pt !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      /* Hide all non-printable UI elements */
      .ambient-light,
      header,
      #kpiStatsGrid,
      #searchFilterCard,
      #editModal,
      #deleteClientModal,
      #attachmentLightbox,
      .no-print,
      .col-actions,
      th.col-actions,
      td.col-actions,
      #tableScreenHeader {
        display: none !important;
      }

      /* Reset card / wrapper layouts so table is 100% full width and completely visible */
      .max-w-\[1700px\],
      .glass-card,
      .overflow-hidden,
      .overflow-x-auto,
      div.space-y-6,
      div.space-y-6 > * {
        all: unset !important;
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        overflow: visible !important;
      }

      /* Dedicated Print Header Sheet */
      .print-header-sheet {
        display: block !important;
        width: 100% !important;
        margin-bottom: 8px !important;
        padding-bottom: 6px !important;
        border-bottom: 2px solid #0f172a !important;
      }

      /* Table layout */
      #clientsTable {
        width: 100% !important;
        max-width: 100% !important;
        border-collapse: collapse !important;
        table-layout: fixed !important;
        font-size: 7pt !important;
        line-height: 1.2 !important;
        color: #000000 !important;
        margin: 0 !important;
      }

      #clientsTable thead {
        display: table-header-group !important;
      }

      #clientsTable tr {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
      }

      #clientsTable th,
      #clientsTable td {
        border: 1px solid #475569 !important;
        padding: 3px 2px !important;
        vertical-align: middle !important;
        color: #000000 !important;
        font-weight: 700 !important;
        background-color: #ffffff !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
        white-space: normal !important;
      }

      #clientsTable thead th {
        background-color: #e2e8f0 !important;
        color: #000000 !important;
        font-weight: 900 !important;
        font-size: 7.5pt !important;
        text-align: center !important;
        padding: 4px 2px !important;
        border: 1.5px solid #0f172a !important;
      }

      /* Precise column widths in landscape printing for all 18 printable columns */
      #clientsTable th.col-seq          { width: 2.5% !important; text-align: center !important; }
      #clientsTable th.col-id           { width: 3.0% !important; text-align: center !important; }
      #clientsTable th.col-sales        { width: 5.0% !important; text-align: center !important; }
      #clientsTable th.col-unit         { width: 5.5% !important; text-align: center !important; }
      #clientsTable th.col-residence    { width: 5.0% !important; text-align: center !important; }
      #clientsTable th.col-branch       { width: 5.0% !important; text-align: center !important; }
      #clientsTable th.col-signing-loc  { width: 5.5% !important; text-align: center !important; }
      #clientsTable th.col-name         { width: 11.0% !important; text-align: right !important; }
      #clientsTable th.col-phone        { width: 6.5% !important; text-align: center !important; }
      #clientsTable th.col-email        { width: 6.5% !important; text-align: center !important; }
      #clientsTable th.col-doc          { width: 6.5% !important; text-align: center !important; }
      #clientsTable th.col-plan         { width: 4.5% !important; text-align: center !important; }
      #clientsTable th.col-october      { width: 5.5% !important; text-align: center !important; }
      #clientsTable th.col-id-address   { width: 9.0% !important; text-align: right !important; }
      #clientsTable th.col-corr-address { width: 9.0% !important; text-align: right !important; }
      #clientsTable th.col-notes        { width: 6.0% !important; text-align: right !important; }
      #clientsTable th.col-files        { width: 3.5% !important; text-align: center !important; }
      #clientsTable th.col-date         { width: 5.5% !important; text-align: center !important; }

      /* Remove badge backgrounds, borders, and shadows in print for crisp clean output */
      #clientsTable span,
      #clientsTable div,
      #clientsTable a,
      #clientsTable button {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        font-size: inherit !important;
        color: #000000 !important;
        font-weight: 700 !important;
        display: inline !important;
        text-decoration: none !important;
      }

      /* Specific text alignments */
      #clientsTable td.col-seq,
      #clientsTable td.col-unit,
      #clientsTable td.col-contract-loc,
      #clientsTable td.col-plan,
      #clientsTable td.col-payment-status,
      #clientsTable td.col-residence,
      #clientsTable td.col-date,
      #clientsTable td.col-total-price {
        text-align: center !important;
      }

      #clientsTable td.col-name,
      #clientsTable td.col-id-address,
      #clientsTable td.col-corr-address {
        text-align: right !important;
      }

      /* Block elements that need to break to next line inside cells */
      #clientsTable .print-block {
        display: block !important;
      }

      /* Remove ellipsis on truncated elements so full text is printed */
      #clientsTable .truncate {
        overflow: visible !important;
        text-overflow: clip !important;
        white-space: normal !important;
        max-width: none !important;
      }

      /* Hide interactive buttons in print */
      .wa-icon,
      .copy-btn,
      .file-preview-btn {
        display: none !important;
      }

      .file-print-text {
        display: inline !important;
      }
    }
  </style>
</head>
<body class="min-h-screen p-3 sm:p-6 lg:p-8 text-slate-950 selection:bg-sky-200 selection:text-slate-950">

  <!-- Ambient light -->
  <div class="ambient-light fixed top-0 right-10 w-96 h-96 bg-sky-400/25 rounded-full blur-3xl pointer-events-none no-print"></div>
  <div class="ambient-light fixed bottom-0 left-10 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl pointer-events-none no-print"></div>

  <div class="max-w-[1700px] mx-auto space-y-6 relative z-10">

    <!-- ═══════════════ TOP HEADER ═══════════════ -->
    <header class="glass-card rounded-3xl p-5 sm:p-6 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xl border-2 border-sky-300 no-print">
      
      <div class="flex items-center gap-4">
        <div class="p-2 rounded-2xl bg-gradient-to-tr from-sky-400/20 via-blue-400/10 to-transparent border-2 border-sky-300 shadow-md">
          <img src="logo.png" alt="Logo" class="h-12 w-auto object-contain">
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black <?= $currentUserRole === 'Admin' ? 'bg-amber-100 text-slate-950 border-2 border-amber-400' : ($currentUserRole === 'Manager' ? 'bg-sky-100 text-slate-950 border-2 border-sky-400' : 'bg-purple-100 text-slate-950 border-2 border-purple-400') ?>">
              <?= strtoupper($currentUserRole) ?> PANEL
            </span>
            <span class="text-xs text-slate-950 font-black bg-white px-2.5 py-1 rounded-xl border-2 border-sky-300 shadow-xs">
              👤 <?= htmlspecialchars($currentUserName) ?>
            </span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-950 mt-1" data-i18n="dashTitle">
            <?php if ($currentUserRole === 'Sales'): ?>
              لوحة تحكم مستشار المبيعات | عقود شورز
            <?php elseif ($currentUserRole === 'Manager'): ?>
              لوحة قيادة مدير القطاع | متابعة فريق المبيعات
            <?php else: ?>
              لوحة تحكم وإدارة عقود شورز الساحلية
            <?php endif; ?>
          </h1>
          <p class="text-xs text-slate-700 font-bold" data-i18n="dashSub">
            <?php if ($currentUserRole === 'Sales'): ?>
              متابعة واستعراض العملاء المسجلين تحت حسابك المباشر مع رابط الإحالة الخاص بك
            <?php elseif ($currentUserRole === 'Manager'): ?>
              إدارة ومتابعة استبيانات وعقود فريق موظفي المبيعات التابعين لقطاعك
            <?php else: ?>
              الإدارة المركزية الشاملة لكافة المديرين وموظفي المبيعات واستبيانات العملاء
            <?php endif; ?>
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
        
        <?php if ($currentUserRole === 'Sales'): ?>
          <!-- زر مخصص لموظف المبيعات: نسخ رابط الاستبيان الخاص به (?ref=CODE) -->
          <button 
            type="button" 
            onclick="copyMySurveyLink('<?= htmlspecialchars($currentUserRef) ?>')"
            class="px-4 py-2.5 bg-gradient-to-r from-sky-400 to-blue-500 hover:from-sky-500 hover:to-blue-600 text-slate-950 text-xs sm:text-sm font-black rounded-2xl transition-all shadow-lg border-2 border-sky-400 flex items-center gap-2 cursor-pointer"
          >
            <span>🔗</span>
            <span data-i18n="btnCopySurveyLink">نسخ رابط الاستبيان الخاص بي</span>
          </button>
        <?php endif; ?>

        <!-- Language Switcher Button -->
        <button 
          id="langToggleBtn" 
          onclick="toggleLanguage()" 
          class="px-3.5 py-2 bg-white hover:bg-sky-50 text-slate-950 text-xs sm:text-sm font-black rounded-2xl transition-all border-2 border-sky-300 flex items-center gap-1.5 cursor-pointer shadow-sm"
        >
          <span>🌐</span> <span id="langBtnText">English</span>
        </button>

        <?php if ($currentUserRole === 'Admin'): ?>
          <!-- Manage Sales & Users (Admin Only) -->
          <a href="manage_sales.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs sm:text-sm font-black rounded-2xl transition-all shadow-lg hover:shadow-purple-500/20 flex items-center gap-2">
            <span>👥</span>
            <span data-i18n="btnManageSales">إدارة مسؤولي المبيعات</span> (<?= count($salesList) ?>)
          </a>
        <?php endif; ?>

        <!-- Export CSV -->
        <a href="view_clients.php?export=csv" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs sm:text-sm font-bold rounded-2xl transition-all shadow-md flex items-center gap-1.5">
          <span>📥</span> <span data-i18n="btnExportCsv">تصدير Excel (CSV)</span>
        </a>

        <!-- Print -->
        <button onclick="window.print()" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs sm:text-sm font-bold rounded-2xl transition-all shadow-md flex items-center gap-1.5 cursor-pointer">
          <span>🖨️</span> <span data-i18n="btnPrint">طباعة</span>
        </button>

        <!-- New Form -->
        <a href="form.php<?= ($currentUserRole === 'Sales') ? '?ref=' . urlencode($currentUserRef) : '' ?>" target="_blank" class="px-3.5 py-2 bg-gradient-to-r from-sky-400 to-blue-500 hover:from-sky-500 hover:to-blue-600 text-slate-950 text-xs sm:text-sm font-black rounded-2xl transition-all shadow-md border-2 border-sky-400 flex items-center gap-1.5">
          <span>+</span> <span data-i18n="btnNewForm">استمارة التعاقد</span>
        </a>

        <!-- Logout -->
        <a href="view_clients.php?logout=1" class="px-3 py-2 bg-red-50 hover:bg-red-100 text-red-700 border-2 border-red-300 text-xs sm:text-sm font-black rounded-2xl transition-all shadow-xs" data-i18n="btnLogout">
          تسجيل خروج
        </a>
      </div>

    </header>

    <!-- ═══════════════ NOTIFICATION ═══════════════ -->
    <?php if ($notification): ?>
      <div class="p-4 rounded-2xl flex items-center justify-between gap-3 shadow-lg border no-print <?= $notificationType === 'error' ? 'bg-red-500/15 border-red-500/40 text-red-200' : 'bg-emerald-500/15 border-emerald-500/40 text-emerald-200' ?>">
        <div class="flex items-center gap-2 text-sm font-bold">
          <span><?= $notificationType === 'error' ? '⚠️' : '✅' ?></span>
          <span><?= htmlspecialchars($notification, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-xs opacity-70 hover:opacity-100 cursor-pointer">✕</button>
      </div>
    <?php endif; ?>

    <!-- ═══════════════ KPI STATS ═══════════════ -->
    <div id="kpiStatsGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 no-print">
      
      <!-- إجمالي العقود -->
      <div class="glass-card rounded-3xl p-5 border-2 border-sky-300 shadow-xl flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-sky-100 border-2 border-sky-300 flex items-center justify-center text-2xl text-sky-800 shadow-xs">
          📑
        </div>
        <div>
          <p class="text-xs text-slate-700 font-black uppercase tracking-wider" data-i18n="kpiTotal">إجمالي العقود المسجلة</p>
          <div class="flex items-baseline gap-2 mt-0.5">
            <span class="text-3xl font-black text-slate-950 count-up" data-target="<?= $totalClients ?>"><?= $totalClients ?></span>
            <span class="text-xs text-sky-950 font-black" data-i18n="kpiTotalSub">عقد معتمد</span>
          </div>
        </div>
      </div>

      <!-- حالة سداد مقدم أكتوبر -->
      <div class="glass-card rounded-3xl p-5 border-2 border-sky-300 shadow-xl flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-emerald-100 border-2 border-emerald-300 flex items-center justify-center text-2xl text-emerald-800 shadow-xs">
          💳
        </div>
        <div>
          <p class="text-xs text-slate-700 font-black uppercase tracking-wider" data-i18n="kpi5Percent">سداد مقدم أكتوبر</p>
          <div class="flex items-center gap-2 mt-1.5 flex-wrap">
            <?php foreach ($paidStats as $ps): ?>
              <span class="px-2.5 py-0.5 rounded-lg text-xs font-black text-slate-950 border-2 <?= ($ps['paid_5_percent'] === 'نعم') ? 'bg-emerald-100 border-emerald-400' : 'bg-red-100 border-red-300' ?>">
                <?= htmlspecialchars($ps['paid_5_percent'] ?: 'غير محدد') ?>: <?= $ps['count'] ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- توزيع الفروع -->
      <div class="glass-card rounded-3xl p-5 border-2 border-sky-300 shadow-xl flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-blue-100 border-2 border-blue-300 flex items-center justify-center text-2xl text-blue-800 shadow-xs">
          🏛️
        </div>
        <div>
          <p class="text-xs text-slate-700 font-black uppercase tracking-wider" data-i18n="kpiBranches">فروع ومكان التوثيق</p>
          <div class="flex items-center gap-2 mt-1.5 flex-wrap">
            <?php foreach ($locStats as $ls): ?>
              <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-sky-100 text-slate-950 border-2 border-sky-300 shadow-xs">
                <?= htmlspecialchars($ls['contract_location'] ?: 'غير محدد') ?>: <?= $ls['count'] ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- مسؤولو المبيعات -->
      <div class="glass-card rounded-3xl p-5 border-2 border-sky-300 shadow-xl flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-purple-100 border-2 border-purple-300 flex items-center justify-center text-2xl text-purple-800 shadow-xs">
          👥
        </div>
        <div>
          <p class="text-xs text-slate-700 font-black uppercase tracking-wider" data-i18n="kpiSalesTeam">فريق المبيعات</p>
          <div class="flex items-baseline gap-2 mt-0.5">
            <span class="text-3xl font-black text-slate-950 count-up" data-target="<?= count($salesList) ?>"><?= count($salesList) ?></span>
            <span class="text-xs text-slate-700 font-bold" data-i18n="kpiSalesTeamSub">موظف نشط</span>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══════════════ FILTERS & LIVE SEARCH TOOLBAR ═══════════════ -->
    <div id="searchFilterCard" class="glass-card rounded-3xl p-4 sm:p-5 border-2 border-sky-300 shadow-xl flex flex-col md:flex-row items-center justify-between gap-4 no-print">
      
      <!-- Live Search Box -->
      <div class="w-full md:w-96 relative">
        <input 
          type="text" 
          id="tableSearchInput" 
          placeholder="بحث سريع: اسم العميل، الهاتف، كود الوحدة، السيلز..." 
          data-i18n-placeholder="phSearchClients"
          oninput="liveFilterClients(this.value)"
          class="w-full pl-4 pr-10 py-3 rounded-2xl bg-white border-2 border-sky-300 text-slate-950 placeholder-slate-400 text-xs sm:text-sm focus:outline-none focus:border-sky-500 transition-all font-bold shadow-xs"
        >
        <span class="absolute right-3.5 top-3.5 text-slate-400 text-sm">🔍</span>
      </div>

      <!-- Dropdown Filters -->
      <form method="GET" action="view_clients.php" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
        
        <?php if ($currentUserRole !== 'Sales'): ?>
        <!-- Sales Filter (Manager / Admin View) -->
        <div class="flex items-center gap-1.5">
          <span class="text-xs text-slate-950 font-black" data-i18n="filterSalesLbl">
            <?= $currentUserRole === 'Manager' ? 'موظف السيلز (فريقك):' : 'السيلز:' ?>
          </span>
          <select 
            name="sales_id" 
            onchange="this.form.submit()" 
            class="px-3 py-2.5 bg-white border-2 border-sky-300 text-slate-950 text-xs font-black rounded-xl cursor-pointer focus:outline-none focus:border-sky-500 shadow-xs"
          >
            <option value="" data-i18n="optAllSales">
              <?= $currentUserRole === 'Manager' ? 'كل موظفي فريقي (عرض الكل)' : 'كل موظفي Sales (عرض الكل)' ?>
            </option>
            <?php foreach ($salesList as $s): ?>
              <option value="<?= $s['id'] ?>" <?= ($filterSalesId !== null && $filterSalesId == $s['id']) ? 'selected' : '' ?>>
                👤 <?= htmlspecialchars($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <!-- Branch Filter -->
        <div class="flex items-center gap-1.5">
          <span class="text-xs text-slate-950 font-black" data-i18n="filterBranchLbl">مكان التوثيق:</span>
          <select 
            name="location" 
            onchange="this.form.submit()" 
            class="px-3 py-2.5 bg-white border-2 border-sky-300 text-slate-950 text-xs font-black rounded-xl cursor-pointer focus:outline-none focus:border-sky-500 shadow-xs"
          >
            <option value="" data-i18n="optAllBranches">كل الأماكن</option>
            <option value="القاهرة" <?= ($filterLoc === 'القاهرة') ? 'selected' : '' ?> data-i18n="optCairoBranch">🏛️ القاهرة</option>
            <option value="الإسكندرية" <?= ($filterLoc === 'الإسكندرية') ? 'selected' : '' ?> data-i18n="optAlexBranch">🌊 الإسكندرية</option>
            <option value="خارج جمهورية مصر العربية" <?= ($filterLoc === 'خارج جمهورية مصر العربية') ? 'selected' : '' ?>>🌍 خارج مصر</option>
          </select>
        </div>

        <?php if ($filterSalesId !== null || !empty($filterLoc)): ?>
          <a href="view_clients.php" class="px-3 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-black rounded-xl border-2 border-red-300 shadow-xs" data-i18n="btnClearFilters">
            ✕ إلغاء الفلترة
          </a>
        <?php endif; ?>

      </form>

    </div>

    <!-- ═══════════════ MAIN CLIENTS TABLE ═══════════════ -->
    <div class="glass-card rounded-3xl border-2 border-sky-300 shadow-2xl overflow-hidden">
      
      <!-- Screen Header Bar -->
      <div id="tableScreenHeader" class="p-5 border-b-2 border-sky-200 flex justify-between items-center bg-sky-100/90 no-print">
        <div class="flex items-center gap-3">
          <h2 class="font-black text-base sm:text-lg text-slate-950" data-i18n="tblTitle">جدول عقود واستبيانات العملاء</h2>
          <span class="text-xs bg-white text-slate-950 border-2 border-sky-300 px-3 py-0.5 rounded-full font-mono font-black shadow-xs" id="visibleRowsCount">
            <?= count($clients) ?> عقد
          </span>
          <button type="button" onclick="window.print()" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-black shadow-xs flex items-center gap-1.5 cursor-pointer">
            <span>🖨️</span>
            <span data-i18n="btnPrint">طباعة الشيت</span>
          </button>
        </div>
        <span class="text-xs text-slate-700 font-mono font-bold">آخر تحديث: <?= date('Y-m-d H:i') ?></span>
      </div>

      <!-- Dedicated Print Sheet Header (Only appears on printed paper / PDF) -->
      <div class="print-header-sheet hidden">
        <div class="flex items-center justify-between pb-2 border-b-2 border-slate-900 mb-2">
          <div class="flex items-center gap-3">
            <img src="logo.png" alt="Logo" class="h-10 w-auto object-contain">
            <div>
              <h1 class="text-sm font-black text-slate-950 m-0 leading-tight">مجموعة العمار للتطوير العقاري</h1>
              <p class="text-[10px] font-bold text-slate-700 m-0">كشف عقود واستبيانات مشروع الساحل الشمالي (شورز)</p>
            </div>
          </div>
          <div class="text-left text-[9px] font-bold text-slate-900 leading-tight">
            <div>تاريخ الطباعة: <span class="font-mono font-black"><?= date('Y-m-d H:i') ?></span></div>
            <div>عدد العقود: <span class="font-mono font-black" id="printRowsCount"><?= count($clients) ?> عقد</span></div>
            <div>المسؤول الحالي: <span class="font-black"><?= htmlspecialchars($currentUserName) ?> (<?= htmlspecialchars($currentUserRole) ?>)</span></div>
          </div>
        </div>
      </div>

      <!-- ═══════════════ TABLE CONTAINER (STICKY HEADER + SMOOTH HORIZONTAL SCROLL) ═══════════════ -->
      <div class="overflow-x-auto max-h-[76vh] overflow-y-auto relative rounded-2xl border-2 border-sky-300 shadow-xl bg-white" id="tableScrollContainer">
        <table class="w-full text-xs sm:text-sm text-right border-collapse" id="clientsTable">
          <thead class="sticky top-0 z-20 bg-sky-200/95 text-slate-950 font-black border-b-2 border-sky-400 text-[11px] sm:text-xs uppercase tracking-wider backdrop-blur-md">
            <tr>
              <th class="p-3.5 w-12 text-center col-seq">#</th>
              <th class="p-3.5 w-16 text-center col-id">ID</th>
              <th class="p-3.5 text-center col-sales min-w-[130px]" data-i18n="thSales">مسؤول المبيعات</th>
              <th class="p-3.5 text-center col-unit min-w-[140px]" data-i18n="thUnit">كود الوحدة</th>
              <th class="p-3.5 text-center col-residence min-w-[120px]" data-i18n="thResidence">مكان الإقامة</th>
              <th class="p-3.5 text-center col-branch min-w-[120px]" data-i18n="thBranch">مكان التوثيق</th>
              <th class="p-3.5 text-center col-signing-loc min-w-[130px]" data-i18n="thSigningLoc">المكان الأقرب للتوقيع</th>
              <th class="p-3.5 col-name min-w-[210px] text-right" data-i18n="thName">اسم العميل في البطاقة / الشركاء</th>
              <th class="p-3.5 text-center col-phone min-w-[150px]" data-i18n="thPhone">أرقام التواصل</th>
              <th class="p-3.5 text-center col-email min-w-[160px]" data-i18n="thEmail">البريد الإلكتروني</th>
              <th class="p-3.5 text-center col-doc min-w-[140px]" data-i18n="thIdDoc">إثبات الشخصية</th>
              <th class="p-3.5 text-center col-plan min-w-[110px]" data-i18n="thPlan">فترة السداد</th>
              <th class="p-3.5 text-center col-october min-w-[135px]" data-i18n="th5Percent">مقدم أكتوبر</th>
              <th class="p-3.5 col-id-address min-w-[200px] text-right" data-i18n="thIdAddress">عنوان الهوية</th>
              <th class="p-3.5 col-corr-address min-w-[200px] text-right" data-i18n="thCorrAddress">عنوان المراسلات</th>
              <th class="p-3.5 col-notes min-w-[190px] text-right" data-i18n="thNotes">الملاحظات والاستثناءات</th>
              <th class="p-3.5 text-center col-files min-w-[110px]" data-i18n="thDocs">المستندات</th>
              <th class="p-3.5 text-center col-date min-w-[135px]" data-i18n="thDate">التاريخ</th>
              <th class="p-3.5 text-center no-print col-actions min-w-[110px]" data-i18n="thActions">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-sky-100 text-slate-950 font-bold" id="clientsTableBody">
            <?php if (empty($clients)): ?>
              <tr>
                <td colspan="19" class="p-14 text-center text-slate-950 font-black text-sm" data-i18n="noClientsFound">
                  <div class="text-4xl mb-2">🏖️</div>
                  لا توجد أي بيانات أو عقود مسجلة تطابق معايير البحث الحالية.
                </td>
              </tr>
            <?php else: ?>
              <?php 
              $seq = 1;
              foreach ($clients as $client): 
                $attachments = json_decode($client['attachments'] ?? '[]', true);
                if (!is_array($attachments)) $attachments = [];
                
                // جلب كافة العملاء الآخرين المسجلين في نفس الوحدة والمشروع (مع استبعاد العميل المعروض)
                $otherUnitClients = getOtherClientsInSameUnit(
                    $pdo, 
                    (int)$client['id'], 
                    (string)$client['eoi_number'], 
                    (string)($client['project'] ?? ''), 
                    $unitsOtherClientsMap
                );
                $client['other_unit_clients'] = $otherUnitClients;
                $clientJson = htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8');
                $loc = $client['contract_location'] ?? 'القاهرة';
                $residence = $client['residence_country'] ?? 'داخل جمهورية مصر العربية';
                $isOutside = str_contains($residence, 'خارج');

                // تفكيك الشركاء للوحدة إلى صفوف مستقلة
                $cNames  = splitPartnerValues((string)$client['name']);
                $cPhones = splitPartnerValues((string)$client['phone']);
                $cEmails = splitPartnerValues((string)($client['email'] ?? ''));
                $cIdNums = splitPartnerValues((string)($client['id_number'] ?? ''));

                $partnerCount = max(count($cNames), count($cPhones), count($cEmails), 1);

                // إدراج كافة البيانات ضمن مؤشر البحث اللحظي
                $otherSearchText = '';
                if (!empty($otherUnitClients)) {
                    foreach ($otherUnitClients as $oc) {
                        $otherSearchText .= ' ' . ($oc['name'] ?? '') . ' ' . ($oc['id_number'] ?? '');
                    }
                }

                for ($pIdx = 0; $pIdx < $partnerCount; $pIdx++):
                  $pName  = $cNames[$pIdx] ?? ($cNames[0] ?? $client['name']);
                  $pPhone = $cPhones[$pIdx] ?? ($cPhones[0] ?? $client['phone']);
                  $pEmail = $cEmails[$pIdx] ?? ($cEmails[0] ?? $client['email']);
                  $pIdNum = $cIdNums[$pIdx] ?? ($cIdNums[0] ?? $client['id_number']);
                  $rawPhone = preg_replace('/[^0-9]/', '', (string)$pPhone);
                  $rowSearchText = mb_strtolower($pName . ' ' . $pPhone . ' ' . $client['eoi_number'] . ' ' . ($client['sales_name'] ?? '') . ' ' . ($client['id_address'] ?? '') . ' ' . ($client['correspondence_address'] ?? '') . ' ' . $pIdNum . $otherSearchText);
              ?>
                <tr 
                  class="transition-colors client-row hover:bg-sky-50/80" 
                  data-search="<?= htmlspecialchars($rowSearchText, ENT_QUOTES, 'UTF-8') ?>"
                >
                  
                  <!-- 1. # (الترقيم التسلسلي) -->
                  <td class="p-3.5 text-center font-black text-slate-950 font-mono col-seq">
                    <?= $seq++ ?>
                  </td>

                  <!-- 2. ID -->
                  <td class="p-3.5 text-center text-slate-950 font-mono text-xs font-black whitespace-nowrap col-id">
                    #<?= htmlspecialchars($client['id']) ?>
                    <?php if ($partnerCount > 1): ?>
                      <div class="text-[10px] text-amber-800 font-black mt-0.5">شريك <?= ($pIdx + 1) ?> من <?= $partnerCount ?></div>
                    <?php endif; ?>
                  </td>

                  <!-- 3. مسؤول المبيعات -->
                  <td class="p-3.5 text-center col-sales min-w-[130px]">
                    <?php if (!empty($client['sales_name'])): ?>
                      <a 
                        href="view_clients.php?sales_id=<?= $client['sales_id'] ?>" 
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-black bg-purple-100 text-purple-950 border-2 border-purple-300 hover:bg-purple-200 transition-colors shadow-2xs"
                        title="فلترة وعرض عملاء هذا الموظف فقط"
                      >
                        <span>👤</span>
                        <span class="text-slate-950 font-black"><?= htmlspecialchars($client['sales_name']) ?></span>
                      </a>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-black bg-sky-100 text-slate-950 border-2 border-sky-300 shadow-2xs" data-i18n="badgeDirect">
                        🌐 مباشر
                      </span>
                    <?php endif; ?>
                  </td>

                  <!-- 4. كود الوحدة مع السعر إن وجد -->
                  <td class="p-3.5 text-center font-mono font-black text-slate-950 col-unit min-w-[140px]">
                    <button 
                      type="button"
                      onclick="copyText('<?= htmlspecialchars($client['eoi_number']) ?>')" 
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-sky-100 hover:bg-sky-200 border-2 border-sky-300 text-slate-950 text-xs font-mono font-black cursor-pointer shadow-xs transition-all hover:scale-105 active:scale-95"
                      title="اضغط لنسخ كود الوحدة"
                    >
                      <span>🏢</span>
                      <span><?= htmlspecialchars($client['eoi_number']) ?></span>
                    </button>
                    <div class="mt-1 space-y-0.5">
                      <?php if (!empty($client['unit_price'])): ?>
                        <div class="text-[11px] text-slate-950 font-black font-mono">
                          💰 <?= number_format($client['unit_price']) ?> ج.م
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($client['maintenance_deposit'])): ?>
                        <div class="text-[10px] text-emerald-800 font-black font-mono bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200 inline-block">
                          🛠️ صيانة: <?= number_format($client['maintenance_deposit']) ?> ج.م
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>

                  <!-- 5. مكان الإقامة الحالي -->
                  <td class="p-3.5 text-center text-xs font-black text-slate-950 col-residence min-w-[120px]">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl font-black text-slate-950 border-2 <?= $isOutside ? 'bg-blue-100 border-blue-300 text-blue-950' : 'bg-emerald-100 border-emerald-300 text-emerald-950' ?> shadow-2xs">
                      <?= $isOutside ? '🌍 خارج مصر' : '🇪🇬 داخل مصر' ?>
                    </span>
                  </td>

                  <!-- 6. مكان توثيق العقد -->
                  <td class="p-3.5 text-center text-xs font-black text-slate-950 col-branch min-w-[120px]">
                    <?php if ($isOutside): ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-sky-50 text-slate-950 border-2 border-sky-200 font-black shadow-2xs">
                        مستثنى (خارج مصر)
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl font-black text-slate-950 border-2 <?= ($loc === 'الإسكندرية') ? 'bg-sky-100 border-sky-300' : 'bg-amber-100 border-amber-300' ?> shadow-2xs">
                        <?= ($loc === 'الإسكندرية') ? '🌊 ' : '🏛️ ' ?>
                        <?= htmlspecialchars($loc) ?>
                      </span>
                    <?php endif; ?>
                  </td>

                  <!-- 7. المكان الأقرب لتوقيع العقد -->
                  <td class="p-3.5 text-xs font-black text-slate-950 text-center col-signing-loc min-w-[130px]">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-sky-50 border-2 border-sky-300 text-slate-950 font-black shadow-2xs">
                      <?= htmlspecialchars($client['preferred_signing_location'] ?? '-') ?>
                    </span>
                  </td>

                  <!-- 8. اسم العميل في البطاقة / الشركاء -->
                  <td class="p-3.5 font-black text-slate-950 col-name min-w-[210px] whitespace-normal break-words text-right">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="text-slate-950 font-black text-xs sm:text-sm leading-tight"><?= htmlspecialchars($pName, ENT_QUOTES, 'UTF-8') ?></span>
                      <?php if ($partnerCount > 1): ?>
                        <span class="inline-block px-2 py-0.5 rounded-lg text-[10px] bg-amber-100 text-amber-950 border border-amber-300 font-black whitespace-nowrap shadow-xs">شريك <?= ($pIdx + 1) ?> من <?= $partnerCount ?></span>
                      <?php endif; ?>
                    </div>

                    <?php if (!empty($otherUnitClients)): ?>
                      <div class="mt-2 pt-1.5 border-t border-sky-200">
                        <div class="text-[11px] font-black text-blue-900 flex items-center gap-1 mb-1">
                          <span>👥</span>
                          <span>عملاء آخرون في نفس الوحدة</span>
                        </div>
                        <div class="space-y-1">
                          <?php foreach ($otherUnitClients as $oc): 
                            $ocNames = splitPartnerValues((string)$oc['name']);
                            $ocIds   = splitPartnerValues((string)($oc['id_number'] ?? ''));
                            $ocCount = max(count($ocNames), count($ocIds), 1);
                            for ($k = 0; $k < $ocCount; $k++):
                              $ocSingleName = trim($ocNames[$k] ?? ($ocNames[0] ?? $oc['name']));
                              $ocSingleId   = trim($ocIds[$k] ?? ($ocIds[0] ?? ($oc['id_number'] ?? '')));
                              if ($ocSingleName === '') continue;
                          ?>
                            <div class="text-[11px] bg-sky-100/90 p-1.5 px-2 rounded-xl border border-sky-300 text-slate-950 font-bold shadow-2xs">
                              <div class="font-black text-slate-950"><?= htmlspecialchars($ocSingleName, ENT_QUOTES, 'UTF-8') ?></div>
                              <?php if ($ocSingleId !== ''): ?>
                                <div class="text-[10px] text-slate-800 font-mono font-bold mt-0.5">
                                  <?= htmlspecialchars($ocSingleId, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                              <?php endif; ?>
                            </div>
                          <?php 
                            endfor;
                          endforeach; 
                          ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  </td>

                  <!-- 9. أرقام التواصل مع واتساب السريع -->
                  <td class="p-3.5 text-center col-phone min-w-[150px]">
                    <div class="flex items-center justify-center gap-1.5 dir-ltr">
                      <?php if (!empty($rawPhone)): ?>
                        <a 
                          href="https://wa.me/<?= $rawPhone ?>" 
                          target="_blank" 
                          class="p-1 px-1.5 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-slate-950 border-2 border-emerald-300 shadow-2xs text-xs font-black"
                          title="محادثة واتساب سريعة"
                        >
                          💬
                        </a>
                      <?php endif; ?>
                      <span class="font-mono text-xs font-black text-slate-950"><?= htmlspecialchars($pPhone) ?></span>
                    </div>
                  </td>

                  <!-- 10. البريد الإلكتروني للمراسلات -->
                  <td class="p-3.5 font-mono text-xs text-slate-950 font-black text-center col-email min-w-[160px] dir-ltr">
                    <span class="text-slate-950 font-black"><?= htmlspecialchars($pEmail ?: '-') ?></span>
                  </td>

                  <!-- 11. إثبات الشخصية -->
                  <td class="p-3.5 text-center text-xs font-mono font-black text-slate-950 col-doc min-w-[140px]">
                    <div class="text-slate-950 font-black font-mono"><?= htmlspecialchars($pIdNum ?: ($client['id_number'] ?: '-')) ?></div>
                    <div class="text-[10px] text-slate-600 font-black mt-0.5"><?= ($client['id_type'] === 'passport') ? 'جواز سفر' : 'رقم قومي' ?></div>
                  </td>

                  <!-- 12. فترة السداد والنسبة -->
                  <td class="p-3.5 text-center text-xs font-black text-slate-950 col-plan min-w-[110px]">
                    <div class="inline-block px-2.5 py-1 rounded-xl bg-purple-100 text-purple-950 border-2 border-purple-300 font-black shadow-2xs">
                      <?= htmlspecialchars($client['period'] ?: $client['payment_period'] ?: '-') ?>
                    </div>
                    <?php if (!empty($client['final_rate'])): ?>
                      <div class="text-[11px] text-slate-800 font-mono font-black mt-0.5"><?= htmlspecialchars($client['final_rate']) ?></div>
                    <?php endif; ?>
                  </td>

                  <!-- 13. سداد مقدم أكتوبر + تاريخ الدفع -->
                  <td class="p-3.5 text-center font-black text-slate-950 col-october min-w-[135px]">
                    <?php if ($client['paid_5_percent'] === 'نعم'): ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-950 border-2 border-emerald-400 shadow-2xs mb-1">
                        ✓ تم الاستكمال
                      </span>
                      <?php if (!empty($client['payment_date'])): ?>
                        <div class="text-[10px] text-slate-800 font-mono font-bold">
                          📅 <?= htmlspecialchars($client['payment_date']) ?>
                        </div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-black bg-red-100 text-red-950 border-2 border-red-300 shadow-2xs">
                        ✕ لم يستكمل
                      </span>
                    <?php endif; ?>
                  </td>

                  <!-- 14. عنوان الهوية (عمود مستقل كامل بدون اقتطاع) -->
                  <td class="p-3.5 text-right font-bold text-slate-950 col-id-address min-w-[200px] whitespace-normal break-words text-xs leading-relaxed">
                    <?php 
                      $idAddr = !empty($client['id_address']) ? $client['id_address'] : (!empty($client['governorate']) ? $client['governorate'] : (!empty($client['addresses']) ? $client['addresses'] : '-'));
                    ?>
                    <span class="text-slate-900 font-bold block"><?= htmlspecialchars($idAddr, ENT_QUOTES, 'UTF-8') ?></span>
                  </td>

                  <!-- 15. عنوان المراسلات (عمود مستقل كامل بدون اقتطاع) -->
                  <td class="p-3.5 text-right font-bold text-slate-950 col-corr-address min-w-[200px] whitespace-normal break-words text-xs leading-relaxed">
                    <?php 
                      $corrAddr = !empty($client['correspondence_address']) ? $client['correspondence_address'] : (!empty($client['addresses']) ? $client['addresses'] : '-');
                    ?>
                    <span class="text-slate-900 font-bold block"><?= htmlspecialchars($corrAddr, ENT_QUOTES, 'UTF-8') ?></span>
                  </td>

                  <!-- 16. الملاحظات والاستثناءات (كاملة بدون اقتطاع) -->
                  <td class="p-3.5 text-right text-xs font-bold text-slate-950 col-notes min-w-[190px] whitespace-normal break-words leading-relaxed">
                    <?php if (!empty($client['general_notes'])): ?>
                      <div class="text-slate-950 font-bold text-[11px] mb-1">
                        <span class="font-black text-slate-900">عامة:</span> <?= htmlspecialchars($client['general_notes']) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($client['notes'])): ?>
                      <div class="text-slate-950 font-bold text-[11px]">
                        <span class="font-black text-slate-900">خطة/بديلة:</span> <?= htmlspecialchars($client['notes']) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (empty($client['general_notes']) && empty($client['notes'])): ?>
                      <span class="text-slate-400 font-bold">-</span>
                    <?php endif; ?>
                  </td>

                  <!-- 17. المستندات والمرفقات -->
                  <td class="p-3.5 text-center col-files min-w-[110px]">
                    <?php if (!empty($attachments)): ?>
                      <div class="flex items-center justify-center flex-wrap gap-1">
                        <?php foreach ($attachments as $fIdx => $f): 
                          $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                        ?>
                          <button 
                            type="button" 
                            onclick="previewAttachment('<?= htmlspecialchars($f) ?>', '<?= $ext ?>')"
                            class="px-2 py-1 rounded-lg bg-sky-100 hover:bg-sky-200 border-2 border-sky-300 text-[11px] font-mono text-slate-950 font-black flex items-center gap-1 cursor-pointer transition-colors shadow-2xs"
                            title="معاينة المرفق"
                          >
                            <span><?= ($ext === 'pdf') ? '📄' : '🖼️' ?></span>
                            <span>#<?= $fIdx+1 ?></span>
                          </button>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-slate-400 font-bold text-xs" data-i18n="lblNoFiles">لا يوجد</span>
                    <?php endif; ?>
                  </td>

                  <!-- 18. التاريخ -->
                  <td class="p-3.5 text-center font-mono text-xs font-black text-slate-950 col-date min-w-[135px]">
                    <div class="bg-sky-50 px-2 py-1 rounded-xl border border-sky-200 inline-block font-mono">
                      <?= date('Y-m-d H:i', strtotime($client['created_at'])) ?>
                    </div>
                  </td>

                  <!-- 19. إجراءات -->
                  <td class="p-3.5 text-center no-print col-actions min-w-[110px]">
                    <div class="flex items-center justify-center gap-1">
                      <!-- زر عرض التفاصيل الكاملة -->
                      <button 
                        type="button"
                        onclick="openClientDetailsModal(<?= $clientJson ?>)"
                        class="p-1.5 px-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-black transition-all cursor-pointer shadow-xs"
                        title="عرض ملف العميل كاملاً"
                      >
                        👁️
                      </button>

                      <!-- زر التعديل -->
                      <button 
                        type="button"
                        onclick="openEditModal(<?= $clientJson ?>)"
                        class="p-1.5 px-2 bg-sky-100 hover:bg-sky-200 text-slate-950 border-2 border-sky-300 rounded-xl text-xs font-black transition-all cursor-pointer shadow-2xs"
                        title="تعديل بيانات العقد"
                      >
                        ✏️
                      </button>

                      <!-- زر الحذف -->
                      <button 
                        type="button"
                        onclick="confirmDeleteClient(<?= $client['id'] ?>, '<?= htmlspecialchars(addslashes($client['name']), ENT_QUOTES) ?>')"
                        class="p-1.5 px-2 bg-red-50 hover:bg-red-100 text-red-700 border-2 border-red-300 rounded-xl text-xs font-black transition-all cursor-pointer shadow-2xs"
                        title="حذف العقد"
                      >
                        🗑️
                      </button>
                    </div>
                  </td>

                </tr>
              <?php endfor; ?>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>

  </div>

  <!-- ═══════════════ CLIENT DETAILS MODAL (عرض كامل التفاصيل) ═══════════════ -->
  <div id="clientDetailsModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/85 backdrop-blur-md hidden overflow-y-auto">
    <div class="glass-card max-w-3xl w-full p-5 sm:p-7 rounded-3xl border-2 border-sky-400 shadow-2xl relative my-8 bg-white text-slate-950">
      
      <!-- Modal Header -->
      <div class="flex items-center justify-between pb-3.5 border-b-2 border-sky-200 mb-4">
        <div class="flex items-center gap-2.5">
          <span class="text-2xl">📋</span>
          <div>
            <h3 class="text-base sm:text-lg font-black text-slate-950 flex items-center gap-2">
              <span>ملف العميل وبيانات التعاقد الشاملة</span>
            </h3>
            <p class="text-[11px] text-slate-600 font-bold">كافة التفاصيل المسجلة للوحدة والعميل والمستندات المرفقة</p>
          </div>
        </div>
        <button onclick="closeClientDetailsModal()" class="text-slate-500 hover:text-slate-950 text-2xl font-black cursor-pointer p-1">✕</button>
      </div>

      <!-- Modal Body -->
      <div class="space-y-4 max-h-[72vh] overflow-y-auto pr-1">
        
        <!-- Header Key Highlights -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 p-3.5 rounded-2xl bg-sky-50 border-2 border-sky-200">
          <div>
            <span class="text-[10px] text-slate-600 font-bold block mb-0.5">كود الوحدة:</span>
            <span class="font-mono text-sm font-black text-sky-950 px-2 py-0.5 rounded-lg bg-white border border-sky-300 inline-block" id="details_modal_unit">-</span>
          </div>
          <div>
            <span class="text-[10px] text-slate-600 font-bold block mb-0.5">إجمالي سعر الوحدة:</span>
            <span class="font-mono text-xs font-black text-slate-950" id="details_modal_unit_price">-</span>
          </div>
          <div>
            <span class="text-[10px] text-slate-600 font-bold block mb-0.5">وديعة الصيانة:</span>
            <span class="font-mono text-xs font-black text-emerald-800" id="details_modal_maintenance">-</span>
          </div>
        </div>

        <!-- Section 1: البيانات الشخصية والهوية -->
        <div class="p-3.5 rounded-2xl bg-white border-2 border-sky-100 space-y-3">
          <div class="text-xs font-black text-slate-950 flex items-center gap-1.5 border-b border-sky-100 pb-1.5">
            <span>👤</span>
            <span>بيانات العميل والهوية:</span>
          </div>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">اسم العميل / الشركاء:</span>
              <span class="font-black text-slate-950 text-sm" id="details_modal_name">-</span>
            </div>
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">إثبات الشخصية:</span>
              <span class="font-black text-slate-950 font-mono" id="details_modal_id_num">-</span>
              <span class="text-[10px] text-slate-600 font-bold mr-1" id="details_modal_id_type"></span>
            </div>
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">رقم الهاتف والتواصل:</span>
              <div class="flex items-center gap-2 mt-0.5">
                <span class="font-black font-mono text-slate-950" id="details_modal_phone">-</span>
                <a id="details_modal_wa" href="#" target="_blank" class="px-2 py-0.5 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-950 border border-emerald-300 font-black text-[11px] inline-flex items-center gap-1 shadow-2xs">
                  <span>💬</span> <span>واتساب</span>
                </a>
              </div>
            </div>
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">البريد الإلكتروني:</span>
              <span class="font-black font-mono text-slate-950" id="details_modal_email">-</span>
            </div>
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">مكان الإقامة الحالي:</span>
              <span class="font-black text-slate-950" id="details_modal_residence">-</span>
            </div>
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">مسؤول المبيعات:</span>
              <span class="font-black text-slate-950" id="details_modal_sales">-</span>
            </div>
          </div>
        </div>

        <!-- Section 2: العناوين التفصيلية -->
        <div class="p-3.5 rounded-2xl bg-white border-2 border-sky-100 space-y-3">
          <div class="text-xs font-black text-slate-950 flex items-center gap-1.5 border-b border-sky-100 pb-1.5">
            <span>📍</span>
            <span>العناوين التفصيلية (الهوية والمراسلات):</span>
          </div>

          <div class="space-y-2.5 text-xs">
            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
              <span class="text-slate-600 font-black text-[11px] block mb-0.5">عنوان الهوية (المسجل بالبطاقة / الجواز):</span>
              <span class="font-black text-slate-950 leading-relaxed block" id="details_modal_id_addr">-</span>
            </div>
            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
              <span class="text-slate-600 font-black text-[11px] block mb-0.5">عنوان المراسلات الرسمي المعتمد:</span>
              <span class="font-black text-slate-950 leading-relaxed block" id="details_modal_corr_addr">-</span>
            </div>
          </div>
        </div>

        <!-- Section 3: التعاقد وخطة السداد -->
        <div class="p-3.5 rounded-2xl bg-white border-2 border-sky-100 space-y-3">
          <div class="text-xs font-black text-slate-950 flex items-center gap-1.5 border-b border-sky-100 pb-1.5">
            <span>💳</span>
            <span>بيانات التعاقد ونظام السداد:</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">مكان التعاقد المعتمد:</span>
              <span class="font-black text-slate-950" id="details_modal_contract_loc">-</span>
            </div>
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">المكان الأقرب للتوقيع:</span>
              <span class="font-black text-slate-950" id="details_modal_preferred_loc">-</span>
            </div>
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">فترة السداد والتقسيط:</span>
              <span class="font-black text-slate-950" id="details_modal_plan">-</span>
              <span class="text-slate-600 font-bold mr-1 font-mono" id="details_modal_rate"></span>
            </div>
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">عدد الشيكات:</span>
              <span class="font-black text-slate-950 font-mono" id="details_modal_cheques">-</span>
            </div>
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">حالة سداد المقدم:</span>
              <div class="mt-0.5 flex items-center gap-2">
                <span id="details_modal_paid_badge" class="px-2.5 py-0.5 rounded-full text-xs font-black">-</span>
                <span class="text-[11px] font-mono font-bold text-slate-700" id="details_modal_pay_date"></span>
              </div>
            </div>
            <div>
              <span class="text-slate-500 font-bold block text-[11px]">تاريخ تسجيل الاستمارة:</span>
              <span class="font-black font-mono text-slate-950" id="details_modal_created">-</span>
            </div>
          </div>

          <!-- الملاحظات والاستثناءات -->
          <div class="pt-2 border-t border-sky-100">
            <span class="text-slate-500 font-bold block text-[11px] mb-0.5">الملاحظات والاستثناءات:</span>
            <div class="p-2.5 rounded-xl bg-amber-50/60 border border-amber-200 text-xs font-bold text-slate-900 leading-relaxed" id="details_modal_notes">
              لا توجد ملاحظات
            </div>
          </div>
        </div>

        <!-- Section 4: المستندات والإيصالات المرفقة -->
        <div class="p-3.5 rounded-2xl bg-white border-2 border-sky-100 space-y-3">
          <div class="text-xs font-black text-slate-950 flex items-center justify-between border-b border-sky-100 pb-1.5">
            <span class="flex items-center gap-1.5">
              <span>🧾</span>
              <span>المستندات وإيصالات الدفع المرفوعة:</span>
            </span>
          </div>
          <div id="details_modal_files" class="space-y-2"></div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="pt-4 border-t-2 border-sky-200 mt-4 flex items-center justify-between gap-2 flex-wrap">
        <button type="button" onclick="closeClientDetailsModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl text-xs font-black text-slate-950 border border-slate-300 cursor-pointer">
          إغلاق النافذة
        </button>
        <div class="flex items-center gap-2">
          <button type="button" onclick="window.print()" class="px-4 py-2 bg-sky-100 hover:bg-sky-200 rounded-xl text-xs font-black text-sky-950 border-2 border-sky-300 cursor-pointer shadow-xs">
            🖨️ طباعة
          </button>
          <button type="button" onclick="editCurrentViewClient()" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-black transition-all cursor-pointer shadow-md">
            ✏️ تعديل بيانات العميل
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- ═══════════════ EDIT CLIENT MODAL ═══════════════ -->
  <div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/85 backdrop-blur-md hidden overflow-y-auto">
    <div class="glass-card max-w-2xl w-full p-6 sm:p-8 rounded-3xl border-2 border-sky-400 shadow-2xl relative my-8 bg-white text-slate-950">
      
      <div class="flex items-center justify-between pb-3 border-b-2 border-sky-200 mb-4">
        <h3 class="text-base sm:text-lg font-black text-slate-950 flex items-center gap-2">
          <span>✏️</span>
          <span data-i18n="editModalTitle">تعديل بيانات العقد والعميل</span>
        </h3>
        <button onclick="closeEditModal()" class="text-slate-500 hover:text-slate-950 text-xl font-black cursor-pointer">✕</button>
      </div>

      <form method="POST" action="view_clients.php" enctype="multipart/form-data" class="space-y-4 text-xs">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="client_id" id="edit_client_id">

        <!-- اختيار مسؤول المبيعات -->
        <div>
          <label class="block text-xs font-black text-slate-950 mb-1" data-i18n="editModalSalesLbl">
            👤 مسؤول المبيعات (Sales Rep):
          </label>
          <?php if ($currentUserRole === 'Sales'): ?>
            <div class="p-2.5 rounded-xl bg-sky-50 border-2 border-sky-300 text-slate-950 font-black">
              👤 <?= htmlspecialchars($currentUserName) ?> (حسابك الحالي)
            </div>
            <input type="hidden" name="edit_sales_id" id="edit_sales_id" value="<?= $currentUserId ?>">
          <?php else: ?>
            <select name="edit_sales_id" id="edit_sales_id" class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs font-bold shadow-xs focus:border-sky-500">
              <option value="" data-i18n="optDirectNoSales">-- مباشر (بدون وسيط / Direct) --</option>
              <?php foreach ($salesList as $s): ?>
                <option value="<?= $s['id'] ?>">👤 <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['referral_code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>

        <!-- كود الوحدة واسم العميل -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1" data-i18n="thUnit">كود الوحدة:</label>
            <input type="text" name="edit_unit_code" id="edit_unit_code" required class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-mono text-sm focus:border-sky-500 focus:outline-none uppercase font-black shadow-xs">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1">اسم العميل في البطاقة / الشركاء:</label>
            <input type="text" name="edit_name" id="edit_name" required class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-sm focus:border-sky-500 focus:outline-none font-bold shadow-xs">
          </div>
        </div>

        <!-- قسم عملاء آخرون في نفس الوحدة داخل المودال إن وجدوا -->
        <div id="modal_other_clients_container" class="hidden p-3 rounded-2xl bg-sky-50 border-2 border-sky-300 text-xs">
          <div class="font-black text-blue-900 mb-1.5 flex items-center gap-1.5">
            <span>👥</span>
            <span>عملاء آخرون في نفس الوحدة</span>
          </div>
          <div id="modal_other_clients_list" class="space-y-1"></div>
        </div>

        <!-- مكان الإقامة ومكان توثيق العقد -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1">مكان الإقامة الحالي:</label>
            <select name="edit_residence_country" id="edit_residence_country" class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs font-bold shadow-xs focus:border-sky-500">
              <option value="داخل جمهورية مصر العربية">🇪🇬 داخل جمهورية مصر العربية</option>
              <option value="خارج جمهورية مصر العربية">🌍 خارج جمهورية مصر العربية</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1">مكان التوثيق (إن كان داخل مصر):</label>
            <select name="edit_contract_location" id="edit_contract_location" class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs font-bold shadow-xs focus:border-sky-500">
              <option value="القاهرة">القاهرة</option>
              <option value="الإسكندرية">الإسكندرية</option>
              <option value="خارج جمهورية مصر العربية">خارج جمهورية مصر العربية</option>
            </select>
          </div>
        </div>

        <!-- الهاتف والبريد -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1" data-i18n="thPhone">رقم الهاتف والتواصل:</label>
            <input type="text" name="edit_phone" id="edit_phone" required class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-mono text-sm focus:border-sky-500 focus:outline-none dir-ltr text-right font-bold shadow-xs">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1">البريد الإلكتروني للمراسلات:</label>
            <input type="email" name="edit_email" id="edit_email" required class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-mono text-sm focus:border-sky-500 focus:outline-none dir-ltr text-right font-bold shadow-xs">
          </div>
        </div>

        <!-- الهوية وسعر الوحدة والصيانة -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1" data-i18n="lblDocType">نوع الوثيقة:</label>
            <select name="edit_id_type" id="edit_id_type" class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs font-bold shadow-xs focus:border-sky-500">
              <option value="national_id">بطاقة الرقم القومي</option>
              <option value="passport">جواز السفر (Passport)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1" data-i18n="lblDocNum">رقم الوثيقة:</label>
            <input type="text" name="edit_id_number" id="edit_id_number" class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-mono text-sm focus:border-sky-500 focus:outline-none font-bold shadow-xs">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1">إجمالي الوحدة (EGP):</label>
            <input type="number" step="any" name="edit_unit_price" id="edit_unit_price" class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-mono text-sm focus:border-sky-500 focus:outline-none font-bold shadow-xs">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1">وديعة الصيانة (EGP):</label>
            <input type="number" step="any" name="edit_maintenance_deposit" id="edit_maintenance_deposit" class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-mono text-sm focus:border-sky-500 focus:outline-none font-bold shadow-xs">
          </div>
        </div>

        <!-- تفاصيل خطة السداد والنسب والشيكات (Payment Plan) -->
        <div class="p-3.5 rounded-2xl bg-sky-50/70 border-2 border-sky-200 space-y-3">
          <div class="flex items-center gap-2 text-xs font-black text-slate-950 pb-2 border-b border-sky-200">
            <span>💳</span>
            <span>نظام وخطة السداد المعتمدة (Payment Plan):</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-black text-slate-950 mb-1" data-i18n="thPlan">فترة السداد:</label>
              <select name="edit_payment_period" id="edit_payment_period" onchange="handleModalPeriodChange(this.value)" class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs font-black shadow-xs focus:border-sky-500 cursor-pointer">
                <option value="7 Years">7 سنوات (7 Years)</option>
                <option value="8 Years">8 سنوات (8 Years)</option>
                <option value="9 Years">9 سنوات (9 Years)</option>
                <option value="10 Years">10 سنوات (10 Years)</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-black text-slate-950 mb-1">النسبة (Rate):</label>
              <select name="edit_final_rate" id="edit_final_rate" class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs font-black shadow-xs focus:border-sky-500 cursor-pointer">
                <!-- يُملأ ديناميكياً حسب خطة السداد -->
              </select>
            </div>

            <div>
              <label class="block text-xs font-black text-slate-950 mb-1">عدد الشيكات المطلوب:</label>
              <input type="text" readonly name="edit_cheques_count" id="edit_cheques_count" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 border-2 border-slate-300 text-amber-950 font-mono text-xs font-black shadow-xs cursor-not-allowed">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <div>
              <label class="block text-xs font-black text-slate-950 mb-1">استكمال مقدم أكتوبر / سداد المقدم:</label>
              <select name="edit_paid_5_percent" id="edit_paid_5_percent" class="w-full px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs font-bold shadow-xs focus:border-sky-500">
                <option value="نعم">نعم (تم الاستكمال)</option>
                <option value="لا">لا (لم يستكمل)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-black text-slate-950 mb-1">تاريخ الدفع:</label>
              <input type="date" name="edit_payment_date" id="edit_payment_date" class="w-full px-3 py-2 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-mono text-xs focus:border-sky-500 focus:outline-none font-bold shadow-xs">
            </div>
          </div>
        </div>

        <!-- العناوين -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1">العنوان في البطاقة / الهوية:</label>
            <textarea name="edit_id_address" id="edit_id_address" rows="2" class="w-full px-3 py-2 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs focus:border-sky-500 focus:outline-none font-bold shadow-xs"></textarea>
          </div>
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1">العنوان المعتمد للمراسلات:</label>
            <textarea name="edit_correspondence_address" id="edit_correspondence_address" rows="2" class="w-full px-3 py-2 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs focus:border-sky-500 focus:outline-none font-bold shadow-xs"></textarea>
          </div>
        </div>

        <!-- الملاحظات والاستثناءات -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1" data-i18n="lblNotes">ملاحظات عامة / خطة بديلة واستثناءات (اختياري):</label>
            <textarea name="edit_notes" id="edit_notes" rows="2" class="w-full px-3 py-2 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs focus:border-sky-500 focus:outline-none font-bold shadow-xs"></textarea>
          </div>
          <div>
            <label class="block text-xs font-black text-slate-950 mb-1">ملاحظات عامة (اختياري):</label>
            <textarea name="edit_general_notes" id="edit_general_notes" rows="2" class="w-full px-3 py-2 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs focus:border-sky-500 focus:outline-none font-bold shadow-xs"></textarea>
          </div>
        </div>

        <!-- المرفقات الحالية وإضافة جديدة -->
        <div class="border-2 border-sky-300 rounded-2xl p-4 bg-sky-50 space-y-3">
          <label class="block text-xs font-black text-slate-950" data-i18n="editModalDocs">📎 إدارة المستندات والمرفقات:</label>
          <div id="modal_existing_files" class="space-y-2"></div>
          <div>
            <label class="block text-[11px] font-black text-slate-950 mb-1" data-i18n="editModalUploadMore">+ رفع ملفات إضافية:</label>
            <input 
              type="file" 
              name="new_attachments[]" 
              multiple 
              accept=".pdf,image/*" 
              class="w-full text-xs text-slate-950 font-black file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border file:border-sky-300 file:text-xs file:font-black file:bg-sky-200 file:text-slate-950 hover:file:bg-sky-300 cursor-pointer"
            >
          </div>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="flex items-center justify-end gap-3 pt-3 border-t-2 border-sky-200">
          <button type="button" onclick="closeEditModal()" class="px-4 py-2.5 bg-white hover:bg-sky-50 rounded-xl text-xs font-black text-slate-950 border-2 border-sky-300 cursor-pointer shadow-xs" data-i18n="btnCancel">
            إلغاء
          </button>
          <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-sky-400 to-blue-500 hover:from-sky-500 hover:to-blue-600 text-slate-950 font-black rounded-xl text-xs shadow-lg border-2 border-sky-400 cursor-pointer" data-i18n="btnSaveEdit">
            💾 حفظ التعديلات
          </button>
        </div>

      </form>

    </div>
  </div>

  <!-- ═══════════════ DELETE CONFIRMATION MODAL ═══════════════ -->
  <div id="deleteClientModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md hidden">
    <div class="glass-card max-w-md w-full p-6 rounded-3xl border-2 border-red-300 text-center shadow-2xl bg-white text-slate-950">
      <div class="w-14 h-14 rounded-2xl bg-red-500/20 text-red-400 flex items-center justify-center text-2xl mx-auto mb-3">
        ⚠️
      </div>
      <h3 class="text-base font-black text-slate-950 mb-1" data-i18n="delClientModalTitle">تأكيد حذف عقد العميل</h3>
      <p class="text-xs text-slate-700 font-bold mb-5" id="delClientModalDesc">
        هل أنت متأكد تماماً من حذف سجل هذا العميل ومرفقاته؟ لن يمكن استرجاع البيانات بعد الحذف.
      </p>

      <form method="POST" action="view_clients.php" class="flex gap-3 justify-center">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="client_id" id="deleteClientId">
        
        <button 
          type="button" 
          onclick="closeDeleteClientModal()" 
          class="px-5 py-2.5 bg-white hover:bg-sky-50 text-slate-950 font-black rounded-xl text-xs border-2 border-sky-300 cursor-pointer shadow-xs"
          data-i18n="btnCancel"
        >
          إلغاء
        </button>
        <button 
          type="submit" 
          class="px-6 py-2.5 bg-red-600 hover:bg-red-500 text-white font-black rounded-xl text-xs shadow-lg cursor-pointer"
          data-i18n="btnConfirmDelete"
        >
          نعم، تأكيد الحذف
        </button>
      </form>
    </div>
  </div>

  <!-- ═══════════════ ATTACHMENT LIGHTBOX PREVIEW MODAL ═══════════════ -->
  <div id="attachmentLightbox" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-md hidden" onclick="closeLightbox()">
    <div class="max-w-4xl w-full max-h-[90vh] bg-white border-2 border-sky-400 rounded-3xl p-4 shadow-2xl relative flex flex-col items-center text-slate-950" onclick="event.stopPropagation()">
      <div class="w-full flex justify-between items-center mb-3 pb-2 border-b-2 border-sky-200">
        <span class="text-xs font-black text-slate-950 font-mono" id="lightboxTitle">معاينة المستند</span>
        <div class="flex items-center gap-2">
          <a id="lightboxDownloadBtn" href="#" target="_blank" download class="text-xs px-3 py-1 bg-gradient-to-r from-sky-400 to-blue-500 text-slate-950 font-black rounded-xl border border-sky-400 shadow-xs" data-i18n="btnDownloadDoc">تحميل</a>
          <button onclick="closeLightbox()" class="text-slate-500 hover:text-slate-950 text-xl font-black cursor-pointer">✕</button>
        </div>
      </div>
      <div id="lightboxContent" class="w-full flex items-center justify-center overflow-auto max-h-[75vh]"></div>
    </div>
  </div>

  <!-- ═══════════════ SCRIPT LOGIC (BILINGUAL I18N + LIVE SEARCH) ═══════════════ -->
  <script>
    const dashboardTranslations = {
      ar: {
        dashBadge: "الإدارة المركزية",
        dashTitle: "لوحة تحكم وإدارة عقود شورز الساحلية",
        dashSub: "تتبع ومراجعة استبيانات وعقود الساحل الشمالي مع إسناد موظفي المبيعات والبيانات المحدثة",
        btnManageSales: "إدارة مسؤولي المبيعات",
        btnExportCsv: "تصدير Excel (CSV)",
        btnPrint: "طباعة",
        btnNewForm: "استمارة التعاقد",
        btnLogout: "تسجيل خروج",
        kpiTotal: "إجمالي العقود المسجلة",
        kpiTotalSub: "عقد معتمد",
        kpi5Percent: "سداد مقدم أكتوبر",
        kpiBranches: "فروع ومكان التوثيق",
        kpiSalesTeam: "فريق المبيعات",
        kpiSalesTeamSub: "موظف نشط",
        phSearchClients: "بحث سريع: اسم العميل، الهاتف، كود الوحدة، السيلز...",
        filterSalesLbl: "السيلز:",
        optAllSales: "كل موظفي Sales (عرض الكل)",
        filterBranchLbl: "مكان التوثيق:",
        optAllBranches: "كل الأماكن",
        optCairoBranch: "🏛️ القاهرة",
        optAlexBranch: "🌊 الإسكندرية",
        btnClearFilters: "✕ إلغاء الفلترة",
        tblTitle: "جدول عقود واستبيانات العملاء",
        thSales: "مسؤول المبيعات",
        thUnit: "كود الوحدة (اليونت)",
        thResidence: "بلد الإقامة",
        thBranch: "مكان التعاقد",
        thContractLoc: "مكان التعاقد",
        thName: "اسم العميل",
        thIdAddress: "عنوان الهوية",
        thCorrAddress: "عنوان المراسلات",
        thPhone: "أرقام التواصل",
        thIdDoc: "إثبات الشخصية",
        thPlan: "فترة السداد",
        thPaymentStatus: "حالة السداد (تم السداد؟)",
        thTotalPrice: "إجمالي سعر الوحدة (توتال اليونت)",
        th5Percent: "مقدم أكتوبر",
        thAddress: "العنوان (الهوية / المراسلات)",
        thNotes: "الملاحظات والاستثناءات",
        thDocs: "المستندات",
        thDate: "تاريخ ووقت التسجيل",
        thActions: "الإجراءات",
        noClientsFound: "لا توجد أي بيانات أو عقود مسجلة تطابق معايير البحث الحالية.",
        badgeDirect: "🌐 مباشر",
        lblNoFiles: "لا يوجد",
        lblNotes: "الملاحظات والاستثناءات:",
        editModalTitle: "تعديل بيانات العقد والعميل",
        editModalSalesLbl: "👤 مسؤول المبيعات (Sales Rep):",
        optDirectNoSales: "-- مباشر (بدون وسيط / Direct) --",
        lblDocType: "نوع الوثيقة:",
        lblDocNum: "رقم الوثيقة:",
        editModalDocs: "📎 إدارة المستندات والمرفقات:",
        editModalUploadMore: "+ رفع ملفات إضافية:",
        btnCancel: "إلغاء",
        btnSaveEdit: "💾 حفظ التعديلات",
        delClientModalTitle: "تأكيد حذف عقد العميل",
        btnConfirmDelete: "نعم، تأكيد الحذف",
        btnDownloadDoc: "تحميل"
      },
      en: {
        dashBadge: "Central Management",
        dashTitle: "Shores Coastal Contracts Management Dashboard",
        dashSub: "Track and review North Coast client registrations and sales consultant allocations",
        btnManageSales: "Manage Sales Consultants",
        btnExportCsv: "Export Excel (CSV)",
        btnPrint: "Print",
        btnNewForm: "New Contract Form",
        btnLogout: "Logout",
        kpiTotal: "Total Registered Contracts",
        kpiTotalSub: "certified contracts",
        kpi5Percent: "October Down Payment",
        kpiBranches: "Contract Locations",
        kpiSalesTeam: "Sales Team",
        kpiSalesTeamSub: "active consultants",
        phSearchClients: "Quick Search: Client Name, Phone, Unit Code, Sales...",
        filterSalesLbl: "Sales:",
        optAllSales: "All Sales Consultants (Show All)",
        filterBranchLbl: "Location:",
        optAllBranches: "All Locations",
        optCairoBranch: "🏛️ Cairo",
        optAlexBranch: "🌊 Alexandria",
        btnClearFilters: "✕ Clear Filters",
        tblTitle: "Client Contracts & Applications Directory",
        thSales: "Sales Consultant",
        thUnit: "Unit Code",
        thResidence: "Country of Residence",
        thBranch: "Contract Location",
        thContractLoc: "Contract Location",
        thName: "Client Name",
        thIdAddress: "Identity Address",
        thCorrAddress: "Correspondence Address",
        thPhone: "Contact Phones",
        thIdDoc: "ID / Passport",
        thPlan: "Payment Plan",
        thPaymentStatus: "Payment Status",
        thTotalPrice: "Total Unit Price",
        th5Percent: "October Advance",
        thAddress: "Address (ID / Correspondence)",
        thNotes: "Notes & Exceptions",
        thDocs: "Documents",
        thDate: "Date Logged",
        thActions: "Actions",
        noClientsFound: "No clients or contracts found matching current search criteria.",
        badgeDirect: "🌐 Direct",
        lblNoFiles: "None",
        lblNotes: "Notes & Exceptions:",
        editModalTitle: "Edit Client & Contract Information",
        editModalSalesLbl: "👤 Sales Consultant Assigned:",
        optDirectNoSales: "-- Direct (No Consultant) --",
        lblDocType: "Document Type:",
        lblDocNum: "Document Number:",
        editModalDocs: "📎 Document Attachments Management:",
        editModalUploadMore: "+ Upload Additional Files:",
        btnCancel: "Cancel",
        btnSaveEdit: "💾 Save Changes",
        delClientModalTitle: "Confirm Client Contract Deletion",
        btnConfirmDelete: "Yes, Permanently Delete",
        btnDownloadDoc: "Download"
      }
    };

    let currentLang = localStorage.getItem('elamar_lang') || 'ar';

    document.addEventListener('DOMContentLoaded', () => {
      setLanguage(currentLang);
      animateCounters();

      // التحقق من وجود استعلام بحث في الرابط لتطبيقه فوراً (?search=... أو ?q=...)
      const urlParams = new URLSearchParams(window.location.search);
      const searchQuery = urlParams.get('search') || urlParams.get('q');
      if (searchQuery) {
        const searchInput = document.getElementById('tableSearchInput');
        if (searchInput) {
          searchInput.value = searchQuery;
          liveFilterClients(searchQuery);
        }
      }
    });

    function toggleLanguage() {
      const nextLang = (currentLang === 'ar') ? 'en' : 'ar';
      setLanguage(nextLang);
    }

    function setLanguage(lang) {
      currentLang = lang;
      localStorage.setItem('elamar_lang', lang);

      const html = document.documentElement;
      html.dir = (lang === 'ar') ? 'rtl' : 'ltr';
      html.lang = lang;

      document.getElementById('langBtnText').innerText = (lang === 'ar') ? 'English' : 'العربية';

      document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (dashboardTranslations[lang] && dashboardTranslations[lang][key]) {
          el.innerText = dashboardTranslations[lang][key];
        }
      });

      document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (dashboardTranslations[lang] && dashboardTranslations[lang][key]) {
          el.setAttribute('placeholder', dashboardTranslations[lang][key]);
        }
      });
    }

    // Number Count-Up Animation
    function animateCounters() {
      document.querySelectorAll('.count-up').forEach(counter => {
        const target = +counter.getAttribute('data-target');
        if (isNaN(target)) return;
        let count = 0;
        const step = Math.max(1, Math.ceil(target / 25));
        const timer = setInterval(() => {
          count += step;
          if (count >= target) {
            counter.innerText = target;
            clearInterval(timer);
          } else {
            counter.innerText = count;
          }
        }, 30);
      });
    }

    // Live filter clients table rows in real-time
    function liveFilterClients(query) {
      const q = query.trim().toLowerCase();
      const rows = document.querySelectorAll('.client-row');
      let count = 0;
      rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        if (text.includes(q)) {
          row.style.display = '';
          count++;
        } else {
          row.style.display = 'none';
        }
      });
      document.getElementById('visibleRowsCount').innerText = count + ' ' + (currentLang === 'ar' ? 'عقد' : 'records');
    }

    function copyText(str) {
      navigator.clipboard.writeText(str).then(() => {
        alert((currentLang === 'ar' ? 'تم نسخ: ' : 'Copied: ') + str);
      });
    }

    // نسخ رابط الاستبيان الخاص بموظف المبيعات (?ref=CODE)
    function copyMySurveyLink(refCode) {
      const origin = window.location.origin;
      const path = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
      const surveyUrl = `${origin}${path}/form.php?ref=${encodeURIComponent(refCode)}`;
      
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(surveyUrl).then(() => {
          showCopyToast(surveyUrl);
        }).catch(() => {
          prompt((currentLang === 'ar' ? 'رابط الاستبيان الخاص بك:' : 'Your Survey Link:'), surveyUrl);
        });
      } else {
        prompt((currentLang === 'ar' ? 'رابط الاستبيان الخاص بك:' : 'Your Survey Link:'), surveyUrl);
      }
    }

    function showCopyToast(url) {
      const toast = document.createElement('div');
      toast.className = "fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-black px-6 py-3 rounded-2xl shadow-2xl text-xs sm:text-sm flex items-center gap-2 animate-bounce";
      toast.innerHTML = `<span>📋</span> <span>${currentLang === 'ar' ? 'تم نسخ رابط الاستبيان الخاص بك بنجاح!' : 'Survey link copied to clipboard!'}</span>`;
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 4000);
    }

    // Delete Client Modal
    function confirmDeleteClient(id, name) {
      document.getElementById('deleteClientId').value = id;
      const desc = (currentLang === 'ar')
        ? `هل أنت متأكد تماماً من حذف عقد العميل (${name}) ومرفقاته؟ لن يمكن استرجاع البيانات بعد الحذف.`
        : `Are you sure you want to permanently delete the contract for (${name}) and all its attachments? This action cannot be undone.`;
      document.getElementById('delClientModalDesc').innerText = desc;
      document.getElementById('deleteClientModal').classList.remove('hidden');
    }

    function closeDeleteClientModal() {
      document.getElementById('deleteClientModal').classList.add('hidden');
    }

    // ── Payment Plan Configuration & Dynamic Modal Handlers ──
    const paymentRatesConfig = {
      "7 Years": ["5% - 5%"],
      "8 Years": ["5% - 7%"],
      "9 Years": ["5% - 12%", "5% - 10%"],
      "10 Years": ["10% - 10%", "5% - 10%"]
    };

    function normalizePeriodKey(val) {
      if (!val) return "7 Years";
      const s = String(val).trim();
      if (s.includes("7")) return "7 Years";
      if (s.includes("8")) return "8 Years";
      if (s.includes("9")) return "9 Years";
      if (s.includes("10")) return "10 Years";
      return "7 Years";
    }

    function handleModalPeriodChange(selectedPeriod, initialRate = null) {
      const periodKey = normalizePeriodKey(selectedPeriod);
      const periodSelect = document.getElementById('edit_payment_period');
      if (periodSelect && periodSelect.value !== periodKey) {
        periodSelect.value = periodKey;
      }

      const rateSelect = document.getElementById('edit_final_rate');
      const chequesInput = document.getElementById('edit_cheques_count');
      const rates = paymentRatesConfig[periodKey] || [];

      if (rateSelect) {
        rateSelect.innerHTML = '';
        rates.forEach(r => {
          const opt = document.createElement('option');
          opt.value = r;
          opt.textContent = r;
          if (initialRate && initialRate === r) {
            opt.selected = true;
          }
          rateSelect.appendChild(opt);
        });
        if (!initialRate && rates.length > 0) {
          rateSelect.value = rates[0];
        }
      }

      const yearsMatch = periodKey.match(/\d+/);
      const years = yearsMatch ? parseInt(yearsMatch[0], 10) : 7;
      const cheques = years * 4;
      if (chequesInput) {
        chequesInput.value = `${cheques} شيك (${years} سنوات × 4)`;
      }
    }

    // Edit Modal Open & Populate
    function openEditModal(client) {
      document.getElementById('edit_client_id').value = client.id;
      document.getElementById('edit_sales_id').value = client.sales_id || '';
      document.getElementById('edit_unit_code').value = client.eoi_number || '';
      document.getElementById('edit_name').value = client.name || '';
      document.getElementById('edit_phone').value = client.phone || '';
      document.getElementById('edit_email').value = client.email || '';
      document.getElementById('edit_id_type').value = client.id_type || 'national_id';
      document.getElementById('edit_id_number').value = client.id_number || '';
      document.getElementById('edit_residence_country').value = client.residence_country || 'داخل جمهورية مصر العربية';
      document.getElementById('edit_contract_location').value = client.contract_location || 'القاهرة';
      document.getElementById('edit_unit_price').value = client.unit_price || '';
      document.getElementById('edit_maintenance_deposit').value = client.maintenance_deposit || '';

      const rawPeriod = client.period || client.payment_period || '7 Years';
      const periodKey = normalizePeriodKey(rawPeriod);
      document.getElementById('edit_payment_period').value = periodKey;
      handleModalPeriodChange(periodKey, client.final_rate || null);

      document.getElementById('edit_paid_5_percent').value = client.paid_5_percent || 'لا';
      document.getElementById('edit_payment_date').value = client.payment_date || '';
      document.getElementById('edit_id_address').value = client.id_address || client.addresses || '';
      document.getElementById('edit_correspondence_address').value = client.correspondence_address || '';
      document.getElementById('edit_general_notes').value = client.general_notes || '';
      document.getElementById('edit_notes').value = client.notes || '';

      const filesContainer = document.getElementById('modal_existing_files');
      filesContainer.innerHTML = '';
      let files = [];
      try {
        files = JSON.parse(client.attachments || '[]');
      } catch(e) {}

      if (Array.isArray(files) && files.length > 0) {
        files.forEach((f, idx) => {
          const div = document.createElement('div');
          div.className = "flex items-center justify-between p-2.5 rounded-xl bg-white border-2 border-sky-200 text-xs font-bold shadow-xs";
          div.innerHTML = `
            <a href="${f}" target="_blank" class="text-slate-950 font-black hover:underline font-mono truncate max-w-xs">📎 ${f}</a>
            <label class="flex items-center gap-1.5 text-red-600 font-black text-[11px] cursor-pointer">
              <input type="checkbox" name="delete_attachments[]" value="${f}" class="rounded text-red-500">
              ${currentLang === 'ar' ? 'حذف الملف' : 'Delete File'}
            </label>
          `;
          filesContainer.appendChild(div);
        });
      } else {
        filesContainer.innerHTML = `<span class="text-xs text-slate-950 font-black">${currentLang === 'ar' ? 'لا توجد ملفات مرفقة حالياً' : 'No attachments currently'}</span>`;
      }

      // عرض عملاء آخرين في نفس الوحدة داخل المودال إن وجدوا
      const otherContainer = document.getElementById('modal_other_clients_container');
      const otherList = document.getElementById('modal_other_clients_list');
      if (otherContainer && otherList) {
        otherList.innerHTML = '';
        if (Array.isArray(client.other_unit_clients) && client.other_unit_clients.length > 0) {
          client.other_unit_clients.forEach(oc => {
            const div = document.createElement('div');
            div.className = "flex items-center justify-between p-2 rounded-xl bg-white border border-sky-300 font-bold shadow-2xs";
            const ocId = oc.id_number ? `<span class="font-mono text-slate-800 text-[11px] font-bold">${escapeHtml(oc.id_number)}</span>` : '';
            div.innerHTML = `<span class="font-black text-slate-950">${escapeHtml(oc.name)}</span> ${ocId}`;
            otherList.appendChild(div);
          });
          otherContainer.classList.remove('hidden');
        } else {
          otherContainer.classList.add('hidden');
        }
      }

      document.getElementById('editModal').classList.remove('hidden');
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function closeEditModal() {
      document.getElementById('editModal').classList.add('hidden');
    }

    // Client Details Modal (عرض ملف العميل والتفاصيل الكاملة)
    let currentViewClient = null;

    function openClientDetailsModal(client) {
      currentViewClient = client;
      
      document.getElementById('details_modal_unit').innerText = client.eoi_number || '-';
      document.getElementById('details_modal_name').innerText = client.name || '-';
      document.getElementById('details_modal_id_num').innerText = client.id_number || '-';
      document.getElementById('details_modal_id_type').innerText = (client.id_type === 'passport') ? '(جواز سفر)' : '(بطاقة رقم قومي)';
      document.getElementById('details_modal_residence').innerText = client.residence_country || 'داخل جمهورية مصر العربية';
      document.getElementById('details_modal_contract_loc').innerText = client.contract_location || 'القاهرة';
      document.getElementById('details_modal_preferred_loc').innerText = client.preferred_signing_location || '-';

      // Phone & Instant WhatsApp Chat
      const rawPhone = (client.phone || '').replace(/[^0-9]/g, '');
      document.getElementById('details_modal_phone').innerText = client.phone || '-';
      const waBtn = document.getElementById('details_modal_wa');
      if (rawPhone && rawPhone.length >= 8) {
        waBtn.href = `https://wa.me/${rawPhone}`;
        waBtn.classList.remove('hidden');
      } else {
        waBtn.classList.add('hidden');
      }

      document.getElementById('details_modal_email').innerText = client.email || '-';
      document.getElementById('details_modal_id_addr').innerText = client.id_address || client.addresses || '-';
      document.getElementById('details_modal_corr_addr').innerText = client.correspondence_address || client.addresses || '-';

      // Financials & Payment Plan
      document.getElementById('details_modal_unit_price').innerText = client.unit_price ? Number(client.unit_price).toLocaleString() + ' ج.م' : '-';
      document.getElementById('details_modal_maintenance').innerText = client.maintenance_deposit ? Number(client.maintenance_deposit).toLocaleString() + ' ج.م' : '-';

      document.getElementById('details_modal_plan').innerText = client.period || client.payment_period || '-';
      document.getElementById('details_modal_rate').innerText = client.final_rate ? `(${client.final_rate})` : '';
      document.getElementById('details_modal_cheques').innerText = client.cheques_count || '-';

      const isPaid = (client.paid_5_percent === 'نعم');
      const statusBadge = document.getElementById('details_modal_paid_badge');
      if (isPaid) {
        statusBadge.className = 'px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-950 border-2 border-emerald-400';
        statusBadge.innerText = '✓ تم استكمال المقدم';
      } else {
        statusBadge.className = 'px-3 py-1 rounded-full text-xs font-black bg-red-100 text-red-950 border-2 border-red-300';
        statusBadge.innerText = '✕ لم يستكمل';
      }
      document.getElementById('details_modal_pay_date').innerText = client.payment_date ? `(بتاريخ: ${client.payment_date})` : '';

      document.getElementById('details_modal_sales').innerText = client.sales_name ? `👤 ${client.sales_name}` : '🌐 مباشر (بدون وسيط)';
      document.getElementById('details_modal_notes').innerText = client.general_notes || client.notes || 'لا توجد ملاحظات أو استثناءات مسجلة.';
      document.getElementById('details_modal_created').innerText = client.created_at || '-';

      // Attachments & Receipts List
      const filesContainer = document.getElementById('details_modal_files');
      filesContainer.innerHTML = '';
      let files = [];
      try {
        files = JSON.parse(client.attachments || '[]');
      } catch(e) {}

      if (Array.isArray(files) && files.length > 0) {
        files.forEach((f, idx) => {
          const ext = f.split('.').pop().toLowerCase();
          const div = document.createElement('div');
          div.className = "flex items-center justify-between p-2.5 rounded-xl bg-sky-50/80 border-2 border-sky-200 text-xs font-bold";
          div.innerHTML = `
            <div class="flex items-center gap-2 truncate max-w-sm">
              <span class="text-base">${ext === 'pdf' ? '📄' : '🖼️'}</span>
              <span class="truncate font-mono text-slate-950 font-black">${escapeHtml(f)}</span>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <button type="button" onclick="previewAttachment('${f}', '${ext}')" class="px-2.5 py-1 bg-white hover:bg-sky-100 border-2 border-sky-300 rounded-lg text-slate-950 font-black cursor-pointer shadow-2xs">
                معاينة
              </button>
              <a href="${f}" download class="px-2.5 py-1 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-black text-xs shadow-2xs">
                تحميل
              </a>
            </div>
          `;
          filesContainer.appendChild(div);
        });
      } else {
        filesContainer.innerHTML = '<span class="text-xs text-slate-500 font-bold block p-2">لا توجد إيصالات أو مستندات مرفوعة لهذا العميل.</span>';
      }

      document.getElementById('clientDetailsModal').classList.remove('hidden');
    }

    function closeClientDetailsModal() {
      document.getElementById('clientDetailsModal').classList.add('hidden');
    }

    function editCurrentViewClient() {
      if (currentViewClient) {
        closeClientDetailsModal();
        openEditModal(currentViewClient);
      }
    }

    // Lightbox Preview for attachments
    function previewAttachment(path, ext) {
      const box = document.getElementById('attachmentLightbox');
      const content = document.getElementById('lightboxContent');
      const title = document.getElementById('lightboxTitle');
      const downloadBtn = document.getElementById('lightboxDownloadBtn');
      
      title.innerText = path;
      downloadBtn.href = path;

      if (ext === 'pdf') {
        content.innerHTML = `<iframe src="${path}" class="w-full h-[70vh] rounded-xl border border-slate-700"></iframe>`;
      } else {
        content.innerHTML = `<img src="${path}" alt="Preview" class="max-h-[70vh] max-w-full rounded-xl object-contain shadow-2xl">`;
      }

      box.classList.remove('hidden');
    }

    function closeLightbox() {
      document.getElementById('attachmentLightbox').classList.add('hidden');
    }
  </script>

</body>
</html>
