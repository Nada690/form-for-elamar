<?php
/**
 * sales_portal.php — بوابة السيلز والمديرين (صفحة مستقلة تماماً عن view_clients.php)
 *
 * - تسجيل دخول بالإيميل + الباسورد من جدول users الموجود (Admin / Manager / Sales)
 * - Sales: يرى عملاءه فقط (clients.sales_id = id)
 * - Manager: يرى عملاء السيلز التابعين له فقط (users.manager_id = id)
 * - Admin: يرى الكل + يضيف مديرين وسيلز ويحدد المدير ويعطل/يفعل ويغير الباسورد
 * - Session مستقل (elamar_portal) فلا يتداخل مع لوحة view_clients.php
 * - فلترة الصلاحيات في مكان واحد: customerScope()
 */

session_name('elamar_portal');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
    $_SESSION['portal_lang'] = $_GET['lang'];
}
$portalLang = $_SESSION['portal_lang'] ?? 'ar';
$isEn = ($portalLang === 'en');

require_once __DIR__ . '/db.php';

function pt(string $ar, string $en): string {
    global $isEn;
    return $isEn ? $en : $ar;
}

const PORTAL_PAGE_SIZE   = 25;
const PORTAL_MAX_FAILS   = 5;   // محاولات فاشلة
const PORTAL_LOCK_MINUTES = 15; // مدة الحظر

// ───────────────────────── Helpers ─────────────────────────
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
function redirectTo(string $qs = ''): void { header('Location: sales_portal.php' . $qs); exit; }
function splitPartnerValues($val): array {
    if (empty($val)) return [];
    $normalized = str_replace(["\r\n", "\r", "\n", '،'], ',', (string)$val);
    $items = array_map('trim', explode(',', $normalized));
    return array_values(array_filter($items, fn($x) => $x !== ''));
}

function getPartnerAddresses($c, $partnerIndex): array {
    $pNum = $partnerIndex + 1;
    $rawAddresses = (string)($c['addresses'] ?? '');
    
    $idAddr = '';
    $corrAddr = '';

    if ($rawAddresses !== '') {
        $parts = explode('---', $rawAddresses);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            if (strpos($part, "طرف {$pNum}") !== false || strpos($part, "شريك {$pNum}") !== false) {
                if (strpos($part, "الهوية") !== false || strpos($part, "هوية") !== false) {
                    $idAddr = trim(preg_replace('/^طرف\s*\d+[^:]*:\s*(العنوان في الهوية|عنوان الهوية)?:\s*/u', '', $part));
                } elseif (strpos($part, "المراسلات") !== false || strpos($part, "المراسلة") !== false) {
                    $corrAddr = trim(preg_replace('/^طرف\s*\d+[^:]*:\s*(عنوان المراسلات|عنوان المراسلة)?:\s*/u', '', $part));
                }
            }
        }
    }

    if (empty($idAddr)) {
        $splitId = preg_split('/[\n\r]+|\s*،\s*|\s*\.\s*/u', (string)($c['id_address'] ?? ''));
        $idAddr = trim($splitId[$partnerIndex] ?? ($splitId[0] ?? ($c['id_address'] ?? '—')));
    }
    if (empty($corrAddr)) {
        $splitCorr = preg_split('/[\n\r]+|\s*،\s*|\s*\.\s*/u', (string)($c['correspondence_address'] ?? ''));
        $corrAddr = trim($splitCorr[$partnerIndex] ?? ($splitCorr[0] ?? ($c['correspondence_address'] ?? '—')));
    }

    return ['id_address' => $idAddr ?: '—', 'corr_address' => $corrAddr ?: '—'];
}


function portalLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** يضيف عمود active وجدول محاولات الدخول لو مش موجودين (مرة واحدة لكل جلسة) */
function ensureSchema(PDO $pdo): void {
    if (!empty($_SESSION['schema_ok'])) return;
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'active'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER referral_code");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_time (email, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $_SESSION['schema_ok'] = true;
}

/** المستخدم الحالي يُقرأ من الداتا بيز في كل طلب (فالتعطيل وتغيير الدور يسري فوراً) */
function currentUser(PDO $pdo): ?array {
    if (empty($_SESSION['portal_uid'])) return null;
    $st = $pdo->prepare("SELECT id, name, email, role, manager_id, referral_code, active FROM users WHERE id = :id LIMIT 1");
    $st->execute([':id' => (int)$_SESSION['portal_uid']]);
    $u = $st->fetch();
    if (!$u || (int)$u['active'] !== 1) { portalLogout(); session_start(); return null; }
    return $u;
}

/**
 * ★ المكان الوحيد لقاعدة "مين يشوف مين" ★
 * الدور والـ id مأخوذين من الداتا بيز (مش من الطلب أبداً).
 * كل استعلام عن العملاء لازم يضيف هذا الشرط.
 */
function customerScope(array $u): array {
    switch ($u['role']) {
        case 'Admin':
            return ['1 = 1', []];
        case 'Manager':
            return ["c.sales_id IN (SELECT id FROM users WHERE manager_id = :scope_mgr AND role = 'Sales')",
                    [':scope_mgr' => (int)$u['id']]];
        case 'Sales':
            return ['c.sales_id = :scope_sales', [':scope_sales' => (int)$u['id']]];
    }
    return ['0 = 1', []];
}

function makeReferralCode(PDO $pdo, string $name, string $role): string {
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    if ($base === '') $base = ($role === 'Manager') ? 'mgr' : 'sales';
    $base = substr($base, 0, 20);
    $st = $pdo->prepare("SELECT 1 FROM users WHERE referral_code = :c LIMIT 1");
    do {
        $code = $base . '-' . bin2hex(random_bytes(3));
        $st->execute([':c' => $code]);
    } while ($st->fetchColumn());
    return $code;
}

function baseUrl(): string {
    global $isHttps;
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return ($isHttps ? 'https://' : 'http://') . $host . $dir;
}

try {
    ensureSchema($pdo);
} catch (PDOException $e) {
    error_log('Portal schema error: ' . $e->getMessage());
    http_response_code(500);
    die('تعذر تجهيز قاعدة البيانات.');
}

$user = currentUser($pdo);

