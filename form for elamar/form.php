<?php
/**
 * form.php - استمارة التعاقد الرسمية الفاخرة | مجموعة العمار
 * تدعم الروابط المخصصة لكل موظف مبيعات (Sales Tracking عبر slug)
 * دعم كامل للغتين العربية والإنجليزية مع تبديل لحظي عبر JavaScript
 */

require_once __DIR__ . '/db.php';

// استقبال slug الخاص بموظف المبيعات من الرابط
$slug = trim($_GET['slug'] ?? '');
$salesInfo = null;
$salesId = null;
$salesName = '';

if (!empty($slug)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, slug, active FROM sales_employees WHERE slug = :slug AND active = 1 LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $salesInfo = $stmt->fetch();
        
        if ($salesInfo) {
            $salesId = (int)$salesInfo['id'];
            $salesName = $salesInfo['name'];
        }
    } catch (PDOException $e) {
        // العرض كنموذج عام بدون ربط
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>استبيان واستيفاء بيانات التعاقد | مشاريع شورز الساحلية</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <!-- Canvas Confetti Library for celebration effect -->
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

    /* Eye-friendly comfortable luxury palette (neither pitch dark nor blinding white) */
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
      border-color: #d4af37;
      box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.25), 0 0 15px rgba(212, 175, 55, 0.15);
      background: rgba(16, 26, 42, 0.96);
    }
    .glass-input.valid {
      border-color: #10b981;
    }
    .glass-input.invalid {
      border-color: #ef4444;
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

    /* Pulse animation */
    @keyframes subtlePulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.85; transform: scale(0.995); }
    }
    .subtle-pulse {
      animation: subtlePulse 3s infinite ease-in-out;
    }
  </style>
</head>

