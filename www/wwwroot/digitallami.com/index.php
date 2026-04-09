<?php
/**
 * index.php – Router / redirect page.
 * Sends logged-in users to home.php, guests to landing.php.
 */
require_once __DIR__ . '/includes/user_auth.php';

if (isUserLoggedIn()) {
    header('Location: home.php');
} else {
    header('Location: landing.php');
}
exit;
