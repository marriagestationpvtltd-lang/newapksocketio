<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/mysqli_compat.php';
header('Content-Type: application/json');

// Database connection
$host = DB_HOST;
$db_name = DB_NAME;
$username = DB_USER;
$password = DB_PASS;
$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Base URL for profile pictures
$imageurl = APP_API2_BASE_URL;

// Logged-in user
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user_id']);
    exit;
}

/* ================= DELETE LIKE ================= */
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $receiver_id = intval($_GET['receiver_id'] ?? 0);

    if ($receiver_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid receiver_id']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM likes WHERE sender_id = ? AND receiver_id = ?");
    $stmt->bind_param("ii", $user_id, $receiver_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Like deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete like']);
    }

    $stmt->close();
    $conn->close();
    exit;
}

/* ================= FETCH LIKED USERS ================= */
$stmt = $conn->prepare("SELECT receiver_id FROM likes WHERE sender_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = stmt_get_result($stmt);

$receiver_ids = [];
while ($row = $res->fetch_assoc()) {
    $receiver_ids[] = $row['receiver_id'];
}
$stmt->close();

if (empty($receiver_ids)) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

$ids = implode(',', array_map('intval', $receiver_ids));

/* ================= FETCH USERS + PRIVACY ================= */
$sql = "
    SELECT id, firstName, lastName, profile_picture, isVerified, privacy
    FROM users
    WHERE id IN ($ids)
      AND isActive = 1 AND isDelete = 0
";
$result = $conn->query($sql);

$users_data = [];
$fetchedUsers = [];
while ($user = $result->fetch_assoc()) {
    $fetchedUsers[] = $user;
}

if (empty($fetchedUsers)) {
    echo json_encode(['status' => 'success', 'data' => []]);
    $conn->close();
    exit;
}

$fetchedIds = array_map(fn($u) => (int)$u['id'], $fetchedUsers);
$fetchedIdsStr = implode(',', $fetchedIds);

/* ── Pre-fetch photo requests (avoid N+1) ───────────────────── */
$sqlPhoto = "
    SELECT sender_id, receiver_id, status
    FROM proposals
    WHERE request_type = 'Photo'
      AND ((sender_id = $user_id AND receiver_id IN ($fetchedIdsStr))
           OR (sender_id IN ($fetchedIdsStr) AND receiver_id = $user_id))
    ORDER BY id DESC
";
$resPhoto = $conn->query($sqlPhoto);
$photoMap = [];
while ($pr = $resPhoto->fetch_assoc()) {
    $otherId = ($pr['sender_id'] == $user_id) ? (int)$pr['receiver_id'] : (int)$pr['sender_id'];
    if (!isset($photoMap[$otherId])) {
        $photoMap[$otherId] = ($pr['status'] === 'accepted') ? 'accepted' : 'pending';
    }
}

/* ── Pre-fetch cities (avoid N+1) ──────────────────────────── */
$sqlAddr = "SELECT userid, city FROM permanent_address WHERE userid IN ($fetchedIdsStr) GROUP BY userid";
$resAddr = $conn->query($sqlAddr);
$addrMap = [];
while ($a = $resAddr->fetch_assoc()) { $addrMap[(int)$a['userid']] = $a['city']; }

/* ── Pre-fetch designations (avoid N+1) ─────────────────────── */
$sqlEdu = "SELECT userid, designation FROM educationcareer WHERE userid IN ($fetchedIdsStr) GROUP BY userid";
$resEdu = $conn->query($sqlEdu);
$eduMap = [];
while ($e = $resEdu->fetch_assoc()) { $eduMap[(int)$e['userid']] = $e['designation']; }

foreach ($fetchedUsers as $user) {

    $uid = (int)$user['id'];

    /* -------- FINAL USER OBJECT -------- */
    $users_data[] = [
        "userid" => $uid,
        "firstName" => $user['firstName'],
        "lastName" => $user['lastName'],
        "isVerified" => (int)$user['isVerified'],
        "privacy" => (string)$user['privacy'],
        "profile_picture" => $imageurl . ($user['profile_picture'] ?? 'default.jpg'),
        "city" => $addrMap[$uid] ?? null,
        "designation" => $eduMap[$uid] ?? null,
        "photo_request" => $photoMap[$uid] ?? "not sent"   // ✅ pre-fetched
    ];
}

echo json_encode([
    "status" => "success",
    "data" => $users_data
]);

$conn->close();
?>
