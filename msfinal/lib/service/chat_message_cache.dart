import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// In-memory message cache that is backed by SharedPreferences.
///
/// Call [init] once at app startup (before [runApp]) so the
/// SharedPreferences instance is available for synchronous reads.
/// After that, [preloadRoom] and [getMessages] both execute without
/// awaiting any I/O, which lets [ChatDetailScreen] and [AdminChatScreen]
/// populate their message list on the very first frame instead of showing
/// a skeleton loader.
class ChatMessageCache {
  ChatMessageCache._internal();
  static final ChatMessageCache instance = ChatMessageCache._internal();

  SharedPreferences? _prefs;
  final Map<String, List<Map<String, dynamic>>> _cache = {};

  static const int _maxCachedMessages = 30;

  /// Initialize with a [SharedPreferences] instance.
  /// Should be called once from [main] before [runApp].
  Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
  }

  /// Load a room's messages from SharedPreferences into the in-memory cache.
  ///
  /// This is **synchronous** (no awaits) because [_prefs] was pre-initialised
  /// by [init].  If [init] was never called, the method is a no-op.
  void preloadRoom(String chatRoomId) {
    if (_cache.containsKey(chatRoomId)) return;
    final prefs = _prefs;
    if (prefs == null) return;
    try {
      final raw = prefs.getString('chat_msgs_$chatRoomId');
      if (raw == null) return;
      final decoded = jsonDecode(raw) as List<dynamic>;
      _cache[chatRoomId] = decoded.map((item) {
        final m = Map<String, dynamic>.from(item as Map);
        if (m['timestamp'] is String) {
          final dt = DateTime.tryParse(m['timestamp'] as String);
          if (dt != null) m['timestamp'] = dt.toLocal();
        }
        return m;
      }).toList();
    } catch (e) {
      debugPrint('ChatMessageCache.preloadRoom error: $e');
    }
  }

  /// Return the in-memory messages for [chatRoomId], or `null` if not loaded.
  List<Map<String, dynamic>>? getMessages(String chatRoomId) =>
      _cache[chatRoomId];

  /// Update the in-memory cache and persist to SharedPreferences.
  ///
  /// Only the most-recent [_maxCachedMessages] messages are persisted so
  /// the SharedPreferences entry stays small.
  void setMessages(String chatRoomId, List<Map<String, dynamic>> messages) {
    _cache[chatRoomId] = List.from(messages);
    _persistAsync(chatRoomId, messages);
  }

  /// Merge [newMessages] into the existing cache for [chatRoomId].
  void mergeMessages(String chatRoomId, List<Map<String, dynamic>> newMessages) {
    final existing = _cache[chatRoomId] ?? [];
    final existingIds = existing.map((m) => m['messageId']?.toString()).toSet();
    final merged = [
      ...existing,
      ...newMessages.where((m) => !existingIds.contains(m['messageId']?.toString())),
    ];
    setMessages(chatRoomId, merged);
  }

  /// Clear a room's in-memory cache (does **not** delete from SharedPreferences).
  void evictRoom(String chatRoomId) => _cache.remove(chatRoomId);

  // ── private ──────────────────────────────────────────────────────────────

  Map<String, dynamic> _serializeMessage(Map<String, dynamic> msg) {
    final m = Map<String, dynamic>.from(msg);
    if (m['timestamp'] is DateTime) {
      m['timestamp'] = (m['timestamp'] as DateTime).toIso8601String();
    }
    return m;
  }

  void _persistAsync(String chatRoomId, List<Map<String, dynamic>> messages) {
    final prefs = _prefs;
    if (prefs == null) return;
    Future.microtask(() async {
      try {
        final toSave = messages.length > _maxCachedMessages
            ? messages.sublist(messages.length - _maxCachedMessages)
            : messages;
        final encoded = jsonEncode(toSave.map(_serializeMessage).toList());
        await prefs.setString('chat_msgs_$chatRoomId', encoded);
      } catch (e) {
        debugPrint('ChatMessageCache._persistAsync error: $e');
      }
    });
  }
}
