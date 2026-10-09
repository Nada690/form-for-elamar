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

// ── تسجيل الدخول ──
$authError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    $inputUsername = trim($_POST['admin_username'] ?? 'admin');
    $inputPassword = (string)$_POST['admin_password'];

    try {
        $adminStmt = $pdo->prepare("SELECT * FROM admins WHERE username = :u LIMIT 1");
        $adminStmt->execute([':u' => $inputUsername]);
        $adminUser = $adminStmt->fetch();

        if ($adminUser && password_verify($inputPassword, $adminUser['password']) && $adminUser['role'] === 'admin') {
            session_regenerate_id(true);
            $_SESSION['is_authorized_admin'] = true;
            $_SESSION['admin_role']          = $adminUser['role'];
            $_SESSION['admin_user']          = $adminUser['username'];
            $_SESSION['admin_logged_time']   = time();
            header("Location: view_clients.php");
            exit;
        } else {
            $authError = ($adminLang === 'en') 
                ? 'Unauthorized access. Invalid credentials or insufficient permissions.' 
                : 'غير مصرح بالدخول. بيانات الدخول غير صحيحة أو لا تملك صلاحيات مسؤول (Admin).';
        }
    } catch (PDOException $e) {
        $authError = ($adminLang === 'en') ? 'Database verification failed.' : 'حدث خطأ أثناء التحقق من الصلاحيات.';
    }
}

// التحقق من الصلاحية
$isAuthenticated = !empty($_SESSION['is_authorized_admin']) 
                   && $_SESSION['is_authorized_admin'] === true 
                   && !empty($_SESSION['admin_role']) 
                   && $_SESSION['admin_role'] === 'admin';

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
    html[dir="rtl"] { font-family: 'Cairo', sans-serif; }
    html[dir="ltr"] { font-family: 'Plus Jakarta Sans', sans-serif; }
  </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
  
  <div class="fixed top-1/4 right-1/4 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="fixed bottom-1/4 left-1/4 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

  <div class="w-full max-w-md bg-slate-900/95 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center relative overflow-hidden backdrop-blur-2xl z-10">
    <div class="h-1.5 bg-gradient-to-r from-amber-500 via-amber-200 to-amber-600 absolute top-0 left-0 right-0"></div>

    <div class="flex justify-end mb-2">
      <button onclick="toggleLoginLang()" class="text-xs font-bold text-amber-300 hover:text-white px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700">
        🌐 <span id="loginLangBtnText">English</span>
      </button>
    </div>

    <div class="inline-block p-1 rounded-2xl bg-gradient-to-tr from-amber-500 via-amber-200 to-amber-600 mb-4 shadow-xl">
      <div class="bg-slate-950 p-2.5 rounded-xl">
        <img src="logo.png" alt="Logo" class="h-12 w-auto mx-auto object-contain">
      </div>
    </div>

    <h2 class="text-2xl font-black text-white mb-1" id="loginHeading">
      لوحة تحكم المسؤول
    </h2>
    <p class="text-xs text-amber-400 font-bold mb-6" id="loginSubheading">
      منطقة محمية مصرح بها فقط لإدارة عقود الساحل الشمالي
    </p>

    <?php if ($authError): ?>
      <div class="bg-red-500/15 border border-red-500/40 text-red-300 text-xs p-3 rounded-2xl mb-4 font-bold">
        <?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="view_clients.php" class="space-y-4">
      <div class="text-right" id="loginFormContainer">
        
        <label class="block text-xs font-bold text-slate-300 mb-1.5" id="lblUsername">
          اسم المستخدم (Username):
        </label>
        <input 
          type="text" 
          name="admin_username" 
          value="admin"
          placeholder="admin" 
          required 
          class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20 transition-all font-mono mb-3"
        >

        <label class="block text-xs font-bold text-slate-300 mb-1.5" id="lblPassword">
          كلمة المرور المشفرة:
        </label>
        <div class="relative">
          <input 
            type="password" 
            name="admin_password" 
            id="adminPwdInput"
            placeholder="••••••••" 
            required 
            autofocus
            class="w-full px-4 py-3 rounded-xl bg-slate-800/90 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20 transition-all font-mono"
          >
          <button 
            type="button" 
            onclick="const p = document.getElementById('adminPwdInput'); p.type = p.type === 'password' ? 'text' : 'password';" 
            class="absolute left-3 top-3.5 text-xs text-slate-400 hover:text-white cursor-pointer"
          >
            👁️
          </button>
        </div>
      </div>

      <button 
        type="submit" 
        id="loginSubmitBtn"
        class="w-full py-3.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-sm shadow-xl hover:shadow-amber-500/25 transition-all cursor-pointer mt-2"
      >
        دخول اللوحة الآمنة
      </button>
    </form>

    <div class="mt-6 pt-5 border-t border-slate-800 flex justify-center text-xs text-slate-400">
      <a href="form.php" class="hover:text-amber-400 transition-colors" id="loginBackForm">
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
      document.getElementById('loginHeading').innerText = isAr ? 'لوحة تحكم المسؤول' : 'Admin Dashboard';
      document.getElementById('loginSubheading').innerText = isAr ? 'منطقة محمية مصرح بها فقط لإدارة عقود الساحل الشمالي' : 'Protected Area - Shores Coastal Contracts Management';
      document.getElementById('lblUsername').innerText = isAr ? 'اسم المستخدم (Username):' : 'Username:';
      document.getElementById('lblPassword').innerText = isAr ? 'كلمة المرور المشفرة:' : 'Password:';
      document.getElementById('loginSubmitBtn').innerText = isAr ? 'دخول اللوحة الآمنة' : 'Login to Dashboard';
      document.getElementById('loginBackForm').innerText = isAr ? '← العودة لاستمارة التعاقد' : '← Back to Main Form';
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

