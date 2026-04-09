<?php
/**
 * forgot-password.php – Three-step password recovery for Marriage Station.
 * Step 1: Enter email  → send OTP
 * Step 2: Enter OTP    → verify
 * Step 3: New password  → reset
 */
require_once __DIR__ . '/includes/user_auth.php';

if (isUserLoggedIn()) {
    header('Location: home.php');
    exit;
}

$title = 'Forgot Password – Marriage Station';
require_once __DIR__ . '/includes/public_header.php';
?>

<style>
.ms-auth-card {
    max-width: 460px;
    margin: 40px auto;
    background: var(--ms-white);
    border-radius: 16px;
    box-shadow: var(--ms-shadow);
    padding: 40px 32px;
}
.ms-auth-card .ms-brand { text-align: center; margin-bottom: 24px; }
.ms-auth-card .ms-brand i { font-size: 2.2rem; color: var(--ms-primary); }
.ms-auth-card .ms-brand h3 { font-weight: 800; margin-top: 8px; }
.ms-auth-card .ms-brand p { color: var(--ms-text-muted); font-size: 0.95rem; }

.ms-progress { display: flex; gap: 8px; margin-bottom: 28px; }
.ms-progress-step { flex: 1; height: 6px; border-radius: 3px; background: var(--ms-border); transition: background 0.3s; }
.ms-progress-step.active { background: var(--ms-primary); }
.ms-step-label { text-align: center; font-weight: 600; font-size: 0.88rem; color: var(--ms-text-muted); margin-bottom: 20px; }

.fp-step { display: none; }
.fp-step.active { display: block; }

.ms-auth-card .form-label { font-weight: 600; font-size: 0.92rem; }
.ms-auth-card .form-control {
    border-radius: 10px; padding: 10px 14px; border: 1.5px solid var(--ms-border);
}
.ms-auth-card .form-control:focus {
    border-color: var(--ms-primary); box-shadow: 0 0 0 3px rgba(249,14,24,0.1);
}
.btn-ms-primary { width: 100%; padding: 11px; font-size: 1rem; }
.ms-links { text-align: center; margin-top: 18px; font-size: 0.92rem; }
.ms-links a { color: var(--ms-primary); font-weight: 600; text-decoration: none; }
.ms-links a:hover { text-decoration: underline; }
#fpAlert { display: none; }
</style>

<div class="ms-auth-card">
    <div class="ms-brand">
        <i class="fas fa-key"></i>
        <h3>Reset Password</h3>
        <p>We'll help you get back into your account</p>
    </div>

    <div class="ms-progress">
        <div class="ms-progress-step active" id="fpProg1"></div>
        <div class="ms-progress-step" id="fpProg2"></div>
        <div class="ms-progress-step" id="fpProg3"></div>
    </div>
    <div class="ms-step-label" id="fpStepLabel">Step 1 of 3 – Enter Email</div>

    <div id="fpAlert" class="alert py-2 mb-3" role="alert"></div>

    <!-- Step 1: Email -->
    <div class="fp-step active" id="fpStep1">
        <div class="mb-3">
            <label for="fpEmail" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="fpEmail" placeholder="you@example.com" required>
        </div>
        <button type="button" class="btn btn-ms-primary" id="btnSendOtp" onclick="sendOtp()">
            <i class="fas fa-paper-plane me-1"></i> Send OTP
        </button>
    </div>

    <!-- Step 2: OTP -->
    <div class="fp-step" id="fpStep2">
        <div class="mb-3">
            <label for="fpOtp" class="form-label">Enter the 6-digit OTP</label>
            <input type="text" class="form-control text-center" id="fpOtp"
                   maxlength="6" placeholder="------" style="letter-spacing:8px;font-size:1.3rem;font-weight:700;" required>
        </div>
        <button type="button" class="btn btn-ms-primary" id="btnVerifyOtp" onclick="verifyOtp()">
            <i class="fas fa-check-circle me-1"></i> Verify OTP
        </button>
    </div>

    <!-- Step 3: New Password -->
    <div class="fp-step" id="fpStep3">
        <div class="mb-3">
            <label for="fpNewPass" class="form-label">New Password</label>
            <input type="password" class="form-control" id="fpNewPass" placeholder="Min 6 characters" minlength="6" required>
        </div>
        <div class="mb-3">
            <label for="fpConfirmPass" class="form-label">Confirm Password</label>
            <input type="password" class="form-control" id="fpConfirmPass" placeholder="Re-enter password" required>
        </div>
        <button type="button" class="btn btn-ms-primary" id="btnResetPass" onclick="resetPassword()">
            <i class="fas fa-lock me-1"></i> Reset Password
        </button>
    </div>

    <div class="ms-links">
        <a href="login.php"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
    </div>
