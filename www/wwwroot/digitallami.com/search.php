<?php
/**
 * search.php – Search & Filter Profiles
 * Mirrors the mobile SearchPage / filterPage.
 */
$title = 'Search';
require_once __DIR__ . '/includes/user_header.php';

// --- Collect filter params from GET ---
$filters = [
    'minage'                 => isset($_GET['minage']) && $_GET['minage'] !== '' ? (int) $_GET['minage'] : null,
    'maxage'                 => isset($_GET['maxage']) && $_GET['maxage'] !== '' ? (int) $_GET['maxage'] : null,
    'minheight'              => isset($_GET['minheight']) && $_GET['minheight'] !== '' ? (int) $_GET['minheight'] : null,
    'maxheight'              => isset($_GET['maxheight']) && $_GET['maxheight'] !== '' ? (int) $_GET['maxheight'] : null,
    'religion'               => isset($_GET['religion']) && $_GET['religion'] !== '' ? (int) $_GET['religion'] : null,
    'has_photo'              => !empty($_GET['has_photo']) ? '1' : null,
    'is_verified'            => !empty($_GET['is_verified']) ? '1' : null,
    'usertype'               => isset($_GET['usertype']) && $_GET['usertype'] !== '' ? $_GET['usertype'] : null,
    'days_since_registration'=> isset($_GET['days_since_registration']) && $_GET['days_since_registration'] !== '' ? (int) $_GET['days_since_registration'] : null,
    'search_type'            => isset($_GET['search_type']) && $_GET['search_type'] !== '' ? $_GET['search_type'] : null,
    'search_value'           => isset($_GET['search_value']) && $_GET['search_value'] !== '' ? $_GET['search_value'] : null,
];
$hasFilters = !empty(array_filter($filters, function ($v) { return $v !== null; }));

// --- Build API URL ---
$apiParams = ['user_id' => $currentUser['user_id']];
foreach ($filters as $key => $val) {
    if ($val !== null) {
        $apiParams[$key] = $val;
    }
}
$apiUrl = 'https://digitallami.com/Api2/search_opposite_gender.php?' . http_build_query($apiParams);

$profiles   = [];
$apiError   = '';
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

function searchProfileImageUrl(array $profile): string
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

function searchIsPhotoPrivate(array $profile): bool
{
    return isset($profile['privacy']) && (int) $profile['privacy'] === 1
        && (!isset($profile['photo_request']) || $profile['photo_request'] !== 'accepted');
}

$religions = [
    1 => 'Hindu',
    2 => 'Buddhist',
    3 => 'Muslim',
    4 => 'Christian',
    5 => 'Kirant',
    6 => 'Jain',
    7 => 'Sikh',
    8 => 'Other',
];
?>

<style>
/* ---------- Search Layout ---------- */
.ms-search-wrap {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}

/* ---------- Sidebar ---------- */
.ms-filter-sidebar {
    width: 280px;
    flex-shrink: 0;
    background: var(--ms-white);
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    padding: 20px;
    position: sticky;
    top: 86px;
}
.ms-filter-sidebar h4 {
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 18px;
    color: var(--ms-text);
}
.ms-filter-sidebar h4 i { color: var(--ms-primary); margin-right: 6px; }

.ms-filter-group { margin-bottom: 16px; }
.ms-filter-group label {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--ms-text-muted);
    margin-bottom: 4px;
    display: block;
}
.ms-filter-group .form-control,
.ms-filter-group .form-select {
    font-size: 0.88rem;
    border-radius: 8px;
    border: 1px solid var(--ms-border);
    padding: 7px 12px;
}
.ms-filter-group .form-control:focus,
.ms-filter-group .form-select:focus {
    border-color: var(--ms-primary);
    box-shadow: 0 0 0 3px rgba(249,14,24,0.1);
}
.ms-filter-row {
    display: flex;
    gap: 8px;
}
.ms-filter-row .form-control { flex: 1; }
.ms-filter-check {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.ms-filter-check input[type="checkbox"] {
    accent-color: var(--ms-primary);
    width: 16px;
    height: 16px;
}
.ms-filter-check label {
    margin: 0;
    font-size: 0.88rem;
    color: var(--ms-text);
    cursor: pointer;
}
.ms-filter-actions {
    display: flex;
    gap: 8px;
    margin-top: 20px;
}
.ms-filter-actions .btn { flex: 1; font-size: 0.88rem; border-radius: 8px; padding: 8px 12px; }
.ms-btn-search {
    background: var(--ms-primary);
    border-color: var(--ms-primary);
    color: #fff;
    font-weight: 600;
}
.ms-btn-search:hover { background: var(--ms-primary-dark); border-color: var(--ms-primary-dark); color: #fff; }
.ms-btn-clear {
    background: transparent;
    border: 1px solid var(--ms-border);
    color: var(--ms-text-muted);
}
.ms-btn-clear:hover { border-color: var(--ms-primary); color: var(--ms-primary); }

.ms-filter-divider { border: 0; border-top: 1px solid var(--ms-border); margin: 14px 0; }

/* ---------- Results ---------- */
.ms-results { flex: 1; min-width: 0; }
.ms-results-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    flex-wrap: wrap;
    gap: 8px;
}
.ms-results-count {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--ms-text);
}
.ms-results-count span { color: var(--ms-primary); font-weight: 700; }

