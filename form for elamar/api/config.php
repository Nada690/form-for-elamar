<?php
/**
 * api/config.php - API Configuration
 * Base configuration for REST API
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Direct access forbidden']));
}

// CORS Headers (adjust as needed for production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set JSON content type
header('Content-Type: application/json; charset=utf-8');

// Database configuration
require_once __DIR__ . '/../db.php';

// API Response Helper Functions
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function errorResponse($message, $statusCode = 400, $errorCode = null) {
    $response = [
        'success' => false,
        'error' => $message
    ];
    if ($errorCode !== null) {
        $response['code'] = $errorCode;
    }
    jsonResponse($response, $statusCode);
}

function successResponse($data, $message = null) {
    $response = [
        'success' => true,
        'data' => $data
    ];
    if ($message !== null) {
        $response['message'] = $message;
    }
    jsonResponse($response, 200);
}

// Generate unique slug
function generateUniqueSlug($name, $pdo) {
    $baseSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    if (empty($baseSlug)) $baseSlug = 'sales';
    
    $suffix = substr(md5(uniqid()), 0, 6);
    $slug = $baseSlug . '-' . $suffix;
    
    // Ensure uniqueness
    $checkSlug = $pdo->prepare("SELECT id FROM sales_employees WHERE slug = :slug LIMIT 1");
    $checkSlug->execute([':slug' => $slug]);
    while ($checkSlug->fetch()) {
        $suffix = substr(md5(uniqid()), 0, 6);
        $slug = $baseSlug . '-' . $suffix;
        $checkSlug->execute([':slug' => $slug]);
    }
    
    return $slug;
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request body for POST/PUT
function getRequestBody() {
    $rawInput = file_get_contents('php://input');
    $rawInput = preg_replace('/^\xEF\xBB\xBF/', '', $rawInput);
    return json_decode($rawInput, true) ?? [];
}
