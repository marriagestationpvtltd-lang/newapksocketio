<?php
$title = 'Dashboard';
require_once 'includes/header.php';

// Fetch real dashboard data from API
$dashboardData = null;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://digitallami.com/api9/get_dashboard.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);
if ($response) {
    $decoded = json_decode($response, true);
    if (!empty($decoded['success'])) {
        $dashboardData = $decoded['dashboard'];
    }
}

$totalUsers      = $dashboardData['users']['total']                    ?? 0;
$todayUsers      = $dashboardData['users']['today_registered']         ?? 0;
$monthUsers      = $dashboardData['users']['this_month_registered']    ?? 0;
$onlineUsers     = $dashboardData['users']['online']                   ?? 0;
$verifiedUsers   = $dashboardData['users']['verified']                 ?? 0;
$totalEarning    = $dashboardData['payments']['total_earning']         ?? 'Rs 0.00';
$todayEarning    = $dashboardData['payments']['today_earning']         ?? 'Rs 0.00';
$monthEarning    = $dashboardData['payments']['this_month_earning']    ?? 'Rs 0.00';
$activePackages  = $dashboardData['payments']['active_packages']       ?? 0;
$totalSold       = $dashboardData['payments']['total_sold']            ?? 0;

// Fetch documents stats
$documents = [];
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, 'https://digitallami.com/api9/get_documents.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
$docResponse = curl_exec($ch2);
curl_close($ch2);
if ($docResponse) {
    $docDecoded = json_decode($docResponse, true);
    if (!empty($docDecoded['success'])) {
        $documents = $docDecoded['data'];
    }
}
$pendingDocs  = count(array_filter($documents, fn($d) => $d['status'] === 'pending'));
$approvedDocs = count(array_filter($documents, fn($d) => $d['status'] === 'approved'));
$rejectedDocs = count(array_filter($documents, fn($d) => $d['status'] === 'rejected'));
?>

<!-- Stats Cards Row 1: Users -->
<div class="row">
    <div class="col-md-3 mb-4">
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

    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="fas fa-user-plus text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Today Registered</h6>
                        <h3 class="mb-0"><?php echo number_format($todayUsers); ?></h3>
                        <small class="text-muted">This month: <?php echo number_format($monthUsers); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #06b6d4, #0284c7);">
                            <i class="fas fa-circle text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Online Now</h6>
                        <h3 class="mb-0"><?php echo number_format($onlineUsers); ?></h3>
                        <small class="text-muted">Verified: <?php echo number_format($verifiedUsers); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="fas fa-box text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Active Packages</h6>
                        <h3 class="mb-0"><?php echo number_format($activePackages); ?></h3>
                        <small class="text-muted">Total sold: <?php echo number_format($totalSold); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards Row 2: Earnings & Documents -->
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                            <i class="fas fa-rupee-sign text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Total Earnings</h6>
                        <h3 class="mb-0" style="font-size: 1.2rem;"><?php echo htmlspecialchars($totalEarning); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #ec4899, #be185d);">
                            <i class="fas fa-calendar-day text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Today's Earnings</h6>
                        <h3 class="mb-0" style="font-size: 1.2rem;"><?php echo htmlspecialchars($todayEarning); ?></h3>
                        <small class="text-muted">This month: <?php echo htmlspecialchars($monthEarning); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="fas fa-file-alt text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Pending Documents</h6>
                        <h3 class="mb-0"><?php echo number_format($pendingDocs); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="fas fa-check-circle text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Approved Docs</h6>
                        <h3 class="mb-0"><?php echo number_format($approvedDocs); ?></h3>
                        <small class="text-muted">Rejected: <?php echo number_format($rejectedDocs); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title">Quick Navigation</h5>
                <button class="btn btn-sm btn-light" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="documents.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-file-alt fa-2x d-block mb-2"></i>
                            Document Verification
                            <?php if ($pendingDocs > 0): ?>
                                <span class="badge bg-warning ms-1"><?php echo $pendingDocs; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="users.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-users fa-2x d-block mb-2"></i>
                            Users
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="payments.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-rupee-sign fa-2x d-block mb-2"></i>
                            Payments
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="packages.php" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-box fa-2x d-block mb-2"></i>
                            Packages
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>