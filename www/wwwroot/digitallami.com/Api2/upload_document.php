<?php
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json");

// ---------------- DB CONNECTION ----------------
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db = DB_NAME;
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "DB connect failed"]);
    exit;
}

// ---------------- REQUIRED PARAM ----------------
$userid = isset($_POST['userid']) ? intval($_POST['userid']) : 0;
if ($userid <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid userid"]);
    exit;
}

// ---------------- OPTIONAL PARAMS ----------------
$documenttype     = $_POST['documenttype'] ?? null;
$documentidnumber = $_POST['documentidnumber'] ?? null;
$title            = $_POST['title'] ?? null;

// ---------------- FILE UPLOAD ----------------
$photoPath = null;

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {

    // Validate MIME type by inspecting actual file content
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['photo']['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    if (!array_key_exists($mimeType, $allowedMimes)) {
        echo json_encode(["status" => "error", "message" => "Invalid file type"]);
        exit;
    }

    if ($_FILES['photo']['size'] > 10 * 1024 * 1024) {
        echo json_encode(["status" => "error", "message" => "File too large (max 10MB)"]);
        exit;
    }

    $folder = "uploads/user_documents/";
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    $ext = $allowedMimes[$mimeType];
    $filename = "doc_" . $userid . "_" . bin2hex(random_bytes(8)) . "." . $ext;
    $filepath = $folder . $filename;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $filepath)) {
        $photoPath = $filepath;
    }
}

// ---------------- CHECK IF RECORD EXISTS ----------------
$check = $conn->prepare("SELECT id FROM user_documents WHERE userid = ?");
$check->bind_param("i", $userid);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {

    // UPDATE
    $sql = "UPDATE user_documents SET 
                documenttype = ?, 
                documentidnumber = ?, 
                title = IFNULL(?, title),
                photo = IFNULL(?, photo)
            WHERE userid = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $documenttype, $documentidnumber, $title, $photoPath, $userid);

} else {

    // INSERT
    $sql = "INSERT INTO user_documents 
            (userid, documenttype, documentidnumber, title, photo) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $userid, $documenttype, $documentidnumber, $title, $photoPath);
}

// ---------------- EXECUTE ----------------
if ($stmt->execute()) {

    // ✅ UPDATE USER STATUS TO PENDING
    $status = "pending";
    $updateUser = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $updateUser->bind_param("si", $status, $userid);
    $updateUser->execute();

    echo json_encode([
        "status" => "success",
        "message" => "Document uploaded, status set to pending"
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}

$conn->close();
?>
