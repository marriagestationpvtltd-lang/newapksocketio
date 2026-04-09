<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Database connection
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db = DB_NAME;
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// Get and validate userId
if (!isset($_GET['userId']) || !is_numeric($_GET['userId']) || intval($_GET['userId']) <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid or missing userId"]);
    exit;
}
$userId = intval($_GET['userId']);

// Complete SQL query with placeholder
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    $conn->close();
    exit;
}

$stmt->bind_param("i", $userId);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Execute failed: " . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode([
        "success" => true,
        "data" => $data,
        "message" => "Lifestyle details fetched successfully"
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    $json = json_decode($response, true);
    if (!empty($json['success'])) {
        $profile = $json['data'] ?? [];
    } else {
        $apiError = $json['message'] ?? 'Failed to load profile.';
    }
}

// --- Derived values ---
$fullName   = trim(($profile['firstName'] ?? '') . ' ' . ($profile['lastName'] ?? ''));
$imgUrl     = msProfileImg($profile['profile_picture'] ?? '');
$age        = isset($profile['age']) ? (int)$profile['age'] : '';
$verified   = !empty($profile['isVerified']) && (int)$profile['isVerified'] === 1;
$memberId   = $profile['memberID'] ?? $profile['id'] ?? $currentUser['user_id'];
$city       = $profile['city'] ?? '';
$state      = $profile['state'] ?? '';
$country    = $profile['country'] ?? '';
$locationParts = array_filter([$city, $state, $country], function($v){ return !empty($v) && $v !== 'null'; });
$location   = implode(', ', $locationParts);
?>

<style>
/* ---------- Profile Header ---------- */
.ms-profile-header {
    background: linear-gradient(135deg, #F90E18 0%, #D00D15 60%, #a00a10 100%);
    color: #fff;
    border-radius: 16px;
    padding: 32px 28px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    text-align: center;
}
.ms-profile-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 60%; height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 70%);
    pointer-events: none;
}
.ms-profile-photo {
    width: 150px; height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(255,255,255,0.8);
    margin-bottom: 14px;
    position: relative;
}
.ms-profile-photo-placeholder {
    width: 150px; height: 150px;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.8);
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #ffe0e1 0%, #f5f5f5 100%);
    color: #ccc; font-size: 3.5rem;
    margin: 0 auto 14px;
}
.ms-profile-header h2 { font-weight: 800; font-size: 1.5rem; margin-bottom: 4px; position: relative; }
.ms-profile-header .ms-meta { opacity: 0.9; font-size: 0.92rem; position: relative; }
.ms-profile-header .ms-badge-verified {
    display: inline-flex; align-items: center; gap: 4px;
    background: #28a745; color: #fff; font-size: 0.75rem; font-weight: 700;
    padding: 3px 10px; border-radius: 20px;
}
.ms-profile-header .ms-badge-unverified {
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(255,255,255,0.2); color: #fff; font-size: 0.75rem; font-weight: 600;
    padding: 3px 10px; border-radius: 20px;
}

