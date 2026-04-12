<?php
/**
 * proposals.php – Sent & Received Proposals
 */
$title = 'Proposals';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/user_header.php';

$userId = (int) $currentUser['user_id'];

// --- Helper: build profile image URL ---
function msProposalImg(?string $pic): string {
    if (empty($pic)) return '';
    if (!preg_match('/^https?:\/\//', $pic)) return APP_API2_BASE_URL . $pic;
    return $pic;
}

// --- Fetch Received Proposals ---
$receivedProposals = [];
$receivedError     = '';

$ch = curl_init(APP_API2_BASE_URL . 'proposals_api.php?action=received&user_id=' . urlencode($userId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $httpCode !== 200) {
    $receivedError = 'Unable to load received proposals. Please try again later.';
} else {
    $json = json_decode($resp, true);
    if (!empty($json['success'])) {
        $receivedProposals = $json['data'] ?? [];
    } else {
        $receivedError = $json['message'] ?? 'Failed to load received proposals.';
    }
}

// --- Fetch Sent Proposals ---
$sentProposals = [];
$sentError     = '';

$ch = curl_init(APP_API2_BASE_URL . 'proposals_api.php?action=sent&user_id=' . urlencode($userId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $httpCode !== 200) {
    $sentError = 'Unable to load sent proposals. Please try again later.';
} else {
    $json = json_decode($resp, true);
    if (!empty($json['success'])) {
        $sentProposals = $json['data'] ?? [];
    } else {
        $sentError = $json['message'] ?? 'Failed to load sent proposals.';
    }
}
?>

<style>
/* ---------- Tabs ---------- */
.ms-tabs .nav-tabs {
    border-bottom: 2px solid var(--ms-border);
    margin-bottom: 24px;
}
.ms-tabs .nav-tabs .nav-link {
    color: var(--ms-text-muted);
    font-weight: 600;
    font-size: 0.95rem;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 10px 20px;
    margin-bottom: -2px;
    transition: color 0.2s, border-color 0.2s;
}
.ms-tabs .nav-tabs .nav-link:hover {
    color: var(--ms-primary);
    border-bottom-color: var(--ms-primary-light);
}
.ms-tabs .nav-tabs .nav-link.active {
    color: var(--ms-primary);
    border-bottom: 2px solid var(--ms-primary);
    background: transparent;
}

/* ---------- Proposal Card ---------- */
.ms-proposal-card {
    background: var(--ms-white);
    border-radius: 12px;
    box-shadow: var(--ms-shadow);
    padding: 18px 20px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: box-shadow 0.2s ease;
}
.ms-proposal-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
}

/* Avatar */
.ms-proposal-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.ms-proposal-avatar-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--ms-primary-light);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.3rem;
    flex-shrink: 0;
}

/* Info */
.ms-proposal-info {
    flex: 1;
    min-width: 0;
}
.ms-proposal-name {
    font-weight: 700;
    font-size: 1rem;
    color: var(--ms-text);
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}
.ms-proposal-name:hover {
    color: var(--ms-primary);
}
.ms-proposal-meta {
    font-size: 0.84rem;
    color: var(--ms-text-muted);
    margin-top: 2px;
}
.ms-proposal-meta i {
    margin-right: 3px;
}
.ms-proposal-date {
    font-size: 0.78rem;
    color: var(--ms-text-muted);
    margin-top: 4px;
}

/* Actions */
.ms-proposal-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
    align-items: center;
}
.ms-proposal-actions .btn {
    font-size: 0.82rem;
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 600;
}

/* Status badge */
.ms-status-badge {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
}

/* Empty state */
.ms-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--ms-text-muted);
}
.ms-empty i {
    font-size: 3rem;
    margin-bottom: 12px;
    display: block;
    color: #ddd;
}
.ms-empty p {
    font-size: 1rem;
}

/* Responsive: stack on mobile */
@media (max-width: 575.98px) {
    .ms-proposal-card {
        flex-direction: column;
        text-align: center;
        padding: 20px 16px;
    }
    .ms-proposal-actions {
        width: 100%;
        justify-content: center;
    }
    .ms-proposal-name {
        white-space: normal;
    }
}
</style>

<!-- Page heading -->
<h4 class="mb-3" style="font-weight:700;">
    <i class="fas fa-paper-plane me-2" style="color:var(--ms-primary);"></i>Proposals
</h4>

