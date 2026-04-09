import 'package:flutter/material.dart' hide ErrorWidget;
import 'package:flutter/services.dart';
import 'package:ms2026/otherprofile/profileheader.dart';
import 'package:ms2026/otherprofile/profiletabs.dart';
import 'package:ms2026/otherprofile/requestdiag.dart';
import 'package:ms2026/otherprofile/service_profile.dart';
import 'package:ms2026/purposal/purposalScreen.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:async';
import 'dart:convert';

import '../Notification/notification_inbox_service.dart';
import '../pushnotification/pushservice.dart';
import '../service/socket_service.dart';
import '../utils/privacy_utils.dart';
import 'gallerysection.dart';
import 'loadingerror.dart';
import 'modelprofile.dart';



class UserProfilePage extends StatefulWidget {
    final dynamic userId;

 UserProfilePage({Key? key, required this.userId}) : super(key: key);

  @override
  _UserProfilePageState createState() => _UserProfilePageState();
}

class _UserProfilePageState extends State<UserProfilePage> {
  int get _parsedUserId => int.parse(widget.userId.toString());

  ProfileData? _profileData;
  bool _isLoading = true;
  String _errorMessage = '';
  bool _showBlurredImage = true;
  bool _hasRequestedPhoto = false;
  String? _chatRequestStatus;
  List<GalleryImage> _galleryImages = [];
  bool _showPopup = false;
  String _popupMessage = '';
  String _docStatus = 'not_uploaded';

  final ProfileService _profileService = ProfileService();

  bool _isOtherUserOnline = false;
  DateTime? _otherUserLastSeen;
  StreamSubscription? _onlineStatusSub;

  @override
  void initState() {
    super.initState();
    _loadProfileData();
    _checkDocumentStatus();
    _startOnlineStatusListener();
  }

  @override
  void dispose() {
    _onlineStatusSub?.cancel();
    super.dispose();
  }

  void _startOnlineStatusListener() {
    final targetId = widget.userId.toString();

    // Fetch initial status from server
    SocketService().getUserStatus(targetId).then((data) {
      if (!mounted) return;
      final bool isOnline = data['isOnline'] == true;
      final DateTime? lastSeen = SocketService.parseTimestamp(data['lastSeen']);
      final bool recentlySeen = lastSeen != null &&
          DateTime.now().difference(lastSeen).inMinutes < 5;
      setState(() {
        _isOtherUserOnline = isOnline || recentlySeen;
        _otherUserLastSeen = lastSeen;
      });
    });

    // Subscribe to real-time status changes via Socket.IO
    _onlineStatusSub?.cancel();
    _onlineStatusSub = SocketService().onUserStatusChange.listen((data) {
      if (!mounted) return;
      final uid = data['userId']?.toString() ?? '';
      if (uid != targetId) return;
      final bool isOnline = data['isOnline'] == true;
      final DateTime? lastSeen = SocketService.parseTimestamp(data['lastSeen']);
      final bool recentlySeen = lastSeen != null &&
          DateTime.now().difference(lastSeen).inMinutes < 5;
      if (_isOtherUserOnline != (isOnline || recentlySeen) ||
          _otherUserLastSeen != lastSeen) {
        setState(() {
          _isOtherUserOnline = isOnline || recentlySeen;
          _otherUserLastSeen = lastSeen;
        });
      }
    });
  }

