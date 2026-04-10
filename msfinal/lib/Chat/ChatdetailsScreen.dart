// lib/screens/ChatDetailScreen.dart
import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:ms2026/Chat/screen_state_manager.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:record/record.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart'
    if (dart.library.html) 'package:ms2026/utils/web_permission_stub.dart';

// dart:io is only available on native platforms
import 'dart:io' if (dart.library.html) 'package:ms2026/utils/web_io_stub.dart';

import '../service/socket_service.dart';
import '../Calling/videocall.dart';
import '../Calling/OutgoingCall.dart';
import '../Calling/call_history_model.dart';
import '../Calling/call_history_service.dart';
import '../Calling/callmanager.dart';
import '../Calling/incommingcall.dart';
import '../Calling/incomingvideocall.dart';
import '../otherprofile/otherprofileview.dart';
import '../otherenew/othernew.dart';
import '../otherenew/service.dart';
import '../pushnotification/pushservice.dart';
import 'call_overlay_manager.dart';
import '../constant/constant.dart';
import '../utils/time_utils.dart';
import '../utils/image_utils.dart';
import '../utils/privacy_utils.dart';
import 'widgets/typing_indicator.dart';

class ChatDetailScreen extends StatefulWidget {
  final String chatRoomId;
  final String receiverId;
  final String receiverName;
  final String receiverImage;
  final String? receiverPrivacy;
  final String? receiverPhotoRequest;
  final String currentUserId;
  final String currentUserName;
  final String currentUserImage;

  const ChatDetailScreen({
    super.key,
    required this.chatRoomId,
    required this.receiverId,
    required this.receiverName,
    required this.receiverImage,
    this.receiverPrivacy,
    this.receiverPhotoRequest,
    required this.currentUserId,
    required this.currentUserName,
    required this.currentUserImage,
  });

  @override
  State<ChatDetailScreen> createState() => _ChatDetailScreenState();
}

