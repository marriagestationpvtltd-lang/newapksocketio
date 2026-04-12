<?php
/**
 * Admin-only endpoint: full user profile without any privacy filtering.
 * Called by the admin panel UserDetailsScreen.
 *
 * GET /api9/get_user_profile.php?userid=<id>
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$base_url = APP_API2_BASE_URL;

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// ── Admin token verification ─────────────────────────────────────────────────
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (empty($authHeader)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$tokenSecret = getenv('ADMIN_TOKEN_SECRET') ?: 'CHANGE_THIS_SECRET_KEY';
$tokenParts  = explode('.', $authHeader, 2);
$tokenValid  = false;
if (count($tokenParts) === 2) {
    $payloadJson = base64_decode($tokenParts[0]);
    $expected    = hash_hmac('sha256', $payloadJson, $tokenSecret);
    if (hash_equals($expected, $tokenParts[1])) {
        $payload = json_decode($payloadJson, true);
        if (is_array($payload) && isset($payload['exp']) && $payload['exp'] >= time()) {
            $tokenValid = true;
        }
    }
}
if (!$tokenValid) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userid = isset($_GET['userid']) ? intval($_GET['userid']) : 0;
if ($userid <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

// ── Main profile query ────────────────────────────────────────────────────────
$sql = "
SELECT
    u.id, u.firstName, u.lastName, u.profile_picture, u.usertype, u.isVerified,
    u.privacy, u.email, u.contactNo AS phone, u.status, u.gender, u.lastLogin,

    pa.city, pa.country,

    ec.educationmedium AS ec_educationmedium,
    ec.educationtype, ec.faculty, ec.degree,
    ec.areyouworking, ec.occupationtype, ec.companyname,
    ec.designation AS ec_designation,
    ec.workingwith AS ec_workingwith, ec.annualincome AS ec_annualincome, ec.businessname,

    up.memberid, up.height_name, up.maritalStatusId, ms.name AS maritalStatusName,
    up.motherTongue, up.aboutMe, up.birthDate, up.Disability, up.bloodGroup,
    r.name AS religionName,
    c.name AS communityName,
    sc.name AS subCommunityName,

    ua.manglik, ua.birthtime, ua.birthcity,

    uf.id AS familyId, uf.familytype, uf.familybackground,
    uf.fatherstatus, uf.fathername, uf.fathereducation, uf.fatheroccupation,
    uf.motherstatus, uf.mothercaste, uf.mothereducation, uf.motheroccupation, uf.familyorigin,

    ul.id AS lifestyleId, ul.smoketype, ul.diet, ul.drinks, ul.drinktype, ul.smoke,

    upa.minage, upa.maxage, upa.maritalstatus, upa.profilewithchild,
    upa.familytype AS partnerFamilyType,
    upa.religion AS partnerReligion,
    upa.caste AS partnerCaste,
    upa.mothertoungue AS partnerMotherTongue,
    upa.herscopeblief,
    upa.manglik AS partnerManglik,
    upa.country AS partnerCountry,
    upa.state AS partnerState,
    upa.city AS partnerCity,
    upa.qualification AS partnerQualification,
    upa.educationmedium AS partnerEducationMedium,
    upa.proffession AS partnerProfession,
    upa.workingwith AS partnerWorkingWith,
    upa.annualincome AS partnerAnnualIncome,
    upa.diet AS partnerDiet,
    upa.smokeaccept, upa.drinkaccept, upa.disabilityaccept,
    upa.complexion AS partnerComplexion,
    upa.bodytype AS partnerBodyType,
    upa.otherexpectation AS partnerOtherExpectation

FROM users u
LEFT JOIN permanent_address pa ON u.id = pa.userid
LEFT JOIN educationcareer ec ON u.id = ec.userid
LEFT JOIN userpersonaldetail up ON u.id = up.userid
LEFT JOIN maritalstatus ms ON up.maritalStatusId = ms.id
LEFT JOIN religion r ON up.religionId = r.id
LEFT JOIN community c ON up.communityId = c.id
LEFT JOIN subcommunity sc ON up.subCommunityId = sc.id
LEFT JOIN user_astrologic ua ON u.id = ua.userid
LEFT JOIN user_family uf ON u.id = uf.userid
LEFT JOIN user_lifestyle ul ON u.id = ul.userid
LEFT JOIN user_partner upa ON u.id = upa.userid
WHERE u.id = ?
LIMIT 1
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query failed']);
    exit;
}

if (!$row) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$profile_picture = !empty($row['profile_picture'])
    ? $base_url . ltrim($row['profile_picture'], '/')
    : '';

$default = 'Not available';

echo json_encode([
    'status' => 'success',
    'data'   => [
        'personalDetail' => [
            'photo_request'    => 'not_sent',
            'chat_request'     => 'not_sent',
            'firstName'        => $row['firstName']        ?? $default,
            'lastName'         => $row['lastName']         ?? $default,
            'profile_picture'  => $profile_picture,
            'usertype'         => $row['usertype']         ?? $default,
            'isVerified'       => (int)($row['isVerified'] ?? 0),
            'privacy'          => $row['privacy']          ?? 'free',
            'city'             => $row['city']             ?? $default,
            'country'          => $row['country']          ?? $default,
            'educationmedium'  => $row['ec_educationmedium'] ?? $default,
            'educationtype'    => $row['educationtype']    ?? $default,
            'faculty'          => $row['faculty']          ?? $default,
            'degree'           => $row['degree']           ?? $default,
            'areyouworking'    => $row['areyouworking']    ?? $default,
            'occupationtype'   => $row['occupationtype']   ?? $default,
            'companyname'      => $row['companyname']      ?? $default,
            'designation'      => $row['ec_designation']   ?? $default,
            'workingwith'      => $row['ec_workingwith']   ?? $default,
            'annualincome'     => $row['ec_annualincome']  ?? $default,
            'businessname'     => $row['businessname']     ?? '',
            'memberid'         => $row['memberid']         ?? $default,
            'height_name'      => $row['height_name']      ?? $default,
            'maritalStatusId'  => (int)($row['maritalStatusId'] ?? 0),
            'maritalStatusName'=> $row['maritalStatusName'] ?? $default,
            'motherTongue'     => $row['motherTongue']     ?? $default,
            'aboutMe'          => $row['aboutMe']          ?? $default,
            'birthDate'        => $row['birthDate']        ?? '',
            'Disability'       => $row['Disability']       ?? $default,
            'bloodGroup'       => $row['bloodGroup']       ?? $default,
            'religionName'     => $row['religionName']     ?? $default,
            'communityName'    => $row['communityName']    ?? $default,
            'subCommunityName' => $row['subCommunityName'] ?? $default,
            'manglik'          => $row['manglik']          ?? $default,
            'birthtime'        => $row['birthtime']        ?? $default,
            'birthcity'        => $row['birthcity']        ?? $default,
        ],
        'familyDetail' => [
            'familyId'         => (int)($row['familyId']        ?? 0),
            'familytype'       => $row['familytype']        ?? $default,
            'familybackground' => $row['familybackground']  ?? $default,
            'fatherstatus'     => $row['fatherstatus']      ?? $default,
            'fathername'       => $row['fathername']        ?? $default,
            'fathereducation'  => $row['fathereducation']   ?? $default,
            'fatheroccupation' => $row['fatheroccupation']  ?? $default,
            'motherstatus'     => $row['motherstatus']      ?? $default,
            'mothercaste'      => $row['mothercaste']       ?? $default,
            'mothereducation'  => $row['mothereducation']   ?? $default,
            'motheroccupation' => $row['motheroccupation']  ?? $default,
            'familyorigin'     => $row['familyorigin']      ?? $default,
        ],
        'lifestyle' => [
            'lifestyleId' => (int)($row['lifestyleId'] ?? 0),
            'smoketype'   => $row['smoketype'] ?? $default,
            'diet'        => $row['diet']      ?? $default,
            'drinks'      => $row['drinks']    ?? $default,
            'drinktype'   => $row['drinktype'] ?? $default,
            'smoke'       => $row['smoke']     ?? $default,
        ],
        'partner' => [
            'minage'          => (int)($row['minage']         ?? 0),
            'maxage'          => (int)($row['maxage']         ?? 0),
            'minweight'       => 0,
            'maxweight'       => 0,
            'maritalstatus'   => $row['maritalstatus']        ?? $default,
            'profilewithchild'=> $row['profilewithchild']     ?? $default,
            'familytype'      => $row['partnerFamilyType']    ?? $default,
            'religion'        => $row['partnerReligion']      ?? $default,
            'caste'           => $row['partnerCaste']         ?? $default,
            'mothertoungue'   => $row['partnerMotherTongue']  ?? $default,
            'herscopeblief'   => $row['herscopeblief']        ?? $default,
            'manglik'         => $row['partnerManglik']       ?? $default,
            'country'         => $row['partnerCountry']       ?? $default,
            'state'           => $row['partnerState']         ?? $default,
            'city'            => $row['partnerCity']          ?? $default,
            'qualification'   => $row['partnerQualification'] ?? $default,
            'educationmedium' => $row['partnerEducationMedium'] ?? $default,
            'proffession'     => $row['partnerProfession']    ?? $default,
            'workingwith'     => $row['partnerWorkingWith']   ?? $default,
            'annualincome'    => $row['partnerAnnualIncome']  ?? $default,
            'diet'            => $row['partnerDiet']          ?? $default,
            'smokeaccept'     => $row['smokeaccept']          ?? $default,
            'drinkaccept'     => $row['drinkaccept']          ?? $default,
            'disabilityaccept'=> $row['disabilityaccept']     ?? $default,
            'complexion'      => $row['partnerComplexion']    ?? $default,
            'bodytype'        => $row['partnerBodyType']       ?? $default,
            'otherexpectation'=> $row['partnerOtherExpectation'] ?? $default,
        ],
        'contactDetail' => [
            'email'        => $row['email'] ?? '',
            'phone'        => $row['phone'] ?? '',
            'whatsapp'     => $row['phone'] ?? '',
            'country_code' => '',
        ],
    ],
]);