</div>

<script>
const API_BASE = '/Api2';
let fpEmail = '';
let fpOtp   = '';

function showFpAlert(msg, type) {
    const el = document.getElementById('fpAlert');
    el.className = 'alert py-2 mb-3 alert-' + type;
    el.innerHTML = '<i class="fas fa-' + (type === 'danger' ? 'exclamation-circle' : 'check-circle') + ' me-1"></i>' + escapeHtml(msg);
    el.style.display = 'block';
}
function hideFpAlert() { document.getElementById('fpAlert').style.display = 'none'; }

function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function setStep(n) {
    const labels = { 1: 'Step 1 of 3 – Enter Email', 2: 'Step 2 of 3 – Verify OTP', 3: 'Step 3 of 3 – New Password' };
    document.querySelectorAll('.fp-step').forEach(s => s.classList.remove('active'));
    document.getElementById('fpStep' + n).classList.add('active');
    for (let i = 1; i <= 3; i++) document.getElementById('fpProg' + i).classList.toggle('active', i <= n);
    document.getElementById('fpStepLabel').textContent = labels[n];
    hideFpAlert();
}

function setLoading(btn, loading) {
    btn.disabled = loading;
    if (loading) {
        btn.dataset.origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Please wait…';
    } else {
        btn.innerHTML = btn.dataset.origHtml;
    }
}

async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(data)
    });
    return { status: res.status, body: await res.json() };
}

async function sendOtp() {
    const email = document.getElementById('fpEmail').value.trim();
    if (!email || !email.includes('@')) { showFpAlert('Please enter a valid email.', 'danger'); return; }

    const btn = document.getElementById('btnSendOtp');
    setLoading(btn, true);
    try {
        const { status, body } = await apiPost(API_BASE + '/forgot_password_send_otp.php', { email });
        if (body.success || status === 200) {
            fpEmail = email;
            setStep(2);
            showFpAlert(body.message || 'OTP sent to your email.', 'success');
        } else {
            showFpAlert(body.message || 'Failed to send OTP.', 'danger');
        }
    } catch (e) {
        showFpAlert('Network error. Please try again.', 'danger');
    }
    setLoading(btn, false);
}

async function verifyOtp() {
    const otp = document.getElementById('fpOtp').value.trim();
    if (otp.length < 4) { showFpAlert('Please enter the OTP.', 'danger'); return; }

    const btn = document.getElementById('btnVerifyOtp');
    setLoading(btn, true);
    try {
        const { status, body } = await apiPost(API_BASE + '/forgot_password_verify_otp.php', { email: fpEmail, otp });
        if (body.success || status === 200) {
            fpOtp = otp;
            setStep(3);
            showFpAlert('OTP verified! Set your new password.', 'success');
        } else {
            showFpAlert(body.message || 'Invalid or expired OTP.', 'danger');
        }
    } catch (e) {
        showFpAlert('Network error. Please try again.', 'danger');
    }
    setLoading(btn, false);
}

async function resetPassword() {
    const newPass    = document.getElementById('fpNewPass').value;
    const confirmPass = document.getElementById('fpConfirmPass').value;

    if (newPass.length < 6) { showFpAlert('Password must be at least 6 characters.', 'danger'); return; }
    if (newPass !== confirmPass) { showFpAlert('Passwords do not match.', 'danger'); return; }

    const btn = document.getElementById('btnResetPass');
    setLoading(btn, true);
    try {
        const { status, body } = await apiPost(API_BASE + '/forgot_password_reset.php', {
            email: fpEmail, otp: fpOtp, new_password: newPass
        });
        if (body.success || status === 200) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Password Reset!',
                    text: body.message || 'You can now log in with your new password.',
                    confirmButtonColor: '#F90E18'
                }).then(() => { window.location.href = 'login.php?reset=1'; });
            } else {
                window.location.href = 'login.php?reset=1';
            }
        } else {
            showFpAlert(body.message || 'Failed to reset password.', 'danger');
        }
    } catch (e) {
        showFpAlert('Network error. Please try again.', 'danger');
    }
    setLoading(btn, false);
}
</script>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
