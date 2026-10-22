<?php
/**
 * form.php - استمارة التعاقد الرسمية الفاخرة | مجموعة العمار
 * متجاوبة بالكامل للهواتف المحمولة والشاشات الكبيرة
 * دعم كامل للغتين العربية والإنجليزية مع تبديل لحظي عبر JavaScript
 */

session_start();
require_once __DIR__ . '/db.php';

// 1. توحيد واستقبال متغير الرابط المخصص (يدعم slug و ref)
$salesRef = trim($_GET['slug'] ?? $_GET['ref'] ?? '');
$salesInfo = null;
$salesId = null;
$salesName = '';

// فحص جدول المستخدمين users أولاً بواسطة كود الموظف
if (!empty($salesRef)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, role, referral_code 
                               FROM users 
                               WHERE (referral_code = :c1 OR referral_code = :c2) 
                                 AND active = 1 
                               LIMIT 1");
        $stmt->execute([':c1' => $salesRef, ':c2' => $salesRef]);
        $salesInfo = $stmt->fetch();
        
        // التوافق العكسي مع جدول sales_employees إن وجد
        if (!$salesInfo) {
            try {
                $seStmt = $pdo->prepare("SELECT id, name, email, slug FROM sales_employees WHERE slug = :s AND active = 1 LIMIT 1");
                $seStmt->execute([':s' => $salesRef]);
                $seRow = $seStmt->fetch();
                if ($seRow) {
                    $uByEmail = $pdo->prepare("SELECT id, name, email, role, referral_code FROM users WHERE email = :e AND active = 1 LIMIT 1");
                    $uByEmail->execute([':e' => $seRow['email']]);
                    $salesInfo = $uByEmail->fetch() ?: $seRow;
                }
            } catch (PDOException $e2) {}
        }

        if ($salesInfo) {
            $salesId = (int)$salesInfo['id'];
            $salesName = $salesInfo['name'];
        }
    } catch (PDOException $e) {
    }
}

// إلغاء أي Fallback يربط العميل بالأدمن: لا نسند الجلسة إلا إذا كان المستخدم المسجل حالياً مسوق مبيعات (Sales) فقط
if (!$salesInfo && empty($salesRef) && !empty($_SESSION['auth_user_id'])) {
    try {
        $userStmt = $pdo->prepare("SELECT id, name, email, role, referral_code FROM users WHERE id = :uid AND role = 'Sales' AND active = 1 LIMIT 1");
        $userStmt->execute([':uid' => $_SESSION['auth_user_id']]);
        $loggedSales = $userStmt->fetch();
        if ($loggedSales) {
            $salesInfo = $loggedSales;
            $salesId = (int)$loggedSales['id'];
            $salesName = $loggedSales['name'];
            $salesRef = $loggedSales['referral_code'];
        }
    } catch (PDOException $e) {}
}

