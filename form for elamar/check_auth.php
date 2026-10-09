<?php
/**
 * check_auth.php - Secure endpoint to verify admin session status for frontend visibility
 */
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

header('Content-Type: application/json; charset=utf-8');

$isAdmin = !empty($_SESSION['is_authorized_admin']) 
           && $_SESSION['is_authorized_admin'] === true 
           && !empty($_SESSION['admin_role']) 
           && $_SESSION['admin_role'] === 'admin';

echo json_encode([
    'is_admin' => $isAdmin,
    'username' => $isAdmin ? ($_SESSION['admin_user'] ?? 'Admin') : null
], JSON_UNESCAPED_UNICODE);
