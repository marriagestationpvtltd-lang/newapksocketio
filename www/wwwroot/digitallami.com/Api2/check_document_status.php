<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// Database configuration - Update these with your actual credentials
$host = DB_HOST;
$dbname = DB_NAME;
$username = DB_USER;
$password = DB_PASS;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Handle preflight requests

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$userId = isset($input['user_id']) ? intval($input['user_id']) : null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    // Query to get user status and rejection reason
    $stmt = $pdo->prepare("SELECT status, reject_reason, document_upload_date FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Determine if document is uploaded
    $hasUploaded = false;
    $status = $user['status'] ?? 'not_uploaded';
    
    if ($status === 'pending' || $status === 'approved' || $status === 'rejected') {
        $hasUploaded = true;
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'status' => $status,
        'reject_reason' => $user['reject_reason'] ?? '',
        'upload_date' => $user['document_upload_date'] ?? null,
        'has_uploaded' => $hasUploaded,
        'message' => $hasUploaded ? 'Document found' : 'No document uploaded'
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>