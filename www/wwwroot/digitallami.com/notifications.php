<?php
$title = 'Notifications';
require_once __DIR__ . '/includes/user_header.php';

$userId = (int) $currentUser['user_id'];

/* ── Fetch notifications ─────────────────────────────────────────── */
$notifications = [];
$apiError      = '';

$apiUrl = 'https://digitallami.com/Api2/get_notifications.php?user_id=' . $userId;
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
    $apiError = 'Unable to load notifications. Please try again later.';
} else {
    $json = json_decode($response, true);
    if (!empty($json['success'])) {
        $notifications = $json['data'] ?? [];
    } else {
        $apiError = $json['message'] ?? 'Failed to load notifications.';
    }
}

/* ── Helper: notification icon by type ───────────────────────────── */
function msNotifIcon(string $type): array
{
    $map = [
        'proposal_received' => ['fa-paper-plane', '#F90E18', 'rgba(249,14,24,0.12)'],
        'proposal_accepted' => ['fa-check-circle', '#27ae60', 'rgba(39,174,96,0.12)'],
        'proposal_rejected' => ['fa-times-circle', '#e74c3c', 'rgba(231,76,60,0.12)'],
        'like_received'     => ['fa-heart',        '#e84393', 'rgba(232,67,147,0.12)'],
        'message'           => ['fa-comment',      '#0984e3', 'rgba(9,132,227,0.12)'],
        'match'             => ['fa-handshake',    '#6c5ce7', 'rgba(108,92,231,0.12)'],
    ];
    return $map[$type] ?? ['fa-bell', '#636e72', 'rgba(99,110,114,0.12)'];
}

/* ── Helper: time ago ────────────────────────────────────────────── */
function msTimeAgo(string $datetime): string
{
    $ts   = strtotime($datetime);
    $diff = time() - $ts;

    if ($diff < 60)   return 'Just now';
    if ($diff < 3600)  return (int) ($diff / 60) . ' minutes ago';
    if ($diff < 86400) return (int) ($diff / 3600) . ' hours ago';
    return date('M j, Y', $ts);
}
?>

<!-- ── Page-specific CSS ──────────────────────────────────────────── -->
<style>
.ms-notif-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px}
.ms-notif-header h4{margin:0;font-weight:700;color:var(--ms-text)}
.ms-notif-item{display:flex;align-items:flex-start;gap:14px;background:var(--ms-white);border-radius:12px;padding:16px;margin-bottom:10px;box-shadow:0 1px 4px rgba(0,0,0,0.04);transition:opacity .3s}
.ms-notif-item.unread{border-left:3px solid var(--ms-primary);background:#fff5f5}
.ms-notif-icon{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.ms-notif-content{flex:1}
.ms-notif-msg{font-size:.92rem;color:var(--ms-text);margin-bottom:4px}
.ms-notif-time{font-size:.78rem;color:var(--ms-text-muted)}
.ms-notif-delete{background:none;border:none;color:var(--ms-text-muted);cursor:pointer;padding:4px 8px;font-size:.85rem;border-radius:6px;transition:color .2s}
.ms-notif-delete:hover{color:var(--ms-primary)}
.ms-notif-empty{text-align:center;padding:60px 20px;color:var(--ms-text-muted)}
.ms-notif-empty i{font-size:3rem;margin-bottom:12px;display:block;opacity:.5}
.ms-mark-read-btn{background:var(--ms-primary);color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:.85rem;cursor:pointer;transition:background .2s}
.ms-mark-read-btn:hover{background:var(--ms-primary-dark)}
</style>

<div class="container" style="max-width:720px;padding-top:20px;padding-bottom:40px">

    <!-- Header -->
    <div class="ms-notif-header">
        <h4><i class="fas fa-bell" style="color:var(--ms-primary)"></i> Notifications</h4>
        <?php if (!empty($notifications)): ?>
            <button class="ms-mark-read-btn" id="markAllRead">
                <i class="fas fa-check-double"></i> Mark all as read
            </button>
        <?php endif; ?>
    </div>

    <?php if ($apiError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($apiError); ?></div>
    <?php elseif (empty($notifications)): ?>
        <!-- Empty state -->
        <div class="ms-notif-empty">
            <i class="fas fa-bell-slash"></i>
            <p style="font-size:1.05rem;font-weight:600">No notifications yet</p>
            <p style="font-size:.88rem">When you receive proposals, likes, or messages you'll see them here.</p>
        </div>
    <?php else: ?>
        <div id="notifList">
            <?php foreach ($notifications as $n):
                $nId      = (int) ($n['notification_id'] ?? $n['id'] ?? 0);
                $type     = $n['type'] ?? '';
                $msg      = $n['message'] ?? '';
                $time     = $n['created_at'] ?? $n['time'] ?? '';
                $isUnread = empty($n['is_read']) || $n['is_read'] == '0';
                [$icon, $iconColor, $iconBg] = msNotifIcon($type);
            ?>
            <div class="ms-notif-item<?php echo $isUnread ? ' unread' : ''; ?>"
                 data-id="<?php echo $nId; ?>">
                <div class="ms-notif-icon"
                     style="background:<?php echo $iconBg; ?>;color:<?php echo $iconColor; ?>">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="ms-notif-content">
                    <div class="ms-notif-msg"><?php echo htmlspecialchars($msg); ?></div>
                    <?php if ($time): ?>
                        <div class="ms-notif-time"><?php echo htmlspecialchars(msTimeAgo($time)); ?></div>
                    <?php endif; ?>
                </div>
                <button class="ms-notif-delete" title="Delete" data-id="<?php echo $nId; ?>">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ── JavaScript ─────────────────────────────────────────────────── -->
<script>
(function () {
    var userId = <?php echo $userId; ?>;

    /* Mark all as read */
    var markBtn = document.getElementById('markAllRead');
    if (markBtn) {
        markBtn.addEventListener('click', function () {
            markBtn.disabled = true;
            markBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating…';

            fetch('/Api2/mark_as_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    document.querySelectorAll('.ms-notif-item.unread').forEach(function (el) {
                        el.classList.remove('unread');
                    });
                    Swal.fire({ icon: 'success', title: 'Done', text: 'All notifications marked as read.', timer: 1800, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not update notifications.' });
                }
                markBtn.disabled = false;
                markBtn.innerHTML = '<i class="fas fa-check-double"></i> Mark all as read';
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                markBtn.disabled = false;
                markBtn.innerHTML = '<i class="fas fa-check-double"></i> Mark all as read';
            });
        });
    }

    /* Delete single notification */
    document.querySelectorAll('.ms-notif-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var nId  = parseInt(btn.getAttribute('data-id'));
            var item = btn.closest('.ms-notif-item');

            fetch('/Api2/delete_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: nId, user_id: userId })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    item.style.opacity = '0';
                    setTimeout(function () {
                        item.remove();
                        if (!document.querySelector('.ms-notif-item')) {
                            document.getElementById('notifList').innerHTML =
                                '<div class="ms-notif-empty">' +
                                '<i class="fas fa-bell-slash"></i>' +
                                '<p style="font-size:1.05rem;font-weight:600">No notifications yet</p>' +
                                '</div>';
                            var mb = document.getElementById('markAllRead');
                            if (mb) mb.style.display = 'none';
                        }
                    }, 300);
                } else {
                    Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not delete notification.' });
                }
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/user_footer.php'; ?>
