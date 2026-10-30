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
        'sales_id' => "INT NULL AFTER attachments",
        'residence_country' => "VARCHAR(100) DEFAULT 'inside_egypt' AFTER id_number",
        'id_address' => "TEXT NULL AFTER addresses",
        'correspondence_address' => "TEXT NULL AFTER id_address",
        'payment_date' => "VARCHAR(50) NULL AFTER paid_5_percent",
        'unit_price' => "DECIMAL(15,2) NULL AFTER payment_period",
        'maintenance_deposit' => "DECIMAL(15,2) NULL AFTER unit_price",
        'period' => "VARCHAR(50) NULL AFTER payment_period",
        'final_rate' => "VARCHAR(50) NULL AFTER period",
        'general_notes' => "TEXT NULL AFTER notes"
    ];
    foreach ($neededCols as $col => $def) {
        if (!in_array($col, $existingCols)) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN {$col} {$def}");
            echo "✓ تم تحديث جدول clients بإضافة العمود: {$col}{$nl}";
        }
    }

    // 5. إنشاء جدول users الهرمي الموحد (RBAC)
    $sqlUsers = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('Admin', 'Manager', 'Sales') NOT NULL DEFAULT 'Sales',
        manager_id INT NULL,
        referral_code VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_role (role),
        INDEX idx_manager (manager_id),
        INDEX idx_ref (referral_code),
        CONSTRAINT fk_users_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sqlUsers);
    echo "✓ تم إنشاء جدول users بنجاح{$nl}";

    // إضافة مستخدمين تجريبيين لكافة الرتب (Admin, Manager, Sales) إذا كان الجدول فارغاً
    $checkUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($checkUsers == 0) {
        $defaultPass = password_hash('password123', PASSWORD_BCRYPT);
        
        // 1. مدير عام النظام (Admin)
        $insAdmin = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, manager_id, referral_code) VALUES (:name, :email, :pass, 'Admin', NULL, :ref)");
        $insAdmin->execute([
            ':name' => 'المدير التنفيذي (Admin)',
            ':email' => 'admin@elamar.com',
            ':pass' => $defaultPass,
            ':ref' => 'admin'
        ]);
        $adminId = $pdo->lastInsertId();

        // 2. مدير قطاع مبيعات (Manager)
        $insManager = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, manager_id, referral_code) VALUES (:name, :email, :pass, 'Manager', NULL, :ref)");
        $insManager->execute([
            ':name' => 'أحمد إبراهيم (مدير مبيعات)',
            ':email' => 'manager@elamar.com',
            ':pass' => $defaultPass,
            ':ref' => 'mgr-ahmed'
        ]);
        $managerId = $pdo->lastInsertId();

        // 3. موظفي مبيعات (Sales 1 & Sales 2) تابعين للمدير
        $insSales = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, manager_id, referral_code) VALUES (:name, :email, :pass, 'Sales', :mgr, :ref)");
        $insSales->execute([
            ':name' => 'كريم محمود (مستشار مبيعات)',
            ':email' => 'sales1@elamar.com',
            ':pass' => $defaultPass,
            ':mgr' => $managerId,
            ':ref' => 'karim101'
        ]);
        $insSales->execute([
            ':name' => 'سارة الشريف (مستشار مبيعات)',
            ':email' => 'sales2@elamar.com',
            ':pass' => $defaultPass,
            ':mgr' => $managerId,
            ':ref' => 'sara102'
        ]);

        // 4. مدير مبيعات آخر وموظف تابع له لاختبار الفصل التام بين المديرين
        $insManager2 = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, manager_id, referral_code) VALUES (:name, :email, :pass, 'Manager', NULL, :ref)");
        $insManager2->execute([
            ':name' => 'محمود كمال (مدير قطاع 2)',
            ':email' => 'manager2@elamar.com',
            ':pass' => $defaultPass,
            ':ref' => 'mgr-kamal'
        ]);
        $manager2Id = $pdo->lastInsertId();

        $insSales->execute([
            ':name' => 'ياسر العلي (مستشار قطاع 2)',
            ':email' => 'sales3@elamar.com',
            ':pass' => $defaultPass,
            ':mgr' => $manager2Id,
            ':ref' => 'yasser103'
        ]);

        echo "✓ تم إنشاء مستخدمين افتراضيين بنجاح لكافة الصلاحيات (Admin, Manager, Sales){$nl}";
    }

    // 6. ربط جدول clients بـ users(id) إذا رغبنا وتعديل المفتاح الأجنبي
    try {
        $pdo->exec("ALTER TABLE clients ADD CONSTRAINT fk_clients_sales FOREIGN KEY (sales_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "✓ تم ربط sales_id في جدول clients كـ Foreign Key لجدول users{$nl}";
    } catch (Exception $e) {
        // إذا كان المفتاح موجوداً مسبقاً نتخطى
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