// ───────────────────────── Actions (POST) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_check()) {
        flash('انتهت صلاحية الصفحة، حاول مرة أخرى.', 'err');
        redirectTo();
    }

    // ── تسجيل الدخول ──
    if ($action === 'login') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');
        $ip    = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM portal_login_attempts
                              WHERE email = :e AND ip = :ip
                              AND attempted_at > (NOW() - INTERVAL " . PORTAL_LOCK_MINUTES . " MINUTE)");
        $cnt->execute([':e' => $email, ':ip' => $ip]);

        if ((int)$cnt->fetchColumn() >= PORTAL_MAX_FAILS) {
            flash('محاولات كثيرة خاطئة. حاول مرة أخرى بعد ' . PORTAL_LOCK_MINUTES . ' دقيقة.', 'err');
            redirectTo();
        }

        $st = $pdo->prepare("SELECT id, password_hash, active FROM users WHERE email = :e LIMIT 1");
        $st->execute([':e' => $email]);
        $row = $st->fetch();

        if ($row && (int)$row['active'] === 1 && password_verify($pass, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['portal_uid'] = (int)$row['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $pdo->prepare("DELETE FROM portal_login_attempts WHERE email = :e AND ip = :ip")
                ->execute([':e' => $email, ':ip' => $ip]);
            redirectTo();
        }

        $pdo->prepare("INSERT INTO portal_login_attempts (email, ip) VALUES (:e, :ip)")
            ->execute([':e' => $email, ':ip' => $ip]);
        flash('البريد الإلكتروني أو كلمة المرور غير صحيحة.', 'err'); // رسالة عامة
        redirectTo();
    }

    if ($action === 'logout') {
        portalLogout();
        redirectTo();
    }

    if (!$user) redirectTo();

    // ── كل ما بعد ذلك للأدمن فقط ──
    if ($user['role'] !== 'Admin') {
        flash('غير مصرح لك بهذا الإجراء.', 'err');
        redirectTo();
    }

    try {
        if ($action === 'create_user') {
            $name  = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $pw    = (string)($_POST['password'] ?? '');
            $role  = (string)($_POST['role'] ?? '');
            $mgrId = (int)($_POST['manager_id'] ?? 0);
            $err = null;

            if ($name === '' || mb_strlen($name) > 255)             $err = 'يرجى إدخال اسم صحيح.';
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))      $err = 'يرجى إدخال بريد إلكتروني صالح.';
            elseif (strlen($pw) < 8)                                 $err = 'كلمة المرور يجب ألا تقل عن 8 أحرف.';
            elseif (!in_array($role, ['Manager', 'Sales'], true))    $err = 'الدور غير صالح.';

            if (!$err && $role === 'Sales') {
                if ($mgrId > 0) {
                    $chk = $pdo->prepare("SELECT 1 FROM users WHERE id = :id AND role = 'Manager' AND active = 1");
                    $chk->execute([':id' => $mgrId]);
                    if (!$chk->fetchColumn()) $err = 'المدير المختار غير موجود أو غير نشط.';
                } else {
                    $mgrId = null;
                }
            }
            if ($err) { flash($err, 'err'); redirectTo('?view=users'); }

            $pdo->prepare("INSERT INTO users (name, email, password_hash, role, manager_id, referral_code, active)
                           VALUES (:n, :e, :p, :r, :m, :c, 1)")
                ->execute([
                    ':n' => $name,
                    ':e' => $email,
                    ':p' => password_hash($pw, PASSWORD_BCRYPT),
                    ':r' => $role,
                    ':m' => ($role === 'Sales' && !empty($mgrId)) ? $mgrId : null,
                    ':c' => makeReferralCode($pdo, $name, $role),
                ]);
            flash("تم إنشاء الحساب ({$name}) بنجاح.");
            redirectTo('?view=users');
        }

        if ($action === 'toggle_active') {
            $id = (int)($_POST['user_id'] ?? 0);
            if ($id === (int)$user['id']) { flash('لا يمكنك تعطيل حسابك.', 'err'); redirectTo('?view=users'); }
            $pdo->prepare("UPDATE users SET active = 1 - active WHERE id = :id")->execute([':id' => $id]);
            flash('تم تحديث حالة الحساب.');
            redirectTo('?view=users');
        }

        if ($action === 'delete_user') {
            $id = (int)($_POST['user_id'] ?? 0);
            if ($id === (int)$user['id']) {
                flash('لا يمكنك حذف حسابك الحالي.', 'err');
                redirectTo('?view=users');
            }

            $uStmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = :id");
            $uStmt->execute([':id' => $id]);
            $targetUser = $uStmt->fetch();

            if (!$targetUser) {
                flash('المستخدم غير موجود.', 'err');
                redirectTo('?view=users');
            }

            $pdo->beginTransaction();
            // فك ارتباط السيلز التابعين له إذا كان مديراً
            $pdo->prepare("UPDATE users SET manager_id = NULL WHERE manager_id = :id")->execute([':id' => $id]);
            // فك ارتباط العملاء المسندين إليه مع الاحتفاظ ببياناتهم
            $pdo->prepare("UPDATE clients SET sales_id = NULL WHERE sales_id = :id")->execute([':id' => $id]);
            // حذف المستخدم
            $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
            $pdo->commit();

            flash('تم حذف المستخدم (' . $targetUser['name'] . ') بنجاح.');
            redirectTo('?view=users');
        }

        if ($action === 'reset_password') {
            $id = (int)($_POST['user_id'] ?? 0);
            $pw = (string)($_POST['new_password'] ?? '');
            if (strlen($pw) < 8) { flash('كلمة المرور يجب ألا تقل عن 8 أحرف.', 'err'); redirectTo('?view=users'); }
            $pdo->prepare("UPDATE users SET password_hash = :p WHERE id = :id")
                ->execute([':p' => password_hash($pw, PASSWORD_BCRYPT), ':id' => $id]);
            flash('تم تغيير كلمة المرور.');
            redirectTo('?view=users');
        }

        if ($action === 'assign_manager') {
            $sid = (int)($_POST['user_id'] ?? 0);
            $mid = (int)($_POST['manager_id'] ?? 0);
            $s = $pdo->prepare("SELECT 1 FROM users WHERE id = :id AND role = 'Sales'");
            $s->execute([':id' => $sid]);
            if (!$s->fetchColumn()) {
                flash('المستخدم غير موجود أو ليس سيلز.', 'err');
            } elseif ($mid > 0) {
                $m = $pdo->prepare("SELECT 1 FROM users WHERE id = :id AND role = 'Manager' AND active = 1");
                $m->execute([':id' => $mid]);
                if (!$m->fetchColumn()) {
                    flash('المدير المختار غير موجود أو غير نشط.', 'err');
                } else {
                    $pdo->prepare("UPDATE users SET manager_id = :m WHERE id = :id")->execute([':m' => $mid, ':id' => $sid]);
                    flash('تم تحديث المدير المسؤول.');
                }
            } else {
                $pdo->prepare("UPDATE users SET manager_id = NULL WHERE id = :id")->execute([':id' => $sid]);
                flash('تم فك الارتباط بالمدير (مباشر / الإدارة).');
            }
            redirectTo('?view=users');
        }
    } catch (PDOException $e) {
        error_log('Portal action error: ' . $e->getMessage());
        $dup = isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062;
        flash($dup ? 'هذا البريد الإلكتروني مسجل بالفعل.' : 'حدث خطأ أثناء الحفظ.', 'err');
        redirectTo('?view=users');
    }

    redirectTo();
}

// ───────────────────────── Flash ─────────────────────────
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ───────────────────────── HTML head ─────────────────────────
function pageHead(string $title): void { 
    global $portalLang, $isEn;
?>
<!DOCTYPE html>
<html lang="<?= $portalLang ?>" dir="<?= $isEn ? 'ltr' : 'rtl' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= h($title) ?> | <?= $isEn ? 'El Amar Group' : 'مجموعة العمار' ?></title>
  <link rel="icon" type="image/png" href="logo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
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
    html { font-family: <?= $isEn ? "'Inter', 'Plus Jakarta Sans', sans-serif" : "'Cairo', sans-serif" ?>; }
    .font-mono { font-family: 'Plus Jakarta Sans', monospace; }
    
    h1, h2, h3, h4, h5, h6, label, p, span, input, select, textarea, button, a, div, th, td {
      color: #000000 !important;
      font-weight: 700 !important;
    }
    label {
      color: #000000 !important;
      font-weight: 800 !important;
    }
    .glass { 
      background: rgba(255, 255, 255, 0.96) !important; 
      backdrop-filter: blur(28px) !important;
      -webkit-backdrop-filter: blur(28px) !important;
      border: 2px solid #7dd3fc !important;
      box-shadow: 0 20px 50px -10px rgba(2, 132, 199, 0.15), 0 8px 24px rgba(0, 0, 0, 0.04) !important;
    }
    input, select, textarea {
      background: #ffffff !important;
      border: 2px solid #7dd3fc !important;
      color: #000000 !important;
      font-weight: 700 !important;
    }
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: #0284c7 !important;
      box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.35) !important;
    }
  </style>
  <script>
    function toggleLanguage() {
      const current = '<?= $portalLang ?>';
      const next = (current === 'ar') ? 'en' : 'ar';
      localStorage.setItem('elamar_lang', next);
      const url = new URL(window.location.href);
      url.searchParams.set('lang', next);
      window.location.href = url.toString();
    }
    document.addEventListener('DOMContentLoaded', () => {
      const saved = localStorage.getItem('elamar_lang');
      if (saved && saved !== '<?= $portalLang ?>' && !window.location.search.includes('lang=')) {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', saved);
        window.location.replace(url.toString());
      }
    });
  </script>
</head>
<body class="min-h-screen text-slate-950 p-2 sm:p-3 lg:p-4 w-full overflow-x-hidden">
<?php }

function flashBox(?array $flash): void {
    if (!$flash) return;
    $cls = $flash['type'] === 'err' ? 'bg-red-500/15 border-red-500/40 text-red-200' : 'bg-emerald-500/15 border-emerald-500/40 text-emerald-200';
    echo '<div class="p-3 rounded-2xl border text-sm font-bold mb-4 ' . $cls . '">' . h($flash['msg']) . '</div>';
}

