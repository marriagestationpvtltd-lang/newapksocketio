<?php
/**
 * profile-view.php – View another user's profile
 */
$title = 'View Profile';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/user_header.php';

// --- Require profile ID ---
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($profileId <= 0) {
    header('Location: home.php');
    exit;
}

// --- Helpers ---
function msProfileImg(?string $pic): string {
    if (empty($pic)) return '';
    if (!preg_match('/^https?:\/\//', $pic)) {
        return APP_API2_BASE_URL . $pic;
    }
    return $pic;
}

function msVal($val, string $default = 'Not specified'): string {
    return (!empty($val) && $val !== 'null' && $val !== 'N/A')
        ? htmlspecialchars((string)$val)
        : $default;
}

// --- Fetch profile via API ---
$apiUrl = APP_API2_BASE_URL . 'other_profile.php?userId='
        . urlencode($profileId)
        . '&loggedInUserId=' . urlencode($currentUser['user_id']);

$profile  = [];
$apiError = '';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    $apiError = 'Unable to load this profile. Please try again later.';
} else {
    $json = json_decode($response, true);
    if (!empty($json['success'])) {
        $profile = $json['data'] ?? [];
    } else {
        $apiError = $json['message'] ?? 'Profile not found or unavailable.';
    }
}

// --- Derived values ---
$fullName   = trim(($profile['firstName'] ?? '') . ' ' . ($profile['lastName'] ?? ''));
$imgUrl     = msProfileImg($profile['profile_picture'] ?? '');
$age        = isset($profile['age']) ? (int)$profile['age'] : '';
$verified   = !empty($profile['isVerified']) && (int)$profile['isVerified'] === 1;
$memberId   = $profile['memberID'] ?? $profile['id'] ?? $profileId;
$city       = $profile['city'] ?? '';
$state      = $profile['state'] ?? '';
$country    = $profile['country'] ?? '';
$locationParts = array_filter([$city, $state, $country], function($v){ return !empty($v) && $v !== 'null'; });
$location   = implode(', ', $locationParts);

// Privacy check
$isPrivatePhoto = isset($profile['privacy']) && (int)$profile['privacy'] === 1
    && (!isset($profile['photo_request']) || $profile['photo_request'] !== 'accepted');

$currentUserId = (int)$currentUser['user_id'];
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
.ms-profile-photo-wrap {
    position: relative;
    display: inline-block;
    margin-bottom: 14px;
}
.ms-profile-photo {
    width: 150px; height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(255,255,255,0.8);
}
.ms-profile-photo.blurred {
    filter: blur(20px);
    transform: scale(1.1);
}
.ms-profile-photo-placeholder {
    width: 150px; height: 150px;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.8);
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #ffe0e1 0%, #f5f5f5 100%);
    color: #ccc; font-size: 3.5rem;
}
.ms-photo-private-overlay {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    border-radius: 50%;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    background: rgba(0,0,0,0.45);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #fff; text-align: center;
    cursor: pointer;
}
.ms-photo-private-overlay i { font-size: 1.8rem; margin-bottom: 4px; }
.ms-photo-private-overlay span { font-size: 0.72rem; opacity: 0.9; }
.ms-profile-header h2 { font-weight: 800; font-size: 1.5rem; margin-bottom: 4px; position: relative; }
.ms-profile-header .ms-meta { opacity: 0.9; font-size: 0.92rem; position: relative; }
.ms-profile-header .ms-badge-verified {
    display: inline-flex; align-items: center; gap: 4px;
    background: #28a745; color: #fff; font-size: 0.75rem; font-weight: 700;
    padding: 3px 10px; border-radius: 20px;
}

