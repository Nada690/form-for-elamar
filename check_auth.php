<?php
/**
 * check_auth.php - Secure endpoint to verify admin session status for frontend visibility
 */
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$isAuthenticated = !empty($_SESSION['auth_user_id']) || (!empty($_SESSION['is_authorized_admin']) && $_SESSION['is_authorized_admin'] === true);
$role = $_SESSION['auth_user_role'] ?? ($_SESSION['admin_role'] ?? null);
$name = $_SESSION['auth_user_name'] ?? ($_SESSION['admin_user'] ?? null);
$isAdmin = ($role === 'Admin' || $role === 'admin');

echo json_encode([
    'is_authenticated' => $isAuthenticated,
    'is_admin'         => $isAdmin,
    'role'             => $role,
    'name'             => $name,
    'user_id'          => $_SESSION['auth_user_id'] ?? null,
    'referral_code'    => $_SESSION['auth_user_ref'] ?? null
], JSON_UNESCAPED_UNICODE);
