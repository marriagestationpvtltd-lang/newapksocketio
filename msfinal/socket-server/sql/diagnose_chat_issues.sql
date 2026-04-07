-- ============================================================
-- Diagnostic Script for Chat Message Issues
-- This script helps identify problematic messages that may
-- cause chat screens to fail loading.
-- ============================================================

-- Check for messages with invalid JSON in replied_to field
-- These will cause JSON parsing errors
SELECT
    message_id,
    chat_room_id,
    sender_id,
    receiver_id,
    message_type,
    replied_to,
    created_at
FROM chat_messages
WHERE replied_to IS NOT NULL
  AND JSON_VALID(replied_to) = 0
ORDER BY created_at DESC;

-- Check for messages in chat rooms for users 1023 and 1016
-- to identify specific messages that might be causing issues
SELECT
    cm.message_id,
    cm.chat_room_id,
    cm.sender_id,
    cm.receiver_id,
    cm.message_type,
    cm.message,
    cm.replied_to,
    cm.created_at,
    CASE
        WHEN cm.replied_to IS NOT NULL AND JSON_VALID(cm.replied_to) = 0
        THEN 'Invalid replied_to JSON'
        ELSE 'OK'
    END AS status
FROM chat_messages cm
JOIN chat_rooms cr ON cm.chat_room_id = cr.id
WHERE JSON_CONTAINS(cr.participants, '"1023"')
   OR JSON_CONTAINS(cr.participants, '"1016"')
ORDER BY cm.created_at DESC
LIMIT 100;

-- Check for messages with call type that might have invalid JSON
SELECT
    message_id,
    chat_room_id,
    sender_id,
    receiver_id,
    message,
    created_at
FROM chat_messages
WHERE message_type = 'call'
  AND (message IS NULL OR message = '' OR NOT JSON_VALID(message))
ORDER BY created_at DESC
LIMIT 50;

-- Check for messages with profile_card type that might have invalid JSON
SELECT
    message_id,
    chat_room_id,
    sender_id,
    receiver_id,
    message,
    created_at
FROM chat_messages
WHERE message_type = 'profile_card'
  AND (message IS NULL OR message = '' OR NOT JSON_VALID(message))
ORDER BY created_at DESC
LIMIT 50;

-- Count messages by type in rooms with users 1023 and 1016
SELECT
    cm.message_type,
    COUNT(*) as message_count,
    SUM(CASE WHEN cm.replied_to IS NOT NULL THEN 1 ELSE 0 END) as with_reply,
    SUM(CASE WHEN cm.replied_to IS NOT NULL AND JSON_VALID(cm.replied_to) = 0 THEN 1 ELSE 0 END) as invalid_reply_json
FROM chat_messages cm
JOIN chat_rooms cr ON cm.chat_room_id = cr.id
WHERE JSON_CONTAINS(cr.participants, '"1023"')
   OR JSON_CONTAINS(cr.participants, '"1016"')
GROUP BY cm.message_type
ORDER BY message_count DESC;

-- ============================================================
-- OPTIONAL: Fix corrupted replied_to JSON
-- Run this ONLY if you want to set invalid JSON to NULL
-- This will allow messages to load but lose the reply reference
-- ============================================================

-- UNCOMMENT TO RUN:
-- UPDATE chat_messages
-- SET replied_to = NULL
-- WHERE replied_to IS NOT NULL
--   AND JSON_VALID(replied_to) = 0;

-- ============================================================
-- OPTIONAL: Fix corrupted call message JSON
-- Run this ONLY if you want to fix invalid call messages
-- ============================================================

-- UNCOMMENT TO RUN:
-- UPDATE chat_messages
-- SET message = JSON_OBJECT('callType', 'audio', 'callStatus', 'unknown', 'callDuration', 0, 'label', 'Call')
-- WHERE message_type = 'call'
--   AND (message IS NULL OR message = '' OR NOT JSON_VALID(message));

-- ============================================================
-- OPTIONAL: Fix corrupted profile_card message JSON
-- Run this ONLY if you want to fix invalid profile_card messages
-- ============================================================

-- UNCOMMENT TO RUN:
-- UPDATE chat_messages
-- SET message = '{}'
-- WHERE message_type = 'profile_card'
--   AND (message IS NULL OR message = '' OR NOT JSON_VALID(message));
