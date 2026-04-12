<?php
/**
 * get_matches.php — Fetch matched profiles for a given user (admin panel).
 *
 * GET parameter:
 *   user_id : ID of the user whose matches to fetch
 *
 * Delegates to match_admin.php logic by including it with the POST data
 * re-mapped from the GET parameter.  Returns the same JSON envelope that
 * ChatProvider in the Flutter admin app expects:
 *   { "status": "success", "data": [ ... ] }
 */
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ERROR);

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'user_id is required',
        'data'    => [],
    ]);
    exit;
}

// match_admin.php reads user_id from the JSON POST body.
// Re-route the GET param through php://input simulation by using a direct
// PDO query here (same logic as match_admin.php but accepts GET).
include 'db_connect.php';

date_default_timezone_set('Asia/Kathmandu');
$conn->query("SET time_zone = '+05:45'");

$base_url = APP_API2_BASE_URL;

/* ----------------------------------------------------------
   1. Get the requesting user's details and gender
---------------------------------------------------------- */
$userQuery = $conn->prepare("
    SELECT u.id, u.gender, u.firstName, u.lastName,
           upd.birthDate, upd.maritalStatusId, upd.religionId,
           upd.communityId, upd.educationId, upd.annualIncomeId,
           upd.heightId, upd.occupationId
    FROM users u
    LEFT JOIN userpersonaldetail upd ON u.id = upd.userId
    WHERE u.id = ?
");
$userQuery->bind_param('i', $user_id);
$userQuery->execute();
$userResult = $userQuery->get_result();

if ($userResult->num_rows === 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'User not found',
        'data'    => [],
    ]);
    $conn->close();
    exit;
}

$user = $userResult->fetch_assoc();
$opposite_gender = ($user['gender'] === 'Male') ? 'Female' : 'Male';

/* ----------------------------------------------------------
   2. Fetch users of the opposite gender (excluding admin, id=1)
---------------------------------------------------------- */
$query = $conn->prepare("
    SELECT
        u.id,
        u.firstName,
        u.lastName,
        u.gender,
        u.profile_picture,
        u.isOnline,
        u.memberid,
        u.usertype,
        upd.birthDate,
        upd.maritalStatusId,
        upd.religionId,
        upd.communityId,
        upd.educationId,
        upd.annualIncomeId,
        upd.heightId,
        upd.occupationId,
        upd.addressId
    FROM users u
    LEFT JOIN userpersonaldetail upd ON u.id = upd.userId
    WHERE u.gender = ? AND u.id != 1 AND u.id != ?
    ORDER BY u.id DESC
    LIMIT 200
");
$query->bind_param('si', $opposite_gender, $user_id);
$query->execute();
$result = $query->get_result();

$responseData = [];

while ($row = $result->fetch_assoc()) {
    /* -------- Profile picture -------- */
    $profile_picture = null;
    if (!empty($row['profile_picture'])) {
        $profile_picture = (strpos($row['profile_picture'], 'http') === 0)
            ? $row['profile_picture']
            : $base_url . $row['profile_picture'];
    }

    /* -------- Age -------- */
    $age = null;
    if (!empty($row['birthDate'])) {
        $birth = new DateTime($row['birthDate']);
        $age = (int)(new DateTime())->diff($birth)->y;
    }

    /* -------- Paid status -------- */
    $is_paid = ($row['usertype'] === 'paid');

    /* -------- Match percentage (simple gender match = 100) -------- */
    $matchPercent = 60;
    if (!empty($row['religionId']) && $row['religionId'] == $user['religionId'])   $matchPercent += 10;
    if (!empty($row['communityId']) && $row['communityId'] == $user['communityId']) $matchPercent += 10;
    if (!empty($row['educationId']) && $row['educationId'] == $user['educationId']) $matchPercent += 10;
    if ($matchPercent > 100) $matchPercent = 100;

    /* -------- Occupation -------- */
    $occupation = '';
    if (!empty($row['occupationId'])) {
        $q = $conn->prepare('SELECT name FROM occupations WHERE id = ? LIMIT 1');
        $q->bind_param('i', $row['occupationId']);
        $q->execute();
        $r = $q->get_result();
        if ($r->num_rows) $occupation = $r->fetch_assoc()['name'];
    }

    /* -------- Education -------- */
    $education = '';
    if (!empty($row['educationId'])) {
        $q = $conn->prepare('SELECT name FROM educations WHERE id = ? LIMIT 1');
        $q->bind_param('i', $row['educationId']);
        $q->execute();
        $r = $q->get_result();
        if ($r->num_rows) $education = $r->fetch_assoc()['name'];
    }

    /* -------- Marital status -------- */
    $marital = '';
    if (!empty($row['maritalStatusId'])) {
        $q = $conn->prepare('SELECT name FROM maritalstatus WHERE id = ? LIMIT 1');
        $q->bind_param('i', $row['maritalStatusId']);
        $q->execute();
        $r = $q->get_result();
        if ($r->num_rows) $marital = $r->fetch_assoc()['name'];
    }

    /* -------- Country -------- */
    $country = '';
    if (!empty($row['addressId'])) {
        $q = $conn->prepare("
            SELECT c.name FROM addresses a
            JOIN countries c ON a.countryId = c.id
            WHERE a.id = ? LIMIT 1
        ");
        $q->bind_param('i', $row['addressId']);
        $q->execute();
        $r = $q->get_result();
        if ($r->num_rows) $country = $r->fetch_assoc()['name'];
    }

    $responseData[] = [
        'id'                  => (int)$row['id'],
        'member_id'           => $row['memberid'],
        'first_name'          => $row['firstName'],
        'last_name'           => $row['lastName'],
        'full_name'           => trim($row['firstName'] . ' ' . $row['lastName']),
        'gender'              => $row['gender'],
        'age'                 => $age,
        'profile_picture'     => $profile_picture,
        'occupation'          => $occupation,
        'education'           => $education,
        'marital_status'      => $marital,
        'country'             => $country,
        'matching_percentage' => $matchPercent,
        'is_paid'             => $is_paid,
        'is_online'           => (bool)($row['isOnline'] ?? false),
        'has_preference'      => true,
    ];
}

echo json_encode([
    'status'  => 'success',
    'message' => 'Matched profiles fetched successfully',
    'data'    => $responseData,
]);

$conn->close();
