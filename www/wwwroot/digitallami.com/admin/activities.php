<?php
$title = 'User Activities';
require_once 'includes/header.php';

$apiBase = APP_PUBLIC_BASE_URL . '/api9';

// Read filter params
$filterUserId       = isset($_GET['user_id'])       ? (int)$_GET['user_id']            : 0;
$filterType         = isset($_GET['activity_type'])  ? trim($_GET['activity_type'])      : '';
$filterDateFrom     = isset($_GET['date_from'])      ? trim($_GET['date_from'])          : '';
$filterDateTo       = isset($_GET['date_to'])        ? trim($_GET['date_to'])            : '';
$filterSearch       = isset($_GET['search'])         ? trim($_GET['search'])             : '';
$page               = max(1, (int)($_GET['page']     ?? 1));
$limit              = 50;

// Build query string for API
$params = [
    'page'  => $page,
    'limit' => $limit,
];
if ($filterUserId > 0)         $params['user_id']       = $filterUserId;
if (!empty($filterType))       $params['activity_type'] = $filterType;
if (!empty($filterDateFrom))   $params['date_from']     = $filterDateFrom;
if (!empty($filterDateTo))     $params['date_to']       = $filterDateTo;
if (!empty($filterSearch))     $params['search']        = $filterSearch;

$queryStr = http_build_query($params);

$activities  = [];
$totalCount  = 0;
$totalPages  = 1;

$ch = curl_init("$apiBase/get_user_activities.php?$queryStr");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

if ($response) {
    $decoded = json_decode($response, true);
    if (!empty($decoded['success'])) {
        $activities = $decoded['activities'] ?? [];
        $totalCount = $decoded['total']       ?? 0;
        $totalPages = $decoded['total_pages'] ?? 1;
    }
}

$activityTypes = [
    ''                  => 'All Types',
    'like_sent'         => 'Like Sent',
    'like_removed'      => 'Like Removed',
    'message_sent'      => 'Message Sent',
    'request_sent'      => 'Request Sent',
    'request_accepted'  => 'Request Accepted',
    'request_rejected'  => 'Request Rejected',
    'call_made'         => 'Call Made',
    'call_received'     => 'Call Received',
    'profile_viewed'    => 'Profile Viewed',
    'login'             => 'Login',
    'logout'            => 'Logout',
    'photo_uploaded'    => 'Photo Uploaded',
    'package_bought'    => 'Package Bought',
];

$activityIcons = [
    'like_sent'         => ['fas fa-heart',           '#ef4444'],
    'like_removed'      => ['fas fa-heart-broken',    '#6b7280'],
    'message_sent'      => ['fas fa-comment',         '#3b82f6'],
    'request_sent'      => ['fas fa-paper-plane',     '#8b5cf6'],
    'request_accepted'  => ['fas fa-check-circle',    '#10b981'],
    'request_rejected'  => ['fas fa-times-circle',    '#ef4444'],
    'call_made'         => ['fas fa-phone',           '#06b6d4'],
    'call_received'     => ['fas fa-phone-incoming',  '#0284c7'],
    'profile_viewed'    => ['fas fa-eye',             '#f59e0b'],
    'login'             => ['fas fa-sign-in-alt',     '#10b981'],
    'logout'            => ['fas fa-sign-out-alt',    '#6b7280'],
    'photo_uploaded'    => ['fas fa-camera',          '#ec4899'],
    'package_bought'    => ['fas fa-box',             '#f59e0b'],
];

function activityBadgeHtml($type, $icons) {
    $label  = ucwords(str_replace('_', ' ', $type));
    $icon   = $icons[$type][0] ?? 'fas fa-circle';
    $color  = $icons[$type][1] ?? '#6b7280';
    return "<span style=\"display:inline-flex;align-items:center;gap:5px;background:{$color}22;color:{$color};padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;\">
                <i class=\"{$icon}\" style=\"font-size:11px;\"></i>{$label}
            </span>";
}

