<?php
/**
 * api/submissions.php - Client Submissions API Endpoints
 * REST API for managing client submissions
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

// Route: GET /api/submissions - List all submissions (with filters)
// Route: GET /api/submissions/{id} - Get specific submission
// Route: PUT /api/submissions/{id} - Update submission
// Route: DELETE /api/submissions/{id} - Delete submission

try {
    // GET /api/submissions - List all submissions with filters
    if ($method === 'GET' && empty($path)) {
        // Parse query parameters for filtering
        $salesId = isset($_GET['sales_id']) ? (int)$_GET['sales_id'] : null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        // Build query
        $sql = "SELECT c.*, s.name as sales_name, s.email as sales_email 
                FROM clients c 
                LEFT JOIN sales_employees s ON c.sales_id = s.id 
                WHERE 1=1";
        $params = [];
        
        if ($salesId) {
            $sql .= " AND c.sales_id = :sales_id";
            $params[':sales_id'] = $salesId;
        }
        
        if ($dateFrom) {
            $sql .= " AND c.created_at >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND c.created_at <= :date_to";
            $params[':date_to'] = $dateTo;
        }
        
        $sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $submissions = $stmt->fetchAll();
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM clients WHERE 1=1";
        $countParams = [];
        
        if ($salesId) {
            $countSql .= " AND sales_id = :sales_id";
            $countParams[':sales_id'] = $salesId;
        }
        
        if ($dateFrom) {
            $countSql .= " AND created_at >= :date_from";
            $countParams[':date_from'] = $dateFrom;
        }
        
        if ($dateTo) {
            $countSql .= " AND created_at <= :date_to";
            $countParams[':date_to'] = $dateTo;
        }
        
        $countStmt = $pdo->prepare($countSql);
        foreach ($countParams as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetchColumn();
        
        successResponse([
            'submissions' => $submissions,
            'total' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset
        ], 'Submissions retrieved successfully');
    }
    
    // Extract ID from path
    $pathParts = explode('/', trim($path, '/'));
    $submissionId = isset($pathParts[0]) ? (int)$pathParts[0] : null;
    
    if (!$submissionId) {
        errorResponse('Invalid submission ID', 400, 'INVALID_ID');
    }
    
    // GET /api/submissions/{id} - Get specific submission
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT c.*, s.name as sales_name, s.email as sales_email 
            FROM clients c 
            LEFT JOIN sales_employees s ON c.sales_id = s.id 
            WHERE c.id = :id
        ");
        $stmt->execute([':id' => $submissionId]);
        $submission = $stmt->fetch();
        
        if (!$submission) {
            errorResponse('Submission not found', 404, 'NOT_FOUND');
        }
        
        // Decode attachments JSON
        if (!empty($submission['attachments'])) {
            $submission['attachments'] = json_decode($submission['attachments'], true);
        }
        
        successResponse($submission, 'Submission retrieved successfully');
    }
    
    // PUT /api/submissions/{id} - Update submission
    if ($method === 'PUT') {
        $body = getRequestBody();
        
        // Check if submission exists
        $checkSubmission = $pdo->prepare("SELECT id FROM clients WHERE id = :id");
        $checkSubmission->execute([':id' => $submissionId]);
        if (!$checkSubmission->fetch()) {
            errorResponse('Submission not found', 404, 'NOT_FOUND');
        }
        
        // Build update query dynamically
        $updateFields = [];
        $params = [':id' => $submissionId];
        
        $allowedFields = ['name', 'phone', 'email', 'id_type', 'id_number', 'governorate', 
                          'source', 'project', 'contract_location', 'unit_type', 'eoi_number', 
                          'payment_period', 'paid_5_percent', 'addresses', 'sales_id'];
        
        foreach ($allowedFields as $field) {
            if (isset($body[$field])) {
                $updateFields[] = "$field = :$field";
                $params[":$field"] = $body[$field];
            }
        }
        
        if (empty($updateFields)) {
            errorResponse('No fields to update', 400, 'NO_FIELDS');
        }
        
        $sql = "UPDATE clients SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Get updated record
        $updatedSubmission = $pdo->prepare("
            SELECT c.*, s.name as sales_name, s.email as sales_email 
            FROM clients c 
            LEFT JOIN sales_employees s ON c.sales_id = s.id 
            WHERE c.id = :id
        ");
        $updatedSubmission->execute([':id' => $submissionId]);
        $submissionData = $updatedSubmission->fetch();
        
        // Decode attachments JSON
        if (!empty($submissionData['attachments'])) {
            $submissionData['attachments'] = json_decode($submissionData['attachments'], true);
        }
        
        successResponse($submissionData, 'Submission updated successfully');
    }
    
    // DELETE /api/submissions/{id} - Delete submission
    if ($method === 'DELETE') {
        // Check if submission exists
        $checkSubmission = $pdo->prepare("SELECT id, attachments FROM clients WHERE id = :id");
        $checkSubmission->execute([':id' => $submissionId]);
        $submission = $checkSubmission->fetch();
        
        if (!$submission) {
            errorResponse('Submission not found', 404, 'NOT_FOUND');
        }
        
        // Delete attachment files
        if (!empty($submission['attachments'])) {
            $files = json_decode($submission['attachments'], true);
            if (is_array($files)) {
                foreach ($files as $file) {
                    $filePath = __DIR__ . '/../' . $file;
                    if (file_exists($filePath) && is_file($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
        }
        
        // Delete submission
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = :id");
        $stmt->execute([':id' => $submissionId]);
        
        successResponse(['id' => $submissionId], 'Submission deleted successfully');
    }
    
    // Method not allowed
    errorResponse('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    
} catch (PDOException $e) {
    errorResponse('Database error: ' . $e->getMessage(), 500, 'DATABASE_ERROR');
} catch (Exception $e) {
    errorResponse('Server error: ' . $e->getMessage(), 500, 'SERVER_ERROR');
}
