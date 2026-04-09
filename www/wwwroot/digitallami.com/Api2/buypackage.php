<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../shared/activity_logger.php';

try {
    $dbHost = DB_HOST;
    $dbName = DB_NAME;
    $dbUser = DB_USER;
    $dbPass = DB_PASS;

    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get input data
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['userid'], $data['packageid'], $data['paidby'])) {
        echo json_encode([
            "success" => false,
            "message" => "Missing required fields."
        ]);
        exit;
    }

    $userid    = isset($data['userid'])    ? intval($data['userid'])    : 0;
    $packageid = isset($data['packageid']) ? intval($data['packageid']) : 0;
    $paidby    = isset($data['paidby'])    ? strtolower(trim($data['paidby'])) : '';

    // Validate numeric IDs
    if ($userid <= 0 || $packageid <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid userid or packageid."]);
        exit;
    }

    // Whitelist payment methods to prevent unexpected values in DB
    $allowedPaidBy = ['esewa', 'khalti', 'stripe', 'paypal', 'bank', 'card', 'cash', 'other'];
    if (!in_array($paidby, $allowedPaidBy, true)) {
        echo json_encode(["success" => false, "message" => "Invalid payment method."]);
        exit;
    }

    // Get package duration from packagelist table
    $stmt = $pdo->prepare("SELECT duration FROM packagelist WHERE id = :packageid");
    $stmt->execute(['packageid' => $packageid]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$package) {
        echo json_encode([
            "success" => false,
            "message" => "Package not found."
        ]);
        exit;
    }

    $durationMonths = (int)$package['duration'];
    $purchasedate = date('Y-m-d H:i:s');
    $expiredate = date('Y-m-d H:i:s', strtotime("+$durationMonths months"));

    // Insert into user_package
    $stmt = $pdo->prepare("INSERT INTO user_package (userid, packageid, purchasedate, expiredate, paidby)
                           VALUES (:userid, :packageid, :purchasedate, :expiredate, :paidby)");
    $stmt->execute([
        'userid' => $userid,
        'packageid' => $packageid,
        'purchasedate' => $purchasedate,
        'expiredate' => $expiredate,
        'paidby' => $paidby
    ]);

    // Log package purchase activity
    try {
        $pkgStmt = $pdo->prepare("SELECT p.name AS pkg_name, CONCAT(u.firstName,' ',u.lastName) AS user_name
                                  FROM users u, packagelist p
                                  WHERE u.id = :uid AND p.id = :pid LIMIT 1");
        $pkgStmt->execute([':uid' => $userid, ':pid' => $packageid]);
        $pkgRow = $pkgStmt->fetch(PDO::FETCH_ASSOC);
        $uName   = $pkgRow ? $pkgRow['user_name'] : "User $userid";
        $pkgName = $pkgRow ? $pkgRow['pkg_name']  : "Package $packageid";
        logActivity($pdo, [
            'user_id'       => $userid,
            'user_name'     => $uName,
            'activity_type' => 'package_bought',
            'description'   => "$uName le \"$pkgName\" package kina garyo ($paidby bata)",
        ]);
    } catch (Exception $e) {
        // Never let activity logging break the response
    }

    echo json_encode([
        "success" => true,
        "message" => "Package purchased successfully.",
        "data" => [
            "userid" => $userid,
            "packageid" => $packageid,
            "purchasedate" => $purchasedate,
            "expiredate" => $expiredate,
            "paidby" => $paidby
        ]
    ]);

} catch (PDOException $e) {
    error_log("buypackage.php PDO error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Database error. Please try again."
    ]);
}
?>