// Build pagination URL helper
function paginateUrl($page, $params) {
    $p = $params;
    $p['page'] = $page;
    return '?' . http_build_query($p);
}
$filterParams = array_filter([
    'user_id'       => $filterUserId ?: null,
    'activity_type' => $filterType ?: null,
    'date_from'     => $filterDateFrom ?: null,
    'date_to'       => $filterDateTo ?: null,
    'search'        => $filterSearch ?: null,
]);
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="fw-bold mb-0">User Activities</h4>
            <span class="badge text-white" style="background:linear-gradient(135deg,#667eea,#764ba2);font-size:14px;padding:8px 16px;">
                <?php echo number_format($totalCount); ?> records
            </span>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title"><i class="fas fa-filter me-2"></i>Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="activities.php">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">User ID</label>
                    <input type="number" class="form-control form-control-sm" name="user_id"
                           placeholder="e.g. 42" min="1"
                           value="<?php echo $filterUserId > 0 ? $filterUserId : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Activity Type</label>
                    <select class="form-select form-select-sm" name="activity_type">
                        <?php foreach ($activityTypes as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>"
                                <?php echo $filterType === $val ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">From Date</label>
                    <input type="date" class="form-control form-control-sm" name="date_from"
                           value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">To Date</label>
                    <input type="date" class="form-control form-control-sm" name="date_to"
                           value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Search</label>
                    <input type="text" class="form-control form-control-sm" name="search"
                           placeholder="Name / description..."
                           value="<?php echo htmlspecialchars($filterSearch); ?>">
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="activities.php" class="btn btn-secondary btn-sm flex-fill">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Activities Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title">
            Activity Log
            <?php if ($filterUserId > 0): ?>
                <span class="badge bg-primary ms-2">User #<?php echo $filterUserId; ?></span>
            <?php endif; ?>
        </h5>
        <button class="btn btn-sm btn-light" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($activities)): ?>
            <div class="text-center py-5">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No activities found</h5>
                <?php if (!empty($filterParams)): ?>
                    <a href="activities.php" class="btn btn-outline-secondary mt-2">
                        <i class="fas fa-times me-2"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="activitiesTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Activity</th>
                        <th>Target</th>
                        <th>Description</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $act): ?>
                    <tr>
                        <td class="text-muted small"><?php echo intval($act['id']); ?></td>
                        <td>
                            <div>
                                <a href="activities.php?user_id=<?php echo intval($act['user_id']); ?>"
                                   class="fw-semibold text-decoration-none" style="color:#667eea;">
                                    <?php echo htmlspecialchars($act['user_name'] ?: 'User #' . $act['user_id']); ?>
                                </a>
                                <small class="text-muted d-block">ID: <?php echo intval($act['user_id']); ?></small>
                            </div>
                        </td>
                        <td><?php echo activityBadgeHtml($act['activity_type'], $activityIcons); ?></td>
                        <td>
                            <?php if (!empty($act['target_name'])): ?>
                                <a href="activities.php?user_id=<?php echo intval($act['target_id']); ?>"
                                   class="text-decoration-none" style="color:#764ba2;">
                                    <?php echo htmlspecialchars($act['target_name']); ?>
                                </a>
                                <small class="text-muted d-block">ID: <?php echo intval($act['target_id']); ?></small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-muted small" style="max-width:200px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                  title="<?php echo htmlspecialchars($act['description'] ?? ''); ?>">
                                <?php echo htmlspecialchars($act['description'] ?? '—'); ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($act['created_at']); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Page <?php echo $page; ?> of <?php echo $totalPages; ?> &nbsp;|&nbsp; Total: <?php echo number_format($totalCount); ?> records
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo paginateUrl($page - 1, $filterParams); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                $startP = max(1, $page - 2);
                $endP   = min($totalPages, $page + 2);
                for ($p = $startP; $p <= $endP; $p++): ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo paginateUrl($p, $filterParams); ?>"><?php echo $p; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo paginateUrl($page + 1, $filterParams); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
