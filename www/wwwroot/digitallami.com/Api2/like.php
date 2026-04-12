<?php
/**
 * like.php — Add or remove a like between two users.
 *
 * POST parameters:
 *   myid   : ID of the user performing the action
 *   userid : ID of the profile being liked/unliked
 *   like   : "1" to like, "0" to unlike
 *
 * Delegates to like_action.php after normalising the parameter names.
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ERROR);

// Normalise parameters from like.php convention → like_action.php convention
$_REQUEST['sender_id']   = $_POST['myid']   ?? $_GET['myid']   ?? '';
$_REQUEST['receiver_id'] = $_POST['userid']  ?? $_GET['userid']  ?? '';

$likeValue = $_POST['like'] ?? $_GET['like'] ?? null;
if ($likeValue !== null) {
    $_REQUEST['action'] = ($likeValue === '1' || $likeValue === 1 || $likeValue === true)
        ? 'add'
        : 'delete';
} else {
    $_REQUEST['action'] = '';
}

require __DIR__ . '/like_action.php';
