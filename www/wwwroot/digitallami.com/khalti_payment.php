<?php
/**
 * khalti_payment.php — Initiate a Khalti payment and return the payment URL.
 *
 * POST body (JSON):
 *   amount    : total amount in NPR (integer, will be converted to paisa ×100)
 *   userid    : user ID
 *   packageid : package ID to activate after successful payment
 *   paidby    : payment gateway label (e.g. "Khalti")
 *
 * Returns:
 *   { "success": true,  "payment_url": "https://pay.khalti.com/...", "pidx": "..." }
 *   { "success": false, "message": "..." }
 *
 * Khalti credentials are read from the .env file:
 *   KHALTI_SECRET_KEY = live_secret_key_...  (or test_secret_key_... for sandbox)
 *   KHALTI_RETURN_URL = https://react.marriagestation.com.np/khalti_callback.php
 *   KHALTI_WEBSITE_URL = https://react.marriagestation.com.np
 */
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ERROR);

function khalti_respond(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// ── Credentials from .env ──────────────────────────────────────────────────
$khaltiSecretKey = getenv('KHALTI_SECRET_KEY') ?: '';
// The return URL MUST contain "success.php" in its path so the Flutter WebView
// URL-change listener recognises a successful redirect.  The cancel case is
// handled by the `status=User cancelled` query parameter which the listener
// already checks for (see Paymentscreen.dart _isCancelUrl).
$returnUrl       = getenv('KHALTI_RETURN_URL')
    ?: (rtrim(APP_PUBLIC_BASE_URL, '/') . '/khalti_success.php');
$websiteUrl      = getenv('KHALTI_WEBSITE_URL') ?: APP_PUBLIC_BASE_URL;

if (empty($khaltiSecretKey)) {
    khalti_respond(false, 'Khalti is not configured on this server. Please contact support.');
}

// ── Input validation ──────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    khalti_respond(false, 'Invalid JSON request body.');
}

$amount    = isset($body['amount'])    ? intval($body['amount'])    : 0;
$userid    = isset($body['userid'])    ? intval($body['userid'])    : 0;
$packageid = isset($body['packageid']) ? intval($body['packageid']) : 0;

if ($amount <= 0 || $userid <= 0 || $packageid <= 0) {
    khalti_respond(false, 'amount, userid, and packageid are required.');
}

// Khalti expects amount in paisa (1 NPR = 100 paisa)
$amountPaisa = $amount * 100;

// ── Lookup package name for the payment title ──────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->prepare('SELECT name FROM packagelist WHERE id = ? LIMIT 1');
    $stmt->execute([$packageid]);
    $pkg = $stmt->fetch(PDO::FETCH_ASSOC);
    $packageName = $pkg ? $pkg['name'] : 'Package #' . $packageid;
} catch (Throwable $e) {
    error_log('khalti_payment.php DB error: ' . $e->getMessage());
    $packageName = 'Package #' . $packageid;
}

// ── Call Khalti Initiate Payment API ─────────────────────────────────────
$payload = json_encode([
    'return_url'   => $returnUrl,
    'website_url'  => $websiteUrl,
    'amount'       => $amountPaisa,
    'purchase_order_id' => 'PKG_' . $packageid . '_USER_' . $userid . '_' . time(),
    'purchase_order_name' => $packageName,
    'customer_info' => [
        'name' => 'User ' . $userid,
    ],
]);

$ch = curl_init('https://a.khalti.com/api/v2/epayment/initiate/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Key ' . $khaltiSecretKey,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$responseBody = curl_exec($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError    = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('khalti_payment.php cURL error: ' . $curlError);
    khalti_respond(false, 'Network error contacting Khalti. Please try again.');
}

$data = json_decode($responseBody, true);

if ($httpCode === 200 && isset($data['payment_url'])) {
    // Store the pidx so we can verify after redirect
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS khalti_payments (
                id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                userid          INT UNSIGNED NOT NULL,
                packageid       INT UNSIGNED NOT NULL,
                pidx            VARCHAR(100) NOT NULL,
                amount_paisa    INT UNSIGNED NOT NULL,
                status          ENUM('initiated','completed','failed','cancelled')
                                    NOT NULL DEFAULT 'initiated',
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_pidx (pidx),
                INDEX idx_userid (userid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $ins = $pdo->prepare("
            INSERT INTO khalti_payments (userid, packageid, pidx, amount_paisa)
            VALUES (:u, :p, :pidx, :amt)
        ");
        $ins->execute([
            ':u'    => $userid,
            ':p'    => $packageid,
            ':pidx' => $data['pidx'] ?? '',
            ':amt'  => $amountPaisa,
        ]);
    } catch (Throwable $e) {
        // Non-fatal: log and continue
        error_log('khalti_payment.php pidx store error: ' . $e->getMessage());
    }

    khalti_respond(true, 'Payment initiated', [
        'payment_url' => $data['payment_url'],
        'pidx'        => $data['pidx'] ?? '',
    ]);
} else {
    $errorMsg = $data['detail'] ?? $data['message'] ?? 'Failed to initiate payment';
    error_log('khalti_payment.php Khalti error (' . $httpCode . '): ' . $responseBody);
    khalti_respond(false, 'Khalti error: ' . $errorMsg);
}
