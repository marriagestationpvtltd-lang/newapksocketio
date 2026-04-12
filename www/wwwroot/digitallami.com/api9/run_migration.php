<?php
/**
 * One-time setup helper – creates the `admins` table and seeds the
 * default admin account when it does not already exist.
 *
 * HOW TO USE
 * ----------
 * 1. Open  http://<your-server>/digitallami/api9/run_migration.php?token=<SETUP_TOKEN>
 *    where <SETUP_TOKEN> is the value you set for SETUP_TOKEN in your .env file.
 * 2. After the migration succeeds, DELETE this file from the server.
 *
 * SECURITY
 * --------
 * The endpoint is protected by a one-time token defined in the .env file.
 * Without the correct token the script returns 403 and does nothing.
 * Always remove this file from production once the migration has run.
 */

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=UTF-8');

// ── Token guard ───────────────────────────────────────────────────────────────
$expectedToken = getenv('SETUP_TOKEN') ?: '';
$providedToken = $_GET['token'] ?? '';

if ($expectedToken === '' || $providedToken !== $expectedToken) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: invalid or missing token']);
    exit;
}

// ── Migration SQL ─────────────────────────────────────────────────────────────
$migrationFile = __DIR__ . '/../migrations/001_create_admins_table.sql';

if (!file_exists($migrationFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Migration file not found']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Split the SQL file on semicolons so we can execute each statement.
    $sql        = file_get_contents($migrationFile);
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => $s !== '' && $s[0] !== '-' && ltrim($s) !== ''
    );

    $executed = 0;
    foreach ($statements as $statement) {
        if (trim($statement) === '') continue;
        $pdo->exec($statement);
        $executed++;
    }

    // Verify the table now exists.
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `admins`")->fetchColumn();

    echo json_encode([
        'success'    => true,
        'message'    => 'Migration applied successfully. Delete this file now.',
        'statements' => $executed,
        'admins_count' => $count,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('run_migration.php PDO error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