  Future<void> _loadProfileData() async {
    final prefs = await SharedPreferences.getInstance();
    final userDataString = prefs.getString('user_data');


      final userData = json.decode(userDataString!);
       final userid = int.tryParse(userData["id"].toString());




    try {
      final profileResponse = await _profileService.fetchProfileData(_parsedUserId, userid!);

      if (profileResponse['status'] == 'success') {
        setState(() {
          _profileData = ProfileData.fromJson(profileResponse['data']);
        });

        _updatePhotoPrivacyStatus();
        await _fetchGalleryImages();

        setState(() {
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = profileResponse['message'] ?? 'Failed to load profile';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = e.toString();
        _isLoading = false;
      });
    }
  }

  /// Calculate photo privacy status from profile data
  /// Uses consistent logic: privacy == 'free' OR photo_request == 'accepted'
  void _updatePhotoPrivacyStatus() {
    if (_profileData == null) return;

    final privacy = _profileData!.personalDetail['privacy']?.toString().toLowerCase();
    final photoRequest = _profileData!.personalDetail['photo_request']?.toString().toLowerCase();
    final chatRequest = _profileData!.personalDetail['chat_request']?.toString().toLowerCase();

    // Use PrivacyUtils for consistent logic across the app
    final shouldShowClear = PrivacyUtils.shouldShowClearImage(
      privacy: privacy,
      photoRequest: photoRequest,
    );

    // Check if user has already requested photo access
    final hasRequested = photoRequest != null &&
                        photoRequest.isNotEmpty &&
                        photoRequest != 'null' &&
                        photoRequest != 'not_sent';

    setState(() {
      _showBlurredImage = !shouldShowClear;
      _hasRequestedPhoto = hasRequested;
      _chatRequestStatus = chatRequest;
    });
  }


  Future<void> _fetchGalleryImages() async {
    final images = await _profileService.fetchGalleryImages(_parsedUserId);
    setState(() {
      _galleryImages = images;
    });
  }

  Future<void> _checkDocumentStatus() async {
    final prefs = await SharedPreferences.getInstance();
    final userDataString = prefs.getString('user_data');
    if (userDataString != null) {
      final userData = json.decode(userDataString);
      setState(() {
        _docStatus = userData['docstatus'] ?? 'not_uploaded';
      });
    }
  }

  void _showRequestSentPopup(String message) {
    setState(() {
      _popupMessage = message;
      _showPopup = true;
    });

    Future.delayed(const Duration(seconds: 3), () {
      if (mounted) {
        setState(() {
          _showPopup = false;
        });
      }
    });
  }

  void _showSendRequestDialog() {
    if (_profileData == null) return;

    final photoRequest = _profileData!.personalDetail['photo_request']?.toString().toLowerCase();
    final chatRequest = _profileData!.personalDetail['chat_request']?.toString().toLowerCase();

    showDialog(
      context: context,
      builder: (context) => RequestDialog(
        receiverName: '${_profileData!.personalDetail['lastName']}',
        onSendRequest: _sendRequest,
        existingPhotoRequest: photoRequest,
        existingChatRequest: chatRequest,
        onCancelRequest: _cancelRequest,
        onSeeAllRequests: _navigateToAllRequests,
      ),
    );
  }

  void _cancelRequest() async {
    try {
      final senderId = await ProfileService.getCurrentUserId();
      if (senderId == null) {
        _showRequestSentPopup('Please login to cancel request');
        return;
      }

      final photoRequest = _profileData!.personalDetail['photo_request']?.toString().toLowerCase();
      final chatRequest = _profileData!.personalDetail['chat_request']?.toString().toLowerCase();

      final hasPhotoRequest = photoRequest == 'pending';
      final hasChatRequest = chatRequest == 'pending';

      if (!hasPhotoRequest && !hasChatRequest) {
        _showRequestSentPopup('No pending request to cancel');
        return;
      }

      // If both are pending, ask which one to cancel
      String? requestTypeToCancel;
      if (hasPhotoRequest && hasChatRequest) {
        requestTypeToCancel = await showDialog<String>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Cancel Request'),
            content: const Text('Which request would you like to cancel?'),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Cancel'),
              ),
              TextButton(
                onPressed: () => Navigator.pop(context, 'Photo'),
                child: const Text('Photo Request'),
              ),
              TextButton(
                onPressed: () => Navigator.pop(context, 'Chat'),
                child: const Text('Chat Request'),
              ),
              TextButton(
                onPressed: () => Navigator.pop(context, 'Both'),
                child: const Text('Both'),
              ),
            ],
          ),
        );