class _ChatDetailScreenState extends State<ChatDetailScreen>
    with TickerProviderStateMixin, WidgetsBindingObserver {
  final SocketService _socketService = SocketService();
  final Uuid _uuid = Uuid();

  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final FocusNode _messageFocusNode = FocusNode();

  String myImage = "";
  String otherUserImage = "";

  // Overlay
  bool showActionOverlay = false;
  bool showDeletePopup = false;
  Map<String, dynamic>? selectedMessage;
  bool selectedMine = false;

  // Reply functionality
  Map<String, dynamic>? repliedMessage;
  bool isReplying = false;

  // Edit functionality
  Map<String, dynamic>? editingMessage;
  bool isEditing = false;
  final TextEditingController _editController = TextEditingController();

  // Send guard to prevent duplicate messages
  bool _isSending = false;

  // Audio playback
  final AudioPlayer _audioPlayer = AudioPlayer();
  String? _playingMessageId;
  bool _isPlaying = false;
  Duration _playbackPosition = Duration.zero;
  Duration _playbackDuration = Duration.zero;

  // Voice recording
  final AudioRecorder _audioRecorder = AudioRecorder();
  bool _isRecording = false;
  bool _isHoldRecording = false; // true when mic is being held (press-and-hold mode)
  bool _isSendingVoice = false;
  int _recordDuration = 0;
  Timer? _recordTimer;
  String? _recordingPath;
  AnimationController? _recordingAnimController;
  double _audioAmplitude = -160.0; // dBFS — updated while recording
  StreamSubscription? _amplitudeSubscription;

  // Scroll lock during swipe-to-reply
  bool _isHorizontalDragging = false;

  // Cached messages to prevent blinking
  List<Map<String, dynamic>> _cachedMessages = [];
  bool _isFirstLoad = true;

  // Track whether the compose field has text (avoids per-keystroke full rebuild)
  bool _hasText = false;

  // Message-widget cache: rebuilt only when messages/highlight/loading state changes
  List<Widget>? _cachedMessageWidgets;
  int _messagesCacheVersion = 0;
  int _lastBuiltVersion = -1;
  String? _lastBuiltHighlightId;
  bool _lastBuiltIsLoadingMore = false;

  // Lazy loading variables
  bool _isLoadingMore = false;
  bool _hasMoreMessages = true;
  static const int _messagesPerPage = 20;
  // Pagination cursor – only updated on first load and during loadMore (never on stream updates)
  int _currentMessagePage = 1;

  // Call history variables
  List<CallHistory> _callHistory = [];
  bool _showCallHistory = false;
  StreamSubscription? _callHistorySubscription;

  // Backup incoming call listener (mirrors AdminChatScreen logic)
  StreamSubscription<Map<String, dynamic>>? _callListenerSubscription;

  // Messages stream subscription (replaces StreamBuilder to prevent rebuild-on-setState)
  StreamSubscription? _messagesSubscription;

  // Typing indicator
  Timer? _typingDebounce;
  bool _isReceiverTyping = false;
  StreamSubscription? _typingSubscription;
  bool _isMarkingMessagesAsRead = false;
  bool _isReceiverViewingThisChat = false;

  // Receiver online status
  bool _isOtherUserOnline = false;
  DateTime? _otherUserLastSeen;
  StreamSubscription? _otherUserStatusSub;
  StreamSubscription? _audioPlayerStateSubscription;
  StreamSubscription? _audioPlayerPositionSubscription;
  StreamSubscription? _audioPlayerDurationSubscription;
  // Track whether the next scroll-to-bottom should be forced (own message sent)
  bool _forceScrollToBottom = false;

  // Scroll-to-reply + highlight
  final Map<String, GlobalKey> _messageKeys = {};
  String? _highlightedMessageId;

  // Delivered status (hover for web)
  String? _hoveredMessageId;

  // Block / photo-privacy state
  bool _isBlocked = false;
  bool _isLoadingBlock = true;
  String _photoRequestStatus = 'not_sent';
  String _privacyStatus = 'private';

  // Timing constants
  static const int _kTypingTimeoutSeconds = 5;
  static const Duration _kTypingDebounceDelay = Duration(seconds: 3);
  static const Duration _kHighlightDuration = Duration(milliseconds: 700);
  static const Duration _kActiveChatPresenceWindow = Duration(seconds: 30);
  static const Duration _kScrollToMessageDelay = Duration(milliseconds: 400);

  // Image display constants
  static const double _kImageWidthFraction = 0.65;
  static const double _kImageAspectRatio = 0.75; // 4:3
  static const double _kImageMinWidth = 120.0;
  static const double _kImageMaxHeight = 300.0;

  static const LinearGradient _primaryGradient = LinearGradient(
    colors: [Color(0xFFF90E18), Color(0xFFD00D15)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );
  static const LinearGradient _secondaryGradient = LinearGradient(
    colors: [Color(0xFFFFE4E6), Color(0xFFFFF1F2)],
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
  );
  static const Color _accentColor = Color(0xFFF90E18);
  static const Color _backgroundColor = Color(0xFFF8FAFC);
  static const Color _textColor = Color(0xFF1F2937);
  static const Color _lightTextColor = Color(0xFF6B7280);
  static const Color _inputFieldBackground = Color(0xFFF3F4F6);
  static const Color _sendButtonDisabled = Color(0xFFD1D5DB);

  @override
  void initState() {
    super.initState();
    myImage = widget.currentUserImage;
    otherUserImage = widget.receiverImage;

    _recordingAnimController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    );

    // Defer heavy init work off the first frame so the screen opens instantly
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _markMessagesAsRead();
      _checkBlockStatus();
      _loadCallHistory();
      _fetchPhotoRequestStatus();
    });

    // Set chat as active when screen opens
    ScreenStateManager().onChatScreenOpened(
      widget.chatRoomId,
      widget.currentUserId,
      partnerUserId: widget.receiverId,
    );
    _updateActiveChatPresence(true);

    // Add observer for app lifecycle
    WidgetsBinding.instance.addObserver(this);

    // Audio player listeners
    _audioPlayerStateSubscription = _audioPlayer.onPlayerStateChanged.listen((state) {
      if (mounted) {
        setState(() {
          _isPlaying = state == PlayerState.playing;
          if (state == PlayerState.completed) {
            _playingMessageId = null;
            _playbackPosition = Duration.zero;
          }
        });
      }
    });
    _audioPlayerPositionSubscription = _audioPlayer.onPositionChanged.listen((pos) {
      if (mounted) setState(() => _playbackPosition = pos);
    });
    _audioPlayerDurationSubscription = _audioPlayer.onDurationChanged.listen((dur) {
      if (mounted) setState(() => _playbackDuration = dur);
    });

    // Update _hasText without a full rebuild on every keystroke
    _messageController.addListener(_onMessageTextChanged);

    // Add scroll listener for lazy loading
    _scrollController.addListener(_onScroll);

    // Start listening to messages (dedicated subscription to avoid merge running on every setState)
    _listenToMessages();

    // Start listening to receiver's typing status
    _listenToTypingStatus();

    // Start listening to receiver's online status
    _startReceiverStatusListener();

    // Backup incoming call listener so calls ring while typing on this screen.
    // The global CallOverlayWrapper is the primary handler; this ensures the
    // call UI still appears if the global handler's frame callback is delayed
    // (e.g. keyboard open with no pending frames).
    _setupCallListener();
  }

  void _onMessageTextChanged() {
    final hasText = _messageController.text.trim().isNotEmpty;
    if (hasText != _hasText && mounted) {
      setState(() => _hasText = hasText);
    }
  }

  void _onScroll() {
    if (_scrollController.position.pixels <=
            _scrollController.position.minScrollExtent + 200 &&
        !_isLoadingMore &&
        _hasMoreMessages) {
      _loadMoreMessages();
    }
  }

  // ── Local message cache helpers ───────────────────────────────────────────

  /// Converts a message map to a JSON-serializable form (DateTime → ISO string).
  Map<String, dynamic> _serializeMessage(Map<String, dynamic> msg) {
    final m = Map<String, dynamic>.from(msg);
    if (m['timestamp'] is DateTime) {
      m['timestamp'] = (m['timestamp'] as DateTime).toIso8601String();
    }
    return m;
  }

  // Maximum number of messages to persist in the local cache.
  // Kept separate from _messagesPerPage so pagination and cache sizes can be
  // tuned independently.
  static const int _maxCachedMessages = 30;

  /// Saves the most recent [_maxCachedMessages] messages to SharedPreferences.
  Future<void> _saveMessagesToLocalCache() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final toSave = _cachedMessages.length > _maxCachedMessages
          ? _cachedMessages.sublist(_cachedMessages.length - _maxCachedMessages)
          : _cachedMessages;
      final encoded = jsonEncode(toSave.map(_serializeMessage).toList());
      await prefs.setString('chat_msgs_${widget.chatRoomId}', encoded);
    } catch (e) {
      debugPrint('Failed to save messages to local cache: $e');
    }
  }

  /// Loads previously cached messages from SharedPreferences.
  /// Returns an empty list when no cache exists or on error.
  Future<List<Map<String, dynamic>>> _loadMessagesFromLocalCache() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString('chat_msgs_${widget.chatRoomId}');
      if (raw == null) return [];
      final decoded = jsonDecode(raw) as List<dynamic>;
      return decoded.map((item) {
        final m = Map<String, dynamic>.from(item as Map);
        // Re-parse ISO string timestamps back to DateTime
        if (m['timestamp'] is String) {
          final dt = DateTime.tryParse(m['timestamp'] as String);
          if (dt != null) m['timestamp'] = dt;
        }
        return m;
      }).toList();
    } catch (e) {
      debugPrint('Failed to load messages from local cache: $e');
      return [];
    }
  }

  // ─────────────────────────────────────────────────────────────────────────

  void _listenToMessages() {
    // Show locally cached messages immediately so the screen is usable at once
    _socketService.joinRoom(widget.chatRoomId);
    _loadMessagesFromLocalCache().then((cached) {
      if (!mounted) return;
      if (cached.isNotEmpty && _isFirstLoad) {
        setState(() {
          _cachedMessages = cached;
          _messagesCacheVersion++;
          // Show cached messages immediately; server response will replace them.
          _isFirstLoad = false;
        });
        _scrollToBottom(jump: true);
      }
    });

    // Load initial page via Socket.IO request-response
    _socketService.getMessages(widget.chatRoomId, page: 1, limit: _messagesPerPage).then((result) {
      if (!mounted) return;
      final messages = List<Map<String, dynamic>>.from(
        (result['messages'] as List? ?? []).map((m) => Map<String, dynamic>.from(m as Map)),
      );
      setState(() {
        _isFirstLoad = false;
        _cachedMessages = messages;
        _hasMoreMessages = result['hasMore'] == true;
        _currentMessagePage = 1;
        _messagesCacheVersion++;
      });
      _scrollToBottom(jump: true);
      _saveMessagesToLocalCache();
    }).catchError((e) {
      debugPrint('Error loading messages: $e');
      if (mounted) setState(() => _isFirstLoad = false);
    });

    // Real-time new messages
    _messagesSubscription = _socketService.onNewMessage.listen((data) {
      if (!mounted) return;
      if (data['chatRoomId']?.toString() != widget.chatRoomId) return;

      final newMsg = Map<String, dynamic>.from(data);
      // Normalise timestamp to DateTime for UI consistency
      final ts = SocketService.parseTimestamp(newMsg['timestamp']);
      if (ts != null) newMsg['timestamp'] = ts;

      final existingIdx = _cachedMessages.indexWhere(
        (m) => m['messageId']?.toString() == newMsg['messageId']?.toString(),
      );
      final shouldScroll = _forceScrollToBottom || _isNearBottom();

      setState(() {
        if (existingIdx >= 0) {
          _cachedMessages[existingIdx] = newMsg;
        } else {
          _cachedMessages.add(newMsg);
        }
        _messagesCacheVersion++;
        if (_forceScrollToBottom) _forceScrollToBottom = false;
      });

      _syncVisibleIncomingMessagesAsRead(_cachedMessages);
      if (shouldScroll) {
        WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToBottom());
      }
      _saveMessagesToLocalCache();
    });

    // Listen for edits and deletes
    _socketService.onMessageEdited.listen((data) {
      if (!mounted) return;
      if (data['chatRoomId']?.toString() != widget.chatRoomId) return;
      final idx = _cachedMessages.indexWhere(
        (m) => m['messageId']?.toString() == data['messageId']?.toString(),
      );
      if (idx >= 0) {
        setState(() {
          _cachedMessages[idx] = {
            ..._cachedMessages[idx],
            'message':  data['newMessage'],
            'isEdited': true,
            'editedAt': data['editedAt'],
          };
          _messagesCacheVersion++;
        });
        _saveMessagesToLocalCache();
      }
    });

    _socketService.onMessageDeleted.listen((data) {
      if (!mounted) return;
      if (data['chatRoomId']?.toString() != widget.chatRoomId) return;
      final msgId = data['messageId']?.toString();
      if (data['deleteForEveryone'] == true) {
        // Mark with a single flag – show "This message was deleted" placeholder for both parties
        final idx = _cachedMessages.indexWhere((m) => m['messageId']?.toString() == msgId);
        if (idx >= 0) {
          setState(() {
            _cachedMessages[idx] = {
              ..._cachedMessages[idx],
              'deletedForEveryone': true,
            };
            _messagesCacheVersion++;
          });
          _saveMessagesToLocalCache();
        }
      } else {
        final idx = _cachedMessages.indexWhere((m) => m['messageId']?.toString() == msgId);
        if (idx >= 0) {
          final isMine = data['userId']?.toString() == widget.currentUserId;
          setState(() {
            _cachedMessages[idx] = {
              ..._cachedMessages[idx],
              if (isMine) 'isDeletedForSender':   true,
              if (!isMine) 'isDeletedForReceiver': true,
            };
            _messagesCacheVersion++;
          });
          _saveMessagesToLocalCache();
        }
      }
    });
  }

  void _loadCallHistory() {
    // Use a live stream so the section updates whenever a call is made/ended
    _callHistorySubscription =
        CallHistoryService.getCallHistory(widget.currentUserId).listen(
      (allCalls) {
        // Filter calls for this specific chat partner
        final filteredCalls = allCalls.where((call) {
          return (call.callerId == widget.currentUserId &&
                  call.recipientId == widget.receiverId) ||
              (call.recipientId == widget.currentUserId &&
                  call.callerId == widget.receiverId);
        }).toList();

        if (mounted) {
          setState(() {
            _callHistory = filteredCalls;
          });
        }
      },
      onError: (e) {
        print('Error loading call history: $e');
      },
    );
  }
  Future<void> _checkBlockStatus() async {
    final prefs = await SharedPreferences.getInstance();
    final userDataString = prefs.getString('user_data');
    if (userDataString == null) return;

    final userData = jsonDecode(userDataString);
    final myId = userData["id"].toString();

    final service = ProfileService();
    final isBlocked = await service.isUserBlocked(
      myId: myId,
      userId: widget.receiverId,
    );

    if (mounted) {
      setState(() {
        _isBlocked = isBlocked;
      });
    }
  }

  Future<void> _fetchPhotoRequestStatus() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userDataString = prefs.getString('user_data');
      if (userDataString == null) return;

      final userData = jsonDecode(userDataString);
      final myId = userData["id"].toString();

      final service = ProfileService();
      final profileResponse = await service.fetchProfile(
        myId: myId,
        userId: widget.receiverId,
      );

      if (mounted) {
        setState(() {
          _photoRequestStatus = profileResponse.data.personalDetail.photoRequest;
          _privacyStatus = profileResponse.data.personalDetail.privacy;
        });
      }
    } catch (e) {
      debugPrint('Error fetching photo request status: $e');
    }
  }

  // ───────── TYPING INDICATOR ─────────

  void _listenToTypingStatus() {
    _typingSubscription = _socketService.onTypingStart.listen((data) {
      if (!mounted) return;
      if (data['chatRoomId']?.toString() != widget.chatRoomId) return;
      if (data['userId']?.toString() != widget.receiverId) return;
      if (!_isReceiverTyping) setState(() => _isReceiverTyping = true);
      // Reset auto-clear timeout
      _typingDebounce?.cancel();
      _typingDebounce = Timer(Duration(seconds: _kTypingTimeoutSeconds), () {
        if (mounted && _isReceiverTyping) setState(() => _isReceiverTyping = false);
      });
    });

    // Stop typing
    _socketService.onTypingStop.listen((data) {
      if (!mounted) return;
      if (data['chatRoomId']?.toString() != widget.chatRoomId) return;
      if (data['userId']?.toString() != widget.receiverId) return;
      _typingDebounce?.cancel();
      if (_isReceiverTyping) setState(() => _isReceiverTyping = false);
    });
  }

  void _startReceiverStatusListener() {
    _otherUserStatusSub?.cancel();
    _otherUserStatusSub = _socketService.onUserStatusChange.listen((data) {
      if (!mounted) return;
      if (data['userId']?.toString() != widget.receiverId) return;
      final bool online = data['isOnline'] == true;
      final DateTime? lastSeen = SocketService.parseTimestamp(data['lastSeen']);
      setState(() {
        _isOtherUserOnline = online;
        _otherUserLastSeen  = lastSeen;
      });
    });
  }

  /// Format lastSeen timestamp into a human-readable "last active" string.
  String _formatLastSeen(DateTime lastSeen) => formatLastSeen(lastSeen);


  void _onTypingChanged() {
    _typingDebounce?.cancel();
    _socketService.startTyping(widget.chatRoomId, widget.currentUserId);
    _typingDebounce = Timer(_kTypingDebounceDelay, _clearTyping);
  }

  void _clearTyping() {
    _socketService.stopTyping(widget.chatRoomId, widget.currentUserId);
  }

  // Backup incoming call listener so calls ring while the user is typing on
  // this screen.  The global CallOverlayWrapper is the primary handler; this
  // ensures the call UI still appears if its frame callback is delayed
  // (e.g. keyboard open with no frames being rendered).
  void _setupCallListener() {
    _callListenerSubscription?.cancel();
    _callListenerSubscription = NotificationService.incomingCalls.listen((data) {
      final isVideoCall =
          data['type'] == 'video_call' || data['isVideoCall'] == 'true';
      // Dismiss keyboard so it does not block the call screen.
      FocusManager.instance.primaryFocus?.unfocus();
      // Schedule a frame first so the postFrameCallback below fires immediately,
      // even when the app is idle (no frames being scheduled, e.g. keyboard open).
      WidgetsBinding.instance.scheduleFrame();
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        // isCallScreenShowing is set by whichever listener fires first (usually
        // CallOverlayWrapper).  Skip if the screen is already being shown.
        if (CallManager().isCallScreenShowing) return;
        CallManager().isCallScreenShowing = true;
        try {
          Navigator.of(context)
              .push(
                MaterialPageRoute(
                  settings: const RouteSettings(name: activeCallRouteName),
                  fullscreenDialog: true,
                  builder: (_) => isVideoCall
                      ? IncomingVideoCallScreen(callData: data)
                      : IncomingCallScreen(callData: data),
                ),
              )
              .whenComplete(() {
            CallManager().isCallScreenShowing = false;
          });
        } catch (_) {
          // Reset the flag if push fails so future calls are not blocked.
          CallManager().isCallScreenShowing = false;
        }
      });
    });
  }

  Future<void> _setActiveChatPresence({required bool isActive}) async {
    _socketService.setActiveChat(widget.currentUserId, widget.chatRoomId, isActive: isActive);
  }

  void _updateActiveChatPresence(bool isActive) {
    unawaited(
      _setActiveChatPresence(isActive: isActive).catchError((Object error) {
        debugPrint('Error updating active chat presence: $error');
      }),
    );
  }

  void _syncVisibleIncomingMessagesAsRead(List<Map<String, dynamic>> messages) {
    if (_isMarkingMessagesAsRead) return;
    final hasUnreadIncoming = messages.any((message) =>
        message['receiverId'] == widget.currentUserId &&
        message['isRead'] != true);
    if (!hasUnreadIncoming) return;
    _markMessagesAsRead();
  }

  // ───────── SCROLL TO REPLIED MESSAGE ─────────

  /// Scroll to the message with [messageId] and briefly highlight it.
  void _scrollToMessage(String messageId) {
    final key = _messageKeys[messageId];

    void _highlight() {
      setState(() => _highlightedMessageId = messageId);
      Future.delayed(_kHighlightDuration, () {
        if (mounted) setState(() => _highlightedMessageId = null);
      });
    }

    if (key?.currentContext != null) {
      Scrollable.ensureVisible(
        key!.currentContext!,
        duration: const Duration(milliseconds: 350),
        curve: Curves.easeInOut,
        alignment: 0.3,
      );
      _highlight();
      return;
    }

    // Widget not rendered – scroll to approximate position by message index
    final idx = _cachedMessages.indexWhere(
      (m) => m['messageId']?.toString() == messageId,
    );
    if (idx < 0 || !_scrollController.hasClients) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Original message is not in view. Scroll up to find it.'),
          duration: Duration(seconds: 2),
        ),
      );
      return;
    }

    final total = _cachedMessages.length;
    final fraction = idx / total;
    final targetPixels =
        _scrollController.position.maxScrollExtent * fraction;

    _scrollController.animateTo(
      targetPixels,
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeInOut,
    );

    // After scrolling completes, try ensureVisible then highlight
    Future.delayed(_kScrollToMessageDelay, () {
      if (!mounted) return;
      final k = _messageKeys[messageId];
      if (k?.currentContext != null) {
        Scrollable.ensureVisible(
          k!.currentContext!,
          duration: const Duration(milliseconds: 200),
          alignment: 0.3,
        );
      }
      _highlight();
    });
  }
  @override
  void dispose() {
    // Clear chat active state when screen closes
    ScreenStateManager().onChatScreenClosed();
    _updateActiveChatPresence(false);
    WidgetsBinding.instance.removeObserver(this);
    _messageController.removeListener(_onMessageTextChanged);
    _messageController.dispose();
    _editController.dispose();
    _scrollController.dispose();
    _messageFocusNode.dispose();
    _audioPlayerStateSubscription?.cancel();
    _audioPlayerPositionSubscription?.cancel();
    _audioPlayerDurationSubscription?.cancel();
    _audioPlayer.dispose();
    _recordTimer?.cancel();
    _amplitudeSubscription?.cancel();
    _audioRecorder.dispose();
    _recordingAnimController?.dispose();
    _typingDebounce?.cancel();
    _typingSubscription?.cancel();
    _otherUserStatusSub?.cancel();
    _callHistorySubscription?.cancel();
    _callListenerSubscription?.cancel();
    _messagesSubscription?.cancel();
    _socketService.leaveRoom(widget.chatRoomId);
    _clearTyping(); // Remove our typing entry on exit
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);

    // Handle app lifecycle changes
    switch (state) {
      case AppLifecycleState.resumed:
      // App came back to foreground, set chat as active
        ScreenStateManager().onChatScreenOpened(
          widget.chatRoomId,
          widget.currentUserId,
          partnerUserId: widget.receiverId,
        );
        _updateActiveChatPresence(true);
        _markMessagesAsRead();
        break;
      case AppLifecycleState.paused:
      case AppLifecycleState.inactive:
      // App went to background, clear active state
        ScreenStateManager().onChatScreenClosed();
        _updateActiveChatPresence(false);
        break;
      case AppLifecycleState.detached:
      // App is closed
        ScreenStateManager().onChatScreenClosed();
        _updateActiveChatPresence(false);
        break;
      case AppLifecycleState.hidden:
      // App is hidden
        ScreenStateManager().onChatScreenClosed();
        _updateActiveChatPresence(false);
        break;
    }
  }

  // MARK MESSAGES AS READ
  Future<void> _markMessagesAsRead() async {
    if (_isMarkingMessagesAsRead) return;
    _isMarkingMessagesAsRead = true;
    try {
      await _markMessagesAsReadInternal();
    } finally {
      _isMarkingMessagesAsRead = false;
    }
  }

  Future<void> _markMessagesAsReadInternal() async {
    try {
      _socketService.markRead(widget.chatRoomId, widget.currentUserId);
    } catch (e) {
      print('Error marking messages as read: $e');
    }
  }

  // SEND MESSAGE (with reply support)
  Future<void> _sendMessage() async {
    if (_isBlocked) return;
    if (_isSending) return;
    final messageText = _messageController.text.trim();
    if (messageText.isEmpty) return;

    // Clear input immediately to prevent duplicate sends
    _messageController.clear();
    if (mounted) setState(() { _isSending = true; });

    try {
      final timestamp = DateTime.now();
      final messageId = _uuid.v4();
      final bool receiverViewingThisChat = _isReceiverViewingThisChat;

      // Prepare message data
      final messageData = {
        'messageId': messageId,
        'senderId': widget.currentUserId,
        'receiverId': widget.receiverId,
        'message': messageText,
        'messageType': 'text',
        'timestamp': timestamp,
        'isRead': receiverViewingThisChat,
        'isDelivered': receiverViewingThisChat,
        'isDeletedForSender': false,
        'isDeletedForReceiver': false,
      };

      // Add reply data if replying to a message
      if (isReplying && repliedMessage != null) {
        messageData['repliedTo'] = {
          'messageId': repliedMessage!['messageId'],
          'message': repliedMessage!['message'],
          'senderId': repliedMessage!['senderId'],
          'senderName': repliedMessage!['senderId'] == widget.currentUserId
              ? widget.currentUserName
              : widget.receiverName,
          'messageType': repliedMessage!['messageType'] ?? 'text',
        };
      }

      // Clear reply/edit states
      _cancelReply();
      _cancelEdit();

      // Force scroll to bottom after own message
      _forceScrollToBottom = true;
      _scrollToBottom();

      // Send via Socket.IO
      _socketService.sendMessage(
        chatRoomId:        widget.chatRoomId,
        senderId:          widget.currentUserId,
        receiverId:        widget.receiverId,
        message:           messageText,
        messageType:       'text',
        messageId:         messageId,
        repliedTo:         messageData['repliedTo'] as Map<String, dynamic>?,
        isReceiverViewing: receiverViewingThisChat,
        user1Name:         widget.currentUserName,
        user2Name:         widget.receiverName,
        user1Image:        widget.currentUserImage,
        user2Image:        widget.receiverImage,
      );

      if (!receiverViewingThisChat) {
        // Send notification after message is saved
        await NotificationService.sendChatNotification(
          recipientUserId: widget.receiverId.toString(),
          senderName: widget.currentUserName,
          senderId: widget.currentUserId.toString(),
          message: messageText,
        );
      }

    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to send message: $e'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) setState(() { _isSending = false; });
    }
  }

  bool _isSendingImage = false;

  Future<void> _pickAndSendImages() async {
    if (_isBlocked || _isSendingImage) return;
    final picker = ImagePicker();
    List<XFile> picked = [];
    try {
      picked = await picker.pickMultiImage(imageQuality: 80);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Unable to access gallery. Please check permissions.'),
            backgroundColor: Colors.red,
          ),
        );
      }
      return;
    }
    if (picked.isEmpty || !mounted) return;

    setState(() => _isSendingImage = true);
    try {
      final bool receiverViewingThisChat = _isReceiverViewingThisChat;
      // Upload all images in parallel for speed
      final List<String?> urls = await Future.wait(
        picked.map((xfile) async {
          try {
            return await _socketService.uploadChatImage(
              bytes: await xfile.readAsBytes(),
              filename: xfile.name,
              userId:    widget.currentUserId,
              chatRoomId: widget.chatRoomId,
            );
          } catch (_) {
            return null;
          }
        }),
      );

      if (!mounted) return;
      _forceScrollToBottom = true;
      _scrollToBottom();

      final validUrls = urls.whereType<String>().toList();
      if (validUrls.isEmpty) return;

      if (validUrls.length == 1) {
        // Single image — send as normal 'image' message
        _socketService.sendMessage(
          chatRoomId:        widget.chatRoomId,
          senderId:          widget.currentUserId,
          receiverId:        widget.receiverId,
          message:           validUrls.first,
          messageType:       'image',
          messageId:         _uuid.v4(),
          isReceiverViewing: receiverViewingThisChat,
          user1Name:         widget.currentUserName,
          user2Name:         widget.receiverName,
          user1Image:        widget.currentUserImage,
          user2Image:        widget.receiverImage,
        );
      } else {
        // Multiple images — send as a single 'image_gallery' message
        _socketService.sendMessage(
          chatRoomId:        widget.chatRoomId,
          senderId:          widget.currentUserId,
          receiverId:        widget.receiverId,
          message:           jsonEncode(validUrls),
          messageType:       'image_gallery',
          messageId:         _uuid.v4(),
          isReceiverViewing: receiverViewingThisChat,
          user1Name:         widget.currentUserName,
          user2Name:         widget.receiverName,
          user1Image:        widget.currentUserImage,
          user2Image:        widget.receiverImage,
        );
      }

      if (!receiverViewingThisChat) {
        await NotificationService.sendChatNotification(
          recipientUserId: widget.receiverId.toString(),
          senderName: widget.currentUserName,
          senderId: widget.currentUserId.toString(),
          message: validUrls.length == 1 ? '📷 Photo' : '📷 ${validUrls.length} Photos',
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to send image: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSendingImage = false);
    }
  }

  // ── VOICE MESSAGE RECORDING ──────────────────────────────────────────────

  Future<void> _startRecording() async {
    if (_isBlocked || _isRecording) return;

    // Request microphone permission (native only; web uses browser prompt)
    if (!kIsWeb) {
      final status = await Permission.microphone.request();
      if (status != PermissionStatus.granted) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Microphone permission is required to record voice messages.'),
              backgroundColor: Colors.red,
            ),
          );
        }
        return;
      }
    }

    try {
      String path;
      if (kIsWeb) {
        path = 'voice_${DateTime.now().millisecondsSinceEpoch}.webm';
      } else {
        final dir = await getTemporaryDirectory();
        path = '${dir.path}/voice_${DateTime.now().millisecondsSinceEpoch}.m4a';
      }

      await _audioRecorder.start(
        kIsWeb
            ? const RecordConfig(encoder: AudioEncoder.opus, bitRate: 64000, sampleRate: 44100)
            : const RecordConfig(encoder: AudioEncoder.aacLc, bitRate: 64000, sampleRate: 44100),
        path: path,
      );

      // Monitor amplitude so the waveform shows flat bars when there is silence
      _amplitudeSubscription?.cancel();
      _amplitudeSubscription = _audioRecorder
          .onAmplitudeChanged(const Duration(milliseconds: 100))
          .listen((amp) {
        if (mounted) setState(() => _audioAmplitude = amp.current);
      });

      setState(() {
        _isRecording = true;
        _recordDuration = 0;
        _recordingPath = path;
      });

      _recordingAnimController?.repeat();
      _recordTimer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (mounted) setState(() => _recordDuration++);
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to start recording: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  Future<void> _stopAndSendRecording() async {
    if (!_isRecording) return;

    _recordTimer?.cancel();
    _recordTimer = null;
    _amplitudeSubscription?.cancel();
    _amplitudeSubscription = null;
    _audioAmplitude = -160.0;
    _recordingAnimController?.stop();
    _recordingAnimController?.reset();
    if (mounted) setState(() => _isHoldRecording = false);

    try {
      final path = await _audioRecorder.stop();
      setState(() {
        _isRecording = false;
        _isSendingVoice = true;
      });

      if (path == null || path.isEmpty) return;

      final messageId = _uuid.v4();
      final bool receiverViewingThisChat = _isReceiverViewingThisChat;

      // Read file bytes cross-platform (works on web and native)
      Uint8List voiceBytes;
      if (kIsWeb) {
        // On web path is a blob URL; use XFile to read bytes
        final xfile = XFile(path);
        voiceBytes = await xfile.readAsBytes();
      } else {
        voiceBytes = await File(path).readAsBytes();
      }

      final voiceUrl = await _socketService.uploadVoiceMessage(
        bytes: voiceBytes,
        filename: 'voice_${DateTime.now().millisecondsSinceEpoch}.mp3',
        userId: widget.currentUserId,
        chatRoomId: widget.chatRoomId,
      );

      _forceScrollToBottom = true;
      _scrollToBottom();

      _socketService.sendMessage(
        chatRoomId:        widget.chatRoomId,
        senderId:          widget.currentUserId,
        receiverId:        widget.receiverId,
        message:           voiceUrl,
        messageType:       'voice',
        messageId:         messageId,
        isReceiverViewing: receiverViewingThisChat,
        user1Name:         widget.currentUserName,
        user2Name:         widget.receiverName,
        user1Image:        widget.currentUserImage,
        user2Image:        widget.receiverImage,
      );

      if (!receiverViewingThisChat) {
        await NotificationService.sendChatNotification(
          recipientUserId: widget.receiverId.toString(),
          senderName: widget.currentUserName,
          senderId: widget.currentUserId.toString(),
          message: '🎤 Voice message',
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to send voice message: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() { _isRecording = false; _isSendingVoice = false; });
    }
  }

  void _cancelRecording() {
    if (!_isRecording) return;
    _recordTimer?.cancel();
    _recordTimer = null;
    _amplitudeSubscription?.cancel();
    _amplitudeSubscription = null;
    _audioAmplitude = -160.0;
    _recordingAnimController?.stop();
    _recordingAnimController?.reset();
    _audioRecorder.stop();
    setState(() {
      _isRecording = false;
      _isHoldRecording = false;
      _recordDuration = 0;
    });
  }

  String _formatRecordDuration(int seconds) {
    final m = (seconds ~/ 60).toString().padLeft(2, '0');
    final s = (seconds % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  Future<void> _editMessage() async {
    if (editingMessage == null || _editController.text.trim().isEmpty) return;

    try {
      final messageId = editingMessage!['messageId'];
      final newMessage = _editController.text.trim();

      _socketService.editMessage(widget.chatRoomId, messageId, newMessage);

      _cancelEdit();
      _scrollToBottom();

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Message edited'),
          backgroundColor: Colors.green,
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to edit message: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }


  // MESSAGE ACTIONS
  Future<void> _deleteMessage(bool deleteForEveryone) async {
    if (selectedMessage == null) return;

    try {
      final messageId = selectedMessage!['messageId'];

      _socketService.deleteMessage(
        chatRoomId:        widget.chatRoomId,
        messageId:         messageId,
        userId:            widget.currentUserId,
        deleteForEveryone: deleteForEveryone,
      );

      if (mounted) {
        setState(() {
          showDeletePopup = false;
          showActionOverlay = false;
          selectedMessage = null;
        });
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Message deleted'),
          backgroundColor: Colors.green,
        ),
      );
    } catch (e) {
      print('Error deleting message: $e');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to delete message: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  void _copyMessage() {
    if (selectedMessage != null && selectedMessage!['messageType'] == 'text') {
      Clipboard.setData(ClipboardData(text: selectedMessage!['message']));
      if (mounted) {
        setState(() {
          showActionOverlay = false;
          selectedMessage = null;
        });
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Message copied to clipboard'),
          backgroundColor: Colors.green,
        ),
      );
    }
  }

  // REPLY FUNCTIONALITY
  void _setReplyMessage(Map<String, dynamic> message) {
    if (mounted) {
      setState(() {
        repliedMessage = message;
        isReplying = true;
        showActionOverlay = false;
      });
    }

    _messageFocusNode.requestFocus();
  }

  void _cancelReply() {
    if (mounted) {
      setState(() {
        repliedMessage = null;
        isReplying = false;
      });
    }
  }

  // EDIT FUNCTIONALITY
  void _setEditMessage(Map<String, dynamic> message) {
    if (mounted) {
      setState(() {
        editingMessage = message;
        isEditing = true;
        _editController.text = message['message'];
        showActionOverlay = false;
      });
    }

    _messageFocusNode.requestFocus();
  }

  void _cancelEdit() {
    if (mounted) {
      setState(() {
        editingMessage = null;
        isEditing = false;
        _editController.clear();
      });
    }
  }

  // FORMATTING HELPERS
  String _formatTime(DateTime timestamp) {
    return DateFormat('hh:mm a').format(timestamp);
  }

  String _formatDuration(int seconds) {
    final minutes = seconds ~/ 60;
    final remainingSeconds = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${remainingSeconds.toString().padLeft(2, '0')}';
  }
  void _scrollToBottom({bool jump = false}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients && _scrollController.position.maxScrollExtent > 0) {
        if (jump) {
          _scrollController.jumpTo(_scrollController.position.maxScrollExtent);
        } else {
          _scrollController.animateTo(
            _scrollController.position.maxScrollExtent,
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOut,
          );
        }
      }
    });
  }

  /// Returns a short preview string for a replied-to message.
  String _replySnippetText(Map<String, dynamic> repliedTo) {
    switch (repliedTo['messageType'] as String? ?? 'text') {
      case 'image':
        return '📷 Photo';
      case 'image_gallery':
        return '🖼️ Photos';
      case 'voice':
        return '🎤 Voice message';
      case 'call':
        return '📞 Voice call';
      case 'video_call':
        return '📹 Video call';
      case 'profile_card':
        return '👤 Profile card';
      default:
        return repliedTo['message'] as String? ?? '';
    }
  }


  /// Returns true when the user is within 150px of the bottom of the list.
  bool _isNearBottom() {
    if (!_scrollController.hasClients) return true;
    final pos = _scrollController.position;
    return pos.pixels >= pos.maxScrollExtent - 150;
  }

  // VOICE PLAYBACK
  Future<void> _toggleVoicePlayback(String messageId, String audioUrl) async {
    if (_playingMessageId == messageId && _isPlaying) {
      await _audioPlayer.pause();
    } else if (_playingMessageId == messageId && !_isPlaying) {
      await _audioPlayer.resume();
    } else {
      _playbackPosition = Duration.zero;
      _playbackDuration = Duration.zero;
      if (mounted) setState(() => _playingMessageId = messageId);
      await _audioPlayer.play(UrlSource(audioUrl));
    }
  }


  Widget _buildReplyPreview() {
    if (!isReplying || repliedMessage == null) return const SizedBox.shrink();

    final isMyMessage = repliedMessage!['senderId'] == widget.currentUserId;
    final senderName = isMyMessage ? 'You' : widget.receiverName;
    final messageType = repliedMessage!['messageType'] ?? 'text';
    final message = repliedMessage!['message'];

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        gradient: _secondaryGradient,
        borderRadius: BorderRadius.circular(12),
        border: Border(
          left: BorderSide(
            color: _accentColor,
            width: 4,
          ),
        ),
        boxShadow: [
          BoxShadow(
            color: _accentColor.withOpacity(0.10),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Replying to $senderName',
                  style: TextStyle(
                    fontSize: 12,
                    color: _accentColor,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 3),
                if (messageType == 'text')
                  Text(
                    message,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 13, color: _lightTextColor),
                  )
                else if (messageType == 'image')
                  Row(
                    children: [
                      Icon(Icons.image, size: 15, color: _accentColor),
                      const SizedBox(width: 4),
                      Text('Photo',
                          style: TextStyle(fontSize: 13, color: _lightTextColor)),
                    ],
                  )
                else if (messageType == 'image_gallery')
                  Row(
                    children: [
                      Icon(Icons.photo_library, size: 15, color: _accentColor),
                      const SizedBox(width: 4),
                      Text('Photos',
                          style: TextStyle(fontSize: 13, color: _lightTextColor)),
                    ],
                  )
                else if (messageType == 'voice')
                  Row(
                    children: [
                      Icon(Icons.mic, size: 15, color: _accentColor),
                      const SizedBox(width: 4),
                      Text('Voice message',
                          style: TextStyle(fontSize: 13, color: _lightTextColor)),
                    ],
                  )
                else if (messageType == 'call')
                  Row(
                    children: [
                      Icon(Icons.call, size: 15, color: _accentColor),
                      const SizedBox(width: 4),
                      Text('Voice call',
                          style: TextStyle(fontSize: 13, color: _lightTextColor)),
                    ],
                  )
                else if (messageType == 'video_call')
                  Row(
                    children: [
                      Icon(Icons.videocam, size: 15, color: _accentColor),
                      const SizedBox(width: 4),
                      Text('Video call',
                          style: TextStyle(fontSize: 13, color: _lightTextColor)),
                    ],
                  )
                else if (messageType == 'profile_card')
                  Row(
                    children: [
                      Icon(Icons.person, size: 15, color: _accentColor),
                      const SizedBox(width: 4),
                      Text('Profile card',
                          style: TextStyle(fontSize: 13, color: _lightTextColor)),
                    ],
                  )
                else
                  Text(
                    message ?? '',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 13, color: _lightTextColor),
                  ),
              ],
            ),
          ),
          GestureDetector(
            onTap: _cancelReply,
            child: Container(
              padding: const EdgeInsets.all(4),
              decoration: BoxDecoration(
                color: Colors.grey.withOpacity(0.15),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.close, size: 16, color: Colors.grey),
            ),
          ),
        ],
      ),
    );
  }

  // EDIT PREVIEW WIDGET
  Widget _buildEditPreview() {
    if (!isEditing || editingMessage == null) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        gradient: _secondaryGradient,
        borderRadius: BorderRadius.circular(12),
        border: Border(
          left: BorderSide(color: _accentColor, width: 4),
        ),
        boxShadow: [
          BoxShadow(
            color: _accentColor.withOpacity(0.08),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Editing message',
                  style: TextStyle(
                    fontSize: 12,
                    color: _accentColor,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  editingMessage!['message'],
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 13, color: _lightTextColor),
                ),
              ],
            ),
          ),
          GestureDetector(
            onTap: _cancelEdit,
            child: Container(
              padding: const EdgeInsets.all(4),
              decoration: BoxDecoration(
                color: Colors.grey.withOpacity(0.15),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.close, size: 16, color: Colors.grey),
            ),
          ),
        ],
      ),
    );
  }

  // SWIPEABLE MESSAGE WIDGET
  Widget _swipeableMessage({
    required Widget child,
    required Map<String, dynamic> messageData,
    required bool isMine,
  }) {
    return _SwipeToReplyWrapper(
      isMine: isMine,
      onReply: () => _setReplyMessage(messageData),
      onDragStart: () {
        if (mounted) setState(() => _isHorizontalDragging = true);
      },
      onDragEnd: () {
        if (mounted) setState(() => _isHorizontalDragging = false);
      },
      child: child,
    );
  }

  // Message bubble with swipe reply
  Widget _messageBubble({
    required bool isMine,
    required String text,
    required DateTime timestamp,
    required String messageType,
    required bool isRead,
    required bool isDelivered,
    required int? duration,
    required Map<String, dynamic> messageData,
    required Map<String, dynamic>? repliedTo,
    required bool isEdited,
    bool isDeleted = false,
  }) {
    // Show a WhatsApp-style "This message was deleted" placeholder
    if (isDeleted) {
      return Row(
        mainAxisAlignment: isMine ? MainAxisAlignment.end : MainAxisAlignment.start,
        children: [
          Container(
            margin: const EdgeInsets.symmetric(vertical: 3, horizontal: 8),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.grey.shade100,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.grey.shade300, width: 1),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.block, size: 14, color: Colors.grey.shade500),
                const SizedBox(width: 6),
                Text(
                  'This message was deleted',
                  style: TextStyle(
                    color: Colors.grey.shade500,
                    fontSize: 14,
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ],
            ),
          ),
        ],
      );
    }
    final msgId = messageData['messageId'] as String? ?? '';
    // Assign a stable GlobalKey so we can scroll to this message
    final key = _messageKeys.putIfAbsent(msgId, () => GlobalKey());

    final time = _formatTime(timestamp);
    final screenWidth = MediaQuery.sizeOf(context).width;
    final isHighlighted = _highlightedMessageId == msgId;

    // Build the reply snippet widget (tappable to scroll to source)
    Widget? replyWidget;
    if (repliedTo != null) {
      replyWidget = GestureDetector(
        onTap: () {
          final replyId = repliedTo['messageId'] as String?;
          if (replyId != null) _scrollToMessage(replyId);
        },
        child: Container(
          width: 260,
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          margin: const EdgeInsets.only(bottom: 6),
          decoration: BoxDecoration(
            gradient: _secondaryGradient,
            borderRadius: BorderRadius.circular(10),
            border: Border(
              left: BorderSide(color: _accentColor, width: 3.5),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                repliedTo['senderName'] ?? 'User',
                style: TextStyle(
                  fontSize: 12,
                  color: _accentColor,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                _replySnippetText(repliedTo),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 13, color: _lightTextColor),
              ),
            ],
          ),
        ),
      );
    }

    // Choose tick icon based on delivery/read state
    Widget _buildTick() {
      if (isRead) {
        return Icon(Icons.done_all, size: 16, color: const Color(0xFF34B7F1));
      } else if (isDelivered) {
        return Icon(Icons.done_all, size: 16, color: Colors.grey.shade500);
      } else {
        return Icon(Icons.done, size: 16, color: Colors.grey.shade500);
      }
    }

    Widget messageContent = GestureDetector(
      onLongPress: () {
        if (mounted) {
          setState(() {
            selectedMessage = messageData;
            selectedMine = isMine;
            showActionOverlay = true;
          });
        }
      },
      child: Container(
        key: key,
        margin: const EdgeInsets.symmetric(vertical: 3, horizontal: 8),
        decoration: BoxDecoration(
          color: isHighlighted ? _accentColor.withOpacity(0.12) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          mainAxisAlignment: isMine ? MainAxisAlignment.end : MainAxisAlignment.start,
          children: [
            Column(
              crossAxisAlignment: isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
              children: [
                if (replyWidget != null) replyWidget,

                Container(
                  padding: (messageType == 'image' || messageType == 'image_gallery')
                      ? EdgeInsets.zero
                      : const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  constraints: BoxConstraints(
                    maxWidth: screenWidth * 0.75,
                  ),
                  clipBehavior: (messageType == 'image' || messageType == 'image_gallery') ? Clip.antiAlias : Clip.none,
                  decoration: BoxDecoration(
                    gradient: isMine ? _primaryGradient : _secondaryGradient,
                    borderRadius: isMine
                        ? const BorderRadius.only(
                      topLeft: Radius.circular(20),
                      topRight: Radius.circular(20),
                      bottomLeft: Radius.circular(20),
                      bottomRight: Radius.circular(4),
                    )
                        : const BorderRadius.only(
                      topLeft: Radius.circular(20),
                      topRight: Radius.circular(20),
                      bottomLeft: Radius.circular(4),
                      bottomRight: Radius.circular(20),
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.15),
                        blurRadius: 8,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildMessageContent(
                        text: text,
                        messageType: messageType,
                        isMine: isMine,
                        duration: duration,
                        messageId: messageData['messageId'] ?? '',
                      ),
                      if (isEdited)
                        Padding(
                          padding: EdgeInsets.only(top: 4),
                          child: Text(
                            'Edited',
                            style: TextStyle(
                              fontSize: 10,
                              color: isMine ? Colors.white70 : _lightTextColor,
                              fontStyle: FontStyle.italic,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 4),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      time,
                      style: TextStyle(
                        color: _lightTextColor,
                        fontSize: 12,
                      ),
                    ),
                    if (isMine) ...[
                      const SizedBox(width: 4),
                      _buildTick(),
                    ]
                  ],
                ),
              ],
            ),
            if (isMine) ...[
              const SizedBox(width: 10),
            ],
          ],
        ),
      ),
    );

    // Wrap with MouseRegion on web for hover quick-actions
    if (kIsWeb) {
      messageContent = MouseRegion(
        onEnter: (_) => setState(() => _hoveredMessageId = msgId),
        onExit: (_) => setState(() {
          if (_hoveredMessageId == msgId) _hoveredMessageId = null;
        }),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            messageContent,
            if (_hoveredMessageId == msgId)
              Positioned(
                top: 0,
                right: isMine ? null : -80,
                left: isMine ? -80 : null,
                child: _buildHoverActions(messageData, isMine),
              ),
          ],
        ),
      );
    }

    return _swipeableMessage(
      child: messageContent,
      messageData: messageData,
      isMine: isMine,
    );
  }

  /// Small row of quick-action buttons shown on hover (web only).
  Widget _buildHoverActions(Map<String, dynamic> msg, bool isMine) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(color: Colors.black26, blurRadius: 6, offset: const Offset(0, 2)),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _hoverActionBtn(Icons.reply, () => _setReplyMessage(msg)),
          if (isMine && msg['messageType'] == 'text')
            _hoverActionBtn(Icons.edit, () => _setEditMessage(msg)),
          _hoverActionBtn(Icons.delete, () {
            setState(() {
              selectedMessage = msg;
              selectedMine = isMine;
              showDeletePopup = true;
            });
          }, color: Colors.red),
        ],
      ),
    );
  }

  Widget _hoverActionBtn(IconData icon, VoidCallback onTap, {Color? color}) {
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(6),
        child: Icon(icon, size: 18, color: color ?? Colors.grey[700]),
      ),
    );
  }

  Widget _buildMessageContent({
    required String text,
    required String messageType,
    required bool isMine,
    required int? duration,
    required String messageId,
  }) {
    switch (messageType) {
      case 'image':
        final double imgWidth = MediaQuery.of(context).size.width * _kImageWidthFraction;
        final bool shouldBlur = !isMine &&
            _privacyStatus.toLowerCase() != 'free' &&
            _photoRequestStatus.toLowerCase() != 'accepted';

        if (shouldBlur) {
          return SizedBox(
            width: imgWidth,
            height: imgWidth * _kImageAspectRatio,
            child: Stack(
              fit: StackFit.expand,
              children: [
                ImageFiltered(
                  imageFilter: ui.ImageFilter.blur(sigmaX: 15, sigmaY: 15),
                  child: Image.network(
                    text,
                    width: imgWidth,
                    height: imgWidth * _kImageAspectRatio,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) => Container(
                      color: Colors.grey[300],
                    ),
                  ),
                ),
                Container(
                  color: Colors.black.withOpacity(0.4),
                  child: Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: Colors.grey.shade800.withOpacity(0.9),
                          ),
                          child: const Icon(
                            Icons.lock,
                            color: Colors.white,
                            size: 22,
                          ),
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'Photo Protected',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: Colors.white,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          );
        }

        return GestureDetector(
          onTap: () {
            showDialog(
              context: context,
              builder: (context) => Dialog(
                backgroundColor: Colors.black,
                insetPadding: const EdgeInsets.all(8),
                child: Stack(
                  children: [
                    InteractiveViewer(
                      child: Image.network(
                        text,
                        fit: BoxFit.contain,
                        errorBuilder: (context, error, stackTrace) => const Center(
                          child: Icon(Icons.broken_image, color: Colors.white54, size: 64),
                        ),
                      ),
                    ),
                    Positioned(
                      top: 16,
                      right: 16,
                      child: IconButton(
                        icon: const Icon(Icons.close, color: Colors.white),
                        onPressed: () => Navigator.pop(context),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: imgWidth,
              minWidth: _kImageMinWidth,
              maxHeight: _kImageMaxHeight,
            ),
            child: Image.network(
              text,
              fit: BoxFit.cover,
              loadingBuilder: (context, child, loadingProgress) {
                if (loadingProgress == null) return child;
                return SizedBox(
                  width: imgWidth,
                  height: imgWidth * _kImageAspectRatio,
                  child: Center(
                    child: CircularProgressIndicator(
                      value: loadingProgress.expectedTotalBytes != null
                          ? loadingProgress.cumulativeBytesLoaded /
                          loadingProgress.expectedTotalBytes!
                          : null,
                    ),
                  ),
                );
              },
            ),
          ),
        );
      case 'image_gallery':
        return _buildImageGallery(text: text);
      case 'voice':
        final isCurrentlyPlaying = _playingMessageId == messageId && _isPlaying;
        final isCurrentMessage = _playingMessageId == messageId;
        final totalSecs = duration ?? 0;
        final progressValue = isCurrentMessage && _playbackDuration.inSeconds > 0
            ? (_playbackPosition.inMilliseconds / _playbackDuration.inMilliseconds).clamp(0.0, 1.0)
            : 0.0;
        final displayTime = isCurrentMessage && _playbackDuration.inSeconds > 0
            ? _formatDuration(_playbackPosition.inSeconds)
            : _formatDuration(totalSecs);
        return GestureDetector(
          onTap: () => _toggleVoicePlayback(messageId, text),
          child: SizedBox(
            width: 200,
            child: Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: isMine
                      ? Colors.white.withOpacity(0.20)
                      : _accentColor.withOpacity(0.12),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    isCurrentlyPlaying ? Icons.pause : Icons.play_arrow,
                    color: isMine ? Colors.white : _accentColor,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(
                          value: progressValue,
                          minHeight: 3,
                          backgroundColor: Colors.grey.withOpacity(0.25),
                          valueColor: AlwaysStoppedAnimation<Color>(
                            isMine ? Colors.white : _accentColor,
                          ),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        displayTime,
                        style: TextStyle(
                          color: isMine ? Colors.white70 : _lightTextColor,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      default:
        return Text(
          text,
          style: TextStyle(
            color: isMine ? Colors.white : _textColor,
            fontSize: 16,
            height: 1.4,
          ),
        );
    }
  }

  // WhatsApp-style image gallery widget
  Widget _buildImageGallery({required String text}) {
    List<String> urls;
    try {
      final decoded = jsonDecode(text);
      if (decoded is List) {
        urls = decoded.whereType<String>().toList();
      } else {
        urls = [text];
      }
    } on FormatException catch (e) {
      debugPrint('image_gallery: JSON parse error: $e');
      urls = [text];
    } catch (e) {
      debugPrint('image_gallery: unexpected error: $e');
      urls = [text];
    }
    if (urls.isEmpty) return const SizedBox.shrink();
    if (urls.length == 1) {
      // Fallback: single image display
      return _buildSingleGalleryImage(urls.first);
    }

    const int maxVisible = 6;
    final int displayCount = urls.length > maxVisible ? maxVisible : urls.length;
    final int extraCount = urls.length > maxVisible ? urls.length - maxVisible + 1 : 0;
    final double screenWidth = MediaQuery.of(context).size.width;
    final double gridWidth = screenWidth * 0.65;
    const double gap = 2;

    Widget buildThumb(int index, {bool showOverlay = false, int extra = 0}) {
      final url = urls[index];
      Widget img = Image.network(
        url,
        fit: BoxFit.cover,
        loadingBuilder: (ctx, child, progress) {
          if (progress == null) return child;
          return Container(
            color: Colors.grey.shade200,
            child: Center(
              child: CircularProgressIndicator(
                strokeWidth: 2,
                value: progress.expectedTotalBytes != null
                    ? progress.cumulativeBytesLoaded / progress.expectedTotalBytes!
                    : null,
              ),
            ),
          );
        },
        errorBuilder: (_, __, ___) => Container(
          color: Colors.grey.shade300,
          child: Icon(Icons.broken_image, color: Colors.grey.shade500),
        ),
      );

      Widget tile = GestureDetector(
        onTap: () => _openGalleryViewer(urls, index),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: showOverlay
              ? Stack(
                  fit: StackFit.expand,
                  children: [
                    img,
                    Container(color: Colors.black54),
                    Center(
                      child: Text(
                        '+$extra',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                )
              : img,
        ),
      );
      return tile;
    }

    // Build WhatsApp-like layouts
    if (urls.length == 2) {
      // 2 images side by side
      final double h = gridWidth / 2 - gap / 2;
      return SizedBox(
        width: gridWidth,
        child: Row(
          children: [
            Expanded(child: SizedBox(height: h, child: buildThumb(0))),
            SizedBox(width: gap),
            Expanded(child: SizedBox(height: h, child: buildThumb(1))),
          ],
        ),
      );
    }

    if (urls.length == 3) {
      // Large on left, 2 stacked on right
      final double h = gridWidth * 0.6;
      return SizedBox(
        width: gridWidth,
        height: h,
        child: Row(
          children: [
            Expanded(flex: 2, child: SizedBox(height: h, child: buildThumb(0))),
            SizedBox(width: gap),
            Expanded(
              flex: 1,
              child: Column(
                children: [
                  Expanded(child: buildThumb(1)),
                  SizedBox(height: gap),
                  Expanded(child: buildThumb(2)),
                ],
              ),
            ),
          ],
        ),
      );
    }

    if (urls.length == 4) {
      // 2x2 grid
      final double cellW = (gridWidth - gap) / 2;
      final double h = cellW;
      return SizedBox(
        width: gridWidth,
        child: Column(
          children: [
            Row(children: [
              SizedBox(width: cellW, height: h, child: buildThumb(0)),
              SizedBox(width: gap),
              SizedBox(width: cellW, height: h, child: buildThumb(1)),
            ]),
            SizedBox(height: gap),
            Row(children: [
              SizedBox(width: cellW, height: h, child: buildThumb(2)),
              SizedBox(width: gap),
              SizedBox(width: cellW, height: h, child: buildThumb(3)),
            ]),
          ],
        ),
      );
    }

    // 5+ images: 2-column grid, last visible cell may show "+N"
    final double cellW = (gridWidth - gap) / 2;
    final double cellH = cellW;
    final List<Widget> cells = [];
    for (int i = 0; i < displayCount; i++) {
      final bool isLast = i == displayCount - 1 && extraCount > 0;
      cells.add(SizedBox(
        width: cellW,
        height: cellH,
        child: buildThumb(i, showOverlay: isLast, extra: extraCount),
      ));
    }

    final List<Widget> rows = [];
    for (int i = 0; i < cells.length; i += 2) {
      if (i > 0) rows.add(SizedBox(height: gap));
      rows.add(Row(
        children: [
          cells[i],
          SizedBox(width: gap),
          if (i + 1 < cells.length) cells[i + 1] else SizedBox(width: cellW),
        ],
      ));
    }

    return SizedBox(width: gridWidth, child: Column(children: rows));
  }

  Widget _buildSingleGalleryImage(String url) {
    final double imgWidth = MediaQuery.of(context).size.width * _kImageWidthFraction;
    return GestureDetector(
      onTap: () => _openGalleryViewer([url], 0),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: imgWidth,
          minWidth: _kImageMinWidth,
          maxHeight: _kImageMaxHeight,
        ),
        child: Image.network(url, fit: BoxFit.cover),
      ),
    );
  }

  void _openGalleryViewer(List<String> urls, int initialIndex) {
    showDialog(
      context: context,
      builder: (ctx) => _GalleryViewerDialog(urls: urls, initialIndex: initialIndex),
    );
  }

  // FULLSCREEN OVERLAY MENU
  Widget _fullScreenActionOverlay() {
    return GestureDetector(
      onTap: () {
        if (mounted) {
          setState(() => showActionOverlay = false);
        }
      },
      child: Container(
        width: double.infinity,
        height: double.infinity,
        color: Colors.black.withOpacity(0.55),
        child: Center(
          child: Container(
            width: 320,
            decoration: BoxDecoration(
              color: const Color(0xFF1E1E1E),
              borderRadius: BorderRadius.circular(22),
            ),
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Reply is available for all message types
                if (selectedMessage != null)
                  _menuItem(Icons.reply, "Reply", () {
                    _setReplyMessage(selectedMessage!);
                  }),
                if (selectedMessage != null &&
                    selectedMessage!['messageType'] == 'text')
                  _menuItem(Icons.copy, "Copy", _copyMessage),
                if (selectedMessage != null &&
                    selectedMine &&
                    selectedMessage!['messageType'] == 'text')
                  _menuItem(Icons.edit, "Edit", () {
                    _setEditMessage(selectedMessage!);
                  }),
                _menuItem(Icons.delete, "Delete", () {
                  if (mounted) {
                    setState(() {
                      showActionOverlay = false;
                      showDeletePopup = true;
                    });
                  }
                }, isDelete: true),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _deletePopupOverlay() {
    return GestureDetector(
      onTap: () {
        if (mounted) {
          setState(() => showDeletePopup = false);
        }
      },
      child: Container(
        width: double.infinity,
        height: double.infinity,
        color: Colors.black.withOpacity(0.55),
        child: Center(
          child: Container(
            width: 300,
            decoration: BoxDecoration(
              color: const Color(0xFF1E1E1E),
              borderRadius: BorderRadius.circular(22),
            ),
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _menuItem(Icons.delete_outline, "Delete only for you", () {
                  _deleteMessage(false);
                }, isDelete: true),
                if (selectedMine)
                  _menuItem(Icons.delete, "Delete for everyone", () {
                    _deleteMessage(true);
                  }, isDelete: true),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _menuItem(IconData icon, String text, VoidCallback onTap,
      {bool isDelete = false}) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 18),
        child: Row(
          children: [
            Icon(icon, color: isDelete ? Colors.red : Colors.white, size: 20),
            const SizedBox(width: 14),
            Text(
              text,
              style: TextStyle(
                color: isDelete ? Colors.red : Colors.white,
                fontSize: 15,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _bottomInputBar() {
    if (_isBlocked) {
      return Container(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 12,
          bottom: MediaQuery.of(context).padding.bottom + 12,
        ),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border(
            top: BorderSide(color: Colors.grey.shade200, width: 1),
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.block, color: Colors.red.shade400, size: 18),
            const SizedBox(width: 8),
            Text(
              'You have blocked this user',
              style: TextStyle(
                color: Colors.red.shade400,
                fontSize: 14,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      );
    }

    final hasText = isEditing
        ? _editController.text.trim().isNotEmpty
        : _hasText;

    return Listener(
      // Handles release-to-send for the hold-to-record gesture
      onPointerUp: (_) {
        if (_isHoldRecording && _isRecording) {
          _isHoldRecording = false;
          _stopAndSendRecording();
        }
      },
      onPointerCancel: (_) {
        if (_isHoldRecording && _isRecording) {
          _isHoldRecording = false;
          _cancelRecording();
        }
      },
      child: Container(
      padding: EdgeInsets.only(
        left: 12,
        right: 12,
        top: 8,
        bottom: MediaQuery.of(context).padding.bottom + 8,
      ),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(
          top: BorderSide(color: Colors.grey.shade200, width: 1),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 6,
            offset: const Offset(0, -1),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (isReplying) _buildReplyPreview(),
          if (isEditing) _buildEditPreview(),
          if (_isRecording) _buildRecordingBar() else Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              if (!isEditing)
                Padding(
                  padding: const EdgeInsets.only(right: 6, bottom: 4),
                  child: _isSendingImage
                      ? const SizedBox(
                          width: 40,
                          height: 40,
                          child: Padding(
                            padding: EdgeInsets.all(10),
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(_accentColor),
                            ),
                          ),
                        )
                      : IconButton(
                          onPressed: _pickAndSendImages,
                          icon: const Icon(Icons.image_outlined, size: 24),
                          color: _accentColor,
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
                        ),
                ),
              Expanded(
                child: Container(
                  constraints: const BoxConstraints(minHeight: 46),
                  decoration: BoxDecoration(
                    color: _inputFieldBackground,
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(
                      color: Colors.grey.shade300,
                      width: 1,
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      const SizedBox(width: 16),
                      Expanded(
                        child: TextField(
                          controller: isEditing
                              ? _editController
                              : _messageController,
                          focusNode: _messageFocusNode,
                          minLines: 1,
                          maxLines: 5,
                          keyboardType: TextInputType.multiline,
                          textInputAction: TextInputAction.newline,
                          style: const TextStyle(
                            fontSize: 15,
                            color: _textColor,
                            height: 1.4,
                          ),
                          decoration: InputDecoration(
                            hintText: isEditing
                                ? "Edit your message..."
                                : "Type a message...",
                            hintStyle: TextStyle(
                              color: Colors.grey.shade500,
                              fontSize: 15,
                            ),
                            border: InputBorder.none,
                            enabledBorder: InputBorder.none,
                            focusedBorder: InputBorder.none,
                            contentPadding: const EdgeInsets.symmetric(
                              horizontal: 0,
                              vertical: 12,
                            ),
                          ),
                          onChanged: (value) {
                            // Fire typing indicator only when composing (not editing)
                            if (!isEditing && value.isNotEmpty) {
                              _onTypingChanged();
                            } else if (!isEditing && value.isEmpty) {
                              _clearTyping();
                            }
                          },
                          onSubmitted: (_) => isEditing ? _editMessage() : _sendMessage(),
                        ),
                      ),
                      const SizedBox(width: 8),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),
              if (!isEditing && !hasText)
                GestureDetector(
                  // Single tap: existing tap-to-record flow (shows recording bar)
                  onTap: _startRecording,
                  // Long press: WhatsApp-style hold-to-record (release to send)
                  onLongPressStart: (details) async {
                    HapticFeedback.lightImpact();
                    if (mounted) setState(() => _isHoldRecording = true);
                    await _startRecording();
                  },
                  child: Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      gradient: _primaryGradient,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: _accentColor.withOpacity(0.35),
                          blurRadius: 8,
                          offset: const Offset(0, 3),
                        ),
                      ],
                    ),
                    child: const Icon(Icons.mic, color: Colors.white, size: 22),
                  ),
                )
              else
                AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  decoration: BoxDecoration(
                    gradient: hasText ? _primaryGradient : null,
                    color: hasText ? null : _sendButtonDisabled,
                    shape: BoxShape.circle,
                    boxShadow: hasText
                        ? [
                            BoxShadow(
                              color: _accentColor.withOpacity(0.35),
                              blurRadius: 8,
                              offset: const Offset(0, 3),
                            ),
                          ]
                        : [],
                  ),
                  child: IconButton(
                    onPressed: hasText ? (isEditing ? _editMessage : _sendMessage) : null,
                    icon: Icon(
                      isEditing ? Icons.check : Icons.send_rounded,
                      color: Colors.white,
                      size: 22,
                    ),
                    padding: const EdgeInsets.all(13),
                  ),
                ),
            ],
          ),
        ],
      ),
    ), // end Container
    ); // end Listener
  }

  Widget _bottomSection() => _bottomInputBar();

  Widget _buildRecordingBar() {
    if (_recordingAnimController == null) return const SizedBox.shrink();
    // -40 dBFS is a practical silence threshold: typical speech is above ~-30 dBFS
    // while background silence with no voice sits well below -40 dBFS.
    final bool hasAudio = _audioAmplitude > -40.0;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (_isHoldRecording)
          Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.arrow_upward, size: 12, color: Colors.grey.shade500),
                const SizedBox(width: 4),
                Text(
                  'Slide up to cancel \u2022 Release to send',
                  style: TextStyle(
                    fontSize: 11,
                    color: Colors.grey.shade500,
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ],
            ),
          ),
        Row(
          children: [
            IconButton(
              onPressed: _cancelRecording,
              icon: const Icon(Icons.delete_outline, color: Colors.red, size: 24),
              tooltip: 'Cancel',
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Container(
                height: 46,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(
                  color: _accentColor.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: _accentColor.withOpacity(0.3), width: 1),
                ),
                child: Row(
                  children: [
                    // Pulsing red dot – opacity oscillates between 0.4 and 1.0
                    AnimatedBuilder(
                      animation: _recordingAnimController!,
                      builder: (context, _) {
                        final pulse = 0.4 + 0.6 * (0.5 + 0.5 * sin(2 * pi * _recordingAnimController!.value));
                        return Opacity(
                          opacity: pulse,
                          child: Container(
                            width: 10,
                            height: 10,
                            decoration: const BoxDecoration(
                              color: Colors.red,
                              shape: BoxShape.circle,
                            ),
                          ),
                        );
                      },
                    ),
                    const SizedBox(width: 6),
                    Text(
                      _formatRecordDuration(_recordDuration),
                      style: const TextStyle(
                        color: _accentColor,
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(width: 8),
                    // Animated waveform bars – flat when no voice is detected
                    Expanded(
                      child: AnimatedBuilder(
                        animation: _recordingAnimController!,
                        builder: (context, _) {
                          const barCount = 22;
                          const maxH = 20.0;
                          const minH = 4.0;
                          final t = _recordingAnimController!.value;
                          return Row(
                            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                            crossAxisAlignment: CrossAxisAlignment.center,
                            children: List.generate(barCount, (i) {
                              final phase = (i / barCount) * 2 * pi;
                              // Flat bars when no voice is detected, animated wave otherwise
                              final h = hasAudio
                                  ? minH + (maxH - minH) * (0.5 + 0.5 * sin(2 * pi * t + phase))
                                  : minH;
                              return Container(
                                width: 3,
                                height: h,
                                decoration: BoxDecoration(
                                  color: _accentColor.withOpacity(hasAudio ? 0.75 : 0.35),
                                  borderRadius: BorderRadius.circular(2),
                                ),
                              );
                            }),
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 8),
            _isSendingVoice
                ? const SizedBox(
                    width: 46,
                    height: 46,
                    child: Padding(
                      padding: EdgeInsets.all(11),
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        valueColor: AlwaysStoppedAnimation<Color>(_accentColor),
                      ),
                    ),
                  )
                : GestureDetector(
                    onTap: _stopAndSendRecording,
                    child: Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        gradient: _primaryGradient,
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: _accentColor.withOpacity(0.35),
                            blurRadius: 8,
                            offset: const Offset(0, 3),
                          ),
                        ],
                      ),
                      child: const Icon(Icons.send_rounded, color: Colors.white, size: 22),
                    ),
                  ),
          ],
        ),
      ],
    );
  }

  Widget _buildMessagesList() {
    // Show skeleton on very first load before the stream delivers any data.
    if (_isFirstLoad) {
      return _buildSkeletonLoader();
    }

    if (_cachedMessages.isEmpty && _callHistory.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.chat, size: 60, color: Colors.grey[300]),
            const SizedBox(height: 16),
            Text(
              'Start a conversation!',
              style: TextStyle(color: Colors.grey[500]),
            ),
          ],
        ),
      );
    }

    return _buildMessagesFromCache();
  }

  Future<void> _loadMoreMessages() async {
    if (_isLoadingMore || !_hasMoreMessages) return;
    setState(() => _isLoadingMore = true);

    final double scrollExtentBefore = _scrollController.hasClients
        ? _scrollController.position.maxScrollExtent : 0.0;
    final double currentPixels = _scrollController.hasClients
        ? _scrollController.position.pixels : 0.0;

    try {
      final nextPage = _currentMessagePage + 1;
      final result = await _socketService.getMessages(
        widget.chatRoomId, page: nextPage, limit: _messagesPerPage,
      );
      if (!mounted) return;

      final newMessages = List<Map<String, dynamic>>.from(
        (result['messages'] as List? ?? []).map((m) => Map<String, dynamic>.from(m as Map)),
      );

      if (newMessages.isEmpty) {
        setState(() { _hasMoreMessages = false; _isLoadingMore = false; });
        return;
      }

      setState(() {
        _cachedMessages.insertAll(0, newMessages);
        _hasMoreMessages = result['hasMore'] == true;
        _currentMessagePage = nextPage;
        _isLoadingMore = false;
        _messagesCacheVersion++;
      });

      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scrollController.hasClients) {
          final double scrollExtentAfter = _scrollController.position.maxScrollExtent;
          _scrollController.jumpTo(currentPixels + (scrollExtentAfter - scrollExtentBefore));
        }
      });
    } catch (e) {
      debugPrint('Error loading more messages: $e');
      if (mounted) setState(() => _isLoadingMore = false);
    }
  }

  Widget _buildSkeletonLoader() {
    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 20),
      itemCount: 8,
      itemBuilder: (context, index) {
        final isLeft = index % 2 == 0;
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 8.0),
          child: Row(
            mainAxisAlignment:
                isLeft ? MainAxisAlignment.start : MainAxisAlignment.end,
            children: [
              Container(
                constraints: BoxConstraints(maxWidth: 280),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.grey[200],
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 150 + (index * 20.0) % 80,
                      height: 12,
                      decoration: BoxDecoration(
                        color: Colors.grey[300],
                        borderRadius: BorderRadius.circular(6),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Container(
                      width: 100,
                      height: 12,
                      decoration: BoxDecoration(
                        color: Colors.grey[300],
                        borderRadius: BorderRadius.circular(6),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
  Widget _buildMessagesFromCache() {
    // Reuse cached widget list when nothing relevant has changed.
    // Bypass cache while audio is actively playing so progress updates render correctly.
    final canUseCache = _cachedMessageWidgets != null &&
        _playingMessageId == null &&
        _lastBuiltVersion == _messagesCacheVersion &&
        _lastBuiltHighlightId == _highlightedMessageId &&
        _lastBuiltIsLoadingMore == _isLoadingMore;

    if (canUseCache) {
      return ListView(
        controller: _scrollController,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 20),
        children: _cachedMessageWidgets!,
      );
    }

    final List<Widget> messageWidgets = [];

    // Add loading indicator at the top if loading more
    if (_isLoadingMore) {
      messageWidgets.add(
        Padding(
          padding: EdgeInsets.all(16.0),
          child: Center(
            child: SizedBox(
              width: 24,
              height: 24,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                valueColor: AlwaysStoppedAnimation<Color>(_accentColor),
              ),
            ),
          ),
        ),
      );
    }

    // Group messages by date
    final Map<String, List<Map<String, dynamic>>> groupedMessages = {};

    for (final data in _cachedMessages) {
      final isDeletedForSender = data['isDeletedForSender'] ?? false;
      final isDeletedForReceiver = data['isDeletedForReceiver'] ?? false;
      final isDeletedForEveryone = data['deletedForEveryone'] == true;
      final isMine = data['senderId'] == widget.currentUserId;
      // "Delete for me only" — hide from the relevant user
      final isDeletedForMeOnly = !isDeletedForEveryone &&
          (isMine ? isDeletedForSender : isDeletedForReceiver);
      if (isDeletedForMeOnly) continue;

      final rawTs = data['timestamp'];
      if (rawTs == null) continue;
      final timestamp = rawTs is DateTime
          ? rawTs
          : rawTs is String
              ? DateTime.tryParse(rawTs)
              : null;
      if (timestamp == null) continue;
      final dateKey = _formatDateForGrouping(timestamp);

      groupedMessages.putIfAbsent(dateKey, () => []);
      groupedMessages[dateKey]!.add(data);
    }

    // Sort date keys in chronological order (oldest first)
    final sortedDateKeys = _sortDateKeysChronologically(groupedMessages.keys.toList());

    // Build widgets for each date group
    for (final dateKey in sortedDateKeys) {
      final messagesForDate = groupedMessages[dateKey]!;

      // Sort messages within each date group by timestamp (oldest first)
      messagesForDate.sort((a, b) {
        final rawA = a['timestamp'];
        final rawB = b['timestamp'];
        if (rawA == null || rawB == null) return 0;
        final timeA = rawA is DateTime
            ? rawA
            : rawA is String
                ? (DateTime.tryParse(rawA) ?? DateTime.now())
                : DateTime.now();
        final timeB = rawB is DateTime
            ? rawB
            : rawB is String
                ? (DateTime.tryParse(rawB) ?? DateTime.now())
                : DateTime.now();
        return timeA.compareTo(timeB);
      });

      // Add date separator/label
      messageWidgets.add(_dateSeparator(dateKey));

      // Add all messages for this date
      for (final data in messagesForDate) {
        final rawTs = data['timestamp'];
        final timestamp = rawTs is DateTime
            ? rawTs
            : rawTs is String
                ? (DateTime.tryParse(rawTs) ?? DateTime.now())
                : DateTime.now();

        if ((data['messageType'] ?? 'text') == 'call') {
          messageWidgets.add(_buildInlineCallBubble(
            callType: data['callType'] ?? 'audio',
            callStatus: data['callStatus'] ?? 'missed',
            duration: data['duration']?.toInt() ?? 0,
            callerId: data['senderId'] ?? '',
            timestamp: timestamp,
          ));
        } else {
          final isMine = data['senderId'] == widget.currentUserId;
          final isDeletedForEveryone = data['deletedForEveryone'] == true;
          messageWidgets.add(_messageBubble(
            isMine: isMine,
            text: data['message'],
            timestamp: timestamp,
            messageType: data['messageType'] ?? 'text',
            isRead: data['isRead'] ?? false,
            isDelivered: data['isDelivered'] ?? false,
            duration: data['duration']?.toInt(),
            messageData: data,
            repliedTo: data['repliedTo'],
            isEdited: data['isEdited'] ?? false,
            isDeleted: isDeletedForEveryone,
          ));
        }
      }
    }

    // Cache for next rebuild
    _cachedMessageWidgets = messageWidgets;
    _lastBuiltVersion = _messagesCacheVersion;
    _lastBuiltHighlightId = _highlightedMessageId;
    _lastBuiltIsLoadingMore = _isLoadingMore;

    return ListView.builder(
      reverse: false, // Keep as false for natural order
      controller: _scrollController,
      physics: _isHorizontalDragging
          ? const NeverScrollableScrollPhysics()
          : null,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 20),
      itemCount: messageWidgets.length,
      itemBuilder: (context, index) {
        return messageWidgets[index];
      },
    );
  }

  Widget _buildInlineCallBubble({
    required String callType,
    required String callStatus,
    required int duration,
    required String callerId,
    required DateTime timestamp,
  }) {
    final isVideo = callType == 'video';
    final isBusy = callStatus == 'busy';
    final isMissed = isBusy || callStatus == 'missed' || callStatus == 'declined' || callStatus == 'cancelled';
    final isOutgoing = callerId == widget.currentUserId;

    Color iconColor;
    IconData directionIcon;
    if (isBusy) {
      iconColor = Colors.orange;
      directionIcon = Icons.phone_locked;
    } else if (isMissed) {
      iconColor = Colors.red;
      directionIcon = isVideo ? Icons.videocam_off : Icons.phone_missed;
    } else if (isOutgoing) {
      iconColor = const Color(0xFF25D366);
      directionIcon = isVideo ? Icons.videocam : Icons.call_made;
    } else {
      iconColor = const Color(0xFF25D366);
      directionIcon = isVideo ? Icons.videocam : Icons.call_received;
    }

    String label;
    if (isBusy) {
      label = 'User is busy';
    } else if (isMissed) {
      label = isVideo ? 'Missed video call' : 'Missed voice call';
    } else if (isOutgoing) {
      label = isVideo ? 'Outgoing video call' : 'Outgoing voice call';
    } else {
      label = isVideo ? 'Incoming video call' : 'Incoming voice call';
    }

    String durationStr = '';
    if (!isMissed && duration > 0) {
      final m = duration ~/ 60;
      final s = duration % 60;
      durationStr = m > 0 ? '${m}m ${s}s' : '${s}s';
    }

    final timeStr = _formatTime(timestamp);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Center(
        child: Container(
          constraints: const BoxConstraints(maxWidth: 260),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: isBusy
                ? Colors.orange.withOpacity(0.08)
                : isMissed
                    ? Colors.red.withOpacity(0.08)
                    : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: isBusy
                  ? Colors.orange.withOpacity(0.3)
                  : isMissed
                      ? Colors.red.withOpacity(0.3)
                      : Colors.grey.withOpacity(0.25),
              width: 1,
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.06),
                blurRadius: 4,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: iconColor.withOpacity(0.12),
                  shape: BoxShape.circle,
                ),
                child: Icon(directionIcon, color: iconColor, size: 16),
              ),
              const SizedBox(width: 10),
              Flexible(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: isMissed ? Colors.red[700] : Colors.grey[800],
                      ),
                    ),
                    if (durationStr.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        durationStr,
                        style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Text(
                timeStr,
                style: TextStyle(fontSize: 11, color: Colors.grey[500]),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCallHistorySection() {
    return Container(
      margin: const EdgeInsets.only(bottom: 20, top: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header with toggle button
          GestureDetector(
            onTap: () {
              setState(() {
                _showCallHistory = !_showCallHistory;
              });
            },
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                gradient: _primaryGradient,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: _accentColor.withOpacity(0.30),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: Row(
                children: [
                  const Icon(Icons.history, color: Colors.white, size: 20),
                  const SizedBox(width: 8),
                  const Expanded(
                    child: Text(
                      'Call History',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.25),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      '${_callHistory.length}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Icon(
                    _showCallHistory
                        ? Icons.keyboard_arrow_up
                        : Icons.keyboard_arrow_down,
                    color: Colors.white,
                  ),
                ],
              ),
            ),
          ),
          // Call history list
          if (_showCallHistory) ...[
            const SizedBox(height: 12),
            Container(
              decoration: BoxDecoration(
                gradient: _secondaryGradient,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                children: _callHistory.take(10).map((call) {
                  return _buildCallHistoryItem(call);
                }).toList(),
              ),
            ),
            if (_callHistory.length > 10)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Center(
                  child: Text(
                    '... and ${_callHistory.length - 10} more calls',
                    style: TextStyle(
                      color: Colors.grey[600],
                      fontSize: 12,
                      fontStyle: FontStyle.italic,
                    ),
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }

  Widget _buildCallHistoryItem(CallHistory call) {
    final isIncoming = call.isIncoming(widget.currentUserId);

    // Status icon based on call type and direction
    IconData statusIcon;
    Color statusColor;

    if (call.status == CallStatus.missed && isIncoming) {
      statusIcon = Icons.call_missed;
      statusColor = Colors.red;
    } else if (call.status == CallStatus.declined) {
      statusIcon = Icons.call_end;
      statusColor = Colors.red;
    } else if (call.status == CallStatus.cancelled) {
      statusIcon = Icons.call_missed_outgoing;
      statusColor = Colors.orange;
    } else if (isIncoming) {
      statusIcon = Icons.call_received;
      statusColor = Colors.green;
    } else {
      statusIcon = Icons.call_made;
      statusColor = Colors.green;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        border: Border(
          bottom: BorderSide(color: Colors.grey[200]!, width: 0.5),
        ),
      ),
      child: Row(
        children: [
          // Call type icon
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: statusColor.withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(
              call.callType == CallType.video ? Icons.videocam : Icons.call,
              color: statusColor,
              size: 18,
            ),
          ),
          const SizedBox(width: 12),
          // Call details
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(statusIcon, size: 14, color: statusColor),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        isIncoming ? 'Incoming Call' : 'Outgoing Call',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                          color: Colors.grey[700],
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  _formatCallDateTime(call.startTime),
                  style: TextStyle(
                    fontSize: 11,
                    color: Colors.grey[600],
                  ),
                ),
              ],
            ),
          ),
          // Duration or status
          if (call.status == CallStatus.completed)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.green.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                call.getFormattedDuration(),
                style: const TextStyle(
                  fontSize: 12,
                  color: Colors.green,
                  fontWeight: FontWeight.w600,
                ),
              ),
            )
          else
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: statusColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                call.getStatusText(widget.currentUserId),
                style: TextStyle(
                  fontSize: 11,
                  color: statusColor,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
        ],
      ),
    );
  }

  String _formatCallDateTime(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inDays == 0) {
      return 'Today ${DateFormat('HH:mm').format(dateTime)}';
    } else if (difference.inDays == 1) {
      return 'Yesterday ${DateFormat('HH:mm').format(dateTime)}';
    } else if (difference.inDays < 7) {
      final dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      return '${dayNames[dateTime.weekday % 7]} ${DateFormat('HH:mm').format(dateTime)}';
    } else {
      return DateFormat('yyyy/MM/dd HH:mm').format(dateTime);
    }
  }

// Helper method to sort date keys chronologically with Today at the bottom
  List<String> _sortDateKeysChronologically(List<String> dateKeys) {
    final uniqueKeys = dateKeys.toSet().toList();

    uniqueKeys.sort((a, b) {
      // Convert date strings to DateTime for comparison
      DateTime? dateA, dateB;

      if (a == 'Today') {
        dateA = DateTime.now();
      } else if (a == 'Yesterday') {
        dateA = DateTime.now().subtract(const Duration(days: 1));
      } else {
        try {
          dateA = DateFormat('MMM dd, yyyy').parse(a);
        } catch (e) {
          dateA = DateTime.now();
        }
      }

      if (b == 'Today') {
        dateB = DateTime.now();
      } else if (b == 'Yesterday') {
        dateB = DateTime.now().subtract(const Duration(days: 1));
      } else {
        try {
          dateB = DateFormat('MMM dd, yyyy').parse(b);
        } catch (e) {
          dateB = DateTime.now();
        }
      }

      // Sort chronologically (oldest first)
      return dateA.compareTo(dateB);
    });

    return uniqueKeys;
  }

// Format date for grouping
  String _formatDateForGrouping(DateTime date) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final yesterday = DateTime(now.year, now.month, now.day - 1);
    final messageDate = DateTime(date.year, date.month, date.day);

    if (messageDate == today) {
      return 'Today';
    } else if (messageDate == yesterday) {
      return 'Yesterday';
    } else {
      return DateFormat('MMM dd, yyyy').format(date);
    }
  }

// Helper method to sort date keys in correct order: Today → Yesterday → Older dates


// Format date for grouping

// Enhanced date separator widget
  Widget _dateSeparator(String date) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 16),
      child: Center(
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          decoration: BoxDecoration(
            color: Colors.grey[200],
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            date,
            style: const TextStyle(
              color: Colors.grey,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

// Update scrollToBottom method for correct scroll direction




  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
        systemStatusBarContrastEnforced: false,
      ),
      child: Scaffold(
        backgroundColor: _backgroundColor,
        body: Stack(
          children: [
            Column(
              children: [
                _buildHeader(context),
                Expanded(
                  child: Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [_backgroundColor, _backgroundColor.withOpacity(0.92)],
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                      ),
                    ),
                    child: _buildMessagesList(),
                  ),
                ),
                if (_isReceiverTyping)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Container(
                      margin: const EdgeInsets.only(left: 12, bottom: 4),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      decoration: BoxDecoration(
                        gradient: _secondaryGradient,
                        borderRadius: const BorderRadius.only(
                          topLeft: Radius.circular(20),
                          topRight: Radius.circular(20),
                          bottomLeft: Radius.circular(4),
                          bottomRight: Radius.circular(20),
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.08),
                            blurRadius: 4,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: const TypingIndicatorWidget(
                        dotColor: Color(0xFF6B7280),
                        dotSize: 7.0,
                      ),
                    ),
                  ),
                _bottomSection(),
              ],
            ),

            if (showActionOverlay) _fullScreenActionOverlay(),
            if (showDeletePopup) _deletePopupOverlay(),
          ],
        ),
      ),
    );
  }

  /// Returns all image URLs exchanged in this chat (both sender and receiver).
  List<_SharedPhoto> _getSharedPhotos() {
    final List<_SharedPhoto> photos = [];
    for (final msg in _cachedMessages) {
      final type = msg['messageType']?.toString() ?? 'text';
      if (type != 'image' && type != 'image_gallery') continue;

      final text = msg['message']?.toString() ?? '';
      final senderId = msg['senderId']?.toString();
      final bool isFromReceiver = senderId != null && senderId != widget.currentUserId;

      if (type == 'image' && text.isNotEmpty) {
        photos.add(_SharedPhoto(
          url: resolveApiImageUrl(text),
          isFromReceiver: isFromReceiver,
        ));
      } else if (type == 'image_gallery') {
        try {
          final decoded = jsonDecode(text);
          if (decoded is List) {
            for (final u in decoded) {
              if (u is String && u.isNotEmpty) {
                photos.add(_SharedPhoto(
                  url: resolveApiImageUrl(u),
                  isFromReceiver: isFromReceiver,
                ));
              }
            }
          }
        } catch (_) {}
      }
    }
    return photos;
  }

  /// Shows a mini-profile bottom sheet for the person we are chatting with.
  void _showChatUserProfileSheet(BuildContext context) {
    final String resolvedImage = resolveApiImageUrl(widget.receiverImage);
    final List<_SharedPhoto> sharedPhotos = _getSharedPhotos();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _ChatUserProfileSheet(
        receiverId: widget.receiverId,
        receiverName: widget.receiverName,
        receiverImage: resolvedImage,
        receiverPrivacy: widget.receiverPrivacy,
        receiverPhotoRequest: widget.receiverPhotoRequest,
        isOnline: _isOtherUserOnline,
        lastSeen: _otherUserLastSeen,
        sharedPhotos: sharedPhotos,
      ),
    );
  }

  Widget _buildHeader(BuildContext context) {
    final String resolvedReceiverImage =
        resolveApiImageUrl(widget.receiverImage);
    return Container(
      padding: EdgeInsets.only(top: MediaQuery.of(context).padding.top + 8, left: 6, right: 6, bottom: 12),
      decoration: BoxDecoration(
        gradient: _primaryGradient,
        boxShadow: [
          BoxShadow(
            color: _accentColor.withOpacity(0.25),
            blurRadius: 8,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          IconButton(
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.arrow_back, color: Colors.white, size: 26),
          ),
          GestureDetector(
            onTap: () => _showChatUserProfileSheet(context),
            child: PrivacyUtils.buildPrivacyAwareAvatar(
              imageUrl: resolvedReceiverImage,
              privacy: widget.receiverPrivacy,
              photoRequest: widget.receiverPhotoRequest,
              radius: 22,
              backgroundColor: Colors.grey[600],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: GestureDetector(
              onTap: () => _showChatUserProfileSheet(context),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    "${widget.receiverName}",
                    style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w600,
                        fontSize: 17),
                  ),
                  if (_isOtherUserOnline)
                    const Text(
                      "online",
                      style: TextStyle(color: Colors.white70, fontSize: 13),
                    )
                  else if (_otherUserLastSeen != null)
                    Text(
                      _formatLastSeen(_otherUserLastSeen!),
                      style: const TextStyle(color: Colors.white70, fontSize: 12),
                    ),
                ],
              ),
            ),
          ),
          if (!kIsWeb)
            IconButton(
              onPressed: () {
                // Prevent starting a new call if one is already active
                if (CallOverlayManager().isCallActive) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('You are already in a call'),
                      duration: Duration(seconds: 2),
                    ),
                  );
                  return;
                }
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    settings: const RouteSettings(name: activeCallRouteName),
                    builder: (context) => CallScreen(
                      currentUserId: widget.currentUserId,
                      currentUserName: widget.currentUserName,
                      currentUserImage: widget.currentUserImage,
                      otherUserId: widget.receiverId,
                      otherUserName: widget.receiverName,
                      otherUserImage: widget.receiverImage,
                      chatRoomId: widget.chatRoomId,
                    ),
                  ),
                );
              },
              icon: const Icon(Icons.call, color: Colors.white),
            ),
          if (!kIsWeb)
            IconButton(
              onPressed: () {
                // Prevent starting a new call if one is already active
                if (CallOverlayManager().isCallActive) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('You are already in a call'),
                      duration: Duration(seconds: 2),
                    ),
                  );
                  return;
                }
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    settings: const RouteSettings(name: activeCallRouteName),
                    builder: (context) => VideoCallScreen(
                      currentUserId: widget.currentUserId,
                      currentUserName: widget.currentUserName,
                      currentUserImage: widget.currentUserImage,
                      otherUserId: widget.receiverId,
                      otherUserName: widget.receiverName,
                      otherUserImage: widget.receiverImage,
                      chatRoomId: widget.chatRoomId,
                    ),
                  ),
                );
              },
              icon: const Icon(Icons.videocam, color: Colors.white),
            ),
          PopupMenuButton<String>(
            onSelected: (String result) {
              if (result == 'block') {
                _showBlockProfileDialog(context);
              } else if (result == 'report') {
                _showReportDialog(context);
              }
            },
            itemBuilder: (BuildContext context) => <PopupMenuEntry<String>>[
              PopupMenuItem<String>(
                value: 'block',
                child: Row(
                  children: [
                    Icon(
                      _isBlocked ? Icons.check_circle : Icons.block,
                      color: _isBlocked ? Colors.green : Colors.red,
                      size: 20,
                    ),
                    const SizedBox(width: 8),
                    Text(_isBlocked ? 'Unblock Profile' : 'Block Profile'),
                  ],
                ),
              ),
              const PopupMenuItem<String>(
                value: 'report',
                child: Row(
                  children: [
                    Icon(Icons.flag, color: Colors.orange, size: 20),
                    SizedBox(width: 8),
                    Text('Report'),
                  ],
                ),
              ),
            ],
            icon: const Icon(Icons.more_vert, color: Colors.white),
          ),
        ],
      ),
    );
  }

  void _showReportDialog(BuildContext context) {
    String? selectedReason;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (BuildContext sheetContext) {
        return StatefulBuilder(
          builder: (_, setSheetState) {
            return Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(context).viewInsets.bottom + 16,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 12),
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: Colors.grey.shade300,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 20),
                    child: Row(
                      children: [
                        Icon(Icons.flag, color: Colors.orange, size: 24),
                        SizedBox(width: 8),
                        Text(
                          'Report Profile',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 20),
                    child: Text(
                      'Select reason for report:',
                      style: TextStyle(fontSize: 14, color: Colors.grey),
                    ),
                  ),
                  const SizedBox(height: 8),
                  ...AppConstants.reportReasons.map((reason) => RadioListTile<String>(
                        value: reason,
                        groupValue: selectedReason,
                        onChanged: (value) =>
                            setSheetState(() => selectedReason = value),
                        title: Text(reason,
                            style: const TextStyle(fontSize: 14)),
                        activeColor: Colors.red,
                        dense: true,
                      )),
                  const SizedBox(height: 12),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () =>
                                Navigator.of(sheetContext).pop(),
                            child: const Text('Cancel'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: selectedReason == null
                                ? null
                                : () async {
                                    Navigator.of(sheetContext).pop();
                                    await _submitReport(
                                        context, selectedReason!);
                                  },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.red,
                            ),
                            child: const Text(
                              'Report',
                              style: TextStyle(color: Colors.white),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _submitReport(BuildContext context, String reason) async {
    try {
      final adminUserId = AppConstants.adminUserId;
      final reportMessage =
          'I have reported this profile.\n\nReason: $reason\n\nReported Profile ID: ${widget.receiverId}';
      final reportPayload = jsonEncode({
        'reportMessage': reportMessage,
        'reportedUserId': widget.receiverId,
        'reportedUserName': widget.receiverName,
        'reportReason': reason,
      });

      final List<String> ids = [widget.currentUserId, adminUserId]..sort();
      final adminChatRoomId = ids.join('_');
      _socketService.sendMessage(
        chatRoomId: adminChatRoomId,
        senderId: widget.currentUserId,
        receiverId: adminUserId,
        message: reportPayload,
        messageType: 'report',
        messageId: _uuid.v4(),
        user1Name: widget.currentUserName,
        user2Name: 'Admin',
        user1Image: widget.currentUserImage,
        user2Image: '',
      );

      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Profile reported successfully!'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to report: $e')),
        );
      }
    }
  }


  void _showBlockProfileDialog(BuildContext context) async {
    if (_isBlocked) {
      // Show unblock confirmation
      showDialog<void>(
        context: context,
        builder: (BuildContext dialogContext) {
          return AlertDialog(
            title: const Text('Unblock Profile'),
            content: const Text('Are you sure you want to unblock this profile? They will be able to contact you again.'),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text('CANCEL'),
              ),
              TextButton(
                onPressed: () => _unblockUser(dialogContext),
                child: Text(
                  'UNBLOCK',
                  style: TextStyle(color: Theme.of(context).primaryColor),
                ),
              ),
            ],
          );
        },
      );
    } else {
      // Show block confirmation
      showDialog<void>(
        context: context,
        builder: (BuildContext dialogContext) {
          return AlertDialog(
            title: const Text('Block Profile'),
            content: const Text('Are you sure you want to block this profile? They will not be able to contact you or see your profile.'),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text('CANCEL'),
              ),
              TextButton(
                onPressed: () => _blockUser(dialogContext),
                child: Text(
                  'BLOCK',
                  style: TextStyle(color: Theme.of(context).primaryColor),
                ),
              ),
            ],
          );
        },
      );
    }
  }

  Future<void> _blockUser(BuildContext dialogContext) async {
    setState(() {
      _isLoadingBlock = true;
    });

    Navigator.of(dialogContext).pop(); // Close dialog

    try {
      final prefs = await SharedPreferences.getInstance();
      final userDataString = prefs.getString('user_data');
      final userData = jsonDecode(userDataString!);
      final myId = userData["id"].toString();

      final service = ProfileService();
      final result = await service.blockUser(
        myId: myId,
        userId: widget.receiverId,
      );

      if (mounted) {
        if (result['status'] == 'success') {
          setState(() {
            _isBlocked = true;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Profile blocked successfully!'),
              backgroundColor: Colors.green,
            ),
          );
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(result['message'] ?? 'Failed to block user'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoadingBlock = false;
        });
      }
    }
  }

  Future<void> _unblockUser(BuildContext dialogContext) async {
    setState(() {
      _isLoadingBlock = true;
    });

    Navigator.of(dialogContext).pop(); // Close dialog

    try {
      final prefs = await SharedPreferences.getInstance();
      final userDataString = prefs.getString('user_data');
      final userData = jsonDecode(userDataString!);
      final myId = userData["id"].toString();

      final service = ProfileService();
      final result = await service.unblockUser(
        myId: myId,
        userId: widget.receiverId,
      );

      if (mounted) {
        if (result['status'] == 'success') {
          setState(() {
            _isBlocked = false;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Profile unblocked successfully!'),
              backgroundColor: Colors.green,
            ),
          );
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(result['message'] ?? 'Failed to unblock user'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoadingBlock = false;
        });
      }
    }
  }

}

