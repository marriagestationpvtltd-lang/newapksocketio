<?php
$title = 'Settings';
require_once 'includes/header.php';

$apiBase = 'https://digitallami.com';

$actionMsg  = '';
$actionType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_tone') {
        $toneId = $_POST['call_tone_id'] ?? '';
        $payload = json_encode(['call_tone_id' => $toneId]);

        $ch = curl_init("$apiBase/api9/update_app_settings.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($res, true);
        $actionMsg  = $result['message'] ?? 'Unknown error';
        $actionType = !empty($result['success']) ? 'success' : 'danger';

    } elseif ($action === 'upload_tone') {
        if (!empty($_FILES['tone_file']['tmp_name'])) {
            $ch = curl_init("$apiBase/api9/upload_call_tone.php");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'tone_file' => new CURLFile(
                    $_FILES['tone_file']['tmp_name'],
                    $_FILES['tone_file']['type'],
                    $_FILES['tone_file']['name']
                )
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($res, true);
            $actionMsg  = $result['message'] ?? 'Unknown error';
            $actionType = !empty($result['success']) ? 'success' : 'danger';
        } else {
            $actionMsg  = 'No file selected.';
            $actionType = 'warning';
        }

    } elseif ($action === 'clear_tone') {
        $payload = json_encode(['clear_custom_call_tone' => true]);
        $ch = curl_init("$apiBase/api9/update_app_settings.php");
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

// Fetch current settings
$settings = [];
$ch = curl_init("$apiBase/Api2/app_settings.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$res = curl_exec($ch);
curl_close($ch);
if ($res) {
    $decoded = json_decode($res, true);
    if (!empty($decoded['success'])) {
        $settings = $decoded['data'] ?? $decoded['settings'] ?? [];
    }
}

$currentTone         = $settings['call_tone_id']         ?? 'default';
$customToneUrl       = $settings['custom_call_tone_url'] ?? '';
$customToneName      = $settings['custom_call_tone_name'] ?? '';

$builtInTones = [
    'default' => 'Default',
    'classic' => 'Classic',
    'soft'    => 'Soft',
    'modern'  => 'Modern',
];
?>

<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold">App Settings</h4>
    </div>
</div>

<?php if ($actionMsg): ?>
    <div class="alert alert-<?php echo htmlspecialchars($actionType); ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $actionType === 'success' ? 'check-circle' : ($actionType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?> me-2"></i>
        <?php echo htmlspecialchars($actionMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Call Tone Settings -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title"><i class="fas fa-music me-2"></i>Call Ringtone Settings</h5>
    </div>
    <div class="card-body">

        <!-- Built-in Tone Selection -->
        <form method="POST" class="mb-4">
            <input type="hidden" name="action" value="update_tone">
            <div class="mb-3">
                <label class="form-label fw-semibold">Select Built-in Ringtone</label>
                <div class="row g-3">
                    <?php foreach ($builtInTones as $toneId => $toneName): ?>
                    <div class="col-md-3">
                        <div class="form-check border rounded p-3 <?php echo $currentTone === $toneId ? 'border-primary bg-primary bg-opacity-10' : ''; ?>">
                            <input class="form-check-input" type="radio" name="call_tone_id"
                                   id="tone_<?php echo $toneId; ?>"
                                   value="<?php echo $toneId; ?>"
                                   <?php echo $currentTone === $toneId ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold" for="tone_<?php echo $toneId; ?>">
                                <i class="fas fa-bell me-2"></i><?php echo $toneName; ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Save Ringtone
            </button>
        </form>

        <hr>

        <!-- Custom Tone Upload -->
        <div class="mb-3">
            <label class="form-label fw-semibold">Custom Ringtone Upload</label>
            <?php if (!empty($customToneUrl)): ?>
                <div class="alert alert-info d-flex align-items-center justify-content-between">
                    <div>
                        <i class="fas fa-music me-2"></i>
                        Current custom tone: <strong><?php echo htmlspecialchars($customToneName ?: 'custom_tone'); ?></strong>
                        <audio controls class="ms-3" style="height:32px;">
                            <source src="<?php echo htmlspecialchars($apiBase . '/' . ltrim($customToneUrl, '/')); ?>">
                        </audio>
                    </div>
                    <form method="POST" class="ms-3">
                        <input type="hidden" name="action" value="clear_tone">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Remove custom ringtone?')">
                            <i class="fas fa-trash me-1"></i> Remove
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <p class="text-muted small">No custom ringtone uploaded.</p>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_tone">
                <div class="input-group" style="max-width:400px;">
                    <input type="file" class="form-control" name="tone_file" accept="audio/*" required>
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-upload me-1"></i> Upload
                    </button>
                </div>
                <small class="text-muted">Accepted formats: MP3, AAC, WAV, OGG</small>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