// ── إجراء الحذف (Delete Action) ──
if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['client_id'])) {
    $delId = (int)$_POST['client_id'];
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

// ── إجراء التعديل (Edit Action) ──
if (isset($_POST['action']) && $_POST['action'] === 'edit' && !empty($_POST['client_id'])) {
    $editId        = (int)$_POST['client_id'];
    $editName      = trim($_POST['edit_name'] ?? '');
    $editPhone     = trim($_POST['edit_phone'] ?? '');
    $editEmail     = trim($_POST['edit_email'] ?? '');
    $editIdType    = trim($_POST['edit_id_type'] ?? 'national_id');
    $editIdNumber  = trim($_POST['edit_id_number'] ?? '');
    $editUnitCode  = trim($_POST['edit_unit_code'] ?? '');
    $editLocation  = trim($_POST['edit_contract_location'] ?? 'القاهرة');
    $editPayment   = trim($_POST['edit_payment_period'] ?? '');
    $editPaid5     = trim($_POST['edit_paid_5_percent'] ?? '');
    $editAddress   = trim($_POST['edit_addresses'] ?? '');
    $editNotes     = trim($_POST['edit_notes'] ?? '');
    $editSalesId   = !empty($_POST['edit_sales_id']) ? (int)$_POST['edit_sales_id'] : null;

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
                        contract_location = :contract_location, 
                        eoi_number = :unit_code, 
                        payment_period = :payment_period, 
                        paid_5_percent = :paid_5_percent, 
                        addresses = :addresses,
                        notes = :notes,
                        attachments = :attachments,
                        sales_id = :sales_id
                       WHERE id = :id";
            $updStmt = $pdo->prepare($updSql);
            $updStmt->execute([
                ':name'              => $editName,
                ':phone'             => $editPhone,
                ':email'             => $editEmail,
                ':id_type'           => $editIdType,
                ':id_number'         => $editIdNumber,
                ':contract_location' => $editLocation,
                ':unit_code'         => $editUnitCode,
                ':payment_period'    => $editPayment,
                ':paid_5_percent'    => $editPaid5,
                ':addresses'         => $editAddress,
                ':notes'             => $editNotes,
                ':attachments'       => $finalAttachmentsJson,
                ':sales_id'          => $editSalesId,
                ':id'                => $editId
            ]);
            $notification = "تم تحديث وحفظ بيانات العميل رقم #$editId والمرفقات ومسؤول المبيعات بنجاح.";
        }
    } catch (PDOException $e) {
        $notification = "فشل التحديث: " . $e->getMessage();
        $notificationType = 'error';
    }
}