/* ---------- Detail Cards ---------- */
.ms-detail-card {
    background: var(--ms-white, #fff);
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    margin-bottom: 20px;
    overflow: hidden;
}
.ms-detail-card .card-header {
    background: transparent;
    border-bottom: 1px solid var(--ms-border, #e9ecef);
    padding: 16px 20px;
    display: flex; align-items: center; justify-content: space-between;
    font-weight: 700; font-size: 1.05rem;
    color: var(--ms-text, #2d3436);
}
.ms-detail-card .card-header i { color: var(--ms-primary, #F90E18); margin-right: 8px; }
.ms-detail-card .card-body { padding: 20px; }
.ms-detail-card .ms-edit-btn {
    font-size: 0.82rem; color: var(--ms-primary, #F90E18);
    text-decoration: none; font-weight: 600;
}
.ms-detail-card .ms-edit-btn:hover { text-decoration: underline; }

/* ---------- Detail Rows ---------- */
.ms-detail-row {
    display: flex; flex-wrap: wrap;
}
.ms-detail-item {
    flex: 0 0 50%; padding: 8px 0;
    display: flex; flex-direction: column;
}
.ms-detail-item .ms-label {
    font-size: 0.78rem; color: var(--ms-text-muted, #636e72);
    text-transform: uppercase; letter-spacing: 0.5px;
    margin-bottom: 2px;
}
.ms-detail-item .ms-value {
    font-size: 0.95rem; color: var(--ms-text, #2d3436); font-weight: 500;
}
.ms-about-text {
    font-size: 0.95rem; line-height: 1.6;
    color: var(--ms-text, #2d3436);
}

@media (max-width: 575.98px) {
    .ms-profile-header { padding: 22px 16px; }
    .ms-profile-photo, .ms-profile-photo-placeholder { width: 120px; height: 120px; }
    .ms-detail-item { flex: 0 0 100%; }
    .ms-detail-card .card-header { font-size: 0.95rem; padding: 14px 16px; }
    .ms-detail-card .card-body { padding: 16px; }
}
</style>

<?php if ($apiError): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <?php echo htmlspecialchars($apiError); ?>
    </div>
<?php endif; ?>

<?php if (!empty($profile)): ?>

<!-- ======== Profile Header ======== -->
<div class="ms-profile-header">
    <?php if ($imgUrl): ?>
        <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="ms-profile-photo">
    <?php else: ?>
        <div class="ms-profile-photo-placeholder"><i class="fas fa-user"></i></div>
    <?php endif; ?>
    <h2><?php echo htmlspecialchars($fullName ?: 'User'); ?></h2>
    <div class="ms-meta">
        <?php if ($age): ?><?php echo (int)$age; ?> yrs<?php endif; ?>
        <?php if ($age && $location): ?> &middot; <?php endif; ?>
        <?php if ($location): ?><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($location); ?><?php endif; ?>
    </div>
    <div class="mt-2">
        <span class="ms-meta">ID: <?php echo htmlspecialchars((string)$memberId); ?></span>
    </div>
    <div class="mt-2">
        <?php if ($verified): ?>
            <span class="ms-badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
        <?php else: ?>
            <span class="ms-badge-unverified"><i class="fas fa-info-circle"></i> Not Verified</span>
        <?php endif; ?>
    </div>
</div>

<!-- ======== About Me ======== -->
<?php $about = $profile['about'] ?? $profile['bio'] ?? ''; ?>
<?php if (!empty($about) && $about !== 'null'): ?>
<div class="ms-detail-card">
    <div class="card-header">
        <span><i class="fas fa-quote-left"></i> About Me</span>
    </div>
    <div class="card-body">
        <p class="ms-about-text mb-0"><?php echo nl2br(htmlspecialchars($about)); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- ======== Personal Details ======== -->
<div class="ms-detail-card">
    <div class="card-header">
        <span><i class="fas fa-user-circle"></i> Personal Details</span>
        <a href="edit-profile.php?section=personal" class="ms-edit-btn"><i class="fas fa-pen me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <div class="ms-detail-row">
            <div class="ms-detail-item">
                <span class="ms-label">Height</span>
                <span class="ms-value"><?php echo msVal($profile['height'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Weight</span>
                <span class="ms-value"><?php echo msVal($profile['weight'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Marital Status</span>
                <span class="ms-value"><?php echo msVal($profile['marital_status'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Mother Tongue</span>
                <span class="ms-value"><?php echo msVal($profile['mother_tongue'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Religion</span>
                <span class="ms-value"><?php echo msVal($profile['religion'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Community / Caste</span>
                <span class="ms-value"><?php echo msVal($profile['community'] ?? $profile['caste'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Sub Caste</span>
                <span class="ms-value"><?php echo msVal($profile['sub_caste'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Blood Group</span>
                <span class="ms-value"><?php echo msVal($profile['blood_group'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Disability</span>
                <span class="ms-value"><?php echo msVal($profile['disability'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Complexion</span>
                <span class="ms-value"><?php echo msVal($profile['complexion'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Gotra</span>
                <span class="ms-value"><?php echo msVal($profile['gotra'] ?? null); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ======== Education & Career ======== -->
<div class="ms-detail-card">
    <div class="card-header">
        <span><i class="fas fa-graduation-cap"></i> Education &amp; Career</span>
        <a href="edit-profile.php?section=education" class="ms-edit-btn"><i class="fas fa-pen me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <div class="ms-detail-row">
            <div class="ms-detail-item">
                <span class="ms-label">Education Medium</span>
                <span class="ms-value"><?php echo msVal($profile['education_medium'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Education Type</span>
                <span class="ms-value"><?php echo msVal($profile['education_type'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Faculty</span>
                <span class="ms-value"><?php echo msVal($profile['faculty'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Degree</span>
                <span class="ms-value"><?php echo msVal($profile['education'] ?? $profile['degree'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Working Status</span>
                <span class="ms-value"><?php echo msVal($profile['working_status'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Occupation</span>
                <span class="ms-value"><?php echo msVal($profile['occupation'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Company</span>
                <span class="ms-value"><?php echo msVal($profile['company'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Designation</span>
                <span class="ms-value"><?php echo msVal($profile['designation'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Annual Income</span>
                <span class="ms-value"><?php echo msVal($profile['annualincome'] ?? $profile['annual_income'] ?? null); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ======== Family Details ======== -->
<div class="ms-detail-card">
    <div class="card-header">
        <span><i class="fas fa-home"></i> Family Details</span>
        <a href="edit-profile.php?section=family" class="ms-edit-btn"><i class="fas fa-pen me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <div class="ms-detail-row">
            <div class="ms-detail-item">
                <span class="ms-label">Family Type</span>
                <span class="ms-value"><?php echo msVal($profile['family_type'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Family Status</span>
                <span class="ms-value"><?php echo msVal($profile['family_status'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Father Status</span>
                <span class="ms-value"><?php echo msVal($profile['father_status'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Father Details</span>
                <span class="ms-value"><?php echo msVal($profile['father_details'] ?? $profile['father_occupation'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Mother Status</span>
                <span class="ms-value"><?php echo msVal($profile['mother_status'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Mother Details</span>
                <span class="ms-value"><?php echo msVal($profile['mother_details'] ?? $profile['mother_occupation'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Brothers</span>
                <span class="ms-value"><?php echo msVal($profile['no_of_brothers'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Married Brothers</span>
                <span class="ms-value"><?php echo msVal($profile['married_brothers'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Sisters</span>
                <span class="ms-value"><?php echo msVal($profile['no_of_sisters'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Married Sisters</span>
                <span class="ms-value"><?php echo msVal($profile['married_sisters'] ?? null); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ======== Lifestyle ======== -->
<div class="ms-detail-card">
    <div class="card-header">
        <span><i class="fas fa-utensils"></i> Lifestyle</span>
        <a href="edit-profile.php?section=lifestyle" class="ms-edit-btn"><i class="fas fa-pen me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <div class="ms-detail-row">
            <div class="ms-detail-item">
                <span class="ms-label">Diet</span>
                <span class="ms-value"><?php echo msVal($profile['diet'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Drinking</span>
                <span class="ms-value"><?php echo msVal($profile['drinking'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Smoking</span>
                <span class="ms-value"><?php echo msVal($profile['smoking'] ?? null); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ======== Astrological Info ======== -->
<div class="ms-detail-card">
    <div class="card-header">
        <span><i class="fas fa-star-and-crescent"></i> Astrological Info</span>
        <a href="edit-profile.php?section=personal" class="ms-edit-btn"><i class="fas fa-pen me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <div class="ms-detail-row">
            <div class="ms-detail-item">
                <span class="ms-label">Manglik Status</span>
                <span class="ms-value"><?php echo msVal($profile['manglik'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Birth Time</span>
                <span class="ms-value"><?php echo msVal($profile['birth_time'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Birth City</span>
                <span class="ms-value"><?php echo msVal($profile['birth_city'] ?? $profile['birth_place'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Zodiac / Rashi</span>
                <span class="ms-value"><?php echo msVal($profile['zodiac'] ?? $profile['rashi'] ?? null); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ======== Partner Preferences ======== -->
<div class="ms-detail-card">
    <div class="card-header">
        <span><i class="fas fa-heart"></i> Partner Preferences</span>
        <a href="edit-profile.php?section=partner" class="ms-edit-btn"><i class="fas fa-pen me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <div class="ms-detail-row">
            <div class="ms-detail-item">
                <span class="ms-label">Preferred Age</span>
                <span class="ms-value">
                    <?php
                    $ageFrom = $profile['preferred_age_from'] ?? $profile['partner_age_from'] ?? null;
                    $ageTo   = $profile['preferred_age_to'] ?? $profile['partner_age_to'] ?? null;
                    if (!empty($ageFrom) && !empty($ageTo)) {
                        echo htmlspecialchars($ageFrom . ' - ' . $ageTo . ' yrs');
                    } else {
                        echo 'Not specified';
                    }
                    ?>
                </span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Preferred Height</span>
                <span class="ms-value">
                    <?php
                    $hFrom = $profile['preferred_height_from'] ?? $profile['partner_height_from'] ?? null;
                    $hTo   = $profile['preferred_height_to'] ?? $profile['partner_height_to'] ?? null;
                    if (!empty($hFrom) && !empty($hTo)) {
                        echo htmlspecialchars($hFrom . ' - ' . $hTo);
                    } else {
                        echo 'Not specified';
                    }
                    ?>
                </span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Preferred Marital Status</span>
                <span class="ms-value"><?php echo msVal($profile['preferred_marital_status'] ?? $profile['partner_marital_status'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Preferred Religion</span>
                <span class="ms-value"><?php echo msVal($profile['preferred_religion'] ?? $profile['partner_religion'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Preferred Community</span>
                <span class="ms-value"><?php echo msVal($profile['preferred_community'] ?? $profile['partner_community'] ?? $profile['partner_caste'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Preferred Education</span>
                <span class="ms-value"><?php echo msVal($profile['preferred_education'] ?? $profile['partner_education'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Preferred Occupation</span>
                <span class="ms-value"><?php echo msVal($profile['preferred_occupation'] ?? $profile['partner_occupation'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Preferred City</span>
                <span class="ms-value"><?php echo msVal($profile['preferred_city'] ?? $profile['partner_city'] ?? null); ?></span>
            </div>
            <div class="ms-detail-item">
                <span class="ms-label">Preferred Income</span>
                <span class="ms-value"><?php echo msVal($profile['preferred_income'] ?? $profile['partner_income'] ?? null); ?></span>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
    <?php if (empty($apiError)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-1"></i>
            No profile data found. Please complete your profile.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/user_footer.php'; ?>