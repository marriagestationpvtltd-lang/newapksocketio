<?php
/**
 * home.php – User Home / Dashboard – Browse Profiles
 * Mirrors the mobile HomeScreenPage.
 */
$title = 'Home';
require_once __DIR__ . '/includes/user_header.php';

// --- Fetch opposite-gender profiles via API ---
$apiUrl = 'https://digitallami.com/Api2/search_opposite_gender.php?user_id='
        . urlencode($currentUser['user_id']);

$profiles  = [];
$apiError  = '';
$totalCount = 0;

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
    $apiError = 'Unable to load profiles. Please try again later.';
} else {
    $json = json_decode($response, true);
    if (!empty($json['success'])) {
        $profiles   = $json['data'] ?? [];
        $totalCount = (int) ($json['total_count'] ?? count($profiles));
    } else {
        $apiError = $json['message'] ?? 'Failed to load profiles.';
    }
}

/**
 * Return a safe profile-image URL or empty string.
 */
function profileImageUrl(array $profile): string
{
    if (empty($profile['profile_picture'])) {
        return '';
    }
    $pic = $profile['profile_picture'];
    if (!preg_match('/^https?:\/\//', $pic)) {
        $pic = 'https://digitallami.com/Api2/' . $pic;
    }
    return $pic;
}

/**
 * Should the photo be hidden (privacy lock)?
 */
function isPhotoPrivate(array $profile): bool
{
    return isset($profile['privacy']) && (int) $profile['privacy'] === 1
        && (!isset($profile['photo_request']) || $profile['photo_request'] !== 'accepted');
}

$matchedProfiles = array_slice($profiles, 0, 6);
$recentProfiles  = $profiles;
?>

<style>
/* ---------- Welcome Banner ---------- */
.ms-welcome {
    background: linear-gradient(135deg, #F90E18 0%, #D00D15 60%, #a00a10 100%);
    color: #fff;
    border-radius: 16px;
    padding: 32px 28px;
    margin-bottom: 32px;
    position: relative;
    overflow: hidden;
}
.ms-welcome::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 60%; height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 70%);
    pointer-events: none;
}
.ms-welcome h2 { font-weight: 800; font-size: 1.6rem; margin-bottom: 6px; position: relative; }
.ms-welcome p  { opacity: 0.9; margin: 0; position: relative; font-size: 0.95rem; }
.ms-welcome-stats {
    display: flex; gap: 24px; margin-top: 16px; position: relative;
}
.ms-welcome-stat { text-align: center; }
.ms-welcome-stat strong { display: block; font-size: 1.4rem; font-weight: 800; }
.ms-welcome-stat span   { font-size: 0.8rem; opacity: 0.85; }

/* ---------- Section headings ---------- */
.ms-section-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 18px;
}
.ms-section-head h3 { font-weight: 700; font-size: 1.25rem; margin: 0; }
.ms-section-head a   { font-size: 0.88rem; color: var(--ms-primary); font-weight: 600; text-decoration: none; }
.ms-section-head a:hover { text-decoration: underline; }

/* ---------- Horizontal Scroll ---------- */
.ms-hscroll {
    display: flex; gap: 16px; overflow-x: auto; padding-bottom: 12px;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}
.ms-hscroll::-webkit-scrollbar { height: 6px; }
.ms-hscroll::-webkit-scrollbar-thumb { background: #ddd; border-radius: 3px; }
.ms-hscroll .ms-card { min-width: 230px; max-width: 260px; flex-shrink: 0; }

/* ---------- Profile Card ---------- */
.ms-card {
    background: var(--ms-white);
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    overflow: hidden;
    transition: transform 0.22s ease, box-shadow 0.22s ease;
    display: flex;
    flex-direction: column;
}
.ms-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 28px rgba(0,0,0,0.13);
}
.ms-card-img {
    position: relative;
    width: 100%;
    padding-top: 110%;
    background: #f0f0f0;
    overflow: hidden;
}
.ms-card-img img {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    object-fit: cover;
}
.ms-card-img .ms-avatar-placeholder {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #ffe0e1 0%, #f5f5f5 100%);
    color: #ccc; font-size: 3rem;
}
.ms-card-img .ms-privacy-overlay {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    background: rgba(0,0,0,0.45);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    color: #fff; text-align: center;
}
.ms-privacy-overlay i { font-size: 2rem; margin-bottom: 6px; }
.ms-privacy-overlay span { font-size: 0.8rem; opacity: 0.9; }

