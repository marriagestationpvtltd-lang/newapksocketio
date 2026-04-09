<?php
/**
 * register.php – Multi-step user registration for Marriage Station.
 */
require_once __DIR__ . '/includes/user_auth.php';

if (isUserLoggedIn()) {
    header('Location: home.php');
    exit;
}

$errors = [];

// Handle final form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_submit'])) {
    // Collect & sanitize fields
    $profileForId = (int) ($_POST['profileForId'] ?? 1);
    $gender       = trim($_POST['gender'] ?? '');
    $firstName    = trim($_POST['firstName'] ?? '');
    $lastName     = trim($_POST['lastName'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $contactNo    = trim($_POST['contactNo'] ?? '');
    $dateofbirth  = trim($_POST['dateofbirth'] ?? '');
    $maritalStatus = trim($_POST['maritalStatus'] ?? '');
    $religion     = trim($_POST['religion'] ?? '');
    $community    = trim($_POST['community'] ?? '');

    // Server-side validation
    if (!in_array($profileForId, [1, 2, 3, 4, 5, 6])) $errors[] = 'Invalid profile-for selection.';
    if (!in_array($gender, ['Male', 'Female']))          $errors[] = 'Please select a gender.';
    if ($firstName === '')  $errors[] = 'First name is required.';
    if ($lastName === '')   $errors[] = 'Last name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))      $errors[] = 'Valid email is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($contactNo === '')  $errors[] = 'Phone number is required.';
    if ($dateofbirth === '') $errors[] = 'Date of birth is required.';

    if (empty($errors)) {
        // Build cURL request to signup API
        $apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST'] . '/Api2/signup.php';

        $postFields = [
            'profileforId' => $profileForId,
            'firstName'    => $firstName,
            'lastName'     => $lastName,
            'email'        => $email,
            'password'     => $password,
            'contactNo'    => $contactNo,
            'gender'       => $gender,
            'Languages'    => 'Nepali',
            'Nationality'  => 'Nepali',
            'dateofbirth'  => $dateofbirth,
        ];

        if ($maritalStatus !== '') $postFields['maritalStatus'] = $maritalStatus;
        if ($religion !== '')      $postFields['religion']      = $religion;
        if ($community !== '')     $postFields['community']     = $community;

        // Handle photo upload
        $hasPhoto = isset($_FILES['profile_picture'])
                 && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK;

        if ($hasPhoto) {
            $postFields['profile_picture'] = new CURLFile(
                $_FILES['profile_picture']['tmp_name'],
                $_FILES['profile_picture']['type'],
                $_FILES['profile_picture']['name']
            );
        }

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $hasPhoto ? $postFields : json_encode($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $hasPhoto
                ? ['Accept: application/json']
                : ['Content-Type: application/json', 'Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $errors[] = 'Connection error. Please try again later.';
        } else {
            $body = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300 && !empty($body['success'])) {
                header('Location: login.php?registered=1');
                exit;
            }
            $errors[] = $body['message'] ?? 'Registration failed. Please try again.';
        }
    }
}

$title = 'Register – Marriage Station';
require_once __DIR__ . '/includes/public_header.php';
?>

<style>
.ms-auth-card {
    max-width: 540px;
    margin: 24px auto;
    background: var(--ms-white);
    border-radius: 16px;
    box-shadow: var(--ms-shadow);
    padding: 36px 32px;
}
.ms-auth-card .ms-brand { text-align: center; margin-bottom: 24px; }
.ms-auth-card .ms-brand i { font-size: 2.2rem; color: var(--ms-primary); }
.ms-auth-card .ms-brand h3 { font-weight: 800; margin-top: 8px; }
.ms-auth-card .ms-brand p { color: var(--ms-text-muted); font-size: 0.95rem; }

/* Progress bar */
.ms-progress { display: flex; gap: 8px; margin-bottom: 28px; }
.ms-progress-step {
    flex: 1;
    height: 6px;
    border-radius: 3px;
    background: var(--ms-border);
    transition: background 0.3s;
}
.ms-progress-step.active { background: var(--ms-primary); }

.ms-step-label { text-align: center; font-weight: 600; font-size: 0.88rem; color: var(--ms-text-muted); margin-bottom: 20px; }
.ms-step-section { display: none; }
.ms-step-section.active { display: block; }

.ms-auth-card .form-label { font-weight: 600; font-size: 0.92rem; }
.ms-auth-card .form-control,
.ms-auth-card .form-select {
    border-radius: 10px;
    padding: 10px 14px;
    border: 1.5px solid var(--ms-border);
}
.ms-auth-card .form-control:focus,
.ms-auth-card .form-select:focus {
    border-color: var(--ms-primary);
    box-shadow: 0 0 0 3px rgba(249,14,24,0.1);
}

.ms-radio-group { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.ms-radio-group .ms-radio-btn {
    flex: 1 1 auto;
    min-width: 90px;
}
.ms-radio-group input[type="radio"] { display: none; }
.ms-radio-group label {
    display: block;
    text-align: center;
    padding: 10px 14px;
    border: 2px solid var(--ms-border);
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.92rem;
    cursor: pointer;
    transition: all 0.2s;
}
.ms-radio-group input[type="radio"]:checked + label {
    border-color: var(--ms-primary);
    background: rgba(249,14,24,0.06);
    color: var(--ms-primary);
}

.btn-ms-primary { width: 100%; padding: 11px; font-size: 1rem; }
.ms-links { text-align: center; margin-top: 18px; font-size: 0.92rem; }
.ms-links a { color: var(--ms-primary); font-weight: 600; text-decoration: none; }
.ms-links a:hover { text-decoration: underline; }
.step-nav { display: flex; gap: 10px; margin-top: 8px; }
.step-nav .btn { flex: 1; padding: 11px; font-size: 0.95rem; }
</style>

<div class="ms-auth-card">
    <div class="ms-brand">
        <i class="fas fa-heart"></i>
        <h3>Create Your Account</h3>
        <p>Join Marriage Station – it's free!</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2">
            <i class="fas fa-exclamation-circle me-1"></i>
            <?php echo htmlspecialchars(implode(' ', $errors)); ?>
        </div>
    <?php endif; ?>

    <!-- Progress -->
    <div class="ms-progress">
        <div class="ms-progress-step active" id="prog1"></div>
        <div class="ms-progress-step" id="prog2"></div>
        <div class="ms-progress-step" id="prog3"></div>
    </div>
    <div class="ms-step-label" id="stepLabel">Step 1 of 3 – Profile For</div>

    <form method="POST" action="register.php" enctype="multipart/form-data" id="regForm" novalidate>
        <input type="hidden" name="final_submit" value="1">

        <!-- ===== STEP 1 ===== -->
        <div class="ms-step-section active" id="step1">
            <label class="form-label">Creating profile for</label>
            <div class="ms-radio-group">
                <?php
                $profileOptions = [1 => 'Self', 2 => 'Son', 3 => 'Daughter', 4 => 'Brother', 5 => 'Sister', 6 => 'Friend'];
                foreach ($profileOptions as $id => $label): ?>
                    <div class="ms-radio-btn">
                        <input type="radio" name="profileForId" id="pf<?php echo $id; ?>" value="<?php echo $id; ?>" <?php echo $id === 1 ? 'checked' : ''; ?>>
                        <label for="pf<?php echo $id; ?>"><?php echo $label; ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

            <label class="form-label">Gender</label>
            <div class="ms-radio-group">
                <div class="ms-radio-btn">
                    <input type="radio" name="gender" id="gMale" value="Male" checked>
                    <label for="gMale"><i class="fas fa-mars me-1"></i> Male</label>
                </div>
                <div class="ms-radio-btn">
                    <input type="radio" name="gender" id="gFemale" value="Female">
                    <label for="gFemale"><i class="fas fa-venus me-1"></i> Female</label>
                </div>
            </div>

            <button type="button" class="btn btn-ms-primary mt-2" onclick="goToStep(2)">
                Next <i class="fas fa-arrow-right ms-1"></i>
            </button>
        </div>

        <!-- ===== STEP 2 ===== -->
        <div class="ms-step-section" id="step2">
            <div class="row g-3">
                <div class="col-6">
                    <label for="firstName" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="firstName" name="firstName" required
                           value="<?php echo htmlspecialchars($_POST['firstName'] ?? ''); ?>">
                </div>
                <div class="col-6">
                    <label for="lastName" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="lastName" name="lastName" required
                           value="<?php echo htmlspecialchars($_POST['lastName'] ?? ''); ?>">
                </div>
            </div>

            <div class="mb-3 mt-3">
                <label for="regEmail" class="form-label">Email</label>
                <input type="email" class="form-control" id="regEmail" name="email" required
                       placeholder="you@example.com"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label for="regPassword" class="form-label">Password</label>
                <input type="password" class="form-control" id="regPassword" name="password" required
                       placeholder="Min 6 characters" minlength="6">
            </div>

            <div class="mb-3">
                <label for="contactNo" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="contactNo" name="contactNo" required
                       placeholder="98XXXXXXXX"
                       value="<?php echo htmlspecialchars($_POST['contactNo'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label for="dateofbirth" class="form-label">Date of Birth</label>
                <input type="date" class="form-control" id="dateofbirth" name="dateofbirth" required
                       value="<?php echo htmlspecialchars($_POST['dateofbirth'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label for="profile_picture" class="form-label">Profile Photo <small class="text-muted">(optional)</small></label>
                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
            </div>

            <div class="step-nav">
                <button type="button" class="btn btn-ms-outline" onclick="goToStep(1)">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </button>
                <button type="button" class="btn btn-ms-primary" onclick="goToStep(3)">
                    Next <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>

        <!-- ===== STEP 3 ===== -->
        <div class="ms-step-section" id="step3">
            <div class="mb-3">
                <label for="maritalStatus" class="form-label">Marital Status</label>
                <select class="form-select" id="maritalStatus" name="maritalStatus">
                    <option value="">-- Select --</option>
                    <option value="Never Married">Never Married</option>
                    <option value="Divorced">Divorced</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Separated">Separated</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="religion" class="form-label">Religion</label>
                <select class="form-select" id="religion" name="religion">
                    <option value="">-- Select --</option>
                    <option value="Hindu">Hindu</option>
                    <option value="Buddhist">Buddhist</option>
                    <option value="Muslim">Muslim</option>
                    <option value="Christian">Christian</option>
                    <option value="Kirat">Kirat</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="community" class="form-label">Community <small class="text-muted">(optional)</small></label>
                <input type="text" class="form-control" id="community" name="community"
                       placeholder="e.g. Brahmin, Chhetri, Newar…"
                       value="<?php echo htmlspecialchars($_POST['community'] ?? ''); ?>">
            </div>

            <div class="step-nav">
                <button type="button" class="btn btn-ms-outline" onclick="goToStep(2)">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </button>
                <button type="submit" class="btn btn-ms-primary" id="submitBtn">
                    <i class="fas fa-user-plus me-1"></i> Create Account
                </button>
            </div>
        </div>
    </form>

    <div class="ms-links">
        Already have an account? <a href="login.php">Login</a>
    </div>
</div>

<script>
const stepLabels = {
    1: 'Step 1 of 3 – Profile For',
    2: 'Step 2 of 3 – Basic Details',
    3: 'Step 3 of 3 – Personal Details'
};

function goToStep(n) {
    // Validate before advancing
    if (n > 1 && !validateStep(n - 1)) return;

    document.querySelectorAll('.ms-step-section').forEach(s => s.classList.remove('active'));
    document.getElementById('step' + n).classList.add('active');

    for (let i = 1; i <= 3; i++) {
        document.getElementById('prog' + i).classList.toggle('active', i <= n);
    }
    document.getElementById('stepLabel').textContent = stepLabels[n];
}

function validateStep(step) {
    if (step === 1) {
        if (!document.querySelector('input[name="gender"]:checked')) {
            showAlert('Please select a gender.'); return false;
        }
        return true;
    }
    if (step === 2) {
        const f = document.getElementById('firstName').value.trim();
        const l = document.getElementById('lastName').value.trim();
        const e = document.getElementById('regEmail').value.trim();
        const p = document.getElementById('regPassword').value;
        const c = document.getElementById('contactNo').value.trim();
        const d = document.getElementById('dateofbirth').value;

        if (!f)               { showAlert('First name is required.');            return false; }
        if (!l)               { showAlert('Last name is required.');             return false; }
        if (!e || !e.includes('@')) { showAlert('Valid email is required.');      return false; }
        if (p.length < 6)    { showAlert('Password must be at least 6 characters.'); return false; }
        if (!c)               { showAlert('Phone number is required.');          return false; }
        if (!d)               { showAlert('Date of birth is required.');         return false; }
        return true;
    }
    return true;
}

function showAlert(msg) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'warning', text: msg, confirmButtonColor: '#F90E18' });
    } else {
        alert(msg);
    }
}

// Prevent double-submit
document.getElementById('regForm').addEventListener('submit', function () {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating…';
});
</script>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
