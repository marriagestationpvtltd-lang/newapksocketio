<?php
/**
 * packages-user.php – View & purchase packages
 */
$title = 'Packages';
require_once __DIR__ . '/includes/user_header.php';

$userId = (int) $currentUser['user_id'];

// Fetch available packages
$pkgUrl = 'https://digitallami.com/Api2/packagelist.php';
$packages = [];
$pkgError = '';

$ch = curl_init($pkgUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp !== false && $code === 200) {
    $json = json_decode($resp, true);
    if (!empty($json['success'])) {
        $packages = $json['data'] ?? [];
    } else {
        $pkgError = $json['message'] ?? 'Failed to load packages.';
    }
} else {
    $pkgError = 'Unable to load packages. Please try again later.';
}

// Fetch current user package
$upUrl = 'https://digitallami.com/Api2/user_package.php?user_id=' . urlencode($userId);
$userPkg = null;

$ch2 = curl_init($upUrl);
curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false]);
$resp2 = curl_exec($ch2);
$code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($resp2 !== false && $code2 === 200) {
    $json2 = json_decode($resp2, true);
    if (!empty($json2['success']) && !empty($json2['data'])) {
        $userPkg = $json2['data'];
    }
}

function pkgVal($v, string $d = 'N/A'): string {
    return (!empty($v) && $v !== 'null') ? htmlspecialchars((string)$v) : $d;
}
?>

<style>
.ms-pkg-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.ms-pkg-header h2 { font-weight: 700; font-size: 1.5rem; margin: 0; }
.ms-current-pkg {
    background: linear-gradient(135deg, var(--ms-primary) 0%, var(--ms-primary-dark) 100%);
    color: #fff; border-radius: 14px; padding: 24px; margin-bottom: 28px;
}
.ms-current-pkg h5 { font-weight: 700; margin-bottom: 12px; }
.ms-current-pkg .ms-pkg-meta { display: flex; gap: 24px; flex-wrap: wrap; font-size: 0.9rem; }
.ms-current-pkg .ms-pkg-meta span { opacity: 0.9; }
.ms-current-pkg .ms-pkg-meta strong { display: block; font-size: 1rem; opacity: 1; }
.ms-pkg-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
@media (max-width: 991.98px) { .ms-pkg-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575.98px) { .ms-pkg-grid { grid-template-columns: 1fr; } }
.ms-pkg-card {
    background: var(--ms-white); border-radius: 14px; box-shadow: var(--ms-shadow);
    overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s;
}
.ms-pkg-card:hover { transform: translateY(-4px); box-shadow: 0 8px 28px rgba(0,0,0,0.12); }
.ms-pkg-card-head { background: linear-gradient(135deg, var(--ms-primary) 0%, var(--ms-primary-dark) 100%); color: #fff; padding: 20px; text-align: center; }
.ms-pkg-card-head h4 { font-weight: 700; margin: 0 0 4px; font-size: 1.15rem; }
.ms-pkg-card-head .ms-pkg-price { font-size: 1.8rem; font-weight: 800; }
.ms-pkg-card-head .ms-pkg-duration { font-size: 0.85rem; opacity: 0.85; }
.ms-pkg-card-body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
.ms-pkg-features { list-style: none; padding: 0; margin: 0 0 16px; flex: 1; }
.ms-pkg-features li { padding: 6px 0; font-size: 0.88rem; color: var(--ms-text); border-bottom: 1px solid var(--ms-border); display: flex; align-items: center; gap: 8px; }
.ms-pkg-features li:last-child { border-bottom: none; }
.ms-pkg-features li i { color: #28a745; font-size: 0.8rem; }
.ms-pkg-buy {
    background: var(--ms-primary); color: #fff; border: none; width: 100%;
    padding: 10px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: background 0.2s;
}
.ms-pkg-buy:hover { background: var(--ms-primary-dark); }
.ms-empty { text-align: center; padding: 60px 20px; color: var(--ms-text-muted); }
.ms-empty i { font-size: 3rem; margin-bottom: 12px; display: block; color: #ddd; }
</style>

<div class="ms-pkg-header">
    <h2><i class="fas fa-crown me-2" style="color:var(--ms-primary);"></i>Packages</h2>
</div>

<?php if ($userPkg): ?>
<div class="ms-current-pkg">
    <h5><i class="fas fa-check-circle me-2"></i>Your Current Package</h5>
    <div class="ms-pkg-meta">
        <div><span>Package</span><strong><?php echo pkgVal($userPkg['package_name'] ?? $userPkg['name'] ?? null); ?></strong></div>
        <div><span>Purchased</span><strong><?php echo pkgVal($userPkg['purchase_date'] ?? $userPkg['created_at'] ?? null); ?></strong></div>
        <div><span>Expires</span><strong><?php echo pkgVal($userPkg['expiry_date'] ?? $userPkg['expires_at'] ?? null); ?></strong></div>
        <div><span>Status</span><strong><?php echo pkgVal($userPkg['status'] ?? null, 'Active'); ?></strong></div>
    </div>
</div>
<?php endif; ?>

<?php if ($pkgError): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-1"></i> <?php echo htmlspecialchars($pkgError); ?></div>
<?php endif; ?>

<?php if (empty($packages) && !$pkgError): ?>
    <div class="ms-empty"><i class="fas fa-box-open"></i><p>No packages available at the moment.</p></div>
<?php elseif (!empty($packages)): ?>
<div class="ms-pkg-grid">
    <?php foreach ($packages as $pkg): ?>
        <?php
        $pkgId   = (int) ($pkg['id'] ?? 0);
        $name    = pkgVal($pkg['name'] ?? $pkg['package_name'] ?? null, 'Package');
        $price   = pkgVal($pkg['price'] ?? null, '0');
        $dur     = pkgVal($pkg['duration'] ?? $pkg['validity'] ?? null, '');
        $desc    = $pkg['description'] ?? '';
        $features = [];
        if (!empty($pkg['features'])) {
            $features = is_array($pkg['features']) ? $pkg['features'] : explode(',', $pkg['features']);
        }
        ?>
        <div class="ms-pkg-card">
            <div class="ms-pkg-card-head">
                <h4><?php echo $name; ?></h4>
                <div class="ms-pkg-price">₹<?php echo $price; ?></div>
                <?php if ($dur): ?><div class="ms-pkg-duration"><?php echo $dur; ?></div><?php endif; ?>
            </div>
            <div class="ms-pkg-card-body">
                <?php if ($desc): ?><p style="font-size:0.88rem;color:var(--ms-text-muted);margin-bottom:12px;"><?php echo htmlspecialchars($desc); ?></p><?php endif; ?>
                <?php if (!empty($features)): ?>
                <ul class="ms-pkg-features">
                    <?php foreach ($features as $f): ?>
                        <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars(trim($f)); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <button class="ms-pkg-buy" onclick="buyPackage(<?php echo $pkgId; ?>, '<?php echo $name; ?>')">
                    <i class="fas fa-shopping-cart me-1"></i> Buy Now
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function buyPackage(pkgId, pkgName) {
    Swal.fire({
        title: 'Purchase ' + pkgName + '?',
        text: 'Do you want to purchase this package?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#F90E18',
        confirmButtonText: 'Yes, Buy Now'
    }).then(function(result) {
        if (result.isConfirmed) {
            fetch('/Api3/purchase_package.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: <?php echo $userId; ?>, package_id: pkgId })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Purchased!', text: data.message || 'Package purchased successfully.', timer: 2000, showConfirmButton: false })
                    .then(function() { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not complete purchase.' });
                }
            })
            .catch(function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            });
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/user_footer.php'; ?>
