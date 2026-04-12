<?php
/**
 * shared/admin_auth.php
 *
 * Reusable admin JWT validation helper.
 *
 * Usage:
 *   require_once __DIR__ . '/../shared/admin_auth.php';
 *   admin_auth_guard();   // exits 401 if token invalid/missing
 *
 * The Authorization header may contain the raw token or "Bearer <token>".
 * Both forms are accepted.
 */

if (!function_exists('admin_auth_guard')) {
    function admin_auth_guard(): void {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (empty($authHeader)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        // Strip optional "Bearer " prefix (case-insensitive)
        $rawToken = preg_replace('/^Bearer\s+/i', '', trim($authHeader));

        $tokenSecret = getenv('ADMIN_TOKEN_SECRET') ?: 'CHANGE_THIS_SECRET_KEY';
        $tokenParts  = explode('.', $rawToken, 2);
        $tokenValid  = false;

        if (count($tokenParts) === 2) {
            $payloadJson = base64_decode($tokenParts[0]);
            $expected    = hash_hmac('sha256', $payloadJson, $tokenSecret);
            if (hash_equals($expected, $tokenParts[1])) {
                $payload = json_decode($payloadJson, true);
                if (is_array($payload) && isset($payload['exp']) && $payload['exp'] >= time()) {
                    $tokenValid = true;
                }
            }
        }

        if (!$tokenValid) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    }
}
