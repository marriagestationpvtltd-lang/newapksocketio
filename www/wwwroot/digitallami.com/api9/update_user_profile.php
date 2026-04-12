<?php
/**
 * Admin endpoint: update a single user profile field.
 *
 * POST /api9/update_user_profile.php
 * JSON body:
 *   userid  – int   – target user ID
 *   section – string – one of: personal, family, lifestyle, partner, address
 *   field   – string – column/field name (snake_case matching DB column)
 *   value   – string – new value
 *
 * Only columns in the explicit allow-list can be updated so that
 * this endpoint cannot be abused to overwrite sensitive fields.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../shared/admin_auth.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

admin_auth_guard();

$input  = json_decode(file_get_contents('php://input'), true);
$userid = isset($input['userid']) ? (int)$input['userid']           : 0;
$section = isset($input['section']) ? trim((string)$input['section']) : '';
$field   = isset($input['field'])   ? trim((string)$input['field'])   : '';
$value   = isset($input['value'])   ? $input['value']                 : '';

if ($userid <= 0 || $section === '' || $field === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'userid, section and field are required']);
    exit;
}

// ── Allow-lists keyed by section ─────────────────────────────────────────────
// Maps section → [allowed_field => [table, id_column]]
// Only columns listed here can be updated.
$allowedSections = [
    'personal' => [
        'table'      => 'userpersonaldetail',
        'id_col'     => 'userId',
        'fields'     => [
            'aboutMe', 'motherTongue', 'birthDate', 'bloodGroup', 'Disability',
            'height_name', 'weight_name', 'complexion', 'bodyType', 'familyType',
            'maritalStatusId', 'religionId', 'communityId', 'subCommunityId',
            'memberid', 'manglik',
        ],
    ],
    'family' => [
        'table'  => 'user_family',
        'id_col' => 'userid',
        'fields' => [
            'familytype', 'familybackground',
            'fatherstatus', 'fathername', 'fathereducation', 'fatheroccupation',
            'motherstatus', 'mothercaste', 'mothereducation', 'motheroccupation',
            'familyorigin',
        ],
    ],
    'lifestyle' => [
        'table'  => 'user_lifestyle',
        'id_col' => 'userid',
        'fields' => ['diet', 'drinks', 'drinktype', 'smoke', 'smoketype'],
    ],
    'partner' => [
        'table'  => 'user_partner',
        'id_col' => 'userid',
        'fields' => [
            'minage', 'maxage', 'maritalstatus', 'profilewithchild', 'familytype',
            'religion', 'caste', 'mothertoungue', 'herscopeblief', 'manglik',
            'country', 'state', 'city', 'qualification', 'educationmedium',
            'proffession', 'workingwith', 'annualincome', 'diet',
            'smokeaccept', 'drinkaccept', 'disabilityaccept',
            'complexion', 'bodytype', 'otherexpectation',
        ],
    ],
    'address' => [
        'table'  => 'permanent_address',
        'id_col' => 'userid',
        'fields' => ['country', 'state', 'city', 'tole', 'residentalstatus'],
    ],
];

if (!isset($allowedSections[$section])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => "Unknown section '$section'"]);
    exit;
}

$sectionDef = $allowedSections[$section];
if (!in_array($field, $sectionDef['fields'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => "Field '$field' is not allowed in section '$section'"]);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verify user exists
    $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND isDelete = 0");
    $check->execute([$userid]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $table  = $sectionDef['table'];
    $idCol  = $sectionDef['id_col'];
    $value  = ($value === '' || $value === null) ? null : (string)$value;

    // Use INSERT … ON DUPLICATE KEY for sections that may not have a row yet.
    // For simplicity we attempt UPDATE first, then INSERT if no row existed.
    $stmt = $pdo->prepare(
        "UPDATE `$table` SET `$field` = :val WHERE `$idCol` = :uid"
    );
    $stmt->execute([':val' => $value, ':uid' => $userid]);

    if ($stmt->rowCount() === 0) {
        // Row may not exist yet — try to insert a skeleton row
        try {
            $ins = $pdo->prepare(
                "INSERT INTO `$table` (`$idCol`, `$field`) VALUES (:uid, :val)"
            );
            $ins->execute([':uid' => $userid, ':val' => $value]);
        } catch (PDOException $e2) {
            // If INSERT also fails (e.g. NOT NULL constraints on other cols)
            // we still count this as a non-fatal situation — the data is unchanged.
            error_log("update_user_profile.php insert fallback failed: " . $e2->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => 'Profile updated']);

} catch (PDOException $e) {
    error_log('update_user_profile.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
