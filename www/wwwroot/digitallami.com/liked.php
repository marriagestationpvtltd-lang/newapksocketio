<?php
/**
 * liked.php – Liked / Favorite Profiles
 */
$title = 'Liked Profiles';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/user_header.php';

$userId = (int) $currentUser['user_id'];

// --- Helper: build profile image URL ---
function msLikeImg(?string $pic): string {
    if (empty($pic)) return '';
    if (!preg_match('/^https?:\/\//', $pic)) return APP_API2_BASE_URL . $pic;
    return $pic;
}

// --- Fetch "Liked by Me" ---
$likedByMe    = [];
$likedByMeErr = '';

$ch = curl_init(APP_API2_BASE_URL . 'likelist.php?user_id=' . urlencode($userId) . '&type=sent');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $httpCode !== 200) {
    $likedByMeErr = 'Unable to load liked profiles. Please try again later.';
} else {
    $json = json_decode($resp, true);
    if (!empty($json['success'])) {
        $likedByMe = $json['data'] ?? [];
    } else {
        $likedByMeErr = $json['message'] ?? 'Failed to load liked profiles.';
    }
}

// --- Fetch "Who Liked Me" ---
$whoLikedMe    = [];
$whoLikedMeErr = '';

$ch = curl_init(APP_API2_BASE_URL . 'likelist.php?user_id=' . urlencode($userId) . '&type=received');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $httpCode !== 200) {
    $whoLikedMeErr = 'Unable to load profiles. Please try again later.';
} else {
    $json = json_decode($resp, true);
    if (!empty($json['success'])) {
        $whoLikedMe = $json['data'] ?? [];
    } else {
        $whoLikedMeErr = $json['message'] ?? 'Failed to load profiles.';
    }
}
?>

<style>
/* ---------- Tabs ---------- */
.ms-tabs .nav-tabs {
    border-bottom: 2px solid var(--ms-border);
    margin-bottom: 24px;
}
.ms-tabs .nav-tabs .nav-link {
    color: var(--ms-text-muted);
    font-weight: 600;
    font-size: 0.95rem;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 10px 20px;
    margin-bottom: -2px;
    transition: color 0.2s, border-color 0.2s;
}
.ms-tabs .nav-tabs .nav-link:hover {
    color: var(--ms-primary);
    border-bottom-color: var(--ms-primary-light);
}
.ms-tabs .nav-tabs .nav-link.active {
    color: var(--ms-primary);
    border-bottom: 2px solid var(--ms-primary);
    background: transparent;
}

/* ---------- Profile Card (matches home.php) ---------- */
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

.ms-card-actions {
    display: flex; gap: 8px; margin-top: auto;
}
.ms-card-actions .btn {
    font-size: 0.78rem; padding: 6px 10px; border-radius: 8px; flex: 1;
}
.ms-btn-primary {
    background: var(--ms-primary); border-color: var(--ms-primary); color: #fff;
}
.ms-btn-primary:hover {
    background: var(--ms-primary-dark); border-color: var(--ms-primary-dark); color: #fff;
}
.ms-btn-outline {
    border: 1px solid var(--ms-border); color: var(--ms-text); background: transparent;
}
.ms-btn-outline:hover {
    border-color: var(--ms-primary); color: var(--ms-primary);
}

/* ---------- Grid (matches home.php) ---------- */
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
.ms-empty i {
    font-size: 3rem; margin-bottom: 12px; display: block; color: #ddd;
}
.ms-empty p { font-size: 1rem; }

/* Fade-out animation for unlike */
.ms-card-fadeout {
    animation: msFadeOut 0.4s ease forwards;
}
@keyframes msFadeOut {
    to { opacity: 0; transform: scale(0.95); }
}
</style>

<!-- Page heading -->
<h4 class="mb-3" style="font-weight:700;">
    <i class="fas fa-heart me-2" style="color:var(--ms-primary);"></i>Liked Profiles
</h4>

