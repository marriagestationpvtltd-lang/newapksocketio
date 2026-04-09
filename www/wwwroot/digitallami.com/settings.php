<?php
$title = 'Settings';
require_once __DIR__ . '/includes/user_header.php';

$userId = (int) $currentUser['user_id'];

/* ── Fetch blocked users ─────────────────────────────────────────── */
$blockedUsers = [];
$blockedError = '';

$apiUrl = 'https://digitallami.com/Api2/get_blocked_users.php?user_id=' . $userId;
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

if ($response !== false && $httpCode === 200) {
    $json = json_decode($response, true);
    if (!empty($json['success'])) {
        $blockedUsers = $json['data'] ?? [];
    } else {
        $blockedError = $json['message'] ?? '';
    }
}
?>

<!-- ── Page-specific CSS ──────────────────────────────────────────── -->
<style>
.ms-settings-wrap{max-width:720px;margin:0 auto;padding-top:20px;padding-bottom:40px}
.ms-settings-wrap .card{border:none;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,0.04)}
.ms-settings-wrap .card-header{background:var(--ms-white);border-bottom:1px solid var(--ms-border);font-weight:600;font-size:.95rem;padding:14px 20px;border-radius:12px 12px 0 0!important}
.ms-settings-wrap .card-header i{margin-right:8px;width:18px;text-align:center}
.ms-settings-wrap .card-body{padding:20px}
.ms-settings-wrap .card.border-danger .card-header{color:#dc3545}
.ms-blocked-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--ms-border)}
.ms-blocked-item:last-child{border-bottom:none}
.ms-blocked-avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;background:var(--ms-border);flex-shrink:0}
.ms-blocked-avatar-placeholder{width:40px;height:40px;border-radius:50%;background:var(--ms-border);display:flex;align-items:center;justify-content:center;font-weight:600;color:var(--ms-text-muted);font-size:.85rem;flex-shrink:0}
.ms-blocked-name{flex:1;font-size:.92rem;color:var(--ms-text)}
.ms-empty-block{text-align:center;padding:20px;color:var(--ms-text-muted);font-size:.9rem}
</style>