        if (requestTypeToCancel == null) return;
      } else {
        requestTypeToCancel = hasPhotoRequest ? 'Photo' : 'Chat';
      }

      // Show loading
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const Center(
          child: CircularProgressIndicator(),
        ),
      );

      bool success = false;
      String message = '';

      if (requestTypeToCancel == 'Both') {
        // Cancel both requests
        final photoResponse = await _profileService.cancelRequest(
          senderId: senderId,
          receiverId: _parsedUserId,
          requestType: 'Photo',
        );

        final chatResponse = await _profileService.cancelRequest(
          senderId: senderId,
          receiverId: _parsedUserId,
          requestType: 'Chat',
        );

        success = photoResponse['success'] == true && chatResponse['success'] == true;
        message = success ? 'All requests cancelled successfully' : 'Some requests failed to cancel';
      } else {
        // Cancel single request
        final response = await _profileService.cancelRequest(
          senderId: senderId,
          receiverId: _parsedUserId,
          requestType: requestTypeToCancel,
        );

        success = response['success'] == true;
        message = response['message'] ?? 'Request cancelled';
      }

      // Close loading dialog
      if (mounted) Navigator.pop(context);

      _showRequestSentPopup(message);

      if (success) {
        // Reload profile to update request status
        await _loadProfileData();
      }
    } catch (e) {
      if (mounted) Navigator.pop(context);
      _showRequestSentPopup('Error: $e');
    }
  }

  void _navigateToAllRequests() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const ProposalsPage()),
    );
  }

  Future<void> _sendRequest(String requestType) async {
    try {
      final senderId = await ProfileService.getCurrentUserId();
      if (senderId == null) {
        _showRequestSentPopup('Please login to send request');
        return;
      }

      final response = await _profileService.sendRequest(
        senderId: senderId,
        receiverId: _parsedUserId,
        requestType: requestType,
      );

      if (response['success'] == true) {
        bool success = await NotificationService.sendRequestNotification(
          recipientUserId:  widget.userId.toString(),       // ID of the user receiving the request
          senderName: "MS:${senderId}",       // Name of the sender
          senderId: senderId.toString(),              // ID of the sender
          requestType: requestType,
        );

        if(success) {
          print("Request notification sent!");
        } else {
          print("Failed to send notification.");
        }
        await NotificationInboxService.recordOutgoingRequest(
          recipientUserId: widget.userId.toString(),
          requestType: requestType,
          recipientName: '${_profileData?.personalDetail['lastName'] ?? ''}'.trim(),
        );
        _showRequestSentPopup(response['message'] ?? 'Request sent successfully!');

        if (requestType == 'Photo') {
          setState(() {
            _hasRequestedPhoto = true;
          });
        }
      } else {
        _showRequestSentPopup(response['message'] ?? 'Failed to send request');
      }
    } catch (e) {
      _showRequestSentPopup('Error: $e');
    }
  }

  void _requestPhotoAccess() {

        _showSendRequestDialog();

  }



  Widget _buildPopupMessage() {
    return AnimatedOpacity(
      opacity: _showPopup ? 1.0 : 0.0,
      duration: const Duration(milliseconds: 300),
      child: Container(
        padding: const EdgeInsets.all(16),
        margin: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Colors.green,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.2),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          children: [
            const Icon(Icons.check_circle, color: Colors.white, size: 24),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                _popupMessage,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
            IconButton(
              icon: const Icon(Icons.close, color: Colors.white, size: 20),
              onPressed: () {
                setState(() {
                  _showPopup = false;
                });
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile Details'),
        backgroundColor: Colors.red,
        foregroundColor: Colors.white,
        elevation: 0,
        systemOverlayStyle: SystemUiOverlayStyle(
          statusBarColor: Colors.white,
          statusBarIconBrightness: Brightness.dark,
          statusBarBrightness: Brightness.light,
          systemStatusBarContrastEnforced: false,
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadProfileData,
          ),
        ],
      ),
      body: Stack(
        children: [
          if (_isLoading)
            const LoadingWidget()
          else if (_errorMessage.isNotEmpty)
            ErrorWidget(
              errorMessage: _errorMessage,
              onRetry: _loadProfileData,
            )
          else if (_profileData != null)
              _buildProfileContent()
            else
              const Center(child: Text('No profile data available')),

          if (_showPopup) _buildPopupMessage(),
        ],
      ),
    );
  }

  Widget _buildProfileContent() {
    // Create a header instance to access the privacy banner
    final profileHeader = ProfileHeader(
      personalDetail: _profileData!.personalDetail,
      hasRequestedPhoto: _hasRequestedPhoto,
      onRequestPhotoAccess: _requestPhotoAccess,
      id: widget.userId.toString(),
      isOnline: _isOtherUserOnline,
      lastSeen: _otherUserLastSeen,
    );

    return SingleChildScrollView(
      child: Column(
        children: [
          profileHeader,

          // Add privacy notice banner below the profile header
          profileHeader.buildPrivacyNoticeBanner(),

          GallerySection(
            galleryImages: _galleryImages,
            showBlurredImage: _showBlurredImage,
            hasRequestedPhoto: _hasRequestedPhoto,
            onRequestAccess: _requestPhotoAccess,
          ),

          ProfileTabs(
            personalDetail: _profileData!.personalDetail,
            familyDetail: _profileData!.familyDetail,
            lifestyle: _profileData!.lifestyle,
            partner: _profileData!.partner, id: widget.userId.toString(),
          ),
        ],
      ),
    );
  }
}
