<?php
header('Content-Type: application/json');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// ✅ Nepal timezone
date_default_timezone_set('Asia/Kathmandu');

include 'db_connect.php';

// ✅ Ensure MySQL also uses Nepal time
$conn->query("SET time_zone = '+05:45'");

// ✅ Base URL for images
$base_url = APP_API2_BASE_URL;

// ✅ Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 30;
$offset = ($page - 1) * $limit;

// ✅ Search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

/* ----------------------------------------------------------
   STEP 1: Get total count of users
---------------------------------------------------------- */
$countQuery = "SELECT COUNT(*) as total FROM users WHERE id != 1";
$countParams = [];
$countTypes = "";

if (!empty($search)) {
    $countQuery .= " AND (firstName LIKE ? OR lastName LIKE ? OR CONCAT(firstName, ' ', lastName) LIKE ?)";
    $searchParam = "%{$search}%";
    $countParams = [$searchParam, $searchParam, $searchParam];
    $countTypes = "sss";
}

$countStmt = $conn->prepare($countQuery);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalUsers = $countResult->fetch_assoc()['total'];

/* ----------------------------------------------------------
   STEP 2: Get paginated users with optional search
---------------------------------------------------------- */
$userQuery = "SELECT u.*,
    (SELECT MAX(created_at) FROM chats WHERE sender_id = u.id OR receiver_id = u.id) as last_chat_time
    FROM users u
    WHERE u.id != 1";

$userParams = [];
$userTypes = "";

if (!empty($search)) {
    $userQuery .= " AND (u.firstName LIKE ? OR u.lastName LIKE ? OR CONCAT(u.firstName, ' ', u.lastName) LIKE ?)";
    $searchParam = "%{$search}%";
    $userParams = [$searchParam, $searchParam, $searchParam];
    $userTypes = "sss";
}

// Order by: recent chats first, then by last login
$userQuery .= " ORDER BY last_chat_time DESC, u.lastLogin DESC LIMIT ? OFFSET ?";
$userParams[] = $limit;
$userParams[] = $offset;
$userTypes .= "ii";

$userStmt = $conn->prepare($userQuery);
if (!empty($userParams)) {
    $userStmt->bind_param($userTypes, ...$userParams);
}
$userStmt->execute();
$userResult = $userStmt->get_result();

$responseData = [];

while ($user = $userResult->fetch_assoc()) {
    $userId = $user['id'];

    // Full name
    $name = trim($user['firstName'] . ' ' . $user['lastName']);

    // ===============================
    // 🖼️ PROFILE IMAGE
    // ===============================
    if (!empty($user['profile_picture'])) {
        if (strpos($user['profile_picture'], 'http') === 0) {
            $profile_picture = $user['profile_picture'];
        } else {
            $profile_picture = $base_url . $user['profile_picture'];
        }
    } else {
        $profile_picture = $base_url . "default.png"; // fallback image
    }

    // ===============================
    // 💬 LATEST CHAT MESSAGE
    // ===============================
    $chat_message = "";
    $chat_message_type = "text";
    $chatQuery = $conn->prepare("
        SELECT message, messageType
        FROM chats
        WHERE sender_id = ? OR receiver_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $chatQuery->bind_param("ii", $userId, $userId);
    $chatQuery->execute();
    $chatRes = $chatQuery->get_result();
    if ($chatRes->num_rows > 0) {
        $chatRow = $chatRes->fetch_assoc();
        $chat_message = $chatRow['message'];
        $chat_message_type = $chatRow['messageType'] ?? 'text';
    }

    // ===============================
    // ❤️ MATCH COUNT
    // ===============================
    $matchesCount = 0;
    $matchQuery = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM users u
        JOIN userpersonaldetail upd ON u.id = upd.userId
        WHERE u.gender != ? AND u.id != ?
    ");
    $matchQuery->bind_param("si", $user['gender'], $userId);
    $matchQuery->execute();
    $matchRes = $matchQuery->get_result();
    if ($matchRes->num_rows > 0) {
        $matchRow = $matchRes->fetch_assoc();
        $matchesCount = intval($matchRow['total']);
    }

    // ===============================
    // 💰 IS PAID (based on usertype from users table)
    // ===============================
    $is_paid = false;
    
    // Check if usertype column exists and determine paid status
    if (isset($user['usertype']) && !empty($user['usertype'])) {
        $usertype = strtolower(trim($user['usertype']));
        
        // Define what usertype values indicate a paid member
        // ADJUST THESE VALUES BASED ON YOUR ACTUAL DATABASE VALUES
        $paidUsertypes = ['paid', 'premium', 'vip', 'gold', 'member', 'subscribed', 'active', 'pro', 'plus', 'elite'];
        
        // Check if the usertype matches any paid status
        if (in_array($usertype, $paidUsertypes)) {
            $is_paid = true;
        }
        
        // If usertype is numeric (e.g., 0 = free, 1 = paid, 2 = premium, etc.)
        // Uncomment and modify this if your usertype uses numeric values
        /*
        $usertype_numeric = intval($usertype);
        if ($usertype_numeric >= 1) { // Assuming 1 or higher means paid
            $is_paid = true;
        }
        */
        
        // If usertype is boolean (0/1)
        /*
        if ($usertype == '1' || $usertype == 'true') {
            $is_paid = true;
        }
        */
    }

    // ===============================
    // 🟢 ONLINE / OFFLINE LOGIC
    // ===============================
    $last_seen = $user['lastLogin'] ?? null;

    $is_online = false;
    $last_seen_text = "";

    if ($last_seen) {
        $lastSeenTime = strtotime($last_seen);
        $currentTime = time();

        $diffMinutes = ($currentTime - $lastSeenTime) / 60;

        if ($diffMinutes <= 10) {
            $is_online = true;
            $last_seen_text = "Online";
        } else {
            $is_online = false;

            if ($diffMinutes < 60) {
                $last_seen_text = "Last seen " . intval($diffMinutes) . " min ago";
            } elseif ($diffMinutes < 1440) {
                $last_seen_text = "Last seen " . intval($diffMinutes / 60) . " hr ago";
            } else {
                $last_seen_text = "Last seen " . intval($diffMinutes / 1440) . " day ago";
            }
        }
    }

    // ===============================
    // 📦 FINAL RESPONSE ITEM
    // ===============================
    $responseData[] = [
        "id" => (string)$userId,
        "name" => $name,
        "usertype" => $user['usertype'] ?? '', // Adding usertype for debugging
        "profile_picture" => $profile_picture,
        "chat_message" => $chat_message,
        "chat_message_type" => $chat_message_type,
        "matches" => $matchesCount,
        "last_seen" => $last_seen,
        "last_seen_text" => $last_seen_text,
        "is_paid" => $is_paid,
        "is_online" => $is_online
    ];
}

/* ----------------------------------------------------------
   STEP 3: Return paginated response
---------------------------------------------------------- */
echo json_encode([
    "status" => true,
    "data" => $responseData,
    "totalRecords" => $totalUsers,
    "page" => $page,
    "limit" => $limit,
    "totalPages" => ceil($totalUsers / $limit)
], JSON_PRETTY_PRINT);

$conn->close();
?>
