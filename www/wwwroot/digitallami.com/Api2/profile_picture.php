<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../shared/activity_logger.php';

// Database configuration
$host = DB_HOST;
$db_name = DB_NAME;
$username = DB_USER;
$password = DB_PASS;
// Create connection
$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]));
}

// Check if user ID is provided
$userid = isset($_POST['userid']) ? intval($_POST['userid']) : 0;
if ($userid <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid user ID"
    ]);
    exit;
}

// Check if file is uploaded
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] != 0) {
    echo json_encode([
        "status" => "error",
        "message" => "No file uploaded or file upload error"
    ]);
    exit;
}

// File details
$fileTmpPath = $_FILES['profile_picture']['tmp_name'];
$fileName = $_FILES['profile_picture']['name'];
$fileSize = $_FILES['profile_picture']['size'];
$fileType = $_FILES['profile_picture']['type'];

// Create uploads directory if not exists
$uploadDir = __DIR__ . '/uploads/profile_pictures/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique file name to avoid overwriting
$fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
$newFileName = 'profilepicture_' . $userid . '.' . $fileExt;
$destPath = $uploadDir . $newFileName;

// Move uploaded file
if(move_uploaded_file($fileTmpPath, $destPath)) {
    // Store relative path in DB
    $relativePath = 'uploads/profile_pictures/' . $newFileName;
    $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $relativePath, $userid);

    if ($stmt->execute()) {
        // Log photo upload activity
        try {
            $actPdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $nStmt = $actPdo->prepare("SELECT CONCAT(firstName,' ',lastName) AS name FROM users WHERE id = :id LIMIT 1");
            $nStmt->execute([':id' => $userid]);
            $nr = $nStmt->fetch(PDO::FETCH_ASSOC);
            $uName = $nr ? $nr['name'] : "User $userid";
            logActivity($actPdo, [
                'user_id'       => $userid,
                'user_name'     => $uName,
                'activity_type' => 'photo_uploaded',
                'description'   => "$uName le profile photo upload garyo",
            ]);
        } catch (Exception $e) {
            // Never let activity logging break the response
        }

        echo json_encode([
            "status" => "success",
            "message" => "Profile picture updated successfully",
            "path" => $relativePath
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to update database"
        ]);
    }

    $stmt->close();
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to move uploaded file"
    ]);
}

$conn->close();
?>
