<?php
// This file has been disabled for security reasons.
http_response_code(403);
header('Content-Type: application/json');
echo json_encode(['error' => 'Forbidden']);
exit;
?>