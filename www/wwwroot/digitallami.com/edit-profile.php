<?php
/**
 * edit-profile.php – Multi-section profile editor
 */
$title = 'Edit Profile';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/user_header.php';

$userId  = (int) $currentUser['user_id'];
$section = $_GET['section'] ?? 'personal';
$allowed = ['personal', 'education', 'family', 'lifestyle', 'partner'];
if (!in_array($section, $allowed)) $section = 'personal';

// Fetch current profile data
$apiUrl = APP_API2_BASE_URL . 'myprofile.php?userid=' . urlencode($userId);
$profile  = [];
$apiError = '';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp !== false && $code === 200) {
    $json = json_decode($resp, true);
    if (!empty($json['success'])) {
        $profile = $json['data'] ?? [];
    } else {
        $apiError = $json['message'] ?? 'Failed to load profile.';
    }
} else {
    $apiError = 'Unable to load profile data.';
}

function ev($key, $profile): string {
    $v = $profile[$key] ?? '';
    return htmlspecialchars((string)$v);
}

// Section API endpoints
$endpoints = [
    'personal'  => APP_API2_BASE_URL . 'save_personal_detail.php',
    'education' => APP_API2_BASE_URL . 'educationcareer.php',
    'family'    => APP_API2_BASE_URL . 'updatefamily.php',
    'lifestyle' => APP_API2_BASE_URL . 'user_lifestyle.php',
    'partner'   => APP_API2_BASE_URL . 'user_partner.php',
];

$sectionLabels = [
    'personal'  => ['icon' => 'fa-user',           'label' => 'Personal Details'],
    'education' => ['icon' => 'fa-graduation-cap',  'label' => 'Education & Career'],
    'family'    => ['icon' => 'fa-users',           'label' => 'Family Details'],
    'lifestyle' => ['icon' => 'fa-utensils',        'label' => 'Lifestyle'],
    'partner'   => ['icon' => 'fa-heart',           'label' => 'Partner Preferences'],
];
?>

<style>
.ms-edit-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; }
.ms-edit-tab {
    padding: 8px 16px; border-radius: 8px; font-size: 0.88rem; font-weight: 500;
    text-decoration: none; color: var(--ms-text-muted); background: var(--ms-white);
    border: 1px solid var(--ms-border); transition: all 0.2s;
}
.ms-edit-tab:hover { color: var(--ms-primary); border-color: var(--ms-primary); }
.ms-edit-tab.active { background: var(--ms-primary); color: #fff; border-color: var(--ms-primary); }
.ms-edit-card {
    background: var(--ms-white); border-radius: 14px; box-shadow: var(--ms-shadow); padding: 28px; margin-bottom: 24px;
}
.ms-edit-card h4 { font-weight: 700; font-size: 1.15rem; margin-bottom: 20px; color: var(--ms-text); }
.ms-edit-card h4 i { color: var(--ms-primary); margin-right: 8px; }
.ms-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 575.98px) { .ms-form-row { grid-template-columns: 1fr; } }
.ms-form-group { margin-bottom: 16px; }
.ms-form-group label { font-size: 0.85rem; font-weight: 600; color: var(--ms-text); margin-bottom: 4px; display: block; }
.ms-form-group input, .ms-form-group select, .ms-form-group textarea {
    width: 100%; padding: 10px 14px; border: 1px solid var(--ms-border); border-radius: 8px;
    font-size: 0.9rem; transition: border-color 0.2s; background: #fff;
}
.ms-form-group input:focus, .ms-form-group select:focus, .ms-form-group textarea:focus {
    border-color: var(--ms-primary); outline: none; box-shadow: 0 0 0 3px rgba(249,14,24,0.1);
}
.ms-btn-save {
    background: var(--ms-primary); color: #fff; border: none; padding: 12px 32px;
    border-radius: 10px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: background 0.2s;
}
.ms-btn-save:hover { background: var(--ms-primary-dark); }
.ms-btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
.ms-btn-back { color: var(--ms-text-muted); text-decoration: none; font-size: 0.9rem; }
.ms-btn-back:hover { color: var(--ms-primary); }
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 style="font-weight:700;font-size:1.4rem;margin:0;"><i class="fas fa-edit me-2" style="color:var(--ms-primary);"></i>Edit Profile</h2>
    <a href="profile.php" class="ms-btn-back"><i class="fas fa-arrow-left me-1"></i> Back to Profile</a>
</div>

<?php if ($apiError): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-1"></i> <?php echo htmlspecialchars($apiError); ?></div>
<?php endif; ?>

