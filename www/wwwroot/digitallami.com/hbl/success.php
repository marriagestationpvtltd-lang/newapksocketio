<?php
/**
 * hbl/success.php — HBL payment success callback.
 *
 * HBL redirects here after a successful card payment.
 * The URL contains "success.php" so the Flutter WebView _handleUrlChange
 * listener triggers the success flow automatically.
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/html; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ERROR);

$orderId       = isset($_REQUEST['orderId'])       ? trim($_REQUEST['orderId'])       : '';
$transactionId = isset($_REQUEST['transactionId']) ? trim($_REQUEST['transactionId']) : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Successful</title>
<style>
  body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
       min-height:100vh;margin:0;background:#f5f5f5;}
  .card{background:#fff;border-radius:16px;padding:40px;text-align:center;
        box-shadow:0 2px 16px rgba(0,0,0,.1);max-width:400px;width:90%;}
  .icon{font-size:64px;margin-bottom:16px;}
</style>
</head>
<body>
<div class="card">
  <div class="icon">✅</div>
  <h1>Payment Successful</h1>
  <p>Your package has been activated. Please return to the app.</p>
</div>
</body>
</html>