// ───────────────────────── صفحة تسجيل الدخول ─────────────────────────
if (!$user) {
    pageHead(pt('تسجيل الدخول', 'Sign In')); ?>
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="glass w-full max-w-md rounded-3xl p-7 shadow-2xl relative">
      <div class="absolute top-5 <?= $isEn ? 'right-5' : 'left-5' ?>">
        <button type="button" onclick="toggleLanguage()" class="px-3 py-1.5 rounded-xl bg-white hover:bg-sky-50 text-slate-950 text-xs font-black border-2 border-sky-300 shadow-xs cursor-pointer flex items-center gap-1">
          <span>🌐</span>
          <span><?= $isEn ? 'العربية' : 'English' ?></span>
        </button>
      </div>
      <div class="text-center mb-6">
        <img src="logo.png" alt="Logo" class="h-16 mx-auto mb-3 object-contain">
        <h1 class="text-xl font-black text-slate-950"><?= pt('بوابة المبيعات', 'Sales Portal') ?></h1>
        <p class="text-xs text-sky-950 font-black mt-1"><?= pt('دخول السيلز والمديرين', 'Sales & Management Access') ?></p>
      </div>
      <?php flashBox($flash); ?>
      <form method="POST" action="sales_portal.php" autocomplete="on" class="space-y-4">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5"><?= pt('البريد الإلكتروني', 'Email Address') ?></label>
          <input type="email" name="email" required autofocus dir="ltr"
                 class="w-full px-4 py-3 rounded-2xl bg-white border-2 border-sky-300 text-slate-950 text-sm focus:outline-none focus:border-sky-500 font-bold shadow-xs">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5"><?= pt('كلمة المرور', 'Password') ?></label>
          <input type="password" name="password" required dir="ltr"
                 class="w-full px-4 py-3 rounded-2xl bg-white border-2 border-sky-300 text-slate-950 text-sm focus:outline-none focus:border-sky-500 font-bold shadow-xs">
        </div>
        <button type="submit" class="w-full py-3 rounded-2xl bg-gradient-to-r from-sky-400 to-blue-500 text-slate-950 font-black text-sm hover:from-sky-500 hover:to-blue-600 border-2 border-sky-400 shadow-md cursor-pointer">
          <?= pt('دخول', 'Sign In') ?>
        </button>
      </form>
    </div>
  </div>
</body></html>
<?php
    exit;
}

// ───────────────────────── بعد تسجيل الدخول ─────────────────────────
$role    = $user['role'];
$isAdmin = ($role === 'Admin');
$view    = ($isAdmin && ($_GET['view'] ?? '') === 'users') ? 'users' : 'customers';
$roleLabel = ['Admin' => pt('أدمن', 'Admin'), 'Manager' => pt('مدير مبيعات', 'Sales Manager'), 'Sales' => pt('مستشار مبيعات', 'Sales Consultant')][$role] ?? $role;

[$scopeSql, $scopeParams] = customerScope($user);