<!-- Section Tabs -->
<div class="ms-edit-tabs">
    <?php foreach ($sectionLabels as $key => $info): ?>
        <a href="edit-profile.php?section=<?php echo $key; ?>" class="ms-edit-tab <?php echo $section === $key ? 'active' : ''; ?>">
            <i class="fas <?php echo $info['icon']; ?> me-1"></i> <?php echo $info['label']; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="ms-edit-card">
    <h4><i class="fas <?php echo $sectionLabels[$section]['icon']; ?>"></i> <?php echo $sectionLabels[$section]['label']; ?></h4>

    <form id="editForm" onsubmit="return submitForm(event)">
        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
        <?php if (!empty($profile['id'])): ?>
            <input type="hidden" name="id" value="<?php echo (int)$profile['id']; ?>">
        <?php endif; ?>

        <?php if ($section === 'personal'): ?>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>First Name</label>
                <input type="text" name="firstName" value="<?php echo ev('firstName', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Last Name</label>
                <input type="text" name="lastName" value="<?php echo ev('lastName', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-group">
            <label>About Me</label>
            <textarea name="about" rows="3"><?php echo ev('about', $profile); ?></textarea>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Height</label>
                <input type="text" name="height" value="<?php echo ev('height', $profile); ?>" placeholder="e.g. 5'8&quot;">
            </div>
            <div class="ms-form-group">
                <label>Weight</label>
                <input type="text" name="weight" value="<?php echo ev('weight', $profile); ?>" placeholder="e.g. 70 kg">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Complexion</label>
                <select name="complexion">
                    <option value="">Select</option>
                    <?php foreach (['Fair','Very Fair','Dark','Wheatish','Wheatish Brown'] as $o): ?>
                        <option value="<?php echo $o; ?>" <?php echo (($profile['complexion'] ?? '') === $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ms-form-group">
                <label>Marital Status</label>
                <select name="maritalstatus">
                    <option value="">Select</option>
                    <?php foreach (['Never Married','Divorced','Widowed','Separated','Annulled'] as $o): ?>
                        <option value="<?php echo $o; ?>" <?php echo (($profile['maritalstatus'] ?? '') === $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Religion</label>
                <input type="text" name="religion" value="<?php echo ev('religion', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Community / Caste</label>
                <input type="text" name="community" value="<?php echo ev('community', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Sub Caste</label>
                <input type="text" name="subcaste" value="<?php echo ev('subcaste', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Mother Tongue</label>
                <input type="text" name="mothertongue" value="<?php echo ev('mothertongue', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Blood Group</label>
                <select name="bloodgroup">
                    <option value="">Select</option>
                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $o): ?>
                        <option value="<?php echo $o; ?>" <?php echo (($profile['bloodgroup'] ?? '') === $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ms-form-group">
                <label>Disability</label>
                <select name="disability">
                    <option value="">Select</option>
                    <option value="None" <?php echo (($profile['disability'] ?? '') === 'None') ? 'selected' : ''; ?>>None</option>
                    <option value="Physical" <?php echo (($profile['disability'] ?? '') === 'Physical') ? 'selected' : ''; ?>>Physical</option>
                    <option value="Other" <?php echo (($profile['disability'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Gotra</label>
                <input type="text" name="gotra" value="<?php echo ev('gotra', $profile); ?>">
            </div>
        </div>

        <?php elseif ($section === 'education'): ?>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Education Medium</label>
                <input type="text" name="educationmedium" value="<?php echo ev('educationmedium', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Education Type</label>
                <input type="text" name="educationtype" value="<?php echo ev('educationtype', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Faculty</label>
                <input type="text" name="educationfaculty" value="<?php echo ev('educationfaculty', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Degree</label>
                <input type="text" name="educationdegree" value="<?php echo ev('educationdegree', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Working Status</label>
                <select name="workingstatus">
                    <option value="">Select</option>
                    <?php foreach (['Employed','Self-Employed','Not Working','Student','Retired'] as $o): ?>
                        <option value="<?php echo $o; ?>" <?php echo (($profile['workingstatus'] ?? '') === $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ms-form-group">
                <label>Occupation</label>
                <input type="text" name="occupation" value="<?php echo ev('occupation', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Company</label>
                <input type="text" name="company" value="<?php echo ev('company', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Designation</label>
                <input type="text" name="designation" value="<?php echo ev('designation', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-group">
            <label>Annual Income</label>
            <input type="text" name="annualincome" value="<?php echo ev('annualincome', $profile); ?>">
        </div>

        <?php elseif ($section === 'family'): ?>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Family Type</label>
                <select name="familytype">
                    <option value="">Select</option>
                    <?php foreach (['Joint','Nuclear','Other'] as $o): ?>
                        <option value="<?php echo $o; ?>" <?php echo (($profile['familytype'] ?? '') === $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ms-form-group">
                <label>Family Status</label>
                <select name="familystatus">
                    <option value="">Select</option>
                    <?php foreach (['Middle Class','Upper Middle Class','Rich','Affluent'] as $o): ?>
                        <option value="<?php echo $o; ?>" <?php echo (($profile['familystatus'] ?? '') === $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Father Status</label>
                <input type="text" name="fatherstatus" value="<?php echo ev('fatherstatus', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Father Details</label>
                <input type="text" name="fatherdetails" value="<?php echo ev('fatherdetails', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Mother Status</label>
                <input type="text" name="motherstatus" value="<?php echo ev('motherstatus', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Mother Details</label>
                <input type="text" name="motherdetails" value="<?php echo ev('motherdetails', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Number of Brothers</label>
                <input type="number" name="noofbrothers" value="<?php echo ev('noofbrothers', $profile); ?>" min="0">
            </div>
            <div class="ms-form-group">
                <label>Married Brothers</label>
                <input type="number" name="marriedbrothers" value="<?php echo ev('marriedbrothers', $profile); ?>" min="0">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Number of Sisters</label>
                <input type="number" name="noofsisters" value="<?php echo ev('noofsisters', $profile); ?>" min="0">
            </div>
            <div class="ms-form-group">
                <label>Married Sisters</label>
                <input type="number" name="marriedsisters" value="<?php echo ev('marriedsisters', $profile); ?>" min="0">
            </div>
        </div>

        <?php elseif ($section === 'lifestyle'): ?>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Diet</label>
                <select name="diet">
                    <option value="">Select</option>
                    <?php foreach (['Vegetarian','Non-Vegetarian','Eggetarian','Vegan','Jain'] as $o): ?>
                        <option value="<?php echo $o; ?>" <?php echo (($profile['diet'] ?? '') === $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ms-form-group">
                <label>Drinking</label>
                <select name="drinking">
                    <option value="">Select</option>
                    <?php foreach (['No','Yes','Occasionally'] as $o): ?>
                        <option value="<?php echo $o; ?>" <?php echo (($profile['drinking'] ?? '') === $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="ms-form-group" style="max-width:50%;">
            <label>Smoking</label>
            <select name="smoking">
                <option value="">Select</option>
                <?php foreach (['No','Yes','Occasionally'] as $o): ?>
                    <option value="<?php echo $o; ?>" <?php echo (($profile['smoking'] ?? '') === $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php elseif ($section === 'partner'): ?>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Preferred Min Age</label>
                <input type="number" name="partner_min_age" value="<?php echo ev('partner_min_age', $profile); ?>" min="18" max="80">
            </div>
            <div class="ms-form-group">
                <label>Preferred Max Age</label>
                <input type="number" name="partner_max_age" value="<?php echo ev('partner_max_age', $profile); ?>" min="18" max="80">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Preferred Min Height</label>
                <input type="text" name="partner_min_height" value="<?php echo ev('partner_min_height', $profile); ?>" placeholder="e.g. 5'0&quot;">
            </div>
            <div class="ms-form-group">
                <label>Preferred Max Height</label>
                <input type="text" name="partner_max_height" value="<?php echo ev('partner_max_height', $profile); ?>" placeholder="e.g. 6'0&quot;">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Preferred Marital Status</label>
                <input type="text" name="partner_maritalstatus" value="<?php echo ev('partner_maritalstatus', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Preferred Religion</label>
                <input type="text" name="partner_religion" value="<?php echo ev('partner_religion', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Preferred Community</label>
                <input type="text" name="partner_community" value="<?php echo ev('partner_community', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Preferred Education</label>
                <input type="text" name="partner_education" value="<?php echo ev('partner_education', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-row">
            <div class="ms-form-group">
                <label>Preferred Occupation</label>
                <input type="text" name="partner_occupation" value="<?php echo ev('partner_occupation', $profile); ?>">
            </div>
            <div class="ms-form-group">
                <label>Preferred City</label>
                <input type="text" name="partner_city" value="<?php echo ev('partner_city', $profile); ?>">
            </div>
        </div>
        <div class="ms-form-group" style="max-width:50%;">
            <label>Preferred Income</label>
            <input type="text" name="partner_income" value="<?php echo ev('partner_income', $profile); ?>">
        </div>
        <?php endif; ?>

        <div class="d-flex gap-3 mt-3">
            <button type="submit" class="ms-btn-save" id="saveBtn">
                <i class="fas fa-save me-1"></i> Save Changes
            </button>
            <a href="profile.php" class="btn btn-light" style="border-radius:10px;padding:12px 24px;">Cancel</a>
        </div>
    </form>
</div>

<script>
function submitForm(e) {
    e.preventDefault();
    var btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

    var formData = new FormData(document.getElementById('editForm'));
    var data = {};
    formData.forEach(function(val, key) { data[key] = val; });

    fetch('<?php echo $endpoints[$section]; ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (resp.success) {
            Swal.fire({ icon: 'success', title: 'Saved!', text: resp.message || 'Profile updated successfully.', timer: 1800, showConfirmButton: false })
            .then(function() { window.location.href = 'profile.php'; });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: resp.message || 'Failed to save changes.' });
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i> Save Changes';
        }
    })
    .catch(function() {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> Save Changes';
    });

    return false;
}
</script>

<?php require_once __DIR__ . '/includes/user_footer.php'; ?>