/* ---------- Action Buttons ---------- */
.ms-profile-actions {
    display: flex; flex-wrap: wrap; gap: 10px;
    justify-content: center; margin-top: 18px; position: relative;
}
.ms-profile-actions .btn {
    font-size: 0.85rem; font-weight: 600;
    padding: 8px 18px; border-radius: 10px;
}
.ms-action-like { background: #fff; color: var(--ms-primary, #F90E18); border: 2px solid #fff; }
.ms-action-like:hover { background: #ffe0e1; color: var(--ms-primary-dark, #D00D15); }
.ms-action-proposal { background: rgba(255,255,255,0.15); color: #fff; border: 2px solid rgba(255,255,255,0.5); }
.ms-action-proposal:hover { background: rgba(255,255,255,0.3); }
.ms-action-block { background: transparent; color: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.3); font-size: 0.8rem; }
.ms-action-block:hover { color: #fff; border-color: rgba(255,255,255,0.6); }
.ms-action-report { background: transparent; color: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.3); font-size: 0.8rem; }
.ms-action-report:hover { color: #fff; border-color: rgba(255,255,255,0.6); }

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
    display: flex; align-items: center;
    font-weight: 700; font-size: 1.05rem;
    color: var(--ms-text, #2d3436);
}
.ms-detail-card .card-header i { color: var(--ms-primary, #F90E18); margin-right: 8px; }
.ms-detail-card .card-body { padding: 20px; }

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

/* ---------- Error Card ---------- */
.ms-error-card {
    background: var(--ms-white, #fff);
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    padding: 60px 30px;
    text-align: center;
    color: var(--ms-text-muted, #636e72);
}
.ms-error-card i { font-size: 3rem; color: #ddd; margin-bottom: 16px; }
.ms-error-card p { font-size: 1rem; margin-bottom: 20px; }

@media (max-width: 575.98px) {
    .ms-profile-header { padding: 22px 16px; }
    .ms-profile-photo, .ms-profile-photo-placeholder { width: 120px; height: 120px; }
    .ms-profile-actions .btn { padding: 7px 14px; font-size: 0.8rem; }
    .ms-detail-item { flex: 0 0 100%; }
    .ms-detail-card .card-header { font-size: 0.95rem; padding: 14px 16px; }
    .ms-detail-card .card-body { padding: 16px; }
}
</style>

<?php if ($apiError && empty($profile)): ?>
    <!-- ======== Error State ======== -->
    <div class="ms-error-card">
        <i class="fas fa-user-slash d-block"></i>
        <p><?php echo htmlspecialchars($apiError); ?></p>
        <a href="home.php" class="btn ms-btn-primary" style="background:var(--ms-primary);border-color:var(--ms-primary);color:#fff;border-radius:10px;padding:10px 28px;">
            <i class="fas fa-arrow-left me-1"></i> Go Back
        </a>
    </div>
<?php elseif (!empty($profile)): ?>

<?php if ($apiError): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <?php echo htmlspecialchars($apiError); ?>
    </div>
<?php endif; ?>

<!-- ======== Profile Header ======== -->
<div class="ms-profile-header">
    <div class="ms-profile-photo-wrap">
        <?php if ($isPrivatePhoto): ?>
            <?php if ($imgUrl): ?>
                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Photo" class="ms-profile-photo blurred">
            <?php else: ?>
                <div class="ms-profile-photo-placeholder"><i class="fas fa-user"></i></div>
            <?php endif; ?>
            <div class="ms-photo-private-overlay" id="msRequestPhoto" title="Request Photo">
                <i class="fas fa-lock"></i>
                <span>Photo Private<br>Request Photo</span>
            </div>
        <?php elseif ($imgUrl): ?>
            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($fullName); ?>" class="ms-profile-photo">
        <?php else: ?>
            <div class="ms-profile-photo-placeholder"><i class="fas fa-user"></i></div>
        <?php endif; ?>
    </div>
    <h2><?php echo htmlspecialchars($fullName ?: 'User'); ?></h2>
    <div class="ms-meta">
        <?php if ($age): ?><?php echo (int)$age; ?> yrs<?php endif; ?>
        <?php if ($age && $location): ?> &middot; <?php endif; ?>
        <?php if ($location): ?><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($location); ?><?php endif; ?>
    </div>
    <div class="mt-2">
        <span class="ms-meta">ID: <?php echo htmlspecialchars((string)$memberId); ?></span>
    </div>
    <?php if ($verified): ?>
        <div class="mt-2">
            <span class="ms-badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
        </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="ms-profile-actions">
        <button class="btn ms-action-like" id="msLikeBtn" data-id="<?php echo $profileId; ?>">
            <i class="fas fa-heart me-1"></i> Like
        </button>
        <button class="btn ms-action-proposal" id="msProposalBtn" data-id="<?php echo $profileId; ?>">
            <i class="fas fa-paper-plane me-1"></i> Send Proposal
        </button>
        <button class="btn ms-action-block" id="msBlockBtn" data-id="<?php echo $profileId; ?>">
            <i class="fas fa-ban me-1"></i> Block
        </button>
        <button class="btn ms-action-report" id="msReportBtn" data-id="<?php echo $profileId; ?>">
            <i class="fas fa-flag me-1"></i> Report
        </button>
    </div>
</div>

<!-- ======== About Me ======== -->
<?php $about = $profile['about'] ?? $profile['bio'] ?? ''; ?>
<?php if (!empty($about) && $about !== 'null'): ?>
<div class="ms-detail-card">
    <div class="card-header">
        <span><i class="fas fa-quote-left"></i> About</span>
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

<!-- ======== JavaScript Actions ======== -->
<script>
(function() {
    var currentUserId = <?php echo $currentUserId; ?>;
    var profileId = <?php echo $profileId; ?>;

    // Like
    document.getElementById('msLikeBtn').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Liking...';

        fetch('/Api2/like_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: currentUserId, liked_user_id: profileId, action: 'like' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Liked!', text: data.message || 'Profile liked successfully.', timer: 2000, showConfirmButton: false });
                btn.innerHTML = '<i class="fas fa-heart me-1"></i> Liked';
            } else {
                Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not like this profile.' });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-heart me-1"></i> Like';
            }
        })
        .catch(function() {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-heart me-1"></i> Like';
        });
    });

    // Send Proposal
    document.getElementById('msProposalBtn').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending...';

        fetch('/Api2/proposals_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send', sender_id: currentUserId, receiver_id: profileId })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Proposal Sent!', text: data.message || 'Your proposal has been sent.', timer: 2000, showConfirmButton: false });
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Sent';
            } else {
                Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not send proposal.' });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send Proposal';
            }
        })
        .catch(function() {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send Proposal';
        });
    });

    // Block
    document.getElementById('msBlockBtn').addEventListener('click', function() {
        var btn = this;
        Swal.fire({
            title: 'Block this user?',
            text: 'They will no longer be able to see your profile or contact you.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#F90E18',
            confirmButtonText: 'Yes, Block',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Blocking...';

                fetch('/Api2/block_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: currentUserId, blocked_user_id: profileId, action: 'block' })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Blocked', text: data.message || 'User has been blocked.', timer: 2000, showConfirmButton: false });
                        btn.innerHTML = '<i class="fas fa-ban me-1"></i> Blocked';
                    } else {
                        Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not block this user.' });
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-ban me-1"></i> Block';
                    }
                })
                .catch(function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-ban me-1"></i> Block';
                });
            }
        });
    });

    // Report
    document.getElementById('msReportBtn').addEventListener('click', function() {
        var btn = this;
        Swal.fire({
            title: 'Report this profile',
            input: 'textarea',
            inputLabel: 'Please describe the reason for reporting',
            inputPlaceholder: 'Enter your reason here...',
            inputAttributes: { 'aria-label': 'Reason for reporting' },
            showCancelButton: true,
            confirmButtonColor: '#F90E18',
            confirmButtonText: 'Submit Report',
            cancelButtonText: 'Cancel',
            inputValidator: function(value) {
                if (!value || !value.trim()) {
                    return 'Please provide a reason for reporting.';
                }
            }
        }).then(function(result) {
            if (result.isConfirmed && result.value) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Reporting...';

                fetch('/Api2/report_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: currentUserId, reported_user_id: profileId, reason: result.value })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Reported', text: data.message || 'Report submitted successfully.', timer: 2000, showConfirmButton: false });
                        btn.innerHTML = '<i class="fas fa-flag me-1"></i> Reported';
                    } else {
                        Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not submit report.' });
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-flag me-1"></i> Report';
                    }
                })
                .catch(function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-flag me-1"></i> Report';
                });
            }
        });
    });

    // Request Photo (if private)
    var reqPhotoBtn = document.getElementById('msRequestPhoto');
    if (reqPhotoBtn) {
        reqPhotoBtn.addEventListener('click', function() {
            Swal.fire({
                title: 'Request Photo?',
                text: 'Send a photo request to this user.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#F90E18',
                confirmButtonText: 'Send Request',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (result.isConfirmed) {
                    fetch('/Api2/photo_request.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ user_id: currentUserId, requested_user_id: profileId, action: 'request' })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Request Sent!', text: data.message || 'Photo request sent.', timer: 2000, showConfirmButton: false });
                        } else {
                            Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not send photo request.' });
                        }
                    })
                    .catch(function() {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                    });
                }
            });
        });
    }
})();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/user_footer.php'; ?>
