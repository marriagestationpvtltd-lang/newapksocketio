<?php
require_once __DIR__ . '/user_auth.php';
requireUserLogin();
$currentUser = getCurrentUser();
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
            padding-top: 70px; /* navbar height */
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

        .ms-navbar .navbar-brand i {
            margin-right: 6px;
            font-size: 1.1rem;
        }

        .ms-navbar .nav-link {
            color: var(--ms-text-muted);
            font-weight: 500;
            font-size: 0.92rem;
            padding: 8px 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
            position: relative;
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
        .ms-navbar .nav-link i {
            margin-right: 5px;
            font-size: 1rem;
        }

        /* Notification badge */
        .ms-badge-count {
            position: absolute;
            top: 2px;
            right: 4px;
            background: var(--ms-primary);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        /* User dropdown */
        .ms-user-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 10px 4px 4px;
            border-radius: 50px;
            border: 1px solid var(--ms-border);
            background: var(--ms-white);
            cursor: pointer;
            transition: box-shadow 0.2s;
        }
        .ms-user-toggle:hover { box-shadow: 0 0 0 3px rgba(249, 14, 24, 0.12); }

        .ms-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--ms-primary-light);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .ms-user-name {
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--ms-text);
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dropdown-menu {
            border: 1px solid var(--ms-border);
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            padding: 6px 0;
            min-width: 200px;
        }
        .dropdown-item {
            font-size: 0.9rem;
            padding: 9px 18px;
            color: var(--ms-text);
            transition: background 0.15s;
        }
        .dropdown-item:hover { background: rgba(249, 14, 24, 0.06); color: var(--ms-primary); }
        .dropdown-item i { width: 22px; color: var(--ms-text-muted); }
        .dropdown-item:hover i { color: var(--ms-primary); }
        .dropdown-divider { margin: 4px 0; }

        .dropdown-item.text-danger i { color: var(--ms-primary); }

        /* ---------- Hamburger ---------- */
        .navbar-toggler {
            border: 1px solid var(--ms-border);
            padding: 6px 10px;
        }
        .navbar-toggler:focus { box-shadow: 0 0 0 3px rgba(249, 14, 24, 0.15); }
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='%23F90E18' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* ---------- Mobile nav ---------- */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: var(--ms-white);
                border-radius: 0 0 12px 12px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.08);
                padding: 12px 16px;
                margin-top: 8px;
            }
            .ms-navbar .nav-link {
                padding: 10px 12px;
                border-radius: 8px;
            }
            .ms-user-section {
                border-top: 1px solid var(--ms-border);
                margin-top: 8px;
                padding-top: 12px;
            }
            .ms-user-name-mobile { display: inline !important; }
        }

        @media (min-width: 992px) {
            .ms-user-name-mobile { display: none !important; }
        }

        /* ---------- Content area ---------- */
        .ms-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 16px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg ms-navbar fixed-top">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-heart"></i> Marriage Station
        </a>

        <!-- Mobile toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#msMainNav"
                aria-controls="msMainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Collapsible nav -->
        <div class="collapse navbar-collapse" id="msMainNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array($_currentPage, ['index.php', 'home.php']) ? 'active' : ''; ?>" href="home.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $_currentPage === 'search.php' ? 'active' : ''; ?>" href="search.php">
                        <i class="fas fa-search"></i> Search
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $_currentPage === 'proposals.php' ? 'active' : ''; ?>" href="proposals.php">
                        <i class="fas fa-paper-plane"></i> Proposals
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $_currentPage === 'liked.php' ? 'active' : ''; ?>" href="liked.php">
                        <i class="fas fa-heart"></i> Liked
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $_currentPage === 'chat.php' ? 'active' : ''; ?>" href="chat.php">
                        <i class="fas fa-comments"></i> Chat
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $_currentPage === 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
                        <i class="fas fa-bell"></i> Notifications
                        <span class="ms-badge-count" id="msNotifCount" style="display:none;">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $_currentPage === 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
            </ul>

            <!-- User section -->
            <div class="ms-user-section">
                <div class="dropdown">
                    <div class="ms-user-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (!empty($currentUser['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($currentUser['profile_picture']); ?>"
                                 alt="Avatar" class="ms-user-avatar">
                        <?php else: ?>
                            <span class="ms-user-avatar">
                                <?php echo htmlspecialchars(mb_strtoupper(mb_substr($currentUser['firstName'] ?? 'U', 0, 1))); ?>
                            </span>
                        <?php endif; ?>
                        <span class="ms-user-name">
                            <?php echo htmlspecialchars(($currentUser['firstName'] ?? '') . ' ' . ($currentUser['lastName'] ?? '')); ?>
                        </span>
                        <i class="fas fa-chevron-down" style="font-size:0.7rem;color:var(--ms-text-muted);"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Main content wrapper -->
<main class="ms-content">