// ── تصدير CSV ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=shores_clients_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['رقم العقد (ID)', 'كود الوحدة', 'موظف المبيعات (Sales)', 'اسم العميل / الشركاء بالعقد', 'رقم الهاتف', 'البريد الإلكتروني', 'نوع الهوية', 'رقم الوثيقة', 'مكان التعاقد', 'فترة السداد', 'سداد الـ 5%', 'العنوان بالتفصيل', 'الملاحظات', 'تاريخ التسجيل']);
    
    $exportStmt = $pdo->query("SELECT c.id, c.eoi_number, IFNULL(s.name, 'مباشر (بدون وسيط)'), c.name, c.phone, c.email, c.id_type, c.id_number, c.contract_location, c.payment_period, c.paid_5_percent, c.addresses, c.notes, c.created_at FROM clients c LEFT JOIN sales_employees s ON c.sales_id = s.id ORDER BY c.id DESC");
    while ($row = $exportStmt->fetch(PDO::FETCH_NUM)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// ── استرجاع البيانات والإحصائيات ──
try {
    $filterSalesId = isset($_GET['sales_id']) && $_GET['sales_id'] !== '' ? (int)$_GET['sales_id'] : null;
    $filterLoc     = $_GET['location'] ?? '';
    
    $countQuery = "SELECT COUNT(*) AS total FROM clients";
    if ($filterSalesId !== null) $countQuery .= " WHERE sales_id = :sales_id";
    $countStmt = $pdo->prepare($countQuery);
    if ($filterSalesId !== null) $countStmt->execute([':sales_id' => $filterSalesId]);
    else $countStmt->execute();
    $totalClients = (int)$countStmt->fetch()['total'];

    $paidQuery = "SELECT paid_5_percent, COUNT(*) as count FROM clients";
    if ($filterSalesId !== null) $paidQuery .= " WHERE sales_id = :sales_id";
    $paidQuery .= " GROUP BY paid_5_percent";
    $paidStmt = $pdo->prepare($paidQuery);
    if ($filterSalesId !== null) $paidStmt->execute([':sales_id' => $filterSalesId]);
    else $paidStmt->execute();
    $paidStats = $paidStmt->fetchAll();

    $locQuery = "SELECT contract_location, COUNT(*) as count FROM clients GROUP BY contract_location";
    $locStats = $pdo->query($locQuery)->fetchAll();

    $salesStmt = $pdo->query("SELECT id, name, slug FROM sales_employees WHERE active = 1 ORDER BY name ASC");
    $salesList = $salesStmt->fetchAll();

    $clientQuery = "SELECT c.*, s.name as sales_name, s.email as sales_email FROM clients c 
                    LEFT JOIN sales_employees s ON c.sales_id = s.id";
    $whereParts = [];
    $params = [];
    if ($filterSalesId !== null) {
        $whereParts[] = "c.sales_id = :sales_id";
        $params[':sales_id'] = $filterSalesId;
    }
    if (!empty($filterLoc)) {
        $whereParts[] = "c.contract_location = :loc";
        $params[':loc'] = $filterLoc;
    }
    if (!empty($whereParts)) {
        $clientQuery .= " WHERE " . implode(' AND ', $whereParts);
    }
    $clientQuery .= " ORDER BY c.id DESC";
    $stmt = $pdo->prepare($clientQuery);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

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
    html[dir="rtl"] { font-family: 'Cairo', sans-serif; }
    html[dir="ltr"] { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'Plus Jakarta Sans', monospace; }
    
    .glass-card {
      background: rgba(15, 23, 42, 0.9);
      backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }
  </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-3 sm:p-6 lg:p-8 selection:bg-amber-500/30 selection:text-amber-200">

  <!-- Ambient light -->
  <div class="fixed top-0 right-10 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="fixed bottom-0 left-10 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

  <div class="max-w-[1600px] mx-auto space-y-6 relative z-10">

    <!-- ═══════════════ TOP HEADER ═══════════════ -->
    <header class="glass-card rounded-3xl p-5 sm:p-6 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xl border border-slate-800">
      
      <div class="flex items-center gap-4">
        <div class="p-2 rounded-2xl bg-gradient-to-tr from-amber-500/20 via-amber-400/10 to-transparent border border-amber-500/30 shadow-lg">
          <img src="logo.png" alt="Logo" class="h-12 w-auto object-contain">
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-500/15 text-amber-400 border border-amber-500/30">
              EXECUTIVE DASHBOARD
            </span>
            <span class="text-xs text-slate-400" data-i18n="dashBadge">الإدارة المركزية</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-white mt-1" data-i18n="dashTitle">
            لوحة تحكم وإدارة عقود شورز الساحلية
          </h1>
          <p class="text-xs text-slate-400" data-i18n="dashSub">
            تتبع ومراجعة استبيانات وعقود الساحل الشمالي مع إسناد موظفي المبيعات
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
        
        <!-- Language Switcher Button -->
        <button 
          id="langToggleBtn" 
          onclick="toggleLanguage()" 
          class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-amber-300 text-xs sm:text-sm font-bold rounded-2xl transition-all border border-amber-500/30 flex items-center gap-1.5 cursor-pointer shadow-sm hover:border-amber-400"
        >
          <span>🌐</span> <span id="langBtnText">English</span>
        </button>

        <!-- Manage Sales -->
        <a href="manage_sales.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs sm:text-sm font-black rounded-2xl transition-all shadow-lg hover:shadow-purple-500/20 flex items-center gap-2">
          <span>👥</span>
          <span data-i18n="btnManageSales">إدارة مسؤولي المبيعات</span> (<?= count($salesList) ?>)
        </a>

        <!-- Export CSV -->
        <a href="view_clients.php?export=csv" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs sm:text-sm font-bold rounded-2xl transition-all shadow-md flex items-center gap-1.5">
          <span>📥</span> <span data-i18n="btnExportCsv">تصدير Excel (CSV)</span>
        </a>

        <!-- Print -->
        <button onclick="window.print()" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs sm:text-sm font-bold rounded-2xl transition-all shadow-md flex items-center gap-1.5 cursor-pointer">
          <span>🖨️</span> <span data-i18n="btnPrint">طباعة</span>
        </button>

        <!-- New Form -->
        <a href="form.php" target="_blank" class="px-3.5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs sm:text-sm font-black rounded-2xl transition-all shadow-md flex items-center gap-1.5">
          <span>+</span> <span data-i18n="btnNewForm">استمارة التعاقد</span>
        </a>

        <!-- Logout -->
        <a href="view_clients.php?logout=1" class="px-3 py-2 bg-red-500/15 hover:bg-red-500/25 text-red-400 border border-red-500/30 text-xs sm:text-sm font-bold rounded-2xl transition-all" data-i18n="btnLogout">
          تسجيل خروج
        </a>
      </div>

    </header>

    <!-- ═══════════════ NOTIFICATION ═══════════════ -->
    <?php if ($notification): ?>
      <div class="p-4 rounded-2xl flex items-center justify-between gap-3 shadow-lg border <?= $notificationType === 'error' ? 'bg-red-500/15 border-red-500/40 text-red-200' : 'bg-emerald-500/15 border-emerald-500/40 text-emerald-200' ?>">
        <div class="flex items-center gap-2 text-sm font-bold">
          <span><?= $notificationType === 'error' ? '⚠️' : '✅' ?></span>
          <span><?= htmlspecialchars($notification, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-xs opacity-70 hover:opacity-100 cursor-pointer">✕</button>
      </div>
    <?php endif; ?>

    <!-- ═══════════════ KPI STATS WITH ANIMATED COUNTERS ═══════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- إجمالي العقود -->
      <div class="glass-card rounded-3xl p-5 border border-slate-800 shadow-xl flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-2xl text-amber-400">
          📑
        </div>
        <div>
          <p class="text-xs text-slate-400 font-bold uppercase tracking-wider" data-i18n="kpiTotal">إجمالي العقود المسجلة</p>
          <div class="flex items-baseline gap-2 mt-0.5">
            <span class="text-3xl font-black text-white count-up" data-target="<?= $totalClients ?>"><?= $totalClients ?></span>
            <span class="text-xs text-amber-400 font-semibold" data-i18n="kpiTotalSub">عقد معتمد</span>
          </div>
        </div>
      </div>

      <!-- حالة سداد الـ 5% -->
      <div class="glass-card rounded-3xl p-5 border border-slate-800 shadow-xl flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-2xl text-emerald-400">
          💳
        </div>
        <div>
          <p class="text-xs text-slate-400 font-bold uppercase tracking-wider" data-i18n="kpi5Percent">حالة سداد نسبة الـ 5%</p>
          <div class="flex items-center gap-2 mt-1.5">
            <?php foreach ($paidStats as $ps): ?>
              <span class="px-2.5 py-0.5 rounded-lg text-xs font-black <?= ($ps['paid_5_percent'] === 'نعم') ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30' ?>">
                <?= htmlspecialchars($ps['paid_5_percent'] ?: 'غير محدد') ?>: <?= $ps['count'] ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- توزيع الفروع -->
      <div class="glass-card rounded-3xl p-5 border border-slate-800 shadow-xl flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-2xl text-cyan-400">
          🏛️
        </div>
        <div>
          <p class="text-xs text-slate-400 font-bold uppercase tracking-wider" data-i18n="kpiBranches">فروع التعاقد</p>
          <div class="flex items-center gap-2 mt-1.5">
            <?php foreach ($locStats as $ls): ?>
              <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-slate-800 text-cyan-300 border border-slate-700">
                <?= htmlspecialchars($ls['contract_location'] ?: 'غير محدد') ?>: <?= $ls['count'] ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- مسؤولو المبيعات -->
      <div class="glass-card rounded-3xl p-5 border border-slate-800 shadow-xl flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-2xl text-purple-400">
          👥
        </div>
        <div>
          <p class="text-xs text-slate-400 font-bold uppercase tracking-wider" data-i18n="kpiSalesTeam">فريق المبيعات</p>
          <div class="flex items-baseline gap-2 mt-0.5">
            <span class="text-3xl font-black text-purple-400 count-up" data-target="<?= count($salesList) ?>"><?= count($salesList) ?></span>
            <span class="text-xs text-slate-400 font-semibold" data-i18n="kpiSalesTeamSub">موظف نشط</span>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══════════════ FILTERS & LIVE SEARCH TOOLBAR ═══════════════ -->
    <div class="glass-card rounded-3xl p-4 sm:p-5 border border-slate-800 shadow-xl flex flex-col md:flex-row items-center justify-between gap-4">
      
      <!-- Live Search Box -->
      <div class="w-full md:w-96 relative">
        <input 
          type="text" 
          id="tableSearchInput" 
          placeholder="بحث سريع: اسم العميل، الهاتف، كود الوحدة، السيلز..." 
          data-i18n-placeholder="phSearchClients"
          oninput="liveFilterClients(this.value)"
          class="w-full pl-4 pr-10 py-3 rounded-2xl bg-slate-900 border border-slate-700 text-white placeholder-slate-400 text-xs sm:text-sm focus:outline-none focus:border-amber-400 transition-all font-semibold"
        >
        <span class="absolute right-3.5 top-3.5 text-slate-400 text-sm">🔍</span>
      </div>

      <!-- Dropdown Filters (Form Submit) -->
      <form method="GET" action="view_clients.php" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
        
        <!-- Sales Filter -->
        <div class="flex items-center gap-1.5">
          <span class="text-xs text-slate-400 font-bold" data-i18n="filterSalesLbl">السيلز:</span>
          <select 
            name="sales_id" 
            onchange="this.form.submit()" 
            class="px-3 py-2.5 bg-slate-900 border border-slate-700 text-amber-300 text-xs font-bold rounded-xl cursor-pointer focus:outline-none focus:border-amber-400"
          >
            <option value="" data-i18n="optAllSales">كل موظفي Sales (عرض الكل)</option>
            <?php foreach ($salesList as $s): ?>
              <option value="<?= $s['id'] ?>" <?= ($filterSalesId !== null && $filterSalesId == $s['id']) ? 'selected' : '' ?>>
                👤 <?= htmlspecialchars($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Branch Filter -->
        <div class="flex items-center gap-1.5">
          <span class="text-xs text-slate-400 font-bold" data-i18n="filterBranchLbl">الفرع:</span>
          <select 
            name="location" 
            onchange="this.form.submit()" 
            class="px-3 py-2.5 bg-slate-900 border border-slate-700 text-cyan-300 text-xs font-bold rounded-xl cursor-pointer focus:outline-none focus:border-amber-400"
          >
            <option value="" data-i18n="optAllBranches">كل الفروع</option>
            <option value="القاهرة" <?= ($filterLoc === 'القاهرة') ? 'selected' : '' ?> data-i18n="optCairoBranch">🏛️ القاهرة</option>
            <option value="الإسكندرية" <?= ($filterLoc === 'الإسكندرية') ? 'selected' : '' ?> data-i18n="optAlexBranch">🌊 الإسكندرية</option>
          </select>
        </div>

        <?php if ($filterSalesId !== null || !empty($filterLoc)): ?>
          <a href="view_clients.php" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-red-400 text-xs font-bold rounded-xl border border-slate-700" data-i18n="btnClearFilters">
            ✕ إلغاء الفلترة
          </a>
        <?php endif; ?>

      </form>

    </div>

    <!-- ═══════════════ MAIN CLIENTS TABLE ═══════════════ -->
    <div class="glass-card rounded-3xl border border-slate-800 shadow-2xl overflow-hidden">
      
      <div class="p-5 border-b border-slate-800 flex justify-between items-center bg-slate-900/60">
        <div class="flex items-center gap-3">
          <h2 class="font-black text-base sm:text-lg text-white" data-i18n="tblTitle">جدول عقود واستبيانات العملاء</h2>
          <span class="text-xs bg-amber-500/15 text-amber-300 border border-amber-500/30 px-3 py-0.5 rounded-full font-mono font-bold" id="visibleRowsCount">
            <?= count($clients) ?> عقد
          </span>
        </div>
        <span class="text-xs text-slate-400 font-mono">آخر تحديث: <?= date('Y-m-d H:i') ?></span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs sm:text-sm text-right" id="clientsTable">
          <thead class="bg-slate-900 text-amber-400 font-black border-b border-slate-800 text-[11px] sm:text-xs uppercase tracking-wider">
            <tr>
              <th class="p-4 w-12 text-center">#</th>
              <th class="p-4 w-16">ID</th>
              <th class="p-4" data-i18n="thSales">مسؤول المبيعات (Sales)</th>
              <th class="p-4" data-i18n="thUnit">كود الوحدة</th>
              <th class="p-4" data-i18n="thBranch">الفرع</th>
              <th class="p-4" data-i18n="thName">اسم العميل / الشركاء</th>
              <th class="p-4" data-i18n="thPhone">أرقام التواصل</th>
              <th class="p-4">Gmail</th>
              <th class="p-4" data-i18n="thIdDoc">إثبات الشخصية</th>
              <th class="p-4" data-i18n="thPlan">فترة السداد</th>
              <th class="p-4 text-center" data-i18n="th5Percent">سداد الـ 5%</th>
              <th class="p-4" data-i18n="thAddress">العنوان المعتمد</th>
              <th class="p-4" data-i18n="thNotes">الملاحظات</th>
              <th class="p-4" data-i18n="thDocs">المستندات</th>
              <th class="p-4" data-i18n="thDate">التاريخ</th>
              <th class="p-4 text-center" data-i18n="thActions">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/80 text-slate-200" id="clientsTableBody">
            <?php if (empty($clients)): ?>
              <tr>
                <td colspan="15" class="p-14 text-center text-slate-400 font-semibold" data-i18n="noClientsFound">
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
                $clientJson = htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8');
                $loc = $client['contract_location'] ?? 'القاهرة';
              ?>
                <tr 
                  class="hover:bg-slate-800/40 transition-colors client-row" 
                  data-search="<?= htmlspecialchars(mb_strtolower($client['name'] . ' ' . $client['phone'] . ' ' . $client['eoi_number'] . ' ' . ($client['sales_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                >
                  
                  <!-- م (تسلسلي مرئي) -->
                  <td class="p-4 text-center font-bold text-amber-400 font-mono bg-slate-950/30">
                    <?= $seq++ ?>
                  </td>

                  <!-- ID -->
                  <td class="p-4 text-slate-400 font-mono text-xs font-bold">
                    #<?= htmlspecialchars($client['id']) ?>
                  </td>

                  <!-- موظف السيلز المخصص (Sales Rep) -->
                  <td class="p-4">
                    <?php if (!empty($client['sales_name'])): ?>
                      <a 
                        href="view_clients.php?sales_id=<?= $client['sales_id'] ?>" 
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-black bg-purple-500/15 text-purple-300 border border-purple-500/30 hover:bg-purple-500/25 transition-colors"
                        title="فلترة وعرض عملاء هذا الموظف فقط"
                      >
                        <span>👤</span>
                        <span><?= htmlspecialchars($client['sales_name']) ?></span>
                      </a>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-bold bg-slate-800 text-slate-400 border border-slate-700" data-i18n="badgeDirect">
                        🌐 مباشر
                      </span>
                    <?php endif; ?>
                  </td>

                  <!-- كود الوحدة مع نسخ بنقرة واحدة -->
                  <td class="p-4 font-mono font-black text-amber-300">
                    <button 
                      type="button"
                      onclick="copyText('<?= htmlspecialchars($client['eoi_number']) ?>')" 
                      class="bg-amber-500/10 hover:bg-amber-500/20 px-2.5 py-1 rounded-xl border border-amber-500/20 text-xs cursor-pointer"
                      title="Click to copy unit code"
                    >
                      <?= htmlspecialchars($client['eoi_number']) ?>
                    </button>
                  </td>

                  <!-- فرع التعاقد -->
                  <td class="p-4 text-xs font-bold">
                    <span class="px-2.5 py-1 rounded-xl <?= ($loc === 'الإسكندرية') ? 'bg-cyan-500/15 text-cyan-300 border border-cyan-500/30' : 'bg-amber-500/15 text-amber-300 border border-amber-500/30' ?>">
                      <?= ($loc === 'الإسكندرية') ? '🌊 ' : '🏛️ ' ?>
                      <?= htmlspecialchars($loc) ?>
                    </span>
                  </td>

                  <!-- اسم العميل والشركاء -->
                  <td class="p-4 font-bold text-white max-w-[200px] break-words">
                    <?= htmlspecialchars($client['name']) ?>
                  </td>

                  <!-- أرقام الهاتف مع واتساب السريع -->
                  <td class="p-4 font-mono text-xs text-emerald-400 dir-ltr text-right max-w-[170px]">
                    <div class="flex items-center justify-end gap-1.5">
                      <?php 
                        $rawPhone = preg_replace('/[^0-9]/', '', $client['phone']);
                      ?>
                      <a 
                        href="https://wa.me/<?= $rawPhone ?>" 
                        target="_blank" 
                        class="p-1 rounded-md bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300"
                        title="WhatsApp Chat / محادثة واتساب"
                      >
                        💬
                      </a>
                      <span class="font-semibold"><?= htmlspecialchars($client['phone']) ?></span>
                    </div>
                  </td>

                  <!-- البريد الإلكتروني -->
                  <td class="p-4 font-mono text-xs text-cyan-300 dir-ltr text-right">
                    <?= htmlspecialchars($client['email'] ?: '-') ?>
                  </td>

                  <!-- إثبات الهوية -->
                  <td class="p-4 text-xs font-mono">
                    <div class="text-slate-300"><?= htmlspecialchars($client['id_number'] ?: '-') ?></div>
                    <div class="text-[10px] text-slate-500 font-bold"><?= ($client['id_type'] === 'passport') ? 'جواز سفر' : 'رقم قومي' ?></div>
                  </td>

                  <!-- فترة السداد -->
                  <td class="p-4 text-xs font-bold text-amber-200">
                    <?= htmlspecialchars($client['payment_period'] ?: '-') ?>
                  </td>

                  <!-- سداد الـ 5% -->
                  <td class="p-4 text-center">
                    <?php if ($client['paid_5_percent'] === 'نعم'): ?>
                      <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        ✓ تم
                      </span>
                    <?php else: ?>
                      <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-red-500/20 text-red-400 border border-red-500/30">
                        ✕ لا
                      </span>
                    <?php endif; ?>
                  </td>

                  <!-- العنوان -->
                  <td class="p-4 text-xs text-slate-300 max-w-[180px] truncate" title="<?= htmlspecialchars($client['addresses']) ?>">
                    <?= htmlspecialchars($client['addresses'] ?: '-') ?>
                  </td>

                  <!-- الملاحظات -->
                  <td class="p-4 text-xs text-amber-200/90 max-w-[170px] truncate" title="<?= htmlspecialchars($client['notes'] ?? '') ?>">
                    <?= !empty($client['notes']) ? htmlspecialchars($client['notes']) : '<span class="text-slate-500">-</span>' ?>
                  </td>

                  <!-- المستندات والمرفقات -->
                  <td class="p-4">
                    <?php if (!empty($attachments)): ?>
                      <div class="flex flex-wrap gap-1 max-w-[140px]">
                        <?php foreach ($attachments as $fIdx => $f): 
                          $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                        ?>
                          <button 
                            type="button" 
                            onclick="previewAttachment('<?= htmlspecialchars($f) ?>', '<?= $ext ?>')"
                            class="px-2 py-0.5 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-700 text-[10px] font-mono text-amber-300 flex items-center gap-1 cursor-pointer transition-colors"
                          >
                            <span><?= ($ext === 'pdf') ? '📄' : '🖼️' ?></span>
                            <span>#<?= $fIdx+1 ?></span>
                          </button>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-slate-500 text-xs" data-i18n="lblNoFiles">لا يوجد</span>
                    <?php endif; ?>
                  </td>

                  <!-- التاريخ -->
                  <td class="p-4 font-mono text-[11px] text-slate-400">
                    <?= date('Y-m-d H:i', strtotime($client['created_at'])) ?>
                  </td>

                  <!-- إجراءات -->
                  <td class="p-4 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                      
                      <!-- زر التعديل -->
                      <button 
                        type="button"
                        onclick="openEditModal(<?= $clientJson ?>)"
                        class="p-2 bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 border border-amber-500/30 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        title="Edit / تعديل بيانات العقد"
                      >
                        ✏️
                      </button>

                      <!-- زر الحذف -->
                      <button 
                        type="button"
                        onclick="confirmDeleteClient(<?= $client['id'] ?>, '<?= addslashes($client['name']) ?>')"
                        class="p-2 bg-red-500/15 hover:bg-red-500/25 text-red-400 border border-red-500/30 rounded-xl text-xs font-bold transition-all cursor-pointer"
                        title="Delete / حذف العقد"
                      >
                        🗑️
                      </button>

                    </div>
                  </td>

                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>

  </div>

  <!-- ═══════════════ EDIT MODAL ═══════════════ -->
  <div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md hidden">
    <div class="bg-slate-900 border border-slate-700 rounded-3xl p-6 sm:p-8 max-w-2xl w-full shadow-2xl text-slate-100 max-h-[92vh] overflow-y-auto">
      
      <div class="flex justify-between items-center border-b border-slate-800 pb-3 mb-5">
        <h3 class="font-black text-lg text-amber-400 flex items-center gap-2">
          <span>✏️</span> <span data-i18n="editModalTitle">تعديل بيانات العقد والعميل</span>
        </h3>
        <button onclick="closeEditModal()" class="text-slate-400 hover:text-white text-xl cursor-pointer">✕</button>
      </div>

      <form method="POST" action="view_clients.php" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="client_id" id="edit_client_id">

        <!-- إسناد موظف السيلز (Sales Attribution) -->
        <div class="p-3 rounded-2xl bg-slate-950/60 border border-purple-500/30">
          <label class="block text-xs font-bold text-purple-300 mb-1.5" data-i18n="editModalSalesLbl">👤 مسؤول المبيعات (Sales Rep المنسوب له هذا العقد):</label>
          <select name="edit_sales_id" id="edit_sales_id" class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-white text-xs font-bold focus:border-amber-400 focus:outline-none">
            <option value="" data-i18n="optDirectNoSales">-- مباشر (بدون وسيط / Direct) --</option>
            <?php foreach ($salesList as $s): ?>
              <option value="<?= $s['id'] ?>">👤 <?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- كود الوحدة واسم العميل -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1" data-i18n="thUnit">كود الوحدة:</label>
            <input type="text" name="edit_unit_code" id="edit_unit_code" required class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-amber-300 font-mono text-sm focus:border-amber-400 focus:outline-none uppercase font-bold">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1" data-i18n="thName">اسم العميل والشركاء:</label>
            <input type="text" name="edit_name" id="edit_name" required class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm focus:border-amber-400 focus:outline-none font-semibold">
          </div>
        </div>

        <!-- الهاتف والبريد -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1" data-i18n="thPhone">رقم الهاتف:</label>
            <input type="text" name="edit_phone" id="edit_phone" required class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-emerald-400 font-mono text-sm focus:border-amber-400 focus:outline-none dir-ltr text-right">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">البريد الإلكتروني (Gmail):</label>
            <input type="email" name="edit_email" id="edit_email" required class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-cyan-300 font-mono text-sm focus:border-amber-400 focus:outline-none dir-ltr text-right">
          </div>
        </div>

        <!-- الهوية ومكان التعاقد -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1" data-i18n="lblDocType">نوع الوثيقة:</label>
            <select name="edit_id_type" id="edit_id_type" class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-white text-xs font-bold">
              <option value="national_id">بطاقة الرقم القومي</option>
              <option value="passport">جواز السفر (Passport)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1" data-i18n="lblDocNum">رقم الوثيقة:</label>
            <input type="text" name="edit_id_number" id="edit_id_number" class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-white font-mono text-sm focus:border-amber-400 focus:outline-none">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1" data-i18n="thBranch">فرع التعاقد:</label>
            <select name="edit_contract_location" id="edit_contract_location" class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-amber-300 text-xs font-bold">
              <option value="القاهرة">🏛️ فرع القاهرة</option>
              <option value="الإسكندرية">🌊 فرع الإسكندرية</option>
            </select>
          </div>
        </div>

        <!-- فترة السداد والـ 5% -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1" data-i18n="thPlan">فترة السداد:</label>
            <input type="text" name="edit_payment_period" id="edit_payment_period" class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-amber-200 text-xs font-bold focus:border-amber-400 focus:outline-none">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-300 mb-1" data-i18n="th5Percent">سداد الـ 5%:</label>
            <select name="edit_paid_5_percent" id="edit_paid_5_percent" class="w-full px-3 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-white text-xs font-bold">
              <option value="نعم">نعم (تم السداد)</option>
              <option value="لا">لا (لم يتم)</option>
            </select>
          </div>
        </div>

        <!-- العنوان -->
        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1" data-i18n="thAddress">العنوان بالتفصيل:</label>
          <textarea name="edit_addresses" id="edit_addresses" rows="2" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-white text-xs focus:border-amber-400 focus:outline-none font-semibold"></textarea>
        </div>

        <!-- الملاحظات والطلبات الخاصة -->
        <div>
          <label class="block text-xs font-bold text-slate-300 mb-1" data-i18n="lblNotes">الملاحظات والطلبات الخاصة (الحد الأقصى 250 حرف):</label>
          <textarea name="edit_notes" id="edit_notes" rows="2" maxlength="250" class="w-full px-3 py-2 rounded-xl bg-slate-800 border border-slate-700 text-amber-200 text-xs focus:border-amber-400 focus:outline-none font-semibold"></textarea>
        </div>

        <!-- المرفقات الحالية وإضافة جديدة -->
        <div class="border border-slate-800 rounded-2xl p-4 bg-slate-950/50 space-y-3">
          <label class="block text-xs font-black text-amber-300" data-i18n="editModalDocs">📎 إدارة المستندات والمرفقات:</label>
          <div id="modal_existing_files" class="space-y-2"></div>
          <div>
            <label class="block text-[11px] font-bold text-slate-400 mb-1" data-i18n="editModalUploadMore">+ رفع ملفات إضافية:</label>
            <input 
              type="file" 
              name="new_attachments[]" 
              multiple 
              accept=".pdf,image/*" 
              class="w-full text-xs text-slate-300 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-500/20 file:text-amber-300 hover:file:bg-amber-500/30 cursor-pointer"
            >
          </div>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
          <button type="button" onclick="closeEditModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 rounded-xl text-xs font-bold cursor-pointer" data-i18n="btnCancel">
            إلغاء
          </button>
          <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black rounded-xl text-xs shadow-lg cursor-pointer" data-i18n="btnSaveEdit">
            💾 حفظ التعديلات
          </button>
        </div>

      </form>

    </div>
  </div>

  <!-- ═══════════════ DELETE CONFIRMATION MODAL ═══════════════ -->
  <div id="deleteClientModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md hidden">
    <div class="glass-card max-w-md w-full p-6 rounded-3xl border border-red-500/40 text-center shadow-2xl">
      <div class="w-14 h-14 rounded-2xl bg-red-500/20 text-red-400 flex items-center justify-center text-2xl mx-auto mb-3">
        ⚠️
      </div>
      <h3 class="text-base font-black text-white mb-1" data-i18n="delClientModalTitle">تأكيد حذف عقد العميل</h3>
      <p class="text-xs text-slate-300 mb-5" id="delClientModalDesc">
        هل أنت متأكد تماماً من حذف سجل هذا العميل ومرفقاته؟ لن يمكن استرجاع البيانات بعد الحذف.
      </p>

      <form method="POST" action="view_clients.php" class="flex gap-3 justify-center">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="client_id" id="deleteClientId">
        
        <button 
          type="button" 
          onclick="closeDeleteClientModal()" 
          class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs cursor-pointer"
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
    <div class="max-w-4xl w-full max-h-[90vh] bg-slate-900 border border-slate-700 rounded-3xl p-4 shadow-2xl relative flex flex-col items-center" onclick="event.stopPropagation()">
      <div class="w-full flex justify-between items-center mb-3 pb-2 border-b border-slate-800">
        <span class="text-xs font-bold text-amber-300 font-mono" id="lightboxTitle">معاينة المستند</span>
        <div class="flex items-center gap-2">
          <a id="lightboxDownloadBtn" href="#" target="_blank" download class="text-xs px-3 py-1 bg-amber-500 text-slate-950 font-bold rounded-lg" data-i18n="btnDownloadDoc">تحميل</a>
          <button onclick="closeLightbox()" class="text-slate-400 hover:text-white text-lg cursor-pointer">✕</button>
        </div>
      </div>
      <div id="lightboxContent" class="w-full flex items-center justify-center overflow-auto max-h-[75vh]"></div>
    </div>
  </div>

  <!-- ═══════════════ SCRIPT LOGIC (BILINGUAL I18N + LIVE SEARCH) ═══════════════ -->
  <script>
    // ── BILINGUAL DICTIONARY ──
    const dashboardTranslations = {
      ar: {
        dashBadge: "الإدارة المركزية",
        dashTitle: "لوحة تحكم وإدارة عقود شورز الساحلية",
        dashSub: "تتبع ومراجعة استبيانات وعقود الساحل الشمالي مع إسناد موظفي المبيعات",
        btnManageSales: "إدارة مسؤولي المبيعات",
        btnExportCsv: "تصدير Excel (CSV)",
        btnPrint: "طباعة",
        btnNewForm: "استمارة التعاقد",
        btnLogout: "تسجيل خروج",
        kpiTotal: "إجمالي العقود المسجلة",
        kpiTotalSub: "عقد معتمد",
        kpi5Percent: "حالة سداد نسبة الـ 5%",
        kpiBranches: "فروع التعاقد",
        kpiSalesTeam: "فريق المبيعات",
        kpiSalesTeamSub: "موظف نشط",
        phSearchClients: "بحث سريع: اسم العميل، الهاتف، كود الوحدة، السيلز...",
        filterSalesLbl: "السيلز:",
        optAllSales: "كل موظفي Sales (عرض الكل)",
        filterBranchLbl: "الفرع:",
        optAllBranches: "كل الفروع",
        optCairoBranch: "🏛️ القاهرة",
        optAlexBranch: "🌊 الإسكندرية",
        btnClearFilters: "✕ إلغاء الفلترة",
        tblTitle: "جدول عقود واستبيانات العملاء",
        thSales: "مسؤول المبيعات (Sales)",
        thUnit: "كود الوحدة",
        thBranch: "الفرع",
        thName: "اسم العميل / الشركاء",
        thPhone: "أرقام التواصل",
        thIdDoc: "إثبات الشخصية",
        thPlan: "فترة السداد",
        th5Percent: "سداد الـ 5%",
        thAddress: "العنوان المعتمد",
        thNotes: "الملاحظات",
        thDocs: "المستندات",
        thDate: "التاريخ",
        thActions: "إجراءات",
        noClientsFound: "لا توجد أي بيانات أو عقود مسجلة تطابق معايير البحث الحالية.",
        badgeDirect: "🌐 مباشر",
        lblNoFiles: "لا يوجد",
        lblNotes: "الملاحظات والطلبات الخاصة:",
        editModalTitle: "تعديل بيانات العقد والعميل",
        editModalSalesLbl: "👤 مسؤول المبيعات (Sales Rep المنسوب له هذا العقد):",
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
        dashBadge: "Central Headquarters",
        dashTitle: "Shores Coastal Contracts Management Dashboard",
        dashSub: "Track and verify North Coast contracts with dedicated sales consultant attribution",
        btnManageSales: "Manage Sales Team",
        btnExportCsv: "Export Excel (CSV)",
        btnPrint: "Print",
        btnNewForm: "+ Contract Form",
        btnLogout: "Logout",
        kpiTotal: "Total Registered Contracts",
        kpiTotalSub: "Verified Contracts",
        kpi5Percent: "5% Payment Status",
        kpiBranches: "Contract Branches",
        kpiSalesTeam: "Sales Team",
        kpiSalesTeamSub: "Active Reps",
        phSearchClients: "Quick Search: Client name, phone, unit code, sales...",
        filterSalesLbl: "Sales Rep:",
        optAllSales: "All Sales Reps (Show All)",
        filterBranchLbl: "Branch:",
        optAllBranches: "All Branches",
        optCairoBranch: "🏛️ Cairo",
        optAlexBranch: "🌊 Alexandria",
        btnClearFilters: "✕ Clear Filters",
        tblTitle: "Client Records & Contracts Table",
        thSales: "Sales Rep",
        thUnit: "Unit Code",
        thBranch: "Branch",
        thName: "Client / Partners",
        thPhone: "Phone Numbers",
        thIdDoc: "Identity Doc",
        thPlan: "Payment Plan",
        th5Percent: "5% Paid",
        thAddress: "Verified Address",
        thNotes: "Notes",
        thDocs: "Documents",
        thDate: "Registered At",
        thActions: "Actions",
        noClientsFound: "No client contracts found matching your search criteria.",
        badgeDirect: "🌐 Direct",
        lblNoFiles: "None",
        lblNotes: "Notes & Special Requests:",
        editModalTitle: "Edit Client & Contract Details",
        editModalSalesLbl: "👤 Assigned Sales Consultant:",
        optDirectNoSales: "-- Direct (No Sales Consultant) --",
        lblDocType: "Doc Type:",
        lblDocNum: "Doc Number:",
        editModalDocs: "📎 Document Attachments Management:",
        editModalUploadMore: "+ Upload Additional Files:",
        btnCancel: "Cancel",
        btnSaveEdit: "💾 Save Changes",
        delClientModalTitle: "Confirm Contract Deletion",
        btnConfirmDelete: "Yes, Confirm Deletion",
        btnDownloadDoc: "Download"
      }
    };

    let currentLang = localStorage.getItem('elamar_lang') || 'ar';

    document.addEventListener('DOMContentLoaded', () => {
      setLanguage(currentLang);
      animateCounters();
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

    // Click to copy
    function copyText(str) {
      navigator.clipboard.writeText(str).then(() => {
        alert((currentLang === 'ar' ? 'تم نسخ: ' : 'Copied: ') + str);
      });
    }

    // Delete Client Confirmation Modal
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
      document.getElementById('edit_contract_location').value = client.contract_location || 'القاهرة';
      document.getElementById('edit_payment_period').value = client.payment_period || '';
      document.getElementById('edit_paid_5_percent').value = client.paid_5_percent || 'نعم';
      document.getElementById('edit_addresses').value = client.addresses || '';
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
          div.className = "flex items-center justify-between p-2 rounded-xl bg-slate-900 border border-slate-800 text-xs";
          div.innerHTML = `
            <a href="${f}" target="_blank" class="text-amber-300 hover:underline font-mono truncate max-w-xs">📎 ${f}</a>
            <label class="flex items-center gap-1.5 text-red-400 font-bold text-[11px] cursor-pointer">
              <input type="checkbox" name="delete_attachments[]" value="${f}" class="rounded text-red-500">
              ${currentLang === 'ar' ? 'حذف الملف' : 'Delete File'}
            </label>
          `;
          filesContainer.appendChild(div);
        });
      } else {
        filesContainer.innerHTML = `<span class="text-xs text-slate-500">${currentLang === 'ar' ? 'لا توجد ملفات مرفقة حالياً' : 'No attachments currently'}</span>`;
      }

      document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
      document.getElementById('editModal').classList.add('hidden');
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