.ms-verified-badge {
    position: absolute; top: 10px; right: 10px;
    background: #28a745; color: #fff;
    font-size: 0.7rem; font-weight: 700;
    padding: 3px 8px; border-radius: 20px;
    display: flex; align-items: center; gap: 4px;
}

.ms-card-body {
    padding: 14px 16px 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.ms-card-name {
    font-weight: 700; font-size: 1rem;
    margin-bottom: 2px; color: var(--ms-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ms-card-meta {
    font-size: 0.82rem; color: var(--ms-text-muted);
    margin-bottom: 6px;
}
.ms-card-meta i { margin-right: 3px; }
.ms-card-info {
    font-size: 0.8rem; color: var(--ms-text-muted);
    margin-bottom: 12px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.ms-card-actions {
    display: flex; gap: 8px; margin-top: auto;
}
.ms-card-actions .btn { font-size: 0.78rem; padding: 6px 10px; border-radius: 8px; flex: 1; }
.ms-btn-primary {
    background: var(--ms-primary); border-color: var(--ms-primary); color: #fff;
}
.ms-btn-primary:hover { background: var(--ms-primary-dark); border-color: var(--ms-primary-dark); color: #fff; }
.ms-btn-outline {
    border: 1px solid var(--ms-border); color: var(--ms-text); background: transparent;
}
.ms-btn-outline:hover { border-color: var(--ms-primary); color: var(--ms-primary); }

/* ---------- Grid ---------- */
.ms-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
@media (max-width: 1199.98px) { .ms-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 991.98px)  { .ms-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575.98px)  { .ms-grid { grid-template-columns: 1fr; } }

/* ---------- Empty state ---------- */
.ms-empty {
    text-align: center; padding: 60px 20px; color: var(--ms-text-muted);
}
.ms-empty i { font-size: 3rem; margin-bottom: 12px; display: block; color: #ddd; }
.ms-empty p { font-size: 1rem; }

@media (max-width: 575.98px) {
    .ms-welcome { padding: 22px 18px; }
    .ms-welcome h2 { font-size: 1.3rem; }
    .ms-welcome-stats { gap: 16px; }
}
</style>

<!-- ======== Welcome Banner ======== -->
<div class="ms-welcome">
    <h2><i class="fas fa-heart me-2"></i>Welcome back, <?php echo htmlspecialchars($currentUser['firstName']); ?>!</h2>
    <p>Find your perfect life partner today</p>
    <div class="ms-welcome-stats">
        <div class="ms-welcome-stat">
            <strong><?php echo $totalCount; ?></strong>
            <span>Matches</span>
        </div>
        <div class="ms-welcome-stat">
            <strong><?php echo count($matchedProfiles); ?></strong>
            <span>Featured</span>
        </div>
    </div>
</div>

<?php if ($apiError): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <?php echo htmlspecialchars($apiError); ?>
    </div>
<?php endif; ?>

<?php if (!empty($matchedProfiles)): ?>
<!-- ======== Matched Profiles (Horizontal) ======== -->
<div class="mb-4">
    <div class="ms-section-head">
        <h3><i class="fas fa-star me-2" style="color:var(--ms-primary);"></i>Matched Profiles</h3>
        <a href="search.php">View All <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="ms-hscroll">
        <?php foreach ($matchedProfiles as $p): ?>
            <?php
            $imgUrl   = profileImageUrl($p);
            $private  = isPhotoPrivate($p);
            $age      = isset($p['age']) ? (int) $p['age'] : '';
            $city     = $p['city'] ?? '';
            $edu      = $p['education'] ?? '';
            $verified = !empty($p['isVerified']) && (int) $p['isVerified'] === 1;
            $name     = trim(($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? ''));
            $pid      = (int) ($p['id'] ?? 0);
            ?>
            <div class="ms-card">
                <div class="ms-card-img">
                    <?php if ($private): ?>
                        <?php if ($imgUrl): ?>
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Photo" style="filter:blur(20px);transform:scale(1.1);">
                        <?php else: ?>
                            <div class="ms-avatar-placeholder"><i class="fas fa-user"></i></div>
                        <?php endif; ?>
                        <div class="ms-privacy-overlay">
                            <i class="fas fa-lock"></i>
                            <span>Photo Private</span>
                        </div>
                    <?php elseif ($imgUrl): ?>
                        <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($name); ?>" loading="lazy">
                    <?php else: ?>
                        <div class="ms-avatar-placeholder"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                    <?php if ($verified): ?>
                        <span class="ms-verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php endif; ?>
                </div>
                <div class="ms-card-body">
                    <div class="ms-card-name"><?php echo htmlspecialchars($name); ?></div>
                    <div class="ms-card-meta">
                        <?php if ($age): ?><i class="fas fa-birthday-cake"></i> <?php echo (int) $age; ?> yrs<?php endif; ?>
                        <?php if ($city): ?>&nbsp;&middot;&nbsp;<i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($city); ?><?php endif; ?>
                    </div>
                    <?php if ($edu): ?>
                        <div class="ms-card-info"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($edu); ?></div>
                    <?php endif; ?>
                    <div class="ms-card-actions">
                        <a href="profile-view.php?id=<?php echo $pid; ?>" class="btn ms-btn-outline">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <button class="btn ms-btn-primary ms-send-request" data-id="<?php echo $pid; ?>">
                            <i class="fas fa-paper-plane"></i> Request
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ======== Recent Members (Grid) ======== -->
<div class="mb-4">
    <div class="ms-section-head">
        <h3><i class="fas fa-users me-2" style="color:var(--ms-primary);"></i>Recent Members</h3>
        <a href="search.php">Search &amp; Filter <i class="fas fa-arrow-right ms-1"></i></a>
    </div>

    <?php if (empty($recentProfiles)): ?>
        <div class="ms-empty">
            <i class="fas fa-user-friends"></i>
            <p>No profiles found at the moment. Please check back later!</p>
        </div>
    <?php else: ?>
        <div class="ms-grid">
            <?php foreach ($recentProfiles as $p): ?>
                <?php
                $imgUrl   = profileImageUrl($p);
                $private  = isPhotoPrivate($p);
                $age      = isset($p['age']) ? (int) $p['age'] : '';
                $city     = $p['city'] ?? '';
                $edu      = $p['education'] ?? '';
                $income   = $p['annualincome'] ?? '';
                $verified = !empty($p['isVerified']) && (int) $p['isVerified'] === 1;
                $name     = trim(($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? ''));
                $pid      = (int) ($p['id'] ?? 0);
                ?>
                <div class="ms-card">
                    <div class="ms-card-img">
                        <?php if ($private): ?>
                            <?php if ($imgUrl): ?>
                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Photo" style="filter:blur(20px);transform:scale(1.1);">
                            <?php else: ?>
                                <div class="ms-avatar-placeholder"><i class="fas fa-user"></i></div>
                            <?php endif; ?>
                            <div class="ms-privacy-overlay">
                                <i class="fas fa-lock"></i>
                                <span>Photo Private</span>
                            </div>
                        <?php elseif ($imgUrl): ?>
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($name); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="ms-avatar-placeholder"><i class="fas fa-user"></i></div>
                        <?php endif; ?>
                        <?php if ($verified): ?>
                            <span class="ms-verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                        <?php endif; ?>
                    </div>
                    <div class="ms-card-body">
                        <div class="ms-card-name"><?php echo htmlspecialchars($name); ?></div>
                        <div class="ms-card-meta">
                            <?php if ($age): ?><i class="fas fa-birthday-cake"></i> <?php echo (int) $age; ?> yrs<?php endif; ?>
                            <?php if ($city): ?>&nbsp;&middot;&nbsp;<i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($city); ?><?php endif; ?>
                        </div>
                        <?php if ($edu || $income): ?>
                            <div class="ms-card-info">
                                <?php if ($edu): ?><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($edu); ?><?php endif; ?>
                                <?php if ($edu && $income): ?> &middot; <?php endif; ?>
                                <?php if ($income): ?><?php echo htmlspecialchars($income); ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="ms-card-actions">
                            <a href="profile-view.php?id=<?php echo $pid; ?>" class="btn ms-btn-outline">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <button class="btn ms-btn-primary ms-send-request" data-id="<?php echo $pid; ?>">
                                <i class="fas fa-paper-plane"></i> Request
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.ms-send-request').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var receiverId = this.getAttribute('data-id');
        var button = this;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch('/Api2/send_proposal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'sender_id=<?php echo (int) $currentUser['user_id']; ?>&receiver_id=' + encodeURIComponent(receiverId) + '&request_type=Interest'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Request Sent!', text: data.message || 'Your interest has been sent.', timer: 2000, showConfirmButton: false });
                button.innerHTML = '<i class="fas fa-check"></i> Sent';
                button.classList.remove('ms-btn-primary');
                button.classList.add('ms-btn-outline');
            } else {
                Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not send request.' });
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-paper-plane"></i> Request';
            }
        })
        .catch(function() {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-paper-plane"></i> Request';
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/user_footer.php'; ?>
