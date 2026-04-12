<?php
/**
 * khalti_success.php — Khalti payment return / callback page.
 *
 * Khalti redirects back here after a payment attempt (success or cancel).
 * The Flutter WebView URL-change listener detects this URL:
 *   • "khalti_success.php" contains "success.php"  → triggers success handler
 *   • "status=User cancelled" in query params       → triggers cancel handler
 *     (checked first by _isCancelUrl in Paymentscreen.dart)
 *
 * Query parameters provided by Khalti:
 *   pidx      : Payment ID
 *   status    : "Completed" | "User cancelled" | ...
 *   txnId     : Transaction ID (on success)
 *   amount    : Amount in paisa
 *   mobile    : Payer's phone number
 *   ...
 */
require_once __DIR__ . '/config/db.php';
header('Content-Type: text/html; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ERROR);

$pidx   = isset($_GET['pidx'])   ? trim($_GET['pidx'])   : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Update payment record status if we have a pidx
if ($pidx !== '') {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // 'completed' → success; anything with 'cancel' → cancelled;
        // empty or unknown → treat as failed/incomplete
        $dbStatus = 'failed';
        if (strtolower($status) === 'completed') {
            $dbStatus = 'completed';
        } elseif ($status !== '' && stripos($status, 'cancel') !== false) {
            $dbStatus = 'cancelled';
        }

        $stmt = $pdo->prepare(
            "UPDATE khalti_payments SET status = :s WHERE pidx = :p"
        );
        $stmt->execute([':s' => $dbStatus, ':p' => $pidx]);
    } catch (Throwable $e) {
        error_log('khalti_success.php DB error: ' . $e->getMessage());
    }
}

// This page is opened inside the Flutter WebView.
// The WebView listener watches URL changes; this response is only shown if
// the WebView fails to intercept the navigation (e.g. on web/desktop).
$isSuccess = (strtolower($status) === 'completed');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isSuccess ? 'Payment Successful' : 'Payment Cancelled' ?></title>
<style>
  body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;
       justify-content:center;min-height:100vh;margin:0;background:#f5f5f5;}
  .card{background:#fff;border-radius:16px;padding:40px;text-align:center;
        box-shadow:0 2px 16px rgba(0,0,0,.1);max-width:400px;width:90%;}
  .icon{font-size:64px;margin-bottom:16px;}
  h1{margin:0 0 8px;font-size:22px;}
  p{color:#666;margin:0;}
</style>
</head>
<body>
<div class="card">
  <div class="icon"><?= $isSuccess ? '✅' : '❌' ?></div>
  <h1><?= $isSuccess ? 'Payment Successful' : 'Payment Cancelled' ?></h1>
  <p><?= $isSuccess
    ? 'Your package has been activated. Please return to the app.'
    : 'Your payment was not completed.' ?></p>
</div>
</body>
</html>
