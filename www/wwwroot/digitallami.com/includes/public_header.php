<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('user_session');
    session_start();
}
$_currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Marriage Station'); ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --ms-primary:       #F90E18;
            --ms-primary-dark:  #D00D15;
            --ms-primary-light: #FF4D56;
            --ms-bg:            #f5f6fa;
            --ms-text:          #2d3436;
            --ms-text-muted:    #636e72;
            --ms-border:        #e9ecef;
            --ms-white:         #ffffff;
            --ms-shadow:        0 2px 12px rgba(0,0,0,0.08);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--ms-bg);
            color: var(--ms-text);
            margin: 0;
            padding-top: 70px;
        }

        /* ---------- Navbar ---------- */
        .ms-navbar {
            background: var(--ms-white);
            box-shadow: var(--ms-shadow);
            height: 70px;
            z-index: 1030;
        }

        .ms-navbar .navbar-brand {
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--ms-primary);
            letter-spacing: -0.5px;
        }
        .ms-navbar .navbar-brand:hover { color: var(--ms-primary-dark); }
        .ms-navbar .navbar-brand i { margin-right: 6px; font-size: 1.1rem; }

        .ms-navbar .nav-link {
            color: var(--ms-text-muted);
            font-weight: 500;
            font-size: 0.92rem;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .ms-navbar .nav-link:hover {
            color: var(--ms-primary);
            background: rgba(249, 14, 24, 0.06);
        }
        .ms-navbar .nav-link.active {
            color: var(--ms-primary);
            background: rgba(249, 14, 24, 0.1);
            font-weight: 600;
        }

        /* CTA Buttons */
        .btn-ms-primary {
            background: var(--ms-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 22px;
            font-weight: 600;
            font-size: 0.92rem;
            transition: all 0.2s;
        }
        .btn-ms-primary:hover {
            background: var(--ms-primary-dark);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(249, 14, 24, 0.25);
        }

        .btn-ms-outline {
            background: transparent;
            color: var(--ms-primary);
            border: 2px solid var(--ms-primary);
            border-radius: 8px;
            padding: 6px 20px;
            font-weight: 600;
            font-size: 0.92rem;
            transition: all 0.2s;
        }
        .btn-ms-outline:hover {
            background: var(--ms-primary);
            color: #fff;
        }

        /* Hamburger */
        .navbar-toggler {
            border: 1px solid var(--ms-border);
            padding: 6px 10px;
        }
        .navbar-toggler:focus { box-shadow: 0 0 0 3px rgba(249, 14, 24, 0.15); }
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='%23F90E18' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: var(--ms-white);
                border-radius: 0 0 12px 12px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.08);
                padding: 12px 16px;
                margin-top: 8px;
            }
            .ms-navbar .nav-link { padding: 10px 12px; }
            .ms-auth-buttons {
                border-top: 1px solid var(--ms-border);
                margin-top: 8px;
                padding-top: 12px;
                display: flex;
                gap: 8px;
            }
        }

        .ms-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 16px;
        }
    </style>
</head>
<body>

<!-- Public Navbar -->
<nav class="navbar navbar-expand-lg ms-navbar fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-heart"></i> Marriage Station
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#msPublicNav"
                aria-controls="msPublicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="msPublicNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $_currentPage === 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
            </ul>

            <div class="ms-auth-buttons d-flex align-items-center gap-2">
                <a href="login.php" class="btn btn-ms-outline <?php echo $_currentPage === 'login.php' ? 'active' : ''; ?>">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="register.php" class="btn btn-ms-primary <?php echo $_currentPage === 'register.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-plus"></i> Register
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Main content wrapper -->
<main class="ms-content">
