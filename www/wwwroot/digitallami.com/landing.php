<?php
/**
 * landing.php – Public marketing / landing page for Marriage Station.
 */
require_once __DIR__ . '/includes/user_auth.php';

if (isUserLoggedIn()) {
    header('Location: home.php');
    exit;
}

$title = 'Marriage Station – Find Your Perfect Life Partner';
require_once __DIR__ . '/includes/public_header.php';
?>

<style>
/* ---------- Hero ---------- */
.ms-hero {
    background: linear-gradient(135deg, #F90E18 0%, #D00D15 50%, #a00a10 100%);
    color: #fff;
    text-align: center;
    padding: 80px 20px 90px;
    margin: -24px -16px 0;
    position: relative;
    overflow: hidden;
}
.ms-hero::before {
    content: '';
    position: absolute;
    top: -60%;
    left: -20%;
    width: 140%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
    pointer-events: none;
}
.ms-hero h1 {
    font-size: 2.8rem;
    font-weight: 800;
    margin-bottom: 16px;
    position: relative;
}
.ms-hero p.lead {
    font-size: 1.25rem;
    opacity: 0.92;
    margin-bottom: 32px;
    position: relative;
}
.ms-hero .btn { font-size: 1.05rem; padding: 12px 32px; border-radius: 10px; }
.ms-hero .btn-light { color: #F90E18; font-weight: 700; }
.ms-hero .btn-outline-light { border-width: 2px; font-weight: 600; }

/* ---------- Sections ---------- */
.ms-section { padding: 60px 0; }
.ms-section-title {
    text-align: center;
    font-weight: 800;
    font-size: 1.8rem;
    margin-bottom: 12px;
    color: var(--ms-text);
}
.ms-section-sub {
    text-align: center;
    color: var(--ms-text-muted);
    margin-bottom: 48px;
    font-size: 1.05rem;
}

/* Feature cards */
.ms-feature-card {
    background: var(--ms-white);
    border-radius: 14px;
    padding: 36px 24px;
    text-align: center;
    box-shadow: var(--ms-shadow);
    transition: transform 0.25s, box-shadow 0.25s;
    height: 100%;
}
.ms-feature-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 8px 28px rgba(0,0,0,0.12);
}
.ms-feature-icon {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    background: rgba(249,14,24,0.08);
    color: var(--ms-primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    margin-bottom: 18px;
}
.ms-feature-card h5 { font-weight: 700; margin-bottom: 10px; }
.ms-feature-card p { color: var(--ms-text-muted); font-size: 0.95rem; margin: 0; }

/* How-it-works */
.ms-step {
    text-align: center;
    padding: 20px 10px;
}
.ms-step-number {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: var(--ms-primary);
    color: #fff;
    font-weight: 800;
    font-size: 1.3rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
}
.ms-step h6 { font-weight: 700; font-size: 1.05rem; }
.ms-step p { color: var(--ms-text-muted); font-size: 0.92rem; }

/* Stats */
.ms-stats { background: #fff; border-radius: 14px; box-shadow: var(--ms-shadow); padding: 40px 20px; }
.ms-stat h3 { font-weight: 800; color: var(--ms-primary); margin-bottom: 4px; }
.ms-stat p { color: var(--ms-text-muted); margin: 0; font-weight: 500; }

/* Bottom CTA */
.ms-cta-bottom {
    background: linear-gradient(135deg, #F90E18, #D00D15);
    color: #fff;
    text-align: center;
    padding: 60px 20px;
    border-radius: 18px;
    margin-bottom: 20px;
}
.ms-cta-bottom h2 { font-weight: 800; margin-bottom: 12px; }
.ms-cta-bottom p { opacity: 0.9; margin-bottom: 28px; font-size: 1.1rem; }

@media (max-width: 767.98px) {
    .ms-hero h1 { font-size: 2rem; }
    .ms-hero p.lead { font-size: 1.05rem; }
    .ms-section-title { font-size: 1.5rem; }
}
</style>

<!-- ======== Hero ======== -->
<section class="ms-hero">
    <div class="container">
        <h1>Find Your Perfect Life Partner</h1>
        <p class="lead">Nepal's Most Trusted Matrimony Service</p>
        <a href="register.php" class="btn btn-light me-2 mb-2">
            <i class="fas fa-user-plus me-1"></i> Register Free
        </a>
        <a href="login.php" class="btn btn-outline-light mb-2">
            <i class="fas fa-sign-in-alt me-1"></i> Login
        </a>
    </div>
</section>

<!-- ======== Features ======== -->
<section class="ms-section">
    <div class="container">
        <h2 class="ms-section-title">Why Choose Marriage Station?</h2>
        <p class="ms-section-sub">Trusted by thousands of families across Nepal</p>

        <div class="row g-4">
            <div class="col-sm-6 col-lg-3">
                <div class="ms-feature-card">
                    <div class="ms-feature-icon"><i class="fas fa-user-check"></i></div>
                    <h5>Verified Profiles</h5>
                    <p>Every profile is reviewed to ensure authenticity and trust.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="ms-feature-card">
                    <div class="ms-feature-icon"><i class="fas fa-search"></i></div>
                    <h5>Advanced Search</h5>
                    <p>Filter by age, location, religion, education and more.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="ms-feature-card">
                    <div class="ms-feature-icon"><i class="fas fa-lock"></i></div>
                    <h5>Secure Chat</h5>
                    <p>Private messaging with end-to-end privacy protection.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="ms-feature-card">
                    <div class="ms-feature-icon"><i class="fas fa-video"></i></div>
                    <h5>Video Calling</h5>
                    <p>Connect face-to-face before meeting in person.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ======== How It Works ======== -->
<section class="ms-section" style="background:#fff;margin:0 -16px;padding-left:16px;padding-right:16px;">
    <div class="container">
        <h2 class="ms-section-title">How It Works</h2>
        <p class="ms-section-sub">Four simple steps to find your match</p>

        <div class="row">
            <div class="col-6 col-md-3">
                <div class="ms-step">
                    <div class="ms-step-number">1</div>
                    <h6>Create Profile</h6>
                    <p>Sign up and build your detailed matrimony profile.</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="ms-step">
                    <div class="ms-step-number">2</div>
                    <h6>Search Partners</h6>
                    <p>Use filters to find matches that suit your preferences.</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="ms-step">
                    <div class="ms-step-number">3</div>
                    <h6>Connect &amp; Chat</h6>
                    <p>Send interest, chat and video-call your matches.</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="ms-step">
                    <div class="ms-step-number">4</div>
                    <h6>Get Married</h6>
                    <p>Meet your life partner and start your journey together.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ======== Stats ======== -->
<section class="ms-section">
    <div class="container">
        <div class="ms-stats">
            <div class="row text-center">
                <div class="col-4 ms-stat">
                    <h3>1000+</h3>
                    <p>Profiles</p>
                </div>
                <div class="col-4 ms-stat">
                    <h3>500+</h3>
                    <p>Matches</p>
                </div>
                <div class="col-4 ms-stat">
                    <h3>100%</h3>
                    <p>Verified</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ======== Bottom CTA ======== -->
<section class="ms-section" style="padding-bottom:0;">
    <div class="container">
        <div class="ms-cta-bottom">
            <h2>Start Your Journey Today</h2>
            <p>Join thousands of happy couples who found love on Marriage Station.</p>
            <a href="register.php" class="btn btn-light btn-lg">
                <i class="fas fa-heart me-1"></i> Register Free Now
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
