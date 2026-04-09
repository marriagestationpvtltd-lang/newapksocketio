<?php
$title = 'Payments';
require_once 'includes/header.php';

// Fetch payments from API
$payments = [];
$summary  = [];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://digitallami.com/api9/get_payments.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
curl_close($ch);
if ($response) {
    $decoded = json_decode($response, true);
    if (!empty($decoded['success'])) {
        $payments = $decoded['data'];
        $summary  = $decoded['summary'];
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="fw-bold">Payments</h4>
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
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                            <i class="fas fa-rupee-sign text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Total Earnings</h6>
                        <h4 class="mb-0"><?php echo htmlspecialchars($summary['total_earning'] ?? 'Rs 0.00'); ?></h4>
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
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-box text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Packages Sold</h6>
                        <h4 class="mb-0"><?php echo number_format($summary['total_packages_sold'] ?? 0); ?></h4>
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
                            <i class="fas fa-check-circle text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Active Packages</h6>
                        <h4 class="mb-0"><?php echo number_format($summary['active_packages'] ?? 0); ?></h4>
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
                        <div class="p-3 rounded" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                            <i class="fas fa-times-circle text-white"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Expired Packages</h6>
                        <h4 class="mb-0"><?php echo number_format($summary['expired_packages'] ?? 0); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payments Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">All Payments</h5>
    </div>
    <div class="card-body">
        <?php if (empty($payments)): ?>
            <div class="text-center py-5">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No payments found</h5>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="paymentsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Package</th>
                        <th>Price</th>
                        <th>Paid By</th>
                        <th>Status</th>
                        <th>Purchase Date</th>
                        <th>Expire Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?php echo intval($p['id']); ?></td>
                        <td>
                            <div>
                                <h6 class="mb-0"><?php echo htmlspecialchars(trim($p['firstName'] . ' ' . $p['lastName'])); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($p['email']); ?></small>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($p['package_name']); ?></td>
                        <td><?php echo htmlspecialchars($p['package_price']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($p['paidby'] ?? '-')); ?></td>
                        <td>
                            <?php if (($p['package_status'] ?? '') === 'active'): ?>
                                <span class="badge badge-approved">Active</span>
                            <?php else: ?>
                                <span class="badge badge-rejected">Expired</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?php echo htmlspecialchars($p['purchasedate']); ?></small></td>
                        <td><small><?php echo htmlspecialchars($p['expiredate']); ?></small></td>
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
    $('#paymentsTable').DataTable({
        pageLength: 25,
        responsive: true,
        order: [[0, 'desc']]
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
