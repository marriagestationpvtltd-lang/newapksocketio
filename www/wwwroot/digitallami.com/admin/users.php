<?php
$title = 'Users';
require_once 'includes/header.php';

// Fetch users from API
$users = [];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://digitallami.com/api9/get_users.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
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
$onlineCount   = count(array_filter($users, fn($u) => $u['isOnline'] == 1));
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
    <div class="col-md-4 mb-3">
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
    <div class="col-md-4 mb-3">
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
    <div class="col-md-4 mb-3">
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
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">All Users</h5>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $user): ?>
                    <tr>
                        <td><?php echo intval($user['id']); ?></td>
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
                                    <h6 class="mb-0"><?php echo htmlspecialchars(trim($user['firstName'] . ' ' . $user['lastName'])); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars(ucfirst($user['gender'] ?? '-')); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($user['usertype'] ?? '-')); ?></td>
                        <td>
                            <?php if ($user['isActive'] == 1): ?>
                                <span class="badge badge-approved">Active</span>
                            <?php else: ?>
                                <span class="badge badge-rejected">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['isVerified'] == 1): ?>
                                <span class="badge badge-approved">Yes</span>
                            <?php else: ?>
                                <span class="badge badge-pending">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['isOnline'] == 1): ?>
                                <span class="badge" style="background:#d1fae5;color:#059669;">
                                    <i class="fas fa-circle" style="font-size:8px;"></i> Online
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">Offline</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo !empty($user['lastLogin']) ? htmlspecialchars($user['lastLogin']) : '-'; ?>
                            </small>
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

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        pageLength: 25,
        responsive: true,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: [5, 6] }
        ]
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
