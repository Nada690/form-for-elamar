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

    .bg-luxury-mesh {
      background-color: #121c2b;
      background-image: 
        radial-gradient(at 0% 0%, rgba(212, 175, 55, 0.12) 0px, transparent 50%),
        radial-gradient(at 100% 0%, rgba(37, 99, 235, 0.15) 0px, transparent 45%),
        radial-gradient(at 50% 100%, rgba(18, 28, 43, 0.95) 0px, transparent 65%);
      background-attachment: fixed;
    }

    .glass-card {
      background: rgba(22, 34, 52, 0.94);
      backdrop-filter: blur(28px);
      -webkit-backdrop-filter: blur(28px);
      border: 1.5px solid rgba(212, 175, 55, 0.32);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4), 0 0 25px rgba(212, 175, 55, 0.08);
    }

    .section-card {
      background: rgba(16, 26, 41, 0.75);
      border: 1px solid rgba(212, 175, 55, 0.2);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    }

    .glass-input {
      background: rgba(12, 20, 32, 0.88);
      border: 1.5px solid rgba(148, 163, 184, 0.25);
      color: #f8fafc;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-input:focus {
      outline: none;
      border-color: rgba(212, 175, 55, 0.7);
      box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2), 0 0 10px rgba(0, 0, 0, 0.3);
      background: rgba(16, 26, 42, 0.96);
    }
    .glass-input.valid {
      border-color: #10b981;
    }
    .glass-input.invalid {
      border-color: #ef4444;
    }

    select.glass-input option {
      background-color: #0c1420;
      color: #f8fafc;
    }

    /* تحديد نصوص شفاف ورمادي ناعم بدلاً من الأصفر الفاقع */
    ::selection {
      background: rgba(148, 163, 184, 0.25) !important;
      color: #ffffff !important;
      text-shadow: none !important;
    }
    ::-moz-selection {
      background: rgba(148, 163, 184, 0.25) !important;
      color: #ffffff !important;
      text-shadow: none !important;
    }
    input::selection, textarea::selection {
      background: rgba(148, 163, 184, 0.28) !important;
      color: #ffffff !important;
      text-shadow: none !important;
    }

    /* ضبط الملء التلقائي (Autofill) ليظل داكناً والكلام واضحاً دون تشويه */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    input:-webkit-autofill:active {
      -webkit-box-shadow: 0 0 0 1000px #0c1420 inset !important;
      -webkit-text-fill-color: #f8fafc !important;
      caret-color: #ffffff !important;
      transition: background-color 5000s ease-in-out 0s;
    }

    .custom-check-box {
      cursor: pointer;
      background: rgba(14, 23, 37, 0.75);
      border: 1.5px solid rgba(148, 163, 184, 0.25);
      color: #f1f5f9;
      transition: all 0.25s ease;
    }
    .custom-check-box:hover {
      border-color: #d4af37;
      background: rgba(212, 175, 55, 0.1);
    }
    .custom-check-box.active {
      border-color: #d4af37;
      background: rgba(212, 175, 55, 0.22);
      box-shadow: 0 0 12px rgba(212, 175, 55, 0.25);
    }

    @keyframes subtlePulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.85; transform: scale(0.995); }
    }
    .subtle-pulse {
      animation: subtlePulse 3s infinite ease-in-out;
    }

    .country-scroll::-webkit-scrollbar,
    .unit-scroll::-webkit-scrollbar {
      width: 5px;
    }
    .country-scroll::-webkit-scrollbar-track,
    .unit-scroll::-webkit-scrollbar-track {
      background: rgba(15, 23, 42, 0.6);
      border-radius: 8px;
    }
    .country-scroll::-webkit-scrollbar-thumb,
    .unit-scroll::-webkit-scrollbar-thumb {
      background: rgba(212, 175, 55, 0.4);
      border-radius: 8px;
    }
    .country-scroll::-webkit-scrollbar-thumb:hover,
    .unit-scroll::-webkit-scrollbar-thumb:hover {
      background: rgba(212, 175, 55, 0.7);
    }
    input[type="date"] {
      color-scheme: dark;
    }
    input[type="date"]::-webkit-calendar-picker-indicator {
      filter: invert(0.8) sepia(100%) saturate(300%) hue-rotate(5deg);
      cursor: pointer;
      opacity: 0.85;
      transition: opacity 0.2s ease;
    }
    input[type="date"]::-webkit-calendar-picker-indicator:hover {
      opacity: 1;
    }
  </style>
</head>

