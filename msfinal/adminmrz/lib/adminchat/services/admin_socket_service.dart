import 'dart:async';

import 'package:socket_io_client/socket_io_client.dart' as IO;

/// URL of the Node.js Socket.IO server.
/// ⚠️  Replace this with your actual deployed server URL before building.
/// Example: 'https://socket.yourserver.com:3001'
const String kAdminSocketUrl = 'http://192.168.1.4:3001';

/// Admin user ID — always '1'.
const String kAdminUserId = '1';

/// Default timeout for acknowledgement-based Socket.IO calls.
const Duration kAdminSocketTimeout = Duration(seconds: 15);

/// ---------------------------------------------------------------------------
/// AdminSocketService — singleton that manages the Socket.IO connection for
/// the admin panel (connects as userId = '1') and exposes streams for all
/// real-time chat events.
/// ---------------------------------------------------------------------------
class AdminSocketService {
  static final AdminSocketService _instance = AdminSocketService._internal();
  factory AdminSocketService() => _instance;
  AdminSocketService._internal();

  IO.Socket? _socket;

  // ── Stream controllers ────────────────────────────────────────────────────

  final _newMessageCtrl =
      StreamController<Map<String, dynamic>>.broadcast();
  final _messageEditedCtrl =
      StreamController<Map<String, dynamic>>.broadcast();
  final _messageDeletedCtrl =
      StreamController<Map<String, dynamic>>.broadcast();
  final _messageUnsentCtrl =
      StreamController<Map<String, dynamic>>.broadcast();
  final _messageLikedCtrl =
      StreamController<Map<String, dynamic>>.broadcast();
  final _messagesReadCtrl =
      StreamController<Map<String, dynamic>>.broadcast();
  final _typingStartCtrl =
      StreamController<Map<String, dynamic>>.broadcast();
  final _typingStopCtrl =
      StreamController<Map<String, dynamic>>.broadcast();
  final _userStatusCtrl =
      StreamController<Map<String, dynamic>>.broadcast();
  final _connectionCtrl = StreamController<bool>.broadcast();

  // ── Public streams ────────────────────────────────────────────────────────

  Stream<Map<String, dynamic>> get onNewMessage => _newMessageCtrl.stream;
  Stream<Map<String, dynamic>> get onMessageEdited =>
      _messageEditedCtrl.stream;
  Stream<Map<String, dynamic>> get onMessageDeleted =>
      _messageDeletedCtrl.stream;
  Stream<Map<String, dynamic>> get onMessageUnsent =>
      _messageUnsentCtrl.stream;
  Stream<Map<String, dynamic>> get onMessageLiked =>
      _messageLikedCtrl.stream;
  Stream<Map<String, dynamic>> get onMessagesRead =>
      _messagesReadCtrl.stream;
  Stream<Map<String, dynamic>> get onTypingStart => _typingStartCtrl.stream;
  Stream<Map<String, dynamic>> get onTypingStop => _typingStopCtrl.stream;
  Stream<Map<String, dynamic>> get onUserStatusChange =>
      _userStatusCtrl.stream;
  Stream<bool> get onConnectionChange => _connectionCtrl.stream;

  // ── State ─────────────────────────────────────────────────────────────────

  bool get isConnected => _socket?.connected ?? false;

  // ── Connect / Disconnect ──────────────────────────────────────────────────