// ───────────── تفاصيل عميل واحد (مع فحص النطاق = حماية IDOR) ─────────────
if (isset($_GET['customer'])) {
    $cid = (int)$_GET['customer'];
    $selectedPartner = (int)($_GET['partner'] ?? 0);
    $st = $pdo->prepare("SELECT c.*, u.name AS sales_name FROM clients c
                         LEFT JOIN users u ON u.id = c.sales_id
                         WHERE c.id = :cid AND ({$scopeSql}) LIMIT 1");
    $st->execute(array_merge([':cid' => $cid], $scopeParams));
    $c = $st->fetch();

    if (!$c) http_response_code(404);
    pageHead('تفاصيل العقد والوحدة #' . $cid);
    
    $cNames = splitPartnerValues((string)($c['name'] ?? ''));
    $partnerCount = max(count($cNames), 1);

    $attachments = json_decode($c['attachments'] ?? '[]', true);
    if (!is_array($attachments)) $attachments = [];

    $resCountry = $c['residence_country'] ?? 'داخل جمهورية مصر العربية';
    $isOutside = str_contains($resCountry, 'خارج');
    $isPaid = ($c['paid_5_percent'] === 'نعم');
?>
  <div class="max-w-5xl mx-auto p-4 sm:p-8 space-y-6">
    
    <!-- زر الرجوع وشريط العنوان العلوي -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <a 
        href="sales_portal.php" 
        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white text-slate-950 font-black text-sm border-2 border-sky-300 hover:bg-sky-50 shadow-sm transition-all hover:-translate-x-1"
      >
        <span>←</span>
        <span>الرجوع لجدول المبيعات</span>
      </a>

      <div class="flex items-center gap-2 flex-wrap">
        <!-- 8. تاريخ ووقت التسجيل -->
        <span class="inline-flex items-center gap-2 bg-white px-4 py-2 rounded-2xl border-2 border-sky-300 text-xs font-black font-mono text-slate-950 shadow-sm">
          <span>🕒</span>
          <span>تاريخ ووقت التسجيل:</span>
          <span class="text-sky-950"><?= !empty($c['created_at']) ? date('Y-m-d h:i A', strtotime($c['created_at'])) : '—' ?></span>
        </span>
        <?php if ($role === 'Admin'): ?>
          <a href="view_clients.php" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-2xl bg-slate-900 text-sky-300 hover:text-white text-xs font-black border border-slate-700 shadow-sm transition-colors">
            <span>🛡️</span>
            <span>لوحة الإدارة الكاملة ↗</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- البطاقة الرئيسية لتفاصيل العقد (مطابقة تماماً لبيانات جدول المبيعات) -->
    <div class="glass rounded-3xl p-5 sm:p-7 shadow-2xl space-y-6">
      
      <?php if (!$c): ?>
        <p class="text-center text-slate-400 font-bold py-10">العميل أو العقد غير موجود أو لا تملك صلاحية عرضه.</p>
      <?php else: ?>

        <!-- ترويسة تفاصيل العقد والوحدة -->
        <div class="flex items-center justify-between pb-4 border-b-2 border-sky-200 flex-wrap gap-3">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-sky-100 border-2 border-sky-300 flex items-center justify-center text-2xl shadow-inner">
              🏢
            </div>
            <div>
              <h1 class="text-lg sm:text-xl font-black text-slate-950 flex items-center gap-2 flex-wrap">
                <span>تفاصيل عقد الوحدة:</span>
                <span class="font-mono text-sky-900 bg-sky-100 px-3 py-0.5 rounded-xl border-2 border-sky-300 shadow-2xs"><?= h($c['eoi_number']) ?></span>
              </h1>
              <p class="text-xs text-slate-600 font-bold mt-1 flex items-center gap-2 flex-wrap">
                <span>المشروع: <span class="text-slate-900 font-black"><?= h($c['project'] ?: '—') ?></span></span>
                <span>• كود السجل: <span class="font-mono text-slate-900 font-black">#<?= (int)$c['id'] ?></span></span>
                <?php if ($role !== 'Sales'): ?>
                  <span>• مسؤول المبيعات (السيلز): <span class="text-sky-950 bg-sky-100 px-2.5 py-0.5 rounded-lg border border-sky-300 font-black"><?= h($c['sales_name'] ?? 'مباشر') ?></span></span>
                <?php endif; ?>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 flex-wrap">
            <?php if ($role !== 'Sales'): ?>
              <span class="px-3.5 py-1.5 rounded-xl bg-sky-100 text-sky-950 border-2 border-sky-300 text-xs font-black shadow-2xs inline-flex items-center gap-1.5">
                <span>👔</span>
                <span>السيلز المسؤول: <?= h($c['sales_name'] ?? 'مباشر') ?></span>
              </span>
            <?php endif; ?>
            <!-- شارة حالة السداد -->
            <?php if ($isPaid): ?>
              <span class="px-3.5 py-1.5 rounded-xl bg-emerald-100 text-emerald-950 border-2 border-emerald-400 text-xs font-black shadow-2xs inline-flex items-center gap-1.5">
                <span>✓</span>
                <span>تم سداد الـ 5%</span>
              </span>
            <?php else: ?>
              <span class="px-3.5 py-1.5 rounded-xl bg-red-100 text-red-950 border-2 border-red-300 text-xs font-black shadow-2xs inline-flex items-center gap-1.5">
                <span>✕</span>
                <span>لم يتم سداد الـ 5%</span>
              </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── شبكة الحقول الأساسية الـ 8 (مطابقة لجدول المبيعات حصراً) ── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
          
          <!-- 1. كود الوحدة (اليونت) -->
          <div class="p-4 rounded-2xl bg-white border-2 border-sky-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-sky-50 border border-sky-200 flex items-center justify-center text-lg flex-shrink-0">
              🏢
            </div>
            <div class="min-w-0 flex-1">
              <span class="text-[11px] text-slate-500 font-bold block mb-0.5">كود الوحدة (اليونت):</span>
              <span class="inline-flex items-center gap-1 px-3 py-1 rounded-xl bg-sky-100 border border-sky-300 text-slate-950 font-mono font-black text-xs shadow-2xs">
                <?= h($c['eoi_number']) ?>
              </span>
            </div>
          </div>

          <!-- 2. مكان التعاقد -->
          <div class="p-4 rounded-2xl bg-white border-2 border-sky-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-sky-50 border border-sky-200 flex items-center justify-center text-lg flex-shrink-0">
              📍
            </div>
            <div class="min-w-0 flex-1">
              <span class="text-[11px] text-slate-500 font-bold block mb-0.5">مكان التعاقد:</span>
              <span class="inline-flex items-center gap-1 px-3 py-1 rounded-xl text-xs font-black border-2 <?= ($c['contract_location'] === 'الإسكندرية') ? 'bg-sky-100 border-sky-300 text-slate-950' : 'bg-amber-100 border-amber-300 text-amber-950' ?> shadow-2xs">
                <?= ($c['contract_location'] === 'الإسكندرية') ? '🌊 ' : '🏛️ ' ?>
                <?= h($c['contract_location']) ?>
              </span>
            </div>
          </div>

          <!-- 3. فترة السداد -->
          <div class="p-4 rounded-2xl bg-white border-2 border-sky-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 border border-purple-200 flex items-center justify-center text-lg flex-shrink-0">
              ⏳
            </div>
            <div class="min-w-0 flex-1">
              <span class="text-[11px] text-slate-500 font-bold block mb-0.5">فترة السداد:</span>
              <div class="inline-block px-3 py-1 rounded-xl bg-purple-100 text-purple-950 border border-purple-300 font-black text-xs shadow-2xs">
                <?= h($c['period'] ?: $c['payment_period'] ?: '—') ?>
                <?= !empty($c['final_rate']) ? ' (' . h($c['final_rate']) . ')' : '' ?>
              </div>
            </div>
          </div>

          <!-- 4. سداد الـ 5% (حالة السداد) -->
          <div class="p-4 rounded-2xl bg-white border-2 border-sky-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl <?= $isPaid ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200' ?> flex items-center justify-center text-lg flex-shrink-0">
              <?= $isPaid ? '✅' : '❌' ?>
            </div>
            <div class="min-w-0 flex-1">
              <span class="text-[11px] text-slate-500 font-bold block mb-0.5">سداد الـ 5% (حالة السداد):</span>
              <?php if ($isPaid): ?>
                <span class="inline-flex items-center gap-1 px-3 py-0.5 rounded-full text-xs font-black bg-emerald-100 text-emerald-950 border border-emerald-400 shadow-2xs">
                  ✓ تم السداد
                </span>
                <?php if (!empty($c['payment_date'])): ?>
                  <span class="text-[11px] font-mono font-bold text-slate-700 block mt-1">📅 <?= h($c['payment_date']) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="inline-flex items-center gap-1 px-3 py-0.5 rounded-full text-xs font-black bg-red-100 text-red-950 border border-red-300 shadow-2xs">
                  ✕ لم يسدد
                </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- 5. بلد الإقامة -->
          <div class="p-4 rounded-2xl bg-white border-2 border-sky-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-sky-50 border border-sky-200 flex items-center justify-center text-lg flex-shrink-0">
              🌍
            </div>
            <div class="min-w-0 flex-1">
              <span class="text-[11px] text-slate-500 font-bold block mb-0.5">بلد الإقامة:</span>
              <span class="inline-flex items-center gap-1 px-3 py-1 rounded-xl text-xs font-black border-2 <?= $isOutside ? 'bg-blue-100 border-blue-300 text-blue-950' : 'bg-emerald-100 border-emerald-300 text-emerald-950' ?> shadow-2xs">
                <?= $isOutside ? '🌍 ' . h($resCountry) : '🇪🇬 داخل مصر' ?>
              </span>
            </div>
          </div>

          <!-- 6. توتال اليونت (إجمالي السعر) -->
          <div class="p-4 rounded-2xl bg-white border-2 border-sky-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-lg flex-shrink-0">
              💰
            </div>
            <div class="min-w-0 flex-1">
              <span class="text-[11px] text-slate-500 font-bold block mb-0.5">توتال اليونت (إجمالي السعر):</span>
              <?php if (!empty($c['unit_price'])): ?>
                <div class="text-sm font-black font-mono text-slate-950">
                  <?= number_format((float)$c['unit_price']) ?> ج.م
                </div>
                <?php if (!empty($c['maintenance_deposit'])): ?>
                  <div class="text-[10px] text-emerald-800 font-mono font-bold mt-0.5 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200 inline-block">
                    🛠️ وديعة صيانة: <?= number_format((float)$c['maintenance_deposit']) ?> ج.م
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-slate-400 font-bold text-xs">—</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- كارت مسؤول المبيعات (السيلز) - يظهر لقسم المديرين والإدارة فقط -->
          <?php if ($role !== 'Sales'): ?>
            <div class="p-4 rounded-2xl bg-sky-50/90 border-2 border-sky-300 shadow-sm flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-sky-200/80 border border-sky-400 flex items-center justify-center text-lg flex-shrink-0">
                👔
              </div>
              <div class="min-w-0 flex-1">
                <span class="text-[11px] text-slate-600 font-bold block mb-0.5">مسؤول المبيعات (السيلز):</span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white border border-sky-300 text-slate-950 font-black text-xs shadow-2xs">
                  <span>👤</span>
                  <span><?= h($c['sales_name'] ?? 'مباشر (بدون سيلز)') ?></span>
                </span>
              </div>
            </div>
          <?php endif; ?>

        </div>

        <!-- ── 7. اسم العميل وأطراف التعاقد (الاسم فقط مع صفة الشريك دون أي بيانات حساسة) ── -->
        <div class="p-5 rounded-3xl bg-white border-2 border-sky-200 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b-2 border-sky-100 flex-wrap gap-2">
            <h2 class="text-sm font-black text-slate-950 flex items-center gap-2">
              <span>👥</span>
              <span>اسم العميل وأطراف التعاقد (<?= $partnerCount ?> طرف مسجل):</span>
            </h2>
            <?php if ($partnerCount > 1): ?>
              <span class="text-[11px] text-sky-900 bg-sky-100 px-3 py-1 rounded-xl border border-sky-300 font-black shadow-2xs">
                يحتوي العقد على شركاء متضامنين
              </span>
            <?php endif; ?>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php for ($i = 0; $i < $partnerCount; $i++):
              $pName = $cNames[$i] ?? ($cNames[0] ?? $c['name']);
              $isPrimary = ($i === 0);
              $isFocused = ($selectedPartner > 0 && $selectedPartner === ($i + 1));
            ?>
              <div class="p-4 rounded-2xl bg-sky-50/70 border-2 <?= $isFocused ? 'border-sky-500 ring-4 ring-sky-200' : 'border-sky-200' ?> flex items-center justify-between gap-3 shadow-xs transition-all">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-10 h-10 rounded-xl bg-white border-2 border-sky-300 flex items-center justify-center text-lg flex-shrink-0 shadow-xs">
                    👤
                  </div>
                  <div class="min-w-0">
                    <span class="text-[10px] text-slate-500 font-bold block mb-0.5">
                      <?= $isPrimary ? 'الطرف الأساسي (العميل 1)' : 'الشريك (' . ($i + 1) . ')' ?>:
                    </span>
                    <span class="text-xs sm:text-sm font-black text-slate-950 block truncate">
                      <?= h($pName) ?>
                    </span>
                  </div>
                </div>
                <div class="flex flex-col items-end gap-1 flex-shrink-0">
                  <span class="px-2.5 py-1 rounded-xl text-[10px] font-black <?= $isPrimary ? 'bg-sky-100 text-sky-950 border border-sky-300' : 'bg-amber-100 text-amber-950 border border-amber-300' ?> whitespace-nowrap shadow-2xs">
                    <?= $isPrimary ? 'عميل أساسي' : 'شريك ' . ($i + 1) . ' من ' . $partnerCount ?>
                  </span>
                  <?php if ($isFocused): ?>
                    <span class="px-2 py-0.5 rounded-lg bg-sky-500 text-white text-[9px] font-black shadow-2xs">
                      ✓ محدد
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endfor; ?>
          </div>
        </div>

        <!-- ── 8. إيصالات الدفع المرفوعة (تتبع سداد الـ 5%) ── -->
        <div class="p-5 rounded-3xl bg-white border-2 border-sky-200 shadow-sm space-y-3">
          <div class="flex items-center justify-between pb-2.5 border-b-2 border-sky-100 flex-wrap gap-2">
            <h3 class="text-sm font-black text-slate-950 flex items-center gap-2">
              <span>🧾</span>
              <span>إيصالات الدفع المرفوعة (سداد الـ 5%):</span>
            </h3>
            <span class="text-xs text-slate-500 font-bold">
              <?= !empty($attachments) ? count($attachments) . ' إيصال مرفوع' : 'لم يتم رفع إيصالات' ?>
            </span>
          </div>

          <?php if (!empty($attachments)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              <?php foreach ($attachments as $aIdx => $att): 
                $ext = strtolower(pathinfo($att, PATHINFO_EXTENSION));
                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
              ?>
                <a 
                  href="<?= h($att) ?>" 
                  target="_blank" 
                  class="p-3 rounded-2xl bg-sky-50/80 hover:bg-sky-100 border-2 border-sky-200 hover:border-sky-400 transition-all flex items-center gap-3 shadow-2xs group"
                >
                  <div class="w-12 h-12 rounded-xl bg-white border border-sky-200 flex items-center justify-center text-2xl flex-shrink-0 overflow-hidden shadow-inner">
                    <?php if ($isImg): ?>
                      <img src="<?= h($att) ?>" alt="إيصال" class="w-full h-full object-cover">
                    <?php else: ?>
                      <span>📄</span>
                    <?php endif; ?>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="text-xs font-black text-slate-950 group-hover:text-sky-900 transition-colors">
                      <?= ($ext === 'pdf') ? 'ملف إيصال PDF' : 'صورة إيصال الدفع' ?>
                    </div>
                    <div class="text-[10px] text-slate-500 font-mono mt-0.5 truncate">
                      إيصال #<?= $aIdx + 1 ?> (<?= strtoupper($ext) ?>)
                    </div>
                  </div>
                  <span class="text-sky-600 font-mono text-sm group-hover:translate-x-0.5 transition-transform">↗</span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-center text-xs font-bold text-slate-500">
              لم يقم العميل برفع إيصال دفع حتى الآن.
            </div>
          <?php endif; ?>
        </div>

      <?php endif; ?>

    </div>
  </div>
</body></html>
<?php
    exit;
}

// ───────────── بيانات صفحة العملاء ─────────────
$q        = trim((string)($_GET['q'] ?? ''));
$filterId = (int)($_GET['sales_id'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));

$customers = []; $total = 0; $pages = 1; $teamRows = []; $allUsers = []; $managers = [];

if ($view === 'customers') {
    $where  = [$scopeSql];
    $params = $scopeParams;

    if ($filterId > 0 && $role !== 'Sales') { // الفلتر يُضاف فوق النطاق (AND) فلا يتخطاه
        $where[] = 'c.sales_id = :f_sales';
        $params[':f_sales'] = $filterId;
    }
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(c.name LIKE :q1 OR c.eoi_number LIKE :q2 OR u.name LIKE :q3)';
        $params += [':q1' => $like, ':q2' => $like, ':q3' => $like];
    }
    $whereSql = implode(' AND ', $where);

    $cs = $pdo->prepare("SELECT COUNT(*) FROM clients c LEFT JOIN users u ON u.id = c.sales_id WHERE {$whereSql}");
    $cs->execute($params);
    $total = (int)$cs->fetchColumn();
    $pages = max(1, (int)ceil($total / PORTAL_PAGE_SIZE));
    $page  = min($page, $pages);
    $offset = ($page - 1) * PORTAL_PAGE_SIZE;

    $ls = $pdo->prepare("SELECT c.id, c.name, c.phone, c.email, c.project, c.contract_location, c.eoi_number,
                                c.residence_country, c.period, c.payment_period, c.final_rate,
                                c.paid_5_percent, c.payment_date, c.unit_price, c.maintenance_deposit,
                                c.attachments, c.created_at, c.sales_id, u.name AS sales_name
                         FROM clients c LEFT JOIN users u ON u.id = c.sales_id
                         WHERE {$whereSql} ORDER BY c.id DESC
                         LIMIT " . PORTAL_PAGE_SIZE . " OFFSET " . (int)$offset);
    $ls->execute($params);
    $customers = $ls->fetchAll();

    if ($role === 'Manager') {
        $ts = $pdo->prepare("SELECT u.id, u.name, u.active,
                                    (SELECT COUNT(*) FROM clients WHERE sales_id = u.id) AS cnt
                             FROM users u WHERE u.role = 'Sales' AND u.manager_id = :m ORDER BY u.name");
        $ts->execute([':m' => (int)$user['id']]);
        $teamRows = $ts->fetchAll();
    } elseif ($role === 'Admin') {
        $teamRows = $pdo->query("SELECT id, name FROM users WHERE role = 'Sales' ORDER BY name")->fetchAll();
    }
}

if ($view === 'users') {
    $allUsers = $pdo->query("SELECT u.id, u.name, u.email, u.role, u.manager_id, u.referral_code, u.active,
                                    m.name AS manager_name,
                                    (SELECT COUNT(*) FROM clients WHERE sales_id = u.id) AS cnt
                             FROM users u LEFT JOIN users m ON m.id = u.manager_id
                             ORDER BY FIELD(u.role,'Admin','Manager','Sales'), u.name")->fetchAll();
    $managers = $pdo->query("SELECT id, name FROM users WHERE role = 'Manager' AND active = 1 ORDER BY name")->fetchAll();
}

function pageUrl(array $over = []): string {
    $q = array_merge(array_filter([
        'q' => $_GET['q'] ?? '', 'sales_id' => $_GET['sales_id'] ?? '',
    ], fn($v) => $v !== '' && $v !== null), $over);
    return 'sales_portal.php' . ($q ? '?' . http_build_query($q) : '');
}

pageHead($view === 'users' ? 'إدارة المستخدمين' : 'العملاء'); ?>
<div class="w-full max-w-none p-2 sm:p-4 space-y-4">

  <!-- Header -->
  <header class="glass rounded-3xl p-4 sm:p-5 flex flex-col md:flex-row items-center justify-between gap-4 shadow-2xl">
    <div class="flex items-center gap-4">
      <div class="p-2 rounded-2xl bg-gradient-to-tr from-sky-400/20 via-blue-400/10 to-transparent border-2 border-sky-300 shadow-md">
        <img src="logo.png" alt="Logo" class="h-12 object-contain">
      </div>
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-950"><?= pt('بوابة المبيعات', 'Sales Portal') ?></h1>
        <p class="text-xs text-slate-800 font-bold mt-1">
          <span class="font-black text-slate-950">👤 <?= h($user['name']) ?></span> •
          <span class="px-2.5 py-0.5 rounded-full bg-sky-100 text-sky-950 border border-sky-300 font-black"><?= h($roleLabel) ?></span>
        </p>
      </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <!-- Language Toggle Button -->
      <button 
        type="button" 
        onclick="toggleLanguage()" 
        class="px-3.5 py-2 bg-white hover:bg-sky-50 text-slate-950 text-xs sm:text-sm font-black rounded-2xl transition-all border-2 border-sky-300 flex items-center gap-1.5 cursor-pointer shadow-sm"
      >
        <span>🌐</span>
        <span><?= $isEn ? 'العربية' : 'English' ?></span>
      </button>

      <?php if ($isAdmin): ?>
        <a href="sales_portal.php" class="px-4 py-2 rounded-2xl text-xs sm:text-sm font-black border-2 <?= $view === 'customers' ? 'bg-gradient-to-r from-sky-400 to-blue-500 text-slate-950 border-sky-400 shadow-md' : 'bg-white hover:bg-sky-50 text-slate-950 border-sky-300 shadow-xs' ?>"><?= pt('العملاء', 'Clients') ?></a>
        <a href="sales_portal.php?view=users" class="px-4 py-2 rounded-2xl text-xs sm:text-sm font-black border-2 <?= $view === 'users' ? 'bg-gradient-to-r from-sky-400 to-blue-500 text-slate-950 border-sky-400 shadow-md' : 'bg-white hover:bg-sky-50 text-slate-950 border-sky-300 shadow-xs' ?>"><?= pt('المستخدمون', 'Users') ?></a>
        <a href="view_clients.php" target="_blank" class="px-4 py-2 rounded-2xl text-xs sm:text-sm font-black bg-slate-900 text-sky-300 hover:text-white border-2 border-slate-700 shadow-xs"><?= pt('لوحة الإدارة ↗', 'Master Admin ↗') ?></a>
      <?php endif; ?>
      <form method="POST" action="sales_portal.php">
        <input type="hidden" name="action" value="logout">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <button class="px-4 py-2 rounded-2xl text-xs sm:text-sm font-black bg-red-50 hover:bg-red-100 text-red-700 border-2 border-red-300 shadow-xs cursor-pointer"><?= pt('خروج', 'Logout') ?></button>
      </form>
    </div>
  </header>

  <?php flashBox($flash); ?>

<?php if ($view === 'customers'): ?>

  <?php if ($role === 'Sales'):
      $refLink = baseUrl() . '/form.php?ref=' . urlencode($user['referral_code']); ?>
  <div class="glass rounded-3xl p-4 sm:p-5">
    <p class="text-xs font-black text-slate-950 mb-2">رابطك الشخصي للتسجيل (أي عميل يسجل منه يظهر لك تلقائياً)</p>
    <div class="flex items-center gap-2" dir="ltr">
      <input id="refLink" readonly value="<?= h($refLink) ?>" class="flex-1 px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-xs font-mono font-bold text-slate-950 shadow-xs">
      <button type="button" onclick="copyText('refLink', this)" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-sky-400 to-blue-500 hover:from-sky-500 hover:to-blue-600 text-slate-950 text-xs font-black border-2 border-sky-400 shadow-md cursor-pointer">نسخ</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- بطاقات -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="glass rounded-3xl p-5 border-2 border-sky-300 flex items-center gap-4">
      <div class="w-14 h-14 rounded-2xl bg-sky-100 border-2 border-sky-300 flex items-center justify-center text-2xl text-sky-800 shadow-xs">
        📑
      </div>
      <div>
        <p class="text-xs text-slate-700 font-black uppercase tracking-wider"><?= $role === 'Sales' ? 'عملائي' : ($role === 'Manager' ? 'عملاء فريقي' : 'كل العملاء') ?></p>
        <p class="text-3xl font-black text-slate-950 mt-0.5"><?= (int)$total ?></p>
      </div>
    </div>
    <?php if ($role === 'Manager'): ?>
    <div class="glass rounded-3xl p-5 border-2 border-sky-300 flex items-center gap-4">
      <div class="w-14 h-14 rounded-2xl bg-purple-100 border-2 border-purple-300 flex items-center justify-center text-2xl text-purple-800 shadow-xs">
        👥
      </div>
      <div>
        <p class="text-xs text-slate-700 font-black uppercase tracking-wider">عدد السيلز في فريقي</p>
        <p class="text-3xl font-black text-slate-950 mt-0.5"><?= count($teamRows) ?></p>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($role === 'Manager' && $teamRows): ?>
  <div class="glass rounded-3xl p-4 sm:p-5">
    <p class="text-sm font-black text-slate-950 mb-3 flex items-center gap-2">
      <span>👥</span>
      <span>فريق المبيعات (السيلز) التابع لإدارتي:</span>
    </p>
    <div class="flex flex-wrap gap-2">
      <a href="<?= h(pageUrl(['sales_id' => null, 'page' => null])) ?>" class="px-3.5 py-1.5 rounded-xl text-xs font-black border-2 <?= $filterId ? 'bg-white text-slate-950 border-sky-300 shadow-xs' : 'bg-gradient-to-r from-sky-400 to-blue-500 text-slate-950 border-sky-400 shadow-md' ?>">الكل (جميع السيلز)</a>
      <?php foreach ($teamRows as $t): ?>
        <a href="sales_portal.php?sales_id=<?= (int)$t['id'] ?>" class="px-3.5 py-1.5 rounded-xl text-xs font-black border-2 <?= $filterId === (int)$t['id'] ? 'bg-gradient-to-r from-sky-400 to-blue-500 text-slate-950 border-sky-400 shadow-md' : 'bg-white text-slate-950 border-sky-300 shadow-xs' ?>">
          <span>👔</span>
          <span><?= h($t['name']) ?></span> <span class="font-mono opacity-80">(<?= (int)$t['cnt'] ?>)</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- بحث / فلتر -->
  <form method="GET" action="sales_portal.php" class="glass rounded-3xl p-4 sm:p-5 flex flex-col sm:flex-row gap-3 border-2 border-sky-300 shadow-xl">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="<?= ($role !== 'Sales') ? 'ابحث باسم العميل أو كود الوحدة أو اسم السيلز...' : 'ابحث باسم العميل أو كود الوحدة...' ?>"
           class="flex-1 px-4 py-3 rounded-2xl bg-white border-2 border-sky-300 text-slate-950 placeholder-slate-500 text-sm focus:outline-none focus:border-sky-500 font-bold shadow-xs">
    <?php if ($role === 'Admin' && $teamRows): ?>
      <select name="sales_id" class="px-3 py-3 rounded-2xl bg-white border-2 border-sky-300 text-slate-950 text-sm font-bold shadow-xs focus:outline-none focus:border-sky-500">
        <option value="">كل السيلز</option>
        <?php foreach ($teamRows as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= $filterId === (int)$t['id'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php elseif ($role === 'Manager' && $filterId): ?>
      <input type="hidden" name="sales_id" value="<?= (int)$filterId ?>">
    <?php endif; ?>
    <button class="px-6 py-3 rounded-2xl bg-gradient-to-r from-sky-400 to-blue-500 hover:from-sky-500 hover:to-blue-600 text-slate-950 text-sm font-black border-2 border-sky-400 shadow-md cursor-pointer">بحث</button>
  </form>

  <!-- جدول العملاء -->
  <div class="glass rounded-3xl overflow-hidden shadow-2xl">
    <div class="overflow-x-auto">
      <table class="w-full text-xs sm:text-sm text-right border-collapse">
        <thead class="sticky top-0 z-20 bg-sky-200/95 text-slate-950 text-xs font-black border-b-2 border-sky-400 backdrop-blur-md">
          <tr>
            <th class="p-3.5 w-12 text-center">#</th>
            <th class="p-3.5 min-w-[180px] text-right">اسم العميل</th>
            <th class="p-3.5 text-center min-w-[130px]">كود الوحدة (اليونت)</th>
            <th class="p-3.5 text-center min-w-[120px]">مكان التعاقد</th>
            <th class="p-3.5 text-center min-w-[110px]">فترة السداد</th>
            <th class="p-3.5 text-center min-w-[160px]">حالة السداد (تم السداد؟)</th>
            <th class="p-3.5 text-center min-w-[130px]">بلد الإقامة</th>
            <th class="p-3.5 text-center min-w-[150px]">تاريخ ووقت التسجيل</th>
            <th class="p-3.5 text-center min-w-[140px]">توتال اليونت</th>
            <?php if ($role !== 'Sales'): ?><th class="p-3.5 text-center min-w-[150px]">مسؤول المبيعات (السيلز)</th><?php endif; ?>
            <th class="p-3.5 text-center min-w-[90px]">الإجراءات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-sky-100 text-slate-950 font-bold bg-white">
        <?php if (!$customers): ?>
          <tr>
            <td colspan="<?= ($role !== 'Sales') ? 11 : 10 ?>" class="p-12 text-center text-slate-950 font-black text-sm">
              <div class="text-3xl mb-2">🏖️</div>
              لا يوجد عملاء لعرضهم.
            </td>
          </tr>
        <?php endif; ?>
        <?php 
        $seq = $offset + 1;
        foreach ($customers as $c): 
          $cNames  = splitPartnerValues((string)$c['name']);
          $cPhones = splitPartnerValues((string)$c['phone']);
          $pCount  = max(count($cNames), count($cPhones), 1);
          $resCountry = $c['residence_country'] ?? 'داخل جمهورية مصر العربية';
          $isOutside = str_contains($resCountry, 'خارج');
          $attachments = json_decode($c['attachments'] ?? '[]', true);
          if (!is_array($attachments)) $attachments = [];
          $isPaid = ($c['paid_5_percent'] === 'نعم');

          for ($i = 0; $i < $pCount; $i++):
            $pName  = $cNames[$i] ?? ($cNames[0] ?? $c['name']);
            $pPhone = $cPhones[$i] ?? ($cPhones[0] ?? $c['phone']);
        ?>
          <tr class="hover:bg-sky-50/90 transition-colors">
            <!-- 1. # (الترقيم التسلسلي مع الـ ID) -->
            <td class="p-3.5 text-center font-black text-slate-950 font-mono bg-sky-50/60">
              <div class="text-xs sm:text-sm"><?= $seq++ ?></div>
              <div class="text-[10px] text-slate-500 font-mono">#<?= (int)$c['id'] ?></div>
            </td>

            <!-- 2. اسم العميل مع شارة الشريك إن وجدت -->
            <td class="p-3.5 font-black text-slate-950 min-w-[180px] whitespace-normal break-words text-right">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-xs sm:text-sm text-slate-950 font-black leading-snug"><?= h($pName) ?></span>
                <?php if ($pCount > 1): ?>
                  <span class="inline-block px-2 py-0.5 rounded-lg text-[10px] bg-amber-100 text-amber-950 border border-amber-300 font-black whitespace-nowrap shadow-xs">
                    شريك <?= ($i + 1) ?> من <?= $pCount ?>
                  </span>
                <?php endif; ?>
              </div>
            </td>

            <!-- 3. كود الوحدة داخل بادج بارز -->
            <td class="p-3.5 text-center font-mono font-black text-slate-950 min-w-[130px]">
              <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-sky-100 border-2 border-sky-300 text-slate-950 font-mono font-black text-xs shadow-2xs">
                <span>🏢</span>
                <span><?= h($c['eoi_number']) ?></span>
              </span>
            </td>

            <!-- 4. مكان التعاقد -->
            <td class="p-3.5 text-center font-black text-slate-950 min-w-[120px]">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-black border-2 <?= ($c['contract_location'] === 'الإسكندرية') ? 'bg-sky-100 border-sky-300' : 'bg-amber-100 border-amber-300' ?> shadow-2xs">
                <?= ($c['contract_location'] === 'الإسكندرية') ? '🌊 ' : '🏛️ ' ?>
                <?= h($c['contract_location']) ?>
              </span>
            </td>

            <!-- 5. فترة السداد -->
            <td class="p-3.5 text-center font-black text-slate-950 min-w-[110px]">
              <div class="inline-block px-2.5 py-1 rounded-xl bg-purple-100 text-purple-950 border-2 border-purple-300 font-black text-xs shadow-2xs">
                <?= h($c['period'] ?: $c['payment_period'] ?: '—') ?>
              </div>
              <?php if (!empty($c['final_rate'])): ?>
                <div class="text-[10px] text-slate-800 font-mono font-black mt-0.5"><?= h($c['final_rate']) ?></div>
              <?php endif; ?>
            </td>

            <!-- 6. حالة السداد (نعم / لا) + زر الإيصال -->
            <td class="p-3.5 text-center font-black text-slate-950 min-w-[160px]">
              <?php if ($isPaid): ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-950 border-2 border-emerald-400 shadow-2xs mb-1">
                  ✓ تم السداد
                </span>
                <?php if (!empty($c['payment_date'])): ?>
                  <div class="text-[10px] text-slate-800 font-mono font-bold">
                    📅 <?= h($c['payment_date']) ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($attachments)): ?>
                  <div class="mt-1 flex items-center justify-center gap-1 flex-wrap">
                    <?php foreach ($attachments as $aIdx => $att): 
                      $ext = strtolower(pathinfo($att, PATHINFO_EXTENSION));
                    ?>
                      <a 
                        href="<?= h($att) ?>" 
                        target="_blank" 
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-sky-100 hover:bg-sky-200 border border-sky-300 text-slate-950 text-[10px] font-black transition-colors shadow-2xs"
                        title="معاينة إيصال الدفع"
                      >
                        <span><?= ($ext === 'pdf') ? '📄' : '🖼️' ?></span>
                        <span>عرض الإيصال <?= count($attachments) > 1 ? '#' . ($aIdx + 1) : '' ?></span>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="text-[10px] text-amber-800 font-bold mt-0.5">لم يرفع إيصال</div>
                <?php endif; ?>
              <?php else: ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-black bg-red-100 text-red-950 border-2 border-red-300 shadow-2xs">
                  ✕ لم يسدد
                </span>
                <div class="text-[10px] text-slate-400 font-bold mt-0.5">لم يرفع</div>
              <?php endif; ?>
            </td>

            <!-- 7. بلد الإقامة -->
            <td class="p-3.5 text-center font-black text-slate-950 min-w-[130px]">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-black border-2 <?= $isOutside ? 'bg-blue-100 border-blue-300 text-blue-950' : 'bg-emerald-100 border-emerald-300 text-emerald-950' ?> shadow-2xs">
                <?= $isOutside ? '🌍 ' . h($resCountry) : '🇪🇬 داخل مصر' ?>
              </span>
            </td>

            <!-- 8. تاريخ ووقت التسجيل (الوقت والتاريخ معاً) -->
            <td class="p-3.5 text-center font-mono text-slate-950 min-w-[150px]">
              <div class="bg-sky-50 px-2.5 py-1 rounded-xl border border-sky-200 inline-block font-mono text-xs font-black text-slate-950 shadow-2xs whitespace-nowrap">
                <?= date('Y-m-d h:i A', strtotime($c['created_at'])) ?>
              </div>
            </td>

            <!-- 9. إجمالي سعر الوحدة (توتال اليونت) -->
            <td class="p-3.5 text-center font-black text-slate-950 min-w-[140px]">
              <?php if (!empty($c['unit_price'])): ?>
                <div class="text-xs font-black font-mono text-slate-950 whitespace-nowrap">
                  💰 <?= number_format((float)$c['unit_price']) ?> ج.م
                </div>
                <?php if (!empty($c['maintenance_deposit'])): ?>
                  <div class="text-[10px] text-emerald-800 font-black font-mono bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200 inline-block mt-0.5">
                    🛠️ صيانة: <?= number_format((float)$c['maintenance_deposit']) ?> ج.م
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-slate-400 font-bold text-xs">—</span>
              <?php endif; ?>
            </td>

            <!-- عمود السيلز للأدمن والمدير فقط -->
            <?php if ($role !== 'Sales'): ?>
              <td class="p-3.5 text-center font-black text-slate-950 text-xs min-w-[150px]">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-sky-100 border border-sky-300 text-sky-950 font-black text-xs shadow-2xs">
                  <span>👔</span>
                  <span><?= h($c['sales_name'] ?? 'مباشر') ?></span>
                </span>
              </td>
            <?php endif; ?>

            <!-- 10. الإجراءات (زر عرض للتفاصيل الكاملة) -->
            <td class="p-3.5 text-center min-w-[90px]">
              <a 
                href="sales_portal.php?customer=<?= (int)$c['id'] ?>&partner=<?= ($i + 1) ?>" 
                class="px-3.5 py-1.5 bg-sky-600 hover:bg-sky-700 text-white font-black rounded-xl shadow-xs inline-flex items-center gap-1 text-xs transition-transform hover:scale-105 active:scale-95"
                title="عرض التفاصيل الكاملة"
              >
                <span>👁️</span>
                <span>عرض</span>
              </a>
            </td>
          </tr>
        <?php endfor; endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
    <div class="p-4 border-t border-slate-800 flex items-center justify-between text-xs">
      <span class="text-slate-950 font-black">صفحة <?= $page ?> من <?= $pages ?> • <?= $total ?> عميل</span>
      <div class="flex gap-2">
        <?php if ($page > 1): ?><a class="px-3.5 py-1.5 rounded-xl bg-white hover:bg-sky-50 text-slate-950 font-black border-2 border-sky-300 shadow-xs" href="<?= h(pageUrl(['page' => $page - 1])) ?>">السابق</a><?php endif; ?>
        <?php if ($page < $pages): ?><a class="px-3.5 py-1.5 rounded-xl bg-white hover:bg-sky-50 text-slate-950 font-black border-2 border-sky-300 shadow-xs" href="<?= h(pageUrl(['page' => $page + 1])) ?>">التالي</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

<?php else: /* ───────── إدارة المستخدمين (أدمن) ───────── */ ?>

  <div class="glass rounded-3xl p-5">
    <h2 class="font-black text-slate-950 mb-4">➕ إضافة مدير أو سيلز</h2>
    <form method="POST" action="sales_portal.php" class="grid grid-cols-1 md:grid-cols-12 gap-3" autocomplete="off">
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input name="name" required placeholder="الاسم" class="md:col-span-3 px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-bold shadow-xs">
      <input name="email" type="email" required placeholder="البريد الإلكتروني" dir="ltr" class="md:col-span-3 px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-bold shadow-xs">
      <div class="md:col-span-3 flex gap-2">
        <input name="password" id="newPw" type="text" required minlength="8" placeholder="كلمة المرور (8+)" dir="ltr" class="flex-1 min-w-0 px-3 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-mono font-bold shadow-xs">
        <button type="button" onclick="document.getElementById('newPw').value = genPw()" class="px-3 rounded-xl bg-white hover:bg-sky-50 text-slate-950 font-black border-2 border-sky-300 shadow-xs cursor-pointer">توليد</button>
      </div>
      <select name="role" id="roleSel" onchange="toggleMgr()" class="md:col-span-1 px-2 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-bold shadow-xs">
        <option value="Sales">سيلز</option><option value="Manager">مدير</option>
      </select>
      <select name="manager_id" id="mgrSel" class="md:col-span-2 px-2 py-2.5 rounded-xl bg-white border-2 border-sky-300 text-slate-950 font-bold shadow-xs">
        <option value="">بدون مدير (مباشر / الإدارة)</option>
        <?php foreach ($managers as $m): ?><option value="<?= (int)$m['id'] ?>"><?= h($m['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="md:col-span-12 py-3 rounded-2xl bg-gradient-to-r from-sky-400 to-blue-500 hover:from-sky-500 hover:to-blue-600 text-slate-950 font-black text-sm border-2 border-sky-400 shadow-md cursor-pointer">إنشاء الحساب</button>
    </form>
  </div>

  <div class="glass rounded-3xl overflow-hidden shadow-2xl">
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-right">
        <thead class="bg-sky-200/95 text-slate-950 text-xs font-black border-b-2 border-sky-300">
          <tr><th class="p-3">الاسم</th><th class="p-3">الإيميل</th><th class="p-3">الدور</th><th class="p-3">المدير</th>
              <th class="p-3 text-center">العملاء</th><th class="p-3 text-center">الحالة</th><th class="p-3">إجراءات</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-800/80">
        <?php foreach ($allUsers as $u): $isSelf = ((int)$u['id'] === (int)$user['id']); ?>
          <tr class="hover:bg-sky-50/90 transition-colors align-top">
            <td class="p-3 font-black text-slate-950"><?= h($u['name']) ?></td>
            <td class="p-3 font-mono text-xs text-cyan-300" dir="ltr"><?= h($u['email']) ?></td>
            <td class="p-3"><?= h($u['role']) ?></td>
            <td class="p-3">
              <?php if ($u['role'] === 'Sales'): ?>
                <form method="POST" action="sales_portal.php" class="flex gap-1">
                  <input type="hidden" name="action" value="assign_manager">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <select name="manager_id" class="px-2 py-1 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs font-bold shadow-xs">
                    <option value="" <?= empty($u['manager_id']) ? 'selected' : '' ?>>— بدون مدير —</option>
                    <?php foreach ($managers as $m): ?>
                      <option value="<?= (int)$m['id'] ?>" <?= (int)$u['manager_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= h($m['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="px-2.5 py-1 rounded-xl bg-sky-100 hover:bg-sky-200 text-slate-950 border border-sky-300 text-xs font-black shadow-xs cursor-pointer">حفظ</button>
                </form>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="p-3 text-center font-mono"><?= $u['role'] === 'Sales' ? (int)$u['cnt'] : '—' ?></td>
            <td class="p-3 text-center">
              <span class="px-2 py-0.5 rounded-full text-[11px] font-bold border <?= (int)$u['active'] ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' : 'bg-red-500/15 text-red-400 border-red-500/30' ?>">
                <?= (int)$u['active'] ? 'نشط' : 'معطل' ?>
              </span>
            </td>
            <td class="p-3">
              <div class="flex flex-wrap gap-2">
                <?php if (!$isSelf): ?>
                <form method="POST" action="sales_portal.php" class="inline">
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <button class="px-2.5 py-1 rounded-xl bg-white hover:bg-sky-50 border-2 border-sky-300 text-slate-950 text-xs font-black shadow-xs transition cursor-pointer"><?= (int)$u['active'] ? 'تعطيل' : 'تفعيل' ?></button>
                </form>
                <form method="POST" action="sales_portal.php" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف الحساب (<?= h(addslashes($u['name'])) ?>) نهائياً؟');">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="px-2.5 py-1 rounded-lg bg-red-500/15 hover:bg-red-500/30 border border-red-500/40 text-red-400 hover:text-red-300 text-xs font-bold transition">حذف</button>
                </form>
                <?php endif; ?>
                <form method="POST" action="sales_portal.php" class="flex gap-1" autocomplete="off">
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <input name="new_password" type="text" minlength="8" required placeholder="باسورد جديد" dir="ltr" class="w-28 px-2 py-1 rounded-xl bg-white border-2 border-sky-300 text-slate-950 text-xs font-mono font-bold shadow-xs">
                  <button class="px-2.5 py-1 rounded-xl bg-sky-100 hover:bg-sky-200 text-slate-950 border border-sky-300 text-xs font-black shadow-xs cursor-pointer">تغيير</button>
                </form>
              </div>
              <?php if ($u['role'] === 'Sales'):
                  $l = baseUrl() . '/form.php?ref=' . urlencode($u['referral_code']); ?>
                <div class="mt-1.5 text-[10px] font-mono text-slate-500 break-all" dir="ltr"><?= h($l) ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>
</div>

<script>
  function copyText(id, btn) {
    const el = document.getElementById(id);
    el.select();
    (navigator.clipboard ? navigator.clipboard.writeText(el.value) : Promise.resolve(document.execCommand('copy')))
      .then(() => { const t = btn.innerText; btn.innerText = 'تم ✓'; setTimeout(() => btn.innerText = t, 1500); });
  }
  function genPw() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    const a = new Uint32Array(12); crypto.getRandomValues(a);
    return Array.from(a, n => chars[n % chars.length]).join('');
  }
  function toggleMgr() {
    const isSales = document.getElementById('roleSel').value === 'Sales';
    const m = document.getElementById('mgrSel');
    m.style.display = isSales ? '' : 'none';
    m.required = false;
  }
  if (document.getElementById('roleSel')) toggleMgr();
</script>
</body></html>