<body class="min-h-screen bg-luxury-mesh text-slate-100 flex flex-col relative selection:bg-amber-500/30 selection:text-amber-200">

  <!-- Ambient Light Orbs -->
  <div class="fixed top-10 right-10 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="fixed bottom-10 left-10 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

  <!-- ═══════════════ HEADER ═══════════════ -->
  <header class="border-b border-amber-500/25 bg-[#121c2b]/90 backdrop-blur-xl sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-3">

      <!-- Logo + Brand -->
      <div class="flex items-center gap-3.5">
        <div class="p-2 rounded-2xl bg-gradient-to-br from-amber-500/20 via-amber-400/10 to-[#121c2b] border-2 border-amber-400 shadow-[0_0_20px_rgba(212,175,55,0.45)] flex items-center justify-center">
          <img src="logo.png" alt="Logo" class="h-10 sm:h-12 w-auto object-contain drop-shadow-[0_2px_8px_rgba(0,0,0,0.6)]">
        </div>
        <div>
          <div class="text-white font-black text-base sm:text-lg tracking-wide leading-tight" data-i18n="brandName">
            مجموعة العمار للتطوير العقاري
          </div>
          <div class="text-amber-400 text-[10px] sm:text-xs font-black tracking-widest uppercase drop-shadow-[0_1px_4px_rgba(212,175,55,0.25)]" data-i18n="brandSlogan">
            SHORES ONLY • الساحل الشمالي
          </div>
        </div>
      </div>

      <!-- Controls & Language Toggle -->
      <div class="flex items-center gap-2 sm:gap-3">
        
        <!-- Language Switcher Button -->
        <button 
          id="langToggleBtn" 
          type="button"
          onclick="toggleLanguage()" 
          class="px-3.5 py-1.5 rounded-xl bg-slate-900/80 hover:bg-slate-800 border border-amber-400/40 text-amber-300 text-xs sm:text-sm font-bold flex items-center gap-2 transition-all shadow-md cursor-pointer hover:border-amber-400"
          title="Switch Language / تبديل اللغة"
        >
          <span class="text-sm">🌐</span>
          <span id="langBtnText">English</span>
        </button>

        <!-- Admin Shortcut (Hidden unless authorized) -->
        <a id="adminDashboardBtn" href="view_clients.php" class="hidden items-center gap-1.5 text-amber-300 text-xs px-3 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500/30 hover:bg-amber-500/25 transition-all font-bold">
          🔒 <span data-i18n="adminLink">لوحة المسؤول</span>
        </a>
      </div>

    </div>
  </header>

  <!-- ═══════════════ MAIN CONTENT ═══════════════ -->
  <main class="flex-1 flex items-center justify-center px-3 sm:px-6 py-8 sm:py-14 relative z-10">
    <div class="w-full max-w-2xl">

      <!-- Header Banner & Sales Consultant VIP Tag -->
      <div class="text-center mb-6 sm:mb-8 space-y-3">
        
        <?php if ($salesInfo): ?>
          <!-- VIP Sales Badge -->
          <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-2xl bg-gradient-to-r from-amber-500/20 via-amber-400/15 to-amber-500/20 border-2 border-amber-400/60 text-amber-300 text-xs sm:text-sm font-black shadow-[0_0_20px_rgba(212,175,55,0.25)] subtle-pulse">
            <span class="text-base">💎</span>
            <span data-i18n="vipSalesIntro">استمارة معتمدة بإشراف المستشار العقاري:</span>
            <span class="text-white bg-amber-500/30 px-2.5 py-0.5 rounded-lg border border-amber-400/40 font-bold"><?= htmlspecialchars($salesName, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php else: ?>
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/15 border border-amber-400/50 text-amber-300 text-xs font-bold shadow-sm" data-i18n="officialSurveyTag">
            🏖️ استبيان واستيفاء بيانات عقود الشاليهات الساحلية (Shores Only)
          </div>
        <?php endif; ?>

        <h1 id="pageTitle" class="text-2xl sm:text-3xl lg:text-4xl font-black bg-gradient-to-r from-amber-200 via-amber-400 to-yellow-400 bg-clip-text text-transparent tracking-tight drop-shadow-[0_2px_12px_rgba(212,175,55,0.25)]" data-i18n="pageTitle">
          استمارة بيانات العقد والوحدة
        </h1>
        <p id="pageSubtitle" class="text-slate-300 text-xs sm:text-sm max-w-lg mx-auto font-medium" data-i18n="pageSubtitle">
          يرجى تسجيل وتدقيق بيانات العقد بدقة لضمان سرعة إتمام الإجراءات وتجهيز وثائق الملكية
        </p>

        <div class="h-0.5 w-32 bg-gradient-to-r from-transparent via-amber-400 to-transparent mx-auto pt-1"></div>
      </div>

      <!-- ═══════════════ FORM CARD ═══════════════ -->
      <div class="glass-card rounded-3xl shadow-2xl overflow-hidden relative">
        <div class="h-1.5 bg-gradient-to-r from-transparent via-amber-400 to-transparent"></div>

        <div class="p-5 sm:p-8 md:p-10">

          <form id="contractForm" novalidate class="space-y-7" enctype="multipart/form-data">
            
            <!-- Hidden Sales ID to lock the consultation attribution -->
            <input type="hidden" name="sales_id" value="<?= $salesId ? htmlspecialchars($salesId) : '' ?>">

            <!-- ── SECTION 1: أطراف العقد والشركاء ── -->
            <div class="section-card p-4 sm:p-5 rounded-2xl space-y-4">
              <div class="flex items-center gap-2.5 text-amber-400 font-black text-sm sm:text-base">
                <span class="px-2 py-0.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-400/40 text-xs font-mono font-bold shadow-sm">01</span>
                <span data-i18n="sec1Title">بيانات أطراف العقد والشركاء</span>
              </div>

              <div id="namesContainer" class="space-y-3">
                <div class="form-group">
                  <label class="block text-xs font-bold text-slate-300 mb-1.5" for="primaryName">
                    <span data-i18n="labelPrimaryName">اسم العميل كما هو مدون في العقد</span>
                    <span class="text-red-400">*</span>
                  </label>
                  <input
                    type="text"
                    id="primaryName"
                    name="names[]"
                    data-i18n-placeholder="phPrimaryName"
                    placeholder="اكتب الاسم كاملاً ومطابقاً للبطاقة أو العقد"
                    class="glass-input w-full px-4 py-3 rounded-xl text-white placeholder-slate-500 text-sm font-semibold"
                    oninput="validateField(this, val => val.length >= 3)"
                  />
                  <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errPrimaryName" data-i18n="errPrimaryName">اسم العميل كما هو مدون في العقد مطلوب</p>
                </div>
              </div>

              <!-- زر إضافة شريك -->
              <button 
                type="button" 
                onclick="addPartnerNameField()" 
                class="text-xs font-bold text-amber-300 hover:text-amber-200 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 px-3.5 py-2 rounded-xl flex items-center gap-2 transition-all cursor-pointer shadow-sm"
              >
                <span>➕</span>
                <span data-i18n="btnAddPartner">إضافة اسم شريك / طرف آخر بالعقد</span>
              </button>
            </div>

            <!-- ── SECTION 2: بيانات الاتصال والبريد ── -->
            <div class="section-card p-4 sm:p-5 rounded-2xl space-y-4">
              <div class="flex items-center gap-2.5 text-amber-400 font-black text-sm sm:text-base">
                <span class="px-2 py-0.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-400/40 text-xs font-mono font-bold shadow-sm">02</span>
                <span data-i18n="sec2Title">بيانات الاتصال والتواصل</span>
              </div>

              <!-- الهواتف -->
              <div id="phonesContainer" class="space-y-3">
                <div class="form-group">
                  <label class="block text-xs font-bold text-slate-300 mb-1.5" for="primaryPhone">
                    <span data-i18n="labelPrimaryPhone">رقم الهاتف الأساسي (يدعم واتساب)</span>
                    <span class="text-red-400">*</span>
                  </label>
                  <div class="flex gap-2 dir-ltr">
                    <select name="phone_codes[]" class="glass-input px-3 py-3 rounded-xl text-amber-300 text-xs font-bold w-28 text-center">
                      <option value="+20">🇪🇬 +20</option>
                      <option value="+966">🇸🇦 +966</option>
                      <option value="+971">🇦🇪 +971</option>
                      <option value="+965">🇰🇼 +965</option>
                      <option value="+974">🇶🇦 +974</option>
                      <option value="+1">🇺🇸 +1</option>
                      <option value="+44">🇬🇧 +44</option>
                    </select>
                    <input
                      type="tel"
                      id="primaryPhone"
                      name="phone_numbers[]"
                      placeholder="10xxxxxxxx"
                      class="glass-input flex-1 px-4 py-3 rounded-xl text-emerald-300 placeholder-slate-500 text-sm font-mono tracking-wider text-left"
                      oninput="validateField(this, val => val.replace(/\D/g,'').length >= 8)"
                    />
                  </div>
                  <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errPrimaryPhone" data-i18n="errPrimaryPhone">رقم الهاتف مطلوب وبصيغة صحيحة</p>
                </div>
              </div>

              <!-- زر إضافة هاتف إضافي -->
              <button 
                type="button" 
                onclick="addExtraPhoneField()" 
                class="text-xs font-bold text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 px-3.5 py-1.5 rounded-xl flex items-center gap-2 transition-all cursor-pointer"
              >
                <span>📞</span>
                <span data-i18n="btnAddPhone">+ إضافة رقم هاتف إضافي</span>
              </button>

              <!-- البريد الإلكتروني حصرياً Gmail -->
              <div class="form-group pt-2 border-t border-slate-800/80">
                <label class="block text-xs font-bold text-slate-300 mb-1.5" for="clientEmail">
                  <span data-i18n="labelEmail">البريد الإلكتروني (مخصص حصرياً لـ Gmail)</span>
                  <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <input
                    type="email"
                    id="clientEmail"
                    name="email"
                    placeholder="example@gmail.com"
                    class="glass-input w-full px-4 py-3 rounded-xl text-cyan-300 placeholder-slate-500 text-sm font-mono dir-ltr text-right"
                    oninput="validateGmail(this)"
                  />
                  <span class="absolute left-3.5 top-3.5 text-xs text-slate-500 font-mono">@gmail.com</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-1" id="hintEmail" data-i18n="hintEmail">
                  يجب أن ينتهي البريد بـ @gmail.com لضمان وصول الإشعارات الرسمية
                </p>
                <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errEmail" data-i18n="errEmail">يرجى إدخال بريد Gmail صحيح (name@gmail.com)</p>
              </div>

            </div>

            <!-- ── SECTION 3: إثبات الهوية والعنوان ── -->
            <div class="section-card p-4 sm:p-5 rounded-2xl space-y-4">
              <div class="flex items-center gap-2.5 text-amber-400 font-black text-sm sm:text-base">
                <span class="px-2 py-0.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-400/40 text-xs font-mono font-bold shadow-sm">03</span>
                <span data-i18n="sec3Title">إثبات الشخصية والعنوان الوطني</span>
              </div>

              <!-- نوع الهوية والرقم -->
              <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                <div class="sm:col-span-5">
                  <label class="block text-xs font-bold text-slate-300 mb-1.5" data-i18n="labelIdType">نوع الوثيقة</label>
                  <select name="id_type" id="idType" class="glass-input w-full px-3 py-3 rounded-xl text-white text-xs font-bold">
                    <option value="national_id" data-i18n="optNatId">بطاقة الرقم القومي (14 رقم)</option>
                    <option value="passport" data-i18n="optPassport">جواز السفر (Passport)</option>
                  </select>
                </div>
                <div class="sm:col-span-7 form-group">
                  <label class="block text-xs font-bold text-slate-300 mb-1.5" for="idNumber">
                    <span data-i18n="labelIdNumber">رقم بطاقة الرقم القومي أو جواز السفر</span>
                    <span class="text-amber-400 font-bold">*</span>
                  </label>
                  <input
                    type="text"
                    id="idNumber"
                    name="id_number"
                    data-i18n-placeholder="phIdNumber"
                    placeholder="أدخل الرقم كما هو بالوثيقة"
                    class="glass-input w-full px-4 py-3 rounded-xl text-white placeholder-slate-500 text-sm font-mono tracking-wider"
                    oninput="validateField(this, val => val.length >= 6)"
                  />
                  <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errIdNumber" data-i18n="errIdNumber">رقم إثبات الهوية مطلوب</p>
                </div>
              </div>

              <!-- العنوان بالتفصيل -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-300 mb-1.5" for="primaryAddress">
                  <span data-i18n="labelAddress">العنوان الحالي المعتمد بالتفصيل</span>
                  <span class="text-amber-400 font-bold">*</span>
                </label>
                <textarea
                  id="primaryAddress"
                  name="addresses[]"
                  rows="2"
                  data-i18n-placeholder="phAddress"
                  placeholder="المحافظة، المدينة، اسم الشارع، رقم العقار والشقة"
                  class="glass-input w-full px-4 py-3 rounded-xl text-white placeholder-slate-500 text-sm font-semibold"
                  oninput="validateField(this, val => val.length >= 5)"
                ></textarea>
                <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errAddress" data-i18n="errAddress">العنوان بالتفصيل مطلوب</p>
              </div>

            </div>

            <!-- ── SECTION 4: بيانات الوحدة والتعاقد ── -->
            <div class="section-card p-4 sm:p-5 rounded-2xl space-y-4">
              <div class="flex items-center gap-2.5 text-amber-400 font-black text-sm sm:text-base">
                <span class="px-2 py-0.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-400/40 text-xs font-mono font-bold shadow-sm">04</span>
                <span data-i18n="sec4Title">بيانات الوحدة ونظام التعاقد</span>
              </div>

              <!-- كود الوحدة -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-300 mb-1.5" for="unitCode">
                  <span class="text-amber-300 font-bold" data-i18n="labelUnitCode">كود الوحدة (الرقم الكودي الخاص بالوحدة)</span>
                  <span class="text-amber-400 font-bold">*</span>
                </label>
                <input
                  type="text"
                  id="unitCode"
                  name="unit_code"
                  placeholder="مثال: nn-13 أو B2-104"
                  data-i18n-placeholder="phUnitCode"
                  class="glass-input w-full px-4 py-3 rounded-xl text-amber-300 placeholder-slate-500 text-sm font-mono font-extrabold tracking-widest text-center uppercase border-amber-500/40 focus:border-amber-400"
                  oninput="validateField(this, val => val.length >= 2)"
                />
                <p class="text-[11px] text-slate-400 mt-1" id="hintUnitCode" data-i18n="hintUnitCode">كود فريد يحدد وحدتك في المخطط العام</p>
                <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errUnitCode" data-i18n="errUnitCode">كود الوحدة مطلوب</p>
              </div>

              <!-- مكان التعاقد (قائمة منسدلة List كما طُلِب) -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-300 mb-1.5" for="contractLocation">
                  <span class="text-amber-300 font-bold" data-i18n="labelLocation">مكان التعاقد (الفرع المفضل لإنهاء الإجراءات)</span>
                  <span class="text-amber-400 font-bold">*</span>
                </label>
                <select 
                  name="contract_location" 
                  id="contractLocation" 
                  class="glass-input w-full px-4 py-3 rounded-xl text-amber-300 text-xs sm:text-sm font-bold border-amber-500/30 focus:border-amber-400" 
                  onchange="validateField(this, val => val !== '')"
                >
                  <option value="" data-i18n="optSelectLocation">اختر فرع التعاقد...</option>
                  <option value="القاهرة" selected data-i18n="branchCairo">🏛️ فرع القاهرة (المقر الرئيسي)</option>
                  <option value="الإسكندرية" data-i18n="branchAlex">🌊 فرع الإسكندرية (الفرع الساحلي)</option>
                </select>
                <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errLocation" data-i18n="errLocation">مكان التعاقد مطلوب</p>
              </div>

              <!-- فترة السداد (تحت مكان التعاقد مباشرة) -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-300 mb-1.5" for="paymentPeriod">
                  <span class="text-amber-300 font-bold" data-i18n="labelPeriod">فترة السداد المتفق عليها</span>
                  <span class="text-amber-400 font-bold">*</span>
                </label>
                <select 
                  name="payment_period" 
                  id="paymentPeriod" 
                  class="glass-input w-full px-4 py-3 rounded-xl text-amber-300 text-xs sm:text-sm font-bold border-amber-500/30 focus:border-amber-400" 
                  onchange="validateField(this, val => val !== '')"
                >
                  <option value="" data-i18n="optSelectPeriod">اختر فترة السداد...</option>
                  <option value="كاش" data-i18n="optCash">كاش (Cash)</option>
                  <option value="سنة واحدة" data-i18n="opt1Yr">سنة واحدة</option>
                  <option value="سنتان" data-i18n="opt2Yr">سنتان</option>
                  <option value="3 سنوات" data-i18n="opt3Yr">3 سنوات</option>
                  <option value="4 سنوات" data-i18n="opt4Yr">4 سنوات</option>
                  <option value="5 سنوات" data-i18n="opt5Yr">5 سنوات</option>
                  <option value="6 سنوات" data-i18n="opt6Yr">6 سنوات</option>
                  <option value="7 سنوات" data-i18n="opt7Yr">7 سنوات</option>
                </select>
                <p class="error-msg text-red-400 text-xs mt-1 hidden" id="errPeriod" data-i18n="errPeriod">فترة السداد مطلوبة</p>
              </div>

              <!-- هل تم سداد نسبة الـ 5%؟ (تحت فترة السداد مباشرة) -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-300 mb-1.5">
                  <span class="text-amber-300 font-bold" data-i18n="label5Percent">هل تم سداد نسبة الـ 5%؟</span> <span class="text-amber-400 font-bold">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                  <label class="custom-check-box active rounded-xl py-3 px-3 flex items-center justify-center gap-1.5 cursor-pointer text-xs font-bold text-white" id="boxYes">
                    <input type="radio" name="paid_5_percent" value="نعم" checked class="hidden" onchange="togglePaidBox(this)">
                    <span>✓</span> <span data-i18n="optYesPaid">نعم (تم السداد)</span>
                  </label>
                  <label class="custom-check-box rounded-xl py-3 px-3 flex items-center justify-center gap-1.5 cursor-pointer text-xs font-bold text-white" id="boxNo">
                    <input type="radio" name="paid_5_percent" value="لا" class="hidden" onchange="togglePaidBox(this)">
                    <span>✕</span> <span data-i18n="optNoPaid">لا (لم يتم)</span>
                  </label>
                </div>
              </div>

            </div>

            <!-- ── SECTION 5: المستندات والملاحظات ── -->
            <div class="section-card p-4 sm:p-5 rounded-2xl space-y-4">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5 text-amber-400 font-black text-sm sm:text-base">
                  <span class="px-2 py-0.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-400/40 text-xs font-mono font-bold shadow-sm">05</span>
                  <span data-i18n="sec5Title">المستندات والمرفقات (اختياري / موصى به)</span>
                </div>
                <span class="text-[11px] text-slate-400" data-i18n="fileTypesHint">PDF أو صور</span>
              </div>

              <!-- رفع المستندات (Upload Zone) -->
              <div 
                id="dropZone" 
                class="border-2 border-dashed border-amber-500/35 hover:border-amber-400 rounded-2xl p-6 text-center transition-all bg-slate-900/40 hover:bg-amber-500/5 cursor-pointer shadow-sm"
                onclick="document.getElementById('fileInput').click()"
              >
                <input 
                  type="file" 
                  id="fileInput" 
                  multiple 
                  accept="image/*,.pdf" 
                  class="hidden" 
                  onchange="handleFilesSelected(this.files)"
                >
                <div class="text-3xl mb-2">📁</div>
                <div class="font-bold text-xs sm:text-sm text-slate-200" data-i18n="dropTitle">
                  اضغط لاختيار المستندات أو اسحب الملفات وأفلتها هنا
                </div>
                <div class="text-[11px] text-slate-400 mt-1" data-i18n="dropSubtitle">
                  (صور البطاقة الشخصية، جواز السفر، إيصالات السداد، أو مستندات الشركاء)
                </div>
              </div>

              <!-- المعاينات الحية للملفات المرفوعة -->
              <div id="fileListContainer" class="space-y-2 hidden">
                <div class="text-xs font-bold text-amber-300" data-i18n="selectedFilesHeading">المعاينات المرفقة للإرسال:</div>
                <div id="fileItemsList" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
              </div>

              <!-- الملاحظات أو طلبات خاصة (تحت رفع المستندات مباشرة وبحد أقصى 250 حرف) -->
              <div class="form-group pt-4 border-t border-slate-800/80">
                <div class="flex items-center justify-between mb-1.5">
                  <label class="block text-xs font-bold text-slate-300" for="clientNotes">
                    <span data-i18n="labelNotes">ملاحظات أو طلبات خاصة (اختياري)</span>
                  </label>
                  <span id="notesCounter" class="text-[11px] font-mono font-bold text-slate-400 bg-slate-800/80 px-2 py-0.5 rounded-md border border-slate-700/60 transition-colors">
                    <span id="currentNotesChars">0</span> / 250 <span data-i18n="charLabel">حرف</span>
                  </span>
                </div>
                <textarea
                  id="clientNotes"
                  name="notes"
                  rows="3"
                  maxlength="250"
                  data-i18n-placeholder="phNotes"
                  placeholder="أي ملاحظات إضافية تود تسجيلها مع استمارة التعاقد (الحد الأقصى 250 حرف)..."
                  class="glass-input w-full px-4 py-3 rounded-xl text-white placeholder-slate-500 text-sm focus:border-amber-400 transition-all resize-y"
                  oninput="updateNotesCounter(this)"
                ></textarea>
                <div class="flex items-center justify-between mt-1 text-[11px] text-slate-400">
                  <span data-i18n="hintNotes">يمكنك كتابة تفاصيل محددة ترغب في توثيقها مع الطلب</span>
                  <span id="notesRemainingText" class="text-[10px] text-slate-400 font-mono">المتبقي: 250 حرف</span>
                </div>
              </div>

            </div>

            <!-- ── زر التأكيد والإرسال ── -->
            <div class="pt-2">
              <button
                type="submit"
                id="submitBtn"
                class="w-full py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black text-base shadow-2xl hover:shadow-amber-500/30 transition-all cursor-pointer flex items-center justify-center gap-2"
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
    <div class="w-16 h-16 border-4 border-amber-500/20 border-t-amber-400 rounded-full animate-spin mb-4"></div>
    <div class="font-black text-white text-base" data-i18n="loadingTitle">جارٍ حفظ وتأكيد بيانات العقد...</div>
    <div class="text-xs text-amber-400 mt-1 font-mono" data-i18n="loadingSub">يرجى الانتظار ثوانٍ معدودة</div>
  </div>

  <!-- ═══════════════ SUCCESS MODAL ═══════════════ -->
  <div id="successModal" class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md flex items-center justify-center p-4 hidden">
    <div class="glass-card max-w-md w-full p-6 sm:p-8 rounded-3xl border border-amber-500/40 text-center shadow-2xl relative">
      <div class="w-20 h-20 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-3xl mx-auto mb-4 text-emerald-400 animate-bounce">
        ✓
      </div>

      <h3 class="text-xl sm:text-2xl font-black text-white mb-2" data-i18n="successTitle">
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

  <!-- ═══════════════ SCRIPT LOGIC (BILINGUAL I18N + INTERACTIVITY) ═══════════════ -->
  <script>
    // ── BILINGUAL DICTIONARY ──
    const translations = {
      ar: {
        brandName: "مجموعة العمار للتطوير العقاري",
        brandSlogan: "SHORES ONLY • الساحل الشمالي",
        adminLink: "لوحة المسؤول",
        vipSalesIntro: "استمارة معتمدة بإشراف المستشار العقاري:",
        officialSurveyTag: "🏖️ استبيان واستيفاء بيانات عقود الشاليهات الساحلية (Shores Only)",
        pageTitle: "استمارة بيانات العقد والوحدة",
        pageSubtitle: "يرجى تسجيل وتدقيق بيانات العقد بدقة لضمان سرعة إتمام الإجراءات وتجهيز وثائق الملكية",
        sec1Title: "بيانات أطراف العقد والشركاء",
        labelPrimaryName: "اسم العميل كما هو مدون في العقد",
        phPrimaryName: "اكتب الاسم كاملاً ومطابقاً للبطاقة أو العقد",
        errPrimaryName: "اسم العميل كما هو مدون في العقد مطلوب",
        btnAddPartner: "إضافة اسم شريك / طرف آخر بالعقد",
        sec2Title: "بيانات الاتصال والتواصل",
        labelPrimaryPhone: "رقم الهاتف الأساسي (يدعم واتساب)",
        errPrimaryPhone: "رقم الهاتف مطلوب وبصيغة صحيحة",
        btnAddPhone: "+ إضافة رقم هاتف إضافي",
        labelEmail: "البريد الإلكتروني (مخصص حصرياً لـ Gmail)",
        hintEmail: "يجب أن ينتهي البريد بـ @gmail.com لضمان وصول الإشعارات الرسمية",
        errEmail: "يرجى إدخال بريد Gmail صحيح (name@gmail.com)",
        sec3Title: "إثبات الشخصية والعنوان الوطني",
        labelIdType: "نوع الوثيقة",
        optNatId: "بطاقة الرقم القومي (14 رقم)",
        optPassport: "جواز السفر (Passport)",
        labelIdNumber: "رقم بطاقة الرقم القومي أو جواز السفر",
        phIdNumber: "أدخل الرقم كما هو بالوثيقة",
        errIdNumber: "رقم إثبات الهوية مطلوب",
        labelAddress: "العنوان الحالي المعتمد بالتفصيل",
        phAddress: "المحافظة، المدينة، اسم الشارع، رقم العقار والشقة",
        errAddress: "العنوان بالتفصيل مطلوب",
        sec4Title: "بيانات الوحدة ونظام التعاقد",
        labelUnitCode: "كود الوحدة (الرقم الكودي الخاص بالوحدة)",
        phUnitCode: "مثال: nn-13 أو B2-104",
        hintUnitCode: "كود فريد يحدد وحدتك في المخطط العام",
        errUnitCode: "كود الوحدة مطلوب",
        labelLocation: "مكان التعاقد (الفرع المفضل لإنهاء الإجراءات)",
        optSelectLocation: "اختر فرع التعاقد...",
        branchCairo: "🏛️ فرع القاهرة (المقر الرئيسي)",
        branchAlex: "🌊 فرع الإسكندرية (الفرع الساحلي)",
        errLocation: "مكان التعاقد مطلوب",
        labelPeriod: "فترة السداد المتفق عليها",
        optSelectPeriod: "اختر فترة السداد...",
        optCash: "كاش (Cash)",
        opt1Yr: "سنة واحدة",
        opt2Yr: "سنتان",
        opt3Yr: "3 سنوات",
        opt4Yr: "4 سنوات",
        opt5Yr: "5 سنوات",
        opt6Yr: "6 سنوات",
        opt7Yr: "7 سنوات",
        errPeriod: "فترة السداد مطلوبة",
        label5Percent: "هل تم سداد نسبة الـ 5%؟",
        optYesPaid: "نعم (تم السداد)",
        optNoPaid: "لا (لم يتم)",
        sec5Title: "المستندات والمرفقات (اختياري / موصى به)",
        fileTypesHint: "PDF أو صور",
        dropTitle: "اضغط لاختيار المستندات أو اسحب الملفات وأفلتها هنا",
        dropSubtitle: "(صور البطاقة الشخصية، جواز السفر، إيصالات السداد، أو مستندات الشركاء)",
        selectedFilesHeading: "المعاينات المرفقة للإرسال:",
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
        btnAnotherForm: "حسناً، تسجيل استمارة أخرى",
        partnerPlaceholder: "اسم الشريك بالكامل كما هو بالعقد",
        labelNotes: "ملاحظات أو طلبات خاصة (اختياري)",
        phNotes: "أي ملاحظات إضافية تود تسجيلها مع استمارة التعاقد (الحد الأقصى 250 حرف)...",
        hintNotes: "يمكنك كتابة تفاصيل محددة ترغب في توثيقها مع الطلب",
        charLabel: "حرف"
      },
      en: {
        brandName: "Al Amar Real Estate Development",
        brandSlogan: "SHORES ONLY • NORTH COAST",
        adminLink: "Admin Dashboard",
        vipSalesIntro: "Certified Form Supervised by Property Consultant:",
        officialSurveyTag: "🏖️ Official Shores Coastal Units Survey & Contract Verification",
        pageTitle: "Contract & Unit Registration Form",
        pageSubtitle: "Please review and enter contract information accurately for formal processing & official registry",
        sec1Title: "Contract Parties & Partners",
        labelPrimaryName: "Client Name as in Contract",
        phPrimaryName: "Full client name exactly as in ID or contract",
        errPrimaryName: "Client name as in contract is required",
        btnAddPartner: "+ Add Partner Name to Contract",
        sec2Title: "Contact & Email Details",
        labelPrimaryPhone: "Primary Phone (WhatsApp Supported)",
        errPrimaryPhone: "Valid phone number is required",
        btnAddPhone: "+ Add Additional Phone Number",
        labelEmail: "Email Address (Gmail Exclusive)",
        hintEmail: "Must end with @gmail.com for official contract notifications",
        errEmail: "Please provide a valid Gmail address (@gmail.com)",
        sec3Title: "Identity Verification & Address",
        labelIdType: "ID Document Type",
        optNatId: "National ID (14 digits)",
        optPassport: "Passport",
        labelIdNumber: "National ID or Passport Number",
        phIdNumber: "Enter document number accurately",
        errIdNumber: "Identification document number is required",
        labelAddress: "Detailed Residential Address",
        phAddress: "Governorate, City, Street, Building & Apt No.",
        errAddress: "Detailed address is required",
        sec4Title: "Unit Specifications & Payment Plan",
        labelUnitCode: "Unit Code (e.g. nn-13)",
        phUnitCode: "e.g. nn-13 or B2-104",
        hintUnitCode: "Unique unit identifier in master plan",
        errUnitCode: "Unit code is required",
        labelLocation: "Contract Location Branch",
        optSelectLocation: "Select contract branch...",
        branchCairo: "🏛️ Cairo Branch (Head Office)",
        branchAlex: "🌊 Alexandria Branch (Coastal Branch)",
        errLocation: "Contract branch is required",
        labelPeriod: "Agreed Payment Plan",
        optSelectPeriod: "Select payment term...",
        optCash: "Cash",
        opt1Yr: "1 Year",
        opt2Yr: "2 Years",
        opt3Yr: "3 Years",
        opt4Yr: "4 Years",
        opt5Yr: "5 Years",
        opt6Yr: "6 Years",
        opt7Yr: "7 Years",
        errPeriod: "Payment plan selection is required",
        label5Percent: "Was the 5% Payment Completed?",
        optYesPaid: "Yes (Completed)",
        optNoPaid: "No (Pending)",
        sec5Title: "Attachments & Supporting Documents (Optional)",
        fileTypesHint: "PDF or Images",
        dropTitle: "Click to upload documents or drag & drop files here",
        dropSubtitle: "(National ID photos, passport, payment receipts, partner docs)",
        selectedFilesHeading: "Attached Files Preview:",
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
        btnAnotherForm: "Register Another Contract",
        partnerPlaceholder: "Full partner name as in contract",
        labelNotes: "Special Notes or Remarks (Optional)",
        phNotes: "Any additional notes you would like to include (Max 250 characters)...",
        hintNotes: "You can write specific requests or remarks regarding your contract",
        charLabel: "chars"
      }
    };

    let currentLang = localStorage.getItem('elamar_lang') || 'ar';
    let selectedFiles = [];

    // Apply saved language on initial load
    document.addEventListener('DOMContentLoaded', () => {
      setLanguage(currentLang);
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

      // Update button text
      document.getElementById('langBtnText').innerText = (lang === 'ar') ? 'English' : 'العربية';

      // Update all data-i18n elements
      document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (translations[lang] && translations[lang][key]) {
          el.innerText = translations[lang][key];
        }
      });

      // Update all placeholders
      document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (translations[lang] && translations[lang][key]) {
          el.setAttribute('placeholder', translations[lang][key]);
        }
      });

      const notesInput = document.getElementById('clientNotes');
      if (notesInput) {
        updateNotesCounter(notesInput);
      }
    }

    // Dynamic Live Counter for Notes
    function updateNotesCounter(textarea) {
      if (!textarea) return;
      const maxLen = 250;
      let len = textarea.value.length;
      if (len > maxLen) {
        textarea.value = textarea.value.substring(0, maxLen);
        len = maxLen;
      }
      const charEl = document.getElementById('currentNotesChars');
      const counterEl = document.getElementById('notesCounter');
      const remEl = document.getElementById('notesRemainingText');
      
      if (charEl) charEl.innerText = len;
      
      const rem = maxLen - len;
      const remPrefix = (currentLang === 'en') ? 'Remaining:' : 'المتبقي:';
      const charUnit = (currentLang === 'en') ? 'chars' : 'حرف';
      if (remEl) remEl.innerText = `${remPrefix} ${rem} ${charUnit}`;

      if (counterEl) {
        if (len >= maxLen) {
          counterEl.className = "text-[11px] font-mono font-bold text-red-400 bg-red-500/10 px-2 py-0.5 rounded-md border border-red-500/40 transition-colors";
        } else if (len >= maxLen - 25) {
          counterEl.className = "text-[11px] font-mono font-bold text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-md border border-amber-500/40 transition-colors";
        } else {
          counterEl.className = "text-[11px] font-mono font-bold text-slate-400 bg-slate-800/80 px-2 py-0.5 rounded-md border border-slate-700/60 transition-colors";
        }
      }
    }
    function addPartnerNameField() {
      const container = document.getElementById('namesContainer');
      const div = document.createElement('div');
      div.className = "flex gap-2 items-center transform transition-all duration-300 opacity-0 translate-y-2";
      div.innerHTML = `
        <input
          type="text"
          name="names[]"
          placeholder="${translations[currentLang].partnerPlaceholder}"
          class="glass-input flex-1 px-4 py-2.5 rounded-xl text-white placeholder-slate-500 text-sm font-semibold"
        />
        <button type="button" onclick="removeDynamicField(this)" class="p-2.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-xs cursor-pointer font-bold">
          ✕
        </button>
      `;
      container.appendChild(div);
      setTimeout(() => {
        div.classList.remove('opacity-0', 'translate-y-2');
      }, 20);
    }

    // Dynamic Extra Phone with smooth animation
    function addExtraPhoneField() {
      const container = document.getElementById('phonesContainer');
      const div = document.createElement('div');
      div.className = "flex gap-2 items-center dir-ltr transform transition-all duration-300 opacity-0 translate-y-2";
      div.innerHTML = `
        <select name="phone_codes[]" class="glass-input px-3 py-2.5 rounded-xl text-amber-300 text-xs font-bold w-28 text-center">
          <option value="+20">🇪🇬 +20</option>
          <option value="+966">🇸🇦 +966</option>
          <option value="+971">🇦🇪 +971</option>
          <option value="+965">🇰🇼 +965</option>
          <option value="+974">🇶🇦 +974</option>
          <option value="+1">🇺🇸 +1</option>
        </select>
        <input
          type="tel"
          name="phone_numbers[]"
          placeholder="10xxxxxxxx"
          class="glass-input flex-1 px-4 py-2.5 rounded-xl text-emerald-300 placeholder-slate-500 text-sm font-mono text-left"
        />
        <button type="button" onclick="removeDynamicField(this)" class="p-2.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-xs cursor-pointer font-bold">
          ✕
        </button>
      `;
      container.appendChild(div);
      setTimeout(() => {
        div.classList.remove('opacity-0', 'translate-y-2');
      }, 20);
    }

    function removeDynamicField(btn) {
      const parent = btn.parentElement;
      parent.classList.add('opacity-0', '-translate-y-2');
      setTimeout(() => parent.remove(), 250);
    }

    // Radio box interactive toggles
    function toggleLocBox(radio) {
      document.getElementById('locCairoBox').classList.toggle('active', radio.value === 'القاهرة');
      document.getElementById('locAlexBox').classList.toggle('active', radio.value === 'الإسكندرية');
    }

    function togglePaidBox(radio) {
      document.getElementById('boxYes').classList.toggle('active', radio.value === 'نعم');
      document.getElementById('boxNo').classList.toggle('active', radio.value === 'لا');
    }

    // Real-time Visual Input Validation
    function validateField(input, testFn) {
      const val = input.value.trim();
      const isValid = testFn(val);
      input.classList.toggle('valid', isValid);
      input.classList.toggle('invalid', !isValid && val.length > 0);
      return isValid;
    }

    function validateGmail(input) {
      const val = input.value.trim().toLowerCase();
      const isValid = /^[a-z0-9._%+-]+@gmail\.com$/i.test(val);
      input.classList.toggle('valid', isValid);
      input.classList.toggle('invalid', !isValid && val.length > 0);
      return isValid;
    }

    // Drag and drop files handling with LIVE THUMBNAILS
    const dropZone = document.getElementById('dropZone');
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
      handleFilesSelected(e.dataTransfer.files);
    });

    function handleFilesSelected(files) {
      Array.from(files).forEach(file => {
        if (file.size <= 25 * 1024 * 1024) {
          selectedFiles.push(file);
        } else {
          alert(`File ${file.name} exceeds max size (25MB)`);
        }
      });
      renderSelectedFiles();
    }

    function renderSelectedFiles() {
      const container = document.getElementById('fileListContainer');
      const list = document.getElementById('fileItemsList');
      if (selectedFiles.length === 0) {
        container.classList.add('hidden');
        list.innerHTML = '';
        return;
      }
      container.classList.remove('hidden');
      list.innerHTML = '';

      selectedFiles.forEach((file, idx) => {
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
          <button type="button" onclick="removeFile(${idx})" class="p-1.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 font-bold cursor-pointer transition-colors">
            ✕
          </button>
        `;
        list.appendChild(item);
      });
    }

    function removeFile(index) {
      selectedFiles.splice(index, 1);
      renderSelectedFiles();
    }

    // Submit Handling
    document.getElementById('contractForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      // Reset errors
      document.querySelectorAll('.error-msg').forEach(el => el.classList.add('hidden'));

      let hasError = false;

      // 1. Primary Name
      const primaryName = document.getElementById('primaryName').value.trim();
      if (!primaryName || primaryName.length < 2) {
        document.getElementById('errPrimaryName').classList.remove('hidden');
        hasError = true;
      }

      // 2. Primary Phone
      const primaryPhone = document.getElementById('primaryPhone').value.trim();
      if (!primaryPhone || primaryPhone.replace(/\D/g,'').length < 7) {
        document.getElementById('errPrimaryPhone').classList.remove('hidden');
        hasError = true;
      }

      // 3. Gmail validation
      const email = document.getElementById('clientEmail').value.trim().toLowerCase();
      const gmailRegex = /^[a-z0-9._%+-]+@gmail\.com$/i;
      if (!email || !gmailRegex.test(email)) {
        document.getElementById('errEmail').classList.remove('hidden');
        hasError = true;
      }

      // 4. ID Number
      const idNumber = document.getElementById('idNumber').value.trim();
      if (!idNumber) {
        document.getElementById('errIdNumber').classList.remove('hidden');
        hasError = true;
      }

      // 5. Address
      const address = document.getElementById('primaryAddress').value.trim();
      if (!address) {
        document.getElementById('errAddress').classList.remove('hidden');
        hasError = true;
      }

      // 6. Unit Code
      const unitCode = document.getElementById('unitCode').value.trim();
      if (!unitCode) {
        document.getElementById('errUnitCode').classList.remove('hidden');
        hasError = true;
      }

      // 6.1. Contract Location
      const location = document.getElementById('contractLocation').value;
      if (!location) {
        document.getElementById('errLocation').classList.remove('hidden');
        hasError = true;
      }

      // 7. Payment Period
      const period = document.getElementById('paymentPeriod').value;
      if (!period) {
        document.getElementById('errPeriod').classList.remove('hidden');
        hasError = true;
      }

      if (hasError) {
        const firstErr = document.querySelector('.error-msg:not(.hidden)');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }

      // Show Loading
      document.getElementById('loadingOverlay').classList.remove('hidden');

      const formData = new FormData(this);
      formData.append('lang', currentLang);

      // Append selected files
      selectedFiles.forEach(file => {
        formData.append('attachments[]', file, file.name);
      });

      try {
        const res = await fetch('submit.php', {
          method: 'POST',
          body: formData
        });

        const data = await res.json();
        document.getElementById('loadingOverlay').classList.add('hidden');

        if (data.status === 'success') {
          document.getElementById('modalUnitCode').innerText = unitCode;
          document.getElementById('successModal').classList.remove('hidden');

          // Trigger Confetti Celebration Blast!
          if (typeof confetti === 'function') {
            confetti({
              particleCount: 120,
              spread: 70,
              origin: { y: 0.6 }
            });
          }
        } else {
          if (data.field === 'unitCode' || data.field === 'eoiNumber') {
            document.getElementById('errUnitCode').innerText = data.message;
            document.getElementById('errUnitCode').classList.remove('hidden');
            document.getElementById('unitCode').focus();
          } else if (data.field === 'email') {
            document.getElementById('errEmail').innerText = data.message;
            document.getElementById('errEmail').classList.remove('hidden');
            document.getElementById('clientEmail').focus();
          } else {
            alert('Error: ' + (data.message || 'Error occurred while saving contract.'));
          }
        }
      } catch (err) {
        document.getElementById('loadingOverlay').classList.add('hidden');
        alert('Server connection error. Please ensure Apache & MySQL are running.');
      }
    });

    // Check admin session to show shortcut button if authorized
    fetch('check_auth.php')
      .then(res => res.json())
      .then(data => {
        if (data && data.is_admin === true) {
          const btn = document.getElementById('adminDashboardBtn');
          if (btn) {
            btn.classList.remove('hidden');
            btn.classList.add('flex');
          }
        }
      })
      .catch(() => {});
  </script>

</body>
</html>