/* Mobile filter toggle */
.ms-filter-toggle {
    display: none;
    background: var(--ms-primary);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 18px;
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 16px;
    cursor: pointer;
}
.ms-filter-toggle i { margin-right: 6px; }
.ms-filter-toggle:hover { background: var(--ms-primary-dark); }

/* ---------- Profile Card (same as home) ---------- */
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
    position: relative; width: 100%; padding-top: 110%;
    background: #f0f0f0; overflow: hidden;
}
.ms-card-img img {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%; object-fit: cover;
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
    padding: 14px 16px 16px; flex: 1;
    display: flex; flex-direction: column;
}
.ms-card-name {
    font-weight: 700; font-size: 1rem;
    margin-bottom: 2px; color: var(--ms-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ms-card-meta {
    font-size: 0.82rem; color: var(--ms-text-muted); margin-bottom: 6px;
}
.ms-card-meta i { margin-right: 3px; }
.ms-card-info {
    font-size: 0.8rem; color: var(--ms-text-muted);
    margin-bottom: 12px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ms-card-actions { display: flex; gap: 8px; margin-top: auto; }
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
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}
@media (max-width: 1199.98px) { .ms-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575.98px)  { .ms-grid { grid-template-columns: 1fr; } }

/* ---------- Empty ---------- */
.ms-empty {
    text-align: center; padding: 60px 20px; color: var(--ms-text-muted);
}
.ms-empty i { font-size: 3rem; margin-bottom: 12px; display: block; color: #ddd; }
.ms-empty p { font-size: 1rem; }

/* ---------- Mobile responsive ---------- */
@media (max-width: 991.98px) {
    .ms-filter-toggle { display: inline-flex; align-items: center; }
    .ms-filter-sidebar {
        display: none;
        width: 100%;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 1050;
        border-radius: 0;
        overflow-y: auto;
        padding: 20px;
    }
    .ms-filter-sidebar.ms-open { display: block; }
    .ms-filter-close {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }
    .ms-filter-close h4 { margin: 0; }
    .ms-filter-close-btn {
        background: none; border: none; font-size: 1.4rem;
        color: var(--ms-text-muted); cursor: pointer;
        padding: 4px 8px;
    }
    .ms-filter-close-btn:hover { color: var(--ms-primary); }
    .ms-search-wrap { flex-direction: column; }
}
@media (min-width: 992px) {
    .ms-filter-close { display: none; }
}
</style>

<!-- Mobile filter toggle -->
<button class="ms-filter-toggle" id="msFilterToggle">
    <i class="fas fa-sliders-h"></i> Filters
    <?php if ($hasFilters): ?>
        <span class="badge bg-light text-danger ms-1">Active</span>
    <?php endif; ?>
</button>

<div class="ms-search-wrap">
    <!-- ======== Sidebar Filters ======== -->
    <aside class="ms-filter-sidebar" id="msFilterSidebar">
        <div class="ms-filter-close">
            <h4><i class="fas fa-sliders-h"></i> Filters</h4>
            <button class="ms-filter-close-btn" id="msFilterClose" type="button">&times;</button>
        </div>

        <form method="GET" action="search.php" id="msFilterForm">
            <!-- Quick Search -->
            <div class="ms-filter-group">
                <label>Quick Search</label>
                <div class="ms-filter-row" style="margin-bottom:6px;">
                    <select name="search_type" class="form-select" style="flex:0.8;">
                        <option value="">Type</option>
                        <option value="name"  <?php echo ($filters['search_type'] === 'name')  ? 'selected' : ''; ?>>Name</option>
                        <option value="id"    <?php echo ($filters['search_type'] === 'id')    ? 'selected' : ''; ?>>ID</option>
                        <option value="email" <?php echo ($filters['search_type'] === 'email') ? 'selected' : ''; ?>>Email</option>
                    </select>
                </div>
                <input type="text" name="search_value" class="form-control"
                       placeholder="Enter search term..."
                       value="<?php echo htmlspecialchars($filters['search_value'] ?? ''); ?>">
            </div>

            <hr class="ms-filter-divider">

            <!-- Age Range -->
            <div class="ms-filter-group">
                <label>Age Range</label>
                <div class="ms-filter-row">
                    <input type="number" name="minage" class="form-control" placeholder="Min"
                           min="18" max="80"
                           value="<?php echo $filters['minage'] !== null ? (int) $filters['minage'] : ''; ?>">
                    <input type="number" name="maxage" class="form-control" placeholder="Max"
                           min="18" max="80"
                           value="<?php echo $filters['maxage'] !== null ? (int) $filters['maxage'] : ''; ?>">
                </div>
            </div>

            <!-- Height Range -->
            <div class="ms-filter-group">
                <label>Height (cm)</label>
                <div class="ms-filter-row">
                    <input type="number" name="minheight" class="form-control" placeholder="Min"
                           min="100" max="250"
                           value="<?php echo $filters['minheight'] !== null ? (int) $filters['minheight'] : ''; ?>">
                    <input type="number" name="maxheight" class="form-control" placeholder="Max"
                           min="100" max="250"
                           value="<?php echo $filters['maxheight'] !== null ? (int) $filters['maxheight'] : ''; ?>">
                </div>
            </div>

            <!-- Religion -->
            <div class="ms-filter-group">
                <label>Religion</label>
                <select name="religion" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($religions as $rid => $rname): ?>
                        <option value="<?php echo $rid; ?>"
                            <?php echo ($filters['religion'] !== null && (int) $filters['religion'] === $rid) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($rname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <hr class="ms-filter-divider">

            <!-- Checkboxes -->
            <div class="ms-filter-check">
                <input type="checkbox" name="has_photo" id="fHasPhoto" value="1"
                    <?php echo $filters['has_photo'] ? 'checked' : ''; ?>>
                <label for="fHasPhoto">Has Photo Only</label>
            </div>
            <div class="ms-filter-check">
                <input type="checkbox" name="is_verified" id="fVerified" value="1"
                    <?php echo $filters['is_verified'] ? 'checked' : ''; ?>>
                <label for="fVerified">Verified Only</label>
            </div>

            <!-- Member Type -->
            <div class="ms-filter-group">
                <label>Member Type</label>
                <select name="usertype" class="form-select">
                    <option value="">All</option>
                    <option value="free" <?php echo ($filters['usertype'] === 'free') ? 'selected' : ''; ?>>Free</option>
                    <option value="paid" <?php echo ($filters['usertype'] === 'paid') ? 'selected' : ''; ?>>Premium</option>
                </select>
            </div>

            <!-- Recently Registered -->
            <div class="ms-filter-group">
                <label>Registered Within (days)</label>
                <input type="number" name="days_since_registration" class="form-control"
                       placeholder="e.g. 30" min="1"
                       value="<?php echo $filters['days_since_registration'] !== null ? (int) $filters['days_since_registration'] : ''; ?>">
            </div>

            <!-- Actions -->
            <div class="ms-filter-actions">
                <button type="submit" class="btn ms-btn-search">
                    <i class="fas fa-search me-1"></i> Search
                </button>
                <a href="search.php" class="btn ms-btn-clear">
                    <i class="fas fa-times me-1"></i> Clear
                </a>
            </div>
        </form>
    </aside>

    <!-- ======== Results ======== -->
    <div class="ms-results">
        <?php if ($apiError): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <?php echo htmlspecialchars($apiError); ?>
            </div>
        <?php endif; ?>

        <div class="ms-results-bar">
            <div class="ms-results-count">
                <?php if ($hasFilters): ?>
                    <span><?php echo $totalCount; ?></span> profile<?php echo $totalCount !== 1 ? 's' : ''; ?> found
                <?php else: ?>
                    Showing all <span><?php echo $totalCount; ?></span> profile<?php echo $totalCount !== 1 ? 's' : ''; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($profiles)): ?>
            <div class="ms-empty">
                <i class="fas fa-search"></i>
                <p>No profiles found matching your criteria.<br>Try adjusting your filters.</p>
            </div>
        <?php else: ?>
            <div class="ms-grid">
                <?php foreach ($profiles as $p): ?>
                    <?php
                    $imgUrl   = searchProfileImageUrl($p);
                    $private  = searchIsPhotoPrivate($p);
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
</div>

<script>
// Mobile filter panel toggle
(function() {
    var sidebar = document.getElementById('msFilterSidebar');
    var toggle  = document.getElementById('msFilterToggle');
    var close   = document.getElementById('msFilterClose');

    if (toggle) {
        toggle.addEventListener('click', function() {
            sidebar.classList.add('ms-open');
            document.body.style.overflow = 'hidden';
        });
    }
    if (close) {
        close.addEventListener('click', function() {
            sidebar.classList.remove('ms-open');
            document.body.style.overflow = '';
        });
    }
})();

// Send Request buttons
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
