<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../shared/activity_logger.php';

// DB CONNECTION
$conn = new mysqli("localhost", "ms", "ms", "ms");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}

// GET POST DATA - Use $_POST instead of json_decode
if (!isset($_POST['proposal_id'], $_POST['user_id'])) {
    echo json_encode(["success" => false, "message" => "Missing parameters"]);
    exit;
}

$proposalId = (int)$_POST['proposal_id'];
$userId = (int)$_POST['user_id'];

try {
    // 1. VERIFY USER CAN ACCEPT THIS PROPOSAL
    $checkSql = "SELECT receiver_id FROM proposals WHERE id = ? AND status = 'pending'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $proposalId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Proposal not found or already processed"]);
        exit;
    }

    $proposal = $checkResult->fetch_assoc();
    if ($proposal['receiver_id'] != $userId) {
        echo json_encode(["success" => false, "message" => "You are not authorized to accept this proposal"]);
        exit;
    }

    // 2. UPDATE PROPOSAL STATUS
    $updateSql = "UPDATE proposals SET status = 'accepted', updated_at = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $proposalId);

    if ($updateStmt->execute()) {
        // 3. GET SENDER ID TO SEND NOTIFICATION IF NEEDED
        $senderSql = "SELECT sender_id FROM proposals WHERE id = ?";
        $senderStmt = $conn->prepare($senderSql);
        $senderStmt->bind_param("i", $proposalId);
        $senderStmt->execute();
        $senderResult = $senderStmt->get_result();
        
        if ($senderResult->num_rows > 0) {
            $senderData = $senderResult->fetch_assoc();
            $senderId = $senderData['sender_id'];
            
            // 4. CREATE NOTIFICATION (OPTIONAL) - Check if notifications table exists
            try {
                $notifSql = "INSERT INTO notifications (user_id, title, message, type, reference_id, created_at) 
                             VALUES (?, 'Proposal Accepted', 'Your proposal has been accepted', 'proposal', ?, NOW())";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("ii", $senderId, $proposalId);
                $notifStmt->execute();
            } catch (Exception $e) {
                // Silently continue if notifications table doesn't exist
            }

            // 5. LOG ACTIVITY
            try {
                $actPdo = new PDO("mysql:host=localhost;dbname=ms;charset=utf8mb4", "ms", "ms",
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $nStmt = $actPdo->prepare("SELECT id, CONCAT(firstName,' ',lastName) AS name FROM users WHERE id IN (:a,:b)");
                $nStmt->execute([':a' => $userId, ':b' => $senderId]);
                $nMap = [];
                foreach ($nStmt->fetchAll(PDO::FETCH_ASSOC) as $nr) { $nMap[$nr['id']] = $nr['name']; }
                $acceptorName = $nMap[$userId]   ?? "User $userId";
                $senderName   = $nMap[$senderId] ?? "User $senderId";
                logActivity($actPdo, [
                    'user_id'       => $userId,
                    'user_name'     => $acceptorName,
                    'target_id'     => $senderId,
                    'target_name'   => $senderName,
                    'activity_type' => 'request_accepted',
                    'description'   => "$acceptorName le $senderName ko proposal accept garyo",
                ]);
            } catch (Exception $e) {
                // Never let activity logging break the response
            }
        }
        
        echo json_encode(["success" => true, "message" => "Proposal accepted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to accept proposal"]);
    }
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}

$conn->close();
?>