<?php
/**
 * hbl/index.php — HBL payment page.
 *
 * The Flutter app opens this URL in a WebView with query parameters:
 *   input_amount : Amount in NPR
 *   userid       : User ID
 *   packageid    : Package ID to activate on success
 *   paidby       : "hbl"
 *
 * On payment completion, HBL's gateway redirects to a success/cancel URL.
 * The Flutter WebView _handleUrlChange detects "success.php" or
 * "status=cancelled" in the redirected URL.
 *
 * Configuration via .env:
 *   HBL_MERCHANT_ID      : HBL-assigned merchant ID
 *   HBL_SECRET_KEY       : HBL HMAC secret key
 *   HBL_PAYMENT_URL      : HBL gateway URL (sandbox or production)
 *   HBL_SUCCESS_URL      : Return URL on success (default: .../hbl/success.php)
 *   HBL_FAILURE_URL      : Return URL on failure (default: .../hbl/failed.php)
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/html; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ERROR);

$amount    = isset($_GET['input_amount']) ? floatval($_GET['input_amount']) : 0;
$userid    = isset($_GET['userid'])       ? intval($_GET['userid'])         : 0;
$packageid = isset($_GET['packageid'])    ? intval($_GET['packageid'])      : 0;
$paidby    = isset($_GET['paidby'])       ? trim($_GET['paidby'])           : 'hbl';

$hblMerchantId  = getenv('HBL_MERCHANT_ID')  ?: '';
$hblSecretKey   = getenv('HBL_SECRET_KEY')   ?: '';
$hblPaymentUrl  = getenv('HBL_PAYMENT_URL')  ?: 'https://hbl.com.np/payment'; // sandbox
$successUrl     = getenv('HBL_SUCCESS_URL')
    ?: rtrim(APP_PUBLIC_BASE_URL, '/') . '/hbl/success.php';
$failureUrl     = getenv('HBL_FAILURE_URL')
    ?: rtrim(APP_PUBLIC_BASE_URL, '/') . '/hbl/failed.php';

if (!$hblMerchantId || !$hblSecretKey) {
    ?><!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>HBL Payment</title></head>
<body style="font-family:sans-serif;text-align:center;padding:40px;">
<h2>HBL payment is not configured.</h2>
<p>Please contact support.</p>
</body></html><?php
    exit;
}

if ($amount <= 0 || $userid <= 0 || $packageid <= 0) {
    ?><!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>HBL Payment</title></head>
<body style="font-family:sans-serif;text-align:center;padding:40px;">
<h2>Invalid payment parameters.</h2>
</body></html><?php
    exit;
}

$orderId  = 'MS_' . $userid . '_' . $packageid . '_' . time();
$amountFormatted = number_format($amount, 2, '.', '');

// Build HMAC signature per HBL specification
// Adjust the field order to match the actual HBL documentation for your integration.
$signatureData = $orderId . $amountFormatted . $hblMerchantId;
$signature = hash_hmac('sha256', $signatureData, $hblSecretKey);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HBL Card Payment</title>
<style>
  body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
       min-height:100vh;margin:0;background:#f0f0f0;}
  .card{background:#fff;border-radius:12px;padding:32px;max-width:420px;width:90%;
        box-shadow:0 2px 16px rgba(0,0,0,.12);}
  h2{margin:0 0 24px;text-align:center;color:#333;}
  .info{display:flex;justify-content:space-between;margin-bottom:12px;font-size:15px;}
  .info span:first-child{color:#666;}
  .info strong{color:#222;}
  button{width:100%;padding:14px;background:#00539b;color:#fff;border:none;
         border-radius:8px;font-size:16px;cursor:pointer;margin-top:16px;}
  button:hover{background:#003f7a;}
</style>
</head>
<body>
<div class="card">
  <h2>HBL Card Payment</h2>
  <div class="info"><span>Amount</span><strong>NPR <?= htmlspecialchars($amountFormatted) ?></strong></div>
  <div class="info"><span>Order ID</span><strong><?= htmlspecialchars($orderId) ?></strong></div>
  <form method="POST" action="<?= htmlspecialchars($hblPaymentUrl) ?>">
    <input type="hidden" name="merchantId"  value="<?= htmlspecialchars($hblMerchantId) ?>">
    <input type="hidden" name="amount"      value="<?= htmlspecialchars($amountFormatted) ?>">
    <input type="hidden" name="orderId"     value="<?= htmlspecialchars($orderId) ?>">
    <input type="hidden" name="successUrl"  value="<?= htmlspecialchars($successUrl) ?>">
    <input type="hidden" name="failureUrl"  value="<?= htmlspecialchars($failureUrl) ?>">
    <input type="hidden" name="cancelUrl"   value="<?= htmlspecialchars($failureUrl) ?>">
    <input type="hidden" name="signature"   value="<?= htmlspecialchars($signature) ?>">
    <input type="hidden" name="customerId"  value="<?= htmlspecialchars((string)$userid) ?>">
    <input type="hidden" name="packageId"   value="<?= htmlspecialchars((string)$packageid) ?>">
    <button type="submit">Pay with HBL Card</button>
  </form>
</div>
</body>
</html>