  void connect() {
    if (_socket != null && _socket!.connected) return;

    _socket?.dispose();
    _socket = IO.io(
      kAdminSocketUrl,
      IO.OptionBuilder()
          .setTransports(['websocket', 'polling'])
          .setReconnectionDelay(2000)
          .setReconnectionDelayMax(10000)
          .setReconnectionAttempts(double.infinity as int)
          .enableReconnection()
          .disableAutoConnect()
          .build(),
    );

    _socket!.onConnect((_) {
      _connectionCtrl.add(true);
      // Authenticate as admin
      _socket!.emit('authenticate', {'userId': kAdminUserId});
    });

    _socket!.onDisconnect((_) {
      _connectionCtrl.add(false);
    });

    _socket!.on('new_message', (data) {
      if (data is Map) _newMessageCtrl.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('message_edited', (data) {
      if (data is Map)
        _messageEditedCtrl.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('message_deleted', (data) {
      if (data is Map)
        _messageDeletedCtrl.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('message_unsent', (data) {
      if (data is Map)
        _messageUnsentCtrl.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('message_liked', (data) {
      if (data is Map)
        _messageLikedCtrl.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('messages_read', (data) {
      if (data is Map)
        _messagesReadCtrl.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('typing_start', (data) {
      if (data is Map) _typingStartCtrl.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('typing_stop', (data) {
      if (data is Map) _typingStopCtrl.add(Map<String, dynamic>.from(data));
    });

    _socket!.on('user_status_change', (data) {
      if (data is Map) _userStatusCtrl.add(Map<String, dynamic>.from(data));
    });

    _socket!.connect();
  }

  void disconnect() {
    _socket?.disconnect();
  }

  // ── Room management ───────────────────────────────────────────────────────

  void joinRoom(String chatRoomId) {
    _socket?.emit('join_room', {'chatRoomId': chatRoomId});
  }

  void leaveRoom(String chatRoomId) {
    _socket?.emit('leave_room', {'chatRoomId': chatRoomId});
  }

  void setActiveChat(String chatRoomId, {bool isActive = true}) {
    _socket?.emit('set_active_chat', {
      'userId': kAdminUserId,
      'chatRoomId': isActive ? chatRoomId : null,
      'isActive': isActive,
    });
  }

  // ── Messaging ─────────────────────────────────────────────────────────────

  /// Send a message from admin to [receiverId].
  void sendMessage({
    required String chatRoomId,
    required String receiverId,
    required String message,
    required String messageType,
    required String messageId,
    Map<String, dynamic>? repliedTo,
    String? receiverName,
    String? receiverImage,
  }) {
    _socket?.emit('send_message', {
      'chatRoomId': chatRoomId,
      'senderId': kAdminUserId,
      'receiverId': receiverId,
      'message': message,
      'messageType': messageType,
      'messageId': messageId,
      if (repliedTo != null) 'repliedTo': repliedTo,
      'user1Name': 'Admin',
      'user2Name': receiverName ?? '',
      'user1Image': '',
      'user2Image': receiverImage ?? '',
    });
  }

  void editMessage({
    required String chatRoomId,
    required String messageId,
    required String newMessage,
  }) {
    _socket?.emit('edit_message', {
      'chatRoomId': chatRoomId,
      'messageId': messageId,
      'newMessage': newMessage,
    });
  }

  void deleteMessage({
    required String chatRoomId,
    required String messageId,
  }) {
    _socket?.emit('delete_message', {
      'chatRoomId': chatRoomId,
      'messageId': messageId,
      'userId': kAdminUserId,
      'deleteForEveryone': true,
    });
  }

  void unsendMessage({
    required String chatRoomId,
    required String messageId,
  }) {
    _socket?.emit('unsend_message', {
      'chatRoomId': chatRoomId,
      'messageId': messageId,
      'userId': kAdminUserId,
    });
  }

  void toggleLike({
    required String chatRoomId,
    required String messageId,
  }) {
    _socket?.emit('toggle_like', {
      'chatRoomId': chatRoomId,
      'messageId': messageId,
    });
  }

  void markRead(String chatRoomId) {
    _socket?.emit('mark_read', {
      'chatRoomId': chatRoomId,
      'userId': kAdminUserId,
    });
  }

  void sendTypingStart(String chatRoomId) {
    _socket?.emit('typing_start', {
      'chatRoomId': chatRoomId,
      'userId': kAdminUserId,
    });
  }

  void sendTypingStop(String chatRoomId) {
    _socket?.emit('typing_stop', {
      'chatRoomId': chatRoomId,
      'userId': kAdminUserId,
    });
  }

  // ── Request/Response (with ack) ───────────────────────────────────────────

  /// Load a page of messages for [chatRoomId].
  Future<Map<String, dynamic>> getMessages(
    String chatRoomId, {
    int page = 1,
    int limit = 30,
  }) {
    final completer = Completer<Map<String, dynamic>>();
    final timer = Timer(kAdminSocketTimeout, () {
      if (!completer.isCompleted) {
        completer.completeError(
            TimeoutException('getMessages timed out', kAdminSocketTimeout));
      }
    });

    _socket?.emitWithAck(
      'get_messages',
      {'chatRoomId': chatRoomId, 'page': page, 'limit': limit},
      ack: (data) {
        timer.cancel();
        if (!completer.isCompleted) {
          completer.complete(Map<String, dynamic>.from(data as Map? ?? {}));
        }
      },
    );

    return completer.future;
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  /// Compute the chat room ID shared between admin and [userId].
  static String chatRoomId(String userId) {
    final ids = [kAdminUserId, userId]..sort();
    return ids.join('_');
  }

  /// Parse a nullable timestamp string to [DateTime].
  static DateTime? parseTimestamp(dynamic ts) {
    if (ts == null) return null;
    if (ts is String) return DateTime.tryParse(ts);
    return null;
  }
}
