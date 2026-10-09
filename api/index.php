<?php
/**
 * api/index.php - API Router
 * Routes API requests to appropriate endpoint files
 */

header('Content-Type: application/json; charset=utf-8');

// Get the request path
$requestUri = $_SERVER['REQUEST_URI'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);

// Remove script directory from request URI
$path = str_replace($scriptDir, '', $requestUri);
$path = trim($path, '/');

// Split path into parts
$parts = explode('/', $path);

// Get the endpoint (first part)
$endpoint = $parts[0] ?? null;

// Set PATH_INFO for the endpoint files
$_SERVER['PATH_INFO'] = '/' . implode('/', array_slice($parts, 1));

// Route to appropriate endpoint
switch ($endpoint) {
    case 'sales':
        require_once __DIR__ . '/sales.php';
        break;
    
    case 'submissions':
        require_once __DIR__ . '/submissions.php';
        break;
    
    case 'public':
        require_once __DIR__ . '/public.php';
        break;
    
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint not found',
            'available_endpoints' => [
                '/api/sales' => 'Sales employees management',
                '/api/submissions' => 'Client submissions management',
                '/api/public/forms/{slug}' => 'Public form validation',
                '/api/public/forms/{slug}/submissions' => 'Public form submission'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
}
