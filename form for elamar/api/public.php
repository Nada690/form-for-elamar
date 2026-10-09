<?php
/**
 * api/public.php - Public Form API Endpoints
 * REST API for public form submissions with slug validation
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

// Route: GET /api/public/forms/{slug} - Validate slug and get form metadata
// Route: POST /api/public/forms/{slug}/submissions - Submit client data for specific sales

try {
    // Extract slug from path or query parameter
    $pathParts = explode('/', trim($path, '/'));
    $slug = !empty($pathParts[0]) ? $pathParts[0] : (trim($_GET['slug'] ?? ''));
    $action = $pathParts[1] ?? null;
    
    if (!$slug) {
        errorResponse('Slug is required', 400, 'SLUG_REQUIRED');
    }
    
    // GET /api/public/forms/{slug} - Validate slug and get form metadata
    if ($method === 'GET' && !$action) {
        $stmt = $pdo->prepare("
            SELECT id, name, email, slug, active 
            FROM sales_employees 
            WHERE slug = :slug AND active = 1 
            LIMIT 1
        ");
        $stmt->execute([':slug' => $slug]);
        $sales = $stmt->fetch();
        
        if (!$sales) {
            errorResponse('Invalid or inactive form link', 404, 'INVALID_SLUG');
        }
        
        // Return minimal sales info (don't expose sensitive data)
        successResponse([
            'sales_id' => $sales['id'],
            'sales_name' => $sales['name'],
            'slug' => $sales['slug'],
            'form_valid' => true
        ], 'Form link is valid');
    }
    
    // POST /api/public/forms/{slug}/submissions - Submit client data
    if ($method === 'POST' && $action === 'submissions') {
        // Validate slug first
        $stmt = $pdo->prepare("
            SELECT id, name, email, slug, active 
            FROM sales_employees 
            WHERE slug = :slug AND active = 1 
            LIMIT 1
        ");
        $stmt->execute([':slug' => $slug]);
        $sales = $stmt->fetch();
        
        if (!$sales) {
            errorResponse('Invalid or inactive form link', 404, 'INVALID_SLUG');
        }
        
        $salesId = $sales['id'];
        
        // Get request body
        $body = getRequestBody();
        
        // If multipart/form-data (file uploads), merge with $_POST
        if (!empty($_POST)) {
            $body = array_merge($body, $_POST);
        }
        
        // Validation
        $errors = [];
        
        if (empty($body['name'])) {
            $errors[] = 'Client name is required';
        }
        
        if (empty($body['phone'])) {
            $errors[] = 'Phone number is required';
        }
        
        if (empty($body['email']) || !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        
        if (empty($body['id_number'])) {
            $errors[] = 'ID number is required';
        }
        
        if (empty($body['eoi_number'])) {
            $errors[] = 'Unit code (EOI number) is required';
        }
        
        if (empty($body['contract_location'])) {
            $errors[] = 'Contract location is required';
        }
        
        if (empty($body['payment_period'])) {
            $errors[] = 'Payment period is required';
        }
        
        if (empty($body['paid_5_percent'])) {
            $errors[] = '5% payment status is required';
        }
        
        if (!empty($errors)) {
            errorResponse('Validation failed: ' . implode(', ', $errors), 422, 'VALIDATION_ERROR');
        }
        
        // Check for duplicate unit code
        $checkEoi = $pdo->prepare("SELECT id FROM clients WHERE eoi_number = :eoi_number LIMIT 1");
        $checkEoi->execute([':eoi_number' => $body['eoi_number']]);
        if ($checkEoi->fetch()) {
            errorResponse('Unit code already exists', 409, 'DUPLICATE_UNIT_CODE');
        }
        
        // Handle file uploads
        $uploadedFiles = [];
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        
        if (!empty($_FILES['attachments'])) {
            $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'bmp', 'tiff'];
            $fileCount = is_array($_FILES['attachments']['name']) ? count($_FILES['attachments']['name']) : 1;
            
            for ($i = 0; $i < $fileCount; $i++) {
                $fileName = is_array($_FILES['attachments']['name']) ? $_FILES['attachments']['name'][$i] : $_FILES['attachments']['name'];
                $tmpName = is_array($_FILES['attachments']['tmp_name']) ? $_FILES['attachments']['tmp_name'][$i] : $_FILES['attachments']['tmp_name'];
                $fileSize = is_array($_FILES['attachments']['size']) ? $_FILES['attachments']['size'][$i] : $_FILES['attachments']['size'];
                $fileErr = is_array($_FILES['attachments']['error']) ? $_FILES['attachments']['error'][$i] : $_FILES['attachments']['error'];
                
                if ($fileErr === UPLOAD_ERR_OK && is_uploaded_file($tmpName)) {
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExts) && $fileSize <= 25 * 1024 * 1024) {
                        $cleanBaseName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
                        $newFileName = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $cleanBaseName . '.' . $ext;
                        $targetPath = $uploadDir . $newFileName;
                        
                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $uploadedFiles[] = 'uploads/' . $newFileName;
                        }
                    }
                }
            }
        }
        $attachmentsJson = json_encode($uploadedFiles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Prepare data for insertion
        $data = [
            ':name' => $body['name'],
            ':phone' => $body['phone'],
            ':email' => $body['email'],
            ':id_type' => $body['id_type'] ?? 'national_id',
            ':id_number' => $body['id_number'],
            ':governorate' => $body['governorate'] ?? $body['contract_location'] ?? 'الساحل',
            ':source' => $body['source'] ?? 'مباشر',
            ':project' => $body['project'] ?? 'شورز — Shores (الساحل الشمالي)',
            ':contract_location' => $body['contract_location'],
            ':unit_type' => $body['unit_type'] ?? '',
            ':eoi_number' => $body['eoi_number'],
            ':payment_period' => $body['payment_period'],
            ':paid_5_percent' => $body['paid_5_percent'],
            ':addresses' => $body['addresses'] ?? '',
            ':attachments' => $attachmentsJson,
            ':lang' => $body['lang'] ?? 'ar',
            ':sales_id' => $salesId
        ];
        
        // Insert submission
        $sql = "INSERT INTO clients 
                (name, phone, email, id_type, id_number, governorate, source, project, 
                 contract_location, unit_type, eoi_number, payment_period, paid_5_percent, 
                 addresses, attachments, lang, sales_id) 
                VALUES 
                (:name, :phone, :email, :id_type, :id_number, :governorate, :source, :project, 
                 :contract_location, :unit_type, :eoi_number, :payment_period, :paid_5_percent, 
                 :addresses, :attachments, :lang, :sales_id)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        
        $submissionId = $pdo->lastInsertId();
        
        // Get the created submission
        $newSubmission = $pdo->prepare("
            SELECT c.*, s.name as sales_name, s.email as sales_email 
            FROM clients c 
            LEFT JOIN sales_employees s ON c.sales_id = s.id 
            WHERE c.id = :id
        ");
        $newSubmission->execute([':id' => $submissionId]);
        $submissionData = $newSubmission->fetch();
        
        // Decode attachments JSON
        if (!empty($submissionData['attachments'])) {
            $submissionData['attachments'] = json_decode($submissionData['attachments'], true);
        }
        
        successResponse($submissionData, 'Client submission created successfully');
    }
    
    // Method not allowed
    errorResponse('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    
} catch (PDOException $e) {
    errorResponse('Database error: ' . $e->getMessage(), 500, 'DATABASE_ERROR');
} catch (Exception $e) {
    errorResponse('Server error: ' . $e->getMessage(), 500, 'SERVER_ERROR');
}
