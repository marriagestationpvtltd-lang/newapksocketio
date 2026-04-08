<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../shared/activity_logger.php';

// Database configuration
$dbHost = "127.0.0.1";
$dbName = "ms";
$dbUser = "ms";
$dbPass = "ms";

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8",
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ===============================
    // 🔥 SUPPORT JSON + FORM DATA
    // ===============================
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    // ===============================
    // 🔥 MAP KEYS
    // ===============================
    $sender_id = isset($input['sender_id']) 
        ? intval($input['sender_id']) 
        : (isset($input['myid']) ? intval($input['myid']) : null);

    $receiver_id = isset($input['receiver_id']) 
        ? intval($input['receiver_id']) 
        : (isset($input['userid']) ? intval($input['userid']) : null);

    $request_type = isset($input['request_type']) 
        ? $input['request_type'] 
        : 'Photo';

    // ===============================
    // ✅ VALIDATION
    // ===============================
    $valid_types = ['Photo', 'Profile', 'Chat'];

    if (!$sender_id || !$receiver_id || !in_array($request_type, $valid_types)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid input. sender_id, receiver_id, and valid request_type required."
        ]);
        exit;
    }

    // ❌ Prevent self request
    if ($sender_id == $receiver_id) {
        echo json_encode([
            "success" => false,
            "message" => "You cannot send request to yourself"
        ]);
        exit;
    }

    $status = 'pending';
    $created_at = date('Y-m-d H:i:s');

    // ===============================
    // 🔍 CHECK ONLY SAME TYPE
    // ===============================
    $checkStmt = $pdo->prepare("
        SELECT id 
        FROM proposals 
        WHERE sender_id = :sender_id 
        AND receiver_id = :receiver_id 
        AND request_type = :request_type
        LIMIT 1
    ");

    $checkStmt->execute([
        ':sender_id' => $sender_id,
        ':receiver_id' => $receiver_id,
        ':request_type' => $request_type
    ]);

    // ===============================
    // 🔄 IF SAME TYPE → UPDATE
    // ===============================
    if ($checkStmt->rowCount() > 0) {
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $updateStmt = $pdo->prepare("
            UPDATE proposals 
            SET status = :status, created_at = :created_at 
            WHERE id = :id
        ");

        $updateStmt->execute([
            ':status' => $status,
            ':created_at' => $created_at,
            ':id' => $row['id']
        ]);

        echo json_encode([
            "success" => true,
            "message" => "",
            "proposal_id" => $row['id']
        ]);

    } else {
        // ===============================
        // ➕ DIFFERENT TYPE → INSERT NEW
        // ===============================
        $insertStmt = $pdo->prepare("
            INSERT INTO proposals 
            (sender_id, receiver_id, request_type, status, created_at) 
            VALUES 
            (:sender_id, :receiver_id, :request_type, :status, :created_at)
        ");

        $insertStmt->execute([
            ':sender_id' => $sender_id,
            ':receiver_id' => $receiver_id,
            ':request_type' => $request_type,
            ':status' => $status,
            ':created_at' => $created_at
        ]);

        $newProposalId = $pdo->lastInsertId();

        // Resolve names for activity log
        $nStmt = $pdo->prepare("SELECT id, CONCAT(firstName,' ',lastName) AS name FROM users WHERE id IN (:s,:r)");
        $nStmt->execute([':s' => $sender_id, ':r' => $receiver_id]);
        $nMap = [];
        foreach ($nStmt->fetchAll() as $nr) { $nMap[$nr['id']] = $nr['name']; }
        $sName = $nMap[$sender_id]   ?? "User $sender_id";
        $rName = $nMap[$receiver_id] ?? "User $receiver_id";

        logActivity($pdo, [
            'user_id'       => $sender_id,
            'user_name'     => $sName,
            'target_id'     => $receiver_id,
            'target_name'   => $rName,
            'activity_type' => 'request_sent',
            'description'   => "$sName le $rName lai $request_type request pathayo",
        ]);

        echo json_encode([
            "success" => true,
            "message" => "",
            "proposal_id" => $newProposalId
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>