// ---------------------------------------------------------------------------
// Swipe-to-reply wrapper
// Encapsulates all swipe state so that per-frame offset updates only rebuild
// this single widget, not the entire chat screen.
// ---------------------------------------------------------------------------
class _SwipeToReplyWrapper extends StatefulWidget {
  const _SwipeToReplyWrapper({
    required this.child,
    required this.isMine,
    required this.onReply,
    this.onDragStart,
    this.onDragEnd,
  });

  final Widget child;
  final bool isMine;
  final VoidCallback onReply;
  final VoidCallback? onDragStart;
  final VoidCallback? onDragEnd;

  @override
  State<_SwipeToReplyWrapper> createState() => _SwipeToReplyWrapperState();
}

class _SwipeToReplyWrapperState extends State<_SwipeToReplyWrapper>
    with SingleTickerProviderStateMixin {
  double _dragOffset = 0.0;
  bool _isDragging = false;
  late final AnimationController _animCtrl;
  late final Animation<double> _anim;

  static const double _kThreshold = 60.0;

  @override
  void initState() {
    super.initState();
    _animCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 200),
    );
    _anim = Tween<double>(begin: 0.0, end: 1.0)
        .animate(CurvedAnimation(parent: _animCtrl, curve: Curves.easeOut));
  }

  @override
  void dispose() {
    _animCtrl.dispose();
    super.dispose();
  }

  void _onDragStart(DragStartDetails _) {
    _isDragging = true;
    _dragOffset = 0.0;
    _animCtrl.forward();
    widget.onDragStart?.call();
  }

  void _onDragUpdate(DragUpdateDetails details) {
    if (!_isDragging) return;
    final newOffset = _dragOffset + details.delta.dx;
    // Own messages swipe left; others' messages swipe right
    if (widget.isMine && newOffset > 0) return;
    if (!widget.isMine && newOffset < 0) return;
    setState(() {
      _dragOffset = newOffset.clamp(-100.0, 100.0);
    });
  }

  void _onDragEnd(DragEndDetails _) {
    if (!_isDragging) return;
    _isDragging = false;
    if (_dragOffset.abs() >= _kThreshold) {
      HapticFeedback.lightImpact();
      widget.onReply();
    }
    _animCtrl.reverse().then((_) {
      if (mounted) setState(() => _dragOffset = 0.0);
    });
    widget.onDragEnd?.call();
  }

  @override
  Widget build(BuildContext context) {
    final double offset = _dragOffset;
    return GestureDetector(
      onHorizontalDragStart: _onDragStart,
      onHorizontalDragUpdate: _onDragUpdate,
      onHorizontalDragEnd: _onDragEnd,
      child: Stack(
        children: [
          AnimatedBuilder(
            animation: _anim,
            builder: (_, __) => Transform.translate(
              offset: Offset(offset * _anim.value, 0),
              child: widget.child,
            ),
          ),
          if (offset.abs() > 8)
            Positioned.fill(
              child: AnimatedBuilder(
                animation: _anim,
                builder: (_, __) {
                  final opacity =
                      (offset.abs() / 100.0).clamp(0.0, 1.0) * _anim.value;
                  return Row(
                    mainAxisAlignment: widget.isMine
                        ? MainAxisAlignment.start
                        : MainAxisAlignment.end,
                    children: [
                      if (widget.isMine)
                        Padding(
                          padding: const EdgeInsets.only(left: 20),
                          child: Icon(Icons.reply,
                              color: Colors.grey.withOpacity(opacity)),
                        ),
                      if (!widget.isMine)
                        Padding(
                          padding: const EdgeInsets.only(right: 20),
                          child: Icon(Icons.reply,
                              color: Colors.grey.withOpacity(opacity)),
                        ),
                    ],
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}

class _SharedPhoto {
  final String url;
  final bool isFromReceiver;

  const _SharedPhoto({
    required this.url,
    required this.isFromReceiver,
  });
}

// ─── Mini-profile bottom sheet shown when tapping avatar / name in chat ───────

class _ChatUserProfileSheet extends StatelessWidget {
  final String receiverId;
  final String receiverName;
  final String receiverImage;
  final String? receiverPrivacy;
  final String? receiverPhotoRequest;
  final bool isOnline;
  final DateTime? lastSeen;
  final List<_SharedPhoto> sharedPhotos;

  const _ChatUserProfileSheet({
    required this.receiverId,
    required this.receiverName,
    required this.receiverImage,
    this.receiverPrivacy,
    this.receiverPhotoRequest,
    required this.isOnline,
    this.lastSeen,
    required this.sharedPhotos,
  });

  void _openPhotoViewer(BuildContext context, int index) {
    if (sharedPhotos.isEmpty) return;
    showDialog(
      context: context,
      builder: (ctx) => _GalleryViewerDialog(
        urls: sharedPhotos.map((p) => p.url).toList(),
        initialIndex: index,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bool canShowPhotos = PrivacyUtils.shouldShowClearImage(
      privacy: receiverPrivacy,
      photoRequest: receiverPhotoRequest,
    );

    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Drag handle
            Container(
              margin: const EdgeInsets.only(top: 12, bottom: 8),
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            // Avatar + name + status
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
              child: Row(
                children: [
                  PrivacyUtils.buildPrivacyAwareAvatar(
                    imageUrl: receiverImage,
                    privacy: receiverPrivacy,
                    photoRequest: receiverPhotoRequest,
                    radius: 32,
                    backgroundColor: Colors.grey[300],
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          receiverName,
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: Colors.black87,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Container(
                              width: 8,
                              height: 8,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: isOnline ? Colors.green : Colors.grey,
                              ),
                            ),
                            const SizedBox(width: 5),
                            Text(
                              isOnline
                                  ? 'Online'
                                  : (lastSeen != null
                                      ? 'Last seen ${_formatLastSeen(lastSeen!)}'
                                      : 'Offline'),
                              style: TextStyle(
                                fontSize: 13,
                                color: isOnline ? Colors.green : Colors.grey[600],
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            // Shared photos section
            if (sharedPhotos.isNotEmpty) ...[
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 6),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Shared Photos',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: Colors.black87,
                      ),
                    ),
                    GestureDetector(
                      onTap: () => _openPhotoViewer(context, 0),
                      child: const Text(
                        'View All',
                        style: TextStyle(
                          fontSize: 13,
                          color: Colors.red,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(
                height: 90,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: sharedPhotos.length > 8
                      ? 8
                      : sharedPhotos.length,
                  itemBuilder: (ctx, i) {
                    final isLastVisible = i == 7 &&
                        sharedPhotos.length > 8;
                    final photo = sharedPhotos[i];
                    final shouldBlur = photo.isFromReceiver && !canShowPhotos;
                    return GestureDetector(
                      onTap: () => _openPhotoViewer(context, i),
                      child: Container(
                        width: 80,
                        height: 80,
                        margin: const EdgeInsets.only(right: 8),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(10),
                          color: Colors.grey[200],
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(10),
                          child: Stack(
                            fit: StackFit.expand,
                            children: [
                              shouldBlur
                                  ? Stack(
                                      fit: StackFit.expand,
                                      children: [
                                        Image.network(
                                          photo.url,
                                          fit: BoxFit.cover,
                                        ),
                                        BackdropFilter(
                                          filter: ui.ImageFilter.blur(
                                              sigmaX: 8, sigmaY: 8),
                                          child: Container(
                                            color:
                                                Colors.black.withOpacity(0.3),
                                          ),
                                        ),
                                        const Center(
                                          child: Icon(Icons.lock_outline,
                                              color: Colors.white, size: 24),
                                        ),
                                      ],
                                    )
                                  : Image.network(
                                      photo.url,
                                      fit: BoxFit.cover,
                                      errorBuilder: (_, __, ___) => const Icon(
                                        Icons.photo,
                                        color: Colors.grey,
                                        size: 30,
                                      ),
                                    ),
                              if (isLastVisible)
                                Container(
                                  color: Colors.black54,
                                  child: Center(
                                    child: Text(
                                      '+${sharedPhotos.length - 7}',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 16,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: 8),
              const Divider(height: 1),
            ],
            // View Profile button
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 16),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () {
                    Navigator.pop(context);
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) =>
                            UserProfilePage(userId: receiverId),
                      ),
                    );
                  },
                  icon: const Icon(Icons.person_outline),
                  label: const Text('View Profile'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.red,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    textStyle: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static String _formatLastSeen(DateTime lastSeen) {
    final diff = DateTime.now().difference(lastSeen);
    if (diff.inMinutes < 1) return 'just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    return '${diff.inDays}d ago';
  }
}

/// Full-screen gallery viewer dialog (swipe through multiple images).
class _GalleryViewerDialog extends StatefulWidget {
  final List<String> urls;
  final int initialIndex;

  const _GalleryViewerDialog({required this.urls, required this.initialIndex});

  @override
  State<_GalleryViewerDialog> createState() => _GalleryViewerDialogState();
}

class _GalleryViewerDialogState extends State<_GalleryViewerDialog> {
  late final PageController _pageController;
  late int _current;

  @override
  void initState() {
    super.initState();
    _current = widget.initialIndex;
    _pageController = PageController(initialPage: widget.initialIndex);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: Colors.black,
      insetPadding: EdgeInsets.zero,
      child: Stack(
        children: [
          PageView.builder(
            controller: _pageController,
            itemCount: widget.urls.length,
            onPageChanged: (i) => setState(() => _current = i),
            itemBuilder: (ctx, i) => InteractiveViewer(
              child: Center(
                child: Image.network(
                  widget.urls[i],
                  fit: BoxFit.contain,
                  errorBuilder: (_, __, ___) => const Icon(
                    Icons.broken_image,
                    color: Colors.white54,
                    size: 64,
                  ),
                ),
              ),
            ),
          ),
          // Close button
          Positioned(
            top: 40,
            right: 16,
            child: IconButton(
              icon: const Icon(Icons.close, color: Colors.white, size: 28),
              onPressed: () => Navigator.pop(context),
            ),
          ),
          // Page indicator
          if (widget.urls.length > 1)
            Positioned(
              bottom: 24,
              left: 0,
              right: 0,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(widget.urls.length, (i) => Container(
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  width: _current == i ? 10 : 6,
                  height: _current == i ? 10 : 6,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: _current == i ? Colors.white : Colors.white38,
                  ),
                )),
              ),
            ),
        ],
      ),
    );
  }
}