<div class="container ms-settings-wrap">

    <h4 class="mb-4" style="font-weight:700;color:var(--ms-text)">
        <i class="fas fa-cog" style="color:var(--ms-primary)"></i> Settings
    </h4>

    <!-- ════════════ Account ════════════ -->
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-user-circle" style="color:var(--ms-primary)"></i> Account</div>
        <div class="card-body">
            <!-- Email (read-only) -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" disabled>
            </div>

            <hr>

            <!-- Change Password -->
            <h6 class="fw-semibold mb-3">Change Password</h6>
            <form id="changePasswordForm" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="currentPassword" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password <small class="text-muted">(min 6 characters)</small></label>
                    <input type="password" class="form-control" id="newPassword" minlength="6" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirmPassword" required>
                </div>
                <button type="submit" class="btn btn-sm px-4" id="changePassBtn"
                        style="background:var(--ms-primary);color:#fff;border-radius:8px">
                    <i class="fas fa-key"></i> Update Password
                </button>
            </form>
        </div>
    </div>

    <!-- ════════════ Privacy ════════════ -->
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-shield-alt" style="color:var(--ms-primary)"></i> Privacy</div>
        <div class="card-body">
            <div class="form-check form-switch d-flex align-items-center justify-content-between">
                <div>
                    <label class="form-check-label fw-semibold" for="hidePhotoToggle">Hide Profile Photo</label>
                    <p class="text-muted mb-0" style="font-size:.82rem">Other users won't see your profile picture</p>
                </div>
                <input class="form-check-input" type="checkbox" role="switch" id="hidePhotoToggle" style="font-size:1.3rem">
            </div>
        </div>
    </div>

    <!-- ════════════ Notifications ════════════ -->
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-bell" style="color:var(--ms-primary)"></i> Notifications</div>
        <div class="card-body">
            <div class="form-check form-switch d-flex align-items-center justify-content-between">
                <div>
                    <label class="form-check-label fw-semibold" for="pushNotifToggle">Enable Push Notifications</label>
                    <p class="text-muted mb-0" style="font-size:.82rem">Receive alerts for new proposals, likes, and messages</p>
                </div>
                <input class="form-check-input" type="checkbox" role="switch" id="pushNotifToggle" style="font-size:1.3rem" checked>
            </div>
        </div>
    </div>

    <!-- ════════════ Blocked Users ════════════ -->
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-ban" style="color:var(--ms-primary)"></i> Blocked Users</div>
        <div class="card-body" id="blockedUsersBody">
            <?php if (!empty($blockedError)): ?>
                <div class="alert alert-warning mb-0"><?php echo htmlspecialchars($blockedError); ?></div>
            <?php elseif (empty($blockedUsers)): ?>
                <div class="ms-empty-block"><i class="fas fa-check-circle text-success"></i> No blocked users</div>
            <?php else: ?>
                <?php foreach ($blockedUsers as $bu):
                    $buId   = (int) ($bu['user_id'] ?? $bu['blocked_user_id'] ?? 0);
                    $buName = trim(($bu['firstName'] ?? '') . ' ' . ($bu['lastName'] ?? ''));
                    $buPic  = $bu['profile_picture'] ?? '';
                ?>
                <div class="ms-blocked-item" data-blocked-id="<?php echo $buId; ?>">
                    <?php if ($buPic): ?>
                        <img src="<?php echo htmlspecialchars($buPic); ?>" alt="" class="ms-blocked-avatar">
                    <?php else: ?>
                        <span class="ms-blocked-avatar-placeholder">
                            <?php echo htmlspecialchars(mb_strtoupper(mb_substr($buName ?: 'U', 0, 1))); ?>
                        </span>
                    <?php endif; ?>
                    <span class="ms-blocked-name"><?php echo htmlspecialchars($buName ?: 'User'); ?></span>
                    <button class="btn btn-sm btn-outline-secondary unblock-btn" data-id="<?php echo $buId; ?>"
                            style="border-radius:8px;font-size:.82rem">
                        <i class="fas fa-unlock"></i> Unblock
                    </button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ════════════ Packages ════════════ -->
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-crown" style="color:var(--ms-primary)"></i> Packages</div>
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span style="font-size:.92rem;color:var(--ms-text)">Manage your subscription</span>
            <a href="packages-user.php" class="btn btn-sm px-4"
               style="background:var(--ms-primary);color:#fff;border-radius:8px">
                <i class="fas fa-box-open"></i> View Packages
            </a>
        </div>
    </div>

    <!-- ════════════ Delete Account ════════════ -->
    <div class="card mb-4 border-danger">
        <div class="card-header"><i class="fas fa-exclamation-triangle"></i> Delete Account</div>
        <div class="card-body">
            <p style="font-size:.9rem;color:var(--ms-text)">
                <strong>Warning:</strong> Deleting your account is permanent. All your data, matches,
                and conversations will be removed and cannot be recovered.
            </p>
            <button class="btn btn-danger btn-sm px-4" id="deleteAccountBtn" style="border-radius:8px">
                <i class="fas fa-trash-alt"></i> Delete My Account
            </button>
        </div>
    </div>

</div>