<body class="min-h-screen bg-luxury-mesh text-slate-100 flex flex-col relative selection:bg-slate-400/25 selection:text-white overflow-x-hidden">

  <!-- Ambient Light Orbs -->
  <div class="fixed top-10 right-10 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="fixed bottom-10 left-10 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

  <!-- ═══════════════ HEADER ═══════════════ -->
  <header class="border-b border-amber-500/25 bg-[#121c2b]/90 backdrop-blur-xl sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-3 sm:px-6 py-3 flex items-center justify-between gap-2 sm:gap-3">

      <!-- Logo + Brand -->
      <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0">
        <div class="p-1.5 sm:p-2 rounded-2xl bg-gradient-to-br from-amber-500/20 via-amber-400/10 to-[#121c2b] border-2 border-amber-400 shadow-[0_0_20px_rgba(212,175,55,0.45)] flex items-center justify-center flex-shrink-0">
          <img src="logo.png" alt="Logo" class="h-9 sm:h-12 w-auto object-contain drop-shadow-[0_2px_8px_rgba(0,0,0,0.6)]">
        </div>
        <div class="min-w-0">
          <div class="text-white font-black text-sm sm:text-lg tracking-wide leading-tight truncate" data-i18n="brandName">
            مجموعة العمار للتطوير العقاري
          </div>
          <div class="text-amber-400 text-[9px] sm:text-xs font-black tracking-widest uppercase drop-shadow-[0_1px_4px_rgba(212,175,55,0.25)] truncate" data-i18n="brandSlogan">
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
          class="px-2.5 sm:px-3.5 py-1.5 rounded-xl bg-slate-900/80 hover:bg-slate-800 border border-amber-400/40 text-amber-300 text-xs sm:text-sm font-bold flex items-center gap-1.5 transition-all shadow-md cursor-pointer hover:border-amber-400"
          title="Switch Language / تبديل اللغة"
        >
          <span class="text-xs sm:text-sm">🌐</span>
          <span id="langBtnText">English</span>
        </button>

        <a id="adminDashboardBtn" href="view_clients.php" class="hidden items-center gap-1 text-amber-300 text-xs px-2.5 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500/30 hover:bg-amber-500/25 transition-all font-bold">
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
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-2xl bg-gradient-to-r from-amber-500/20 via-amber-400/15 to-amber-500/20 border-2 border-amber-400/60 text-amber-300 text-xs sm:text-sm font-black shadow-[0_0_20px_rgba(212,175,55,0.25)] subtle-pulse max-w-full truncate">
            <span>💎</span>
            <span data-i18n="vipSalesIntro">استمارة معتمدة بإشراف:</span>
            <span class="text-white bg-amber-500/30 px-2 py-0.5 rounded-lg border border-amber-400/40 font-bold"><?= htmlspecialchars($salesName, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php else: ?>
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/15 border border-amber-400/50 text-amber-300 text-[11px] sm:text-xs font-bold shadow-sm max-w-full" data-i18n="officialSurveyTag">
            🏖️ استبيان واستيفاء بيانات عقود الشاليهات الساحلية (Shores Only)
          </div>
        <?php endif; ?>

        <h1 id="pageTitle" class="text-xl sm:text-3xl lg:text-4xl font-black bg-gradient-to-r from-amber-200 via-amber-400 to-yellow-400 bg-clip-text text-transparent tracking-tight drop-shadow-[0_2px_12px_rgba(212,175,55,0.25)]" data-i18n="pageTitle">
          استمارة بيانات العقد والوحدة
        </h1>
        <p id="pageSubtitle" class="text-slate-300 text-xs sm:text-sm max-w-lg mx-auto font-medium px-2" data-i18n="pageSubtitle">
          يرجى تسجيل وتدقيق بيانات العقد بدقة لضمان سرعة إتمام الإجراءات وتجهيز وثائق الملكية
        </p>

        <div class="h-0.5 w-24 sm:w-32 bg-gradient-to-r from-transparent via-amber-400 to-transparent mx-auto pt-0.5"></div>
      </div>

      <!-- ═══════════════ FORM CARD ═══════════════ -->
      <div class="glass-card rounded-2xl sm:rounded-3xl shadow-2xl overflow-hidden relative w-full">
        <div class="h-1.5 bg-gradient-to-r from-transparent via-amber-400 to-transparent"></div>

        <div class="p-3.5 sm:p-6 md:p-8 lg:p-10">

          <form id="contractForm" novalidate class="space-y-6 sm:space-y-7" enctype="multipart/form-data">
            
            <input type="hidden" name="sales_ref" value="<?= htmlspecialchars($salesRef, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="sales_id" value="<?= $salesId ? htmlspecialchars((string)$salesId, ENT_QUOTES, 'UTF-8') : '' ?>">

            <!-- ── SECTION 01: بيانات أطراف العقد والشركاء (Client & Partner Cards) ── -->
            <div class="section-card p-4 sm:p-6 rounded-2xl sm:rounded-3xl space-y-5">
              <div class="flex items-center justify-between pb-3 border-b border-amber-500/20">
                <div class="flex items-center gap-2.5 text-amber-400 font-black text-sm sm:text-base">
                  <span class="px-2.5 py-1 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-400/40 text-xs font-mono font-bold shadow-sm">01</span>
                  <span data-i18n="sec1Title">بيانات أطراف العقد والشركاء (بيانات العملاء)</span>
                </div>
                <span class="text-[11px] text-amber-300/90 font-mono font-bold bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/30" id="clientsCountBadge">الطرف الأول (الأساسي)</span>
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
                  class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-amber-500/15 via-amber-500/25 to-amber-500/15 hover:from-amber-500/25 hover:via-amber-500/35 hover:to-amber-500/25 border-2 border-dashed border-amber-400/50 hover:border-amber-300 text-amber-300 hover:text-white font-black text-xs sm:text-sm flex items-center justify-center gap-2.5 transition-all shadow-lg cursor-pointer group"
                >
                  <span class="text-base group-hover:scale-110 transition-transform">➕</span>
                  <span data-i18n="btnAddClientCard">+ إضافة شريك / عميل جديد بالعقد</span>
                </button>
              </div>
            </div>

            <!-- ── SECTION 02: بيانات الوحدة ونظام التعاقد وخطة السداد ── -->
            <div class="section-card p-4 sm:p-5 rounded-2xl space-y-4 sm:space-y-5">
              <div class="flex items-center gap-2.5 text-amber-400 font-black text-sm sm:text-base">
                <span class="px-2 py-0.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-400/40 text-xs font-mono font-bold shadow-sm">02</span>
                <span data-i18n="sec2TitleUnit">بيانات الوحدة ونظام التعاقد وخطة السداد</span>
              </div>

              <!-- ── حاوية الوحدات الأساسية والإضافية ── -->
              <div id="unitsContainer" class="space-y-4 w-full">

                <!-- 1. الوحدة الأساسية (إلزامية) -->
                <div class="unit-block p-4 rounded-2xl bg-slate-900/60 border border-amber-500/35 space-y-3 relative">
                  <div class="flex items-center justify-between pb-1 border-b border-slate-800/80">
                    <div class="flex items-center gap-2 text-amber-300 font-bold text-xs sm:text-sm">
                      <span>🏢</span>
                      <span data-i18n="unitMainTitle">بيانات الوحدة الأساسية (الوحدة 1)</span>
                      <span class="text-amber-400 font-bold">*</span>
                    </div>
                  </div>

                  <!-- سؤال: هل تم استكمال قيمة المقدم؟ (نعم / لا) وتاريخ الدفع الخاص بالوحدة 1 -->
                  <div class="p-3 sm:p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 mb-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                      <!-- السؤال وخيارات نعم / لا -->
                      <div class="space-y-1.5 flex-1">
                        <label class="block text-xs font-bold text-slate-300">
                          <span class="text-amber-300 font-bold" data-i18n="labelAdvanceQuestion">هل تم استكمال قيمة المقدم؟</span> 
                          <span class="text-amber-400 font-bold">*</span>
                        </label>
                        <div class="flex items-center gap-2 max-w-[220px]">
                          <label class="custom-check-box active flex-1 rounded-xl py-2 px-3 flex items-center justify-center gap-1.5 cursor-pointer text-xs font-bold text-white transition-all text-center" id="boxYes">
                            <input type="radio" name="paid_5_percent" value="نعم" checked class="hidden" onchange="toggleAdvancePaidBox(this)">
                            <span>✓</span> <span data-i18n="optYesPaid">نعم</span>
                          </label>
                          <label class="custom-check-box flex-1 rounded-xl py-2 px-3 flex items-center justify-center gap-1.5 cursor-pointer text-xs font-bold text-white transition-all text-center" id="boxNo">
                            <input type="radio" name="paid_5_percent" value="لا" class="hidden" onchange="toggleAdvancePaidBox(this)">
                            <span>✕</span> <span data-i18n="optNoPaid">لا</span>
                          </label>
                        </div>
                      </div>

                      <!-- حقل تاريخ الدفع (يظهر عندما تكون الإجابة نعم) -->
                      <div id="paymentDateContainer" class="space-y-1.5 sm:w-60 w-full transition-all">
                        <label class="block text-xs font-bold text-slate-300" for="paymentDate">
                          <span class="text-amber-300 font-bold" data-i18n="labelPaymentDate">📅 تاريخ الدفع:</span>
                          <span class="text-amber-400 font-bold">*</span>
                        </label>
                        <input
                          type="date"
                          id="paymentDate"
                          name="payment_date"
                          class="glass-input w-full px-3.5 py-2 rounded-xl text-white text-xs sm:text-sm font-mono border-amber-500/40 focus:border-amber-400"
                          value="<?= date('Y-m-d') ?>"
                        />
                        <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errPaymentDate" data-i18n="errPaymentDate">تاريخ الدفع مطلوب</p>
                      </div>
                    </div>
                  </div>

                  <!-- رسالة التوقف عند اختيار "لا" في استكمال المقدم -->
                  <div id="advanceNoticeNo" class="hidden p-4 rounded-2xl bg-amber-500/10 border border-amber-500/40 text-center space-y-2 my-2 animate-pulse">
                    <div class="text-2xl">⚠️</div>
                    <h4 class="text-amber-300 font-bold text-sm sm:text-base">سداد المقدم مطلوب لإتمام التعاقد</h4>
                    <p class="text-xs text-slate-300 leading-relaxed max-w-lg mx-auto">
                      عفواً، لا يمكن استكمال تسجيل التعاقد وحجز الوحدة إلا بعد استكمال سداد قيمة المقدم رسمياً. يُرجى مراجعة إدارة المبيعات لسداد المقدم أولاً للمتابعة.
                    </p>
                  </div>

                  <!-- حقول تفاصيل الوحدة الأساسية (تختفي إذا تم اختيار "لا") -->
                  <div id="primaryUnitDetailsContainer" class="grid grid-cols-1 sm:grid-cols-12 gap-3 transition-all">
                    <!-- كود الوحدة (إلزامي) -->
                    <div class="sm:col-span-12 form-group">
                      <label class="block text-xs font-bold text-slate-300 mb-1.5" for="unitCode">
                        <span class="text-amber-300 font-bold" data-i18n="labelUnitCode">كود الوحدة (الرقم الكودي الخاص بالوحدة)</span>
                        <span class="text-amber-400 font-bold">*</span>
                      </label>
                      
                      <!-- Searchable Unit Picker Component -->
                      <div class="relative unit-picker" id="primaryUnitPicker">
                        <input type="hidden" id="unitCode" name="unit_code" value="" />
                        
                        <!-- Trigger Box -->
                        <button
                          type="button"
                          id="unitPickerTrigger"
                          class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-300 text-xs sm:text-sm font-mono font-bold tracking-wider flex items-center justify-between border-amber-500/40 hover:border-amber-400 focus:border-amber-400 transition-all cursor-pointer select-none bg-slate-900/80"
                          onclick="toggleUnitDropdown('primaryUnitPicker')"
                        >
                          <span id="unitPickerSelectedText" class="truncate font-extrabold text-amber-300 tracking-widest uppercase">
                            <span class="text-slate-500 font-sans font-normal tracking-normal text-xs" data-i18n="phSelectUnit">اختر كود الوحدة من القائمة...</span>
                          </span>
                          <span class="text-amber-400 text-xs transition-transform duration-200 chevron-icon mr-2">▼</span>
                        </button>

                        <!-- Dropdown Panel -->
                        <div
                          id="unitDropdownPanel"
                          class="unit-dropdown hidden absolute top-full mt-1.5 inset-x-0 z-50 bg-[#0e1726] border border-amber-500/40 rounded-2xl shadow-[0_12px_40px_rgba(0,0,0,0.85)] p-2.5 backdrop-blur-xl"
                        >
                          <!-- Search Bar Inside Dropdown -->
                          <div class="relative mb-2">
                            <input
                              type="text"
                              id="unitSearchInput"
                              placeholder="ابحث عن كود الوحدة (مثال: N1-001)..."
                              data-i18n-placeholder="phSearchUnit"
                              dir="ltr"
                              autocomplete="off"
                              class="w-full pl-8 pr-3 py-2 rounded-xl bg-slate-900/90 border border-slate-700/80 text-amber-300 placeholder-slate-500 text-xs font-mono font-bold uppercase focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/30 transition-all"
                              oninput="filterUnitsList('primaryUnitPicker', this.value)"
                            />
                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">🔍</span>
                          </div>

                          <!-- Units Scrollable List -->
                          <div
                            id="unitListItems"
                            class="unit-scroll max-h-56 overflow-y-auto space-y-1 pr-1"
                          >
                            <!-- Generated Dynamically -->
                          </div>
                          
                          </div>
                        </div>
                      </div>

                      <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errUnitCode" data-i18n="errUnitCode">كود الوحدة مطلوب</p>
                    </div>

                    <!-- فترة السداد والنسبة (خطة السداد) -->
                    <div class="sm:col-span-6 form-group">
                      <label class="block text-xs font-bold text-slate-300 mb-1.5" for="period">
                        <span class="text-amber-300 font-bold" data-i18n="labelPeriod">فترة السداد</span>
                        <span class="text-amber-400 font-bold">*</span>
                      </label>
                      <select 
                        name="period" 
                        id="period" 
                        class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-amber-300 text-xs sm:text-sm font-bold border-amber-500/30 focus:border-amber-400 bg-slate-900/80 cursor-pointer" 
                        onchange="handlePeriodChange(this); validateField(this, val => val !== '')"
                      >
                        <option value="">اختر فترة السداد (Select Payment Period)...</option>
                        <option value="7 Years">7 Years</option>
                        <option value="8 Years">8 Years</option>
                        <option value="9 Years">9 Years</option>
                        <option value="10 Years">10 Years</option>
                      </select>
                      <input type="hidden" name="payment_period" id="paymentPeriod" value="">
                      <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errPeriod">فترة السداد مطلوبة</p>
                    </div>

                    <!-- حقل النسبة (Rate) - يظهر دائماً عند اختيار أي فترة -->
                    <div class="sm:col-span-6 form-group hidden" id="rateSelectContainer">
                      <label class="block text-xs font-bold text-slate-300 mb-1.5" for="rateSelect">
                        <span class="text-amber-300 font-bold" data-i18n="labelRate">النسبة (Rate)</span>
                        <span class="text-amber-400 font-bold">*</span>
                      </label>
                      <select 
                        id="rateSelect" 
                        class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-amber-300 text-xs sm:text-sm font-bold border-amber-500/30 focus:border-amber-400 bg-slate-900/80 cursor-pointer"
                        onchange="handleRateChange(this)"
                      >
                        <option value="">اختر النسبة...</option>
                      </select>
                      <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errRateSelect">يرجى اختيار النسبة</p>
                    </div>

                    <!-- حقل النسبة النهائي المخفي للباك إند وقاعدة البيانات -->
                    <input type="hidden" name="final_rate" id="final_rate" value="">

                    <!-- مقترح نظام السداد (موجود مباشرة تحت الخطة المختارة) -->
                    <div class="sm:col-span-12 form-group pt-2">
                      <label class="block text-xs font-bold text-slate-300 mb-1.5" for="clientPlanExceptions">
                        <span class="text-amber-300 font-bold" data-i18n="labelPlanExceptions">اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام</span>
                        <span class="text-slate-400 text-[10px] font-normal" data-i18n="optOptional">(اختياري)</span>
                      </label>
                      <textarea
                        id="clientPlanExceptions"
                        name="notes"
                        rows="2"
                        data-i18n-placeholder="phPlanExceptions"
                        placeholder="اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام..."
                        class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-white placeholder-slate-500 text-xs sm:text-sm focus:border-amber-400 transition-all resize-y"
                      ></textarea>
                    </div>
                  </div>
                </div>

              </div>

              <!-- زر إضافة وحدة أخرى -->
              <button 
                type="button" 
                onclick="addExtraUnitField()" 
                class="text-xs font-bold text-amber-300 hover:text-amber-200 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 px-4 py-2.5 rounded-xl flex items-center gap-2 transition-all cursor-pointer shadow-sm w-full justify-center"
              >
                <span>➕</span>
                <span data-i18n="btnAddUnit">إضافة وحدة أخرى (اختياري)</span>
              </button>


              <!-- 4. المكان الأقرب لتوثيق العقد (يظهر للعميل داخل مصر بعد سؤال المقدم مباشرة، ويخفى خارج مصر تماماً) -->
              <!-- خيارات نص صافي بدون أي إيموجي أو مصطلحات إضافية: القاهرة / الإسكندرية -->
              <div id="contractLocationContainer" class="form-group pt-2 border-t border-slate-800/80 w-full">
                <label class="block text-xs font-bold text-slate-300 mb-1.5" for="contractLocation">
                  <span class="text-amber-300 font-bold" data-i18n="labelLocation">المكان الأقرب لتوثيق العقد</span>
                  <span class="text-amber-400 font-bold">*</span>
                </label>
                <select 
                  name="contract_location" 
                  id="contractLocation" 
                  class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-amber-300 text-xs sm:text-sm font-bold border-amber-500/30 focus:border-amber-400" 
                  onchange="validateField(this, val => val !== '')"
                >
                  <option value="" data-i18n="optSelectLocation">اختر المكان الأقرب لتوثيق العقد...</option>
                  <option value="القاهرة" selected data-i18n="branchCairoClean">القاهرة</option>
                  <option value="الإسكندرية" data-i18n="branchAlexClean">الإسكندرية</option>
                </select>
                <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errLocation" data-i18n="errLocation">المكان الأقرب لتوثيق العقد مطلوب</p>
              </div>

              <!-- 5. هل لديك أي ملاحظات تود إخبارنا بها؟ (اختياري) -->
              <div class="form-group pt-3 border-t border-slate-800/80 w-full">
                <label class="block text-xs font-bold text-slate-300 mb-1.5" for="generalNotes">
                  <span class="text-amber-300 font-bold" data-i18n="labelGeneralNotes">هل لديك أي ملاحظات تود إخبارنا بها؟</span>
                  <span class="text-slate-400 text-[10px] font-normal" data-i18n="optOptional">(اختياري)</span>
                </label>
                <textarea
                  id="generalNotes"
                  name="general_notes"
                  rows="3"
                  data-i18n-placeholder="phGeneralNotes"
                  placeholder="اكتب هنا أي ملاحظات أو استفسارات إضافية تود إخبارنا بها..."
                  class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-white placeholder-slate-500 text-xs sm:text-sm focus:border-amber-400 transition-all resize-y"
                ></textarea>
              </div>



            </div>

            <!-- ── زر التأكيد والإرسال ── -->
            <div class="pt-2">
              <button
                type="submit"
                id="submitBtn"
                class="w-full py-3.5 sm:py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-sm sm:text-base shadow-2xl hover:shadow-amber-500/30 transition-all cursor-pointer flex items-center justify-center gap-2"
              >
                <span>🔒</span>
                <span data-i18n="submitBtnText">استيفاء وتسجيل بيانات التعاقد رسمياً</span>
              </button>
              <p class="text-[11px] text-slate-400 text-center mt-2.5" data-i18n="securityNotice">
                بالضغط على تسجيل، يتم حفظ بياناتك بأمان في قاعدة بيانات مجموعة العمار وتوثيقها رسمياً
              </p>
            </div>

          </form>

        </div>
      </div>

    </div>
  </main>

  <!-- ═══════════════ LOADING OVERLAY ═══════════════ -->
  <div id="loadingOverlay" class="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-md flex flex-col items-center justify-center hidden">
    <div class="w-14 sm:w-16 h-14 sm:h-16 border-4 border-amber-500/20 border-t-amber-400 rounded-full animate-spin mb-4"></div>
    <div class="font-black text-white text-sm sm:text-base" data-i18n="loadingTitle">جارٍ حفظ وتأكيد بيانات العقد...</div>
    <div class="text-xs text-amber-400 mt-1 font-mono" data-i18n="loadingSub">يرجى الانتظار ثوانٍ معدودة</div>
  </div>

  <!-- ═══════════════ SUCCESS MODAL ═══════════════ -->
  <div id="successModal" class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md flex items-center justify-center p-4 hidden">
    <div class="glass-card max-w-md w-full p-6 sm:p-8 rounded-3xl border border-amber-500/40 text-center shadow-2xl relative">
      <div class="w-16 sm:w-20 h-16 sm:h-20 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-3xl mx-auto mb-4 text-emerald-400 animate-bounce">
        ✓
      </div>

      <h3 class="text-lg sm:text-2xl font-black text-white mb-2" data-i18n="successTitle">
        تم استيفاء وتسجيل بياناتك بنجاح!
      </h3>

      <p class="text-xs sm:text-sm text-slate-300 mb-6" data-i18n="successDesc">
        تم تسجيل بيانات التعاقد وحفظها في المنظومة بنجاح، وسيقوم فريق المبيعات بالتواصل معكم لاستكمال الإجراءات الرسمية.
      </p>

      <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 text-xs mb-6 text-right space-y-2">
        <div class="flex justify-between">
          <span class="text-slate-400" data-i18n="lblUnitRef">كود الوحدة:</span>
          <span class="font-mono text-amber-300 font-bold uppercase" id="modalUnitCode">-</span>
        </div>
        <?php if ($salesInfo): ?>
          <div class="flex justify-between border-t border-slate-800 pt-1.5">
            <span class="text-slate-400" data-i18n="lblAdvisor">المستشار العقاري:</span>
            <span class="text-emerald-400 font-bold"><?= htmlspecialchars($salesName) ?></span>
          </div>
        <?php endif; ?>
        <div class="flex justify-between border-t border-slate-800 pt-1.5">
          <span class="text-slate-400" data-i18n="lblStatus">حالة الطلب:</span>
          <span class="text-emerald-400 font-bold" data-i18n="valApproved">معتمد وموثق</span>
        </div>
      </div>

      <button
        type="button"
        onclick="location.reload()"
        class="w-full py-3.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black rounded-xl text-sm shadow-lg cursor-pointer"
        data-i18n="btnAnotherForm"
      >
        حسناً، تسجيل استمارة أخرى
      </button>
    </div>
  </div>

  <!-- ═══════════════ SCRIPT LOGIC ═══════════════ -->
  <script>
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
        sec1Title: "قسم البيانات الشخصية وبيانات العميل",
        labelPrimaryName: "اسم العميل كما هو في البطاقة الشخصية",
        phPrimaryName: "(الاسم كاملاً ومطابقاً للهوية الشخصية)",
        errPrimaryName: "اسم العميل كما هو في البطاقة الشخصية مطلوب",
        btnAddPartner: "إضافة اسم شريك / طرف آخر بالعقد",
        sec2Title: "رقم الهاتف والتواصل والبريد",
        labelPrimaryPhone: "رقم الهاتف والتواصل (يدعم الأرقام المحلية والدولية)",
        phSearchCountry: "بحث عن الدولة أو الكود...",
        errPrimaryPhone: "رقم الهاتف مطلوب وبصيغة صحيحة",
        btnAddPhone: "+ إضافة رقم هاتف إضافي",
        labelEmail: "البريد الإلكتروني (متاح للتواصل والمراسلات الرسمية)",
        hintEmail: "يرجى إدخال بريد إلكتروني نشط ومتاح لتلقي الإشعارات والمراسلات الرسمية الخاصة بالعقد",
        phEmail: "example@domain.com",
        errEmail: "يرجى إدخال بريد إلكتروني صحيح ومتاح للمراسلات الرسمية (example@domain.com)",
        sec3Title: "إثبات الشخصية ومحل الإقامة والعنوان والمرفقات",
        labelIdType: "نوع الوثيقة",
        optNatId: "بطاقة الرقم القومي (14 رقم)",
        optPassport: "جواز السفر (Passport)",
        labelIdNumber: "رقم بطاقة الرقم القومي أو جواز السفر",
        phIdNumber: "أدخل 14 رقماً بالضبط للبطاقة الشخصية",
        errIdNumberNational: "رقم البطاقة الشخصية يجب أن يتكون من 14 رقماً بالضبط",
        errIdNumberPassport: "رقم جواز السفر يجب أن يتكون من 6 إلى 9 أحرف وأرقام بدون رموز خاصة",
        labelResidenceCountry: "مكان الإقامة الحالي:",
        optInsideEgypt: "داخل جمهورية مصر العربية",
        optOutsideEgypt: "خارج جمهورية مصر العربية",
        labelIdAddress: "العنوان كما هو مسجل في إثبات الهوية الشخصية",
        phIdAddress: "المحافظة، المدينة، اسم الشارع، رقم العقار كما في البطاقة أو الجواز",
        errIdAddress: "العنوان كما هو مسجل في إثبات الهوية الشخصية مطلوب",
        labelCorrespondenceAddress: "العنوان المعتمد بالتفصيل للمراسلات",
        phCorrespondenceAddress: "اسم الشارع، رقم المبنى، الشقة للمراسلات الرسمية",
        errCorrespondenceAddress: "العنوان المعتمد للمراسلات مطلوب بالتفصيل",
        secAttachmentsTitle: "المستندات والمرفقات المطلوبة",
        docNoticeInside: "للعملاء المقيمين داخل مصر: يُشترط رفع صورة واضحة لبطاقة الرقم القومي (الوجهين: الأمامي والخلفي).",
        docNoticeOutside: "للعملاء المقيمين خارج مصر: يُشترط رفع صورة واضحة لجواز السفر الساري + صورة بطاقة الإقامة السارية.",
        dropTitle: "اضغط لاختيار المستندات أو اسحب الملفات وأفلتها هنا",
        dropSubtitleInside: "(مطلوب إجباري: صورة بطاقة الرقم القومي وجهين)",
        dropSubtitleOutside: "(مطلوب إجباري: جواز السفر الساري + الإقامة)",
        selectedFilesHeading: "المعاينات المرفقة للإرسال:",
        errAttachments: "المستندات المطلوبة إلزامية، يرجى رفع الملفات المحددة أعلاه",
        sec4Title: "بيانات الوحدة ونظام التعاقد والخيارات الشرطية",
        labelUnitCode: "كود الوحدة (الرقم الكودي الخاص بالوحدة)",
        phUnitCode: "مثال: nn-13 أو B2-104",
        errUnitCode: "كود الوحدة مطلوب",
        labelPeriod: "فترة السداد",
        labelRate: "النسبة (Rate)",
        errRateSelect: "يرجى اختيار النسبة",
        optSelectPeriod: "اختر فترة السداد...",
        opt3Yr: "3 سنوات",
        opt4Yr: "4 سنوات",
        opt5Yr: "5 سنوات",
        opt6Yr: "6 سنوات",
        opt7Yr: "7 سنوات",
        opt8Yr: "8 سنوات",
        opt9Yr: "9 سنوات",
        opt10Yr: "10 سنوات",
        errPeriod: "فترة السداد مطلوبة",
        labelAdvanceQuestion: "هل تم استكمال قيمة المقدم؟",
        optYesPaid: "نعم",
        optNoPaid: "لا",
        labelPaymentDate: "📅 تاريخ الدفع:",
        hintPaymentDate: "حدد تاريخ استكمال سداد قيمة المقدم",
        errPaymentDate: "تاريخ الدفع مطلوب",
        labelLocation: "المكان الأقرب لتوثيق العقد",
        optSelectLocation: "اختر المكان الأقرب لتوثيق العقد...",
        branchCairoClean: "القاهرة",
        branchAlexClean: "الإسكندرية",
        errLocation: "المكان الأقرب لتوثيق العقد مطلوب",
        labelPlanExceptions: "اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام",
        phPlanExceptions: "اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام...",
        optOptional: "(اختياري)",
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
        btnAddUnit: "إضافة وحدة أخرى (اختياري)",
        labelGeneralNotes: "هل لديك أي ملاحظات تود إخبارنا بها؟",
        phGeneralNotes: "اكتب هنا أي ملاحظات أو استفسارات إضافية تود إخبارنا بها...",
        unitMainTitle: "بيانات الوحدة الأساسية (الوحدة 1)",
        labelUnitPrice: "قيمة / سعر الوحدة (EGP)",
        phUnitPrice: "مثال: 4,500,000",
        partnerPlaceholder: "اسم الشريك بالكامل كما هو بالهوية الشخصية",
        phSelectUnit: "اختر كود الوحدة من القائمة...",
        phSearchUnit: "ابحث عن كود الوحدة (مثال: N1-001)...",
        noUnitsFound: "لا توجد وحدة مطابقة لبحثك",
        showingCount: "عرض {count} من {total} وحدة",
        allUnitsCount: "عرض أول {count} وحدة"
      },
      en: {
        brandName: "Al Amar Real Estate Development",
        brandSlogan: "SHORES ONLY • NORTH COAST",
        adminLink: "Admin Dashboard",
        vipSalesIntro: "Certified Form Supervised by:",
        officialSurveyTag: "🏖️ Official Shores Coastal Units Survey & Contract Verification",
        pageTitle: "Contract & Unit Registration Form",
        pageSubtitle: "Please review and enter contract information accurately for formal processing & official registry",
        sec1Title: "Personal Details & Client Information",
        labelPrimaryName: "Client Name as in National ID",
        phPrimaryName: "(Full name matching National ID or Passport)",
        errPrimaryName: "Client name as in ID is required",
        btnAddPartner: "+ Add Partner Name to Contract",
        sec2Title: "Contact Phone, International Code & Email",
        labelPrimaryPhone: "Phone & Contact Number (Local & International)",
        phSearchCountry: "Search country or dialing code...",
        errPrimaryPhone: "Valid phone number is required",
        btnAddPhone: "+ Add Additional Phone Number",
        labelEmail: "Email (Official Correspondence & Notices)",
        hintEmail: "Please provide an active email for official contract notices and correspondence",
        phEmail: "example@domain.com",
        errEmail: "Please provide a valid email for correspondence (example@domain.com)",
        sec3Title: "Identity Proof, Residence, Address & Attachments",
        labelIdType: "ID Document Type",
        optNatId: "National ID (14 digits)",
        optPassport: "Passport",
        labelIdNumber: "National ID or Passport Number",
        phIdNumber: "Enter exactly 14 digits for National ID",
        errIdNumberNational: "National ID must be exactly 14 digits",
        errIdNumberPassport: "Passport number must be 6 to 9 alphanumeric characters without special symbols",
        labelResidenceCountry: "Current Residence Place:",
        optInsideEgypt: "Inside Arab Republic of Egypt",
        optOutsideEgypt: "Outside Arab Republic of Egypt",
        labelIdAddress: "Address as registered in Personal ID",
        phIdAddress: "Governorate, City, Street, Building No. as in ID or Passport",
        errIdAddress: "Address as in ID is required",
        labelCorrespondenceAddress: "Approved Correspondence Address in Detail",
        phCorrespondenceAddress: "Street name, building number, apartment number for official mail",
        errCorrespondenceAddress: "Approved correspondence address is required in detail",
        secAttachmentsTitle: "Required Documents & Attachments",
        docNoticeInside: "For clients residing inside Egypt: Uploading both sides of the National ID is mandatory.",
        docNoticeOutside: "For clients residing outside Egypt: Uploading valid Passport + Residence permit is mandatory.",
        dropTitle: "Click to upload documents or drag & drop files here",
        dropSubtitleInside: "(Mandatory: National ID card both sides)",
        dropSubtitleOutside: "(Mandatory: Valid Passport + Residence Permit)",
        selectedFilesHeading: "Attached Files Preview:",
        errAttachments: "Required documents are mandatory. Please attach the required files.",
        sec4Title: "Unit Information, Payment Term & Conditional Options",
        labelUnitCode: "Unit Code (Unit Identification Code)",
        phUnitCode: "e.g. nn-13 or B2-104",
        errUnitCode: "Unit code is required",
        labelPeriod: "Payment Term",
        labelRate: "Rate",
        errRateSelect: "Please select a rate",
        optSelectPeriod: "Select payment term...",
        opt3Yr: "3 Years",
        opt4Yr: "4 Years",
        opt5Yr: "5 Years",
        opt6Yr: "6 Years",
        opt7Yr: "7 Years",
        opt8Yr: "8 Years",
        opt9Yr: "9 Years",
        opt10Yr: "10 Years",
        errPeriod: "Payment term selection is required",
        labelAdvanceQuestion: "Was the down payment completed?",
        optYesPaid: "Yes",
        optNoPaid: "No",
        labelPaymentDate: "📅 Payment Date:",
        hintPaymentDate: "Specify the date the down payment was made",
        errPaymentDate: "Payment date is required",
        labelLocation: "Nearest Branch for Contract Signing",
        optSelectLocation: "Select contract branch...",
        branchCairoClean: "Cairo",
        branchAlexClean: "Alexandria",
        errLocation: "Contract branch is required",
        labelPlanExceptions: "Write any proposed payment plan you wish to record freely without obligation",
        phPlanExceptions: "Write any proposed payment plan you wish to record freely without obligation...",
        optOptional: "(Optional)",
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
        btnAddUnit: "Add Another Unit (Optional)",
        labelGeneralNotes: "Do you have any notes or inquiries you'd like to tell us?",
        phGeneralNotes: "Write any additional notes or inquiries you'd like to share with us...",
        unitMainTitle: "Primary Unit Information (Unit 1)",
        labelUnitPrice: "Unit Price (EGP)",
        phUnitPrice: "e.g. 4,500,000",
        partnerPlaceholder: "Full partner name as in ID",
        phSelectUnit: "Select unit code from list...",
        phSearchUnit: "Search unit code (e.g. N1-001)...",
        noUnitsFound: "No matching units found",
        showingCount: "Showing {count} of {total} units",
        allUnitsCount: "Showing first {count} units"
      }
    };

    let currentLang = localStorage.getItem('elamar_lang') || 'ar';
    let currentResidence = 'داخل جمهورية مصر العربية';
    let selectedFiles = [];

    document.addEventListener('DOMContentLoaded', () => {
      addNewClientCard(true);
      setLanguage(currentLang);
      
      document.addEventListener('click', (e) => {
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

      document.getElementById('langBtnText').innerText = (lang === 'ar') ? 'English' : 'العربية';

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

      if (typeof reNumberClientCards === 'function') {
        reNumberClientCards();
      }
      if (typeof updateClientsCountBadge === 'function') {
        updateClientsCountBadge();
      }
      if (typeof activeClientIds !== 'undefined' && Array.isArray(activeClientIds)) {
        activeClientIds.forEach(cId => {
          if (typeof initClientAddressFields === 'function') {
            initClientAddressFields(cId);
          }
        });
      }
    }

    // ── Country Dropdown Builder with Clean Formatting ──
    function buildCountryList(container) {
      const listContainer = container.querySelector('.country-scroll');
      if (!listContainer) return;
      listContainer.innerHTML = '';

      const pinnedTitle = document.createElement('div');
      pinnedTitle.className = "text-[10px] font-bold text-amber-400/80 px-2 pt-1 pb-0.5 uppercase tracking-wider text-start";
      pinnedTitle.innerText = (currentLang === 'en') ? '⭐ Popular Countries' : '⭐ الدول الأكثر استخداماً';
      listContainer.appendChild(pinnedTitle);

      countryData.filter(c => c.pinned).forEach(c => {
        listContainer.appendChild(createCountryItemEl(c, container));
      });

      const div = document.createElement('div');
      div.className = "border-t border-slate-800 my-1";
      listContainer.appendChild(div);

      const allTitle = document.createElement('div');
      allTitle.className = "text-[10px] font-bold text-slate-400 px-2 pt-1 pb-0.5 uppercase tracking-wider text-start";
      allTitle.innerText = (currentLang === 'en') ? '🌍 All Countries (Alphabetical)' : '🌍 باقي دول العالم (ترتيب أبجدي)';
      listContainer.appendChild(allTitle);

      countryData.filter(c => !c.pinned).forEach(c => {
        listContainer.appendChild(createCountryItemEl(c, container));
      });
    }

    function createCountryItemEl(c, pickerContainer) {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = "country-item w-full flex items-center justify-between px-2.5 py-1.5 rounded-xl hover:bg-amber-500/15 text-slate-200 hover:text-white transition-colors cursor-pointer";
      item.setAttribute('data-name-ar', c.nameAr);
      item.setAttribute('data-name-en', c.nameEn);
      item.setAttribute('data-code', c.code);

      const name = (currentLang === 'en') ? c.nameEn : c.nameAr;
      item.innerHTML = `
        <span class="flex items-center gap-2 truncate text-start">
          <span class="text-base flex-shrink-0">${c.flag}</span>
          <span class="truncate font-semibold text-xs">${name}</span>
        </span>
        <span class="font-mono text-amber-300 font-bold dir-ltr ml-2 flex-shrink-0 text-xs">${c.code}</span>
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
          <div class="p-3 text-center text-xs text-slate-400 bg-slate-900/60 rounded-xl border border-slate-800">
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
        btn.className = `w-full text-right px-3 py-2 rounded-xl text-xs sm:text-sm font-mono font-bold tracking-wider flex items-center justify-between transition-all cursor-pointer select-none ${
          isSelected 
            ? 'bg-amber-500/25 text-amber-300 border border-amber-400 shadow-sm' 
            : 'text-slate-200 hover:bg-slate-800/90 hover:text-amber-300'
        }`;
        
        btn.innerHTML = `
          <span class="flex items-center gap-2">
            <span class="text-amber-400 text-xs font-mono">🏢</span>
            <span class="uppercase">${unitCode}</span>
          </span>
          ${isSelected ? '<span class="text-amber-400 text-xs">✓</span>' : '<span class="text-[10px] text-slate-500">شورز</span>'}
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
        selectedSpan.innerHTML = `<span class="text-amber-300 font-mono font-extrabold tracking-widest uppercase">${code}</span>`;
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
          class="glass-input flex-1 px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-white placeholder-slate-500 text-xs sm:text-sm font-semibold"
        />
        <button type="button" onclick="removeDynamicField(this)" class="p-2.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-xs cursor-pointer font-bold flex-shrink-0">
          ✕
        </button>
      `;
      container.appendChild(div);
      setTimeout(() => {
        div.classList.remove('opacity-0', 'translate-y-2');
      }, 20);
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
            class="country-btn glass-input w-full px-2 sm:px-2.5 py-2.5 sm:py-3 rounded-xl text-amber-300 text-xs font-bold flex items-center justify-between cursor-pointer hover:border-amber-400 transition-all"
          >
            <span class="country-display flex items-center gap-1 sm:gap-1.5 truncate">
              <span class="text-sm sm:text-base">🇪🇬</span>
              <span class="font-mono text-xs">+20</span>
            </span>
            <span class="text-[9px] text-slate-400">▼</span>
          </button>
          
          <input type="hidden" name="phone_codes[]" value="+20" class="country-code-val">

          <div class="country-dropdown hidden absolute top-full mt-1.5 start-0 z-50 w-72 max-w-[85vw] sm:w-80 bg-slate-900/98 border border-amber-500/40 rounded-2xl shadow-2xl p-2.5 backdrop-blur-2xl">
            <div class="relative mb-2">
              <input 
                type="text" 
                placeholder="بحث عن الدولة أو الكود..." 
                oninput="filterCountryList(this)" 
                class="country-search-input w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-medium"
              >
              <span class="absolute end-2.5 top-2.5 text-xs text-slate-400">🔍</span>
            </div>
            <div class="country-scroll max-h-56 overflow-y-auto space-y-1 text-xs pr-1"></div>
          </div>
        </div>

        <input
          type="tel"
          name="phone_numbers[]"
          placeholder="10xxxxxxxx / 010xxxxxxxx"
          class="glass-input flex-1 min-w-0 px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-emerald-300 placeholder-slate-500 text-xs sm:text-sm font-mono text-left"
          oninput="handlePhoneInput(this)"
        />
        <button type="button" onclick="removeDynamicField(this)" class="p-2.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-xs cursor-pointer font-bold flex-shrink-0">
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
      div.className = "unit-block p-4 rounded-2xl bg-slate-900/60 border border-slate-700/70 hover:border-amber-500/40 space-y-3 relative transform transition-all duration-300 opacity-0 translate-y-2";
      const pickerId = `extraUnitPicker_${extraUnitCounter}`;
      const cNum = extraUnitCounter;

      const unitTitle = (currentLang === 'en') ? `Additional Unit (Unit ${cNum})` : `بيانات الوحدة الإضافية (الوحدة ${cNum})`;
      const removeTitle = (currentLang === 'en') ? 'Remove this unit' : 'حذف هذه الوحدة';
      const labelUnitCode = translations[currentLang]?.labelUnitCode || (currentLang === 'en' ? 'Unit Code' : 'كود الوحدة (الرقم الكودي الخاص بالوحدة)');
      const phSelectUnit = translations[currentLang]?.phSelectUnit || (currentLang === 'en' ? 'Select unit code from list...' : 'اختر كود الوحدة من القائمة...');
      const phSearchUnit = translations[currentLang]?.phSearchUnit || (currentLang === 'en' ? 'Search unit code...' : 'ابحث عن كود الوحدة...');
      const labelPeriod = translations[currentLang]?.labelPeriod || (currentLang === 'en' ? 'Payment Term' : 'فترة السداد');
      const labelRate = translations[currentLang]?.labelRate || (currentLang === 'en' ? 'Rate' : 'النسبة (Rate)');
      const labelProposal = translations[currentLang]?.labelPlanExceptions || (currentLang === 'en' ? 'Write any proposed payment plan you wish to record freely without obligation' : 'اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام');
      const phProposal = translations[currentLang]?.phPlanExceptions || (currentLang === 'en' ? 'Write any proposed payment plan you wish to record freely without obligation...' : 'اكتب أي مقترح لنظام سداد ترغب في تسجيله بحرية بدون إلزام...');
      const optOptional = (currentLang === 'en') ? '(Optional)' : '(اختياري)';

      div.innerHTML = `
        <div class="flex items-center justify-between pb-1 border-b border-slate-800/80">
          <div class="flex items-center gap-2 text-amber-300 font-bold text-xs sm:text-sm">
            <span>🏢</span>
            <span>${unitTitle}</span>
            <span class="text-slate-400 text-[10px] font-normal">${optOptional}</span>
          </div>
          <button 
            type="button" 
            onclick="removeExtraUnit(this)" 
            class="text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/25 border border-red-500/30 px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1"
            title="${removeTitle}"
          >
            <span>❌</span>
            <span>${removeTitle}</span>
          </button>
        </div>

        <!-- سؤال: هل تم استكمال قيمة المقدم؟ (نعم / لا) وتاريخ الدفع للوحدة الإضافية -->
        <div class="p-3 sm:p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 mb-2">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <!-- السؤال وخيارات نعم / لا -->
            <div class="space-y-1.5 flex-1">
              <label class="block text-xs font-bold text-slate-300">
                <span class="text-amber-300 font-bold">${currentLang === 'en' ? 'Was the down payment completed?' : 'هل تم استكمال قيمة المقدم؟'}</span> 
                <span class="text-amber-400 font-bold">*</span>
              </label>
              <div class="flex items-center gap-2 max-w-[220px]">
                <label class="custom-check-box active flex-1 rounded-xl py-2 px-3 flex items-center justify-center gap-1.5 cursor-pointer text-xs font-bold text-white transition-all text-center extra-box-yes" id="extraBoxYes_${cNum}">
                  <input type="radio" name="extra_paid_5_percent_${cNum}" value="نعم" checked class="hidden" onchange="toggleExtraAdvancePaidBox(${cNum}, this)">
                  <span>✓</span> <span>${currentLang === 'en' ? 'Yes' : 'نعم'}</span>
                </label>
                <label class="custom-check-box flex-1 rounded-xl py-2 px-3 flex items-center justify-center gap-1.5 cursor-pointer text-xs font-bold text-white transition-all text-center extra-box-no" id="extraBoxNo_${cNum}">
                  <input type="radio" name="extra_paid_5_percent_${cNum}" value="لا" class="hidden" onchange="toggleExtraAdvancePaidBox(${cNum}, this)">
                  <span>✕</span> <span>${currentLang === 'en' ? 'No' : 'لا'}</span>
                </label>
              </div>
            </div>

            <!-- حقل تاريخ الدفع -->
            <div id="extraPaymentDateContainer_${cNum}" class="space-y-1.5 sm:w-60 w-full transition-all">
              <label class="block text-xs font-bold text-slate-300" for="extraPaymentDate_${cNum}">
                <span class="text-amber-300 font-bold">📅 ${currentLang === 'en' ? 'Payment Date:' : 'تاريخ الدفع:'}</span>
                <span class="text-amber-400 font-bold">*</span>
              </label>
              <input
                type="date"
                id="extraPaymentDate_${cNum}"
                name="extra_payment_dates[]"
                class="glass-input w-full px-3.5 py-2 rounded-xl text-white text-xs sm:text-sm font-mono border-amber-500/40 focus:border-amber-400"
                value="<?= date('Y-m-d') ?>"
              />
              <p class="error-msg text-red-400 text-xs mt-1 hidden extra-payment-date-error">${currentLang === 'en' ? 'Payment date is required' : 'تاريخ الدفع مطلوب'}</p>
            </div>
          </div>
        </div>

        <!-- رسالة التوقف للوحدة الإضافية عند اختيار "لا" -->
        <div id="extraAdvanceNoticeNo_${cNum}" class="hidden p-3 rounded-2xl bg-amber-500/10 border border-amber-500/40 text-center space-y-1.5 my-2 animate-pulse">
          <div class="text-xl">⚠️</div>
          <p class="text-xs text-amber-300 font-bold">${currentLang === 'en' ? 'Down payment is required to register this unit' : 'استكمال سداد المقدم مطلوب لحجز هذه الوحدة'}</p>
          <p class="text-[11px] text-slate-300">${currentLang === 'en' ? 'Cannot proceed with registering this unit without completing down payment.' : 'لا يمكن تسجيل هذه الوحدة وحجزها بدون استكمال سداد المقدم.'}</p>
        </div>

        <div id="extraUnitDetailsContainer_${cNum}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 transition-all">
          <!-- كود الوحدة الإضافية (مطابق تماماً للوحدة 1) -->
          <div class="sm:col-span-12 form-group">
            <label class="block text-xs font-bold text-slate-300 mb-1.5">
              <span class="text-amber-300 font-bold">${labelUnitCode}</span>
              <span class="text-amber-400 font-bold">*</span>
            </label>
            <div class="relative unit-picker" id="${pickerId}">
              <input type="hidden" name="extra_unit_codes[]" value="" />
              <button
                type="button"
                class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-slate-300 text-xs sm:text-sm font-mono font-bold tracking-wider flex items-center justify-between border-amber-500/40 hover:border-amber-400 focus:border-amber-400 transition-all cursor-pointer select-none bg-slate-900/80"
                onclick="toggleUnitDropdown('${pickerId}')"
              >
                <span class="truncate font-extrabold text-amber-300 tracking-widest uppercase selected-text">
                  <span class="text-slate-500 font-sans font-normal tracking-normal text-xs">${phSelectUnit}</span>
                </span>
                <span class="text-amber-400 text-xs transition-transform duration-200 chevron-icon mr-2">▼</span>
              </button>

              <div class="unit-dropdown hidden absolute top-full mt-1.5 inset-x-0 z-50 bg-[#0e1726] border border-amber-500/40 rounded-2xl shadow-[0_12px_40px_rgba(0,0,0,0.85)] p-2.5 backdrop-blur-xl">
                <div class="relative mb-2">
                  <input
                    type="text"
                    placeholder="${phSearchUnit}"
                    dir="ltr"
                    autocomplete="off"
                    class="w-full pl-8 pr-3 py-2 rounded-xl bg-slate-900/90 border border-slate-700/80 text-amber-300 placeholder-slate-500 text-xs font-mono font-bold uppercase focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400/30 transition-all"
                    oninput="filterUnitsList('${pickerId}', this.value)"
                  />
                  <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">🔍</span>
                </div>
                <div class="unit-scroll max-h-56 overflow-y-auto space-y-1 pr-1 unit-items-list"></div>
              </div>
            </div>
            <p class="error-msg text-red-400 text-xs mt-1 hidden extra-unit-error">${currentLang === 'en' ? 'Unit code is required' : 'كود الوحدة مطلوب'}</p>
          </div>

          <!-- فترة السداد للوحدة الإضافية (مطابقة تماماً للوحدة 1) -->
          <div class="sm:col-span-6 form-group">
            <label class="block text-xs font-bold text-slate-300 mb-1.5" for="extraPeriod_${cNum}">
              <span class="text-amber-300 font-bold">${labelPeriod}</span>
              <span class="text-amber-400 font-bold">*</span>
            </label>
            <select 
              name="extra_payment_periods[]" 
              id="extraPeriod_${cNum}" 
              class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-amber-300 text-xs sm:text-sm font-bold border-amber-500/30 focus:border-amber-400 bg-slate-900/80 cursor-pointer" 
              onchange="handleExtraPeriodChange(${cNum}, this)"
            >
              <option value="">${currentLang === 'en' ? 'Select Payment Term...' : 'اختر فترة السداد...'}</option>
              <option value="7 Years">7 Years</option>
              <option value="8 Years">8 Years</option>
              <option value="9 Years">9 Years</option>
              <option value="10 Years">10 Years</option>
            </select>
            <p class="error-msg text-red-400 text-xs mt-1 hidden extra-period-error">${currentLang === 'en' ? 'Payment term is required' : 'فترة السداد مطلوبة'}</p>
          </div>

          <!-- حقل النسبة للوحدة الإضافية (يظهر عند اختيار الفترة، ويعرض النسبة فوراً حتى لو وحيدة) -->
          <div class="sm:col-span-6 form-group hidden" id="extraRateContainer_${cNum}">
            <label class="block text-xs font-bold text-slate-300 mb-1.5" for="extraRateSelect_${cNum}">
              <span class="text-amber-300 font-bold">${labelRate}</span>
              <span class="text-amber-400 font-bold">*</span>
            </label>
            <select 
              name="extra_final_rates[]" 
              id="extraRateSelect_${cNum}" 
              class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-amber-300 text-xs sm:text-sm font-bold border-amber-500/30 focus:border-amber-400 bg-slate-900/80 cursor-pointer"
              onchange="handleExtraRateChange(${cNum}, this)"
            >
              <option value="">${currentLang === 'en' ? 'Select Rate...' : 'اختر النسبة...'}</option>
            </select>
            <p class="error-msg text-red-400 text-xs mt-1 hidden extra-rate-error">${currentLang === 'en' ? 'Please select a rate' : 'يرجى اختيار النسبة'}</p>
          </div>

          <!-- مقترح نظام السداد للوحدة الإضافية (مطابق تماماً للوحدة 1) -->
          <div class="sm:col-span-12 form-group pt-2">
            <label class="block text-xs font-bold text-slate-300 mb-1.5" for="extraNotes_${cNum}">
              <span class="text-amber-300 font-bold">${labelProposal}</span>
              <span class="text-slate-400 text-[10px] font-normal">${optOptional}</span>
            </label>
            <textarea
              id="extraNotes_${cNum}"
              name="extra_notes[]"
              rows="2"
              placeholder="${phProposal}"
              class="glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-white placeholder-slate-500 text-xs sm:text-sm focus:border-amber-400 transition-all resize-y"
            ></textarea>
          </div>
        </div>
      `;

      container.appendChild(div);
      setTimeout(() => {
        div.classList.remove('opacity-0', 'translate-y-2');
      }, 20);
    }

    function removeExtraUnit(btn) {
      const block = btn.closest('.unit-block');
      if (block) {
        block.classList.add('opacity-0', '-translate-y-2');
        setTimeout(() => block.remove(), 250);
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
      ],
      "خارج جمهورية مصر العربية / أخرى": [
        "مدينة / منطقة بالخارج"
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
      "الوادي الجديد": "New Valley",
      "خارج جمهورية مصر العربية / أخرى": "Outside Egypt / Other"
    };

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
      card.className = "client-card p-4 sm:p-6 rounded-2xl sm:rounded-3xl bg-slate-900/60 border border-amber-500/30 space-y-5 relative shadow-xl transition-all duration-300";
      card.id = `clientCard_${clientId}`;
      card.dataset.clientId = clientId;

      const cardTitle = isPrimary 
        ? (currentLang === 'en' ? 'Client / First Party (Main)' : 'بيانات العميل / الطرف الأول (الأساسي)')
        : (currentLang === 'en' ? `Partner / Contract Party (${index + 1})` : `بيانات الشريك / الطرف (${index + 1})`);

      const cardBadge = isPrimary
        ? `<span class="px-2.5 py-0.5 rounded-lg bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-[11px] font-bold">${currentLang === 'en' ? 'Primary Party' : 'الطرف الأساسي'}</span>`
        : `<span class="px-2.5 py-0.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/40 text-[11px] font-bold">${currentLang === 'en' ? 'Contract Partner' : 'شريك بالعقد'}</span>`;

      const deleteBtn = isPrimary ? '' : `
        <button 
          type="button" 
          onclick="removeClientCard(${clientId})" 
          class="text-xs font-bold text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/25 border border-red-500/30 px-3 py-1.5 rounded-xl flex items-center gap-1.5 transition-all cursor-pointer shadow-sm"
          title="${currentLang === 'en' ? 'Remove this partner' : 'حذف هذا الشريك'}"
        >
          <span>🗑️</span>
          <span>${currentLang === 'en' ? 'Remove Partner' : 'حذف هذا الشريك'}</span>
        </button>
      `;

      card.innerHTML = `
        <!-- رأس بطاقة العميل / الشريك -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
          <div class="flex items-center gap-2.5">
            <span class="text-lg">👤</span>
            <h4 class="card-title text-amber-300 font-extrabold text-sm sm:text-base">${cardTitle}</h4>
            ${cardBadge}
          </div>
          ${deleteBtn}
        </div>

        <!-- 1. اسم العميل كما هو في البطاقة الشخصية -->
        <div class="form-group w-full">
          <label class="block text-xs font-bold text-slate-300 mb-1.5">
            <span>${currentLang === 'en' ? 'Client Name as in ID' : 'اسم العميل كما هو في البطاقة الشخصية'}</span>
            <span class="text-red-400">*</span>
          </label>
          <input
            type="text"
            placeholder="${currentLang === 'en' ? '(Full name matching National ID or Passport)' : '(الاسم كاملاً ومطابقاً للهوية الشخصية)'}"
            class="client-name-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-white placeholder-slate-500 text-xs sm:text-sm font-semibold"
            oninput="validateField(this, val => val.length >= 3)"
          />
          <p class="error-msg text-red-400 text-xs mt-1 hidden">${currentLang === 'en' ? 'Client name as in ID is required' : 'اسم العميل كما هو في البطاقة الشخصية مطلوب'}</p>
        </div>

        <!-- 2. أرقام الهواتف والتواصل (Dynamic Array / List) -->
        <div class="space-y-2.5 pt-2 border-t border-slate-800/80">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-slate-300">
              <span>${currentLang === 'en' ? 'Phone & Contact Number (Local & International)' : 'أرقام الهواتف والتواصل (يدعم الأرقام المحلية والدولية)'}</span>
              <span class="text-red-400">*</span>
            </label>
          </div>
          <div id="phonesList_${clientId}" class="space-y-2.5"></div>
          <button 
            type="button" 
            onclick="addPhoneRow(${clientId})" 
            class="text-xs font-bold text-amber-300 hover:text-amber-200 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 px-3.5 py-2 rounded-xl flex items-center gap-1.5 transition-all cursor-pointer shadow-sm w-full sm:w-auto justify-center"
          >
            <span>📞</span>
            <span>${currentLang === 'en' ? '+ Add Additional Phone Number' : '+ إضافة رقم هاتف إضافي'}</span>
          </button>
        </div>

        <!-- 3. البريد الإلكتروني للمراسلات الرسمية (Dynamic Array / List) -->
        <div class="space-y-2.5 pt-2 border-t border-slate-800/80">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-slate-300">
              <span>${currentLang === 'en' ? 'Official Correspondence Email' : 'البريد الإلكتروني (متاح للتواصل والمراسلات الرسمية)'}</span>
              <span class="text-red-400">*</span>
            </label>
          </div>
          <div id="emailsList_${clientId}" class="space-y-2.5"></div>
          <button 
            type="button" 
            onclick="addEmailRow(${clientId})" 
            class="text-xs font-bold text-cyan-300 hover:text-cyan-200 bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 px-3.5 py-2 rounded-xl flex items-center gap-1.5 transition-all cursor-pointer shadow-sm w-full sm:w-auto justify-center"
          >
            <span>✉️</span>
            <span>${currentLang === 'en' ? '+ Add Additional Email' : '+ إضافة بريد إلكتروني إضافي'}</span>
          </button>
        </div>

        <!-- 4. إثبات الهوية والرقم القومي / جواز السفر -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 pt-2 border-t border-slate-800/80">
          <div class="sm:col-span-5">
            <label class="block text-xs font-bold text-slate-300 mb-1.5">${currentLang === 'en' ? 'ID Document Type' : 'نوع الوثيقة'}</label>
            <select onchange="handleClientIdTypeChange(${clientId}, this.value)" class="client-id-type glass-input w-full px-3 py-2.5 sm:py-3 rounded-xl text-white text-xs font-bold">
              <option value="national_id">${currentLang === 'en' ? 'National ID (14 digits)' : 'بطاقة الرقم القومي (14 رقم)'}</option>
              <option value="passport">${currentLang === 'en' ? 'Passport' : 'جواز السفر (Passport)'}</option>
            </select>
          </div>
          <div class="sm:col-span-7 form-group">
            <label class="block text-xs font-bold text-slate-300 mb-1.5">
              <span>${currentLang === 'en' ? 'National ID or Passport Number' : 'رقم بطاقة الرقم القومي أو جواز السفر'}</span>
              <span class="text-amber-400 font-bold">*</span>
            </label>
            <input
              type="text"
              maxlength="14"
              placeholder="${currentLang === 'en' ? 'Enter exactly 14 digits' : 'أدخل 14 رقماً بالضبط للبطاقة الشخصية'}"
              class="client-id-number glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-white placeholder-slate-500 text-xs sm:text-sm font-mono tracking-wider"
              oninput="validateClientIdNumber(this, ${clientId})"
            />
            <p class="error-msg text-red-400 text-xs mt-1 hidden client-id-error"></p>
          </div>
        </div>

        <!-- 5. مكان الإقامة الحالي (داخل مصر / خارج مصر) -->
        <div class="form-group pt-2 border-t border-slate-800/80">
          <label class="block text-xs font-bold text-amber-300 mb-2">
            <span>${currentLang === 'en' ? 'Current Residence Place:' : 'مكان الإقامة الحالي:'}</span>
            <span class="text-amber-400 font-bold">*</span>
          </label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
            <label class="residence-box-egypt custom-check-box active rounded-2xl py-3 px-3 sm:px-4 flex items-center justify-center gap-2 cursor-pointer text-xs sm:text-sm font-bold text-white shadow-sm" onclick="setClientResidence(${clientId}, 'داخل جمهورية مصر العربية')">
              <input type="radio" name="client_residence_${clientId}" value="داخل جمهورية مصر العربية" checked class="hidden">
              <span class="text-base">🇪🇬</span>
              <span>${currentLang === 'en' ? 'Inside Arab Republic of Egypt' : 'داخل جمهورية مصر العربية'}</span>
            </label>
            <label class="residence-box-abroad custom-check-box rounded-2xl py-3 px-3 sm:px-4 flex items-center justify-center gap-2 cursor-pointer text-xs sm:text-sm font-bold text-white shadow-sm" onclick="setClientResidence(${clientId}, 'خارج جمهورية مصر العربية')">
              <input type="radio" name="client_residence_${clientId}" value="خارج جمهورية مصر العربية" class="hidden">
              <span class="text-base">🌍</span>
              <span>${currentLang === 'en' ? 'Outside Arab Republic of Egypt' : 'خارج جمهورية مصر العربية'}</span>
            </label>
          </div>
        </div>

        <!-- 6. العناوين (المحافظة والمدينة وباقي تفاصيل العنوان) -->
        <div class="space-y-4 pt-3 border-t border-slate-800/80">
          
          <!-- العنوان كما هو في إثبات الهوية الشخصية -->
          <div class="client-id-address-group space-y-2.5">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-bold text-slate-300">
                <span>📍 ${currentLang === 'en' ? 'Address as registered in Personal ID' : 'العنوان كما هو مسجل في إثبات الهوية الشخصية'}</span>
                <span class="text-amber-400 font-bold">*</span>
              </label>
              <span class="text-[10px] text-amber-300/80 font-mono">${currentLang === 'en' ? 'Select Gov & City + Street details' : 'اختر المحافظة والمدينة واكتب باقي التفاصيل'}</span>
            </div>

            <!-- صف الاختيار: المحافظة والمدينة -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
              <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1">
                  ${currentLang === 'en' ? 'Governorate' : 'المحافظة'} <span class="text-amber-400">*</span>
                </label>
                <select
                  class="client-gov-select glass-input w-full px-3 py-2.5 sm:py-3 rounded-xl text-white bg-slate-900 border border-slate-700/80 focus:border-amber-400 focus:outline-none text-xs sm:text-sm font-semibold cursor-pointer"
                  onchange="handleClientGovChange(${clientId}, this.value, 'id')"
                >
                  <option value="">${currentLang === 'en' ? 'Select Governorate...' : 'اختر المحافظة...'}</option>
                </select>
              </div>

              <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1">
                  ${currentLang === 'en' ? 'City / District / Area' : 'المدينة / الحي / المركز'} <span class="text-amber-400">*</span>
                </label>
                <select
                  class="client-city-select glass-input w-full px-3 py-2.5 sm:py-3 rounded-xl text-white bg-slate-900 border border-slate-700/80 focus:border-amber-400 focus:outline-none text-xs sm:text-sm font-semibold cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                  disabled
                  onchange="handleClientCityChange(${clientId}, this.value, 'id')"
                >
                  <option value="">${currentLang === 'en' ? 'Select Governorate first...' : 'اختر المحافظة أولاً...'}</option>
                </select>
              </div>
            </div>

            <!-- حقل كتابة باقي تفاصيل العنوان (الشارع، رقم العقار، الشقة) -->
            <div>
              <label class="block text-[11px] font-semibold text-slate-400 mb-1">
                ${currentLang === 'en' ? 'Street, Building & Apt No. Details' : 'باقي تفاصيل العنوان (اسم الشارع، رقم العقار، رقم الشقة)'} <span class="text-amber-400">*</span>
              </label>
              <input
                type="text"
                placeholder="${currentLang === 'en' ? 'Street name, building number, apartment number, landmark...' : 'اسم الشارع، رقم العمارة، رقم الشقة، علامة مميزة...'}"
                class="client-street-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-white placeholder-slate-500 text-xs sm:text-sm font-semibold"
                oninput="handleClientStreetInput(${clientId}, 'id')"
              />
            </div>

            <!-- الحقل المدمج النهائي للعنوان بالهوية (hidden) -->
            <input type="hidden" class="client-id-address" value="" />
            <p class="error-msg client-id-address-error text-red-400 text-xs mt-1 hidden">${currentLang === 'en' ? 'Please select governorate, city and enter street address details' : 'يرجى اختيار المحافظة والمدينة وكتابة باقي تفاصيل العنوان'}</p>
          </div>

          <!-- عنوان المراسلات الرسمي -->
          <div class="client-corr-address-group space-y-2.5 pt-3 border-t border-slate-800/60">
            <div class="flex items-center justify-between flex-wrap gap-2">
              <label class="block text-xs font-bold text-slate-300">
                <span>📬 ${currentLang === 'en' ? 'Approved Correspondence Address in Detail' : 'العنوان المعتمد بالتفصيل للمراسلات'}</span>
                <span class="text-amber-400 font-bold">*</span>
              </label>
            </div>

            <!-- حاوية حقول المراسلات المخصصة (ظاهرة أوتوماتيكياً) -->
            <div class="client-custom-corr-container space-y-2.5 p-3 rounded-2xl bg-slate-950/40 border border-slate-800/80">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
                <div>
                  <label class="block text-[11px] font-semibold text-slate-400 mb-1">
                    ${currentLang === 'en' ? 'Governorate' : 'المحافظة'} <span class="text-amber-400">*</span>
                  </label>
                  <select
                    class="client-corr-gov-select glass-input w-full px-3 py-2.5 sm:py-3 rounded-xl text-white bg-slate-900 border border-slate-700/80 focus:border-amber-400 focus:outline-none text-xs sm:text-sm font-semibold cursor-pointer"
                    onchange="handleClientGovChange(${clientId}, this.value, 'corr')"
                  >
                    <option value="">${currentLang === 'en' ? 'Select Governorate...' : 'اختر المحافظة...'}</option>
                  </select>
                </div>

                <div>
                  <label class="block text-[11px] font-semibold text-slate-400 mb-1">
                    ${currentLang === 'en' ? 'City / District / Area' : 'المدينة / الحي / المركز'} <span class="text-amber-400">*</span>
                  </label>
                  <select
                    class="client-corr-city-select glass-input w-full px-3 py-2.5 sm:py-3 rounded-xl text-white bg-slate-900 border border-slate-700/80 focus:border-amber-400 focus:outline-none text-xs sm:text-sm font-semibold cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled
                    onchange="handleClientCityChange(${clientId}, this.value, 'corr')"
                  >
                    <option value="">${currentLang === 'en' ? 'Select Governorate first...' : 'اختر المحافظة أولاً...'}</option>
                  </select>
                </div>
              </div>

              <div>
                <label class="block text-[11px] font-semibold text-slate-400 mb-1">
                  ${currentLang === 'en' ? 'Street, Building & Apt No. Details' : 'باقي تفاصيل العنوان للمراسلات'} <span class="text-amber-400">*</span>
                </label>
                <input
                  type="text"
                  placeholder="${currentLang === 'en' ? 'Street name, building number, apartment number for official mail' : 'اسم الشارع، رقم المبنى والشقة للمراسلات الرسمية'}"
                  class="client-corr-street-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-white placeholder-slate-500 text-xs sm:text-sm font-semibold"
                  oninput="handleClientStreetInput(${clientId}, 'corr')"
                />
              </div>
            </div>

            <!-- الحقل المدمج النهائي لعنوان المراسلات (hidden) -->
            <input type="hidden" class="client-corr-address" value="" />
            <p class="error-msg client-corr-address-error text-red-400 text-xs mt-1 hidden">${currentLang === 'en' ? 'Approved correspondence address is required' : 'العنوان المعتمد للمراسلات مطلوب'}</p>
          </div>
        </div>

        <!-- 7. المستندات والمرفقات المطلوبة لهذا الطرف -->
        <div class="pt-3 border-t border-slate-800/80 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-amber-400 font-bold text-xs sm:text-sm">
              <span>📎</span>
              <span>${currentLang === 'en' ? 'Required Documents & Attachments' : 'المستندات والمرفقات المطلوبة'}</span>
              <span class="text-red-400">*</span>
            </div>
            <span class="text-[10px] text-slate-400 font-mono">PDF / JPG / PNG</span>
          </div>

          <div class="client-doc-notice p-3 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-200 text-xs font-semibold flex items-start gap-2">
            <span class="text-base flex-shrink-0">⚠️</span>
            <div class="client-doc-notice-text">
              ${currentLang === 'en' ? 'For clients residing inside Egypt: Uploading both sides of the National ID is mandatory.' : 'للعملاء المقيمين داخل مصر: يُشترط رفع صورة واضحة لبطاقة الرقم القومي (الوجهين: الأمامي والخلفي).'}
            </div>
          </div>

          <div 
            class="client-drop-zone border-2 border-dashed border-amber-500/35 hover:border-amber-400 rounded-2xl p-4 sm:p-5 text-center transition-all bg-slate-900/40 hover:bg-amber-500/5 cursor-pointer shadow-sm"
          >
            <input 
              type="file" 
              multiple 
              accept="image/*,.pdf" 
              class="hidden client-file-input" 
              onchange="handleClientFilesSelected(${clientId}, this.files)"
            >
            <div class="text-2xl mb-1 pointer-events-none">📁</div>
            <div class="font-bold text-xs sm:text-sm text-slate-200 pointer-events-none">
              ${currentLang === 'en' ? 'Click to select documents or drag & drop files here' : 'اضغط لاختيار المستندات أو اسحب الملفات وأفلتها هنا'}
            </div>
            <div class="text-[11px] text-slate-400 mt-1 client-drop-subtitle pointer-events-none">
              ${currentLang === 'en' ? '(Mandatory: National ID card both sides)' : '(مطلوب إجباري: صورة بطاقة الرقم القومي وجهين)'}
            </div>
          </div>

          <!-- قائمة الملفات المرفقة لهذا العميل -->
          <div class="client-files-container space-y-2 hidden">
            <div class="text-xs font-bold text-amber-300">${currentLang === 'en' ? 'Attached Files Preview:' : 'المعاينات المرفقة لهذا الطرف:'}</div>
            <div class="client-files-list grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
          </div>
          <p class="error-msg text-red-400 text-xs mt-1 hidden client-files-error">${currentLang === 'en' ? 'Required documents are mandatory. Please attach the required files.' : 'المستندات المطلوبة إلزامية، يرجى رفع الملفات المحددة أعلاه'}</p>
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
          class="p-2.5 sm:p-3 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 font-bold transition-colors cursor-pointer flex-shrink-0"
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
            class="country-btn glass-input w-full px-2 sm:px-2.5 py-2.5 sm:py-3 rounded-xl text-amber-300 text-xs font-bold flex items-center justify-between cursor-pointer hover:border-amber-400 transition-all"
          >
            <span class="country-display flex items-center gap-1 sm:gap-1.5 truncate">
              <span class="text-sm sm:text-base">🇪🇬</span>
              <span class="font-mono text-xs">+20</span>
            </span>
            <span class="text-[9px] text-slate-400">▼</span>
          </button>
          
          <input type="hidden" name="phone_codes[]" value="${initialCode}" class="country-code-val">

          <!-- Dropdown Menu -->
          <div class="country-dropdown hidden absolute top-full mt-1.5 start-0 z-50 w-72 max-w-[85vw] sm:w-80 bg-slate-900/98 border border-amber-500/40 rounded-2xl shadow-2xl p-2.5 backdrop-blur-2xl">
            <div class="relative mb-2">
              <input 
                type="text" 
                placeholder="${currentLang === 'en' ? 'Search country or dialing code...' : 'بحث عن الدولة أو الكود...'}" 
                oninput="filterCountryList(this)" 
                class="country-search-input w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-amber-400 font-medium"
              >
              <span class="absolute end-2.5 top-2.5 text-xs text-slate-400">🔍</span>
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
            class="phone-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-emerald-300 placeholder-slate-500 text-xs sm:text-sm font-mono tracking-wider text-left"
            oninput="handlePhoneInput(this)"
          />
          <p class="error-msg text-red-400 text-xs mt-1 hidden"></p>
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
          class="p-2.5 sm:p-3 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 font-bold transition-colors cursor-pointer flex-shrink-0"
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
            class="email-input glass-input w-full px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl text-cyan-300 placeholder-slate-500 text-xs sm:text-sm font-mono dir-ltr text-right"
            oninput="validateGeneralEmail(this)"
          />
          <p class="error-msg text-red-400 text-xs mt-1 hidden">${currentLang === 'en' ? 'Please provide a valid email (example@domain.com)' : 'يرجى إدخال بريد إلكتروني صحيح ومتاح للمراسلات الرسمية (example@domain.com)'}</p>
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

    function validateClientIdNumber(input, clientId) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return false;
      const type = card.querySelector('.client-id-type').value;
      const val = input.value.trim();
      const errEl = card.querySelector('.client-id-error');

      if (errEl) errEl.classList.add('hidden');
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
      return isValid;
    }

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

        const govSelect = card.querySelector('.client-gov-select');
        if (govSelect && (!govSelect.value || govSelect.value === 'القاهرة')) {
          govSelect.value = 'خارج جمهورية مصر العربية / أخرى';
          handleClientGovChange(clientId, 'خارج جمهورية مصر العربية / أخرى', 'id');
        }
      } else {
        if (noticeText) noticeText.innerText = (currentLang === 'en')
          ? 'For clients residing inside Egypt: Uploading both sides of the National ID is mandatory.'
          : 'للعملاء المقيمين داخل مصر: يُشترط رفع صورة واضحة لبطاقة الرقم القومي (الوجهين: الأمامي والخلفي).';
        if (dropSubtitle) dropSubtitle.innerText = (currentLang === 'en')
          ? '(Mandatory: National ID card both sides)'
          : '(مطلوب إجباري: صورة بطاقة الرقم القومي وجهين)';

        const govSelect = card.querySelector('.client-gov-select');
        if (govSelect && govSelect.value === 'خارج جمهورية مصر العربية / أخرى') {
          govSelect.value = '';
          handleClientGovChange(clientId, '', 'id');
        }
      }

      updateContractLocationVisibility();
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

    // ── Dynamic Address Picker (Governorate & City Dropdowns + Street Details) ──
    function initClientAddressFields(clientId) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;

      const govSelect = card.querySelector('.client-gov-select');
      const corrGovSelect = card.querySelector('.client-corr-gov-select');

      populateGovernorateOptions(govSelect);
      populateGovernorateOptions(corrGovSelect);
    }

    function populateGovernorateOptions(selectEl) {
      if (!selectEl) return;
      const currentVal = selectEl.value;
      selectEl.innerHTML = `<option value="">${currentLang === 'en' ? 'Select Governorate...' : 'اختر المحافظة...'}</option>`;

      Object.keys(egyptGovernoratesAndCities).forEach(gov => {
        const opt = document.createElement('option');
        opt.value = gov;
        opt.textContent = (currentLang === 'en' && egyptGovernoratesEn[gov]) ? egyptGovernoratesEn[gov] : gov;
        opt.className = "bg-slate-900 text-white";
        selectEl.appendChild(opt);
      });

      if (currentVal && egyptGovernoratesAndCities[currentVal]) {
        selectEl.value = currentVal;
      }
    }

    function handleClientGovChange(clientId, govVal, type) {
      const card = document.getElementById(`clientCard_${clientId}`);
      if (!card) return;

      const citySelect = (type === 'id') 
        ? card.querySelector('.client-city-select') 
        : card.querySelector('.client-corr-city-select');

      if (!citySelect) return;

      citySelect.innerHTML = '';

      if (!govVal) {
        citySelect.disabled = true;
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = (currentLang === 'en') ? 'Select Governorate first...' : 'اختر المحافظة أولاً...';
        opt.className = "bg-slate-900 text-white";
        citySelect.appendChild(opt);
      } else {
        citySelect.disabled = false;
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = (currentLang === 'en') ? 'Select City / District...' : 'اختر المدينة / الحي / المركز...';
        defaultOpt.className = "bg-slate-900 text-white";
        citySelect.appendChild(defaultOpt);

        const cities = egyptGovernoratesAndCities[govVal] || [];
        cities.forEach(city => {
          const opt = document.createElement('option');
          opt.value = city;
          opt.textContent = city;
          opt.className = "bg-slate-900 text-white";
          citySelect.appendChild(opt);
        });

        // خيار إضافي لأي حي أو مركز غير مدرج
        const otherOpt = document.createElement('option');
        otherOpt.value = (currentLang === 'en') ? 'Other district / area' : 'أخرى / منطقة أخرى';
        otherOpt.textContent = (currentLang === 'en') ? 'Other district / area' : 'أخرى / منطقة أخرى';
        otherOpt.className = "bg-slate-900 text-white";
        citySelect.appendChild(otherOpt);
      }

      updateCombinedAddress(clientId, type);
    }

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
        item.className = "flex items-center justify-between p-2.5 rounded-2xl bg-slate-900 border border-slate-800 text-xs shadow-md";

        const isImg = file.type.startsWith('image/');
        const previewUrl = isImg ? URL.createObjectURL(file) : null;

        item.innerHTML = `
          <div class="flex items-center gap-2.5 truncate">
            ${isImg 
              ? `<img src="${previewUrl}" class="w-10 h-10 object-cover rounded-xl border border-slate-700">`
              : `<div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-lg">📄</div>`
            }
            <div class="truncate">
              <div class="truncate font-mono font-bold text-white">${file.name}</div>
              <div class="text-[10px] text-slate-400 font-mono">${(file.size/1024).toFixed(1)} KB</div>
            </div>
          </div>
          <button type="button" onclick="removeClientFile(${clientId}, ${idx})" class="p-1.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 font-bold cursor-pointer transition-colors">
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
        const nameVal = nameInput ? nameInput.value.trim() : '';
        if (!nameVal || nameVal.length < 2) {
          nameInput.classList.add('invalid');
          nameInput.parentElement.querySelector('.error-msg')?.classList.remove('hidden');
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
          idAddrErr?.classList.remove('hidden');
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
          corrAddrErr?.classList.remove('hidden');
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

      // التحقق من بيانات الوحدة ونظام السداد (Section 02)
      const unitCode = document.getElementById('unitCode').value.trim();
      const unitPickerTrigger = document.getElementById('unitPickerTrigger');
      if (!unitCode) {
        document.getElementById('errUnitCode')?.classList.remove('hidden');
        if (unitPickerTrigger) {
          unitPickerTrigger.classList.add('invalid');
          unitPickerTrigger.classList.remove('valid');
        }
        hasError = true;
      } else {
        if (unitPickerTrigger) {
          unitPickerTrigger.classList.add('valid');
          unitPickerTrigger.classList.remove('invalid');
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
        const uHidden = blk.querySelector('input[name="extra_unit_codes[]"]');
        const trigger = blk.querySelector('.unit-picker button');
        const errUnit = blk.querySelector('.extra-unit-error');
        if (!uHidden || !uHidden.value.trim()) {
          if (errUnit) errUnit.classList.remove('hidden');
          if (trigger) trigger.classList.add('invalid');
          hasError = true;
        } else {
          if (errUnit) errUnit.classList.add('hidden');
          if (trigger) trigger.classList.remove('invalid');
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
