<?php
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json");

require_once __DIR__ . '/../shared/activity_logger.php';

// ---------------- DB CONNECTION ----------------
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}

// ---------------- PARAMS ----------------
if (!isset($_POST['proposal_id'], $_POST['user_id'])) {
    echo json_encode(["success" => false, "message" => "Missing parameters"]);
    exit;
}

$proposalId = (int) $_POST['proposal_id'];
$userId     = (int) $_POST['user_id'];

try {

    // ---------------- FETCH PROPOSAL ----------------
    $sql = "
        SELECT id, sender_id, receiver_id, status 
        FROM proposals 
        WHERE id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $proposalId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Proposal not found"]);
        exit;
    }

    $proposal = $result->fetch_assoc();

    // ---------------- CHECK STATUS ----------------
    if ($proposal['status'] !== 'pending') {
        echo json_encode([
            "success" => false,
            "message" => "Proposal already processed"
        ]);
        exit;
    }

    // ---------------- AUTHORIZATION ----------------
    if ($proposal['sender_id'] != $userId && $proposal['receiver_id'] != $userId) {
        echo json_encode([
            "success" => false,
            "message" => "You are not authorized to reject this proposal"
        ]);
        exit;
    }

    // ---------------- REJECT PROPOSAL ----------------
    $updateSql = "UPDATE proposals SET status = 'rejected' WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $proposalId);

    if (!$updateStmt->execute()) {
        echo json_encode(["success" => false, "message" => "Failed to reject proposal"]);
        exit;
    }

    // ---------------- SEND NOTIFICATION (OPTIONAL) ----------------
    $otherUserId = ($proposal['sender_id'] == $userId)
        ? $proposal['receiver_id']
        : $proposal['sender_id'];

    try {
        $notifSql = "
            INSERT INTO notifications 
            (user_id, title, message, type, reference_id, created_at)
            VALUES (?, 'Proposal Rejected', 'Your proposal has been rejected', 'proposal', ?, NOW())
        ";
        $notifStmt = $conn->prepare($notifSql);
        $notifStmt->bind_param("ii", $otherUserId, $proposalId);
        $notifStmt->execute();
    } catch (Exception $e) {
        // Ignore notification failure
    }

    // ---------------- LOG ACTIVITY ----------------
    try {
        $actPdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $nStmt = $actPdo->prepare("SELECT id, CONCAT(firstName,' ',lastName) AS name FROM users WHERE id IN (:a,:b)");
        $nStmt->execute([':a' => $userId, ':b' => $otherUserId]);
        $nMap = [];
        foreach ($nStmt->fetchAll(PDO::FETCH_ASSOC) as $nr) { $nMap[$nr['id']] = $nr['name']; }
        $rejectorName = $nMap[$userId]       ?? "User $userId";
        $otherName    = $nMap[$otherUserId]  ?? "User $otherUserId";
        logActivity($actPdo, [
            'user_id'       => $userId,
            'user_name'     => $rejectorName,
            'target_id'     => $otherUserId,
            'target_name'   => $otherName,
            'activity_type' => 'request_rejected',
            'description'   => "$rejectorName le $otherName ko proposal reject garyo",
        ]);
    } catch (Exception $e) {
        // Never let activity logging break the response
    }

    echo json_encode([
        "success" => true,
        "message" => "Proposal rejected successfully"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Server error",
        "debug"   => $e->getMessage()
    ]);
}

$conn->close();
?>