<!-- ── JavaScript ─────────────────────────────────────────────────── -->
<script>
(function () {
    var userId = <?php echo $userId; ?>;

    /* ── Change Password ───────────────────────────────────────────── */
    var passForm = document.getElementById('changePasswordForm');
    passForm.addEventListener('submit', function (e) {
        e.preventDefault();

        var curPass  = document.getElementById('currentPassword').value.trim();
        var newPass  = document.getElementById('newPassword').value.trim();
        var confPass = document.getElementById('confirmPassword').value.trim();

        if (newPass.length < 6) {
            Swal.fire({ icon: 'warning', title: 'Too Short', text: 'New password must be at least 6 characters.' });
            return;
        }
        if (newPass !== confPass) {
            Swal.fire({ icon: 'warning', title: 'Mismatch', text: 'New password and confirmation do not match.' });
            return;
        }

        var btn = document.getElementById('changePassBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating…';

        fetch('/Api2/change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, current_password: curPass, new_password: newPass })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Password Updated', text: 'Your password has been changed successfully.', timer: 2000, showConfirmButton: false });
                passForm.reset();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not update password.' });
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-key"></i> Update Password';
        })
        .catch(function () {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-key"></i> Update Password';
        });
    });

    /* ── Hide Profile Photo Toggle ─────────────────────────────────── */
    var hideToggle = document.getElementById('hidePhotoToggle');
    hideToggle.addEventListener('change', function () {
        var privacyVal = hideToggle.checked ? 1 : 0;

        fetch('/Api3/privacy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, privacy: privacyVal })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Updated', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not update privacy setting.' });
                hideToggle.checked = !hideToggle.checked;
            }
        })
        .catch(function () {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            hideToggle.checked = !hideToggle.checked;
        });
    });

    /* ── Push Notifications Toggle ─────────────────────────────────── */
    var pushToggle = document.getElementById('pushNotifToggle');
    pushToggle.addEventListener('change', function () {
        var enabled = pushToggle.checked ? 1 : 0;

        fetch('/Api2/update_notification_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, push_enabled: enabled })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Updated', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not update notification setting.' });
                pushToggle.checked = !pushToggle.checked;
            }
        })
        .catch(function () {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            pushToggle.checked = !pushToggle.checked;
        });
    });

    /* ── Unblock User ──────────────────────────────────────────────── */
    document.querySelectorAll('.unblock-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var blockedId = parseInt(btn.getAttribute('data-id'));
            var item = btn.closest('.ms-blocked-item');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('/Api2/unblock_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, blocked_user_id: blockedId })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    item.remove();
                    Swal.fire({ icon: 'success', title: 'Unblocked', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
                    if (!document.querySelector('.ms-blocked-item')) {
                        document.getElementById('blockedUsersBody').innerHTML =
                            '<div class="ms-empty-block"><i class="fas fa-check-circle text-success"></i> No blocked users</div>';
                    }
                } else {
                    Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not unblock user.' });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-unlock"></i> Unblock';
                }
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-unlock"></i> Unblock';
            });
        });
    });

    /* ── Delete Account ────────────────────────────────────────────── */
    document.getElementById('deleteAccountBtn').addEventListener('click', function () {
        Swal.fire({
            icon: 'warning',
            title: 'Delete Account?',
            html: 'This action is <strong>permanent</strong> and cannot be undone.<br>Enter your password to confirm.',
            input: 'password',
            inputPlaceholder: 'Enter your password',
            inputAttributes: { autocomplete: 'current-password' },
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: '<i class="fas fa-trash-alt"></i> Delete Permanently',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            preConfirm: function (password) {
                if (!password || password.length < 1) {
                    Swal.showValidationMessage('Please enter your password');
                    return false;
                }
                return fetch('/Api2/delete_account.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, password: password })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success) {
                        throw new Error(data.message || 'Could not delete account.');
                    }
                    return data;
                })
                .catch(function (err) {
                    Swal.showValidationMessage(err.message || 'Network error. Please try again.');
                });
            },
            allowOutsideClick: function () { return !Swal.isLoading(); }
        }).then(function (result) {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Account Deleted',
                    text: 'Your account has been permanently deleted.',
                    timer: 2500,
                    showConfirmButton: false
                }).then(function () {
                    window.location.href = 'login.php';
                });
            }
        });
    });

})();
</script>

<?php require_once __DIR__ . '/includes/user_footer.php'; ?>
