<?php
$title = 'Packages';
require_once 'includes/header.php';

$apiBase = APP_PUBLIC_BASE_URL . '/api9';

// Handle Create / Update / Delete actions (JSON POST to API)
$actionMsg = '';
$actionType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $payload = json_encode([
            'name'        => trim($_POST['name'] ?? ''),
            'duration'    => $_POST['duration'] ?? '',
            'description' => trim($_POST['description'] ?? ''),
            'price'       => $_POST['price'] ?? '',
        ]);
        $ch = curl_init("$apiBase/create_package.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($res, true);
        $actionMsg  = $result['message'] ?? 'Unknown error';
        $actionType = !empty($result['success']) ? 'success' : 'danger';

    } elseif ($action === 'update') {
        $payload = json_encode([
            'id'          => intval($_POST['id'] ?? 0),
            'name'        => trim($_POST['name'] ?? ''),
            'duration'    => $_POST['duration'] ?? '',
            'description' => trim($_POST['description'] ?? ''),
            'price'       => $_POST['price'] ?? '',
        ]);
        $ch = curl_init("$apiBase/update_package.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($res, true);
        $actionMsg  = $result['message'] ?? 'Unknown error';
        $actionType = !empty($result['success']) ? 'success' : 'danger';

    } elseif ($action === 'delete') {
        $payload = json_encode(['id' => intval($_POST['id'] ?? 0)]);
        $ch = curl_init("$apiBase/delete_package.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($res, true);
        $actionMsg  = $result['message'] ?? 'Unknown error';
        $actionType = !empty($result['success']) ? 'success' : 'danger';
    }
}

// Fetch packages
$packages = [];
$ch = curl_init("$apiBase/get_packages.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);
if ($response) {
    $decoded = json_decode($response, true);
    if (!empty($decoded['success'])) {
        $packages = $decoded['data'];
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="fw-bold">Packages</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="fas fa-plus me-2"></i> Add Package
            </button>
        </div>
    </div>
</div>

<?php if ($actionMsg): ?>
    <div class="alert alert-<?php echo htmlspecialchars($actionType); ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $actionType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <?php echo htmlspecialchars($actionMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Packages Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">All Packages</h5>
    </div>
    <div class="card-body">
        <?php if (empty($packages)): ?>
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No packages found</h5>
                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="fas fa-plus me-2"></i> Create First Package
                </button>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="packagesTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $pkg): ?>
                    <tr>
                        <td><?php echo intval($pkg['id']); ?></td>
                        <td><strong><?php echo htmlspecialchars($pkg['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($pkg['duration']); ?></td>
                        <td><?php echo htmlspecialchars($pkg['price']); ?></td>
                        <td>
                            <span class="text-muted" style="max-width:200px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?php echo htmlspecialchars($pkg['description'] ?? '-'); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1"
                                onclick="editPackage(
                                    <?php echo intval($pkg['id']); ?>,
                                    <?php echo json_encode($pkg['name']); ?>,
                                    <?php echo intval($pkg['duration']); ?>,
                                    <?php echo json_encode($pkg['description'] ?? ''); ?>,
                                    <?php echo floatval($pkg['price']); ?>
                                )">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="deletePackage(<?php echo intval($pkg['id']); ?>, <?php echo json_encode($pkg['name']); ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Package Modal -->
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createForm">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Package Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Gold Plan" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duration (months) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="duration" placeholder="e.g. 3" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (Rs) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="price" placeholder="e.g. 499" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Package features..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Package Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Package Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="editName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duration (months) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="duration" id="editDuration" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (Rs) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="price" id="editPrice" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    <?php if (!empty($packages)): ?>
    $('#packagesTable').DataTable({
        pageLength: 25,
        responsive: true,
        order: [[0, 'desc']],
        columnDefs: [{ orderable: false, targets: [5] }]
    });
    <?php endif; ?>
});

function editPackage(id, name, duration, description, price) {
    document.getElementById('editId').value          = id;
    document.getElementById('editName').value        = name;
    document.getElementById('editDuration').value    = duration;
    document.getElementById('editDescription').value = description;
    document.getElementById('editPrice').value       = price;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deletePackage(id, name) {
    Swal.fire({
        title: 'Delete Package?',
        text: 'Package "' + name + '" will be permanently deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
