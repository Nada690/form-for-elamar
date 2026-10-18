<?php
/**
 * manage_sales.php - إدارة مسؤولي المبيعات والروابط المخصصة
 * لوحة تحكم متطورة للأدمن لإنشاء موظفي المبيعات، توليد الروابط المخصصة، وتتبع الإحصائيات
 * دعم كامل للغتين العربية والإنجليزية مع أدوات متقدمة ومولد QR Code تفاعلي
 */

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

require_once __DIR__ . '/db.php';

// التحقق من صلاحية الأدمن (Admin Only)
$isAdmin = (!empty($_SESSION['auth_user_role']) && $_SESSION['auth_user_role'] === 'Admin')
           || (!empty($_SESSION['is_authorized_admin']) && $_SESSION['is_authorized_admin'] === true && !empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');

if (!$isAdmin) {
    header("Location: view_clients.php");
    exit;
}

// حساب الرابط الأساسي الكامل بدقة
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$fullBaseUrl = $protocol . $host . ($scriptDir ? $scriptDir : '');

$notification = '';
$notificationType = 'success';
$lastCreatedSlug = '';
$lastCreatedLink = '';
$lastCreatedName = '';

// ── معالجة الطلبات (Actions) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. إنشاء موظف Sales جديد
    if ($_POST['action'] === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($name) || empty($email)) {
            $notification = 'يرجى إدخال اسم الموظف والبريد الإلكتروني بشكل صحيح.';
            $notificationType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $notification = 'يرجى إدخال بريد إلكتروني صالح.';
            $notificationType = 'error';
        } else {
            $baseSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
            if (empty($baseSlug)) $baseSlug = 'sales';
            
            $suffix = substr(md5(uniqid(mt_rand(), true)), 0, 6);
            $slug = $baseSlug . '-' . $suffix;
            
            $checkSlug = $pdo->prepare("SELECT id FROM sales_employees WHERE slug = :slug LIMIT 1");
            $checkSlug->execute([':slug' => $slug]);
            while ($checkSlug->fetch()) {
                $suffix = substr(md5(uniqid(mt_rand(), true)), 0, 6);
                $slug = $baseSlug . '-' . $suffix;
                $checkSlug->execute([':slug' => $slug]);
            }
            
            try {
                $checkEmail = $pdo->prepare("SELECT id FROM sales_employees WHERE email = :email LIMIT 1");
                $checkEmail->execute([':email' => $email]);
                if ($checkEmail->fetch()) {
                    $notification = 'البريد الإلكتروني مسجل بالفعل لموظف آخر.';
                    $notificationType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO sales_employees (name, email, slug, active) VALUES (:name, :email, :slug, 1)");
                    $stmt->execute([
                        ':name'  => $name,
                        ':email' => $email,
                        ':slug'  => $slug
                    ]);
                    
                    $lastCreatedSlug = $slug;
                    $lastCreatedLink = $fullBaseUrl . '/form.php?slug=' . urlencode($slug);
                    $lastCreatedName = $name;
                    $notification = "تم إنشاء موظف المبيعات ({$name}) وتوليد رابطه المخصص بنجاح!";
                    $notificationType = 'success';
                }
            } catch (PDOException $e) {
                $notification = 'خطأ أثناء الحفظ: ' . $e->getMessage();
                $notificationType = 'error';
            }
        }
    }
    
    // 2. تفعيل / تعطيل
    if ($_POST['action'] === 'toggle_active' && !empty($_POST['sales_id'])) {
        $salesId = (int)$_POST['sales_id'];
        try {
            $stmt = $pdo->prepare("UPDATE sales_employees SET active = NOT active WHERE id = :id");
            $stmt->execute([':id' => $salesId]);
            $notification = 'تم تحديث حالة تفعيل موظف المبيعات بنجاح.';
        } catch (PDOException $e) {
            $notification = 'فشل تحديث الحالة: ' . $e->getMessage();
            $notificationType = 'error';
        }
    }
    
    // 3. حذف موظف
    if ($_POST['action'] === 'delete' && !empty($_POST['sales_id'])) {
        $salesId = (int)$_POST['sales_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM sales_employees WHERE id = :id");
            $stmt->execute([':id' => $salesId]);
            $notification = 'تم حذف موظف المبيعات بنجاح مع الحفاظ على سجلات العملاء.';
        } catch (PDOException $e) {
            $notification = 'فشل الحذف: ' . $e->getMessage();
            $notificationType = 'error';
        }
    }
}

