<?php
/**
 * api/sales.php - Sales Employees API Endpoints
 * REST API for managing sales employees
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

// Route: GET /api/sales - List all sales employees
// Route: POST /api/sales - Create new sales employee
// Route: GET /api/sales/{id} - Get specific sales employee
// Route: PUT /api/sales/{id} - Update sales employee
// Route: DELETE /api/sales/{id} - Delete sales employee

try {
    // GET /api/sales - List all sales
    if ($method === 'GET' && empty($path)) {
        $stmt = $pdo->query("
            SELECT s.*, 
                   (SELECT COUNT(*) FROM clients WHERE sales_id = s.id) as client_count 
            FROM sales_employees s 
            ORDER BY s.created_at DESC
        ");
        $sales = $stmt->fetchAll();
        
        successResponse($sales, 'Sales employees retrieved successfully');
    }
    
    // POST /api/sales - Create new sales employee
    if ($method === 'POST' && empty($path)) {
        $body = getRequestBody();
        
        // Validation
        if (empty($body['name'])) {
            errorResponse('Name is required', 400, 'NAME_REQUIRED');
        }
        if (empty($body['email'])) {
            errorResponse('Email is required', 400, 'EMAIL_REQUIRED');
        }
        
        // Check if email already exists
        $checkEmail = $pdo->prepare("SELECT id FROM sales_employees WHERE email = :email LIMIT 1");
        $checkEmail->execute([':email' => $body['email']]);
        if ($checkEmail->fetch()) {
            errorResponse('Email already exists', 409, 'EMAIL_EXISTS');
        }
        
        // Generate unique slug
        $slug = generateUniqueSlug($body['name'], $pdo);
        
        // Insert new sales employee
        $stmt = $pdo->prepare("
            INSERT INTO sales_employees (name, email, slug, active) 
            VALUES (:name, :email, :slug, 1)
        ");
        $stmt->execute([
            ':name' => $body['name'],
            ':email' => $body['email'],
            ':slug' => $slug
        ]);
        
        $salesId = $pdo->lastInsertId();
        
        // Get the created record
        $newSales = $pdo->prepare("SELECT * FROM sales_employees WHERE id = :id");
        $newSales->execute([':id' => $salesId]);
        $salesData = $newSales->fetch();
        
        // Add the form URL
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        $salesData['form_url'] = $baseUrl . '/../form.php?slug=' . $slug;
        
        successResponse($salesData, 'Sales employee created successfully');
    }
    
    // Extract ID from path
    $pathParts = explode('/', trim($path, '/'));
    $salesId = isset($pathParts[0]) ? (int)$pathParts[0] : null;
    
    if (!$salesId) {
        errorResponse('Invalid sales ID', 400, 'INVALID_ID');
    }
    
    // GET /api/sales/{id} - Get specific sales employee
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   (SELECT COUNT(*) FROM clients WHERE sales_id = s.id) as client_count 
            FROM sales_employees s 
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => $salesId]);
        $sales = $stmt->fetch();
        
        if (!$sales) {
            errorResponse('Sales employee not found', 404, 'NOT_FOUND');
        }
        
        // Add the form URL
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        $sales['form_url'] = $baseUrl . '/../form.php?slug=' . $sales['slug'];
        
        successResponse($sales, 'Sales employee retrieved successfully');
    }
    
    // PUT /api/sales/{id} - Update sales employee
    if ($method === 'PUT') {
        $body = getRequestBody();
        
        // Check if sales exists
        $checkSales = $pdo->prepare("SELECT id FROM sales_employees WHERE id = :id");
        $checkSales->execute([':id' => $salesId]);
        if (!$checkSales->fetch()) {
            errorResponse('Sales employee not found', 404, 'NOT_FOUND');
        }
        
        // Build update query dynamically
        $updateFields = [];
        $params = [':id' => $salesId];
        
        if (!empty($body['name'])) {
            $updateFields[] = 'name = :name';
            $params[':name'] = $body['name'];
        }
        
        if (!empty($body['email'])) {
            // Check if email already exists for another sales
            $checkEmail = $pdo->prepare("SELECT id FROM sales_employees WHERE email = :email AND id != :id LIMIT 1");
            $checkEmail->execute([':email' => $body['email'], ':id' => $salesId]);
            if ($checkEmail->fetch()) {
                errorResponse('Email already exists', 409, 'EMAIL_EXISTS');
            }
            $updateFields[] = 'email = :email';
            $params[':email'] = $body['email'];
        }
        
        if (isset($body['active'])) {
            $updateFields[] = 'active = :active';
            $params[':active'] = $body['active'] ? 1 : 0;
        }
        
        if (empty($updateFields)) {
            errorResponse('No fields to update', 400, 'NO_FIELDS');
        }
        
        $sql = "UPDATE sales_employees SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Get updated record
        $updatedSales = $pdo->prepare("SELECT * FROM sales_employees WHERE id = :id");
        $updatedSales->execute([':id' => $salesId]);
        $salesData = $updatedSales->fetch();
        
        // Add the form URL
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        $salesData['form_url'] = $baseUrl . '/../form.php?slug=' . $salesData['slug'];
        
        successResponse($salesData, 'Sales employee updated successfully');
    }
    
    // DELETE /api/sales/{id} - Delete sales employee
    if ($method === 'DELETE') {
        // Check if sales exists
        $checkSales = $pdo->prepare("SELECT id FROM sales_employees WHERE id = :id");
        $checkSales->execute([':id' => $salesId]);
        if (!$checkSales->fetch()) {
            errorResponse('Sales employee not found', 404, 'NOT_FOUND');
        }
        
        // Delete sales employee (clients will have sales_id set to NULL due to ON DELETE SET NULL)
        $stmt = $pdo->prepare("DELETE FROM sales_employees WHERE id = :id");
        $stmt->execute([':id' => $salesId]);
        
        successResponse(['id' => $salesId], 'Sales employee deleted successfully');
    }
    
    // Method not allowed
    errorResponse('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    
} catch (PDOException $e) {
    errorResponse('Database error: ' . $e->getMessage(), 500, 'DATABASE_ERROR');
} catch (Exception $e) {
    errorResponse('Server error: ' . $e->getMessage(), 500, 'SERVER_ERROR');
}
