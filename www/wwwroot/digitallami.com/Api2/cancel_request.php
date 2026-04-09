<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../shared/activity_logger.php';

// Database configuration
$dbHost = DB_HOST;
$dbName = DB_NAME;
$dbUser = DB_USER;
$dbPass = DB_PASS;

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Support both JSON and form data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    // Get parameters
    $sender_id = isset($input['sender_id']) ? intval($input['sender_id']) : null;
    $receiver_id = isset($input['receiver_id']) ? intval($input['receiver_id']) : null;
    $request_type = isset($input['request_type']) ? $input['request_type'] : null;

    // Validation
    $valid_types = ['Photo', 'Profile', 'Chat'];

    if (!$sender_id || !$receiver_id || !in_array($request_type, $valid_types)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid input. sender_id, receiver_id, and valid request_type required."
        ]);
        exit;
    }

    // Delete the request where current user is the sender
    $deleteStmt = $pdo->prepare("
        DELETE FROM proposals
        WHERE sender_id = :sender_id
        AND receiver_id = :receiver_id
        AND request_type = :request_type
        AND status = 'pending'
    ");

    $deleteStmt->execute([
        ':sender_id' => $sender_id,
        ':receiver_id' => $receiver_id,
        ':request_type' => $request_type
    ]);

    $rowsAffected = $deleteStmt->rowCount();

    if ($rowsAffected > 0) {
        // Resolve names for activity log
        $nStmt = $pdo->prepare("SELECT id, CONCAT(firstName,' ',lastName) AS name FROM users WHERE id IN (:s,:r)");
        $nStmt->execute([':s' => $sender_id, ':r' => $receiver_id]);
        $nMap = [];
        foreach ($nStmt->fetchAll() as $nr) {
            $nMap[$nr['id']] = $nr['name'];
        }
        $sName = $nMap[$sender_id]   ?? "User $sender_id";
        $rName = $nMap[$receiver_id] ?? "User $receiver_id";

        logActivity($pdo, [
            'user_id'       => $sender_id,
            'user_name'     => $sName,
            'target_id'     => $receiver_id,
            'target_name'   => $rName,
            'activity_type' => 'request_cancelled',
            'description'   => "$sName le $rName lai pathayeko $request_type request cancel garyo",
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Request cancelled successfully"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No pending request found to cancel"
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