// ── استرجاع البيانات والإحصائيات ──
try {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM sales_employees");
    $totalSales = (int)$countStmt->fetchColumn();

    $activeStmt = $pdo->query("SELECT COUNT(*) FROM sales_employees WHERE active = 1");
    $activeSales = (int)$activeStmt->fetchColumn();

    $clientsCountStmt = $pdo->query("SELECT COUNT(*) FROM clients WHERE sales_id IS NOT NULL");
    $totalSalesClients = (int)$clientsCountStmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT s.*, 
               (SELECT COUNT(*) FROM clients WHERE sales_id = s.id) as client_count 
        FROM sales_employees s 
        ORDER BY s.id DESC
    ");
    $salesEmployees = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("خطأ في جلب بيانات Sales: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إدارة مسؤولي المبيعات والروابط المخصصة | مجموعة العمار</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
  <!-- QRCode.js for generating instant offline QR codes -->
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            cairo: ['Cairo', 'sans-serif'],
            sans: ['Plus Jakarta Sans', 'Cairo', 'sans-serif'],
            mono: ['Plus Jakarta Sans', 'monospace'],
          }
        }
      }
    }
  </script>
  <style>
    * { 
      transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    html[dir="rtl"] { font-family: 'Cairo', sans-serif; }
    html[dir="ltr"] { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'Plus Jakarta Sans', monospace; }
    
    .glass-card {
      background: rgba(15, 23, 42, 0.88);
      backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }
  </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-3 sm:p-6 lg:p-8 selection:bg-amber-500/30 selection:text-amber-200">

  <!-- Ambient Glow -->
  <div class="fixed top-0 right-1/4 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="fixed bottom-0 left-1/4 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

  <div class="max-w-7xl mx-auto space-y-6 relative z-10">

    <!-- ═══════════════ HEADER ═══════════════ -->
    <header class="glass-card rounded-3xl p-5 sm:p-6 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xl border border-slate-800">
      <div class="flex items-center gap-4">
        <div class="p-2 rounded-2xl bg-gradient-to-tr from-amber-500/20 via-amber-400/10 to-transparent border border-amber-500/30 shadow-lg">
          <img src="logo.png" alt="Logo" class="h-12 w-auto object-contain">
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-amber-500/15 text-amber-400 border border-amber-500/30">
              SHORES NORTH COAST
            </span>
            <span class="text-xs text-slate-400" data-i18n="headerBadge">منظومة المبيعات</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-white mt-1" data-i18n="headerTitle">
            إدارة مسؤولي المبيعات والروابط المخصصة
          </h1>
          <p class="text-xs text-slate-400" data-i18n="headerSub">
            توليد روابط فورمة فريدة لكل موظف لمعرفة مصدر كل عميل مسجل تلقائياً
          </p>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Language Switcher -->
        <button 
          id="langToggleBtn" 
          onclick="toggleLanguage()" 
          class="px-3.5 py-2 rounded-2xl bg-slate-800 hover:bg-slate-700 border border-amber-500/30 text-amber-300 text-xs sm:text-sm font-bold flex items-center gap-2 transition-all cursor-pointer shadow-md"
        >
          <span>🌐</span>
          <span id="langBtnText">English</span>
        </button>

        <a href="view_clients.php" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs sm:text-sm font-bold rounded-2xl transition-all border border-slate-700 flex items-center gap-2 shadow-sm">
          <span>📊</span>
          <span data-i18n="btnDashboard">لوحة تحكم العملاء</span>
        </a>
        <a href="form.php" target="_blank" class="px-4 py-2.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 text-xs sm:text-sm font-black rounded-2xl transition-all shadow-lg hover:shadow-amber-500/20 flex items-center gap-2">
          <span>🔗</span>
          <span data-i18n="btnPublicForm">معاينة الفورم العام</span>
        </a>
      </div>
    </header>

    <!-- ═══════════════ NOTIFICATION ═══════════════ -->
    <?php if ($notification): ?>
      <div class="p-4 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-lg border <?= $notificationType === 'error' ? 'bg-red-500/15 border-red-500/40 text-red-200' : 'bg-emerald-500/15 border-emerald-500/40 text-emerald-200' ?>">
        <div class="flex items-center gap-3">
          <span class="text-xl"><?= $notificationType === 'error' ? '⚠️' : '🎉' ?></span>
          <div>
            <div class="font-bold text-sm"><?= htmlspecialchars($notification, ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ($lastCreatedLink): ?>
              <div class="mt-1 flex items-center gap-2 text-xs">
                <span class="text-slate-300 font-semibold" data-i18n="lblRepLink">الرابط الخاص بـ (<?= htmlspecialchars($lastCreatedName) ?>):</span>
                <code class="font-mono bg-slate-900/80 px-2 py-0.5 rounded text-amber-300 select-all"><?= htmlspecialchars($lastCreatedLink) ?></code>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($lastCreatedLink): ?>
          <div class="flex items-center gap-2 w-full sm:w-auto">
            <button onclick="copyToClipboard('<?= addslashes($lastCreatedLink) ?>', '<?= addslashes($lastCreatedName) ?>')" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer">
              <span>📋</span> <span data-i18n="btnCopyNow">نسخ الرابط فوراً</span>
            </button>
            <button onclick="openQrModal('<?= addslashes($lastCreatedLink) ?>', '<?= addslashes($lastCreatedName) ?>')" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white font-black rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer">
              <span>📱</span> QR Code
            </button>
          </div>
        <?php else: ?>
          <button onclick="this.parentElement.remove()" class="text-xs opacity-70 hover:opacity-100 cursor-pointer">✕</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- ═══════════════ STATS CARDS WITH NUMBER COUNT-UP ═══════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      
      <!-- إجمالي الموظفين -->
      <div class="glass-card rounded-3xl p-5 border border-slate-800 shadow-xl flex items-center gap-4 hover:border-slate-700 transition-all">
        <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-2xl text-amber-400 shadow-inner">
          👥
        </div>
        <div>
          <p class="text-xs text-slate-400 font-bold uppercase tracking-wider" data-i18n="cardTotalSales">إجمالي مسؤولي المبيعات</p>
          <div class="flex items-baseline gap-2 mt-0.5">
            <span class="text-3xl font-black text-white count-up" data-target="<?= $totalSales ?>"><?= $totalSales ?></span>
            <span class="text-xs text-amber-400 font-semibold" data-i18n="cardTotalSalesSub">موظف مسجل</span>
          </div>
        </div>
      </div>

      <!-- الروابط النشطة -->
      <div class="glass-card rounded-3xl p-5 border border-slate-800 shadow-xl flex items-center gap-4 hover:border-slate-700 transition-all">
        <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-2xl text-emerald-400 shadow-inner">
          ⚡
        </div>
        <div>
          <p class="text-xs text-slate-400 font-bold uppercase tracking-wider" data-i18n="cardActiveLinks">الروابط النشطة حالياً</p>
          <div class="flex items-baseline gap-2 mt-0.5">
            <span class="text-3xl font-black text-emerald-400 count-up" data-target="<?= $activeSales ?>"><?= $activeSales ?></span>
            <span class="text-xs text-slate-400 font-semibold" data-i18n="cardActiveLinksSub">من أصل <?= $totalSales ?></span>
          </div>
        </div>
      </div>

      <!-- العقود المسجلة عبر السيلز -->
      <div class="glass-card rounded-3xl p-5 border border-slate-800 shadow-xl flex items-center gap-4 hover:border-slate-700 transition-all">
        <div class="w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/30 flex items-center justify-center text-2xl text-blue-400 shadow-inner">
          📑
        </div>
        <div>
          <p class="text-xs text-slate-400 font-bold uppercase tracking-wider" data-i18n="cardLeadsCount">إجمالي العقود المحولة عبر السيلز</p>
          <div class="flex items-baseline gap-2 mt-0.5">
            <span class="text-3xl font-black text-blue-400 count-up" data-target="<?= $totalSalesClients ?>"><?= $totalSalesClients ?></span>
            <span class="text-xs text-slate-400 font-semibold" data-i18n="cardLeadsCountSub">عقد تم ربطه بالموظف</span>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══════════════ CREATE SALES EMPLOYEE CARD ═══════════════ -->
    <div class="glass-card rounded-3xl p-6 sm:p-7 border border-slate-800 shadow-2xl relative overflow-hidden">
      <div class="h-1.5 bg-gradient-to-r from-amber-500 via-amber-300 to-amber-600 absolute top-0 left-0 right-0"></div>

      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
          <h2 class="text-lg font-black text-white flex items-center gap-2.5">
            <span class="p-1.5 rounded-xl bg-amber-500/20 text-amber-400 text-sm">➕</span>
            <span data-i18n="createCardTitle">إضافة موظف مبيعات جديد وتوليد الرابط المخصص</span>
          </h2>
          <p class="text-xs text-slate-400 mt-1" data-i18n="createCardSub">
            بمجرد إدخال الاسم، سيقوم النظام تلقائياً بتوليد رابط دائم وفريد يربط جميع استمارات العملاء بهذا الموظف
          </p>
        </div>
      </div>

      <form method="POST" action="manage_sales.php" class="grid grid-cols-1 md:grid-cols-12 gap-4">
        <input type="hidden" name="action" value="create">

        <!-- الاسم -->
        <div class="md:col-span-5">
          <label class="block text-xs font-bold text-slate-300 mb-1.5">
            <span data-i18n="lblRepName">اسم موظف المبيعات</span> <span class="text-amber-400">*</span>
          </label>
          <input 
            type="text" 
            name="name" 
            id="inputSalesName"
            placeholder="مثال: أحمد محمود أو مصطفى إبراهيم"
            data-i18n-placeholder="phRepName"
            required
            oninput="updatePreviewSlug(this.value)"
            class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-slate-700 text-white placeholder-slate-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20 transition-all font-semibold"
          >
        </div>

        <!-- البريد الإلكتروني -->
        <div class="md:col-span-4">
          <label class="block text-xs font-bold text-slate-300 mb-1.5">
            <span data-i18n="lblRepEmail">البريد الإلكتروني</span> <span class="text-amber-400">*</span>
          </label>
          <input 
            type="email" 
            name="email" 
            placeholder="name@company.com"
            required
            class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-slate-700 text-cyan-300 placeholder-slate-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20 transition-all font-mono"
          >
        </div>

        <!-- زر الإرسال -->
        <div class="md:col-span-3 flex items-end">
          <button 
            type="submit" 
            class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-sm shadow-xl hover:shadow-amber-500/25 transition-all cursor-pointer flex items-center justify-center gap-2"
          >
            <span>✨</span>
            <span data-i18n="btnGenerateLink">توليد الرابط المخصص</span>
          </button>
        </div>

        <!-- معاينة حية للرابط -->
        <div class="md:col-span-12">
          <div class="p-3 rounded-2xl bg-slate-900/80 border border-slate-800 text-xs flex flex-wrap items-center gap-2">
            <span class="text-slate-400 font-bold" data-i18n="lblLinkPreview">معاينة شكل الرابط المتوقع:</span>
            <span class="font-mono text-amber-300 bg-slate-950 px-2 py-0.5 rounded border border-slate-800 dir-ltr" id="slugPreview">
              <?= htmlspecialchars($fullBaseUrl) ?>/form.php?slug=<span id="previewSlugPart" class="text-amber-400 font-bold">sales-xxxxxx</span>
            </span>
          </div>
        </div>

      </form>
    </div>

    <!-- ═══════════════ TABLE & TEAM LIST ═══════════════ -->
    <div class="glass-card rounded-3xl border border-slate-800 shadow-2xl overflow-hidden">
      
      <!-- Table Header & Search -->
      <div class="p-5 border-b border-slate-800 flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-900/60">
        <div class="flex items-center gap-3 w-full sm:w-auto">
          <div class="p-2 rounded-xl bg-slate-800 text-amber-400">📋</div>
          <div>
            <h2 class="font-black text-base sm:text-lg text-white" data-i18n="tableTitle">قائمة مسؤولي المبيعات والروابط المخصصة</h2>
            <p class="text-xs text-slate-400" data-i18n="tableSub">انسخ الرابط أو شارك QR Code مع العميل، وستظهر العقود باسم الموظف فوراً في الداشبورد</p>
          </div>
        </div>

        <!-- Real-time search box -->
        <div class="w-full sm:w-72">
          <div class="relative">
            <input 
              type="text" 
              id="searchSalesInput" 
              placeholder="ابحث بالاسم أو البريد..." 
              data-i18n-placeholder="phSearchSales"
              oninput="filterSalesTable(this.value)"
              class="w-full pl-4 pr-9 py-2.5 rounded-xl bg-slate-800 border border-slate-700 text-white placeholder-slate-400 text-xs focus:outline-none focus:border-amber-400 transition-all"
            >
            <span class="absolute right-3 top-2.5 text-slate-400 text-sm">🔍</span>
          </div>
        </div>
      </div>

      <!-- Table Body -->
      <div class="overflow-x-auto">
        <table class="w-full text-xs sm:text-sm text-right" id="salesTable">
          <thead class="bg-slate-900 text-amber-400 font-black border-b border-slate-800 text-[11px] sm:text-xs uppercase tracking-wider">
            <tr>
              <th class="p-4 w-14 text-center">#</th>
              <th class="p-4" data-i18n="thRep">الموظف</th>
              <th class="p-4" data-i18n="thEmail">البريد الإلكتروني</th>
              <th class="p-4" data-i18n="thUrl">الرابط المخصص للفورم (Custom URL)</th>
              <th class="p-4 text-center" data-i18n="thLeads">العقود المسجلة</th>
              <th class="p-4 text-center" data-i18n="thStatus">الحالة</th>
              <th class="p-4 text-center" data-i18n="thActions">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/80 text-slate-200" id="salesTableBody">
            <?php if (empty($salesEmployees)): ?>
              <tr>
                <td colspan="7" class="p-12 text-center text-slate-400 font-semibold" data-i18n="noSalesFound">
                  <div class="text-3xl mb-2">👥</div>
                  لم يتم إضافة أي موظفي مبيعات حتى الآن. استخدم النموذج أعلاه لإنشاء أول موظف!
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($salesEmployees as $index => $sales): 
                $fullLink = $fullBaseUrl . '/form.php?slug=' . urlencode($sales['slug']);
                $initials = mb_substr($sales['name'], 0, 1, 'UTF-8');
              ?>
                <tr class="hover:bg-slate-800/40 transition-colors sales-row" data-name="<?= htmlspecialchars(mb_strtolower($sales['name']), ENT_QUOTES, 'UTF-8') ?>" data-email="<?= htmlspecialchars(strtolower($sales['email']), ENT_QUOTES, 'UTF-8') ?>">
                  
                  <!-- ID -->
                  <td class="p-4 text-center font-mono text-slate-400 text-xs font-bold">
                    #<?= htmlspecialchars($sales['id']) ?>
                  </td>

                  <!-- Name with Avatar -->
                  <td class="p-4">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500/20 to-amber-300/10 border border-amber-500/30 flex items-center justify-center text-amber-300 font-black text-sm">
                        <?= htmlspecialchars($initials) ?>
                      </div>
                      <div>
                        <div class="font-black text-white text-sm"><?= htmlspecialchars($sales['name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="text-[10px] text-slate-400 font-mono"><?= date('Y-m-d', strtotime($sales['created_at'])) ?></div>
                      </div>
                    </div>
                  </td>

                  <!-- Email -->
                  <td class="p-4 font-mono text-xs text-cyan-300 dir-ltr text-right">
                    <?= htmlspecialchars($sales['email'], ENT_QUOTES, 'UTF-8') ?>
                  </td>

                  <!-- Custom Form Link -->
                  <td class="p-4">
                    <div class="flex flex-col gap-1.5 max-w-md">
                      
                      <div class="flex items-center gap-1.5 bg-slate-900/90 border border-slate-800 rounded-xl p-1.5 dir-ltr">
                        <span class="text-amber-400 text-xs px-1">🔗</span>
                        <input 
                          type="text" 
                          readonly 
                          value="<?= htmlspecialchars($fullLink) ?>" 
                          class="bg-transparent font-mono text-xs text-slate-300 w-full focus:outline-none select-all"
                        >
                        <!-- Quick Copy Button -->
                        <button 
                          onclick="copyToClipboard('<?= addslashes($fullLink) ?>', '<?= addslashes($sales['name']) ?>')"
                          class="px-2.5 py-1 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-lg text-xs flex items-center gap-1 transition-all cursor-pointer whitespace-nowrap shadow-sm"
                          title="Copy Link / نسخ الرابط"
                        >
                          📋 <span data-i18n="btnCopy">نسخ</span>
                        </button>
                      </div>

                      <div class="flex items-center gap-3 text-[11px]">
                        <!-- Test in New Tab -->
                        <a 
                          href="<?= htmlspecialchars($fullLink) ?>" 
                          target="_blank" 
                          class="text-amber-400 hover:text-amber-300 flex items-center gap-1 font-semibold hover:underline"
                        >
                          ↗ <span data-i18n="btnTestLink">تجربة الرابط</span>
                        </a>
                        <span class="text-slate-600">•</span>
                        <!-- QR Code Generator -->
                        <button 
                          type="button"
                          onclick="openQrModal('<?= addslashes($fullLink) ?>', '<?= addslashes($sales['name']) ?>')" 
                          class="text-purple-400 hover:text-purple-300 flex items-center gap-1 font-semibold hover:underline cursor-pointer"
                        >
                          📱 <span data-i18n="btnQr">QR Code</span>
                        </button>
                        <span class="text-slate-600">•</span>
                        <!-- WhatsApp Direct -->
                        <a 
                          href="https://api.whatsapp.com/send?text=<?= urlencode("مرحباً بك، يرجى استيفاء وتأكيد بيانات التعاقد الخاصة بمشروع شورز عبر الرابط التالي:\n" . $fullLink) ?>" 
                          target="_blank" 
                          class="text-emerald-400 hover:text-emerald-300 flex items-center gap-1 font-semibold hover:underline"
                        >
                          💬 <span data-i18n="btnWa">واتساب</span>
                        </a>
                      </div>

                    </div>
                  </td>

                  <!-- Client Count Badge -->
                  <td class="p-4 text-center">
                    <a 
                      href="view_clients.php?sales_id=<?= $sales['id'] ?>" 
                      class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-black <?= $sales['client_count'] > 0 ? 'bg-amber-500/15 text-amber-300 border border-amber-500/30 hover:bg-amber-500/25' : 'bg-slate-800 text-slate-400' ?> transition-colors"
                      title="عرض عملاء هذا الموظف فقط في الداشبورد"
                    >
                      <span>📑</span>
                      <span class="text-sm font-mono"><?= $sales['client_count'] ?></span>
                      <span class="text-[10px]" data-i18n="lblClients">عميل</span>
                    </a>
                  </td>

                  <!-- Status -->
                  <td class="p-4 text-center">
                    <?php if ($sales['active']): ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30" data-i18n="statusActive">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        نشط ومتاح
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-red-500/15 text-red-400 border border-red-500/30" data-i18n="statusDisabled">
                        معطل
                      </span>
                    <?php endif; ?>
                  </td>

                  <!-- Actions -->
                  <td class="p-4 text-center">
                    <div class="flex items-center justify-center gap-2">
                      
                      <!-- تفعيل/تعطيل -->
                      <form method="POST" action="manage_sales.php" class="inline">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="sales_id" value="<?= $sales['id'] ?>">
                        <button 
                          type="submit" 
                          class="p-2 rounded-xl text-xs font-bold transition-all cursor-pointer border <?= $sales['active'] ? 'bg-slate-800 hover:bg-red-500/20 text-slate-400 hover:text-red-300 border-slate-700' : 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 hover:bg-emerald-500/30' ?>"
                          title="<?= $sales['active'] ? 'تعطيل هذا الرابط مؤقتاً' : 'إعادة تفعيل هذا الرابط' ?>"
                        >
                          <?= $sales['active'] ? '🔒' : '🔓' ?>
                        </button>
                      </form>

                      <!-- حذف -->
                      <button 
                        type="button" 
                        onclick="confirmDeleteSales(<?= $sales['id'] ?>, '<?= addslashes($sales['name']) ?>')"
                        class="p-2 rounded-xl bg-slate-800 hover:bg-red-500/20 text-slate-400 hover:text-red-400 border border-slate-700 transition-all cursor-pointer"
                        title="Delete / حذف الموظف"
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

  <!-- ═══════════════ QR CODE MODAL ═══════════════ -->
  <div id="qrModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md hidden" onclick="closeQrModal()">
    <div class="glass-card max-w-sm w-full p-6 rounded-3xl border border-amber-500/40 text-center shadow-2xl relative" onclick="event.stopPropagation()">
      <div class="flex justify-between items-center mb-4 pb-2 border-b border-slate-800">
        <h3 class="text-sm font-black text-amber-400 flex items-center gap-1.5">
          <span>📱</span> <span data-i18n="qrModalTitle">رمز الاستجابة السريع (QR Code)</span>
        </h3>
        <button onclick="closeQrModal()" class="text-slate-400 hover:text-white text-lg cursor-pointer">✕</button>
      </div>

      <div class="text-xs text-slate-300 mb-3">
        <span data-i18n="qrModalSub">امسح الكود بكاميرا الهاتف لفتح استمارة:</span>
        <strong id="qrModalRepName" class="text-amber-300 block text-sm mt-0.5"></strong>
      </div>

      <!-- QR Code Canvas Container -->
      <div class="bg-white p-4 rounded-2xl inline-block shadow-inner mb-4 mx-auto" id="qrcodeCanvas"></div>

      <div class="flex gap-2">
        <button 
          onclick="copyToClipboard(currentQrUrl, currentQrName)" 
          class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-xl text-xs transition-colors cursor-pointer"
          data-i18n="btnCopyUrl"
        >
          📋 نسخ الرابط
        </button>
        <button 
          onclick="closeQrModal()" 
          class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs cursor-pointer"
          data-i18n="btnClose"
        >
          إغلاق
        </button>
      </div>
    </div>
  </div>

  <!-- ═══════════════ DELETE CONFIRMATION MODAL ═══════════════ -->
  <div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md hidden">
    <div class="glass-card max-w-md w-full p-6 rounded-3xl border border-red-500/40 text-center shadow-2xl">
      <div class="w-14 h-14 rounded-2xl bg-red-500/20 text-red-400 flex items-center justify-center text-2xl mx-auto mb-3">
        ⚠️
      </div>
      <h3 class="text-base font-black text-white mb-1" data-i18n="delModalTitle">تأكيد حذف موظف المبيعات</h3>
      <p class="text-xs text-slate-300 mb-5" id="delModalDesc">
        هل أنت متأكد من حذف هذا الموظف؟ العقود المسجلة باسمه ستظل محفوظة ولن تفقد بياناتها.
      </p>

      <form method="POST" action="manage_sales.php" class="flex gap-3 justify-center">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="sales_id" id="deleteSalesId">
        
        <button 
          type="button" 
          onclick="closeDeleteModal()" 
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

  <!-- ═══════════════ TOAST NOTIFICATION ═══════════════ -->
  <div id="toastNotification" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-slate-900 border border-amber-500/50 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-3 transition-all transform duration-300 opacity-0 pointer-events-none translate-y-4">
    <div class="p-1 rounded-lg bg-amber-500 text-slate-950 font-black text-sm">✓</div>
    <div>
      <div class="font-black text-xs text-amber-300" id="toastTitle">تم نسخ الرابط بنجاح!</div>
      <div class="text-[11px] text-slate-300 font-mono mt-0.5 dir-ltr text-left" id="toastDesc"></div>
    </div>
  </div>

  <!-- ═══════════════ SCRIPT LOGIC ═══════════════ -->
  <script>
    // ── BILINGUAL TRANSLATIONS DICTIONARY ──
    const salesTranslations = {
      ar: {
        headerBadge: "منظومة المبيعات",
        headerTitle: "إدارة مسؤولي المبيعات والروابط المخصصة",
        headerSub: "توليد روابط فورمة فريدة لكل موظف لمعرفة مصدر كل عميل مسجل تلقائياً",
        btnDashboard: "لوحة تحكم العملاء",
        btnPublicForm: "معاينة الفورم العام",
        cardTotalSales: "إجمالي مسؤولي المبيعات",
        cardTotalSalesSub: "موظف مسجل",
        cardActiveLinks: "الروابط النشطة حالياً",
        cardActiveLinksSub: "من أصل <?= $totalSales ?>",
        cardLeadsCount: "إجمالي العقود المحولة عبر السيلز",
        cardLeadsCountSub: "عقد تم ربطه بالموظف",
        createCardTitle: "إضافة موظف مبيعات جديد وتوليد الرابط المخصص",
        createCardSub: "بمجرد إدخال الاسم، سيقوم النظام تلقائياً بتوليد رابط دائم وفريد يربط جميع استمارات العملاء بهذا الموظف",
        lblRepName: "اسم موظف المبيعات",
        phRepName: "مثال: أحمد محمود أو مصطفى إبراهيم",
        lblRepEmail: "البريد الإلكتروني",
        btnGenerateLink: "توليد الرابط المخصص",
        lblLinkPreview: "معاينة شكل الرابط المتوقع:",
        tableTitle: "قائمة مسؤولي المبيعات والروابط المخصصة",
        tableSub: "انسخ الرابط أو شارك QR Code مع العميل، وستظهر العقود باسم الموظف فوراً في الداشبورد",
        phSearchSales: "ابحث بالاسم أو البريد...",
        thRep: "الموظف",
        thEmail: "البريد الإلكتروني",
        thUrl: "الرابط المخصص للفورم (Custom URL)",
        thLeads: "العقود المسجلة",
        thStatus: "الحالة",
        thActions: "الإجراءات",
        noSalesFound: "لم يتم إضافة أي موظفي مبيعات حتى الآن. استخدم النموذج أعلاه لإنشاء أول موظف!",
        btnCopy: "نسخ",
        btnTestLink: "تجربة الرابط",
        btnQr: "QR Code",
        btnWa: "واتساب",
        lblClients: "عميل",
        statusActive: "نشط ومتاح",
        statusDisabled: "معطل",
        qrModalTitle: "رمز الاستجابة السريع (QR Code)",
        qrModalSub: "امسح الكود بكاميرا الهاتف لفتح استمارة:",
        btnCopyUrl: "📋 نسخ الرابط",
        btnClose: "إغلاق",
        delModalTitle: "تأكيد حذف موظف المبيعات",
        btnCancel: "إلغاء",
        btnConfirmDelete: "نعم، تأكيد الحذف",
        lblRepLink: "الرابط الخاص بـ:",
        btnCopyNow: "نسخ الرابط فوراً",
        toastSuccess: "تم نسخ الرابط بنجاح!"
      },
      en: {
        headerBadge: "Sales Ecosystem",
        headerTitle: "Sales Representatives & Custom Links Portal",
        headerSub: "Generate permanent unique form links per sales rep to track lead attribution automatically",
        btnDashboard: "Clients Dashboard",
        btnPublicForm: "Preview Public Form",
        cardTotalSales: "Total Sales Representatives",
        cardTotalSalesSub: "Registered Reps",
        cardActiveLinks: "Active Form Links",
        cardActiveLinksSub: "out of <?= $totalSales ?>",
        cardLeadsCount: "Contracts Attributed to Sales",
        cardLeadsCountSub: "attributed contracts",
        createCardTitle: "Add New Sales Rep & Generate Custom Link",
        createCardSub: "Once you enter the name, the system generates a unique permanent link that attributes client leads to this rep",
        lblRepName: "Sales Rep Name",
        phRepName: "e.g. Ahmed Mahmoud or Mostafa Ibrahim",
        lblRepEmail: "Email Address",
        btnGenerateLink: "Generate Dedicated Link",
        lblLinkPreview: "Expected Link Preview:",
        tableTitle: "Sales Team & Dedicated Form Links",
        tableSub: "Copy the link or share QR Code with clients; submissions will display the consultant's name in dashboard",
        phSearchSales: "Search by name or email...",
        thRep: "Sales Rep",
        thEmail: "Email Address",
        thUrl: "Dedicated Form URL",
        thLeads: "Registered Contracts",
        thStatus: "Status",
        thActions: "Actions",
        noSalesFound: "No sales reps added yet. Use the form above to add your first sales consultant!",
        btnCopy: "Copy",
        btnTestLink: "Test Link",
        btnQr: "QR Code",
        btnWa: "WhatsApp",
        lblClients: "Clients",
        statusActive: "Active",
        statusDisabled: "Disabled",
        qrModalTitle: "Quick Response Code (QR Code)",
        qrModalSub: "Scan code with smartphone camera to open form for:",
        btnCopyUrl: "📋 Copy Link",
        btnClose: "Close",
        delModalTitle: "Confirm Sales Rep Deletion",
        btnCancel: "Cancel",
        btnConfirmDelete: "Yes, Delete Rep",
        lblRepLink: "Link for:",
        btnCopyNow: "Copy Link Now",
        toastSuccess: "Link Copied Successfully!"
      }
    };

    let currentLang = localStorage.getItem('elamar_lang') || 'ar';
    let currentQrUrl = '';
    let currentQrName = '';

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
        if (salesTranslations[lang] && salesTranslations[lang][key]) {
          el.innerText = salesTranslations[lang][key];
        }
      });

      document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (salesTranslations[lang] && salesTranslations[lang][key]) {
          el.setAttribute('placeholder', salesTranslations[lang][key]);
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

    // Copy to clipboard with Toast UI
    function copyToClipboard(url, name) {
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(() => showToast(url, name));
      } else {
        const textArea = document.createElement("textarea");
        textArea.value = url;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
          document.execCommand('copy');
          showToast(url, name);
        } catch (err) {
          alert('Copied: ' + url);
        }
        textArea.remove();
      }
    }

    function showToast(url, name) {
      const toast = document.getElementById('toastNotification');
      const titleText = name 
        ? ((currentLang === 'ar') ? `تم نسخ الرابط الخاص بـ (${name})` : `Link for (${name}) copied!`)
        : salesTranslations[currentLang].toastSuccess;

      document.getElementById('toastTitle').innerText = titleText;
      document.getElementById('toastDesc').innerText = url;
      toast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-4');
      toast.classList.add('opacity-100', 'translate-y-0');
      
      setTimeout(() => {
        toast.classList.remove('opacity-100', 'translate-y-0');
        toast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4');
      }, 3500);
    }

    // QR Code Modal Generator using QRCode.js
    function openQrModal(url, name) {
      currentQrUrl = url;
      currentQrName = name;
      document.getElementById('qrModalRepName').innerText = name;
      
      const container = document.getElementById('qrcodeCanvas');
      container.innerHTML = '';

      new QRCode(container, {
        text: url,
        width: 180,
        height: 180,
        colorDark: "#071220",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
      });

      document.getElementById('qrModal').classList.remove('hidden');
    }

    function closeQrModal() {
      document.getElementById('qrModal').classList.add('hidden');
    }

    // Delete Confirmation Modal
    function confirmDeleteSales(id, name) {
      document.getElementById('deleteSalesId').value = id;
      const desc = (currentLang === 'ar')
        ? `هل أنت متأكد من حذف الموظف (${name})؟ العقود المسجلة باسمه ستظل محفوظة ولن تفقد بياناتها.`
        : `Are you sure you want to delete (${name})? Existing contracts registered under this rep will remain safely preserved.`;
      document.getElementById('delModalDesc').innerText = desc;
      document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
      document.getElementById('deleteModal').classList.add('hidden');
    }

    // Dynamic slug preview as user types name
    function updatePreviewSlug(val) {
      const clean = val.toLowerCase().replace(/[^a-z0-9]/g, '');
      const base = clean || 'sales';
      document.getElementById('previewSlugPart').innerText = base + '-xxxxxx';
    }

    // Client-side real-time table filter
    function filterSalesTable(query) {
      const q = query.trim().toLowerCase();
      const rows = document.querySelectorAll('.sales-row');
      rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const email = row.getAttribute('data-email') || '';
        if (name.includes(q) || email.includes(q)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }
  </script>

</body>
</html>