<!-- Tabs -->
<div class="ms-tabs">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="received-tab" data-bs-toggle="tab"
                    data-bs-target="#receivedPane" type="button" role="tab"
                    aria-controls="receivedPane" aria-selected="true">
                <i class="fas fa-inbox me-1"></i> Received
                <?php if (count($receivedProposals)): ?>
                    <span class="badge bg-danger ms-1"><?php echo count($receivedProposals); ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sent-tab" data-bs-toggle="tab"
                    data-bs-target="#sentPane" type="button" role="tab"
                    aria-controls="sentPane" aria-selected="false">
                <i class="fas fa-share me-1"></i> Sent
                <?php if (count($sentProposals)): ?>
                    <span class="badge bg-secondary ms-1"><?php echo count($sentProposals); ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- ======== Received Tab ======== -->
        <div class="tab-pane fade show active" id="receivedPane" role="tabpanel" aria-labelledby="received-tab">
            <?php if ($receivedError): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <?php echo htmlspecialchars($receivedError); ?>
                </div>
            <?php elseif (empty($receivedProposals)): ?>
                <div class="ms-empty">
                    <i class="fas fa-inbox"></i>
                    <p>No proposals received yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($receivedProposals as $rp):
                    $rpImg    = msProposalImg($rp['profile_picture'] ?? '');
                    $rpName   = trim(($rp['firstName'] ?? '') . ' ' . ($rp['lastName'] ?? ''));
                    $rpAge    = isset($rp['age']) ? (int) $rp['age'] : '';
                    $rpCity   = $rp['city'] ?? '';
                    $rpDate   = !empty($rp['created_at']) ? date('M d, Y', strtotime($rp['created_at'])) : '';
                    $rpId     = (int) ($rp['proposal_id'] ?? $rp['id'] ?? 0);
                    $rpSender = (int) ($rp['sender_id'] ?? $rp['user_id'] ?? 0);
                    $rpStatus = strtolower($rp['status'] ?? 'pending');
                    $rpLetter = mb_strtoupper(mb_substr($rpName ?: 'U', 0, 1));
                ?>
                <div class="ms-proposal-card" id="proposal-<?php echo $rpId; ?>">
                    <!-- Avatar -->
                    <?php if ($rpImg): ?>
                        <img src="<?php echo htmlspecialchars($rpImg); ?>" alt="<?php echo htmlspecialchars($rpName); ?>"
                             class="ms-proposal-avatar" loading="lazy">
                    <?php else: ?>
                        <div class="ms-proposal-avatar-placeholder"><?php echo htmlspecialchars($rpLetter); ?></div>
                    <?php endif; ?>

                    <!-- Info -->
                    <div class="ms-proposal-info">
                        <a href="profile-view.php?id=<?php echo $rpSender; ?>" class="ms-proposal-name">
                            <?php echo htmlspecialchars($rpName ?: 'Not specified'); ?>
                        </a>
                        <div class="ms-proposal-meta">
                            <?php if ($rpAge): ?><i class="fas fa-birthday-cake"></i> <?php echo $rpAge; ?> yrs<?php endif; ?>
                            <?php if ($rpAge && $rpCity): ?> &middot; <?php endif; ?>
                            <?php if ($rpCity): ?><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($rpCity); ?><?php endif; ?>
                            <?php if (!$rpAge && !$rpCity): ?>Not specified<?php endif; ?>
                        </div>
                        <?php if ($rpDate): ?>
                            <div class="ms-proposal-date"><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($rpDate); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="ms-proposal-actions" id="actions-<?php echo $rpId; ?>">
                        <?php if ($rpStatus === 'pending'): ?>
                            <button class="btn btn-success btn-sm ms-accept-btn" data-proposal="<?php echo $rpId; ?>">
                                <i class="fas fa-check me-1"></i>Accept
                            </button>
                            <button class="btn btn-outline-danger btn-sm ms-reject-btn" data-proposal="<?php echo $rpId; ?>">
                                <i class="fas fa-times me-1"></i>Reject
                            </button>
                        <?php elseif ($rpStatus === 'accepted'): ?>
                            <span class="badge bg-success ms-status-badge"><i class="fas fa-check-circle me-1"></i>Accepted</span>
                        <?php elseif ($rpStatus === 'rejected'): ?>
                            <span class="badge bg-danger ms-status-badge"><i class="fas fa-times-circle me-1"></i>Rejected</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark ms-status-badge"><?php echo htmlspecialchars(ucfirst($rpStatus)); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ======== Sent Tab ======== -->
        <div class="tab-pane fade" id="sentPane" role="tabpanel" aria-labelledby="sent-tab">
            <?php if ($sentError): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <?php echo htmlspecialchars($sentError); ?>
                </div>
            <?php elseif (empty($sentProposals)): ?>
                <div class="ms-empty">
                    <i class="fas fa-paper-plane"></i>
                    <p>You haven't sent any proposals yet. <a href="search.php" style="color:var(--ms-primary);font-weight:600;">Browse profiles</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($sentProposals as $sp):
                    $spImg      = msProposalImg($sp['profile_picture'] ?? '');
                    $spName     = trim(($sp['firstName'] ?? '') . ' ' . ($sp['lastName'] ?? ''));
                    $spAge      = isset($sp['age']) ? (int) $sp['age'] : '';
                    $spCity     = $sp['city'] ?? '';
                    $spDate     = !empty($sp['created_at']) ? date('M d, Y', strtotime($sp['created_at'])) : '';
                    $spId       = (int) ($sp['proposal_id'] ?? $sp['id'] ?? 0);
                    $spReceiver = (int) ($sp['receiver_id'] ?? $sp['user_id'] ?? 0);
                    $spStatus   = strtolower($sp['status'] ?? 'pending');
                    $spLetter   = mb_strtoupper(mb_substr($spName ?: 'U', 0, 1));
                ?>
                <div class="ms-proposal-card">
                    <!-- Avatar -->
                    <?php if ($spImg): ?>
                        <img src="<?php echo htmlspecialchars($spImg); ?>" alt="<?php echo htmlspecialchars($spName); ?>"
                             class="ms-proposal-avatar" loading="lazy">
                    <?php else: ?>
                        <div class="ms-proposal-avatar-placeholder"><?php echo htmlspecialchars($spLetter); ?></div>
                    <?php endif; ?>

                    <!-- Info -->
                    <div class="ms-proposal-info">
                        <a href="profile-view.php?id=<?php echo $spReceiver; ?>" class="ms-proposal-name">
                            <?php echo htmlspecialchars($spName ?: 'Not specified'); ?>
                        </a>
                        <div class="ms-proposal-meta">
                            <?php if ($spAge): ?><i class="fas fa-birthday-cake"></i> <?php echo $spAge; ?> yrs<?php endif; ?>
                            <?php if ($spAge && $spCity): ?> &middot; <?php endif; ?>
                            <?php if ($spCity): ?><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($spCity); ?><?php endif; ?>
                            <?php if (!$spAge && !$spCity): ?>Not specified<?php endif; ?>
                        </div>
                        <?php if ($spDate): ?>
                            <div class="ms-proposal-date"><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($spDate); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Status badge -->
                    <div class="ms-proposal-actions">
                        <?php if ($spStatus === 'accepted'): ?>
                            <span class="badge bg-success ms-status-badge"><i class="fas fa-check-circle me-1"></i>Accepted</span>
                        <?php elseif ($spStatus === 'rejected'): ?>
                            <span class="badge bg-danger ms-status-badge"><i class="fas fa-times-circle me-1"></i>Rejected</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark ms-status-badge"><i class="fas fa-hourglass-half me-1"></i>Pending</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    var currentUserId = <?php echo $userId; ?>;

    // --- Accept Proposal ---
    document.querySelectorAll('.ms-accept-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var proposalId = this.getAttribute('data-proposal');
            var actionsDiv = document.getElementById('actions-' + proposalId);
            var button = this;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('/Api2/acceptProposal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proposal_id: proposalId, user_id: currentUserId })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    actionsDiv.innerHTML = '<span class="badge bg-success ms-status-badge"><i class="fas fa-check-circle me-1"></i>Accepted</span>';
                    Swal.fire({
                        icon: 'success',
                        title: 'Accepted!',
                        text: data.message || 'Proposal accepted successfully.',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not accept proposal.' });
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-check me-1"></i>Accept';
                }
            })
            .catch(function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-check me-1"></i>Accept';
            });
        });
    });

    // --- Reject Proposal ---
    document.querySelectorAll('.ms-reject-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var proposalId = this.getAttribute('data-proposal');
            var actionsDiv = document.getElementById('actions-' + proposalId);
            var button = this;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('/Api2/rejectProposal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ proposal_id: proposalId, user_id: currentUserId })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    actionsDiv.innerHTML = '<span class="badge bg-danger ms-status-badge"><i class="fas fa-times-circle me-1"></i>Rejected</span>';
                    Swal.fire({
                        icon: 'success',
                        title: 'Rejected',
                        text: data.message || 'Proposal rejected.',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({ icon: 'info', title: 'Note', text: data.message || 'Could not reject proposal.' });
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-times me-1"></i>Reject';
                }
            })
            .catch(function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-times me-1"></i>Reject';
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/user_footer.php'; ?>