// قراءة بيانات الأكواد وتضمينها مباشرة لتجنب أي مشاكل شبكة أو كاش
$inventoryJsonFile = __DIR__ . '/inventory_units.json';
$embeddedInventoryJson = file_exists($inventoryJsonFile) ? file_get_contents($inventoryJsonFile) : '[]';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="overflow-x-hidden">
<head>
  <script src="customer_units_lookup.js"></script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>استبيان واستيفاء بيانات التعاقد | مشاريع شورز الساحلية</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
  
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            cairo: ['Cairo', 'sans-serif'],
            sans: ['Plus Jakarta Sans', 'Cairo', 'sans-serif'],
            mono: ['Plus Jakarta Sans', 'monospace'],
          },
          colors: {
            navy: {
              950: '#040b14',
              900: '#071220',
              850: '#0b1c31',
              800: '#102540',
              700: '#173458',
            },
            gold: {
              300: '#ffe082',
              400: '#e5c158',
              500: '#d4af37',
              600: '#b89428',
            }
          }
        }
      }
    }
  </script>

  <style>
    * { 
      -webkit-tap-highlight-color: transparent;
      box-sizing: border-box;
      transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    
    html[dir="rtl"] { font-family: 'Cairo', sans-serif; }
    html[dir="ltr"] { font-family: 'Plus Jakarta Sans', sans-serif; }

    /* Baby Blue background & Black Bold text theme */
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

    .bg-luxury-mesh {
      background-color: #dbeafe !important;
      background-image: 
        radial-gradient(at 0% 0%, #bae6fd 0px, transparent 55%),
        radial-gradient(at 100% 0%, #bfdbfe 0px, transparent 50%),
        radial-gradient(at 50% 100%, #e0f2fe 0px, transparent 65%) !important;
      background-attachment: fixed !important;
    }

    /* All text bold and black */
    h1, h2, h3, h4, h5, h6, label, p, span, input, select, textarea, button, a, div {
      font-weight: 700;
    }
    label {
      color: #000000 !important;
      font-weight: 800 !important;
    }

    .glass-card {
      background: rgba(255, 255, 255, 0.96) !important;
      backdrop-filter: blur(28px) !important;
      -webkit-backdrop-filter: blur(28px) !important;
      border: 2px solid #7dd3fc !important;
      box-shadow: 0 20px 50px -10px rgba(2, 132, 199, 0.2), 0 8px 24px rgba(0, 0, 0, 0.05) !important;
    }

    .section-card {
      background: #f0f9ff !important;
      border: 1.5px solid #bae6fd !important;
      box-shadow: 0 4px 15px rgba(2, 132, 199, 0.08) !important;
    }

    .client-card, .unit-block {
      background: #ffffff !important;
      border: 1.5px solid #7dd3fc !important;
      box-shadow: 0 4px 15px rgba(2, 132, 199, 0.08) !important;
    }

    .glass-input {
      background: #ffffff !important;
      border: 1.5px solid #94a3b8 !important;
      color: #000000 !important;
      font-weight: 700 !important;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-input:focus {
      outline: none;
      border-color: #0284c7 !important;
      box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.35) !important;
      background: #ffffff !important;
      color: #000000 !important;
    }
    .glass-input::placeholder {
      color: #475569 !important;
      font-weight: 700 !important;
    }
    .glass-input.valid {
      border-color: #10b981 !important;
    }
    .glass-input.invalid {
      border-color: #ef4444 !important;
    }

    select.glass-input {
      background-color: #ffffff !important;
      color: #000000 !important;
      font-weight: 700 !important;
    }
    select.glass-input option {
      background-color: #ffffff !important;
      color: #000000 !important;
      font-weight: 700 !important;
    }

    /* Selection */
    ::selection {
      background: #bae6fd !important;
      color: #000000 !important;
    }
    ::-moz-selection {
      background: #bae6fd !important;
      color: #000000 !important;
    }
    input::selection, textarea::selection {
      background: #bae6fd !important;
      color: #000000 !important;
    }

    /* Autofill */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    input:-webkit-autofill:active {
      -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
      -webkit-text-fill-color: #000000 !important;
      caret-color: #000000 !important;
      transition: background-color 5000s ease-in-out 0s;
    }

    .custom-check-box {
      cursor: pointer;
      background: #ffffff !important;
      border: 2px solid #cbd5e1 !important;
      color: #000000 !important;
      font-weight: 800 !important;
      transition: all 0.25s ease;
    }
    .custom-check-box:hover {
      border-color: #0284c7 !important;
      background: #e0f2fe !important;
    }
    .custom-check-box.active {
      border-color: #0284c7 !important;
      background: #bae6fd !important;
      color: #000000 !important;
      font-weight: 900 !important;
      box-shadow: 0 0 12px rgba(2, 132, 199, 0.25) !important;
    }

    .country-scroll::-webkit-scrollbar,
    .unit-scroll::-webkit-scrollbar {
      width: 5px;
    }
    .country-scroll::-webkit-scrollbar-track,
    .unit-scroll::-webkit-scrollbar-track {
      background: #f1f5f9;
      border-radius: 8px;
    }
    .country-scroll::-webkit-scrollbar-thumb,
    .unit-scroll::-webkit-scrollbar-thumb {
      background: #7dd3fc;
      border-radius: 8px;
    }
    .country-scroll::-webkit-scrollbar-thumb:hover,
    .unit-scroll::-webkit-scrollbar-thumb:hover {
      background: #0284c7;
    }

    input[type="date"] {
      color-scheme: light;
      color: #000000 !important;
      font-weight: 800 !important;
    }
    input[type="date"]::-webkit-calendar-picker-indicator {
      filter: none;
      cursor: pointer;
      opacity: 0.9;
    }
  </style>
</head>

<body class="min-h-screen bg-luxury-mesh text-slate-950 font-bold flex flex-col relative overflow-x-hidden">

  <!-- Ambient Light Orbs -->
  <div class="fixed top-10 right-10 w-96 h-96 bg-sky-300/30 rounded-full blur-3xl pointer-events-none"></div>
  <div class="fixed bottom-10 left-10 w-96 h-96 bg-blue-300/25 rounded-full blur-3xl pointer-events-none"></div>
  <div class="fixed bottom-10 left-10 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

  <!-- ═══════════════ HEADER ═══════════════ -->
  <header class="border-b-2 border-sky-200 bg-white/95 backdrop-blur-xl shadow-sm sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-3 sm:px-6 py-3 flex items-center justify-between gap-2 sm:gap-3">

      <!-- Logo + Brand -->
      <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0">
        <div class="p-1.5 sm:p-2 rounded-2xl bg-white border-2 border-sky-300 shadow-sm flex items-center justify-center flex-shrink-0">
          <img src="logo.png" alt="Logo" class="h-9 sm:h-12 w-auto object-contain">
        </div>
        <div class="min-w-0">
          <div class="text-slate-950 font-black text-sm sm:text-lg tracking-wide leading-tight truncate" data-i18n="brandName">
            مجموعة العمار للتطوير العقاري
          </div>
          <div class="text-sky-800 text-[9px] sm:text-xs font-black tracking-widest uppercase truncate" data-i18n="brandSlogan">
            SHORES ONLY • الساحل الشمالي
          </div>
        </div>
      </div>

      <!-- Controls & Language Toggle -->
      <div class="flex items-center gap-2 flex-shrink-0">
        <button 
          id="langToggleBtn" 
          type="button"
          onclick="toggleLanguage()" 
          class="px-2.5 sm:px-3.5 py-1.5 rounded-xl bg-sky-50 hover:bg-sky-100 border-2 border-sky-300 text-slate-950 text-xs sm:text-sm font-black flex items-center gap-1.5 transition-all shadow-sm cursor-pointer"
          title="Switch Language / تبديل اللغة"
        >
          <span class="text-xs sm:text-sm">🌐</span>
          <span id="langBtnText">English</span>
        </button>

        <a id="adminDashboardBtn" href="view_clients.php" class="hidden items-center gap-1 text-slate-950 text-xs px-2.5 py-1.5 rounded-xl bg-sky-100 border-2 border-sky-300 hover:bg-sky-200 transition-all font-black">
          🔒 <span data-i18n="adminLink">لوحة المسؤول</span>
        </a>
      </div>

    </div>
  </header>

  <!-- ═══════════════ MAIN CONTENT ═══════════════ -->
  <main class="flex-1 flex items-center justify-center px-2.5 sm:px-6 py-6 sm:py-12 relative z-10 w-full">
    <div class="w-full max-w-3xl lg:max-w-4xl mx-auto">

      <!-- Header Banner & Sales Consultant VIP Tag -->
      <div class="text-center mb-6 sm:mb-8 space-y-2 sm:space-y-3">
        <?php if ($salesInfo): ?>
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-2xl bg-white border-2 border-sky-400 text-slate-950 text-xs sm:text-sm font-black shadow-md max-w-full truncate">
            <span>💎</span>
            <span data-i18n="vipSalesIntro">استمارة معتمدة بإشراف:</span>
            <span class="text-sky-900 bg-sky-100 px-2 py-0.5 rounded-lg border border-sky-300 font-black"><?= htmlspecialchars($salesName, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php else: ?>
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white border-2 border-sky-300 text-slate-950 text-[11px] sm:text-xs font-black shadow-sm max-w-full" data-i18n="officialSurveyTag">
            🏖️ استبيان واستيفاء بيانات عقود الشاليهات الساحلية (Shores Only)
          </div>
        <?php endif; ?>

        <h1 id="pageTitle" class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-950 tracking-tight" data-i18n="pageTitle">
          استمارة بيانات العقد والوحدة
        </h1>
        <p id="pageSubtitle" class="text-slate-800 text-xs sm:text-sm max-w-lg mx-auto font-bold px-2" data-i18n="pageSubtitle">
          يرجى تسجيل وتدقيق بيانات العقد بدقة لضمان سرعة إتمام الإجراءات وتجهيز وثائق الملكية
        </p>

        <div class="h-1 w-24 sm:w-32 bg-sky-400 rounded-full mx-auto"></div>
      </div>

      <!-- ═══════════════ FORM CARD ═══════════════ -->
      <div class="glass-card rounded-2xl sm:rounded-3xl shadow-2xl overflow-hidden relative w-full bg-white/95">
        <div class="h-2 bg-gradient-to-r from-sky-400 via-blue-500 to-sky-400"></div>

        <div class="p-3.5 sm:p-6 md:p-8 lg:p-10">

          <form id="contractForm" novalidate class="space-y-6 sm:space-y-7" enctype="multipart/form-data">
            
            <input type="hidden" name="sales_ref" value="<?= htmlspecialchars($salesRef, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="sales_id" value="<?= $salesId ? htmlspecialchars((string)$salesId, ENT_QUOTES, 'UTF-8') : '' ?>">

            <!-- ── SECTION 01: بيانات أطراف العقد والشركاء (Client & Partner Cards) ── -->
            <div class="section-card p-4 sm:p-6 rounded-2xl sm:rounded-3xl space-y-5 bg-[#f0f9ff] border-2 border-sky-200">
              <div class="flex items-center justify-between pb-3 border-b-2 border-sky-200">
                <div class="flex items-center gap-2.5 text-slate-950 font-black text-sm sm:text-base">
                  <span class="px-2.5 py-1 rounded-xl bg-sky-200 text-slate-950 border border-sky-400 text-xs font-mono font-black shadow-sm">01</span>
                  <span data-i18n="sec1Title">بيانات أطراف العقد والشركاء (بيانات العملاء)</span>
                </div>
                <span class="text-[11px] text-slate-950 font-black bg-white px-2.5 py-1 rounded-lg border-2 border-sky-300 shadow-sm" id="clientsCountBadge">الطرف الأول (الأساسي)</span>
              </div>

              <!-- حاوية بطاقات العملاء والشركاء المجمعة -->
              <div id="clientsContainer" class="space-y-6 w-full">
                <!-- سيتم بناء البطاقات هنا عبر Repeater Component JS -->
              </div>

              <!-- زر إضافة شريك / عميل جديد بالعقد -->
              <div class="pt-2">
                <button 
                  type="button" 
                  onclick="addNewClientCard()" 
                  class="w-full py-3.5 px-4 rounded-2xl bg-white hover:bg-sky-50 border-2 border-dashed border-sky-400 hover:border-sky-500 text-slate-950 font-black text-xs sm:text-sm flex items-center justify-center gap-2.5 transition-all shadow-md cursor-pointer group"
                >
                  <span class="text-base group-hover:scale-110 transition-transform">➕</span>
                  <span data-i18n="btnAddClientCard">+ إضافة شريك / عميل جديد بالعقد</span>
                </button>
              </div>
            </div>

            <!-- ── SECTION 02: بيانات الوحدة ونظام التعاقد وخطة السداد ── -->
            <div class="section-card p-4 sm:p-5 rounded-2xl space-y-4 sm:space-y-5 bg-[#f0f9ff] border-2 border-sky-200">
              <div class="flex items-center gap-2.5 text-slate-950 font-black text-sm sm:text-base">
                <span class="px-2 py-0.5 rounded-lg bg-sky-200 text-slate-950 border border-sky-400 text-xs font-mono font-black shadow-sm">02</span>
                <span data-i18n="sec2TitleUnit">بيانات الوحدة ونظام التعاقد وخطة السداد</span>
              </div>

              <!-- ── حاوية الوحدات الأساسية والإضافية ── -->
              <div id="unitsContainer" class="space-y-4 w-full">

                <!-- 1. الوحدة الأساسية (إلزامية) -->
                <div class="unit-block p-4 rounded-2xl bg-white border-2 border-sky-200 space-y-3 relative shadow-md">
                  <div class="flex items-center justify-between pb-1 border-b border-sky-100">
                    <div class="flex items-center gap-2 text-slate-950 font-black text-xs sm:text-sm">
                      <span>🏢</span>
                      <span data-i18n="unitMainTitle">بيانات الوحدة الأساسية (الوحدة 1)</span>
                      <span class="text-red-500 font-bold">*</span>
                    </div>
                  </div>

                  <!-- سؤال: هل تم استكمال قيمة المقدم؟ (نعم / لا) وتاريخ الدفع الخاص بالوحدة 1 -->
                  <div class="p-3 sm:p-3.5 rounded-xl bg-sky-50/80 border border-sky-200 mb-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                      <!-- السؤال وخيارات نعم / لا -->
                      <div class="space-y-1.5 flex-1">
                        <label class="block text-xs font-black text-slate-950">
                          <span class="text-slate-950 font-black" data-i18n="labelAdvanceQuestion">هل تم استكمال قيمة المقدم؟</span> 
                          <span class="text-red-500 font-bold">*</span>
                        </label>
                        <div class="flex items-center gap-2 max-w-[220px]">
                          <label class="custom-check-box active flex-1 rounded-xl py-2 px-3 flex items-center justify-center gap-1.5 cursor-pointer text-xs font-black text-slate-950 transition-all text-center" id="boxYes">
                            <input type="radio" name="paid_5_percent" value="نعم" checked class="hidden" onchange="toggleAdvancePaidBox(this)">
                            <span>✓</span> <span data-i18n="optYesPaid">نعم</span>
                          </label>
                          <label class="custom-check-box flex-1 rounded-xl py-2 px-3 flex items-center justify-center gap-1.5 cursor-pointer text-xs font-black text-slate-950 transition-all text-center" id="boxNo">
                            <input type="radio" name="paid_5_percent" value="لا" class="hidden" onchange="toggleAdvancePaidBox(this)">
                            <span>✕</span> <span data-i18n="optNoPaid">لا</span>
                          </label>
                        </div>
                      </div>

                      <!-- حقل تاريخ الدفع (يظهر عندما تكون الإجابة نعم) -->
                      <div id="paymentDateContainer" class="space-y-1.5 sm:w-60 w-full transition-all">
                        <label class="block text-xs font-black text-slate-950" for="paymentDate">
                          <span class="text-slate-950 font-black" data-i18n="labelPaymentDate">📅 تاريخ الدفع:</span>
                          <span class="text-red-500 font-bold">*</span>
                        </label>
                        <input
                          type="date"
                          id="paymentDate"
                          name="payment_date"
                          class="glass-input w-full px-3.5 py-2 rounded-xl text-slate-950 text-xs sm:text-sm font-mono font-black border-2 border-sky-300 focus:border-sky-500"
                          value="<?= date('Y-m-d') ?>"
                        />
                        <p class="error-msg text-red-600 font-black text-xs mt-1 hidden" id="errPaymentDate" data-i18n="errPaymentDate">تاريخ الدفع مطلوب</p>
                      </div>
                    </div>
                  </div>

                  <!-- رسالة التوقف عند اختيار "لا" في استكمال المقدم -->
                  <div id="advanceNoticeNo" class="hidden p-4 rounded-2xl bg-amber-50 border-2 border-amber-400 text-center space-y-2 my-2 animate-pulse">
                    <div class="text-2xl">⚠️</div>
                    <h4 class="text-amber-950 font-black text-sm sm:text-base" data-i18n="advanceNoticeTitle">سداد المقدم مطلوب لإتمام التعاقد</h4>
                    <p class="text-xs text-slate-900 font-bold leading-relaxed max-w-lg mx-auto" data-i18n="advanceNoticeDesc">
                      عفواً، لا يمكن استكمال تسجيل التعاقد وحجز الوحدة إلا بعد استكمال سداد قيمة المقدم رسمياً. يُرجى مراجعة إدارة المبيعات لسداد المقدم أولاً للمتابعة.
                    </p>
                  </div>

                  <!-- حقول تفاصيل الوحدة الأساسية (تختفي إذا تم اختيار "لا") -->
                  <div id="primaryUnitDetailsContainer" class="grid grid-cols-1 sm:grid-cols-12 gap-3 transition-all">
                    <!-- كود الوحدة (إدخال مباشر بدون قائمة منسدلة + جلب تلقائي بالرقم القومي) -->
                    <div class="sm:col-span-12 form-group">
                      <label class="block text-xs font-black text-slate-950 mb-1.5" for="unit_code">
                        <span class="text-slate-950 font-black" data-i18n="labelUnitCode">كود الوحدة (الرقم الكودي الخاص بالوحدة)</span>
                        <span class="text-red-500 font-bold">*</span>
                      </label>
                      
                      <div class="relative">
                        <input
                          type="text"
                          id="unit_code"
                          name="unit_code"
                          data-alias="unitCode"
                          dir="ltr"
                          autocomplete="off"
                          placeholder="أدخل كود الوحدة (مثال: J49-26)..."
                          data-i18n-placeholder="phInputUnit"
                          class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-400 text-xs sm:text-sm font-mono font-black tracking-wider uppercase border-2 border-sky-300 focus:border-sky-500 bg-white shadow-sm transition-all"
                          oninput="this.value = this.value.toUpperCase(); validateField(this, val => val.length >= 2);"
                        />
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">🏢</span>
                      </div>

                      <!-- تنبيه الجلب التلقائي وقائمة الوحدات المتعددة إن وجدت -->
                      <div id="unitAutoHint" class="hidden mt-2 p-2.5 rounded-xl bg-emerald-50 border border-emerald-300 text-xs font-bold text-emerald-950 transition-all">
                        <div class="flex items-center gap-1.5 text-emerald-700 font-black" id="unit_msg_box">
                          <span id="unit_msg_icon">✓</span>
                          <span id="unit_msg">تم جلب كود الوحدة تلقائياً بناءً على الرقم القومي</span>
                          <span id="unitAutoHintText" class="hidden"></span>
                        </div>
                        <div id="unit_multi_choices" class="hidden mt-2 flex flex-wrap items-center gap-1.5 pt-1.5 border-t border-sky-200">
                          <span class="text-[11px] font-bold text-slate-700" data-i18n="lblCustomerUnits">الوحدات المسجلة لهذا العميل (انقر للاختيار):</span>
                          <div id="unit_pills" class="flex flex-wrap gap-1.5"></div>
                          <div id="unitPillsContainer" class="hidden"></div>
                          <p id="unitAllSelectedPrimary" class="text-[11px] text-emerald-700 font-black hidden">✓ تم اختيار كل وحدات العميل</p>
                        </div>
                      </div>

                      <p class="error-msg text-red-600 font-black text-xs mt-1 hidden" id="errUnitCode" data-i18n="errUnitCode">كود الوحدة مطلوب</p>
                      <p class="duplicate-unit-warning text-xs font-black text-amber-600 mt-1 hidden" id="duplicateUnitWarnPrimary"></p>
                    </div>

                    <!-- فترة السداد والنسبة (خطة السداد) -->
                    <div class="sm:col-span-6 form-group">
                      <label class="block text-xs font-black text-slate-950 mb-1.5" for="period">
                        <span class="text-slate-950 font-black" data-i18n="labelPeriod">فترة السداد</span>
                        <span class="text-red-500 font-bold">*</span>
                      </label>
                      <select 
                        name="period" 
                        id="period" 
                        class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 text-xs sm:text-sm font-black border-2 border-sky-300 focus:border-sky-500 bg-white cursor-pointer" 
                        onchange="handlePeriodChange(this); validateField(this, val => val !== '')"
                      >
                        <option value="" data-i18n="optSelectPeriod">اختر فترة السداد...</option>
                        <option value="7 Years">7 Years</option>
                        <option value="8 Years">8 Years</option>
                        <option value="9 Years">9 Years</option>
                        <option value="10 Years">10 Years</option>
                      </select>
                      <input type="hidden" name="payment_period" id="paymentPeriod" value="">
                      <p class="error-msg text-red-600 font-black text-xs mt-1 hidden" id="errPeriod" data-i18n="errPeriod">فترة السداد مطلوبة</p>
                    </div>

                    <!-- حقل النسبة (Rate) - يظهر دائماً عند اختيار أي فترة -->
                    <div class="sm:col-span-6 form-group hidden" id="rateSelectContainer">
                      <label class="block text-xs font-black text-slate-950 mb-1.5" for="rateSelect">
                        <span class="text-slate-950 font-black" data-i18n="labelRate">النسبة (Rate)</span>
                        <span class="text-red-500 font-bold">*</span>
                      </label>
                      <select 
                        id="rateSelect" 
                        class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 text-xs sm:text-sm font-black border-2 border-sky-300 focus:border-sky-500 bg-white cursor-pointer"
                        onchange="handleRateChange(this)"
                      >
                        <option value="" data-i18n="optSelectRate">اختر النسبة...</option>
                      </select>
                      <p class="error-msg text-red-600 font-black text-xs mt-1 hidden" id="errRateSelect" data-i18n="errRateSelect">يرجى اختيار النسبة</p>
                    </div>

                    <!-- حقل النسبة النهائي المخفي للباك إند وقاعدة البيانات -->
                    <input type="hidden" name="final_rate" id="final_rate" value="">

                    <!-- مقترح نظام السداد (موجود مباشرة تحت الخطة المختارة) -->
                    <div class="sm:col-span-12 form-group pt-2">
                      <label class="block text-xs font-black text-slate-950 mb-1.5" for="clientPlanExceptions">
                        <span class="text-slate-950 font-black" data-i18n="labelPlanExceptions">اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام</span>
                        <span class="text-slate-600 text-[10px] font-bold" data-i18n="optOptional">(اختياري)</span>
                      </label>
                      <textarea
                        id="clientPlanExceptions"
                        name="notes"
                        rows="2"
                        data-i18n-placeholder="phPlanExceptions"
                        placeholder="اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام..."
                        class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-bold border-2 border-sky-300 focus:border-sky-500 transition-all resize-y"
                      ></textarea>
                    </div>
                  </div>
                </div>

              </div>

              <!-- زر إضافة وحدة أخرى -->
              <button 
                type="button" 
                onclick="addExtraUnitField()" 
                class="text-xs font-black text-slate-950 hover:bg-sky-100 bg-white border-2 border-sky-300 px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all cursor-pointer shadow-sm w-full justify-center"
              >
                <span>➕</span>
                <span data-i18n="btnAddUnit">إضافة وحدة أخرى (اختياري)</span>
              </button>


              <!-- 4. المكان الأقرب لتوثيق العقد (يظهر للعميل داخل مصر بعد سؤال المقدم مباشرة، ويخفى خارج مصر تماماً) -->
              <div id="contractLocationContainer" class="form-group pt-2 border-t-2 border-sky-200 w-full">
                <label class="block text-xs font-black text-slate-950 mb-1.5" for="contractLocation">
                  <span class="text-slate-950 font-black" data-i18n="labelLocation">المكان الأقرب لتوثيق العقد</span>
                  <span class="text-red-500 font-bold">*</span>
                </label>
                <select 
                  name="contract_location" 
                  id="contractLocation" 
                  class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 text-xs sm:text-sm font-black border-2 border-sky-300 focus:border-sky-500 bg-white" 
                  onchange="validateField(this, val => val !== '')"
                >
                  <option value="" data-i18n="optSelectLocation">اختر المكان الأقرب لتوثيق العقد...</option>
                  <option value="القاهرة" selected data-i18n="branchCairoClean">القاهرة</option>
                  <option value="الإسكندرية" data-i18n="branchAlexClean">الإسكندرية</option>
                </select>
                <p class="error-msg text-red-600 font-black text-xs mt-1 hidden" id="errLocation" data-i18n="errLocation">المكان الأقرب لتوثيق العقد مطلوب</p>
              </div>

              <!-- 5. هل لديك أي ملاحظات تود إخبارنا بها؟ (اختياري) -->
              <div class="form-group pt-3 border-t-2 border-sky-200 w-full">
                <label class="block text-xs font-black text-slate-950 mb-1.5" for="generalNotes">
                  <span class="text-slate-950 font-black" data-i18n="labelGeneralNotes">هل لديك أي ملاحظات تود إخبارنا بها؟</span>
                  <span class="text-slate-600 text-[10px] font-bold" data-i18n="optOptional">(اختياري)</span>
                </label>
                <textarea
                  id="generalNotes"
                  name="general_notes"
                  rows="3"
                  data-i18n-placeholder="phGeneralNotes"
                  placeholder="اكتب هنا أي ملاحظات أو استفسارات إضافية تود إخبارنا بها..."
                  class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-bold border-2 border-sky-300 focus:border-sky-500 transition-all resize-y"
                ></textarea>
              </div>

            </div>

            <!-- ── زر التأكيد والإرسال ── -->
            <div class="pt-2">
              <button
                type="submit"
                id="submitBtn"
                class="w-full py-3.5 sm:py-4 rounded-2xl bg-gradient-to-r from-sky-400 via-sky-500 to-blue-500 hover:from-sky-500 hover:to-blue-600 text-slate-950 font-black text-sm sm:text-base shadow-xl hover:shadow-sky-400/30 transition-all cursor-pointer flex items-center justify-center gap-2 border-2 border-sky-400"
              >
                <span>🔒</span>
                <span data-i18n="submitBtnText">استيفاء وتسجيل بيانات التعاقد رسمياً</span>
              </button>
              <p class="text-[11px] text-slate-700 font-bold text-center mt-2.5" data-i18n="securityNotice">
                بالضغط على تسجيل، يتم حفظ بياناتك بأمان في قاعدة بيانات مجموعة العمار وتوثيقها رسمياً
              </p>
            </div>

          </form>

        </div>
      </div>

    </div>
  </main>

  <!-- ═══════════════ LOADING OVERLAY ═══════════════ -->
  <div id="loadingOverlay" class="fixed inset-0 z-50 bg-sky-950/60 backdrop-blur-md flex flex-col items-center justify-center hidden">
    <div class="w-14 sm:w-16 h-14 sm:h-16 border-4 border-sky-200 border-t-sky-500 rounded-full animate-spin mb-4"></div>
    <div class="font-black text-white text-sm sm:text-base" data-i18n="loadingTitle">جارٍ حفظ وتأكيد بيانات العقد...</div>
    <div class="text-xs text-sky-200 mt-1 font-mono font-bold" data-i18n="loadingSub">يرجى الانتظار ثوانٍ معدودة</div>
  </div>

  <!-- ═══════════════ SUCCESS MODAL ═══════════════ -->
  <div id="successModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-md flex items-center justify-center p-4 hidden">
    <div class="glass-card max-w-md w-full p-6 sm:p-8 rounded-3xl border-2 border-sky-300 text-center shadow-2xl relative bg-white">
      <div class="w-16 sm:w-20 h-16 sm:h-20 rounded-full bg-emerald-100 border-2 border-emerald-400 flex items-center justify-center text-3xl mx-auto mb-4 text-emerald-600 animate-bounce font-black">
        ✓
      </div>

      <h3 class="text-lg sm:text-2xl font-black text-slate-950 mb-2" data-i18n="successTitle">
        تم استيفاء وتسجيل بياناتك بنجاح!
      </h3>

      <p class="text-xs sm:text-sm text-slate-800 font-bold mb-6" data-i18n="successDesc">
        تم تسجيل بيانات التعاقد وحفظها في المنظومة بنجاح، وسيقوم فريق المبيعات بالتواصل معكم لاستكمال الإجراءات الرسمية.
      </p>

      <div class="p-4 rounded-2xl bg-sky-50 border-2 border-sky-200 text-xs mb-6 text-right space-y-2">
        <div class="flex justify-between">
          <span class="text-slate-700 font-bold" data-i18n="lblUnitRef">كود الوحدة:</span>
          <span class="font-mono text-slate-950 font-black uppercase" id="modalUnitCode">-</span>
        </div>
        <?php if ($salesInfo): ?>
          <div class="flex justify-between border-t border-sky-200 pt-1.5">
            <span class="text-slate-700 font-bold" data-i18n="lblAdvisor">المستشار العقاري:</span>
            <span class="text-slate-950 font-black"><?= htmlspecialchars($salesName) ?></span>
          </div>
        <?php endif; ?>
        <div class="flex justify-between border-t border-sky-200 pt-1.5">
          <span class="text-slate-700 font-bold" data-i18n="lblStatus">حالة الطلب:</span>
          <span class="text-emerald-700 font-black" data-i18n="valApproved">معتمد وموثق</span>
        </div>
      </div>

      <button
        type="button"
        onclick="location.reload()"
        class="w-full py-3.5 bg-gradient-to-r from-sky-400 to-blue-500 hover:from-sky-500 hover:to-blue-600 text-slate-950 font-black rounded-xl text-sm shadow-lg cursor-pointer border-2 border-sky-400"
        data-i18n="btnAnotherForm"
      >
        حسناً، تسجيل استمارة أخرى
      </button>
    </div>
  </div>

  <!-- ═══════════════ SCRIPT LOGIC ═══════════════ -->
  <script>
    window.onerror = function(msg, url, line, col, err) {
      document.body.innerHTML = '<div id="DEBUG_ERROR" style="color:red;font-size:24px;font-weight:bold;padding:20px;background:white;position:fixed;top:0;left:0;right:0;bottom:0;z-index:999999;">ERROR: ' + msg + ' at line ' + line + ':' + col + '<br><pre>' + (err ? (err.stack || err.message) : '') + '</pre></div>';
    };
    // ── قائمة الدول والأكواد (أول 3 دول مثبتة ثم أبجدياً) ──
    const countryData = [
      // Top 3 pinned
      { code: '+20', flag: '🇪🇬', nameAr: 'مصر', nameEn: 'Egypt', pinned: true },
      { code: '+966', flag: '🇸🇦', nameAr: 'المملكة العربية السعودية', nameEn: 'Saudi Arabia', pinned: true },
      { code: '+971', flag: '🇦🇪', nameAr: 'الإمارات العربية المتحدة', nameEn: 'United Arab Emirates', pinned: true },
      // Alphabetical rest
      { code: '+962', flag: '🇯🇴', nameAr: 'الأردن', nameEn: 'Jordan' },
      { code: '+973', flag: '🇧🇭', nameAr: 'البحرين', nameEn: 'Bahrain' },
      { code: '+213', flag: '🇩🇿', nameAr: 'الجزائر', nameEn: 'Algeria' },
      { code: '+249', flag: '🇸🇩', nameAr: 'السودان', nameEn: 'Sudan' },
      { code: '+964', flag: '🇮🇶', nameAr: 'العراق', nameEn: 'Iraq' },
      { code: '+965', flag: '🇰🇼', nameAr: 'الكويت', nameEn: 'Kuwait' },
      { code: '+212', flag: '🇲🇦', nameAr: 'المغرب', nameEn: 'Morocco' },
      { code: '+967', flag: '🇾🇪', nameAr: 'اليمن', nameEn: 'Yemen' },
      { code: '+216', flag: '🇹🇳', nameAr: 'تونس', nameEn: 'Tunisia' },
      { code: '+968', flag: '🇴🇲', nameAr: 'سلطنة عمان', nameEn: 'Oman' },
      { code: '+963', flag: '🇸🇾', nameAr: 'سوريا', nameEn: 'Syria' },
      { code: '+970', flag: '🇵🇸', nameAr: 'فلسطين', nameEn: 'Palestine' },
      { code: '+974', flag: '🇶🇦', nameAr: 'قطر', nameEn: 'Qatar' },
      { code: '+961', flag: '🇱🇧', nameAr: 'لبنان', nameEn: 'Lebanon' },
      { code: '+218', flag: '🇱🇾', nameAr: 'ليبيا', nameEn: 'Libya' },
      { code: '+222', flag: '🇲🇷', nameAr: 'موريتانيا', nameEn: 'Mauritania' },
      { code: '+1', flag: '🇺🇸', nameAr: 'أمريكا / كندا', nameEn: 'USA / Canada' },
      { code: '+44', flag: '🇬🇧', nameAr: 'المملكة المتحدة (بريطانيا)', nameEn: 'United Kingdom' },
      { code: '+49', flag: '🇩🇪', nameAr: 'ألمانيا', nameEn: 'Germany' },
      { code: '+33', flag: '🇫🇷', nameAr: 'فرنسا', nameEn: 'France' },
      { code: '+39', flag: '🇮🇹', nameAr: 'إيطاليا', nameEn: 'Italy' },
      { code: '+34', flag: '🇪🇸', nameAr: 'إسبانيا', nameEn: 'Spain' },
      { code: '+90', flag: '🇹🇷', nameAr: 'تركيا', nameEn: 'Turkey' },
      { code: '+31', flag: '🇳🇱', nameAr: 'هولندا', nameEn: 'Netherlands' },
      { code: '+41', flag: '🇨🇭', nameAr: 'سويسرا', nameEn: 'Switzerland' },
      { code: '+46', flag: '🇸🇪', nameAr: 'السويد', nameEn: 'Sweden' },
      { code: '+43', flag: '🇦🇹', nameAr: 'النمسا', nameEn: 'Austria' },
      { code: '+32', flag: '🇧🇪', nameAr: 'بلجيكا', nameEn: 'Belgium' },
      { code: '+61', flag: '🇦🇺', nameAr: 'أستراليا', nameEn: 'Australia' },
      { code: '+86', flag: '🇨🇳', nameAr: 'الصين', nameEn: 'China' },
      { code: '+91', flag: '🇮🇳', nameAr: 'الهند', nameEn: 'India' },
      { code: '+81', flag: '🇯🇵', nameAr: 'اليابان', nameEn: 'Japan' },
      { code: '+7', flag: '🇷🇺', nameAr: 'روسيا', nameEn: 'Russia' },
      { code: '+55', flag: '🇧🇷', nameAr: 'البرازيل', nameEn: 'Brazil' },
      { code: '+60', flag: '🇲🇾', nameAr: 'ماليزيا', nameEn: 'Malaysia' },
      { code: '+30', flag: '🇬🇷', nameAr: 'اليونان', nameEn: 'Greece' },
      { code: '+351', flag: '🇵🇹', nameAr: 'البرتغال', nameEn: 'Portugal' }
    ];

    const translations = {
      ar: {
        brandName: "مجموعة العمار للتطوير العقاري",
        brandSlogan: "SHORES ONLY • الساحل الشمالي",
        adminLink: "لوحة المسؤول",
        vipSalesIntro: "استمارة معتمدة بإشراف:",
        officialSurveyTag: "🏖️ استبيان واستيفاء بيانات عقود الشاليهات الساحلية (Shores Only)",
        pageTitle: "استمارة بيانات العقد والوحدة",
        pageSubtitle: "يرجى تسجيل وتدقيق بيانات العقد بدقة لضمان سرعة إتمام الإجراءات وتجهيز وثائق الملكية",
        sec1Title: "بيانات أطراف العقد والشركاء (بيانات العملاء)",
        btnAddClientCard: "+ إضافة شريك / عميل جديد بالعقد",
        badgePrimaryParty: "الطرف الأساسي",
        badgePartnerParty: "شريك بالعقد",
        btnRemovePartner: "حذف هذا الشريك",
        labelPrimaryName: "اسم العميل كما هو في البطاقة الشخصية (باللغة العربية فقط)",
        phPrimaryName: "(الاسم كاملاً باللغة العربية مطابقاً للهوية)",
        errPrimaryName: "اسم العميل مطلوب ويجب أن يكون باللغة العربية فقط",
        labelPrimaryPhone: "أرقام الهواتف والتواصل (يدعم الأرقام المحلية والدولية)",
        phSearchCountry: "بحث عن الدولة أو الكود...",
        errPrimaryPhone: "رقم الهاتف مطلوب وبصيغة صحيحة",
        btnAddPhone: "+ إضافة رقم هاتف إضافي",
        btnRemovePhone: "حذف هذا الرقم",
        labelEmail: "البريد الإلكتروني (متاح للتواصل والمراسلات الرسمية)",
        phEmail: "example@domain.com",
        errEmail: "يرجى إدخال بريد إلكتروني صحيح ومتاح للمراسلات الرسمية (example@domain.com)",
        btnAddEmail: "+ إضافة بريد إلكتروني إضافي",
        btnRemoveEmail: "حذف هذا البريد",
        labelIdType: "نوع الوثيقة",
        optNatId: "بطاقة الرقم القومي (14 رقم)",
        optPassport: "جواز السفر (Passport)",
        labelIdNumber: "رقم بطاقة الرقم القومي أو جواز السفر",
        phIdNumber: "أدخل 14 رقماً بالضبط للبطاقة الشخصية",
        phPassportNumber: "أدخل من 6 إلى 9 أحرف وأرقام للجواز",
        errIdNumberNational: "رقم البطاقة الشخصية يجب أن يتكون من 14 رقماً بالضبط",
        errIdNumberPassport: "رقم جواز السفر يجب أن يتكون من 6 إلى 9 أحرف وأرقام بدون رموز خاصة",
        labelResidenceCountry: "مكان الإقامة الحالي:",
        optInsideEgypt: "داخل جمهورية مصر العربية",
        optOutsideEgypt: "خارج جمهورية مصر العربية",
        labelIdAddress: "العنوان كما هو مسجل في إثبات الهوية الشخصية",
        subIdAddress: "اختر المحافظة والمدينة واكتب باقي التفاصيل",
        lblGovernorate: "المحافظة",
        lblCountry: "الدولة",
        optSelectGov: "اختر المحافظة...",
        optSelectCountry: "اختر الدولة...",
        lblCity: "المدينة / الحي / المركز",
        lblCityAbroad: "المدينة / الولاية / المقاطعة",
        optSelectGovFirst: "اختر المحافظة أولاً...",
        optSelectCountryFirst: "اختر الدولة أولاً...",
        optSelectCity: "اختر المدينة / الحي / المركز...",
        optSelectCityAbroad: "اختر المدينة / الولاية / المقاطعة...",
        optOtherArea: "أخرى / منطقة أخرى",
        optOtherCityAbroad: "أخرى / مدينة أخرى بالخارج",
        subIdAddress: "اختر المحافظة والمدينة واكتب باقي التفاصيل",
        subIdAddressAbroad: "اختر الدولة والمدينة واكتب باقي التفاصيل",
        lblStreetDetails: "باقي تفاصيل العنوان (اسم الشارع، رقم العقار، رقم الشقة)",
        phStreetDetails: "اسم الشارع، رقم العمارة، رقم الشقة، علامة مميزة...",
        errIdAddressSelect: "يرجى اختيار المحافظة والمدينة وكتابة باقي تفاصيل العنوان",
        errIdAddressSelectAbroad: "يرجى اختيار الدولة والمدينة وكتابة باقي تفاصيل العنوان",
        labelCorrespondenceAddress: "العنوان المعتمد بالتفصيل للمراسلات",
        lblCorrStreetDetails: "باقي تفاصيل العنوان للمراسلات",
        phCorrStreetDetails: "اسم الشارع، رقم المبنى والشقة للمراسلات الرسمية",
        errCorrespondenceAddress: "العنوان المعتمد للمراسلات مطلوب",
        secAttachmentsTitle: "المستندات والمرفقات المطلوبة",
        docNoticeInside: "للعملاء المقيمين داخل مصر: يُشترط رفع صورة واضحة لبطاقة الرقم القومي (الوجهين: الأمامي والخلفي).",
        docNoticeOutside: "للعملاء المقيمين خارج مصر: يُشترط رفع صورة واضحة لجواز السفر الساري + صورة بطاقة الإقامة السارية.",
        dropTitle: "اضغط لاختيار المستندات أو اسحب الملفات وأفلتها هنا",
        dropSubtitleInside: "(مطلوب إجباري: صورة بطاقة الرقم القومي وجهين)",
        dropSubtitleOutside: "(مطلوب إجباري: جواز سفر ساري + إقامة سارية)",
        selectedFilesHeading: "المعاينات المرفقة لهذا الطرف:",
        errAttachments: "المستندات المطلوبة إلزامية، يرجى رفع الملفات المحددة أعلاه",
        sec2TitleUnit: "بيانات الوحدة ونظام التعاقد وخطة السداد",
        unitMainTitle: "بيانات الوحدة الأساسية (الوحدة 1)",
        labelAdvanceQuestion: "هل تم استكمال قيمة المقدم؟",
        optYesPaid: "نعم",
        optNoPaid: "لا",
        labelPaymentDate: "📅 تاريخ الدفع:",
        errPaymentDate: "تاريخ الدفع مطلوب",
        advanceNoticeTitle: "سداد المقدم مطلوب لإتمام التعاقد",
        advanceNoticeDesc: "عفواً، لا يمكن استكمال تسجيل التعاقد وحجز الوحدة إلا بعد استكمال سداد قيمة المقدم رسمياً. يُرجى مراجعة إدارة المبيعات لسداد المقدم أولاً للمتابعة.",
        advanceNoticeExtraTitle: "استكمال سداد المقدم مطلوب لحجز هذه الوحدة",
        advanceNoticeExtraDesc: "لا يمكن تسجيل هذه الوحدة وحجزها بدون استكمال سداد المقدم.",
        labelUnitCode: "كود الوحدة (الرقم الكودي الخاص بالوحدة)",
        phInputUnit: "أدخل كود الوحدة (مثال: J49-26)...",
        lblCustomerUnits: "الوحدات المسجلة لهذا العميل:",
        phSelectUnit: "اختر كود الوحدة من القائمة...",
        phSearchUnit: "ابحث عن كود الوحدة (مثال: N1-001)...",
        errUnitCode: "كود الوحدة مطلوب",
        labelPeriod: "فترة السداد",
        optSelectPeriod: "اختر فترة السداد...",
        errPeriod: "فترة السداد مطلوبة",
        labelRate: "النسبة (Rate)",
        optSelectRate: "اختر النسبة...",
        errRateSelect: "يرجى اختيار النسبة",
        labelPlanExceptions: "اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام",
        phPlanExceptions: "اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام...",
        optOptional: "(اختياري)",
        btnAddUnit: "إضافة وحدة أخرى (اختياري)",
        btnRemoveUnit: "حذف هذه الوحدة",
        labelLocation: "المكان الأقرب لتوثيق العقد",
        optSelectLocation: "اختر المكان الأقرب لتوثيق العقد...",
        branchCairoClean: "القاهرة",
        branchAlexClean: "الإسكندرية",
        errLocation: "المكان الأقرب لتوثيق العقد مطلوب",
        labelGeneralNotes: "هل لديك أي ملاحظات تود إخبارنا بها؟",
        phGeneralNotes: "اكتب هنا أي ملاحظات أو استفسارات إضافية تود إخبارنا بها...",
        submitBtnText: "استيفاء وتسجيل بيانات التعاقد رسمياً",
        securityNotice: "بالضغط على تسجيل، يتم حفظ بياناتك بأمان في قاعدة بيانات مجموعة العمار وتوثيقها رسمياً",
        loadingTitle: "جارٍ حفظ وتأكيد بيانات العقد...",
        loadingSub: "يرجى الانتظار ثوانٍ معدودة",
        successTitle: "تم استيفاء وتسجيل بياناتك بنجاح!",
        successDesc: "تم تسجيل بيانات التعاقد وحفظها في المنظومة بنجاح، وسيقوم فريق المبيعات بالتواصل معكم لاستكمال الإجراءات الرسمية.",
        lblUnitRef: "كود الوحدة:",
        lblAdvisor: "المستشار العقاري:",
        lblStatus: "حالة الطلب:",
        valApproved: "معتمد وموثق",
        btnAnotherForm: "حسناً، تسجيل استمارة أخرى"
      },
      en: {
        brandName: "Al Amar Real Estate Development",
        brandSlogan: "SHORES ONLY • NORTH COAST",
        adminLink: "Admin Dashboard",
        vipSalesIntro: "Certified Form Supervised by:",
        officialSurveyTag: "🏖️ Official Shores Coastal Units Survey & Contract Verification",
        pageTitle: "Contract & Unit Registration Form",
        pageSubtitle: "Please review and enter contract information accurately for formal processing & official registry",
        sec1Title: "Contract Parties & Partners Information (Client Data)",
        btnAddClientCard: "+ Add Partner / New Contract Client",
        badgePrimaryParty: "Primary Party",
        badgePartnerParty: "Contract Partner",
        btnRemovePartner: "Remove Partner",
        labelPrimaryName: "Client Name as in ID (Arabic Only)",
        phPrimaryName: "(Full name in Arabic matching ID)",
        errPrimaryName: "Client name is required (Arabic letters only)",
        labelPrimaryPhone: "Phone & Contact Numbers (Supports Local & International)",
        phSearchCountry: "Search country or dialing code...",
        errPrimaryPhone: "Phone number is required in a valid format",
        btnAddPhone: "+ Add Additional Phone Number",
        btnRemovePhone: "Remove phone",
        labelEmail: "Email (Official Correspondence & Notices)",
        phEmail: "example@domain.com",
        errEmail: "Please provide a valid email (example@domain.com)",
        btnAddEmail: "+ Add Additional Email",
        btnRemoveEmail: "Remove email",
        labelIdType: "ID Document Type",
        optNatId: "National ID (14 digits)",
        optPassport: "Passport",
        labelIdNumber: "National ID or Passport Number",
        phIdNumber: "Enter exactly 14 digits for National ID",
        phPassportNumber: "Enter 6 to 9 alphanumeric characters",
        errIdNumberNational: "National ID must be exactly 14 digits",
        errIdNumberPassport: "Passport number must be 6 to 9 alphanumeric characters",
        labelResidenceCountry: "Current Residence Place:",
        optInsideEgypt: "Inside Arab Republic of Egypt",
        optOutsideEgypt: "Outside Arab Republic of Egypt",
        labelIdAddress: "Address as registered in Personal ID",
        lblGovernorate: "Governorate",
        lblCountry: "Country",
        optSelectGov: "Select Governorate...",
        optSelectCountry: "Select Country...",
        lblCity: "City / District / Area",
        lblCityAbroad: "City / State / Province",
        optSelectGovFirst: "Select Governorate first...",
        optSelectCountryFirst: "Select Country first...",
        optSelectCity: "Select City / District...",
        optSelectCityAbroad: "Select City / State / Province...",
        optOtherArea: "Other district / area",
        optOtherCityAbroad: "Other city / area abroad",
        subIdAddress: "Select Gov & City + Street details",
        subIdAddressAbroad: "Select Country & City + Street details",
        lblStreetDetails: "Street, Building & Apt No. Details",
        phStreetDetails: "Street name, building number, apartment number, landmark...",
        errIdAddressSelect: "Please select governorate, city and enter street address details",
        errIdAddressSelectAbroad: "Please select country, city and enter street address details",
        labelCorrespondenceAddress: "Approved Correspondence Address in Detail",
        lblCorrStreetDetails: "Street, Building & Apt No. Details",
        phCorrStreetDetails: "Street name, building number, apartment number for official mail",
        errCorrespondenceAddress: "Approved correspondence address is required",
        secAttachmentsTitle: "Required Documents & Attachments",
        docNoticeInside: "For clients residing inside Egypt: Uploading both sides of the National ID is mandatory.",
        docNoticeOutside: "For clients residing outside Egypt: Uploading valid Passport + Residence permit is mandatory.",
        dropTitle: "Click to select documents or drag & drop files here",
        dropSubtitleInside: "(Mandatory: National ID card both sides)",
        dropSubtitleOutside: "(Mandatory: Valid Passport + Residence Permit)",
        selectedFilesHeading: "Attached Files Preview:",
        errAttachments: "Required documents are mandatory. Please attach the required files.",
        sec2TitleUnit: "Unit Information, Contract System & Payment Plan",
        unitMainTitle: "Primary Unit Information (Unit 1)",
        labelAdvanceQuestion: "Was the down payment completed?",
        optYesPaid: "Yes",
        optNoPaid: "No",
        labelPaymentDate: "📅 Payment Date:",
        errPaymentDate: "Payment date is required",
        advanceNoticeTitle: "Down payment is required to complete the contract",
        advanceNoticeDesc: "Registration cannot proceed without completing the down payment. Please contact sales management to settle the down payment first.",
        advanceNoticeExtraTitle: "Down payment is required to register this unit",
        advanceNoticeExtraDesc: "Cannot proceed with registering this unit without completing down payment.",
        labelUnitCode: "Unit Code (Unit Identification Code)",
        phInputUnit: "Enter Unit Code (e.g. J49-26)...",
        lblCustomerUnits: "Units registered for this client:",
        phSelectUnit: "Select unit code from list...",
        phSearchUnit: "Search unit code (e.g. N1-001)...",
        errUnitCode: "Unit code is required",
        labelPeriod: "Payment Term",
        optSelectPeriod: "Select payment term...",
        errPeriod: "Payment term is required",
        labelRate: "Rate",
        optSelectRate: "Select Rate...",
        errRateSelect: "Please select a rate",
        labelPlanExceptions: "Write any proposed payment plan you wish to record freely without obligation",
        phPlanExceptions: "Write any proposed payment plan you wish to record freely without obligation...",
        optOptional: "(Optional)",
        btnAddUnit: "Add Another Unit (Optional)",
        btnRemoveUnit: "Remove this unit",
        labelLocation: "Nearest Branch for Contract Signing",
        optSelectLocation: "Select contract branch...",
        branchCairoClean: "Cairo",
        branchAlexClean: "Alexandria",
        errLocation: "Contract branch is required",
        labelGeneralNotes: "Do you have any notes or inquiries you'd like to tell us?",
        phGeneralNotes: "Write any additional notes or inquiries you'd like to share with us...",
        submitBtnText: "Submit & Officially Register Contract",
        securityNotice: "By clicking submit, your data is securely stored and registered with Al Amar Group",
        loadingTitle: "Saving and confirming contract details...",
        loadingSub: "Please wait a few seconds",
        successTitle: "Contract Information Submitted Successfully!",
        successDesc: "Your registration has been securely documented. Our sales team will get in touch to finalize official paperwork.",
        lblUnitRef: "Unit Code:",
        lblAdvisor: "Property Consultant:",
        lblStatus: "Application Status:",
        valApproved: "Certified & Logged",
        btnAnotherForm: "Done, register another form"
      }
    };

    let currentLang = localStorage.getItem('elamar_lang') || 'ar';
    let currentResidence = 'داخل جمهورية مصر العربية';
    let selectedFiles = [];

    document.addEventListener('DOMContentLoaded', () => {
      addNewClientCard(true);
      setLanguage(currentLang);
      
      document.addEventListener('click', (e) => {
        if (!e.target.closest('.searchable-region-picker')) {
          closeAllRegionPickerDropdowns();
        }
        if (!e.target.closest('.country-picker')) {
          document.querySelectorAll('.country-dropdown').forEach(d => d.classList.add('hidden'));
        }
        if (!e.target.closest('.unit-picker')) {
          closeAllUnitDropdowns();
        }
      });
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

      const langBtn = document.getElementById('langBtnText');
      if (langBtn) langBtn.innerText = (lang === 'ar') ? 'English' : 'العربية';

      document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (translations[lang] && translations[lang][key]) {
          el.innerText = translations[lang][key];
        }
      });

      document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (translations[lang] && translations[lang][key]) {
          el.setAttribute('placeholder', translations[lang][key]);
        }
      });

      if (typeof updateAllDynamicCardsLanguage === 'function') {
        updateAllDynamicCardsLanguage(lang);
      }
      if (typeof reNumberClientCards === 'function') {
        reNumberClientCards();
      }
      if (typeof updateClientsCountBadge === 'function') {
        updateClientsCountBadge();
      }
      if (typeof updateAllExtraUnitsLanguage === 'function') {
        updateAllExtraUnitsLanguage(lang);
      }

      // Update static primary unit picker placeholder if empty
      const unitCodeInput = getUnitCodeInput();
      if (unitCodeInput) {
        unitCodeInput.placeholder = translations[lang]?.phInputUnit || (lang === 'en' ? 'Enter Unit Code (e.g. J49-26)...' : 'أدخل كود الوحدة (مثال: J49-26)...');
      }
    }

    function updateAllDynamicCardsLanguage(lang) {
      const isEn = (lang === 'en');
      const t = translations[lang] || {};

      if (typeof activeClientIds === 'undefined' || !Array.isArray(activeClientIds)) return;

      activeClientIds.forEach((cId, idx) => {
        const card = document.getElementById(`clientCard_${cId}`);
        if (!card) return;

        // 1. Badge & Delete button
        const badge = card.querySelector('.card-badge');
        if (badge) {
          badge.innerText = (idx === 0) ? (t.badgePrimaryParty || 'Primary Party') : (t.badgePartnerParty || 'Contract Partner');
        }
        const delBtn = card.querySelector('.card-delete-btn');
        if (delBtn) {
          delBtn.setAttribute('title', t.btnRemovePartner || 'Remove Partner');
          const spanText = delBtn.querySelector('span:last-child');
          if (spanText) spanText.innerText = t.btnRemovePartner || 'Remove Partner';
        }

        // 2. ID document placeholder depending on type
        const idTypeSelect = card.querySelector('.client-id-type');
        const idInput = card.querySelector('.client-id-number');
        if (idTypeSelect && idInput) {
          if (idTypeSelect.value === 'passport') {
            idInput.placeholder = t.phPassportNumber || 'Enter 6 to 9 alphanumeric characters';
          } else {
            idInput.placeholder = t.phIdNumber || 'Enter exactly 14 digits';
          }
        }

        // 3. Notice text and drop subtitle according to residence
        const resVal = clientResidenceMap[cId] || 'داخل جمهورية مصر العربية';
        const isAbroad = resVal.includes('خارج');
        const noticeEl = card.querySelector('.client-doc-notice-text');
        const subEl = card.querySelector('.client-drop-subtitle');
        if (noticeEl) {
          noticeEl.innerText = isAbroad ? t.docNoticeOutside : t.docNoticeInside;
        }
        if (subEl) {
          subEl.innerText = isAbroad ? t.dropSubtitleOutside : t.dropSubtitleInside;
        }

        // 4. Update address labels and dropdowns (Searchable Region Picker for ID & standard select for correspondence)
        updateClientAddressLabels(cId);
        renderRegionPickerItems(cId);

        const govInput = card.querySelector('.client-gov-select');
        const curGovVal = govInput ? govInput.value : '';
        const displayEl = card.querySelector('.region-picker-display');

        if (curGovVal) {
          const cObj = isAbroad ? worldCountriesData.find(c => c.nameAr === curGovVal) : null;
          const flag = cObj ? cObj.flag : (isAbroad ? '🌍' : '📍');
          const dispName = (isEn && isAbroad && worldCountriesEn[curGovVal]) ? worldCountriesEn[curGovVal] : ((isEn && !isAbroad && egyptGovernoratesEn[curGovVal]) ? egyptGovernoratesEn[curGovVal] : curGovVal);
          if (displayEl) {
            displayEl.innerHTML = `<span class="region-flag text-base flex-shrink-0">${flag}</span><span class="region-name font-black truncate text-slate-950">${dispName}</span>`;
          }
          const citySelect = card.querySelector('.client-city-select');
          const curCity = citySelect ? citySelect.value : '';
          handleClientRegionChange(cId, curGovVal, 'id');
          if (citySelect && curCity) citySelect.value = curCity;
        } else if (displayEl) {
          displayEl.innerHTML = isAbroad
            ? `<span class="region-flag text-base flex-shrink-0">🌍</span><span class="region-name font-black truncate text-slate-950">${isEn ? 'Select Country...' : 'اختر الدولة...'}</span>`
            : `<span class="region-flag text-base flex-shrink-0">🇪🇬</span><span class="region-name font-black truncate text-slate-950">${isEn ? 'Select Governorate...' : 'اختر المحافظة...'}</span>`;
        }

        // Correspondence address: ALWAYS Egyptian governorates & cities
        const corrGovSelect = card.querySelector('.client-corr-gov-select');
        const curCorrGovVal = corrGovSelect ? corrGovSelect.value : '';
        populateGovernorateOptions(corrGovSelect);

        const corrCitySelect = card.querySelector('.client-corr-city-select');
        if (curCorrGovVal) {
          const curCorrCity = corrCitySelect ? corrCitySelect.value : '';
          handleClientCorrGovChange(cId, curCorrGovVal);
          if (corrCitySelect && curCorrCity) corrCitySelect.value = curCorrCity;
        } else if (corrCitySelect && corrCitySelect.options && corrCitySelect.options.length > 0) {
          corrCitySelect.options[0].textContent = t.optSelectGovFirst || (isEn ? 'Select Governorate first...' : 'اختر المحافظة أولاً...');
        }

        // 5. Phone rows: placeholders and delete button titles and country list
        card.querySelectorAll('.phone-row').forEach(pRow => {
          const pInput = pRow.querySelector('.phone-input');
          const codeVal = pRow.querySelector('.country-code-val')?.value || '+20';
          if (pInput) updatePhonePlaceholderAndConfig(pInput, codeVal);
          const delPhoneBtn = pRow.querySelector('button[onclick^="removePhoneRow"]');
          if (delPhoneBtn) delPhoneBtn.setAttribute('title', t.btnRemovePhone || 'Remove phone');
          const picker = pRow.querySelector('.country-picker');
          if (picker) buildCountryList(picker);
        });

        // 6. Email rows: delete button titles
        card.querySelectorAll('.email-row').forEach(eRow => {
          const delEmailBtn = eRow.querySelector('button[onclick^="removeEmailRow"]');
          if (delEmailBtn) delEmailBtn.setAttribute('title', t.btnRemoveEmail || 'Remove email');
        });
      });
    }

    function updateAllExtraUnitsLanguage(lang) {
      const isEn = (lang === 'en');
      const t = translations[lang] || {};

      const extraBlocks = document.querySelectorAll('#unitsContainer .unit-block:not(:first-child)');
      extraBlocks.forEach((blk, idx) => {
        const uNum = idx + 2;
        const titleSpan = blk.querySelector('.unit-block-title');
        if (titleSpan) {
          titleSpan.innerText = isEn ? `Additional Unit (Unit ${uNum})` : `بيانات الوحدة الإضافية (الوحدة ${uNum})`;
        }
        const delBtn = blk.querySelector('button[onclick^="removeExtraUnit"]');
        if (delBtn) {
          delBtn.setAttribute('title', t.btnRemoveUnit || (isEn ? 'Remove this unit' : 'حذف هذه الوحدة'));
          const delSpan = delBtn.querySelector('span:last-child');
          if (delSpan) delSpan.innerText = t.btnRemoveUnit || (isEn ? 'Remove this unit' : 'حذف هذه الوحدة');
        }

        const pSel = blk.querySelector('select[name="extra_payment_periods[]"]');
        if (pSel && pSel.options[0] && pSel.options[0].value === '') {
          pSel.options[0].textContent = t.optSelectPeriod || (isEn ? 'Select payment term...' : 'اختر فترة السداد...');
        }

        const rSel = blk.querySelector('select[name="extra_final_rates[]"]');
        if (rSel && rSel.options[0] && rSel.options[0].value === '') {
          rSel.options[0].textContent = t.optSelectRate || (isEn ? 'Select Rate...' : 'اختر النسبة...');
        }

        const pickerSelected = blk.querySelector('.unit-picker .selected-text');
        const hiddenUnit = blk.querySelector('input[name="extra_unit_codes[]"]');
        if (pickerSelected && (!hiddenUnit || !hiddenUnit.value)) {
          pickerSelected.innerHTML = `<span class="text-slate-500 font-sans font-normal tracking-normal text-xs" data-i18n="phSelectUnit">${t.phSelectUnit || 'Select unit code from list...'}</span>`;
        }
      });
    }

    // ── Country Dropdown Builder with Clean Formatting ──
    function buildCountryList(container) {
      const listContainer = container.querySelector('.country-scroll');
      if (!listContainer) return;
      listContainer.innerHTML = '';

      const pinnedTitle = document.createElement('div');
      pinnedTitle.className = "text-[10px] font-black text-sky-950 px-2 pt-1 pb-0.5 uppercase tracking-wider text-start";
      pinnedTitle.innerText = (currentLang === 'en') ? '⭐ Popular Countries' : '⭐ الدول الأكثر استخداماً';
      listContainer.appendChild(pinnedTitle);

      countryData.filter(c => c.pinned).forEach(c => {
        listContainer.appendChild(createCountryItemEl(c, container));
      });

      const div = document.createElement('div');
      div.className = "border-t-2 border-sky-100 my-1";
      listContainer.appendChild(div);

      const allTitle = document.createElement('div');
      allTitle.className = "text-[10px] font-black text-slate-700 px-2 pt-1 pb-0.5 uppercase tracking-wider text-start";
      allTitle.innerText = (currentLang === 'en') ? '🌍 All Countries (Alphabetical)' : '🌍 باقي دول العالم (ترتيب أبجدي)';
      listContainer.appendChild(allTitle);

      countryData.filter(c => !c.pinned).forEach(c => {
        listContainer.appendChild(createCountryItemEl(c, container));
      });
    }

    function createCountryItemEl(c, pickerContainer) {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = "country-item w-full flex items-center justify-between px-2.5 py-1.5 rounded-xl hover:bg-sky-100 text-slate-950 transition-colors cursor-pointer font-bold";
      item.setAttribute('data-name-ar', c.nameAr);
      item.setAttribute('data-name-en', c.nameEn);
      item.setAttribute('data-code', c.code);

      const name = (currentLang === 'en') ? c.nameEn : c.nameAr;
      item.innerHTML = `
        <span class="flex items-center gap-2 truncate text-start">
          <span class="text-base flex-shrink-0">${c.flag}</span>
          <span class="truncate font-black text-xs text-slate-950">${name}</span>
        </span>
        <span class="font-mono text-sky-900 font-black dir-ltr ml-2 flex-shrink-0 text-xs">${c.code}</span>
      `;

      item.onclick = (e) => {
        e.stopPropagation();
        selectCountry(c, pickerContainer);
      };
      return item;
    }

    function toggleCountryDropdown(btn) {
      const container = btn.closest('.country-picker');
      const dropdown = container.querySelector('.country-dropdown');
      const isHidden = dropdown.classList.contains('hidden');

      document.querySelectorAll('.country-dropdown').forEach(d => d.classList.add('hidden'));

      if (isHidden) {
        dropdown.classList.remove('hidden');
        const searchInput = dropdown.querySelector('.country-search-input');
        if (searchInput) {
          searchInput.value = '';
          filterCountryList(searchInput);
          setTimeout(() => searchInput.focus(), 50);
        }
      } else {
        dropdown.classList.add('hidden');
      }
    }

    // ── Helper Sanitizer & Formatter: إزالة + وتخزين الأرقام فقط (Digits Only) ──
    function sanitizeAndFormatPhoneNumber(code, rawNum) {
      const codeDigits = (code || '+20').replace(/[^\d]/g, '');
      let numDigits = (rawNum || '').replace(/[^\d]/g, '');

      // إزالة بادئة الاتصال الدولي 00
      if (numDigits.startsWith('00')) {
        numDigits = numDigits.substring(2);
      }

      // إزالة تكرار كود الدولة إن وجد داخل الرقم
      if (codeDigits && numDigits.startsWith(codeDigits)) {
        numDigits = numDigits.substring(codeDigits.length);
      }

      // إزالة الصفر في البداية
      numDigits = numDigits.replace(/^0+/, '');

      // الأرقام المصرية (+20): تُحفظ كـ 20 متبوعة بـ 10 أرقام (بدون + نهائياً)
      if (codeDigits === '20' || !codeDigits) {
        return '20' + numDigits;
      }

      // الأرقام الدولية: كود الدولة مدمج مباشرة مع الرقم كأرقام فقط (بدون +)
      return codeDigits + numDigits;
    }

    function updatePhonePlaceholderAndConfig(phoneInput, code) {
      if (!phoneInput) return;
      code = code || '+20';
      const isEn = (currentLang === 'en');
      if (code === '+20') {
        phoneInput.placeholder = isEn ? '10xxxxxxxx (10 digits without leading 0)' : '10xxxxxxxx (10 أرقام مباشرة بدون صفر)';
        phoneInput.maxLength = 10;
      } else if (code === '+966') {
        phoneInput.placeholder = isEn ? '5xxxxxxxx (9 digits without +966)' : '5xxxxxxxx (9 أرقام بدون كود الدولة)';
        phoneInput.maxLength = 9;
      } else if (code === '+971') {
        phoneInput.placeholder = isEn ? '5xxxxxxxx (9 digits without +971)' : '5xxxxxxxx (9 أرقام بدون كود +971)';
        phoneInput.maxLength = 9;
      } else {
        phoneInput.placeholder = isEn ? 'Phone number without country code' : 'رقم الهاتف بدون كود الدولة';
        phoneInput.maxLength = 15;
      }
    }

    function handlePhoneInput(input) {
      if (!input) return false;
      const row = input.closest('.phone-row') || input.closest('.dir-ltr') || input.parentElement;
      const codeValEl = row ? row.querySelector('.country-code-val') : null;
      const code = codeValEl ? codeValEl.value.trim() : '+20';
      const codeDigits = code.replace(/\D/g, '');

      let errEl = row ? row.querySelector('.error-msg') : null;
      if (!errEl && row && row.parentElement) {
        errEl = row.parentElement.querySelector('.error-msg');
      }

      let val = input.value;
      // 1. إزالة علامة + وأي رموز أو مسافات غير رقمية فوراً
      val = val.replace(/[^\d]/g, '');

      // 2. منع بادئات الاتصال الدولي (00)
      if (val.startsWith('00')) {
        val = val.substring(2);
      }

      // 3. منع كتابة كود الدولة في الحقل (حذف الكود فوراً إذا بدأ به الرقم)
      if (codeDigits && val.startsWith(codeDigits)) {
        val = val.substring(codeDigits.length);
      }

      const isEn = (currentLang === 'en');
      let customError = '';
      let isValid = false;

      // ── التعامل مع الأرقام المصرية (Country Code: Egypt / +20) ──
      if (code === '+20' || codeDigits === '20') {
        input.maxLength = 10;

        // لا يُسمح للمستخدم بكتابة الصفر في البداية؛ إظهار رسالة خطأ فورية إذا حاول البدء بـ 0 أو 01
        if (val.startsWith('0')) {
          customError = isEn 
            ? 'Please enter without leading 0. Start directly with 10, 11, 12, or 15.' 
            : 'يرجى كتابة الرقم بدون صفر في البداية (ابدأ مباشرة بـ 10 أو 11 أو 12 أو 15).';
          val = val.replace(/^0+/, '');
        }

        // يجب منع كتابة أي رقم يبدأ بغير هذه الأرقام
        if (val.length === 1) {
          if (val !== '1') {
            customError = isEn
              ? 'Egyptian mobile numbers must start with 10, 11, 12, or 15.'
              : 'أرقام المحمول المصرية يجب أن تبدأ بكود الشبكة (10، 11، 12، 15).';
          }
        } else if (val.length >= 2) {
          const prefix = val.substring(0, 2);
          if (!['10', '11', '12', '15'].includes(prefix)) {
            customError = isEn
              ? 'Invalid prefix. Egyptian mobile must start with 10, 11, 12, or 15.'
              : 'رقم غير صحيح؛ يجب أن يبدأ بكود إحدى الشبكات المصرية: (10، 11، 12، 15).';
          }
        }

        // تحديد طول الرقم بـ 10 أرقام فقط
        if (val.length > 10) {
          val = val.substring(0, 10);
        }

        // التحقق من اكتمال الرقم: 10 أرقام تبدأ بـ 10 أو 11 أو 12 أو 15
        isValid = /^(10|11|12|15)\d{8}$/.test(val);
        if (!isValid && val.length > 0 && !customError) {
          if (val.length < 10) {
            customError = isEn 
              ? `Egyptian phone must be exactly 10 digits (${val.length}/10 entered)` 
              : `رقم الهاتف المصري يجب أن يتكون من 10 أرقام (${val.length} من 10)`;
          }
        }
      } 
      // ── التعامل مع الأرقام الدولية ──
      else if (code === '+966' || codeDigits === '966') {
        input.maxLength = 9;
        if (val.startsWith('0')) val = val.substring(1);
        if (val.length > 9) val = val.substring(0, 9);
        isValid = /^5\d{8}$/.test(val);
        if (!isValid && val.length > 0) {
          customError = isEn ? 'Enter 9 digits starting with 5 (without +966 code)' : 'يرجى إدخال 9 أرقام تبدأ بـ 5 بدون كود الدولة (مثال: 5xxxxxxxx)';
        }
      } else if (code === '+971' || codeDigits === '971') {
        input.maxLength = 9;
        if (val.startsWith('0')) val = val.substring(1);
        if (val.length > 9) val = val.substring(0, 9);
        isValid = /^5\d{8}$/.test(val);
        if (!isValid && val.length > 0) {
          customError = isEn ? 'Enter 9 digits starting with 5' : 'يرجى إدخال 9 أرقام تبدأ بـ 5';
        }
      } else {
        input.maxLength = 15;
        if (val.startsWith('0')) val = val.substring(1);
        if (val.length > 15) val = val.substring(0, 15);
        isValid = val.length >= 7;
        if (!isValid && val.length > 0) {
          customError = isEn ? 'Valid phone number is required (min 7 digits)' : 'رقم الهاتف مطلوب وبصيغة صحيحة (7 أرقام على الأقل)';
        }
      }

      input.value = val;
      input.classList.toggle('valid', isValid);
      input.classList.toggle('invalid', (!isValid && val.length > 0) || !!customError);

      if (errEl) {
        if (customError) {
          errEl.innerText = customError;
          errEl.classList.remove('hidden');
        } else if (isValid || val.length === 0) {
          errEl.classList.add('hidden');
        }
      }

      return isValid;
    }

    function selectCountry(c, pickerContainer) {
      const displaySpan = pickerContainer.querySelector('.country-display');
      const hiddenInput = pickerContainer.querySelector('.country-code-val');
      const dropdown = pickerContainer.querySelector('.country-dropdown');

      if (displaySpan) {
        displaySpan.innerHTML = `<span class="text-sm sm:text-base">${c.flag}</span><span class="font-mono text-xs">${c.code}</span>`;
      }
      if (hiddenInput) {
        hiddenInput.value = c.code;
      }
      if (dropdown) {
        dropdown.classList.add('hidden');
      }

      // تحديث وإعادة تهيئة حقل الهاتف المرتبط
      const row = pickerContainer.closest('.phone-row') || pickerContainer.closest('.dir-ltr') || pickerContainer.parentElement;
      if (row) {
        const phoneInput = row.querySelector('input[type="tel"]');
        if (phoneInput) {
          updatePhonePlaceholderAndConfig(phoneInput, c.code);
          handlePhoneInput(phoneInput);
        }
      }
    }

    function filterCountryList(input) {
      const q = input.value.trim().toLowerCase();
      const dropdown = input.closest('.country-dropdown');
      const items = dropdown.querySelectorAll('.country-item');

      items.forEach(item => {
        const ar = (item.getAttribute('data-name-ar') || '').toLowerCase();
        const en = (item.getAttribute('data-name-en') || '').toLowerCase();
        const code = (item.getAttribute('data-code') || '').toLowerCase();
        const match = ar.includes(q) || en.includes(q) || code.includes(q);
        item.style.display = match ? 'flex' : 'none';
      });
    }

    // ── Inventory Units Searchable Dropdown Logic (2900+ units, optimized) ──
    const globalInventoryUnits = <?= $embeddedInventoryJson ?>;
    let inventoryUnitsCache = Array.isArray(globalInventoryUnits) && globalInventoryUnits.length > 0 ? globalInventoryUnits : null;

    async function loadInventoryUnits() {
      if (inventoryUnitsCache && inventoryUnitsCache.length > 0) return inventoryUnitsCache;
      try {
        const res = await fetch('inventory_units.json');
        if (res.ok) {
          inventoryUnitsCache = await res.json();
          return inventoryUnitsCache;
        }
      } catch (e) {
        console.error('Error loading inventory units:', e);
      }
      return inventoryUnitsCache || [];
    }

    function closeAllUnitDropdowns() {
      document.querySelectorAll('.unit-dropdown').forEach(d => d.classList.add('hidden'));
      document.querySelectorAll('.unit-picker .chevron-icon').forEach(c => c.style.transform = 'rotate(0deg)');
    }

    async function toggleUnitDropdown(pickerId) {
      const picker = document.getElementById(pickerId);
      if (!picker) return;
      const dropdown = picker.querySelector('.unit-dropdown');
      const chevron = picker.querySelector('.chevron-icon');
      const isHidden = dropdown.classList.contains('hidden');

      closeAllUnitDropdowns();

      if (isHidden) {
        dropdown.classList.remove('hidden');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
        
        // Ensure units are loaded and rendered
        await renderUnitDropdownItems(pickerId, '');
        const searchInput = dropdown.querySelector('input[type="text"]');
        if (searchInput) {
          searchInput.value = '';
          setTimeout(() => searchInput.focus(), 50);
        }
      }
    }

    // State to support full scrolling of all units without DOM freeze
    const unitPickerState = {};

    function normalizeUnitSearchString(str) {
      if (!str) return '';
      // استبدال الأرقام العربية بالأرقام الإنجليزية
      const arabicNumerals = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
      let s = String(str);
      for (let i = 0; i < 10; i++) {
        s = s.replaceAll(arabicNumerals[i], String(i));
      }
      // إزالة علامات الشرطة المتنوعة والمسافات للتسهيل على المستخدم إذا كتب n2 102 أو n2102 أو n2-102
      return s.toUpperCase().trim();
    }

    async function renderUnitDropdownItems(pickerId, filterQuery = '') {
      const picker = document.getElementById(pickerId);
      if (!picker) return;
      const listContainer = picker.querySelector('.unit-scroll') || picker.querySelector('#unitListItems');
      const countInfo = picker.querySelector('.unit-count-info') || picker.querySelector('#unitCountInfo');
      if (!listContainer) return;

      const units = await loadInventoryUnits();
      const rawQ = filterQuery || '';
      const normalizedQ = normalizeUnitSearchString(rawQ);
      // نسخة خالية من الفواصل والشرطات لمطابقة مرنة جداً
      const cleanQ = normalizedQ.replace(/[\s\-_–—]/g, '');

      let matched = [];
      if (!cleanQ) {
        matched = units;
      } else {
        matched = units.filter(u => {
          const normU = normalizeUnitSearchString(u);
          const cleanU = normU.replace(/[\s\-_–—]/g, '');
          return normU.includes(normalizedQ) || cleanU.includes(cleanQ);
        });
      }

      // Save state for infinite scroll
      unitPickerState[pickerId] = {
        matchedUnits: matched,
        renderedCount: 0,
        pageSize: 100
      };

      listContainer.innerHTML = '';

      if (matched.length === 0) {
        const noResText = translations[currentLang]?.noUnitsFound || 'لا توجد وحدة مطابقة لبحثك';
        listContainer.innerHTML = `
          <div class="p-3 text-center text-xs text-slate-800 bg-sky-50 rounded-xl border border-sky-200 font-bold">
            ${noResText}
          </div>
        `;
      } else {
        appendMoreUnits(pickerId);

        // Bind scroll event once for progressive loading of all units
        if (!listContainer.dataset.scrollAttached) {
          listContainer.dataset.scrollAttached = "true";
          listContainer.addEventListener('scroll', () => {
            if (listContainer.scrollTop + listContainer.clientHeight >= listContainer.scrollHeight - 60) {
              appendMoreUnits(pickerId);
            }
          });
        }
      }

      if (countInfo) {
        if (!cleanQ) {
          countInfo.innerText = (currentLang === 'en') ? `All ${units.length} units available` : `عرض كل الوحدات (${units.length} وحدة)`;
        } else {
          countInfo.innerText = (currentLang === 'en') 
            ? `Found ${matched.length} units` 
            : `تم العثور على ${matched.length} وحدة`;
        }
      }
    }

    function appendMoreUnits(pickerId) {
      const state = unitPickerState[pickerId];
      if (!state) return;
      const picker = document.getElementById(pickerId);
      if (!picker) return;
      const listContainer = picker.querySelector('.unit-scroll') || picker.querySelector('#unitListItems');
      if (!listContainer) return;

      const total = state.matchedUnits.length;
      if (state.renderedCount >= total) return;

      const nextBatch = state.matchedUnits.slice(state.renderedCount, state.renderedCount + state.pageSize);
      state.renderedCount += nextBatch.length;

      const hiddenInput = picker.querySelector('input[type="hidden"]');
      const currentVal = hiddenInput ? hiddenInput.value : '';

      const fragment = document.createDocumentFragment();
      nextBatch.forEach(unitCode => {
        const isSelected = (unitCode === currentVal);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `w-full text-right px-3 py-2 rounded-xl text-xs sm:text-sm font-mono font-black tracking-wider flex items-center justify-between transition-all cursor-pointer select-none ${
          isSelected 
            ? 'bg-sky-200 text-slate-950 border-2 border-sky-500 shadow-sm font-black' 
            : 'text-slate-950 hover:bg-sky-100 font-bold'
        }`;
        
        btn.innerHTML = `
          <span class="flex items-center gap-2">
            <span class="text-sky-600 text-xs font-mono">🏢</span>
            <span class="uppercase">${unitCode}</span>
          </span>
          ${isSelected ? '<span class="text-sky-700 font-black text-xs">✓</span>' : '<span class="text-[10px] text-slate-600 font-bold">شورز</span>'}
        `;

        btn.onclick = () => selectUnitOption(pickerId, unitCode);
        fragment.appendChild(btn);
      });

      listContainer.appendChild(fragment);
    }

    function filterUnitsList(pickerId, query) {
      renderUnitDropdownItems(pickerId, query);
    }

    function selectUnitOption(pickerId, code) {
      const picker = document.getElementById(pickerId);
      if (!picker) return;

      const hiddenInput = picker.querySelector('input[type="hidden"]');
      const selectedSpan = picker.querySelector('#unitPickerSelectedText') || picker.querySelector('.selected-text');
      
      if (hiddenInput) {
        hiddenInput.value = code;
      }

      if (selectedSpan) {
        selectedSpan.innerHTML = `<span class="text-slate-950 font-mono font-black tracking-widest uppercase">${code}</span>`;
      }

      closeAllUnitDropdowns();

      // Trigger validation for primary unit if applicable
      if (pickerId === 'primaryUnitPicker') {
        const errEl = document.getElementById('errUnitCode');
        const triggerBtn = document.getElementById('unitPickerTrigger');
        if (errEl) errEl.classList.add('hidden');
        if (triggerBtn) {
          triggerBtn.classList.add('valid');
          triggerBtn.classList.remove('invalid');
        }
      } else {
        const block = picker.closest('.unit-block');
        if (block) {
          const errEl = block.querySelector('.extra-unit-error');
          if (errEl) errEl.classList.add('hidden');
          const triggerBtn = picker.querySelector('button');
          if (triggerBtn) {
            triggerBtn.classList.add('valid');
            triggerBtn.classList.remove('invalid');
          }
        }
      }
    }

    function addPartnerNameField() {
      const container = document.getElementById('namesContainer');
      const div = document.createElement('div');
      div.className = "flex gap-2 items-center transform transition-all duration-300 opacity-0 translate-y-2 w-full";
      div.innerHTML = `
        <input
          type="text"
          name="names[]"
          placeholder="${translations[currentLang].partnerPlaceholder}"
          class="glass-input flex-1 px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-bold border-2 border-sky-300 focus:border-sky-500 bg-white"
        />
        <button type="button" onclick="removeDynamicField(this)" class="p-2.5 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 border-2 border-red-300 text-xs cursor-pointer font-black flex-shrink-0">
          ✕
        </button>
      `;
      container.appendChild(div);
      setTimeout(() => {
        div.classList.remove('opacity-0', 'translate-y-2');
      }, 20);
      if (typeof refreshAllUnitsPills === 'function') {
        refreshAllUnitsPills();
      }
    }

    let extraPhoneCounter = 0;
    function addExtraPhoneField() {
      extraPhoneCounter++;
      const container = document.getElementById('phonesContainer');
      const div = document.createElement('div');
      div.className = "flex gap-2 items-center dir-ltr transform transition-all duration-300 opacity-0 translate-y-2 w-full relative";
      const pickerId = `extraPicker_${extraPhoneCounter}`;

      div.innerHTML = `
        <div class="country-picker relative w-28 sm:w-36 flex-shrink-0" id="${pickerId}">
          <button 
            type="button" 
            onclick="toggleCountryDropdown(this)" 
            class="country-btn glass-input w-full px-2 sm:px-2.5 py-2.5 sm:py-3 rounded-xl text-slate-950 text-xs font-black flex items-center justify-between cursor-pointer border-2 border-sky-300 hover:border-sky-500 transition-all bg-white"
          >
            <span class="country-display flex items-center gap-1 sm:gap-1.5 truncate">
              <span class="text-sm sm:text-base">🇪🇬</span>
              <span class="font-mono text-xs font-black">+20</span>
            </span>
            <span class="text-[9px] text-slate-600 font-bold">▼</span>
          </button>
          
          <input type="hidden" name="phone_codes[]" value="+20" class="country-code-val">

          <div class="country-dropdown hidden absolute top-full mt-1.5 start-0 z-50 w-72 max-w-[85vw] sm:w-80 bg-white border-2 border-sky-300 rounded-2xl shadow-2xl p-2.5 backdrop-blur-2xl">
            <div class="relative mb-2">
              <input 
                type="text" 
                placeholder="بحث عن الدولة أو الكود..." 
                oninput="filterCountryList(this)" 
                class="country-search-input w-full px-3 py-2 text-xs bg-sky-50 border border-sky-200 rounded-xl text-slate-950 placeholder-slate-500 focus:outline-none focus:border-sky-500 font-bold"
              >
              <span class="absolute end-2.5 top-2.5 text-xs text-slate-500">🔍</span>
            </div>
            <div class="country-scroll max-h-56 overflow-y-auto space-y-1 text-xs pr-1"></div>
          </div>
        </div>

        <input
          type="tel"
          name="phone_numbers[]"
          placeholder="10xxxxxxxx / 010xxxxxxxx"
          class="glass-input flex-1 min-w-0 px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-mono font-black text-left border-2 border-sky-300 focus:border-sky-500 bg-white"
          oninput="handlePhoneInput(this)"
        />
        <button type="button" onclick="removeDynamicField(this)" class="p-2.5 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 border-2 border-red-300 text-xs cursor-pointer font-black flex-shrink-0">
          ✕
        </button>
      `;
      container.appendChild(div);
      buildCountryList(div.querySelector('.country-picker'));

      setTimeout(() => {
        div.classList.remove('opacity-0', 'translate-y-2');
      }, 20);
    }

    function removeDynamicField(btn) {
      const parent = btn.parentElement;
      parent.classList.add('opacity-0', '-translate-y-2');
      setTimeout(() => parent.remove(), 250);
    }

    let extraUnitCounter = 1;
    function addExtraUnitField() {
      extraUnitCounter++;
      const container = document.getElementById('unitsContainer');
      const div = document.createElement('div');
      div.className = "unit-block p-4 rounded-2xl bg-white border-2 border-sky-200 space-y-3 relative transform transition-all duration-300 opacity-0 translate-y-2 shadow-md";
      const pickerId = `extraUnitPicker_${extraUnitCounter}`;
      const cNum = extraUnitCounter;

      const unitTitle = (currentLang === 'en') ? `Additional Unit (Unit ${cNum})` : `بيانات الوحدة الإضافية (الوحدة ${cNum})`;
      const removeTitle = translations[currentLang]?.btnRemoveUnit || (currentLang === 'en' ? 'Remove this unit' : 'حذف هذه الوحدة');
      const labelUnitCode = translations[currentLang]?.labelUnitCode || (currentLang === 'en' ? 'Unit Code' : 'كود الوحدة (الرقم الكودي الخاص بالوحدة)');
      const phSelectUnit = translations[currentLang]?.phSelectUnit || (currentLang === 'en' ? 'Select unit code from list...' : 'اختر كود الوحدة من القائمة...');
      const phSearchUnit = translations[currentLang]?.phSearchUnit || (currentLang === 'en' ? 'Search unit code...' : 'ابحث عن كود الوحدة...');
      const labelPeriod = translations[currentLang]?.labelPeriod || (currentLang === 'en' ? 'Payment Term' : 'فترة السداد');
      const labelRate = translations[currentLang]?.labelRate || (currentLang === 'en' ? 'Rate' : 'النسبة (Rate)');
      const labelProposal = translations[currentLang]?.labelPlanExceptions || (currentLang === 'en' ? 'Write any proposed payment plan you wish to record freely without obligation' : 'اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام');
      const phProposal = translations[currentLang]?.phPlanExceptions || (currentLang === 'en' ? 'Write any proposed payment plan you wish to record freely without obligation...' : 'اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام...');
      const optOptional = translations[currentLang]?.optOptional || (currentLang === 'en' ? '(Optional)' : '(اختياري)');

      div.innerHTML = `
        <div class="flex items-center justify-between pb-1 border-b border-sky-100">
          <div class="flex items-center gap-2 text-slate-950 font-black text-xs sm:text-sm">
            <span>🏢</span>
            <span class="unit-block-title">${unitTitle}</span>
            <span class="text-slate-600 text-[10px] font-bold" data-i18n="optOptional">${optOptional}</span>
          </div>
          <button 
            type="button" 
            onclick="removeExtraUnit(this)" 
            class="text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border-2 border-red-300 px-2.5 py-1 rounded-lg text-xs font-black transition-all cursor-pointer flex items-center gap-1"
            title="${removeTitle}"
          >
            <span>❌</span>
            <span>${removeTitle}</span>
          </button>
        </div>

        <!-- سؤال: هل تم استكمال قيمة المقدم؟ (نعم / لا) وتاريخ الدفع للوحدة الإضافية -->
        <div class="p-3 sm:p-3.5 rounded-xl bg-sky-50/80 border border-sky-200 mb-2">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <!-- السؤال وخيارات نعم / لا -->
            <div class="space-y-1.5 flex-1">
              <label class="block text-xs font-black text-slate-950">
                <span class="text-slate-950 font-black" data-i18n="labelAdvanceQuestion">${currentLang === 'en' ? 'Was the down payment completed?' : 'هل تم استكمال قيمة المقدم؟'}</span> 
                <span class="text-red-500 font-bold">*</span>
              </label>
              <div class="flex items-center gap-2 max-w-[220px]">
                <label class="custom-check-box active flex-1 rounded-xl py-2 px-3 flex items-center justify-center gap-1.5 cursor-pointer text-xs font-black text-slate-950 transition-all text-center extra-box-yes" id="extraBoxYes_${cNum}">
                  <input type="radio" name="extra_paid_5_percent_${cNum}" value="نعم" checked class="hidden" onchange="toggleExtraAdvancePaidBox(${cNum}, this)">
                  <span>✓</span> <span data-i18n="optYesPaid">${currentLang === 'en' ? 'Yes' : 'نعم'}</span>
                </label>
                <label class="custom-check-box flex-1 rounded-xl py-2 px-3 flex items-center justify-center gap-1.5 cursor-pointer text-xs font-black text-slate-950 transition-all text-center extra-box-no" id="extraBoxNo_${cNum}">
                  <input type="radio" name="extra_paid_5_percent_${cNum}" value="لا" class="hidden" onchange="toggleExtraAdvancePaidBox(${cNum}, this)">
                  <span>✕</span> <span data-i18n="optNoPaid">${currentLang === 'en' ? 'No' : 'لا'}</span>
                </label>
              </div>
            </div>

            <!-- حقل تاريخ الدفع -->
            <div id="extraPaymentDateContainer_${cNum}" class="space-y-1.5 sm:w-60 w-full transition-all">
              <label class="block text-xs font-black text-slate-950" for="extraPaymentDate_${cNum}">
                <span class="text-slate-950 font-black" data-i18n="labelPaymentDate">📅 ${currentLang === 'en' ? 'Payment Date:' : 'تاريخ الدفع:'}</span>
                <span class="text-red-500 font-bold">*</span>
              </label>
              <input
                type="date"
                id="extraPaymentDate_${cNum}"
                name="extra_payment_dates[]"
                class="glass-input w-full px-3.5 py-2 rounded-xl text-slate-950 text-xs sm:text-sm font-mono font-black border-2 border-sky-300 focus:border-sky-500"
                value="<?= date('Y-m-d') ?>"
              />
              <p class="error-msg text-red-600 font-black text-xs mt-1 hidden extra-payment-date-error" data-i18n="errPaymentDate">${currentLang === 'en' ? 'Payment date is required' : 'تاريخ الدفع مطلوب'}</p>
            </div>
          </div>
        </div>

        <!-- رسالة التوقف للوحدة الإضافية عند اختيار "لا" -->
        <div id="extraAdvanceNoticeNo_${cNum}" class="hidden p-3 rounded-2xl bg-amber-50 border-2 border-amber-400 text-center space-y-1.5 my-2 animate-pulse">
          <div class="text-xl">⚠️</div>
          <p class="text-xs text-amber-950 font-black" data-i18n="advanceNoticeExtraTitle">${currentLang === 'en' ? 'Down payment is required to register this unit' : 'استكمال سداد المقدم مطلوب لحجز هذه الوحدة'}</p>
          <p class="text-[11px] text-slate-900 font-bold" data-i18n="advanceNoticeExtraDesc">${currentLang === 'en' ? 'Cannot proceed with registering this unit without completing down payment.' : 'لا يمكن تسجيل هذه الوحدة وحجزها بدون استكمال سداد المقدم.'}</p>
        </div>

        <div id="extraUnitDetailsContainer_${cNum}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 transition-all">
          <!-- كود الوحدة الإضافية (إدخال مباشر بدون دروب ليست) -->
          <div class="sm:col-span-12 form-group">
            <label class="block text-xs font-black text-slate-950 mb-1.5">
              <span class="text-slate-950 font-black" data-i18n="labelUnitCode">${labelUnitCode}</span>
              <span class="text-red-500 font-bold">*</span>
            </label>
            <div class="relative">
              <input
                type="text"
                name="extra_unit_codes[]"
                dir="ltr"
                autocomplete="off"
                placeholder="${currentLang === 'en' ? 'Enter Unit Code (e.g. J49-26)...' : 'أدخل كود الوحدة (مثال: J49-26)...'}"
                class="extra-unit-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-400 text-xs sm:text-sm font-mono font-black tracking-wider uppercase border-2 border-sky-300 focus:border-sky-500 bg-white shadow-sm transition-all"
                oninput="this.value = this.value.toUpperCase(); validateField(this, val => val.length >= 2);"
              />
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">🏢</span>
            </div>

            <!-- أزرار الوحدات المتبقية لنفس الرقم القومي -->
            <div class="extra-unit-pills-box hidden mt-2 p-2.5 rounded-xl bg-sky-50 border border-sky-200 text-xs font-bold text-sky-950 transition-all">
              <div class="flex items-center justify-between gap-1 mb-1.5">
                <span class="text-[11px] font-bold text-slate-700" data-i18n="lblCustomerUnits">${currentLang === 'en' ? 'Remaining units for this client (Click to select):' : 'الوحدات المتبقية لهذا العميل (انقر للاختيار):'}</span>
              </div>
              <div class="extra-unit-pills flex flex-wrap gap-1.5"></div>
              <p class="extra-unit-all-selected text-[11px] text-emerald-700 font-black hidden">${currentLang === 'en' ? '✓ All client units have been selected' : '✓ تم اختيار كل وحدات العميل'}</p>
            </div>

            <p class="error-msg text-red-600 font-black text-xs mt-1 hidden extra-unit-error" data-i18n="errUnitCode">${currentLang === 'en' ? 'Unit code is required' : 'كود الوحدة مطلوب'}</p>
            <p class="duplicate-unit-warning text-xs font-black text-amber-600 mt-1 hidden"></p>
          </div>

          <!-- فترة السداد للوحدة الإضافية (مطابقة تماماً للوحدة 1) -->
          <div class="sm:col-span-6 form-group">
            <label class="block text-xs font-black text-slate-950 mb-1.5" for="extraPeriod_${cNum}">
              <span class="text-slate-950 font-black" data-i18n="labelPeriod">${labelPeriod}</span>
              <span class="text-red-500 font-bold">*</span>
            </label>
            <select 
              name="extra_payment_periods[]" 
              id="extraPeriod_${cNum}" 
              class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 text-xs sm:text-sm font-black border-2 border-sky-300 focus:border-sky-500 bg-white cursor-pointer" 
              onchange="handleExtraPeriodChange(${cNum}, this)"
            >
              <option value="" data-i18n="optSelectPeriod">${currentLang === 'en' ? 'Select Payment Term...' : 'اختر فترة السداد...'}</option>
              <option value="7 Years">7 Years</option>
              <option value="8 Years">8 Years</option>
              <option value="9 Years">9 Years</option>
              <option value="10 Years">10 Years</option>
            </select>
            <p class="error-msg text-red-600 font-black text-xs mt-1 hidden extra-period-error" data-i18n="errPeriod">${currentLang === 'en' ? 'Payment term is required' : 'فترة السداد مطلوبة'}</p>
          </div>

          <!-- حقل النسبة للوحدة الإضافية (يظهر عند اختيار الفترة، ويعرض النسبة فوراً حتى لو وحيدة) -->
          <div class="sm:col-span-6 form-group hidden" id="extraRateContainer_${cNum}">
            <label class="block text-xs font-black text-slate-950 mb-1.5" for="extraRateSelect_${cNum}">
              <span class="text-slate-950 font-black" data-i18n="labelRate">${labelRate}</span>
              <span class="text-red-500 font-bold">*</span>
            </label>
            <select 
              name="extra_final_rates[]" 
              id="extraRateSelect_${cNum}" 
              class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 text-xs sm:text-sm font-black border-2 border-sky-300 focus:border-sky-500 bg-white cursor-pointer" 
              onchange="handleExtraRateChange(${cNum}, this)"
            >
              <option value="" data-i18n="optSelectRate">${currentLang === 'en' ? 'Select Rate...' : 'اختر النسبة...'}</option>
            </select>
            <p class="error-msg text-red-600 font-black text-xs mt-1 hidden extra-rate-error" data-i18n="errRateSelect">${currentLang === 'en' ? 'Please select a rate' : 'يرجى اختيار النسبة'}</p>
          </div>

          <!-- مقترح نظام السداد للوحدة الإضافية (مطابق تماماً للوحدة 1) -->
          <div class="sm:col-span-12 form-group pt-2">
            <label class="block text-xs font-black text-slate-950 mb-1.5" for="extraNotes_${cNum}">
              <span class="text-slate-950 font-black" data-i18n="labelPlanExceptions">${labelProposal}</span>
              <span class="text-slate-600 text-[10px] font-bold" data-i18n="optOptional">${optOptional}</span>
            </label>
            <textarea
              id="extraNotes_${cNum}"
              name="extra_notes[]"
              rows="2"
              placeholder="${phProposal}"
              data-i18n-placeholder="phPlanExceptions"
              class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-bold border-2 border-sky-300 focus:border-sky-500 bg-white transition-all resize-y"
            ></textarea>
          </div>
        </div>
      `;

      container.appendChild(div);
      setTimeout(() => {
        div.classList.remove('opacity-0', 'translate-y-2');
      }, 20);
      if (typeof refreshAllUnitsPills === 'function') {
        refreshAllUnitsPills();
      }
    }

    function removeExtraUnit(btn) {
      const block = btn.closest('.unit-block');
      if (block) {
        block.classList.add('opacity-0', '-translate-y-2');
        setTimeout(() => {
          block.remove();
          if (typeof refreshAllUnitsPills === 'function') {
            refreshAllUnitsPills();
          }
        }, 250);
      }
    }

    // ── 1. بيانات خطة السداد والنسب لكل فترة في كائن واحد يسهل تعديله ──
    const paymentRatesConfig = {
      "7 Years": ["5% - 5%"],
      "8 Years": ["5% - 7%"],
      "9 Years": ["5% - 12%", "5% - 10%"],
      "10 Years": ["10% - 10%", "5% - 10%"]
    };

    function handlePeriodChange(selectEl) {
      const selectedPeriod = selectEl.value;
      const rateSelectContainer = document.getElementById('rateSelectContainer');
      const rateSelect = document.getElementById('rateSelect');
      const finalRateInput = document.getElementById('final_rate');
      const errRateSelect = document.getElementById('errRateSelect');
      const legacyPeriod = document.getElementById('paymentPeriod');

      if (legacyPeriod) {
        legacyPeriod.value = selectedPeriod;
      }

      // تصفير القيم السابقة دائماً عند تغيير الفترة
      if (finalRateInput) finalRateInput.value = '';
      if (rateSelect) {
        rateSelect.innerHTML = '<option value="">' + (currentLang === 'en' ? 'Select Rate...' : 'اختر النسبة...') + '</option>';
        rateSelect.classList.remove('invalid');
      }
      if (errRateSelect) errRateSelect.classList.add('hidden');

      // لو الفترة فاضية، كل حاجة تفضل فاضية وتختفي خانة النسبة
      if (!selectedPeriod) {
        if (rateSelectContainer) rateSelectContainer.classList.add('hidden');
        return;
      }

      const rates = paymentRatesConfig[selectedPeriod] || [];

      // إذا كانت الفترة تحتوي على نسبة واحدة فقط (7 أو 8) -> تظهر النسبة فوراً وتعتمد
      if (rates.length === 1) {
        if (finalRateInput) finalRateInput.value = rates[0];
        if (rateSelect) {
          rateSelect.innerHTML = `<option value="${rates[0]}" selected>${rates[0]}</option>`;
          rateSelect.classList.remove('invalid');
        }
        if (rateSelectContainer) rateSelectContainer.classList.remove('hidden');
      } 
      // إذا كانت الفترة تحتوي على أكثر من نسبة (9 أو 10) -> تظهر القائمة للاختيار
      else if (rates.length > 1) {
        if (rateSelect) {
          rateSelect.innerHTML = '<option value="">' + (currentLang === 'en' ? 'Select Rate...' : 'اختر النسبة...') + '</option>';
          rates.forEach(rate => {
            const opt = document.createElement('option');
            opt.value = rate;
            opt.textContent = rate;
            rateSelect.appendChild(opt);
          });
          rateSelect.classList.remove('invalid');
        }
        if (rateSelectContainer) rateSelectContainer.classList.remove('hidden');
      }
    }

    function handleRateChange(selectEl) {
      const chosenRate = selectEl.value;
      const finalRateInput = document.getElementById('final_rate');
      const errRateSelect = document.getElementById('errRateSelect');

      if (chosenRate) {
        if (finalRateInput) finalRateInput.value = chosenRate;
        if (errRateSelect) errRateSelect.classList.add('hidden');
        selectEl.classList.remove('invalid');
      } else {
        if (finalRateInput) finalRateInput.value = '';
        if (errRateSelect) errRateSelect.classList.remove('hidden');
        selectEl.classList.add('invalid');
      }
    }

    function handleExtraPeriodChange(counter, selectEl) {
      const selectedPeriod = selectEl.value;
      const rateContainer = document.getElementById(`extraRateContainer_${counter}`);
      const rateSelect = document.getElementById(`extraRateSelect_${counter}`);
      const errRate = selectEl.closest('.unit-block')?.querySelector('.extra-rate-error');
      const errPeriod = selectEl.closest('.unit-block')?.querySelector('.extra-period-error');

      if (errPeriod) errPeriod.classList.add('hidden');
      selectEl.classList.remove('invalid');

      if (rateSelect) {
        rateSelect.innerHTML = '<option value="">' + (currentLang === 'en' ? 'Select Rate...' : 'اختر النسبة...') + '</option>';
        rateSelect.classList.remove('invalid');
      }
      if (errRate) errRate.classList.add('hidden');

      if (!selectedPeriod) {
        if (rateContainer) rateContainer.classList.add('hidden');
        return;
      }

      const rates = paymentRatesConfig[selectedPeriod] || [];

      // إذا كانت الفترة تحتوي على نسبة واحدة فقط (7 أو 8) -> تظهر النسبة فوراً وتعتمد
      if (rates.length === 1) {
        if (rateSelect) {
          rateSelect.innerHTML = `<option value="${rates[0]}" selected>${rates[0]}</option>`;
          rateSelect.classList.remove('invalid');
        }
        if (rateContainer) rateContainer.classList.remove('hidden');
      } 
      // إذا كانت الفترة تحتوي على أكثر من نسبة (9 أو 10) -> تظهر القائمة للاختيار
      else if (rates.length > 1) {
        if (rateSelect) {
          rateSelect.innerHTML = '<option value="">' + (currentLang === 'en' ? 'Select Rate...' : 'اختر النسبة...') + '</option>';
          rates.forEach(rate => {
            const opt = document.createElement('option');
            opt.value = rate;
            opt.textContent = rate;
            rateSelect.appendChild(opt);
          });
          rateSelect.classList.remove('invalid');
        }
        if (rateContainer) rateContainer.classList.remove('hidden');
      }
    }

    function handleExtraRateChange(counter, selectEl) {
      const chosenRate = selectEl.value;
      const errRate = selectEl.closest('.unit-block')?.querySelector('.extra-rate-error');
      if (chosenRate) {
        if (errRate) errRate.classList.add('hidden');
        selectEl.classList.remove('invalid');
      } else {
        if (errRate) errRate.classList.remove('hidden');
        selectEl.classList.add('invalid');
      }
    }

    // ── Egyptian Governorates & Cities Dataset for Dynamic Address Picker ──
    const egyptGovernoratesAndCities = {
      "القاهرة": [
        "التجمع الخامس (القاهرة الجديدة)", "التجمع الأول", "الرحاب", "مدينتي", "مدينة نصر", "مصر الجديدة",
        "المعادي", "الزمالك", "وسط البلد", "المقطم", "الشروق", "بدر", "العبور", "شبرا", "عين شمس", 
        "الزيتون", "حلوان", "15 مايو", "النزهة", "المستقبل", "العاصمة الإدارية الجديدة"
      ],
      "الجيزة": [
        "الشيخ زايد", "6 أكتوبر", "حدائق أكتوبر", "أكتوبر الجديدة", "الدقي", "المهندسين", "العجوزة", 
        "الهرم", "فيصل", "العمرانية", "بولاق الدكرور", "الوراق", "إمبابة", "الحوامدية", "البدرشين", 
        "العياط", "أوسيم", "كرداسة"
      ],
      "الإسكندرية": [
        "سموحة", "سيدي جابر", "لوران", "سان ستيفانو", "كفر عبده", "رشدي", "محرم بك", "الإبراهيمية", 
        "سيدي بشر", "ميامي", "العصافرة", "المندرة", "المنتزه", "المعمورة", "العجمي", "الهانوفيل", 
        "برج العرب", "المنشية"
      ],
      "القليوبية": [
        "بنها", "شبرا الخيمة", "قليوب", "القناطر الخيرية", "الخانكة", "كفر شكر", "طوخ", "قها", "العبور", "الخصوص", "شبين القناطر"
      ],
      "الدقهلية": [
        "المنصورة", "طلخا", "ميت غمر", "دكرنس", "بلقاس", "السنبلاوين", "شربين", "بني عبيد", "منية النصر", "المنزلة", "أجا", "نبروه", "جمصة"
      ],
      "الشرقية": [
        "الزقازيق", "العاشر من رمضان", "بلبيس", "منيا القمح", "فاقوس", "أبو حماد", "أبو كبير", "الحسينية", "كفر صقر", "ديرب نجم", "الصالحية الجديدة"
      ],
      "المنوفية": [
        "شبين الكوم", "مدينة السادات", "منوف", "أشمون", "سرس الليان", "قويسنا", "بركة السبع", "تلا", "الشهداء"
      ],
      "الغربية": [
        "طنطا", "المحلة الكبرى", "كفر الزيات", "زفتى", "السنطة", "سمنود", "بسيون", "قطور"
      ],
      "كفر الشيخ": [
        "كفر الشيخ", "دسوق", "فوه", "مطوبس", "بلطيم", "سيدي سالم", "الرياض", "بيلا", "الحامول", "قلين"
      ],
      "البحيرة": [
        "دمنهور", "كفر الدوار", "رشيد", "إدكو", "أبو المطامير", "أبو حمص", "الدلنجات", "محمودية", "إيتاي البارود", "حوش عيسى", "كوم حمادة", "بدر", "وادي النطرون", "النوبارية الجديدة"
      ],
      "دمياط": [
        "دمياط", "دمياط الجديدة", "رأس البر", "فارسكور", "الزرقا", "كفر سعد", "كفر البطيخ", "عزبة البرج"
      ],
      "بورسعيد": [
        "حي الشرق", "حي العرب", "حي المناخ", "حي الضواحي", "حي الجنوب", "حي الزهور", "حي غرب", "مدينة بورفؤاد"
      ],
      "الإسماعيلية": [
        "الإسماعيلية", "فايد", "القنطرة شرق", "القنطرة غرب", "التل الكبير", "أبو صوير", "القصاصين"
      ],
      "السويس": [
        "حي السويس", "حي الأربعين", "حي عتاقة", "حي فيصل", "حي الجناين", "العين السخنة"
      ],
      "مطروح": [
        "العلمين", "العلمين الجديدة", "مرسى مطروح", "الضبعة", "الحمام", "سيدي عبد الرحمن", "سيدي براني", "السلوم", "سيوة"
      ],
      "البحر الأحمر": [
        "الغردقة", "الجونة", "سفاجا", "سهل حشيش", "القصيرة", "مرسى علم", "رأس غارب"
      ],
      "جنوب سيناء": [
        "شرم الشيخ", "دهب", "نويبع", "طابا", "طور سيناء", "رأس سدر", "سانت كاترين"
      ],
      "شمال سيناء": [
        "العريش", "الشيخ زويد", "رفح", "بئر العبد"
      ],
      "الفيوم": [
        "الفيوم", "الفيوم الجديدة", "طامية", "سنورس", "إطسا", "إبشواي", "يوسف الصديق"
      ],
      "بني سويف": [
        "بني سويف", "بني سويف الجديدة", "الواسطى", "ناصر", "إهناسيا", "ببا", "الفشن", "سمسطا"
      ],
      "المنيا": [
        "المنيا", "المنيا الجديدة", "مغاغة", "بني مزار", "مطاي", "سمالوط", "أبو قرقاص", "ملوي", "دير مواس"
      ],
      "أسيوط": [
        "أسيوط", "أسيوط الجديدة", "ديروط", "القوصية", "أبنوب", "منفلوط", "الفتح", "أبو تيج", "الغنايم"
      ],
      "سوهاج": [
        "سوهاج", "سوهاج الجديدة", "أخميم", "البلينا", "المراغة", "جرجا", "طهطا", "طما"
      ],
      "قنا": [
        "قنا", "قنا الجديدة", "نجع حمادي", "دشنا", "فرشوط", "أبو تشت", "قوص", "نقادة"
      ],
      "الأقصر": [
        "الأقصر", "طيبة الجديدة", "إسنا", "أرمنت", "البياضية", "القرنة"
      ],
      "أسوان": [
        "أسوان", "أسوان الجديدة", "دراو", "كوم أمبو", "نصر النوبة", "إدفو", "أبو سمبل"
      ],
      "الوادي الجديد": [
        "الخارجة", "الداخلة", "الفرافرة", "باريس", "بلاط"
      ]
    };

    const egyptGovernoratesEn = {
      "القاهرة": "Cairo",
      "الجيزة": "Giza",
      "الإسكندرية": "Alexandria",
      "القليوبية": "Qalyubia",
      "الدقهلية": "Dakahlia",
      "الشرقية": "Sharqia",
      "المنوفية": "Monufia",
      "الغربية": "Gharbia",
      "كفر الشيخ": "Kafr El-Sheikh",
      "البحيرة": "Beheira",
      "دمياط": "Damietta",
      "بورسعيد": "Port Said",
      "الإسماعيلية": "Ismailia",
      "السويس": "Suez",
      "مطروح": "Matrouh",
      "البحر الأحمر": "Red Sea",
      "جنوب سيناء": "South Sinai",
      "شمال سيناء": "North Sinai",
      "الفيوم": "Fayoum",
      "بني سويف": "Beni Suef",
      "المنيا": "Minya",
      "أسيوط": "Asyut",
      "سوهاج": "Sohag",
      "قنا": "Qena",
      "الأقصر": "Luxor",
      "أسوان": "Aswan",
      "الوادي الجديد": "New Valley"
    };

    // ── World Countries & Major Cities Dataset across all 6 Continents ──
    const worldCountriesData = [
  {
    "nameAr": "مصر",
    "nameEn": "Egypt",
    "flag": "🇪🇬",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "pinned": true,
    "cities": [
      "القاهرة",
      "الجيزة",
      "الإسكندرية",
      "الشيخ زايد",
      "6 أكتوبر",
      "التجمع الخامس",
      "المنصورة",
      "طنطا",
      "بورسعيد",
      "الإسماعيلية",
      "السويس",
      "الغردقة",
      "شرم الشيخ",
      "أسيوط",
      "سوهاج",
      "الأقصر",
      "أسوان"
    ]
  },
  {
    "nameAr": "المملكة العربية السعودية",
    "nameEn": "Saudi Arabia",
    "flag": "🇸🇦",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "الرياض",
      "جدة",
      "مكة المكرمة",
      "المدينة المنورة",
      "الدمام",
      "الخبر",
      "الظهران",
      "الأحساء",
      "الجبيل",
      "الطائف",
      "القصيم (بريدة)",
      "تبوك",
      "خميس مشيط",
      "أبها",
      "حائل",
      "نجران",
      "جازان",
      "ينبع",
      "حفر الباطن",
      "الخرج"
    ]
  },
  {
    "nameAr": "الإمارات العربية المتحدة",
    "nameEn": "United Arab Emirates",
    "flag": "🇦🇪",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "دبي",
      "أبوظبي",
      "الشارقة",
      "عجمان",
      "رأس الخيمة",
      "الفجيرة",
      "أم القيوين",
      "العين",
      "خورفكان"
    ]
  },
  {
    "nameAr": "الكويت",
    "nameEn": "Kuwait",
    "flag": "🇰🇼",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "مدينة الكويت",
      "حولي",
      "السالمية",
      "الفروانية",
      "الأحمدي",
      "الجهراء",
      "مبارك الكبير",
      "الفحيحيل"
    ]
  },
  {
    "nameAr": "قطر",
    "nameEn": "Qatar",
    "flag": "🇶🇦",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "الدوحة",
      "الريان",
      "الوكرة",
      "الخور",
      "لوسيل",
      "أم صلال",
      "الظعاين",
      "الشحانية"
    ]
  },
  {
    "nameAr": "سلطنة عمان",
    "nameEn": "Oman",
    "flag": "🇴🇲",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "مسقط",
      "صلالة",
      "صحار",
      "نزوى",
      "صور",
      "السيب",
      "بوشر",
      "مطرح",
      "البريمي"
    ]
  },
  {
    "nameAr": "البحرين",
    "nameEn": "Bahrain",
    "flag": "🇧🇭",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "المنامة",
      "المحرق",
      "الرفاع",
      "مدينة عيسى",
      "مدينة حمد",
      "سترة",
      "البديع"
    ]
  },
  {
    "nameAr": "الأردن",
    "nameEn": "Jordan",
    "flag": "🇯🇴",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "عمان",
      "إربد",
      "الزرقاء",
      "العقبة",
      "السلط",
      "مادبا",
      "جرش",
      "الكرك"
    ]
  },
  {
    "nameAr": "العراق",
    "nameEn": "Iraq",
    "flag": "🇮🇶",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "بغداد",
      "البصرة",
      "أربيل",
      "الموصل",
      "النجف",
      "كربلاء",
      "السليمانية",
      "كركوك"
    ]
  },
  {
    "nameAr": "لبنان",
    "nameEn": "Lebanon",
    "flag": "🇱🇧",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "بيروت",
      "طرابلس",
      "صيدا",
      "جونيه",
      "زحلة",
      "صور",
      "جبيل"
    ]
  },
  {
    "nameAr": "فلسطين",
    "nameEn": "Palestine",
    "flag": "🇵🇸",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "القدس",
      "رام الله",
      "غزة",
      "نابلس",
      "الخليل",
      "بيت لحم",
      "جنين",
      "طولكرم"
    ]
  },
  {
    "nameAr": "السودان",
    "nameEn": "Sudan",
    "flag": "🇸🇩",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "pinned": true,
    "cities": [
      "الخرطوم",
      "أم درمان",
      "بورتسودان",
      "كسلا",
      "ود مدني",
      "عطبرة"
    ]
  },
  {
    "nameAr": "ليبيا",
    "nameEn": "Libya",
    "flag": "🇱🇾",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "pinned": true,
    "cities": [
      "طرابلس",
      "بنغازي",
      "مصراتة",
      "الزاوية",
      "البيضاء",
      "طبرق",
      "سرت"
    ]
  },
  {
    "nameAr": "تونس",
    "nameEn": "Tunisia",
    "flag": "🇹🇳",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "pinned": true,
    "cities": [
      "تونس العاصمة",
      "صفاقس",
      "سوسة",
      "نابل",
      "المنستير",
      "بنزرت"
    ]
  },
  {
    "nameAr": "الجزائر",
    "nameEn": "Algeria",
    "flag": "🇩🇿",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "pinned": true,
    "cities": [
      "الجزائر العاصمة",
      "وهران",
      "قسنطينة",
      "عنابة",
      "سطيف",
      "باتنة"
    ]
  },
  {
    "nameAr": "المغرب",
    "nameEn": "Morocco",
    "flag": "🇲🇦",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "pinned": true,
    "cities": [
      "الدار البيضاء",
      "الرباط",
      "مراكش",
      "طنجة",
      "فاس",
      "أكادير",
      "مكناس"
    ]
  },
  {
    "nameAr": "اليمن",
    "nameEn": "Yemen",
    "flag": "🇾🇪",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "صنعاء",
      "عدن",
      "تعز",
      "الحديدة",
      "المكلا",
      "إب"
    ]
  },
  {
    "nameAr": "سوريا",
    "nameEn": "Syria",
    "flag": "🇸🇾",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "دمشق",
      "ريف دمشق",
      "حلب",
      "حمص",
      "اللاذقية",
      "طرطوس",
      "حماة"
    ]
  },
  {
    "nameAr": "موريتانيا",
    "nameEn": "Mauritania",
    "flag": "🇲🇷",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "pinned": true,
    "cities": [
      "نواكشوط",
      "نواذيبو",
      "روصو"
    ]
  },
  {
    "nameAr": "الصومال",
    "nameEn": "Somalia",
    "flag": "🇸🇴",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "مقديشو",
      "هرجيسا",
      "بوساسو"
    ]
  },
  {
    "nameAr": "جيبوتي",
    "nameEn": "Djibouti",
    "flag": "🇩🇯",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "جيبوتي العاصمة",
      "علي صبيح"
    ]
  },
  {
    "nameAr": "جزر القمر",
    "nameEn": "Comoros",
    "flag": "🇰🇲",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "موروني",
      "موتسامودو"
    ]
  },
  {
    "nameAr": "الولايات المتحدة الأمريكية (USA)",
    "nameEn": "United States (USA)",
    "flag": "🇺🇸",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "pinned": true,
    "cities": [
      "نيويورك (New York)",
      "كاليفورنيا (California)",
      "تكساس (Texas)",
      "فلوريدا (Florida)",
      "إلينوي (Chicago - Illinois)",
      "واشنطن (Washington)",
      "نيوجيرسي (New Jersey)",
      "ماساتشوستس (Boston)",
      "فرجينيا (Virginia)",
      "بنسلفانيا (Pennsylvania)",
      "جورجيا (Atlanta)",
      "ميشيغان (Michigan)",
      "نورث كارولاينا",
      "أوهايو",
      "كولورادو"
    ]
  },
  {
    "nameAr": "كندا (Canada)",
    "nameEn": "Canada",
    "flag": "🇨🇦",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "pinned": true,
    "cities": [
      "تورونتو (Toronto)",
      "مونتريال (Montreal)",
      "فانكوفر (Vancouver)",
      "أوتاوا (Ottawa)",
      "كالغاري (Calgary)",
      "إدمونتون (Edmonton)",
      "كيبك سيتي (Quebec City)"
    ]
  },
  {
    "nameAr": "المكسيك",
    "nameEn": "Mexico",
    "flag": "🇲🇽",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "مكسيكو سيتي (Mexico City)",
      "غوادالاخارا",
      "مونتيري",
      "كانكون",
      "بويبلا"
    ]
  },
  {
    "nameAr": "بنما",
    "nameEn": "Panama",
    "flag": "🇵🇦",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "بنما سيتي",
      "كولون"
    ]
  },
  {
    "nameAr": "كوستاريكا",
    "nameEn": "Costa Rica",
    "flag": "🇨🇷",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "سان خوسيه",
      "ألاخويلا"
    ]
  },
  {
    "nameAr": "كوبا",
    "nameEn": "Cuba",
    "flag": "🇨🇺",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "هافانا (Havana)",
      "سانتياغو دي كوبا"
    ]
  },
  {
    "nameAr": "جمهورية الدومينيكان",
    "nameEn": "Dominican Republic",
    "flag": "🇩🇴",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "سانتو دومينغو",
      "سانتياغو"
    ]
  },
  {
    "nameAr": "جامايكا",
    "nameEn": "Jamaica",
    "flag": "🇯🇲",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "كينغستون",
      "مونتيغو باي"
    ]
  },
  {
    "nameAr": "غواتيمالا",
    "nameEn": "Guatemala",
    "flag": "🇬🇹",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "غواتيمالا سيتي"
    ]
  },
  {
    "nameAr": "هندوراس",
    "nameEn": "Honduras",
    "flag": "🇭🇳",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "تيغوسيغالبا",
      "سان بيدرو سولا"
    ]
  },
  {
    "nameAr": "السلفادور",
    "nameEn": "El Salvador",
    "flag": "🇸🇻",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "سان سلفادور"
    ]
  },
  {
    "nameAr": "نيكاراغوا",
    "nameEn": "Nicaragua",
    "flag": "🇳🇮",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "ماناغوا"
    ]
  },
  {
    "nameAr": "هايتي",
    "nameEn": "Haiti",
    "flag": "🇭🇹",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "بورت أو برانس"
    ]
  },
  {
    "nameAr": "باهاماس",
    "nameEn": "Bahamas",
    "flag": "🇧🇸",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "ناساو"
    ]
  },
  {
    "nameAr": "ترينيداد وتوباغو",
    "nameEn": "Trinidad and Tobago",
    "flag": "🇹🇹",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "بورت أوف سبين"
    ]
  },
  {
    "nameAr": "باربادوس",
    "nameEn": "Barbados",
    "flag": "🇧🇧",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "بريدج تاون"
    ]
  },
  {
    "nameAr": "بليز",
    "nameEn": "Belize",
    "flag": "🇧🇿",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "بلموبان",
      "بليز سيتي"
    ]
  },
  {
    "nameAr": "سانت لوسيا",
    "nameEn": "Saint Lucia",
    "flag": "🇱🇨",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "كاستريس"
    ]
  },
  {
    "nameAr": "أنتيغوا وباربودا",
    "nameEn": "Antigua and Barbuda",
    "flag": "🇦🇬",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "سانت جونز"
    ]
  },
  {
    "nameAr": "غرينادا",
    "nameEn": "Grenada",
    "flag": "🇬🇩",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "سانت جورجز"
    ]
  },
  {
    "nameAr": "سانت فنسنت والغرينادين",
    "nameEn": "Saint Vincent",
    "flag": "🇻🇨",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "كينغستاون"
    ]
  },
  {
    "nameAr": "سانت كيتس ونيفيس",
    "nameEn": "Saint Kitts and Nevis",
    "flag": "🇰🇳",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "باستير"
    ]
  },
  {
    "nameAr": "دومينيكا",
    "nameEn": "Dominica",
    "flag": "🇩🇲",
    "continentAr": "أمريكا الشمالية",
    "continentEn": "North America",
    "cities": [
      "روسو"
    ]
  },
  {
    "nameAr": "البرازيل (Brazil)",
    "nameEn": "Brazil",
    "flag": "🇧🇷",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "ساو باولو (Sao Paulo)",
      "ريو دي جانيرو (Rio de Janeiro)",
      "برازيليا (Brasilia)",
      "سلفادور",
      "فورتاليزا"
    ]
  },
  {
    "nameAr": "الأرجنتين",
    "nameEn": "Argentina",
    "flag": "🇦🇷",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "بوينس آيرس (Buenos Aires)",
      "قرطبة",
      "روزاريو",
      "ميندوزا"
    ]
  },
  {
    "nameAr": "كولومبيا",
    "nameEn": "Colombia",
    "flag": "🇨🇴",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "بوغوتا (Bogota)",
      "ميديلين",
      "كالي",
      "بارانكيا"
    ]
  },
  {
    "nameAr": "تشيلي",
    "nameEn": "Chile",
    "flag": "🇨🇱",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "سانتياغو (Santiago)",
      "فالبارايسو",
      "كونسيبسيون"
    ]
  },
  {
    "nameAr": "بيرو",
    "nameEn": "Peru",
    "flag": "🇵🇪",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "ليما (Lima)",
      "أريكيبا",
      "كوزكو"
    ]
  },
  {
    "nameAr": "فنزويلا",
    "nameEn": "Venezuela",
    "flag": "🇻🇪",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "كاراكاس (Caracas)",
      "ماراكايبو",
      "فالنسيا"
    ]
  },
  {
    "nameAr": "الإكوادور",
    "nameEn": "Ecuador",
    "flag": "🇪🇨",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "كيتو (Quito)",
      "غواياكيل",
      "كوينكا"
    ]
  },
  {
    "nameAr": "بوليفيا",
    "nameEn": "Bolivia",
    "flag": "🇧🇴",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "لاباز",
      "سانتا كروز",
      "سوكري"
    ]
  },
  {
    "nameAr": "باراغواي",
    "nameEn": "Paraguay",
    "flag": "🇵🇾",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "أسونسيون"
    ]
  },
  {
    "nameAr": "أوروغواي",
    "nameEn": "Uruguay",
    "flag": "🇺🇾",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "مونتيفيديو"
    ]
  },
  {
    "nameAr": "غيانا",
    "nameEn": "Guyana",
    "flag": "🇬🇾",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "جورج تاون"
    ]
  },
  {
    "nameAr": "سورينام",
    "nameEn": "Suriname",
    "flag": "🇸🇷",
    "continentAr": "أمريكا الجنوبية",
    "continentEn": "South America",
    "cities": [
      "باراماريبو"
    ]
  },
  {
    "nameAr": "المملكة المتحدة (بريطانيا - UK)",
    "nameEn": "United Kingdom (UK)",
    "flag": "🇬🇧",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "pinned": true,
    "cities": [
      "لندن (London)",
      "مانشستر (Manchester)",
      "برمنغهام (Birmingham)",
      "ليفربول (Liverpool)",
      "ليدز (Leeds)",
      "إدنبرة (Edinburgh)",
      "غلاسكو (Glasgow)",
      "كارديف (Cardiff)",
      "بريستول",
      "نيوكاسل"
    ]
  },
  {
    "nameAr": "ألمانيا",
    "nameEn": "Germany",
    "flag": "🇩🇪",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "pinned": true,
    "cities": [
      "برلين (Berlin)",
      "ميونخ (Munich)",
      "فرانكفورت (Frankfurt)",
      "هامبورغ (Hamburg)",
      "كولونيا (Cologne)",
      "شتوتغارت (Stuttgart)",
      "دوسلدورف (Dusseldorf)",
      "دورتموند",
      "لايبزيغ"
    ]
  },
  {
    "nameAr": "فرنسا",
    "nameEn": "France",
    "flag": "🇫🇷",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "pinned": true,
    "cities": [
      "باريس (Paris)",
      "ليون (Lyon)",
      "مارسيليا (Marseille)",
      "نيس (Nice)",
      "تولوز (Toulouse)",
      "بوردو (Bordeaux)",
      "ستراسبورغ (Strasbourg)",
      "ليل (Lille)"
    ]
  },
  {
    "nameAr": "إيطاليا",
    "nameEn": "Italy",
    "flag": "🇮🇹",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "pinned": true,
    "cities": [
      "روما (Rome)",
      "ميلانو (Milan)",
      "نابولي (Naples)",
      "تورينو (Turin)",
      "فلورنسا (Florence)",
      "بولونيا (Bologna)",
      "البندقية (Venice)",
      "جنوى"
    ]
  },
  {
    "nameAr": "إسبانيا",
    "nameEn": "Spain",
    "flag": "🇪🇸",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "pinned": true,
    "cities": [
      "مدريد (Madrid)",
      "برشلونة (Barcelona)",
      "فالنسيا (Valencia)",
      "إشبيلية (Seville)",
      "سرقسطة (Zaragoza)",
      "مالقة (Malaga)",
      "بلباو (Bilbao)"
    ]
  },
  {
    "nameAr": "هولندا",
    "nameEn": "Netherlands",
    "flag": "🇳🇱",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "pinned": true,
    "cities": [
      "أمستردام (Amsterdam)",
      "روتردام (Rotterdam)",
      "لاهاي (The Hague)",
      "أوترخت (Utrecht)",
      "أيندهوفن"
    ]
  },
  {
    "nameAr": "سويسرا",
    "nameEn": "Switzerland",
    "flag": "🇨🇭",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "pinned": true,
    "cities": [
      "زيورخ (Zurich)",
      "جنيف (Geneva)",
      "بازل (Basel)",
      "برن (Bern)",
      "لوزان (Lausanne)"
    ]
  },
  {
    "nameAr": "السويد",
    "nameEn": "Sweden",
    "flag": "🇸🇪",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "ستوكهولم (Stockholm)",
      "غوتنبرغ (Gothenburg)",
      "مالمو (Malmo)",
      "أوبسالا"
    ]
  },
  {
    "nameAr": "النمسا",
    "nameEn": "Austria",
    "flag": "🇦🇹",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "فيينا (Vienna)",
      "غراتس (Graz)",
      "لينتس (Linz)",
      "سالزبورغ (Salzburg)",
      "إنسبروك"
    ]
  },
  {
    "nameAr": "بلجيكا",
    "nameEn": "Belgium",
    "flag": "🇧🇪",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "بروكسل (Brussels)",
      "أنتويرب (Antwerp)",
      "غنت (Ghent)",
      "بروج (Bruges)",
      "لييج"
    ]
  },
  {
    "nameAr": "اليونان",
    "nameEn": "Greece",
    "flag": "🇬🇷",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "أثينا (Athens)",
      "سالونيك (Thessaloniki)",
      "باتراس (Patras)",
      "هيراكليون"
    ]
  },
  {
    "nameAr": "البرتغال",
    "nameEn": "Portugal",
    "flag": "🇵🇹",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "لشبونة (Lisbon)",
      "بورتو (Porto)",
      "براغا (Braga)",
      "كويمبرا"
    ]
  },
  {
    "nameAr": "روسيا",
    "nameEn": "Russia",
    "flag": "🇷🇺",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "موسكو (Moscow)",
      "سانت بطرسبرغ (St. Petersburg)",
      "نوفوسيبيرسك",
      "يكاترينبورغ",
      "كازان"
    ]
  },
  {
    "nameAr": "بولندا",
    "nameEn": "Poland",
    "flag": "🇵🇱",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "وارسو (Warsaw)",
      "كراكوف (Krakow)",
      "غدانسك",
      "فروتسواف",
      "بوزنان"
    ]
  },
  {
    "nameAr": "أوكرانيا",
    "nameEn": "Ukraine",
    "flag": "🇺🇦",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "كييف (Kyiv)",
      "لفيف (Lviv)",
      "أوديسا (Odesa)",
      "خاركيف"
    ]
  },
  {
    "nameAr": "النرويج",
    "nameEn": "Norway",
    "flag": "🇳🇴",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "أوسلو (Oslo)",
      "بيرغن (Bergen)",
      "تروندهايم",
      "ستافانغر"
    ]
  },
  {
    "nameAr": "الدنمارك",
    "nameEn": "Denmark",
    "flag": "🇩🇰",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "كوبنهاغن (Copenhagen)",
      "آرهوس",
      "أودنسه"
    ]
  },
  {
    "nameAr": "فنلندا",
    "nameEn": "Finland",
    "flag": "🇫🇮",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "هلسنكي (Helsinki)",
      "إسبو",
      "تامبيري",
      "فانتا"
    ]
  },
  {
    "nameAr": "أيرلندا",
    "nameEn": "Ireland",
    "flag": "🇮🇪",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "دبلن (Dublin)",
      "كورك (Cork)",
      "غالواي",
      "ليمريك"
    ]
  },
  {
    "nameAr": "جمهورية التشيك",
    "nameEn": "Czech Republic",
    "flag": "🇨🇿",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "براغ (Prague)",
      "برنو",
      "أوسترافا"
    ]
  },
  {
    "nameAr": "المجر",
    "nameEn": "Hungary",
    "flag": "🇭🇺",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "بودابست (Budapest)",
      "ديبريتسين",
      "سيغيد"
    ]
  },
  {
    "nameAr": "رومانيا",
    "nameEn": "Romania",
    "flag": "🇷🇴",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "بوخارست (Bucharest)",
      "كلوج نابوكا",
      "تيميشوارا"
    ]
  },
  {
    "nameAr": "كرواتيا",
    "nameEn": "Croatia",
    "flag": "🇭🇷",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "زغرب (Zagreb)",
      "سبليت (Split)",
      "رييكا",
      "دوبروفنيك"
    ]
  },
  {
    "nameAr": "صربيا",
    "nameEn": "Serbia",
    "flag": "🇷🇸",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "بلغراد (Belgrade)",
      "نوفي ساد",
      "نيش"
    ]
  },
  {
    "nameAr": "سلوفاكيا",
    "nameEn": "Slovakia",
    "flag": "🇸🇰",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "براتيسلافا (Bratislava)",
      "كوشيتسه"
    ]
  },
  {
    "nameAr": "بلغاريا",
    "nameEn": "Bulgaria",
    "flag": "🇧🇬",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "صوفيا (Sofia)",
      "بلوفديف",
      "فارنا"
    ]
  },
  {
    "nameAr": "بيلاروسيا",
    "nameEn": "Belarus",
    "flag": "🇧🇾",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "مينسك (Minsk)",
      "غوميل",
      "بريست"
    ]
  },
  {
    "nameAr": "سلوفينيا",
    "nameEn": "Slovenia",
    "flag": "🇸🇮",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "ليوبليانا (Ljubljana)",
      "ماريبور"
    ]
  },
  {
    "nameAr": "قبرص",
    "nameEn": "Cyprus",
    "flag": "🇨🇾",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "نيقوسيا (Nicosia)",
      "ليماسول",
      "لارنكا",
      "بافوس"
    ]
  },
  {
    "nameAr": "ليتوانيا",
    "nameEn": "Lithuania",
    "flag": "🇱🇹",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "فيلنيوس (Vilnius)",
      "كاوناس"
    ]
  },
  {
    "nameAr": "لاتفيا",
    "nameEn": "Latvia",
    "flag": "🇱🇻",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "ريغا (Riga)",
      "داوغافبيلس"
    ]
  },
  {
    "nameAr": "إستونيا",
    "nameEn": "Estonia",
    "flag": "🇪🇪",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "تالين (Tallinn)",
      "تارتو"
    ]
  },
  {
    "nameAr": "البوسنة والهرسك",
    "nameEn": "Bosnia & Herzegovina",
    "flag": "🇧🇦",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "سراييفو (Sarajevo)",
      "بانيا لوكا",
      "موستار"
    ]
  },
  {
    "nameAr": "ألبانيا",
    "nameEn": "Albania",
    "flag": "🇦🇱",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "تيرانا (Tirana)",
      "دوريس",
      "فلورا"
    ]
  },
  {
    "nameAr": "مقدونيا الشمالية",
    "nameEn": "North Macedonia",
    "flag": "🇲🇰",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "سكوبيه (Skopje)",
      "بيتولا"
    ]
  },
  {
    "nameAr": "مولدوفا",
    "nameEn": "Moldova",
    "flag": "🇲🇩",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "كيشيناو (Chisinau)"
    ]
  },
  {
    "nameAr": "مالطا",
    "nameEn": "Malta",
    "flag": "🇲🇹",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "فاليتا (Valletta)",
      "سليمة"
    ]
  },
  {
    "nameAr": "لوكسمبورغ",
    "nameEn": "Luxembourg",
    "flag": "🇱🇺",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "لوكسمبورغ العاصمة"
    ]
  },
  {
    "nameAr": "أيسلندا",
    "nameEn": "Iceland",
    "flag": "🇮🇸",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "ريكيافيك (Reykjavik)"
    ]
  },
  {
    "nameAr": "الجبل الأسود",
    "nameEn": "Montenegro",
    "flag": "🇲🇪",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "بودغوريتسا (Podgorica)",
      "كوتور"
    ]
  },
  {
    "nameAr": "موناكو",
    "nameEn": "Monaco",
    "flag": "🇲🇨",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "موناكو"
    ]
  },
  {
    "nameAr": "أندورا",
    "nameEn": "Andorra",
    "flag": "🇦🇩",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "أندورا لا فيلا"
    ]
  },
  {
    "nameAr": "ليختنشتاين",
    "nameEn": "Liechtenstein",
    "flag": "🇱🇮",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "فادوز"
    ]
  },
  {
    "nameAr": "سان مارينو",
    "nameEn": "San Marino",
    "flag": "🇸🇲",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "سان مارينو"
    ]
  },
  {
    "nameAr": "الفاتيكان",
    "nameEn": "Vatican City",
    "flag": "🇻🇦",
    "continentAr": "أوروبا",
    "continentEn": "Europe",
    "cities": [
      "مدينة الفاتيكان"
    ]
  },
  {
    "nameAr": "تركيا",
    "nameEn": "Turkey",
    "flag": "🇹🇷",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "pinned": true,
    "cities": [
      "إسطنبول (Istanbul)",
      "أنقرة (Ankara)",
      "إزمير (Izmir)",
      "بورصة (Bursa)",
      "أنطاليا (Antalya)",
      "غازي عنتاب",
      "قونية",
      "طرابزون",
      "أضنة"
    ]
  },
  {
    "nameAr": "الصين",
    "nameEn": "China",
    "flag": "🇨🇳",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "بكين (Beijing)",
      "شنغهاي (Shanghai)",
      "قوانغتشو (Guangzhou)",
      "شنزن (Shenzhen)",
      "تشنغدو",
      "هانغتشو"
    ]
  },
  {
    "nameAr": "اليابان",
    "nameEn": "Japan",
    "flag": "🇯🇵",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "طوكيو (Tokyo)",
      "أوساكا (Osaka)",
      "يوكوهاما (Yokohama)",
      "ناغويا (Nagoya)",
      "كيوتو (Kyoto)",
      "سابورو"
    ]
  },
  {
    "nameAr": "الهند",
    "nameEn": "India",
    "flag": "🇮🇳",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "نيودلهي (New Delhi)",
      "مومباي (Mumbai)",
      "بنغالور (Bangalore)",
      "حيدر أباد",
      "تشيناي",
      "كولكاتا"
    ]
  },
  {
    "nameAr": "ماليزيا",
    "nameEn": "Malaysia",
    "flag": "🇲🇾",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "كوالالمبور (Kuala Lumpur)",
      "بينانغ (Penang)",
      "جوهور بهرو",
      "سلاغور",
      "كوتا كينابالو"
    ]
  },
  {
    "nameAr": "إندونيسيا",
    "nameEn": "Indonesia",
    "flag": "🇮🇩",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "جاكرتا (Jakarta)",
      "سورابايا",
      "باندونغ",
      "بالي (Bali)",
      "ميدان"
    ]
  },
  {
    "nameAr": "سنغافورة",
    "nameEn": "Singapore",
    "flag": "🇸🇬",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "سنغافورة (العاصمة)"
    ]
  },
  {
    "nameAr": "كوريا الجنوبية",
    "nameEn": "South Korea",
    "flag": "🇰🇷",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "سول (Seoul)",
      "بوسان (Busan)",
      "إنتشون",
      "دايغو"
    ]
  },
  {
    "nameAr": "تايلاند",
    "nameEn": "Thailand",
    "flag": "🇹🇭",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "بانكوك (Bangkok)",
      "بوكيت (Phuket)",
      "تشيانغ ماي",
      "باتايا"
    ]
  },
  {
    "nameAr": "فيتنام",
    "nameEn": "Vietnam",
    "flag": "🇻🇳",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "هانوي (Hanoi)",
      "هو تشي منه (Ho Chi Minh)",
      "دا نانغ"
    ]
  },
  {
    "nameAr": "الفلبين",
    "nameEn": "Philippines",
    "flag": "🇵🇭",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "مانيلا (Manila)",
      "كيزون سيتي",
      "سيبو",
      "دافاو"
    ]
  },
  {
    "nameAr": "باكستان",
    "nameEn": "Pakistan",
    "flag": "🇵🇰",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "إسلام آباد (Islamabad)",
      "كراتشي (Karachi)",
      "لاهور (Lahore)",
      "فيصل آباد"
    ]
  },
  {
    "nameAr": "بنغلاديش",
    "nameEn": "Bangladesh",
    "flag": "🇧🇩",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "دكا (Dhaka)",
      "شيتاغونغ",
      "سيلهيت"
    ]
  },
  {
    "nameAr": "سريلانكا",
    "nameEn": "Sri Lanka",
    "flag": "🇱🇰",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "كولومبو (Colombo)",
      "كاندي",
      "غالي"
    ]
  },
  {
    "nameAr": "كازاخستان",
    "nameEn": "Kazakhstan",
    "flag": "🇰🇿",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "أستانا (Astana)",
      "ألماتي (Almaty)",
      "شيمكنت"
    ]
  },
  {
    "nameAr": "أوزبكستان",
    "nameEn": "Uzbekistan",
    "flag": "🇺🇿",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "طشقند (Tashkent)",
      "سمرقند (Samarkand)",
      "بخارى"
    ]
  },
  {
    "nameAr": "أذربيجان",
    "nameEn": "Azerbaijan",
    "flag": "🇦🇿",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "باكو (Baku)",
      "غنجة",
      "سومقاييت"
    ]
  },
  {
    "nameAr": "جورجيا",
    "nameEn": "Georgia",
    "flag": "🇬🇪",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "تبليسي (Tbilisi)",
      "باتومي (Batumi)",
      "كوتايسي"
    ]
  },
  {
    "nameAr": "أرمينيا",
    "nameEn": "Armenia",
    "flag": "🇦🇲",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "يريفان (Yerevan)",
      "غيومري"
    ]
  },
  {
    "nameAr": "إيران",
    "nameEn": "Iran",
    "flag": "🇮🇷",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "طهران (Tehran)",
      "مشهد",
      "أصفهان",
      "شيراز",
      "تبريز"
    ]
  },
  {
    "nameAr": "أفغانستان",
    "nameEn": "Afghanistan",
    "flag": "🇦🇫",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "كابول",
      "هرات",
      "مزار شريف"
    ]
  },
  {
    "nameAr": "نيبال",
    "nameEn": "Nepal",
    "flag": "🇳🇵",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "كاتماندو",
      "بوكارا"
    ]
  },
  {
    "nameAr": "ميانمار (بورما)",
    "nameEn": "Myanmar",
    "flag": "🇲🇲",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "نايبيداو",
      "يانغون",
      "ماندالاي"
    ]
  },
  {
    "nameAr": "كمبوديا",
    "nameEn": "Cambodia",
    "flag": "🇰🇭",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "بنوم بنه",
      "سيام ريب"
    ]
  },
  {
    "nameAr": "لاوس",
    "nameEn": "Laos",
    "flag": "🇱🇦",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "فينتيان",
      "لوانغ برابانغ"
    ]
  },
  {
    "nameAr": "بروناي",
    "nameEn": "Brunei",
    "flag": "🇧🇳",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "بندر سري بكاوان"
    ]
  },
  {
    "nameAr": "منغوليا",
    "nameEn": "Mongolia",
    "flag": "🇲🇳",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "أولان باتور"
    ]
  },
  {
    "nameAr": "طاجيكستان",
    "nameEn": "Tajikistan",
    "flag": "🇹🇯",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "دوشنبه",
      "خجند"
    ]
  },
  {
    "nameAr": "تركمانستان",
    "nameEn": "Turkmenistan",
    "flag": "🇹🇲",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "عشق آباد",
      "تركمنباشي"
    ]
  },
  {
    "nameAr": "قيرغيزستان",
    "nameEn": "Kyrgyzstan",
    "flag": "🇰🇬",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "بيشكك",
      "أوش"
    ]
  },
  {
    "nameAr": "جزر المالديف",
    "nameEn": "Maldives",
    "flag": "🇲🇻",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "ماليه (Male)"
    ]
  },
  {
    "nameAr": "بوتان",
    "nameEn": "Bhutan",
    "flag": "🇧🇹",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "تيمفو"
    ]
  },
  {
    "nameAr": "تيمور الشرقية",
    "nameEn": "Timor-Leste",
    "flag": "🇹🇱",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "ديلي"
    ]
  },
  {
    "nameAr": "تايوان",
    "nameEn": "Taiwan",
    "flag": "🇹🇼",
    "continentAr": "آسيا",
    "continentEn": "Asia",
    "cities": [
      "تايبيه (Taipei)",
      "كاوهسيونغ",
      "تايتشونغ"
    ]
  },
  {
    "nameAr": "جنوب إفريقيا",
    "nameEn": "South Africa",
    "flag": "🇿🇦",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "جوهانسبرغ (Johannesburg)",
      "كيب تاون (Cape Town)",
      "ديربان (Durban)",
      "بريتوريا"
    ]
  },
  {
    "nameAr": "نيجيريا",
    "nameEn": "Nigeria",
    "flag": "🇳🇬",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "لاغوس (Lagos)",
      "أبوجا (Abuja)",
      "إبادان",
      "كانو"
    ]
  },
  {
    "nameAr": "كينيا",
    "nameEn": "Kenya",
    "flag": "🇰🇪",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "نيروبي (Nairobi)",
      "مومباسا (Mombasa)",
      "كيسومو"
    ]
  },
  {
    "nameAr": "غانا",
    "nameEn": "Ghana",
    "flag": "🇬🇭",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "أكرا (Accra)",
      "كوماسي",
      "تامالي"
    ]
  },
  {
    "nameAr": "إثيوبيا",
    "nameEn": "Ethiopia",
    "flag": "🇪🇹",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "أديس أبابا (Addis Ababa)",
      "ديرة داوا",
      "بحر دار"
    ]
  },
  {
    "nameAr": "السنغال",
    "nameEn": "Senegal",
    "flag": "🇸🇳",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "داكار (Dakar)",
      "توبا",
      "ثيس"
    ]
  },
  {
    "nameAr": "كوت ديفوار (ساحل العاج)",
    "nameEn": "Ivory Coast",
    "flag": "🇨🇮",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "أبيدجان (Abidjan)",
      "ياموسوكرو",
      "بواكي"
    ]
  },
  {
    "nameAr": "الكاميرون",
    "nameEn": "Cameroon",
    "flag": "🇨🇲",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "ياوندي (Yaounde)",
      "دوالا",
      "غاروا"
    ]
  },
  {
    "nameAr": "تنزانيا",
    "nameEn": "Tanzania",
    "flag": "🇹🇿",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "دار السلام (Dar es Salaam)",
      "دودوما",
      "زنجبار"
    ]
  },
  {
    "nameAr": "أوغندا",
    "nameEn": "Uganda",
    "flag": "🇺🇬",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "كمبالا (Kampala)",
      "عنتيبي",
      "جينجا"
    ]
  },
  {
    "nameAr": "رواندا",
    "nameEn": "Rwanda",
    "flag": "🇷🇼",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "كيغالي (Kigali)",
      "غيسيني",
      "بوتاري"
    ]
  },
  {
    "nameAr": "أنغولا",
    "nameEn": "Angola",
    "flag": "🇦🇴",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "لواندا",
      "هوامبو",
      "لوبانغو"
    ]
  },
  {
    "nameAr": "جمهورية الكونغو الديمقراطية",
    "nameEn": "DR Congo",
    "flag": "🇨🇩",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "كينشاسا",
      "لوبومباشي",
      "غوما"
    ]
  },
  {
    "nameAr": "جمهورية الكونغو",
    "nameEn": "Congo",
    "flag": "🇨🇬",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "برازافيل",
      "بوانت نوار"
    ]
  },
  {
    "nameAr": "زامبيا",
    "nameEn": "Zambia",
    "flag": "🇿🇲",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "لوساكا",
      "كيتوي",
      "ندولا"
    ]
  },
  {
    "nameAr": "زيمبابوي",
    "nameEn": "Zimbabwe",
    "flag": "🇿🇼",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "هراري",
      "بولاوايو"
    ]
  },
  {
    "nameAr": "ناميبيا",
    "nameEn": "Namibia",
    "flag": "🇳🇦",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "ويندهوك",
      "والفيس باي"
    ]
  },
  {
    "nameAr": "بوتسوانا",
    "nameEn": "Botswana",
    "flag": "🇧🇼",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "غابورون",
      "فرانسيستاون"
    ]
  },
  {
    "nameAr": "موزمبيق",
    "nameEn": "Mozambique",
    "flag": "🇲🇿",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "مابوتو",
      "بيرا",
      "نامبولا"
    ]
  },
  {
    "nameAr": "مدغشقر",
    "nameEn": "Madagascar",
    "flag": "🇲🇬",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "أنتاناناريفو",
      "تواماسينا"
    ]
  },
  {
    "nameAr": "مالي",
    "nameEn": "Mali",
    "flag": "🇲🇱",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "باماكو",
      "سيكاسو",
      "موبتي"
    ]
  },
  {
    "nameAr": "النيجر",
    "nameEn": "Niger",
    "flag": "🇳🇪",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "نيامي",
      "مارادي",
      "زيندر"
    ]
  },
  {
    "nameAr": "تشاد",
    "nameEn": "Chad",
    "flag": "🇹🇩",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "انجمينا",
      "موندو"
    ]
  },
  {
    "nameAr": "بوركينا فاسو",
    "nameEn": "Burkina Faso",
    "flag": "🇧🇫",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "واغادوغو",
      "بوبوديولاسو"
    ]
  },
  {
    "nameAr": "غينيا",
    "nameEn": "Guinea",
    "flag": "🇬🇳",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "كوناكري",
      "كانكان"
    ]
  },
  {
    "nameAr": "سيراليون",
    "nameEn": "Sierra Leone",
    "flag": "🇸🇱",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "فريتاون",
      "بو"
    ]
  },
  {
    "nameAr": "ليبيريا",
    "nameEn": "Liberia",
    "flag": "🇱🇷",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "مونروفيا",
      "غبارنغا"
    ]
  },
  {
    "nameAr": "الغابون",
    "nameEn": "Gabon",
    "flag": "🇬🇦",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "ليبرفيل",
      "بورت جنتيل"
    ]
  },
  {
    "nameAr": "إريتريا",
    "nameEn": "Eritrea",
    "flag": "🇪🇷",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "أسمرة",
      "عصب",
      "مصوع"
    ]
  },
  {
    "nameAr": "غامبيا",
    "nameEn": "Gambia",
    "flag": "🇬🇲",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "بانجول",
      "سيريكوندا"
    ]
  },
  {
    "nameAr": "بينين",
    "nameEn": "Benin",
    "flag": "🇧🇯",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "بورتو نوفو",
      "كوتونو"
    ]
  },
  {
    "nameAr": "توغو",
    "nameEn": "Togo",
    "flag": "🇹🇬",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "لومي",
      "سوكودي"
    ]
  },
  {
    "nameAr": "موريشيوس",
    "nameEn": "Mauritius",
    "flag": "🇲🇺",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "بورت لويس",
      "فاكواس"
    ]
  },
  {
    "nameAr": "سيشل",
    "nameEn": "Seychelles",
    "flag": "🇸🇨",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "فيكتوريا"
    ]
  },
  {
    "nameAr": "الرأس الأخضر",
    "nameEn": "Cape Verde",
    "flag": "🇨🇻",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "برايا",
      "منديلو"
    ]
  },
  {
    "nameAr": "غينيا الاستوائية",
    "nameEn": "Equatorial Guinea",
    "flag": "🇬🇶",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "مالابو",
      "باتا"
    ]
  },
  {
    "nameAr": "جنوب السودان",
    "nameEn": "South Sudan",
    "flag": "🇸🇸",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "جوبا",
      "واو",
      "ملكال"
    ]
  },
  {
    "nameAr": "بوروندي",
    "nameEn": "Burundi",
    "flag": "🇧🇮",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "غيتيغا",
      "بوجومبورا"
    ]
  },
  {
    "nameAr": "ملاوي",
    "nameEn": "Malawi",
    "flag": "🇲🇼",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "ليلونغوي",
      "بلانتيري"
    ]
  },
  {
    "nameAr": "ليسوتو",
    "nameEn": "Lesotho",
    "flag": "🇱🇸",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "ماسيرو"
    ]
  },
  {
    "nameAr": "إي سواتيني (سوازيلاند)",
    "nameEn": "Eswatini",
    "flag": "🇸🇿",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "مبابان",
      "مانزيني"
    ]
  },
  {
    "nameAr": "جمهورية إفريقيا الوسطى",
    "nameEn": "Central African Republic",
    "flag": "🇨🇫",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "بانغي"
    ]
  },
  {
    "nameAr": "ساو تومي وبرينسيب",
    "nameEn": "Sao Tome and Principe",
    "flag": "🇸🇹",
    "continentAr": "إفريقيا",
    "continentEn": "Africa",
    "cities": [
      "ساو تومي"
    ]
  },
  {
    "nameAr": "أستراليا (Australia)",
    "nameEn": "Australia",
    "flag": "🇦🇺",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "pinned": true,
    "cities": [
      "سيدني (Sydney)",
      "ملبورن (Melbourne)",
      "بريزبان (Brisbane)",
      "بيرث (Perth)",
      "أديلايد (Adelaide)",
      "كانبرا (Canberra)",
      "جولد كوست"
    ]
  },
  {
    "nameAr": "نيوزيلندا",
    "nameEn": "New Zealand",
    "flag": "🇳🇿",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "أوكلاند (Auckland)",
      "ويلينغتون (Wellington)",
      "كرايستشرش",
      "هاميلتون"
    ]
  },
  {
    "nameAr": "فيجي",
    "nameEn": "Fiji",
    "flag": "🇫🇯",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "سوفا",
      "نادي"
    ]
  },
  {
    "nameAr": "بابوا غينيا الجديدة",
    "nameEn": "Papua New Guinea",
    "flag": "🇵🇬",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "بورت مورسبي"
    ]
  },
  {
    "nameAr": "ساموا",
    "nameEn": "Samoa",
    "flag": "🇼🇸",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "أبيا"
    ]
  },
  {
    "nameAr": "فانواتو",
    "nameEn": "Vanuatu",
    "flag": "🇻🇺",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "بورت فيلا"
    ]
  },
  {
    "nameAr": "جزر سليمان",
    "nameEn": "Solomon Islands",
    "flag": "🇸🇧",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "هونيارا"
    ]
  },
  {
    "nameAr": "تونغا",
    "nameEn": "Tonga",
    "flag": "🇹🇴",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "نوكو ألوفا"
    ]
  },
  {
    "nameAr": "ميكرونيزيا",
    "nameEn": "Micronesia",
    "flag": "🇫🇲",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "باليكير"
    ]
  },
  {
    "nameAr": "بالاو",
    "nameEn": "Palau",
    "flag": "🇵🇼",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "نغيرولمود"
    ]
  },
  {
    "nameAr": "جزر مارشال",
    "nameEn": "Marshall Islands",
    "flag": "🇲🇭",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "ماجورو"
    ]
  },
  {
    "nameAr": "كيريباتي",
    "nameEn": "Kiribati",
    "flag": "🇰🇮",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "تاراوا"
    ]
  },
  {
    "nameAr": "ناورو",
    "nameEn": "Nauru",
    "flag": "🇳🇷",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "يارين"
    ]
  },
  {
    "nameAr": "توفالو",
    "nameEn": "Tuvalu",
    "flag": "🇹🇻",
    "continentAr": "أستراليا وأوقيانوسيا",
    "continentEn": "Oceania",
    "cities": [
      "فونافوتي"
    ]
  },
  {
    "nameAr": "دولة أخرى / غير مدرجة",
    "nameEn": "Other Country / Unlisted",
    "flag": "🌐",
    "continentAr": "أخرى",
    "continentEn": "Other",
    "cities": [
      "العاصمة / مدينة رئيسية",
      "مدينة / منطقة أخرى"
    ]
  }
];

    const worldCountriesAndCities = {};
    const worldCountriesEn = {};
    worldCountriesData.forEach(c => {
      worldCountriesAndCities[c.nameAr] = c.cities || [];
      worldCountriesEn[c.nameAr] = c.nameEn;
    });

    // ── Client Card Repeater State & Logic ──
    let clientCardSequence = 0;
    const activeClientIds = [];
    const clientFilesState = {};
    const clientResidenceMap = {};

    function addNewClientCard(isPrimary = false) {
      clientCardSequence++;
      const clientId = clientCardSequence;
      activeClientIds.push(clientId);

      const container = document.getElementById('clientsContainer');
      if (!container) return;

      const index = activeClientIds.indexOf(clientId);
      const cardEl = createClientCardElement(clientId, isPrimary, index);
      container.appendChild(cardEl);

      // Default state
      clientResidenceMap[clientId] = 'داخل جمهورية مصر العربية';
      clientFilesState[clientId] = [];

      // Add default initial phone row and email row
      addPhoneRow(clientId, '+20', '');
      addEmailRow(clientId, '');

      // Setup dropzone events for this client card
      setupClientDropzone(clientId);

      // Initialize address dropdowns (Governorate & City)
      initClientAddressFields(clientId);

      updateClientsCountBadge();

      if (!isPrimary) {
        cardEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const nameInput = cardEl.querySelector('.client-name-input');
        if (nameInput) setTimeout(() => nameInput.focus(), 250);
      }
    }

    function removeClientCard(clientId) {
      const index = activeClientIds.indexOf(clientId);
      if (index === -1) return;
      if (index === 0) {
        alert(currentLang === 'en' ? 'Primary client cannot be deleted.' : 'لا يمكن حذف العميل الأساسي (الطرف الأول).');
        return;
      }

      const card = document.getElementById(`clientCard_${clientId}`);
      if (card) {
        card.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
          card.remove();
          activeClientIds.splice(index, 1);
          delete clientResidenceMap[clientId];
          delete clientFilesState[clientId];
          reNumberClientCards();
          updateClientsCountBadge();
          updateContractLocationVisibility();
        }, 250);
      }
    }

    function reNumberClientCards() {
      activeClientIds.forEach((cId, idx) => {
        const card = document.getElementById(`clientCard_${cId}`);
        if (!card) return;
        const titleEl = card.querySelector('.card-title');
        if (titleEl) {
          if (idx === 0) {
            titleEl.innerText = (currentLang === 'en') ? 'Client / First Party (Main)' : 'بيانات العميل / الطرف الأول (الأساسي)';
          } else {
            titleEl.innerText = (currentLang === 'en') ? `Partner / Contract Party (${idx + 1})` : `بيانات الشريك / الطرف (${idx + 1})`;
          }
        }
      });
    }

    function updateClientsCountBadge() {
      const badge = document.getElementById('clientsCountBadge');
      if (!badge) return;
      const count = activeClientIds.length;
      if (count <= 1) {
        badge.innerText = (currentLang === 'en') ? 'Primary Client' : 'الطرف الأول (الأساسي)';
      } else {
        badge.innerText = (currentLang === 'en') ? `${count} Contract Parties` : `${count} أطراف بالعقد`;
      }
    }

    function createClientCardElement(clientId, isPrimary = false, index = 0) {
      const card = document.createElement('div');
      card.className = "client-card p-4 sm:p-6 rounded-2xl sm:rounded-3xl bg-white border-2 border-sky-200 space-y-5 relative shadow-md transition-all duration-300";
      card.id = `clientCard_${clientId}`;
      card.dataset.clientId = clientId;

      const cardTitle = isPrimary 
        ? (currentLang === 'en' ? 'Client / First Party (Main)' : 'بيانات العميل / الطرف الأول (الأساسي)')
        : (currentLang === 'en' ? `Partner / Contract Party (${index + 1})` : `بيانات الشريك / الطرف (${index + 1})`);

      const cardBadge = isPrimary
        ? `<span class="card-badge px-2.5 py-0.5 rounded-lg bg-sky-100 text-sky-950 border border-sky-300 text-[11px] font-black" data-i18n="badgePrimaryParty">${currentLang === 'en' ? 'Primary Party' : 'الطرف الأساسي'}</span>`
        : `<span class="card-badge px-2.5 py-0.5 rounded-lg bg-sky-200 text-slate-950 border border-sky-400 text-[11px] font-black" data-i18n="badgePartnerParty">${currentLang === 'en' ? 'Contract Partner' : 'شريك بالعقد'}</span>`;

      const deleteBtn = isPrimary ? '' : `
        <button 
          type="button" 
          onclick="removeClientCard(${clientId})" 
          class="card-delete-btn text-xs font-black text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border-2 border-red-300 px-3 py-1.5 rounded-xl flex items-center gap-1.5 transition-all cursor-pointer shadow-sm"
          title="${currentLang === 'en' ? 'Remove Partner' : 'حذف هذا الشريك'}"
        >
          <span>🗑️</span>
          <span data-i18n="btnRemovePartner">${currentLang === 'en' ? 'Remove Partner' : 'حذف هذا الشريك'}</span>
        </button>
      `;

      card.innerHTML = `
        <!-- رأس بطاقة العميل / الشريك -->
        <div class="flex items-center justify-between pb-3 border-b border-sky-100">
          <div class="flex items-center gap-2.5">
            <span class="text-lg">👤</span>
            <h4 class="card-title text-slate-950 font-black text-sm sm:text-base">${cardTitle}</h4>
            ${cardBadge}
          </div>
          ${deleteBtn}
        </div>

        <!-- 1. اسم العميل كما هو في البطاقة الشخصية (باللغة العربية فقط) -->
        <div class="form-group w-full">
          <label class="block text-xs font-black text-slate-950 mb-1.5">
            <span data-i18n="labelPrimaryName">${currentLang === 'en' ? 'Client Name as in ID (Arabic Only)' : 'اسم العميل كما هو في البطاقة الشخصية (باللغة العربية فقط)'}</span>
            <span class="text-red-500 font-bold">*</span>
          </label>
          <input
            type="text"
            placeholder="${currentLang === 'en' ? '(Full name in Arabic matching ID)' : '(الاسم كاملاً باللغة العربية مطابقاً للهوية)'}"
            data-i18n-placeholder="phPrimaryName"
            class="client-name-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-bold border-2 border-sky-300 focus:border-sky-500 bg-white"
            oninput="handleClientNameInput(this)"
          />
          <p class="error-msg text-red-600 font-black text-xs mt-1 hidden" data-i18n="errPrimaryName">${currentLang === 'en' ? 'Client name is required (Arabic letters only)' : 'اسم العميل مطلوب ويجب أن يكون باللغة العربية فقط'}</p>
        </div>

        <!-- 2. أرقام الهواتف والتواصل (Dynamic Array / List) -->
        <div class="space-y-2.5 pt-2 border-t border-sky-100">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-black text-slate-950">
              <span data-i18n="labelPrimaryPhone">${currentLang === 'en' ? 'Phone & Contact Numbers (Supports Local & International)' : 'أرقام الهواتف والتواصل (يدعم الأرقام المحلية والدولية)'}</span>
              <span class="text-red-500 font-bold">*</span>
            </label>
          </div>
          <div id="phonesList_${clientId}" class="space-y-2.5"></div>
          <button 
            type="button" 
            onclick="addPhoneRow(${clientId})" 
            class="text-xs font-black text-slate-950 hover:bg-sky-100 bg-white border-2 border-sky-300 px-3.5 py-2 rounded-xl flex items-center gap-1.5 transition-all cursor-pointer shadow-sm w-full sm:w-auto justify-center"
          >
            <span>📞</span>
            <span data-i18n="btnAddPhone">${currentLang === 'en' ? '+ Add Additional Phone Number' : '+ إضافة رقم هاتف إضافي'}</span>
          </button>
        </div>

        <!-- 3. البريد الإلكتروني للمراسلات الرسمية (Dynamic Array / List) -->
        <div class="space-y-2.5 pt-2 border-t border-sky-100">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-black text-slate-950">
              <span data-i18n="labelEmail">${currentLang === 'en' ? 'Email (Official Correspondence & Notices)' : 'البريد الإلكتروني (متاح للتواصل والمراسلات الرسمية)'}</span>
              <span class="text-red-500 font-bold">*</span>
            </label>
          </div>
          <div id="emailsList_${clientId}" class="space-y-2.5"></div>
          <button 
            type="button" 
            onclick="addEmailRow(${clientId})" 
            class="text-xs font-black text-slate-950 hover:bg-sky-100 bg-white border-2 border-sky-300 px-3.5 py-2 rounded-xl flex items-center gap-1.5 transition-all cursor-pointer shadow-sm w-full sm:w-auto justify-center"
          >
            <span>✉️</span>
            <span data-i18n="btnAddEmail">${currentLang === 'en' ? '+ Add Additional Email' : '+ إضافة بريد إلكتروني إضافي'}</span>
          </button>
        </div>

        <!-- 4. إثبات الهوية والرقم القومي / جواز السفر -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 pt-2 border-t border-sky-100">
          <div class="sm:col-span-5">
            <label class="block text-xs font-black text-slate-950 mb-1.5" data-i18n="labelIdType">${currentLang === 'en' ? 'ID Document Type' : 'نوع الوثيقة'}</label>
            <select onchange="handleClientIdTypeChange(${clientId}, this.value)" class="client-id-type glass-input w-full px-3 py-2.5 sm:py-3 rounded-xl text-slate-950 text-xs font-black border-2 border-sky-300 focus:border-sky-500 bg-white">
              <option value="national_id" data-i18n="optNatId">${currentLang === 'en' ? 'National ID (14 digits)' : 'بطاقة الرقم القومي (14 رقم)'}</option>
              <option value="passport" data-i18n="optPassport">${currentLang === 'en' ? 'Passport' : 'جواز السفر (Passport)'}</option>
            </select>
          </div>
          <div class="sm:col-span-7 form-group">
            <label class="block text-xs font-black text-slate-950 mb-1.5">
              <span data-i18n="labelIdNumber">${currentLang === 'en' ? 'National ID or Passport Number' : 'رقم بطاقة الرقم القومي أو جواز السفر'}</span>
              <span class="text-red-500 font-bold">*</span>
            </label>
            <input
              type="text"
              ${isPrimary ? 'id="national_id"' : ''}
              maxlength="14"
              placeholder="${currentLang === 'en' ? 'Enter exactly 14 digits for National ID' : 'أدخل 14 رقماً بالضبط للبطاقة الشخصية'}"
              class="client-id-number glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-mono font-black tracking-wider border-2 border-sky-300 focus:border-sky-500 bg-white"
              oninput="validateClientIdNumber(this, ${clientId})"
            />
            <p class="error-msg text-red-600 font-black text-xs mt-1 hidden client-id-error"></p>
          </div>
        </div>

        <!-- 5. مكان الإقامة الحالي (داخل مصر / خارج مصر) -->
        <div class="form-group pt-2 border-t border-sky-100">
          <label class="block text-xs font-black text-slate-950 mb-2">
            <span data-i18n="labelResidenceCountry">${currentLang === 'en' ? 'Current Residence Place:' : 'مكان الإقامة الحالي:'}</span>
            <span class="text-red-500 font-bold">*</span>
          </label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
            <label class="residence-box-egypt custom-check-box active rounded-2xl py-3 px-3 sm:px-4 flex items-center justify-center gap-2 cursor-pointer text-xs sm:text-sm font-black text-slate-950 shadow-sm" onclick="setClientResidence(${clientId}, 'داخل جمهورية مصر العربية')">
              <input type="radio" name="client_residence_${clientId}" value="داخل جمهورية مصر العربية" checked class="hidden">
              <span class="inline-flex items-center justify-center w-5 h-3.5 rounded overflow-hidden shadow-xs border border-slate-300 flex-shrink-0">
                <svg viewBox="0 0 3 2" class="w-full h-full">
                  <rect width="3" height="0.67" y="0" fill="#ce1126"/>
                  <rect width="3" height="0.67" y="0.67" fill="#ffffff"/>
                  <rect width="3" height="0.67" y="1.34" fill="#000000"/>
                  <circle cx="1.5" cy="1" r="0.22" fill="#c6a664"/>
                </svg>
              </span>
              <span data-i18n="optInsideEgypt">${currentLang === 'en' ? 'Inside Arab Republic of Egypt' : 'داخل جمهورية مصر العربية'}</span>
            </label>
            <label class="residence-box-abroad custom-check-box rounded-2xl py-3 px-3 sm:px-4 flex items-center justify-center gap-2 cursor-pointer text-xs sm:text-sm font-black text-slate-950 shadow-sm" onclick="setClientResidence(${clientId}, 'خارج جمهورية مصر العربية')">
              <input type="radio" name="client_residence_${clientId}" value="خارج جمهورية مصر العربية" class="hidden">
              <span class="text-base flex-shrink-0">🌍</span>
              <span data-i18n="optOutsideEgypt">${currentLang === 'en' ? 'Outside Arab Republic of Egypt' : 'خارج جمهورية مصر العربية'}</span>
            </label>
          </div>
        </div>

        <!-- 6. العناوين (المحافظة والمدينة وباقي تفاصيل العنوان) -->
        <div class="space-y-4 pt-3 border-t border-sky-100">
          
          <!-- العنوان كما هو في إثبات الهوية الشخصية -->
          <div class="client-id-address-group space-y-2.5">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-black text-slate-950">
                <span>📍 <span data-i18n="labelIdAddress">${currentLang === 'en' ? 'Address as registered in Personal ID' : 'العنوان كما هو مسجل في إثبات الهوية الشخصية'}</span></span>
                <span class="text-red-500 font-bold">*</span>
              </label>
              <span class="client-sub-id-addr text-[10px] text-slate-700 font-bold font-mono" data-i18n="subIdAddress">${currentLang === 'en' ? 'Select Gov & City + Street details' : 'اختر المحافظة والمدينة واكتب باقي التفاصيل'}</span>
            </div>

            <!-- صف الاختيار: المحافظة/الدولة والمدينة -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
              <div>
                <label class="block text-[11px] font-black text-slate-700 mb-1">
                  <span class="client-lbl-gov-text" data-i18n="lblGovernorate">${currentLang === 'en' ? 'Governorate' : 'المحافظة'}</span> <span class="text-red-500 font-bold">*</span>
                </label>
                <!-- Searchable Region/Country Picker with Search Bar -->
                <div class="searchable-region-picker relative w-full" id="regionPicker_${clientId}">
                  <button
                    type="button"
                    onclick="toggleRegionPickerDropdown(${clientId}, this)"
                    class="region-picker-btn glass-input w-full px-3 py-2.5 sm:py-3 rounded-xl text-slate-950 bg-white border-2 border-sky-300 hover:border-sky-500 focus:border-sky-500 flex items-center justify-between cursor-pointer text-xs sm:text-sm font-bold shadow-xs transition-all"
                  >
                    <span class="region-picker-display flex items-center gap-2 truncate">
                      <span class="region-flag text-base flex-shrink-0">🇪🇬</span>
                      <span class="region-name font-black truncate text-slate-950">${currentLang === 'en' ? 'Select Governorate...' : 'اختر المحافظة...'}</span>
                    </span>
                    <span class="text-[9px] text-slate-600 font-bold flex-shrink-0">▼</span>
                  </button>

                  <input type="hidden" class="client-gov-select" value="" />

                  <div class="region-picker-dropdown hidden absolute top-full mt-1.5 start-0 z-50 w-full min-w-[280px] sm:min-w-[320px] bg-white border-2 border-sky-400 rounded-2xl shadow-2xl p-2.5 backdrop-blur-2xl">
                    <div class="relative mb-2">
                      <input 
                        type="text" 
                        placeholder="${currentLang === 'en' ? 'Search country or continent...' : 'ابحث عن الدولة أو القارة (مثال: مصر، السعودية، فرنسا، كندا)...'}" 
                        oninput="filterRegionPicker(${clientId}, this)" 
                        class="region-picker-search w-full px-3 py-2 text-xs bg-sky-50 border-2 border-sky-300 rounded-xl text-slate-950 placeholder-slate-500 focus:outline-none focus:border-sky-500 font-bold"
                      />
                      <span class="absolute end-2.5 top-2.5 text-xs text-slate-500">🔍</span>
                    </div>
                    <div class="region-picker-scroll max-h-56 overflow-y-auto space-y-1 text-xs pr-1"></div>
                  </div>
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-black text-slate-700 mb-1">
                  <span class="client-lbl-city-text" data-i18n="lblCity">${currentLang === 'en' ? 'City / District / Area' : 'المدينة / الحي / المركز'}</span> <span class="text-red-500 font-bold">*</span>
                </label>
                <select
                  class="client-city-select glass-input w-full px-3 py-2.5 sm:py-3 rounded-xl text-slate-950 bg-white border-2 border-sky-300 focus:border-sky-500 focus:outline-none text-xs sm:text-sm font-bold cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                  disabled
                  onchange="handleClientCityChange(${clientId}, this.value, 'id')"
                >
                  <option value="" data-i18n="optSelectGovFirst">${currentLang === 'en' ? 'Select Governorate first...' : 'اختر المحافظة أولاً...'}</option>
                </select>
              </div>
            </div>

            <!-- حقل كتابة باقي تفاصيل العنوان (الشارع، رقم العقار، الشقة) -->
            <div>
              <label class="block text-[11px] font-black text-slate-700 mb-1">
                <span data-i18n="lblStreetDetails">${currentLang === 'en' ? 'Street, Building & Apt No. Details' : 'باقي تفاصيل العنوان (اسم الشارع، رقم العقار، رقم الشقة)'}</span> <span class="text-red-500 font-bold">*</span>
              </label>
              <input
                type="text"
                placeholder="${currentLang === 'en' ? 'Street name, building number, apartment number, landmark...' : 'اسم الشارع، رقم العمارة، رقم الشقة، علامة مميزة...'}"
                data-i18n-placeholder="phStreetDetails"
                class="client-street-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-bold border-2 border-sky-300 focus:border-sky-500 bg-white"
                oninput="handleClientStreetInput(${clientId}, 'id')"
              />
            </div>

            <!-- الحقل المدمج النهائي للعنوان بالهوية (hidden) -->
            <input type="hidden" class="client-id-address" value="" />
            <p class="error-msg client-id-address-error text-red-600 font-black text-xs mt-1 hidden" data-i18n="errIdAddressSelect">${currentLang === 'en' ? 'Please select governorate, city and enter street address details' : 'يرجى اختيار المحافظة والمدينة وكتابة باقي تفاصيل العنوان'}</p>
          </div>

          <!-- عنوان المراسلات الرسمي -->
          <div class="client-corr-address-group space-y-2.5 pt-3 border-t border-sky-100">
            <div class="flex items-center justify-between flex-wrap gap-2">
              <label class="block text-xs font-black text-slate-950">
                <span>📬 <span data-i18n="labelCorrespondenceAddress">${currentLang === 'en' ? 'Approved Correspondence Address in Detail' : 'العنوان المعتمد بالتفصيل للمراسلات'}</span></span>
                <span class="text-red-500 font-bold">*</span>
              </label>
            </div>

            <!-- حاوية حقول المراسلات المخصصة (ظاهرة أوتوماتيكياً) -->
            <div class="client-custom-corr-container space-y-2.5 p-3 rounded-2xl bg-sky-50/60 border-2 border-sky-200">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                <div>
                  <label class="block text-[11px] font-black text-slate-700 mb-1">
                    <span class="client-corr-lbl-gov-text" data-i18n="lblGovernorate">${currentLang === 'en' ? 'Governorate' : 'المحافظة'}</span> <span class="text-red-500 font-bold">*</span>
                  </label>
                  <select
                    class="client-corr-gov-select glass-input w-full px-3 py-2.5 sm:py-3 rounded-xl text-slate-950 bg-white border-2 border-sky-300 focus:border-sky-500 focus:outline-none text-xs sm:text-sm font-bold cursor-pointer"
                    onchange="handleClientCorrGovChange(${clientId}, this.value)"
                  >
                    <option value="" data-i18n="optSelectGov">${currentLang === 'en' ? 'Select Governorate...' : 'اختر المحافظة...'}</option>
                  </select>
                </div>

                <div>
                  <label class="block text-[11px] font-black text-slate-700 mb-1">
                    <span class="client-corr-lbl-city-text" data-i18n="lblCity">${currentLang === 'en' ? 'City / District / Area' : 'المدينة / الحي / المركز'}</span> <span class="text-red-500 font-bold">*</span>
                  </label>
                  <select
                    class="client-corr-city-select glass-input w-full px-3 py-2.5 sm:py-3 rounded-xl text-slate-950 bg-white border-2 border-sky-300 focus:border-sky-500 focus:outline-none text-xs sm:text-sm font-bold cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled
                    onchange="handleClientCityChange(${clientId}, this.value, 'corr')"
                  >
                    <option value="" data-i18n="optSelectGovFirst">${currentLang === 'en' ? 'Select Governorate first...' : 'اختر المحافظة أولاً...'}</option>
                  </select>
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-black text-slate-700 mb-1">
                  <span data-i18n="lblCorrStreetDetails">${currentLang === 'en' ? 'Street, Building & Apt No. Details' : 'باقي تفاصيل العنوان للمراسلات'}</span> <span class="text-red-500 font-bold">*</span>
                </label>
                <input
                  type="text"
                  placeholder="${currentLang === 'en' ? 'Street name, building number, apartment number for official mail' : 'اسم الشارع، رقم المبنى والشقة للمراسلات الرسمية'}"
                  data-i18n-placeholder="phCorrStreetDetails"
                  class="client-corr-street-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-bold border-2 border-sky-300 focus:border-sky-500 bg-white"
                  oninput="handleClientStreetInput(${clientId}, 'corr')"
                />
              </div>
            </div>

            <!-- الحقل المدمج النهائي لعنوان المراسلات (hidden) -->
            <input type="hidden" class="client-corr-address" value="" />
            <p class="error-msg client-corr-address-error text-red-600 font-black text-xs mt-1 hidden" data-i18n="errCorrespondenceAddress">${currentLang === 'en' ? 'Approved correspondence address is required' : 'العنوان المعتمد للمراسلات مطلوب'}</p>
          </div>
        </div>

        <!-- 7. المستندات والمرفقات المطلوبة لهذا الطرف -->
        <div class="pt-3 border-t border-sky-100 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-slate-950 font-black text-xs sm:text-sm">
              <span>📎</span>
              <span data-i18n="secAttachmentsTitle">${currentLang === 'en' ? 'Required Documents & Attachments' : 'المستندات والمرفقات المطلوبة'}</span>
              <span class="text-red-500 font-bold">*</span>
            </div>
            <span class="text-[10px] text-slate-600 font-mono font-bold">PDF / JPG / PNG</span>
          </div>

          <div class="client-doc-notice p-3 rounded-2xl bg-sky-50 border-2 border-sky-300 text-slate-950 text-xs font-bold flex items-start gap-2">
            <span class="text-base flex-shrink-0">⚠️</span>
            <div class="client-doc-notice-text">
              ${currentLang === 'en' ? 'For clients residing inside Egypt: Uploading both sides of the National ID is mandatory.' : 'للعملاء المقيمين داخل مصر: يُشترط رفع صورة واضحة لبطاقة الرقم القومي (الوجهين: الأمامي والخلفي).'}
            </div>
          </div>

          <div 
            class="client-drop-zone border-2 border-dashed border-sky-300 hover:border-sky-500 rounded-2xl p-4 sm:p-5 text-center transition-all bg-sky-50/50 hover:bg-sky-100/50 cursor-pointer shadow-sm"
          >
            <input 
              type="file" 
              multiple 
              accept="image/*,.pdf" 
              class="hidden client-file-input" 
              onchange="handleClientFilesSelected(${clientId}, this.files)"
            >
            <div class="text-2xl mb-1 pointer-events-none">📁</div>
            <div class="font-black text-xs sm:text-sm text-slate-950 pointer-events-none" data-i18n="dropTitle">
              ${currentLang === 'en' ? 'Click to select documents or drag & drop files here' : 'اضغط لاختيار المستندات أو اسحب الملفات وأفلتها هنا'}
            </div>
            <div class="text-[11px] text-slate-700 mt-1 client-drop-subtitle pointer-events-none font-bold">
              ${currentLang === 'en' ? '(Mandatory: National ID card both sides)' : '(مطلوب إجباري: صورة بطاقة الرقم القومي وجهين)'}
            </div>
          </div>

          <!-- قائمة الملفات المرفقة لهذا العميل -->
          <div class="client-files-container space-y-2 hidden">
            <div class="text-xs font-black text-slate-950" data-i18n="selectedFilesHeading">${currentLang === 'en' ? 'Attached Files Preview:' : 'المعاينات المرفقة لهذا الطرف:'}</div>
            <div class="client-files-list grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
          </div>
          <p class="error-msg text-red-600 font-black text-xs mt-1 hidden client-files-error" data-i18n="errAttachments">${currentLang === 'en' ? 'Required documents are mandatory. Please attach the required files.' : 'المستندات المطلوبة إلزامية، يرجى رفع الملفات المحددة أعلاه'}</p>
        </div>
      `;

      return card;
    }

    // ── Phone Rows Repeater per Client ──
    let phoneRowCounter = 0;
    function addPhoneRow(clientId, initialCode = '+20', initialNum = '') {
      phoneRowCounter++;
      const pId = phoneRowCounter;
      const listContainer = document.getElementById(`phonesList_${clientId}`);
      if (!listContainer) return;

      const isFirstRow = listContainer.children.length === 0;

      const row = document.createElement('div');
      row.className = "phone-row flex gap-2 dir-ltr w-full relative items-start";
      row.id = `phoneRow_${pId}`;

      const deleteBtnHtml = isFirstRow ? '' : `
        <button 
          type="button" 
          onclick="removePhoneRow(${pId})" 
          class="p-2.5 sm:p-3 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 border-2 border-red-300 font-black transition-colors cursor-pointer flex-shrink-0"
          title="${currentLang === 'en' ? 'Remove phone' : 'حذف هذا الرقم'}"
        >
          ✕
        </button>
      `;

      row.innerHTML = `
        <!-- Searchable Country Dropdown Trigger -->
        <div class="country-picker relative w-28 sm:w-36 flex-shrink-0">
          <button 
            type="button" 
            onclick="toggleCountryDropdown(this)" 
            class="country-btn glass-input w-full px-2 sm:px-2.5 py-2.5 sm:py-3 rounded-xl text-slate-950 text-xs font-black flex items-center justify-between cursor-pointer border-2 border-sky-300 hover:border-sky-500 transition-all bg-white"
          >
            <span class="country-display flex items-center gap-1 sm:gap-1.5 truncate">
              <span class="text-sm sm:text-base">${initialCode === '+20' ? '🇪🇬' : '🌐'}</span>
              <span class="font-mono text-xs font-black">${initialCode}</span>
            </span>
            <span class="text-[9px] text-slate-600 font-bold">▼</span>
          </button>
          
          <input type="hidden" name="phone_codes[]" value="${initialCode}" class="country-code-val">

          <!-- Dropdown Menu -->
          <div class="country-dropdown hidden absolute top-full mt-1.5 start-0 z-50 w-72 max-w-[85vw] sm:w-80 bg-white border-2 border-sky-300 rounded-2xl shadow-2xl p-2.5 backdrop-blur-2xl">
            <div class="relative mb-2">
              <input 
                type="text" 
                data-i18n-placeholder="phSearchCountry"
                placeholder="${currentLang === 'en' ? 'Search country or dialing code...' : 'بحث عن الدولة أو الكود...'}" 
                oninput="filterCountryList(this)" 
                class="country-search-input w-full px-3 py-2 text-xs bg-sky-50 border border-sky-200 rounded-xl text-slate-950 placeholder-slate-500 focus:outline-none focus:border-sky-500 font-bold"
              >
              <span class="absolute end-2.5 top-2.5 text-xs text-slate-500">🔍</span>
            </div>
            <div class="country-scroll max-h-56 overflow-y-auto space-y-1 text-xs pr-1"></div>
          </div>
        </div>

        <!-- Phone Number Input & Error Container -->
        <div class="flex-1 min-w-0">
          <input
            type="tel"
            name="phone_numbers[]"
            placeholder="${currentLang === 'en' ? '10xxxxxxxx (10 digits without leading 0)' : '10xxxxxxxx (10 أرقام مباشرة بدون صفر)'}"
            maxlength="10"
            value="${initialNum}"
            class="phone-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-mono font-black tracking-wider text-left border-2 border-sky-300 focus:border-sky-500 bg-white"
            oninput="handlePhoneInput(this)"
          />
          <p class="error-msg text-red-600 font-black text-xs mt-1 hidden"></p>
        </div>

        ${deleteBtnHtml}
      `;

      listContainer.appendChild(row);

      // Initialize country picker
      const pickerEl = row.querySelector('.country-picker');
      if (pickerEl) buildCountryList(pickerEl);

      const phoneInput = row.querySelector('input[type="tel"]');
      if (initialCode !== '+20') {
        const cItem = countryData.find(c => c.code === initialCode);
        if (cItem && pickerEl) {
          selectCountry(cItem, pickerEl);
        }
      } else {
        updatePhonePlaceholderAndConfig(phoneInput, '+20');
      }
    }

    function removePhoneRow(pId) {
      const row = document.getElementById(`phoneRow_${pId}`);
      if (row) {
        row.remove();
      }
    }

    // ── Email Rows Repeater per Client ──
    let emailRowCounter = 0;
    function addEmailRow(clientId, initialEmail = '') {
      emailRowCounter++;
      const eId = emailRowCounter;
      const listContainer = document.getElementById(`emailsList_${clientId}`);
      if (!listContainer) return;

      const isFirstRow = listContainer.children.length === 0;

      const row = document.createElement('div');
      row.className = "email-row flex gap-2 w-full relative items-start";
      row.id = `emailRow_${eId}`;

      const deleteBtnHtml = isFirstRow ? '' : `
        <button 
          type="button" 
          onclick="removeEmailRow(${eId})" 
          class="p-2.5 sm:p-3 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 border-2 border-red-300 font-black transition-colors cursor-pointer flex-shrink-0"
          title="${currentLang === 'en' ? 'Remove email' : 'حذف هذا البريد'}"
        >
          ✕
        </button>
      `;

      row.innerHTML = `
        <div class="flex-1 min-w-0">
          <input
            type="email"
            name="emails[]"
            placeholder="example@domain.com"
            value="${initialEmail}"
            class="email-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-950 placeholder-slate-500 text-xs sm:text-sm font-mono font-black dir-ltr text-right border-2 border-sky-300 focus:border-sky-500 bg-white"
            oninput="validateGeneralEmail(this)"
          />
          <p class="error-msg text-red-600 font-black text-xs mt-1 hidden" data-i18n="errEmail">${currentLang === 'en' ? 'Please provide a valid email (example@domain.com)' : 'يرجى إدخال بريد إلكتروني صحيح ومتاح للمراسلات الرسمية (example@domain.com)'}</p>
        </div>
        ${deleteBtnHtml}
      `;

      listContainer.appendChild(row);
    }

    function removeEmailRow(eId) {
      const row = document.getElementById(`emailRow_${eId}`);
      if (row) {
        row.remove();
      }
    }

    // ── Client ID and Residence Handlers ──
    function handleClientIdTypeChange(clientId, type) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;
      const input = card.querySelector('.client-id-number');
      const errEl = card.querySelector('.client-id-error');
      if (errEl) errEl.classList.add('hidden');

      if (type === 'national_id') {
        input.setAttribute('maxlength', '14');
        input.setAttribute('placeholder', (currentLang === 'en') ? 'Enter exactly 14 digits' : 'أدخل 14 رقماً بالضبط للبطاقة الشخصية');
      } else {
        input.setAttribute('maxlength', '9');
        input.setAttribute('placeholder', (currentLang === 'en') ? 'Enter 6 to 9 characters' : 'أدخل من 6 إلى 9 أحرف وأرقام للجواز');
      }
      validateClientIdNumber(input, clientId);
    }

    
    // ── Global Customer Units State & Multi-Card Unit Pills Management ──
    let currentClientUnits = [];
    window.currentClientUnits = currentClientUnits;
    let currentLookupNationalId = '';

    function getUnitCodeInput() {
      return document.getElementById('unit_code') || document.getElementById('unitCode');
    }

    function getAllUnitItems() {
      const items = [];
      const primaryInput = getUnitCodeInput();
      if (primaryInput) {
        items.push({
          input: primaryInput,
          isPrimary: true,
          container: document.getElementById('unit_multi_choices'),
          pillsEl: document.getElementById('unit_pills'),
          allSelectedEl: document.getElementById('unitAllSelectedPrimary'),
          warnEl: document.getElementById('duplicateUnitWarnPrimary')
        });
      }
      const extraBlocks = document.querySelectorAll('#unitsContainer .unit-block:not(:first-child)');
      extraBlocks.forEach(blk => {
        const inp = blk.querySelector('input[name="extra_unit_codes[]"]');
        if (inp) {
          items.push({
            input: inp,
            isPrimary: false,
            container: blk.querySelector('.extra-unit-pills-box'),
            pillsEl: blk.querySelector('.extra-unit-pills'),
            allSelectedEl: blk.querySelector('.extra-unit-all-selected'),
            warnEl: blk.querySelector('.duplicate-unit-warning')
          });
        }
      });
      return items;
    }

    function checkDuplicateUnits() {
      const items = getAllUnitItems();
      const counts = {};
      items.forEach(item => {
        const val = item.input.value.trim().toUpperCase();
        if (val) counts[val] = (counts[val] || 0) + 1;
      });

      let hasDuplicates = false;
      items.forEach(item => {
        const val = item.input.value.trim().toUpperCase();
        if (val && counts[val] > 1) {
          hasDuplicates = true;
          if (item.warnEl) {
            item.warnEl.innerHTML = (currentLang === 'en')
              ? `⚠️ Unit code (<bdi dir="ltr">${val}</bdi>) is already selected in another card`
              : `⚠️ كود الوحدة (<bdi dir="ltr">${val}</bdi>) تم اختياره بالفعل في بطاقة أخرى`;
            item.warnEl.classList.remove('hidden');
          }
          item.input.classList.add('invalid');
          item.input.classList.remove('valid');
        } else {
          if (item.warnEl) item.warnEl.classList.add('hidden');
        }
      });
      return hasDuplicates;
    }

    function refreshAllUnitsPills() {
      const items = getAllUnitItems();

      // 1. Check for duplicates across all cards
      checkDuplicateUnits();

      // 2. Render pills for each card based on currentClientUnits
      items.forEach(item => {
        if (!item.container || !item.pillsEl) return;

        // If no units loaded for current client, hide pills container
        if (!currentClientUnits || currentClientUnits.length === 0) {
          item.container.classList.add('hidden');
          item.pillsEl.innerHTML = '';
          if (item.allSelectedEl) item.allSelectedEl.classList.add('hidden');
          return;
        }

        // Units chosen in OTHER cards (excluding this card's own value)
        const otherChosen = items
          .filter(other => other !== item)
          .map(other => other.input.value.trim().toUpperCase())
          .filter(Boolean);

        // Units available for this card: client units not chosen in other cards
        const available = currentClientUnits.filter(u => !otherChosen.includes(u.toUpperCase()));

        // For Primary Card: only show pills box if client has multiple units
        if (item.isPrimary && currentClientUnits.length <= 1) {
          item.container.classList.add('hidden');
          return;
        }

        if (available.length === 0) {
          // All client units have been selected across cards
          item.container.classList.remove('hidden');
          item.pillsEl.innerHTML = '';
          if (item.allSelectedEl) {
            item.allSelectedEl.textContent = (currentLang === 'en')
              ? '✓ All client units have been selected'
              : '✓ تم اختيار كل وحدات العميل';
            item.allSelectedEl.classList.remove('hidden');
          }
        } else {
          if (item.allSelectedEl) item.allSelectedEl.classList.add('hidden');
          item.container.classList.remove('hidden');
          item.pillsEl.innerHTML = '';

          available.forEach(u => {
            const pill = document.createElement('button');
            pill.type = 'button';
            const isCur = (item.input.value.trim().toUpperCase() === u.toUpperCase());
            pill.className = `px-3 py-1.5 rounded-xl text-xs font-mono font-black border-2 transition-all cursor-pointer ${
              isCur 
                ? 'bg-sky-600 text-white border-sky-700 shadow-sm' 
                : 'bg-white text-slate-950 border-sky-300 hover:bg-sky-100 shadow-2xs'
            }`;
            pill.innerHTML = `<bdi dir="ltr">${u}</bdi>`;
            pill.onclick = () => {
              item.input.value = u;
              item.input.classList.add('valid');
              item.input.classList.remove('invalid');
              item.input.dispatchEvent(new Event('input', { bubbles: true }));
              item.input.dispatchEvent(new Event('change', { bubbles: true }));
              refreshAllUnitsPills();
            };
            item.pillsEl.appendChild(pill);
          });
        }
      });
    }

    function normalizeIdForLookup(str) {
      if (!str) return '';
      const arabicNumerals = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
      let s = String(str);
      for (let i = 0; i < 10; i++) {
        s = s.replaceAll(arabicNumerals[i], String(i));
      }
      return s.replace(/[\s\-_–—]/g, '').toUpperCase().trim();
    }

    async function lookupUnitByNationalId(rawId, clientId) {
      const normId = normalizeIdForLookup(rawId);
      const hintBox = document.getElementById('unitAutoHint');
      const hintText = document.getElementById('unit_msg') || document.getElementById('unitAutoHintText');
      const hintIcon = document.getElementById('unit_msg_icon');
      const hintMsgBox = document.getElementById('unit_msg_box');
      const unitInput = getUnitCodeInput();
      const errUnit = document.getElementById('errUnitCode');

      if (!normId || normId.length < 5) {
        if (hintBox) hintBox.classList.add('hidden');
        currentClientUnits = [];
        window.currentClientUnits = [];
        refreshAllUnitsPills();
        return;
      }

      let data = null;

      // 1. Try fetching from lookup_unit.php backend first
      try {
        const res = await fetch('lookup_unit.php?national_id=' + encodeURIComponent(normId));
        if (res.ok) {
          data = await res.json();
        }
      } catch (err) {
        // Fallback silently if offline or on static Vercel
      }

      // 2. Fallback to client-side window.customerUnitsLookup (for static / Vercel hosting)
      if (!data || typeof data.found === 'undefined') {
        if (window.customerUnitsLookup && window.customerUnitsLookup[normId]) {
          const matches = window.customerUnitsLookup[normId];
          const units = [...new Set(matches.map(m => m.unit))];
          data = {
            found: true,
            unit_code: units[0],
            unit: units[0],
            name: matches[0].name,
            units: units,
            total_units: units.length
          };
        } else {
          data = { found: false, message: 'الرقم ده مش موجود' };
        }
      }

      if (data && data.found) {
        const unitVal = data.unit_code || data.unit || (data.units && data.units[0]) || '';

        // Save into global variable currentClientUnits (Requirement 1)
        currentClientUnits = (data.units && data.units.length > 0) ? [...data.units] : [unitVal];
        window.currentClientUnits = currentClientUnits;
        currentLookupNationalId = normId;

        // Auto-fill primary unit code into input if empty or previously auto-filled
        if (unitInput) {
          if (!unitInput.value.trim() || unitInput.dataset.autoFilled === 'true') {
            unitInput.value = unitVal;
            unitInput.dataset.autoFilled = 'true';
            unitInput.classList.add('valid');
            unitInput.classList.remove('invalid');
            unitInput.dispatchEvent(new Event('input', { bubbles: true }));
            unitInput.dispatchEvent(new Event('change', { bubbles: true }));
            if (errUnit) errUnit.classList.add('hidden');
          }
        }

        // Auto-fill customer name if empty or very short
        const card = document.getElementById(`clientCard_${clientId}`) || document.getElementById(`clientCard_1`);
        if (card) {
          const nameInput = card.querySelector('.client-name-input');
          if (nameInput && (!nameInput.value.trim() || nameInput.value.trim().length < 2)) {
            nameInput.value = data.name;
            if (typeof handleClientNameInput === 'function') {
              handleClientNameInput(nameInput);
            }
          }
        }

        // Display success hint with isolated LTR unit code
        if (hintBox) {
          hintBox.classList.remove('hidden');
          hintBox.className = 'mt-2 p-2.5 rounded-xl bg-emerald-50 border border-emerald-300 text-xs font-bold text-emerald-950 transition-all';
          if (hintMsgBox) hintMsgBox.className = 'flex items-center gap-1.5 text-emerald-700 font-black';
          if (hintIcon) hintIcon.textContent = '✓';
          if (hintText) {
            hintText.innerHTML = (currentLang === 'en')
              ? `Unit (<bdi dir="ltr">${unitVal}</bdi>) retrieved automatically for customer: <strong>${data.name}</strong>`
              : `✓ تم جلب كود الوحدة (<bdi dir="ltr">${unitVal}</bdi>) تلقائياً للعميل: <strong>${data.name}</strong>`;
          }
        }

        // Refresh pills across all unit cards (Primary + Extra)
        refreshAllUnitsPills();

      } else {
        // Not found message
        currentClientUnits = [];
        window.currentClientUnits = [];
        refreshAllUnitsPills();

        if (hintBox) {
          hintBox.classList.remove('hidden');
          hintBox.className = 'mt-2 p-2.5 rounded-xl bg-amber-50 border border-amber-300 text-xs font-bold text-amber-950 transition-all';
          if (hintMsgBox) hintMsgBox.className = 'flex items-center gap-1.5 text-amber-700 font-black';
          if (hintIcon) hintIcon.textContent = 'ℹ';
          if (hintText) {
            hintText.textContent = (currentLang === 'en') ? 'This National ID / Passport was not found' : 'الرقم ده مش موجود';
          }
        }
      }
    }

    function validateClientIdNumber(input, clientId) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return false;
      const type = card.querySelector('.client-id-type').value;
      const val = input.value.trim();
      const errEl = card.querySelector('.client-id-error');

      if (errEl) errEl.classList.add('hidden');

      // Requirement 6: If primary card ID is cleared or changed, reset currentClientUnits and refresh pills
      if (clientId === 1 || clientId === '1') {
        const clean = normalizeIdForLookup(val);
        if (clean !== currentLookupNationalId) {
          currentLookupNationalId = clean;
          currentClientUnits = [];
          window.currentClientUnits = [];
          if (typeof refreshAllUnitsPills === 'function') refreshAllUnitsPills();
          const hintBox = document.getElementById('unitAutoHint');
          if (hintBox) hintBox.classList.add('hidden');
        }
      }

      if (!val) {
        input.classList.remove('valid', 'invalid');
        return false;
      }

      let isValid = false;
      if (type === 'national_id') {
        isValid = /^\d{14}$/.test(val);
        if (!isValid && val.length > 0 && errEl) {
          errEl.innerText = (currentLang === 'en') ? 'National ID must be exactly 14 digits' : 'رقم البطاقة الشخصية يجب أن يتكون من 14 رقماً بالضبط';
          errEl.classList.remove('hidden');
        }
      } else {
        isValid = /^[A-Za-z0-9]{6,9}$/.test(val);
        if (!isValid && val.length > 0 && errEl) {
          errEl.innerText = (currentLang === 'en') ? 'Passport number must be 6 to 9 alphanumeric characters' : 'رقم جواز السفر يجب أن يتكون من 6 إلى 9 أحرف وأرقام بدون رموز خاصة';
          errEl.classList.remove('hidden');
        }
      }

      input.classList.toggle('valid', isValid);
      input.classList.toggle('invalid', !isValid);
      if (typeof lookupUnitByNationalId === 'function') lookupUnitByNationalId(val, clientId);
      return isValid;
    }

    // Real-time reactive updates on any unit input change or typing
    document.addEventListener('input', function(e) {
      if (e.target && (e.target.id === 'unit_code' || e.target.name === 'unit_code' || e.target.name === 'extra_unit_codes[]' || e.target.classList.contains('extra-unit-input'))) {
        if (typeof refreshAllUnitsPills === 'function') refreshAllUnitsPills();
      }
    });
    document.addEventListener('change', function(e) {
      if (e.target && (e.target.id === 'unit_code' || e.target.name === 'unit_code' || e.target.name === 'extra_unit_codes[]' || e.target.classList.contains('extra-unit-input'))) {
        if (typeof refreshAllUnitsPills === 'function') refreshAllUnitsPills();
      }
    });

    function setClientResidence(clientId, value) {
      clientResidenceMap[clientId] = value;
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;

      const egyptBox = card.querySelector('.residence-box-egypt');
      const abroadBox = card.querySelector('.residence-box-abroad');
      const isOutside = value.includes('خارج');

      if (egyptBox) egyptBox.classList.toggle('active', !isOutside);
      if (abroadBox) abroadBox.classList.toggle('active', isOutside);

      const noticeText = card.querySelector('.client-doc-notice-text');
      const dropSubtitle = card.querySelector('.client-drop-subtitle');

      if (isOutside) {
        if (noticeText) noticeText.innerText = (currentLang === 'en')
          ? 'For clients residing outside Egypt: Uploading valid Passport + Residence permit is mandatory.'
          : 'للعملاء المقيمين خارج مصر: يُشترط رفع صورة واضحة لجواز السفر الساري + الإقامة.';
        if (dropSubtitle) dropSubtitle.innerText = (currentLang === 'en')
          ? '(Mandatory: Valid Passport + Residence Permit)'
          : '(مطلوب إجباري: جواز سفر ساري + إقامة سارية)';
      } else {
        if (noticeText) noticeText.innerText = (currentLang === 'en')
          ? 'For clients residing inside Egypt: Uploading both sides of the National ID is mandatory.'
          : 'للعملاء المقيمين داخل مصر: يُشترط رفع صورة واضحة لبطاقة الرقم القومي (الوجهين: الأمامي والخلفي).';
        if (dropSubtitle) dropSubtitle.innerText = (currentLang === 'en')
          ? '(Mandatory: National ID card both sides)'
          : '(مطلوب إجباري: صورة بطاقة الرقم القومي وجهين)';
      }

      // Update labels (المحافظة -> الدولة) for ID address ONLY!
      updateClientAddressLabels(clientId);

      // Refresh the Searchable Region Picker items for ID address
      renderRegionPickerItems(clientId);

      const displayEl = card.querySelector('.region-picker-display');
      const hiddenInput = card.querySelector('.client-gov-select');
      if (displayEl) {
        displayEl.innerHTML = isOutside
          ? `<span class="region-flag text-base flex-shrink-0">🌍</span><span class="region-name font-black truncate text-slate-950">${currentLang === 'en' ? 'Select Country...' : 'اختر الدولة...'}</span>`
          : `<span class="region-flag text-base flex-shrink-0">🇪🇬</span><span class="region-name font-black truncate text-slate-950">${currentLang === 'en' ? 'Select Governorate...' : 'اختر المحافظة...'}</span>`;
      }
      if (hiddenInput) {
        hiddenInput.value = '';
      }

      // Reset ID city select
      handleClientRegionChange(clientId, '', 'id');

      // The correspondence address is NEVER touched or modified!
      // "الجزء الخاص بعنوان المرسالات لا يتاثر بشي"

      updateContractLocationVisibility();
    }

    function updateClientAddressLabels(clientId) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;

      const isOutside = (clientResidenceMap[clientId] || '').includes('خارج');
      const isEn = (currentLang === 'en');
      const t = translations[currentLang] || {};

      const subIdAddr = card.querySelector('.client-sub-id-addr');
      if (subIdAddr) {
        subIdAddr.innerText = isOutside 
          ? (t.subIdAddressAbroad || (isEn ? 'Select Country & City + Street details' : 'اختر الدولة والمدينة واكتب باقي التفاصيل'))
          : (t.subIdAddress || (isEn ? 'Select Gov & City + Street details' : 'اختر المحافظة والمدينة واكتب باقي التفاصيل'));
      }

      const govLabelText = isOutside 
        ? (t.lblCountry || (isEn ? 'Country' : 'الدولة'))
        : (t.lblGovernorate || (isEn ? 'Governorate' : 'المحافظة'));

      const cityLabelText = isOutside 
        ? (t.lblCityAbroad || (isEn ? 'City / State / Province' : 'المدينة / الولاية / المقاطعة'))
        : (t.lblCity || (isEn ? 'City / District / Area' : 'المدينة / الحي / المركز'));

      const lblGov = card.querySelector('.client-lbl-gov-text');
      if (lblGov) lblGov.innerText = govLabelText;

      const lblCity = card.querySelector('.client-lbl-city-text');
      if (lblCity) lblCity.innerText = cityLabelText;

      // Note: Correspondence address labels always remain Governorate & City
      const corrLblGov = card.querySelector('.client-corr-lbl-gov-text');
      if (corrLblGov) corrLblGov.innerText = t.lblGovernorate || (isEn ? 'Governorate' : 'المحافظة');

      const corrLblCity = card.querySelector('.client-corr-lbl-city-text');
      if (corrLblCity) corrLblCity.innerText = t.lblCity || (isEn ? 'City / District / Area' : 'المدينة / الحي / المركز');
    }

    function updateContractLocationVisibility() {
      const locContainer = document.getElementById('contractLocationContainer');
      if (!locContainer) return;
      const anyInside = Object.values(clientResidenceMap).some(v => v.includes('داخل'));
      if (anyInside || Object.keys(clientResidenceMap).length === 0) {
        locContainer.classList.remove('hidden');
      } else {
        locContainer.classList.add('hidden');
        const locSelect = document.getElementById('contractLocation');
        if (locSelect) locSelect.value = '';
      }
    }

    // ── Searchable Region Picker for ID / Residence Address (Search Bar + Egypt #1 + All Continents) ──
    function renderRegionPickerItems(clientId) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;
      const scrollContainer = card.querySelector('.region-picker-scroll');
      if (!scrollContainer) return;
      scrollContainer.innerHTML = '';

      const isOutside = (clientResidenceMap[clientId] || '').includes('خارج');
      const isEn = (currentLang === 'en');

      if (isOutside) {
        // Group 1: Egypt #1 & Popular Countries
        const pinnedHeading = document.createElement('div');
        pinnedHeading.className = "text-[10px] font-black text-sky-950 px-2 pt-1 pb-0.5 uppercase tracking-wider text-start flex items-center justify-between";
        pinnedHeading.innerHTML = `<span>${isEn ? '⭐ Egypt & Popular Countries' : '⭐ مصر والدول الأكثر استخداماً'}</span><span class="text-[9px] text-sky-700 bg-sky-100 px-1.5 py-0.2 rounded font-mono">194 دولة</span>`;
        scrollContainer.appendChild(pinnedHeading);

        const pinnedCountries = worldCountriesData.filter(c => c.pinned);
        pinnedCountries.forEach(c => {
          scrollContainer.appendChild(createCountryPickerItemEl(c, clientId));
        });

        const divider = document.createElement('div');
        divider.className = "border-t-2 border-sky-100 my-1.5";
        scrollContainer.appendChild(divider);

        // Group 2: All other countries across all continents
        const allHeading = document.createElement('div');
        allHeading.className = "text-[10px] font-black text-slate-700 px-2 pt-1 pb-0.5 uppercase tracking-wider text-start";
        allHeading.innerText = isEn ? '🌍 All World Countries (All Continents)' : '🌍 باقي دول العالم (كافة القارات)';
        scrollContainer.appendChild(allHeading);

        const otherCountries = worldCountriesData.filter(c => !c.pinned);
        otherCountries.forEach(c => {
          scrollContainer.appendChild(createCountryPickerItemEl(c, clientId));
        });
      } else {
        // Inside Egypt: 27 Egyptian Governorates
        const govHeading = document.createElement('div');
        govHeading.className = "text-[10px] font-black text-sky-950 px-2 pt-1 pb-0.5 uppercase tracking-wider text-start flex items-center justify-between";
        govHeading.innerHTML = `<span>${isEn ? '🇪🇬 Egyptian Governorates' : '🇪🇬 محافظات جمهورية مصر العربية'}</span><span class="text-[9px] text-sky-700 bg-sky-100 px-1.5 py-0.2 rounded font-mono">27 محافظة</span>`;
        scrollContainer.appendChild(govHeading);

        Object.keys(egyptGovernoratesAndCities).forEach(gov => {
          if (gov.includes('خارج')) return;
          const item = document.createElement('button');
          item.type = 'button';
          item.className = "region-picker-item w-full flex items-center justify-between px-2.5 py-2 rounded-xl hover:bg-sky-100 text-slate-950 transition-colors cursor-pointer font-bold text-start";
          item.setAttribute('data-name-ar', gov);
          item.setAttribute('data-name-en', egyptGovernoratesEn[gov] || gov);
          item.setAttribute('data-continent-ar', 'مصر');
          item.setAttribute('data-continent-en', 'Egypt');

          const displayName = isEn ? (egyptGovernoratesEn[gov] || gov) : gov;
          item.innerHTML = `
            <span class="flex items-center gap-2 truncate">
              <span class="text-sm">📍</span>
              <span class="truncate font-black text-xs text-slate-950">${displayName}</span>
            </span>
          `;

          item.onclick = (e) => {
            e.stopPropagation();
            selectRegionPickerItem(clientId, { nameAr: gov, nameEn: egyptGovernoratesEn[gov] || gov, flag: '📍' }, false);
          };
          scrollContainer.appendChild(item);
        });
      }
    }

    function createCountryPickerItemEl(c, clientId) {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = "region-picker-item w-full flex items-center justify-between px-2.5 py-1.5 rounded-xl hover:bg-sky-100 text-slate-950 transition-colors cursor-pointer font-bold text-start";
      item.setAttribute('data-name-ar', c.nameAr);
      item.setAttribute('data-name-en', c.nameEn);
      item.setAttribute('data-continent-ar', c.continentAr || '');
      item.setAttribute('data-continent-en', c.continentEn || '');

      const isEn = (currentLang === 'en');
      const displayName = isEn ? c.nameEn : c.nameAr;

      item.innerHTML = `
        <span class="flex items-center gap-2 truncate">
          <span class="text-base flex-shrink-0">${c.flag}</span>
          <span class="truncate font-black text-xs text-slate-950">${displayName}</span>
        </span>
      `;

      item.onclick = (e) => {
        e.stopPropagation();
        selectRegionPickerItem(clientId, c, true);
      };
      return item;
    }

    function selectRegionPickerItem(clientId, itemData, isOutside) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;

      const displayEl = card.querySelector('.region-picker-display');
      const hiddenInput = card.querySelector('.client-gov-select');
      const dropdown = card.querySelector('.region-picker-dropdown');

      const isEn = (currentLang === 'en');
      const chosenName = isEn ? itemData.nameEn : itemData.nameAr;
      const storageValue = itemData.nameAr;

      if (displayEl) {
        displayEl.innerHTML = `
          <span class="region-flag text-base flex-shrink-0">${itemData.flag || (isOutside ? '🌍' : '📍')}</span>
          <span class="region-name font-black truncate text-slate-950">${chosenName}</span>
        `;
      }

      if (hiddenInput) {
        hiddenInput.value = storageValue;
        hiddenInput.classList.remove('invalid');
      }

      if (dropdown) {
        dropdown.classList.add('hidden');
      }

      handleClientRegionChange(clientId, storageValue, 'id');
    }

    function filterRegionPicker(clientId, searchInput) {
      const q = searchInput.value.trim().toLowerCase();
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;

      const items = card.querySelectorAll('.region-picker-item');
      items.forEach(item => {
        const ar = (item.getAttribute('data-name-ar') || '').toLowerCase();
        const en = (item.getAttribute('data-name-en') || '').toLowerCase();
        const contAr = (item.getAttribute('data-continent-ar') || '').toLowerCase();
        const contEn = (item.getAttribute('data-continent-en') || '').toLowerCase();
        const match = ar.includes(q) || en.includes(q) || contAr.includes(q) || contEn.includes(q);
        item.style.display = match ? 'flex' : 'none';
      });
    }

    function toggleRegionPickerDropdown(clientId, btn) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;
      const dropdown = card.querySelector('.region-picker-dropdown');
      if (!dropdown) return;

      const isHidden = dropdown.classList.contains('hidden');
      closeAllRegionPickerDropdowns();

      if (isHidden) {
        dropdown.classList.remove('hidden');
        const searchInput = dropdown.querySelector('.region-picker-search');
        if (searchInput) {
          searchInput.value = '';
          filterRegionPicker(clientId, searchInput);
          setTimeout(() => searchInput.focus(), 50);
        }
      }
    }

    function closeAllRegionPickerDropdowns() {
      document.querySelectorAll('.region-picker-dropdown').forEach(d => d.classList.add('hidden'));
    }

    function initClientAddressFields(clientId) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;

      updateClientAddressLabels(clientId);
      renderRegionPickerItems(clientId);

      // Correspondence address ALWAYS has Egyptian Governorates!
      const corrGovSelect = card.querySelector('.client-corr-gov-select');
      populateGovernorateOptions(corrGovSelect);
    }

    function populateGovernorateOptions(selectEl) {
      if (!selectEl) return;
      const currentVal = selectEl.value;
      const isEn = (currentLang === 'en');
      const t = translations[currentLang] || {};

      selectEl.innerHTML = `<option value="">${t.optSelectGov || (isEn ? 'Select Governorate...' : 'اختر المحافظة...')}</option>`;
      Object.keys(egyptGovernoratesAndCities).forEach(gov => {
        if (gov.includes('خارج')) return;
        const opt = document.createElement('option');
        opt.value = gov;
        const display = (isEn && egyptGovernoratesEn[gov]) ? egyptGovernoratesEn[gov] : gov;
        opt.textContent = display;
        opt.className = "bg-white text-slate-950 font-bold";
        selectEl.appendChild(opt);
      });
      if (currentVal && egyptGovernoratesAndCities[currentVal] && !currentVal.includes('خارج')) {
        selectEl.value = currentVal;
      }
    }

    function handleClientCorrGovChange(clientId, govVal) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;

      const citySelect = card.querySelector('.client-corr-city-select');
      if (!citySelect) return;
      citySelect.innerHTML = '';

      if (!govVal) {
        citySelect.disabled = true;
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = (currentLang === 'en') ? 'Select Governorate first...' : 'اختر المحافظة أولاً...';
        opt.className = "bg-white text-slate-950 font-bold";
        citySelect.appendChild(opt);
      } else {
        citySelect.disabled = false;
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = (currentLang === 'en') ? 'Select City / District...' : 'اختر المدينة / الحي / المركز...';
        defaultOpt.className = "bg-white text-slate-950 font-bold";
        citySelect.appendChild(defaultOpt);

        const cities = egyptGovernoratesAndCities[govVal] || [];
        cities.forEach(city => {
          const opt = document.createElement('option');
          opt.value = city;
          opt.textContent = city;
          opt.className = "bg-white text-slate-950 font-bold";
          citySelect.appendChild(opt);
        });

        const otherOpt = document.createElement('option');
        otherOpt.value = (currentLang === 'en') ? 'Other district / area' : 'أخرى / منطقة أخرى';
        otherOpt.textContent = (currentLang === 'en') ? 'Other district / area' : 'أخرى / منطقة أخرى';
        otherOpt.className = "bg-white text-slate-950 font-bold";
        citySelect.appendChild(otherOpt);
      }

      updateCombinedAddress(clientId, 'corr');
    }

    function handleClientRegionChange(clientId, regionVal, type) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;

      const isOutside = (clientResidenceMap[clientId] || '').includes('خارج');
      const isEn = (currentLang === 'en');
      const t = translations[currentLang] || {};

      const citySelect = (type === 'id') 
        ? card.querySelector('.client-city-select') 
        : card.querySelector('.client-corr-city-select');

      if (!citySelect) return;
      citySelect.innerHTML = '';

      if (!regionVal) {
        citySelect.disabled = true;
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = isOutside 
          ? (t.optSelectCountryFirst || (isEn ? 'Select Country first...' : 'اختر الدولة أولاً...'))
          : (t.optSelectGovFirst || (isEn ? 'Select Governorate first...' : 'اختر المحافظة أولاً...'));
        opt.className = "bg-white text-slate-950 font-bold";
        citySelect.appendChild(opt);
      } else {
        citySelect.disabled = false;
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = isOutside
          ? (t.optSelectCityAbroad || (isEn ? 'Select City / State / Province...' : 'اختر المدينة / الولاية / المقاطعة...'))
          : (t.optSelectCity || (isEn ? 'Select City / District...' : 'اختر المدينة / الحي / المركز...'));
        defaultOpt.className = "bg-white text-slate-950 font-bold";
        citySelect.appendChild(defaultOpt);

        const cities = isOutside 
          ? (worldCountriesAndCities[regionVal] || []) 
          : (egyptGovernoratesAndCities[regionVal] || []);

        cities.forEach(city => {
          const opt = document.createElement('option');
          opt.value = city;
          opt.textContent = city;
          opt.className = "bg-white text-slate-950 font-bold";
          citySelect.appendChild(opt);
        });

        // Other option
        const otherOpt = document.createElement('option');
        const otherVal = isOutside
          ? (t.optOtherCityAbroad || (isEn ? 'Other city / area abroad' : 'أخرى / مدينة أخرى بالخارج'))
          : (t.optOtherArea || (isEn ? 'Other district / area' : 'أخرى / منطقة أخرى'));
        otherOpt.value = otherVal;
        otherOpt.textContent = otherVal;
        otherOpt.className = "bg-white text-slate-950 font-bold";
        citySelect.appendChild(otherOpt);
      }

      updateCombinedAddress(clientId, type);
    }

    // Aliases for compatibility
    const handleClientGovChange = handleClientRegionChange;

    function handleClientCityChange(clientId, cityVal, type) {
      updateCombinedAddress(clientId, type);
    }

    function handleClientStreetInput(clientId, type) {
      updateCombinedAddress(clientId, type);
    }

    function toggleClientSameAddress(clientId, isSame) {
      // Retained for backward safety
    }

    function updateCombinedAddress(clientId, type) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;

      if (type === 'id') {
        const gov = card.querySelector('.client-gov-select')?.value.trim() || '';
        const city = card.querySelector('.client-city-select')?.value.trim() || '';
        const street = card.querySelector('.client-street-input')?.value.trim() || '';

        let combined = '';
        if (gov && city && street) {
          combined = `${gov} - ${city} - ${street}`;
        } else if (gov || city || street) {
          combined = [gov, city, street].filter(Boolean).join(' - ');
        }

        const idAddrHidden = card.querySelector('.client-id-address');
        if (idAddrHidden) idAddrHidden.value = combined;
      }

      if (type === 'corr') {
        const gov = card.querySelector('.client-corr-gov-select')?.value.trim() || '';
        const city = card.querySelector('.client-corr-city-select')?.value.trim() || '';
        const street = card.querySelector('.client-corr-street-input')?.value.trim() || '';

        let combined = '';
        if (gov && city && street) {
          combined = `${gov} - ${city} - ${street}`;
        } else if (gov || city || street) {
          combined = [gov, city, street].filter(Boolean).join(' - ');
        }

        const corrAddrHidden = card.querySelector('.client-corr-address');
        if (corrAddrHidden) corrAddrHidden.value = combined;
      }
    }

    // ── Client Specific Attachments ──
    function setupClientDropzone(clientId) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;
      const dropZone = card.querySelector('.client-drop-zone');
      const fileInput = card.querySelector('.client-file-input');

      dropZone.addEventListener('click', (e) => {
        if (e.target !== fileInput) {
          fileInput.click();
        }
      });

      ['dragenter', 'dragover'].forEach(name => {
        dropZone.addEventListener(name, (e) => {
          e.preventDefault();
          dropZone.classList.add('border-amber-400', 'bg-amber-500/10');
        });
      });

      ['dragleave', 'drop'].forEach(name => {
        dropZone.addEventListener(name, (e) => {
          e.preventDefault();
          dropZone.classList.remove('border-amber-400', 'bg-amber-500/10');
        });
      });

      dropZone.addEventListener('drop', (e) => {
        handleClientFilesSelected(clientId, e.dataTransfer.files);
      });
    }

    function handleClientFilesSelected(clientId, files) {
      if (!clientFilesState[clientId]) {
        clientFilesState[clientId] = [];
      }
      Array.from(files).forEach(file => {
        if (file.size <= 25 * 1024 * 1024) {
          clientFilesState[clientId].push(file);
        } else {
          alert(`File ${file.name} exceeds max size (25MB)`);
        }
      });

      renderClientFiles(clientId);

      const card = document.getElementById(`clientCard_${clientId}`);
      if (card && clientFilesState[clientId].length > 0) {
        card.querySelector('.client-files-error')?.classList.add('hidden');
      }
    }

    function renderClientFiles(clientId) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;
      const container = card.querySelector('.client-files-container');
      const list = card.querySelector('.client-files-list');
      const files = clientFilesState[clientId] || [];

      if (files.length === 0) {
        container.classList.add('hidden');
        list.innerHTML = '';
        return;
      }

      container.classList.remove('hidden');
      list.innerHTML = '';

      files.forEach((file, idx) => {
        const item = document.createElement('div');
        item.className = "flex items-center justify-between p-2.5 rounded-2xl bg-white border-2 border-sky-200 text-xs shadow-sm font-bold";

        const isImg = file.type.startsWith('image/');
        const previewUrl = isImg ? URL.createObjectURL(file) : null;

        item.innerHTML = `
          <div class="flex items-center gap-2.5 truncate">
            ${isImg 
              ? `<img src="${previewUrl}" class="w-10 h-10 object-cover rounded-xl border-2 border-sky-300">`
              : `<div class="w-10 h-10 rounded-xl bg-sky-50 border-2 border-sky-300 flex items-center justify-center text-lg">📄</div>`
            }
            <div class="truncate">
              <div class="truncate font-mono font-black text-slate-950">${file.name}</div>
              <div class="text-[10px] text-slate-600 font-mono font-bold">${(file.size/1024).toFixed(1)} KB</div>
            </div>
          </div>
          <button type="button" onclick="removeClientFile(${clientId}, ${idx})" class="p-1.5 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 font-black cursor-pointer transition-colors border border-red-200">
            ✕
          </button>
        `;
        list.appendChild(item);
      });
    }

    function removeClientFile(clientId, index) {
      if (clientFilesState[clientId]) {
        clientFilesState[clientId].splice(index, 1);
        renderClientFiles(clientId);
      }
    }

    function toggleAdvancePaidBox(radio) {
      const isYes = (radio.value === 'نعم');
      document.getElementById('boxYes')?.classList.toggle('active', isYes);
      document.getElementById('boxNo')?.classList.toggle('active', !isYes);

      const dateContainer = document.getElementById('paymentDateContainer');
      const unitDetails = document.getElementById('primaryUnitDetailsContainer');
      const noticeNo = document.getElementById('advanceNoticeNo');
      const submitBtn = document.getElementById('submitBtn');

      if (isYes) {
        if (dateContainer) dateContainer.classList.remove('hidden');
        if (unitDetails) unitDetails.classList.remove('hidden');
        if (noticeNo) noticeNo.classList.add('hidden');
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
      } else {
        if (dateContainer) dateContainer.classList.add('hidden');
        if (unitDetails) unitDetails.classList.add('hidden');
        if (noticeNo) noticeNo.classList.remove('hidden');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
        const dateInput = document.getElementById('paymentDate');
        if (dateInput) dateInput.value = '';
      }
    }

    function toggleExtraAdvancePaidBox(counter, radio) {
      const isYes = (radio.value === 'نعم');
      const boxYes = document.getElementById(`extraBoxYes_${counter}`);
      const boxNo = document.getElementById(`extraBoxNo_${counter}`);
      const container = document.getElementById(`extraPaymentDateContainer_${counter}`);
      const unitDetails = document.getElementById(`extraUnitDetailsContainer_${counter}`);
      const noticeNo = document.getElementById(`extraAdvanceNoticeNo_${counter}`);

      if (isYes) {
        boxYes?.classList.add('active');
        boxNo?.classList.remove('active');
        container?.classList.remove('hidden');
        unitDetails?.classList.remove('hidden');
        noticeNo?.classList.add('hidden');
      } else {
        boxYes?.classList.remove('active');
        boxNo?.classList.add('active');
        container?.classList.add('hidden');
        unitDetails?.classList.add('hidden');
        noticeNo?.classList.remove('hidden');
        const input = document.getElementById(`extraPaymentDate_${counter}`);
        if (input) input.value = '';
      }
    }

    function handleClientNameInput(input) {
      if (!input) return false;
      // السماح بالأحرف العربية وعلامات التشكيل والمسافات فقط (حذف أي حروف إنجليزية أو أرقام أو رموز فوراً)
      const cleanVal = input.value.replace(/[^\u0621-\u064A\u064B-\u0652\s]/g, '');
      if (input.value !== cleanVal) {
        input.value = cleanVal;
      }
      const val = input.value.trim();
      const isValid = /^[\u0621-\u064A\u064B-\u0652\s]{2,}$/.test(val);
      input.classList.toggle('valid', isValid);
      input.classList.toggle('invalid', !isValid && val.length > 0);
      const errEl = input.parentElement ? input.parentElement.querySelector('.error-msg') : null;
      if (errEl) {
        if (!isValid && val.length > 0) {
          errEl.classList.remove('hidden');
        } else if (isValid) {
          errEl.classList.add('hidden');
        }
      }
      return isValid;
    }

    function validateField(input, testFn) {
      const val = input.value.trim();
      const isValid = testFn(val);
      input.classList.toggle('valid', isValid);
      input.classList.toggle('invalid', !isValid && val.length > 0);
      return isValid;
    }

    function validateGeneralEmail(input) {
      const val = input.value.trim().toLowerCase();
      const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
      input.classList.toggle('valid', isValid);
      input.classList.toggle('invalid', !isValid && val.length > 0);
      const row = input.closest('.email-row');
      const errEl = row ? row.querySelector('.error-msg') : null;
      if (errEl) {
        if (!isValid && val.length > 0) {
          errEl.classList.remove('hidden');
        } else {
          errEl.classList.add('hidden');
        }
      }
      return isValid;
    }

    // ── Form Submission ──
    document.getElementById('contractForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      document.querySelectorAll('.error-msg').forEach(el => el.classList.add('hidden'));

      let hasError = false;
      const clientsData = [];

      // التحقق من كل بطاقة عميل / شريك على حدة
      for (let i = 0; i < activeClientIds.length; i++) {
        const cId = activeClientIds[i];
        const card = document.getElementById(`clientCard_${cId}`);
        if (!card) continue;

        const isPrimary = (i === 0);
        const nameInput = card.querySelector('.client-name-input');
        const isNameValid = nameInput ? handleClientNameInput(nameInput) : false;
        if (!isNameValid) {
          nameInput?.classList.add('invalid');
          nameInput?.parentElement?.querySelector('.error-msg')?.classList.remove('hidden');
          hasError = true;
        }

        // التحقق من أرقام الهواتف لهذه البطاقة
        const phoneRows = card.querySelectorAll('.phone-row');
        const clientPhones = [];
        let cardHasValidPhone = false;

        phoneRows.forEach(pRow => {
          const pInput = pRow.querySelector('.phone-input');
          const pCode = pRow.querySelector('.country-code-val')?.value || '+20';
          const isPhoneValid = handlePhoneInput(pInput);
          if (!isPhoneValid) {
            hasError = true;
          } else {
            cardHasValidPhone = true;
            const formatted = sanitizeAndFormatPhoneNumber(pCode, pInput.value.trim());
            clientPhones.push({
              code: pCode,
              number: pInput.value.trim(),
              formatted: formatted
            });
          }
        });

        if (phoneRows.length === 0 || !cardHasValidPhone) {
          hasError = true;
        }

        // التحقق من البريد الإلكتروني
        const emailRows = card.querySelectorAll('.email-row');
        const clientEmails = [];
        let cardHasValidEmail = false;

        emailRows.forEach(eRow => {
          const eInput = eRow.querySelector('.email-input');
          const eVal = eInput ? eInput.value.trim().toLowerCase() : '';
          const isEmailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(eVal);
          if (!isEmailValid) {
            eInput?.classList.add('invalid');
            eRow.querySelector('.error-msg')?.classList.remove('hidden');
            hasError = true;
          } else {
            cardHasValidEmail = true;
            clientEmails.push(eVal);
          }
        });

        if (emailRows.length === 0 || !cardHasValidEmail) {
          hasError = true;
        }

        // التحقق من رقم الهوية / جواز السفر
        const idInput = card.querySelector('.client-id-number');
        const idType = card.querySelector('.client-id-type')?.value || 'national_id';
        const isIdValid = validateClientIdNumber(idInput, cId);
        if (!isIdValid) {
          hasError = true;
        }

        // التحقق من العناوين (المحافظة والمدينة وباقي تفاصيل العنوان)
        updateCombinedAddress(cId, 'id');

        const govSelect = card.querySelector('.client-gov-select');
        const citySelect = card.querySelector('.client-city-select');
        const streetInput = card.querySelector('.client-street-input');
        const idAddrHidden = card.querySelector('.client-id-address');
        const idAddrErr = card.querySelector('.client-id-address-error');

        let cardIdAddrValid = true;
        if (!govSelect || !govSelect.value) {
          govSelect?.classList.add('invalid');
          cardIdAddrValid = false;
        } else {
          govSelect?.classList.remove('invalid');
        }

        if (!citySelect || !citySelect.value) {
          citySelect?.classList.add('invalid');
          cardIdAddrValid = false;
        } else {
          citySelect?.classList.remove('invalid');
        }

        if (!streetInput || streetInput.value.trim().length < 3) {
          streetInput?.classList.add('invalid');
          cardIdAddrValid = false;
        } else {
          streetInput?.classList.remove('invalid');
        }

        if (!cardIdAddrValid) {
          const isOutside = (clientResidenceMap[cId] || '').includes('خارج');
          const isEn = (currentLang === 'en');
          const t = translations[currentLang] || {};
          if (idAddrErr) {
            idAddrErr.innerText = isOutside
              ? (t.errIdAddressSelectAbroad || (isEn ? 'Please select country, city and enter street address details' : 'يرجى اختيار الدولة والمدينة وكتابة باقي تفاصيل العنوان'))
              : (t.errIdAddressSelect || (isEn ? 'Please select governorate, city and enter street address details' : 'يرجى اختيار المحافظة والمدينة وكتابة باقي تفاصيل العنوان'));
            idAddrErr.classList.remove('hidden');
          }
          hasError = true;
        } else {
          idAddrErr?.classList.add('hidden');
        }

        // التحقق من عنوان المراسلات (ظاهر دائماً)
        updateCombinedAddress(cId, 'corr');
        const corrAddrHidden = card.querySelector('.client-corr-address');
        const corrAddrErr = card.querySelector('.client-corr-address-error');
        const corrGovSelect = card.querySelector('.client-corr-gov-select');
        const corrCitySelect = card.querySelector('.client-corr-city-select');
        const corrStreetInput = card.querySelector('.client-corr-street-input');

        let cardCorrValid = true;
        if (!corrGovSelect || !corrGovSelect.value) {
          corrGovSelect?.classList.add('invalid');
          cardCorrValid = false;
        } else {
          corrGovSelect?.classList.remove('invalid');
        }

        if (!corrCitySelect || !corrCitySelect.value) {
          corrCitySelect?.classList.add('invalid');
          cardCorrValid = false;
        } else {
          corrCitySelect?.classList.remove('invalid');
        }

        if (!corrStreetInput || corrStreetInput.value.trim().length < 3) {
          corrStreetInput?.classList.add('invalid');
          cardCorrValid = false;
        } else {
          corrStreetInput?.classList.remove('invalid');
        }

        if (!cardCorrValid) {
          const isOutside = (clientResidenceMap[cId] || '').includes('خارج');
          const isEn = (currentLang === 'en');
          const t = translations[currentLang] || {};
          if (corrAddrErr) {
            corrAddrErr.innerText = isOutside
              ? (t.errCorrespondenceAddressAbroad || (isEn ? 'Approved correspondence address abroad is required' : 'يرجى اختيار الدولة والمدينة وكتابة باقي تفاصيل عنوان المراسلات'))
              : (t.errCorrespondenceAddress || (isEn ? 'Approved correspondence address is required' : 'العنوان المعتمد للمراسلات مطلوب'));
            corrAddrErr.classList.remove('hidden');
          }
          hasError = true;
        } else {
          corrAddrErr?.classList.add('hidden');
        }

        const idAddrInput = idAddrHidden;
        const corrAddrInput = corrAddrHidden;

        // التحقق من المرفقات لهذا العميل
        const files = clientFilesState[cId] || [];
        if (files.length === 0) {
          card.querySelector('.client-files-error')?.classList.remove('hidden');
          hasError = true;
        }

        // بناء كائن العميل داخل المصفوفة
        clientsData.push({
          id: cId,
          isPrimary: isPrimary,
          fullName: nameVal,
          phones: clientPhones,
          emails: clientEmails,
          idType: idType,
          nationalId: idInput ? idInput.value.trim() : '',
          idNumber: idInput ? idInput.value.trim() : '',
          residenceCountry: clientResidenceMap[cId] || 'داخل جمهورية مصر العربية',
          idAddress: idAddrInput ? idAddrInput.value.trim() : '',
          correspondenceAddress: corrAddrInput ? corrAddrInput.value.trim() : ''
        });
      }

      // Check for duplicate units across all unit cards
      if (typeof checkDuplicateUnits === 'function' && checkDuplicateUnits()) {
        alert(currentLang === 'en'
          ? 'There are duplicate unit codes across cards. Please ensure each unit is unique before submitting.'
          : 'يوجد تكرار في كود الوحدة بين البطاقات. يرجى التأكد من عدم تكرار أي وحدة قبل الإرسال.');
        hasError = true;
      }

      // التحقق من بيانات الوحدة ونظام السداد (Section 02)
      const unitCodeInput = getUnitCodeInput();
      const unitCode = unitCodeInput ? unitCodeInput.value.trim() : '';
      if (!unitCode) {
        document.getElementById('errUnitCode')?.classList.remove('hidden');
        if (unitCodeInput) {
          unitCodeInput.classList.add('invalid');
          unitCodeInput.classList.remove('valid');
        }
        hasError = true;
      } else {
        document.getElementById('errUnitCode')?.classList.add('hidden');
        if (unitCodeInput) {
          unitCodeInput.classList.add('valid');
          unitCodeInput.classList.remove('invalid');
        }
      }

      const periodEl = document.getElementById('period') || document.getElementById('paymentPeriod');
      const period = periodEl ? periodEl.value : '';
      const finalRateEl = document.getElementById('final_rate');
      const finalRate = finalRateEl ? finalRateEl.value : '';

      if (!period) {
        document.getElementById('errPeriod')?.classList.remove('hidden');
        hasError = true;
      }
      if (period && !finalRate) {
        const errRateSelect = document.getElementById('errRateSelect');
        const rateSelect = document.getElementById('rateSelect');
        if (errRateSelect) errRateSelect.classList.remove('hidden');
        if (rateSelect) rateSelect.classList.add('invalid');
        hasError = true;
      } else {
        const errRateSelect = document.getElementById('errRateSelect');
        const rateSelect = document.getElementById('rateSelect');
        if (errRateSelect) errRateSelect.classList.add('hidden');
        if (rateSelect) rateSelect.classList.remove('invalid');
      }

      // التحقق من الوحدات الإضافية إن وجدت
      const extraBlocks = document.querySelectorAll('#unitsContainer .unit-block:not(:first-child)');
      extraBlocks.forEach(blk => {
        const uInput = blk.querySelector('input[name="extra_unit_codes[]"]');
        const errUnit = blk.querySelector('.extra-unit-error');
        if (!uInput || !uInput.value.trim()) {
          if (errUnit) errUnit.classList.remove('hidden');
          if (uInput) {
            uInput.classList.add('invalid');
            uInput.classList.remove('valid');
          }
          hasError = true;
        } else {
          if (errUnit) errUnit.classList.add('hidden');
          if (uInput) {
            uInput.classList.add('valid');
            uInput.classList.remove('invalid');
          }
        }

        const pSel = blk.querySelector('select[name="extra_payment_periods[]"]');
        const rSel = blk.querySelector('select[name="extra_final_rates[]"]');
        const errRate = blk.querySelector('.extra-rate-error');
        if (pSel && pSel.value) {
          const rates = paymentRatesConfig[pSel.value] || [];
          if (rates.length > 1 && (!rSel || !rSel.value)) {
            if (errRate) errRate.classList.remove('hidden');
            if (rSel) rSel.classList.add('invalid');
            hasError = true;
          } else {
            if (errRate) errRate.classList.add('hidden');
            if (rSel) rSel.classList.remove('invalid');
          }
        }

        // التحقق من استكمال المقدم وتاريخ الدفع للوحدة الإضافية
        const extraPaidRadio = blk.querySelector('input[type="radio"]:checked');
        const extraPaidVal = extraPaidRadio ? extraPaidRadio.value : '';
        if (extraPaidVal === 'لا') {
          alert(currentLang === 'en'
            ? 'Contract registration cannot proceed without completing the down payment for all units.'
            : 'عفواً، لا يمكن استكمال حجز الوحدة الإضافية إلا بعد استكمال سداد قيمة المقدم الخاص بها.');
          hasError = true;
          return;
        }

        const dateInput = blk.querySelector('input[name="extra_payment_dates[]"]');
        const dateErr = blk.querySelector('.extra-payment-date-error');
        if (extraPaidVal === 'نعم' && (!dateInput || !dateInput.value)) {
          if (dateErr) dateErr.classList.remove('hidden');
          if (dateInput) dateInput.classList.add('invalid');
          hasError = true;
        } else {
          if (dateErr) dateErr.classList.add('hidden');
          if (dateInput) dateInput.classList.remove('invalid');
        }
      });

      // التحقق من استكمال المقدم وتاريخ الدفع للوحدة 1
      const advancePaidRadio = document.querySelector('input[name="paid_5_percent"]:checked');
      const advancePaidVal = advancePaidRadio ? advancePaidRadio.value : '';
      if (advancePaidVal === 'لا') {
        const noticeNo = document.getElementById('advanceNoticeNo');
        if (noticeNo) noticeNo.classList.remove('hidden');
        alert(currentLang === 'en'
          ? 'Contract registration cannot proceed without completing the down payment.'
          : 'عفواً، لا يمكن استكمال تسجيل التعاقد وحجز الوحدة إلا بعد استكمال سداد قيمة المقدم.');
        return;
      }

      if (advancePaidVal === 'نعم') {
        const paymentDate = document.getElementById('paymentDate').value;
        if (!paymentDate) {
          document.getElementById('errPaymentDate')?.classList.remove('hidden');
          hasError = true;
        }
      }

      // فرع توثيق العقد (يطلب فقط لو كان هناك عميل داخل مصر)
      const anyInside = Object.values(clientResidenceMap).some(v => v.includes('داخل'));
      if (anyInside) {
        const location = document.getElementById('contractLocation').value;
        if (!location) {
          document.getElementById('errLocation')?.classList.remove('hidden');
          hasError = true;
        }
      }

      if (hasError) {
        const firstErr = document.querySelector('.error-msg:not(.hidden), .invalid');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }

      // إعداد البيانات للإرسال
      document.getElementById('loadingOverlay').classList.remove('hidden');

      const formData = new FormData(this);
      formData.append('lang', currentLang);
      formData.append('clients_json', JSON.stringify(clientsData));

      // تمرير محافظة العميل الأساسي لحفظها في عمود governorate بجدول clients
      const primaryCard = document.getElementById(`clientCard_${activeClientIds[0]}`);
      const primaryGov = primaryCard?.querySelector('.client-gov-select')?.value.trim() || '';
      if (primaryGov) {
        formData.append('governorate', primaryGov);
      }

      // تمرير حالة سداد المقدم لكل وحدة إضافية
      extraBlocks.forEach(blk => {
        const extraPaidRadio = blk.querySelector('input[type="radio"]:checked');
        const extraPaidVal = extraPaidRadio ? extraPaidRadio.value : 'لا';
        formData.append('extra_paid_5_percent[]', extraPaidVal);
      });

      // إضافة الحقول المسطحة (Flat arrays) لضمان التوافقية الكاملة
      clientsData.forEach(c => {
        formData.append('names[]', c.fullName);
        formData.append('id_numbers[]', c.idNumber);
        formData.append('id_types[]', c.idType);
        formData.append('addresses[]', c.idAddress);
        c.phones.forEach(p => {
          formData.append('phone_numbers[]', p.formatted);
          formData.append('phone_codes[]', p.code);
        });
        c.emails.forEach(em => {
          formData.append('emails[]', em);
        });
      });

      // إضافة جميع المرفقات الخاصة بكافة الأطراف
      Object.keys(clientFilesState).forEach(cId => {
        const files = clientFilesState[cId] || [];
        files.forEach(file => {
          formData.append('attachments[]', file, file.name);
        });
      });

      try {
        const res = await fetch('submit.php', {
          method: 'POST',
          body: formData
        });

        const data = await res.json();
        document.getElementById('loadingOverlay').classList.add('hidden');

        if (data.status === 'success') {
          document.getElementById('modalUnitCode').innerText = data.unit_codes || unitCode;
          document.getElementById('successModal').classList.remove('hidden');

          if (typeof confetti === 'function') {
            confetti({
              particleCount: 120,
              spread: 70,
              origin: { y: 0.6 }
            });
          }
        } else {
          if (data.field === 'phone') {
            alert((currentLang === 'en' ? 'Phone error: ' : 'خطأ في رقم الهاتف: ') + data.message);
          } else if (data.field === 'unitCode' || data.field === 'eoiNumber') {
            document.getElementById('errUnitCode').innerText = data.message;
            document.getElementById('errUnitCode').classList.remove('hidden');
            const trig = document.getElementById('unitPickerTrigger');
            if (trig) {
              trig.classList.add('invalid');
              trig.focus();
            }
          } else {
            alert('Error: ' + (data.message || 'Error occurred while saving contract.'));
          }
        }
      } catch (err) {
        document.getElementById('loadingOverlay').classList.add('hidden');
        alert('Server connection error. Please ensure Apache & MySQL are running.');
      }
    });

    // قراءة كود المبيعات من رابط الصفحة (?slug=CODE أو ?ref=CODE) إن وجد وتمريره تلقائياً
    const urlParams = new URLSearchParams(window.location.search);
    const refFromUrl = urlParams.get('slug') || urlParams.get('ref') || '';
    if (refFromUrl) {
      const salesRefInput = document.querySelector('input[name="sales_ref"]');
      if (salesRefInput) {
        salesRefInput.value = refFromUrl;
      }
    }

    fetch('check_auth.php')
      .then(res => res.json())
      .then(data => {
        if (data && data.is_authenticated === true) {
          const btn = document.getElementById('adminDashboardBtn');
          if (btn) {
            btn.classList.remove('hidden');
            btn.classList.add('flex');
            const adminSpan = btn.querySelector('[data-i18n="adminLink"]');
            if (adminSpan) {
              if (data.role === 'Sales') adminSpan.innerText = 'لوحة المبيعات';
              else if (data.role === 'Manager') adminSpan.innerText = 'لوحة المدير';
              else adminSpan.innerText = 'لوحة التحكم';
            }
          }
        }
      })
      .catch(() => {});
  </script>

</body>
</html>
