<?php
/**
 * setup_db.php - إنشاء قاعدة البيانات وجميع الجداول والمستخدم المسؤول
 * يمكن تشغيله عبر المتصفح أو عبر سطر الأوامر (CLI): php setup_db.php
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';

$isCli = (php_sapi_name() === 'cli');
$nl = $isCli ? "\n" : "<br>";

try {
    // 1. الاتصال بـ MySQL بدون تحديد قاعدة بيانات
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. إنشاء قاعدة البيانات
    $pdo->exec("CREATE DATABASE IF NOT EXISTS elamar_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ تم إنشاء قاعدة البيانات elamar_db بنجاح{$nl}";

    // 3. استخدام قاعدة البيانات
    $pdo->exec("USE elamar_db");

    // 4. إنشاء جدول clients بالهيكل الكامل
    $sqlClients = "CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(100) NOT NULL,
        email VARCHAR(255) NULL,
        id_type VARCHAR(50) DEFAULT 'national_id',
        id_number VARCHAR(100) NULL,
        governorate VARCHAR(100) NULL,
        source VARCHAR(100) NULL,
        project VARCHAR(255) NULL,
        contract_location VARCHAR(100) NULL,
        unit_type VARCHAR(100) NULL,
        eoi_number VARCHAR(100) NOT NULL UNIQUE,
        payment_period VARCHAR(100) NULL,
        paid_5_percent VARCHAR(50) NULL,
        addresses TEXT NULL,
        notes TEXT NULL,
        attachments LONGTEXT NULL,
        sales_id INT NULL,
        lang VARCHAR(10) DEFAULT 'ar',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_eoi (eoi_number),
        INDEX idx_phone (phone),
        INDEX idx_sales (sales_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlClients);
    echo "✓ تم إنشاء جدول clients بنجاح{$nl}";

    // إذا كان الجدول منشأ سابقاً بأعمدة قديمة، نقوم بإضافة الأعمدة الناقصة تلقائياً
    $existingCols = $pdo->query("DESCRIBE clients")->fetchAll(PDO::FETCH_COLUMN);
    $neededCols = [
        'email' => "VARCHAR(255) NULL AFTER phone",
        'id_type' => "VARCHAR(50) DEFAULT 'national_id' AFTER email",
        'id_number' => "VARCHAR(100) NULL AFTER id_type",
        'contract_location' => "VARCHAR(100) NULL AFTER project",
        'payment_period' => "VARCHAR(100) NULL AFTER eoi_number",
        'paid_5_percent' => "VARCHAR(50) NULL AFTER payment_period",
        'addresses' => "TEXT NULL AFTER paid_5_percent",
        'notes' => "TEXT NULL AFTER addresses",
        'attachments' => "LONGTEXT NULL AFTER notes",
        'sales_id' => "INT NULL AFTER attachments"
    ];
    foreach ($neededCols as $col => $def) {
        if (!in_array($col, $existingCols)) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN {$col} {$def}");
            echo "✓ تم تحديث جدول clients بإضافة العمود: {$col}{$nl}";
        }
    }

    // 5. إنشاء جدول sales_employees
    $sqlSales = "CREATE TABLE IF NOT EXISTS sales_employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        slug VARCHAR(100) NOT NULL UNIQUE,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_slug (slug),
        INDEX idx_active (active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlSales);
    echo "✓ تم إنشاء جدول sales_employees بنجاح{$nl}";

    // 6. إنشاء جدول admins
    $sqlAdmins = "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlAdmins);
    echo "✓ تم إنشاء جدول admins بنجاح{$nl}";

    // 7. إضافة مستخدم أدمن افتراضي إذا لم يكن موجوداً
    $checkAdmin = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($checkAdmin == 0) {
        $defaultUser = 'admin';
        $defaultPass = 'admin123';
        $hashedPass = password_hash($defaultPass, PASSWORD_BCRYPT);
        $insertAdmin = $pdo->prepare("INSERT INTO admins (username, password, role) VALUES (:u, :p, 'admin')");
        $insertAdmin->execute([':u' => $defaultUser, ':p' => $hashedPass]);
        echo "✓ تم إنشاء حساب المسؤول الافتراضي بنجاح (المستخدم: {$defaultUser} | كلمة المرور: {$defaultPass}){$nl}";
    }

    // 8. التأكد من وجود مجلد uploads وتصريحاته
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        echo "✓ تم إنشاء مجلد uploads بنجاح{$nl}";
    }

    echo "{$nl}<strong>=== تم تجهيز قاعدة البيانات بنجاح تام! ===</strong>{$nl}";

} catch (PDOException $e) {
    die("خطأ في إعداد قاعدة البيانات: " . $e->getMessage() . $nl);
}
?>