<!-- Tabs -->
<div class="ms-tabs">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="likedbyme-tab" data-bs-toggle="tab"
                    data-bs-target="#likedByMePane" type="button" role="tab"
                    aria-controls="likedByMePane" aria-selected="true">
                <i class="fas fa-heart me-1"></i> Liked by Me
                <?php if (count($likedByMe)): ?>
                    <span class="badge bg-danger ms-1"><?php echo count($likedByMe); ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="wholikedme-tab" data-bs-toggle="tab"
                    data-bs-target="#whoLikedMePane" type="button" role="tab"
                    aria-controls="whoLikedMePane" aria-selected="false">
                <i class="fas fa-smile-beam me-1"></i> Who Liked Me
                <?php if (count($whoLikedMe)): ?>
                    <span class="badge bg-danger ms-1"><?php echo count($whoLikedMe); ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- ======== Liked by Me Tab ======== -->
        <div class="tab-pane fade show active" id="likedByMePane" role="tabpanel" aria-labelledby="likedbyme-tab">
            <?php if ($likedByMeErr): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <?php echo htmlspecialchars($likedByMeErr); ?>
                </div>
            <?php elseif (empty($likedByMe)): ?>
                <div class="ms-empty">
                    <i class="fas fa-heart"></i>
                    <p>You haven't liked anyone yet. <a href="search.php" style="color:var(--ms-primary);font-weight:600;">Explore profiles</a></p>
                </div>
            <?php else: ?>
                <div class="ms-grid">
                    <?php foreach ($likedByMe as $lp):
                        $lpImg      = msLikeImg($lp['profile_picture'] ?? '');
                        $lpName     = trim(($lp['firstName'] ?? '') . ' ' . ($lp['lastName'] ?? ''));
                        $lpAge      = isset($lp['age']) ? (int) $lp['age'] : '';
                        $lpCity     = $lp['city'] ?? '';
                        $lpId       = (int) ($lp['id'] ?? $lp['user_id'] ?? 0);
                        $lpPrivate  = isset($lp['privacy']) && (int) $lp['privacy'] === 1
                                      && (!isset($lp['photo_request']) || $lp['photo_request'] !== 'accepted');
                        $lpVerified = !empty($lp['isVerified']) && (int) $lp['isVerified'] === 1;
                        $lpLetter   = mb_strtoupper(mb_substr($lpName ?: 'U', 0, 1));
                    ?>
                    <div class="ms-card" id="liked-card-<?php echo $lpId; ?>">
                        <div class="ms-card-img">
                            <?php if ($lpPrivate): ?>
                                <?php if ($lpImg): ?>
                                    <img src="<?php echo htmlspecialchars($lpImg); ?>" alt="Photo" style="filter:blur(20px);transform:scale(1.1);">
                                <?php else: ?>
                                    <div class="ms-avatar-placeholder"><i class="fas fa-user"></i></div>
                                <?php endif; ?>
                                <div class="ms-privacy-overlay">
                                    <i class="fas fa-lock"></i>
                                    <span>Photo Private</span>
                                </div>
                            <?php elseif ($lpImg): ?>
                                <img src="<?php echo htmlspecialchars($lpImg); ?>" alt="<?php echo htmlspecialchars($lpName); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="ms-avatar-placeholder"><i class="fas fa-user"></i></div>
                            <?php endif; ?>
                            <?php if ($lpVerified): ?>
                                <span class="ms-verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                            <?php endif; ?>
                        </div>
                        <div class="ms-card-body">
                            <div class="ms-card-name"><?php echo htmlspecialchars($lpName ?: 'Not specified'); ?></div>
                            <div class="ms-card-meta">
                                <?php if ($lpAge): ?><i class="fas fa-birthday-cake"></i> <?php echo $lpAge; ?> yrs<?php endif; ?>
                                <?php if ($lpAge && $lpCity): ?>&nbsp;&middot;&nbsp;<?php endif; ?>
                                <?php if ($lpCity): ?><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($lpCity); ?><?php endif; ?>
                                <?php if (!$lpAge && !$lpCity): ?>Not specified<?php endif; ?>
                            </div>
                            <div class="ms-card-actions">
                                <a href="profile-view.php?id=<?php echo $lpId; ?>" class="btn ms-btn-outline">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button class="btn btn-outline-danger ms-unlike-btn" data-id="<?php echo $lpId; ?>">
                                    <i class="fas fa-heart-broken"></i> Unlike
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======== Who Liked Me Tab ======== -->
        <div class="tab-pane fade" id="whoLikedMePane" role="tabpanel" aria-labelledby="wholikedme-tab">
            <?php if ($whoLikedMeErr): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <?php echo htmlspecialchars($whoLikedMeErr); ?>
                </div>
            <?php elseif (empty($whoLikedMe)): ?>
                <div class="ms-empty">
                    <i class="fas fa-smile-beam"></i>
                    <p>No one has liked your profile yet. Keep your profile updated!</p>
                </div>
            <?php else: ?>
                <div class="ms-grid">
                    <?php foreach ($whoLikedMe as $wl):
                        $wlImg      = msLikeImg($wl['profile_picture'] ?? '');
                        $wlName     = trim(($wl['firstName'] ?? '') . ' ' . ($wl['lastName'] ?? ''));
                        $wlAge      = isset($wl['age']) ? (int) $wl['age'] : '';
                        $wlCity     = $wl['city'] ?? '';
                        $wlId       = (int) ($wl['id'] ?? $wl['user_id'] ?? 0);
                        $wlPrivate  = isset($wl['privacy']) && (int) $wl['privacy'] === 1
                                      && (!isset($wl['photo_request']) || $wl['photo_request'] !== 'accepted');
                        $wlVerified = !empty($wl['isVerified']) && (int) $wl['isVerified'] === 1;
                        $wlLetter   = mb_strtoupper(mb_substr($wlName ?: 'U', 0, 1));
                    ?>
                    <div class="ms-card" id="wholiked-card-<?php echo $wlId; ?>">
                        <div class="ms-card-img">
                            <?php if ($wlPrivate): ?>
                                <?php if ($wlImg): ?>
                                    <img src="<?php echo htmlspecialchars($wlImg); ?>" alt="Photo" style="filter:blur(20px);transform:scale(1.1);">
                                <?php else: ?>
                                    <div class="ms-avatar-placeholder"><i class="fas fa-user"></i></div>
                                <?php endif; ?>
                                <div class="ms-privacy-overlay">
                                    <i class="fas fa-lock"></i>
                                    <span>Photo Private</span>
                                </div>
                            <?php elseif ($wlImg): ?>
                                <img src="<?php echo htmlspecialchars($wlImg); ?>" alt="<?php echo htmlspecialchars($wlName); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="ms-avatar-placeholder"><i class="fas fa-user"></i></div>
                            <?php endif; ?>
                            <?php if ($wlVerified): ?>
                                <span class="ms-verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                            <?php endif; ?>
                        </div>
                        <div class="ms-card-body">
                            <div class="ms-card-name"><?php echo htmlspecialchars($wlName ?: 'Not specified'); ?></div>
                            <div class="ms-card-meta">
                                <?php if ($wlAge): ?><i class="fas fa-birthday-cake"></i> <?php echo $wlAge; ?> yrs<?php endif; ?>
                                <?php if ($wlAge && $wlCity): ?>&nbsp;&middot;&nbsp;<?php endif; ?>
                                <?php if ($wlCity): ?><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($wlCity); ?><?php endif; ?>
                                <?php if (!$wlAge && !$wlCity): ?>Not specified<?php endif; ?>
                            </div>
                            <div class="ms-card-actions">
                                <a href="profile-view.php?id=<?php echo $wlId; ?>" class="btn ms-btn-outline">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button class="btn ms-btn-primary ms-likeback-btn" data-id="<?php echo $wlId; ?>">
                                    <i class="fas fa-heart"></i> Like Back
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    var currentUserId = <?php echo $userId; ?>;

    // --- Unlike ---
    document.querySelectorAll('.ms-unlike-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var profileId = this.getAttribute('data-id');
            var card = document.getElementById('liked-card-' + profileId);
            var button = this;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('/Api2/like_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: currentUserId, liked_user_id: parseInt(profileId), action: 'unlike' })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    card.classList.add('ms-card-fadeout');
                    setTimeout(function() { card.remove(); }, 400);
                    Swal.fire({
                        icon: 'success',
                        title: 'Unliked',
                        text: data.message || 'Profile removed from your liked list.',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not unlike profile.' });
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-heart-broken"></i> Unlike';
                }
            })
            .catch(function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-heart-broken"></i> Unlike';
            });
        });
    });

    // --- Like Back ---
    document.querySelectorAll('.ms-likeback-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var profileId = this.getAttribute('data-id');
            var button = this;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('/Api2/like_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: currentUserId, liked_user_id: parseInt(profileId), action: 'like' })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    button.innerHTML = '<i class="fas fa-check"></i> Liked ✓';
                    button.classList.remove('ms-btn-primary');
                    button.classList.add('ms-btn-outline');
                    button.style.color = '#28a745';
                    button.style.borderColor = '#28a745';
                    Swal.fire({
                        icon: 'success',
                        title: 'Liked!',
                        text: data.message || 'You liked this profile back!',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not like profile.' });
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-heart"></i> Like Back';
                }
            })
            .catch(function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-heart"></i> Like Back';
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/user_footer.php'; ?>
