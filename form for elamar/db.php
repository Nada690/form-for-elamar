<?php
/**
 * db.php - Database Configuration & Secure PDO Connection
 * تم تصميم هذا الملف ليكون معزولاً ومحمياً من الوصول المباشر
 */

// منع الوصول المباشر لهذا الملف عبر المتصفح
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden.');
}

// بيانات الاتصال بقاعدة البيانات (تدعم محلياً XAMPP وسحابياً Railway / Render / Docker)
$db_url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
if (!empty($db_url)) {
    $db_parts = parse_url($db_url);
    $db_host = $db_parts['host'] ?? '127.0.0.1';
    $db_port = $db_parts['port'] ?? 3306;
    $db_user = $db_parts['user'] ?? 'root';
    $db_pass = $db_parts['pass'] ?? '';
    $db_name = ltrim($db_parts['path'] ?? 'elamar_db', '/');
} else {
    $db_host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
    $db_port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';
    $db_name = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'elamar_db';
    $db_user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
    $db_pass = getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');
}

try {
    // الاتصال باستخدام PDO مع تفعيل Prepared Statements والحماية من الحقن
    $pdo = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // رمي أخطاء للتعامل معها برمجياً
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // إرجاع البيانات في شكل مصفوفة ترابطية
            PDO::ATTR_EMULATE_PREPARES   => false,                   // استخدام Prepared Statements الحقيقية لمنع SQL Injection
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"     // فرض دعم كامل للعربية والرموز التعبيرية
        ]
    );
} catch (PDOException $e) {
    // تسجيل الخطأ داخلياً وعدم إظهار تفاصيل حساسة للمستخدم
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'status'  => 'error',
        'message' => 'تعذر الاتصال بقاعدة البيانات، يرجى المحاولة لاحقاً.'
    ], JSON_UNESCAPED_UNICODE));
}
