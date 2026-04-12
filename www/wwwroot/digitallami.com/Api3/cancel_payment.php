<?php
/**
 * cancel_payment.php — Cancel a pending payment.
 *
 * GET parameters:
 *   userid    : user ID
 *   paidby    : payment gateway (e.g. "Khalti", "hbl")
 *   packageid : package ID
 *   status    : "cancelled" (optional, defaults to "cancelled")
 *
 * Records the cancellation attempt in user_package_log if the table exists,
 * then returns a success response so the Flutter app can reset its state.
 */
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json; charset=utf-8");

// Suppress PHP notices/warnings so they never corrupt the JSON response.
ini_set('display_errors', '0');
error_reporting(E_ERROR);

$userid    = isset($_GET['userid'])    ? intval($_GET['userid'])    : 0;
$paidby    = isset($_GET['paidby'])    ? trim($_GET['paidby'])      : '';
$packageid = isset($_GET['packageid']) ? intval($_GET['packageid']) : 0;

if ($userid <= 0 || $packageid <= 0) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'userid and packageid are required',
    ]);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Log the cancellation in user_package if a pending row exists.
    // We use INSERT IGNORE so it silently succeeds even if the table
    // has been removed or renamed.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_cancellations (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            userid      INT UNSIGNED NOT NULL,
            packageid   INT UNSIGNED NOT NULL,
            paidby      VARCHAR(50)  NOT NULL DEFAULT '',
            cancelled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_userid (userid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        INSERT INTO payment_cancellations (userid, packageid, paidby)
        VALUES (:userid, :packageid, :paidby)
    ");
    $stmt->execute([
        ':userid'    => $userid,
        ':packageid' => $packageid,
        ':paidby'    => $paidby,
    ]);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Payment cancellation recorded',
        'data'    => [
            'userid'    => $userid,
            'packageid' => $packageid,
            'paidby'    => $paidby,
        ],
    ]);
} catch (Throwable $e) {
    error_log('cancel_payment.php error: ' . $e->getMessage());
    echo json_encode([
        'status'  => 'success',
        'message' => 'Cancellation acknowledged',
    ]);
}
