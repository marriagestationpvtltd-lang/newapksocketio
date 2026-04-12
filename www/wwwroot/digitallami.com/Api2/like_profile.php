<?php
/**
 * like_profile.php — Thin wrapper around like_action.php.
 *
 * Accepts the same POST/GET parameters as like_action.php:
 *   sender_id   : ID of the user performing the like/unlike
 *   receiver_id : ID of the user being liked/unliked
 *   action      : "add" | "delete"
 *
 * Returns the same JSON response as like_action.php.
 */
require_once __DIR__ . '/like_action.php';
