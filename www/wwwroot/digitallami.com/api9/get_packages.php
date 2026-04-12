<?php
require_once __DIR__ . '/../config/db.php';
header("Content-Type: application/json");

// ================= DB CONFIG =================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("
        SELECT 
            id,
            name,
            duration,
            description,
            price
        FROM packagelist
        ORDER BY id DESC
    ");

    $stmt->execute();
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ FORMAT RESPONSE
    foreach ($packages as &$pkg) {

        // duration → "90 Month"
        if (is_numeric($pkg['duration'])) {
            $pkg['duration'] = $pkg['duration'] . ' Month';
        }

        // price → "Rs 50.00"
        if (stripos($pkg['price'], 'Rs') === false) {
            $pkg['price'] = 'Rs ' . number_format((float)$pkg['price'], 2);
        }
    }

    echo json_encode([
        'success' => true,
        'count' => count($packages),
        'data' => $packages
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}
