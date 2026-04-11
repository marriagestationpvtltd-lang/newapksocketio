<?php
/**
 * User authentication helper for Marriage Station.
 * Handles session management, login, logout and access control
 * for regular users (NOT admin users).
 */

/**
 * Get a PDO database connection.
 */
function getUserPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        require_once __DIR__ . '/../config/db.php';
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Marriage Station user DB error: ' . $e->getMessage());
            die('Database connection error. Please try again later.');
        }
    }
    return $pdo;
}

/**
 * Start the user session with a dedicated session name.
 */
function startUserSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('user_session');
        session_start();
    }
}

// Auto-start session when this file is included
startUserSession();

/**
 * Check whether a user is currently logged in.
 */
function isUserLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Return the current user's data stored in session, or null.
 */
function getCurrentUser(): ?array
{
    if (!isUserLoggedIn()) {
        return null;
    }
    return [
        'user_id'         => $_SESSION['user_id']         ?? 0,
        'firstName'       => $_SESSION['firstName']       ?? '',
        'lastName'        => $_SESSION['lastName']        ?? '',
        'email'           => $_SESSION['email']           ?? '',
        'profile_picture' => $_SESSION['profile_picture'] ?? '',
        'gender'          => $_SESSION['gender']          ?? '',
        'usertype'        => $_SESSION['usertype']        ?? '',
        'isVerified'      => $_SESSION['isVerified']      ?? 0,
        'pageno'          => $_SESSION['pageno']          ?? 0,
    ];
}

/**
 * Authenticate a user against the users table.
 *
 * @param  string $email    User email address
 * @param  string $password Plain-text password to verify
 * @return array  ['success' => bool, 'message' => string]
 */
function loginUser(string $email, string $password): array
{
    try {
        $pdo  = getUserPDO();
        $stmt = $pdo->prepare(
            'SELECT id, firstName, lastName, email, password, profile_picture,
                    gender, usertype, isVerified, pageno
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        // Store user data in session
        $_SESSION['user_id']         = (int) $user['id'];
        $_SESSION['firstName']       = $user['firstName'];
        $_SESSION['lastName']        = $user['lastName'];
        $_SESSION['email']           = $user['email'];
        $_SESSION['profile_picture'] = $user['profile_picture'];
        $_SESSION['gender']          = $user['gender'];
        $_SESSION['usertype']        = $user['usertype'];
        $_SESSION['isVerified']      = $user['isVerified'];
        $_SESSION['pageno']          = $user['pageno'];
        $_SESSION['last_activity']   = time();

        return ['success' => true, 'message' => 'Login successful.'];
    } catch (PDOException $e) {
        error_log('Marriage Station login error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'A server error occurred. Please try again later.'];
    }
}

/**
 * Destroy the user session and redirect to login page.
 */
function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header('Location: login.php');
    exit;
}

/**
 * Guard: redirect to login page if user is not authenticated.
 */
function requireUserLogin(): void
{
    if (!isUserLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
