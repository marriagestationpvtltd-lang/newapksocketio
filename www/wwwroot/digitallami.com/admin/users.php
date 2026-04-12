<?php
$title = 'Users';
require_once 'includes/header.php';

$apiBase = 'https://digitallami.com/api9';

// Handle activate / deactivate action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (in_array($action, ['activate', 'deactivate'], true) && $userId > 0) {
        $payload = json_encode(['user_id' => $userId, 'action' => $action]);

        $ch = curl_init("$apiBase/update_user_status.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res     = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        // Return JSON for AJAX calls
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            if ($curlErr || $res === false) {
                echo json_encode(['success' => false, 'message' => 'Network error communicating with API']);
            } else {
                echo $res;
            }
            exit;
        }
    }
}

// Fetch users from API
$users = [];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$apiBase/get_users.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);
if ($response) {
    $decoded = json_decode($response, true);
    if (!empty($decoded['success'])) {
        $users = $decoded['data'];
    }
}

$totalUsers    = count($users);
$verifiedCount = count(array_filter($users, fn($u) => $u['isVerified'] == 1));
$onlineCount   = count(array_filter($users, fn($u) => $u['isOnline']   == 1));
$activeCount   = count(array_filter($users, fn($u) => $u['isActive']   == 1));
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="fw-bold">Users Management</h4>
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="fas fa-sync-alt me-2"></i> Refresh
            </button>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-users text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Total Users</h6>
                        <h3 class="mb-0"><?php echo number_format($totalUsers); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="fas fa-user-check text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Verified</h6>
                        <h3 class="mb-0"><?php echo number_format($verifiedCount); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #06b6d4, #0284c7);">
                            <i class="fas fa-circle text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Online</h6>
                        <h3 class="mb-0"><?php echo number_format($onlineCount); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="fas fa-user-cog text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Active</h6>
                        <h3 class="mb-0"><?php echo number_format($activeCount); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="card-title">All Users</h5>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <select id="filterGender" class="form-select form-select-sm" style="width:auto;">
                <option value="">All Genders</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>
            <select id="filterStatus" class="form-select form-select-sm" style="width:auto;">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No users found</h5>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Gender</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Verified</th>
                        <th>Online</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <?php
                        $isActive   = intval($user['isActive'])   === 1;
                        $isVerified = intval($user['isVerified']) === 1;
                        $isOnline   = intval($user['isOnline'])   === 1;
                        $uid        = intval($user['id']);
                        $fullName   = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
                    ?>
                    <tr data-gender="<?php echo strtolower(htmlspecialchars($user['gender'] ?? '')); ?>"
                        data-status="<?php echo $isActive ? 'active' : 'inactive'; ?>">
                        <td><?php echo $uid; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>"
                                             alt="Profile"
                                             style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                                    <?php else: ?>
                                        <div style="width:36px;height:36px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#6b7280;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($fullName ?: '—'); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['email'] ?? ''); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars(ucfirst($user['gender'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($user['usertype'] ?? '—')); ?></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge badge-approved">Active</span>
                            <?php else: ?>
                                <span class="badge badge-rejected">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isVerified): ?>
                                <span class="badge badge-approved">Yes</span>
                            <?php else: ?>
                                <span class="badge badge-pending">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isOnline): ?>
                                <span class="badge" style="background:#d1fae5;color:#059669;">
                                    <i class="fas fa-circle" style="font-size:8px;"></i> Online
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">Offline</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo !empty($user['lastLogin']) ? htmlspecialchars($user['lastLogin']) : '—'; ?>
                            </small>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a href="activities.php?user_id=<?php echo $uid; ?>"
                                   class="btn btn-sm btn-outline-primary" title="View Activities">
                                    <i class="fas fa-history"></i>
                                </a>
                                <?php if ($isActive): ?>
                                    <button class="btn btn-sm btn-outline-warning"
                                            title="Deactivate User"
                                            onclick="toggleUserStatus(<?php echo $uid; ?>, 'deactivate', <?php echo json_encode($fullName ?: "User #$uid"); ?>)">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-success"
                                            title="Activate User"
                                            onclick="toggleUserStatus(<?php echo $uid; ?>, 'activate', <?php echo json_encode($fullName ?: "User #$uid"); ?>)">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
var dt;

$(document).ready(function () {
    dt = $('#usersTable').DataTable({
        pageLength: 25,
        responsive: true,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: [5, 6, 8] }
        ]
    });

    // Gender column search
    $('#filterGender').on('change', function () {
        dt.column(2).search($(this).val()).draw();
    });

    // Status filter using row data attribute (scoped to usersTable only)
    $('#filterStatus').on('change', function () {
        var val = $(this).val();
        // Remove any previous status filter for this table
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function (fn) {
            return fn._usersTableFilter !== true;
        });
        if (val) {
            var filterFn = function (settings, data, dataIndex) {
                if (settings.nTable.id !== 'usersTable') return true;
                var row = dt.row(dataIndex).node();
                return $(row).data('status') === val;
            };
            filterFn._usersTableFilter = true;
            $.fn.dataTable.ext.search.push(filterFn);
        }
        dt.draw();
    });
});

function toggleUserStatus(userId, action, name) {
    var isDeactivate = action === 'deactivate';
    Swal.fire({
        title: isDeactivate ? 'Deactivate User?' : 'Activate User?',
        text: (isDeactivate ? 'Deactivate' : 'Activate') + ' user "' + name + '"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: isDeactivate ? '#ef4444' : '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: isDeactivate ? 'Yes, deactivate' : 'Yes, activate'
    }).then(function (result) {
        if (result.isConfirmed) {
            $.ajax({
                type: 'POST',
                url: 'users.php',
                data: { action: action, user_id: userId },
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function (res) {
                    if (res.success) {
                        Swal.fire('Done!', res.message, 'success').then(function () {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', res.message || 'Action failed', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Server error. Please try again.', 'error');
                }
            });
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
