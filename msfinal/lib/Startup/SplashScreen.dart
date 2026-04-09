import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../Auth/Screen/signupscreen10.dart';
import '../Auth/Screen/signupscreen2.dart';
import '../Auth/Screen/signupscreen3.dart';
import '../Auth/Screen/signupscreen4.dart';
import '../Auth/Screen/signupscreen5.dart';
import '../Auth/Screen/signupscreen6.dart';
import '../Auth/Screen/signupscreen7.dart';
import '../Auth/Screen/signupscreen8.dart';
import '../Auth/Screen/signupscreen9.dart';
import '../Auth/SuignupModel/signup_model.dart';
import '../Chat/ChatlistScreen.dart';
import '../Home/Screen/HomeScreenPage.dart';
import '../ReUsable/Navbar.dart';
import '../online/onlineservice.dart';
import '../profile/myprofile.dart';
import '../purposal/purposalScreen.dart';
import '../pushnotification/pushservice.dart';
import '../service/pagenocheck.dart';
import '../webrtc/webrtc.dart';
import '../constant/app_colors.dart';
import '../constant/app_dimensions.dart';
import '../navigation/app_navigation.dart';
import 'MainControllere.dart';
import 'onboarding.dart';

import 'dart:convert';
import 'package:ms2026/config/app_endpoints.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with TickerProviderStateMixin {
  Map<String, dynamic>? _versionData;
  bool _isCheckingVersion = true;
  String? _errorMessage;
  bool _isFirstLaunch = true; // Track if this is the first app launch

  // Prevents double-navigation when the background version check completes
  // after the splash screen has already navigated away.
  bool _navigationStarted = false;

  // Completes when the entrance animation finishes.
  // Guaranteed to be set in initState before any async callback can use it.
  late Future<void> _animationCompleted;

  // Current app versions - Update these with your actual current versions
  final String currentAndroidVersion = '24.0.0'; // Your current Android version
  final String currentIOSVersion = '1.0.0';     // Your current iOS version

  // Animation controllers - nullable to avoid initialization when not needed
  AnimationController? _entranceController;
  AnimationController? _pulseController;
  AnimationController? _dotsController;

  // Entrance animations
  Animation<double>? _logoScale;
  Animation<double>? _logoOpacity;
  Animation<double>? _textOpacity;
  Animation<Offset>? _textSlide;
  Animation<double>? _taglineOpacity;

  // Pulse (breathing) scale while loading
  Animation<double>? _pulseScale;

  @override
  void initState() {
    super.initState();
    _initializeApp();
  }

  Future<void> _initializeApp() async {
    // Check first launch status before doing anything else
    await _checkFirstLaunch();

    // Only setup animations on first launch to save resources
    if (_isFirstLaunch) {
      _setupAnimations();
      // TickerCanceled is expected when the widget disposes while the animation
      // is still running (e.g. user leaves the app); swallow it intentionally.
      _animationCompleted = _entranceController!.forward().orCancel
          .catchError((Object e) {
            if (e is! TickerCanceled) debugPrint('Splash animation error: $e');
          });
    } else {
      // Skip animation entirely on subsequent launches
      _animationCompleted = Future.value();
    }

    // Proceed to navigation immediately — version check runs in the background
    // so a slow or unreachable server never blocks the user from opening the app.
    _proceedWithNavigation();

    // Check for app updates in background. The result never delays navigation;
    // if an update is available a dialog is shown on top of the current screen.
    _checkAppVersionInBackground();
  }

  Future<void> _checkFirstLaunch() async {
    final prefs = await SharedPreferences.getInstance();
    final hasLaunchedBefore = prefs.getBool('has_launched_before') ?? false;

    if (mounted) {
      setState(() {
        _isFirstLaunch = !hasLaunchedBefore;
      });
    }

    // Mark that the app has been launched
    if (!hasLaunchedBefore) {
      await prefs.setBool('has_launched_before', true);
    }
  }

  void _setupAnimations() {
    // Main entrance: 1100 ms
    _entranceController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    );

    // Slow pulse while loading: 1600 ms repeat
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1600),
    )..repeat(reverse: true);

    // 3-dot bounce loop: 900 ms repeat
    _dotsController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..repeat();

    // Logo zooms in from 0.3 → 1.0 with elastic spring (0 – 65%)
    _logoScale = Tween<double>(begin: 0.3, end: 1.0).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.0, 0.65, curve: Curves.elasticOut),
      ),
    );

    // Logo fades in quickly (0 – 35%)
    _logoOpacity = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.0, 0.35, curve: Curves.easeIn),
      ),
    );

    // Subtle breathing pulse while loading (1.0 → 1.04)
    _pulseScale = Tween<double>(begin: 1.0, end: 1.04).animate(
      CurvedAnimation(parent: _pulseController!, curve: Curves.easeInOut),
    );

    // App-name slides up + fades in (50 – 82%)
    _textOpacity = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.50, 0.82, curve: Curves.easeOut),
      ),
    );
    _textSlide = Tween<Offset>(
      begin: const Offset(0, 0.45),
      end: Offset.zero,
    ).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.50, 0.82, curve: Curves.easeOutCubic),
      ),
    );

    // Tagline fades in last (68 – 100%)
    _taglineOpacity = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.68, 1.0, curve: Curves.easeOut),
      ),
    );
  }

  @override
  void dispose() {
    _entranceController?.dispose();
    _pulseController?.dispose();
    _dotsController?.dispose();
    super.dispose();
  }

  /// Background version check — runs after navigation has already started so
  /// it never blocks the user from reaching the app.
  ///
  /// • Within 6 h of the last check  → skipped entirely.
  /// • HTTP success with new version  → shows update dialog on the current screen.
  /// • HTTP error / timeout           → saves a shortened cache time (30 min) so
  ///   we retry later but don't hammer the server on every launch.
  Future<void> _checkAppVersionInBackground() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      // 0 means "never checked" → always proceeds on fresh install.
      final lastCheck = prefs.getInt('last_version_check_ok') ?? 0;
      final msElapsed = DateTime.now().millisecondsSinceEpoch - lastCheck;
      const sixHoursMs = 6 * 60 * 60 * 1000;
      if (msElapsed < sixHoursMs) return; // Checked recently — nothing to do.

      final response = await http.get(
        Uri.parse('${kApiBaseUrl}/app.php'),
      ).timeout(const Duration(seconds: 5));

      // Always update the cache after a real HTTP attempt so that a server
      // returning non-success or an update-not-needed result doesn't trigger
      // a repeat check on the very next launch.
      await prefs.setInt(
          'last_version_check_ok', DateTime.now().millisecondsSinceEpoch);

      if (response.statusCode != 200) return;

      final data = jsonDecode(response.body);
      if (data['success'] != true) return;

      _versionData = data['data'];
      if (mounted) setState(() => _isCheckingVersion = false);

      _showUpdateDialogIfNeeded();
    } catch (_) {
      // Network unavailable or timeout.  Save a shortened cache timestamp so
      // that we retry in ~30 min rather than on every single launch.
      try {
        final prefs = await SharedPreferences.getInstance();
        final retryAfter30Min = DateTime.now().millisecondsSinceEpoch -
            (6 * 60 * 60 * 1000 - 30 * 60 * 1000);
        await prefs.setInt('last_version_check_ok', retryAfter30Min);
      } catch (_) {}
    }
  }

  /// Shows an update dialog on whichever screen is currently active.
  /// Safe to call after the splash screen has already navigated away because it
  /// uses the global [navigatorKey] context instead of the widget's own context.
  void _showUpdateDialogIfNeeded() {
    if (_versionData == null) return;

    final String serverAndroidVersion =
        _versionData!['android_version']?.toString() ?? '';
    final String serverIOSVersion =
        _versionData!['ios_version']?.toString() ?? '';
    final bool forceUpdate = _versionData!['force_update'] == true;
    final String description =
        _versionData!['description']?.toString() ?? '';
    final String appLink = _versionData!['app_link']?.toString() ?? '';

    if (kIsWeb) return;

    bool updateNeeded = false;
    String? platformVersion;
    final isAndroid = defaultTargetPlatform == TargetPlatform.android;
    final isIOS = defaultTargetPlatform == TargetPlatform.iOS;

    if (isAndroid && serverAndroidVersion.isNotEmpty) {
      updateNeeded =
          _compareVersions(currentAndroidVersion, serverAndroidVersion);
      platformVersion = serverAndroidVersion;
    } else if (isIOS && serverIOSVersion.isNotEmpty) {
      updateNeeded = _compareVersions(currentIOSVersion, serverIOSVersion);
      platformVersion = serverIOSVersion;
    }

    if (!updateNeeded) return;

    // Use the global navigator key so the dialog works even after the splash
    // screen has been replaced by the destination screen.
    final ctx = mounted ? context : navigatorKey.currentContext;
    if (ctx == null) return;

    _showUpdateDialog(
      forceUpdate, description, appLink, platformVersion!,
      dialogContext: ctx,
    );
  }

  bool _compareVersions(String current, String server) {
    // Simple version comparison (can be enhanced for more complex versioning)
    List<int> currentParts = current.split('.').map(int.parse).toList();
    List<int> serverParts = server.split('.').map(int.parse).toList();

    for (int i = 0; i < currentParts.length; i++) {
      if (i >= serverParts.length) return false;
      if (serverParts[i] > currentParts[i]) return true;
      if (serverParts[i] < currentParts[i]) return false;
    }
    return serverParts.length > currentParts.length;
  }

  void _showUpdateDialog(bool forceUpdate, String description, String appLink,
      String newVersion, {BuildContext? dialogContext}) {
    // Use the supplied context (e.g. navigatorKey.currentContext when called
    // from the background check) or fall back to the widget's own context.
    final ctx = dialogContext ?? context;
    showDialog(
      context: ctx,
      barrierDismissible: !forceUpdate,
      builder: (BuildContext dialogCtx) {
        return PopScope(
          canPop: !forceUpdate,
          child: AlertDialog(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(20),
            ),
            title: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: forceUpdate ? AppColors.error.withOpacity(0.1) : AppColors.info.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(
                    forceUpdate ? Icons.system_update_alt : Icons.update,
                    color: forceUpdate ? AppColors.error : AppColors.info,
                    size: 24,
                  ),
                ),
                AppSpacing.horizontalMD,
                Expanded(
                  child: Text(
                    forceUpdate ? 'Update Required' : 'New Update Available',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                    ),
                  ),
                ),
              ],
            ),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    'Version $newVersion',
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      color: AppColors.primary,
                      fontSize: 14,
                    ),
                  ),
                ),
                AppSpacing.verticalMD,
                Text(
                  description,
                  style: const TextStyle(
                    fontSize: 14,
                    color: AppColors.textPrimary,
                  ),
                ),
                if (forceUpdate) ...[
                  AppSpacing.verticalMD,
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.error.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: AppColors.error.withOpacity(0.3),
                        width: 1,
                      ),
                    ),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.warning_rounded,
                          color: AppColors.error,
                          size: 20,
                        ),
                        AppSpacing.horizontalSM,
                        const Expanded(
                          child: Text(
                            'You must update to continue using the app.',
                            style: TextStyle(
                              color: AppColors.error,
                              fontSize: 12,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
            actions: [
              if (!forceUpdate)
                TextButton(
                  onPressed: () {
                    Navigator.of(dialogCtx).pop();
                    // _proceedWithNavigation() is a no-op once navigation has
                    // started, so it's safe to call here for the rare case
                    // where the background dialog fires before navigation.
                    _proceedWithNavigation();
                  },
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.textSecondary,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 20,
                      vertical: 12,
                    ),
                  ),
                  child: const Text('Later'),
                ),
              ElevatedButton(
                onPressed: () async {
                  final Uri url = Uri.parse(appLink);
                  if (await canLaunchUrl(url)) {
                    await launchUrl(url, mode: LaunchMode.externalApplication);
                    if (forceUpdate) {
                      // If force update, keep dialog open
                    }
                  }
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: AppColors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 12,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                child: const Text(
                  'Update Now',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
        );
      },
    ).then((_) {
      if (!forceUpdate) {
        _proceedWithNavigation();
      }
    });
  }

  Future<void> _proceedWithNavigation() async {
    // Prevent duplicate navigation (e.g. from the background version-check
    // callback firing after we have already navigated away).
    if (_navigationStarted) return;
    _navigationStarted = true;

    if (!mounted) return;

    // On subsequent launches, proceed immediately without any delay
    if (!_isFirstLaunch) {
      await _navigateBasedOnUserState();
      return;
    }

    // Wait for the entrance animation to finish so we never navigate
    // mid-animation and the user always sees the full splash on first launch.
    await _animationCompleted;

    if (!mounted) return;

    await _navigateBasedOnUserState();
  }

  Future<void> _navigateBasedOnUserState() async {
    await context.read<SignupModel>().loadUserData();

    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('bearer_token');
    final userDataString = prefs.getString('user_data');

    // NO TOKEN → GO TO ONBOARDING
    if (token == null || userDataString == null) {
      _goTo(const OnboardingScreen());
      return;
    }

    // Decode stored signup response
    final userData = jsonDecode(userDataString);
    final userId = int.tryParse(userData["id"].toString());

    if (userId == null) {
      _goTo(const OnboardingScreen());
      return;
    }

    _initFCM();

    // Use cached pageNo for instant navigation (skip API call on subsequent launches)
    final cachedPageNo = prefs.getInt('cached_page_no');
    if (cachedPageNo != null) {
      if (!mounted) return;
      _navigateToPage(cachedPageNo);
      // Validate in background and re-navigate only if pageNo changed
      PageService.getPageNo(userId).then((freshPageNo) {
        if (freshPageNo != null) {
          prefs.setInt('cached_page_no', freshPageNo);
          if (freshPageNo != cachedPageNo && mounted) {
            _navigateToPage(freshPageNo);
          }
        }
      }).catchError((e) {
        debugPrint('Background pageNo validation failed: $e');
      });
      return;
    }

    // No cached pageNo → call API (first launch or cache cleared after logout)
    final pageNo = await PageService.getPageNo(userId);

    if (!mounted) return;

    if (pageNo == null) {
      _goTo(const OnboardingScreen());
      return;
    }

    await prefs.setInt('cached_page_no', pageNo);
    _navigateToPage(pageNo);
  }

  void _navigateToPage(int pageNo) {
    switch (pageNo) {
      case 0:
        _goTo(const PersonalDetailsPage());
        break;
      case 1:
        _goTo(const CommunityDetailsPage());
        break;
      case 2:
        _goTo(const LivingStatusPage());
        break;
      case 3:
        _goTo(FamilyDetailsPage());
        break;
      case 4:
        _goTo(EducationCareerPage());
        break;
      case 5:
        _goTo(AstrologicDetailsPage());
        break;
      case 6:
        _goTo(LifestylePage());
        break;
      case 7:
        _goTo(PartnerPreferencesPage());
        break;
      case 8:
        _goTo(IDVerificationScreen());
        break;
      case 9:
        _goTo(const IDVerificationScreen());
        break;
      case 10:
        _goTo(const MainControllerScreen(initialIndex: 0));
        break;
      default:
        _goTo(const OnboardingScreen());
    }
  }

  Future<void> _initFCM() async {
    final prefs = await SharedPreferences.getInstance();

    final userDataString = prefs.getString('user_data');
    if (userDataString == null) return;

    final userData = jsonDecode(userDataString);
    final String userId = userData["id"].toString();

    try {
      // Request notification permission. The result only affects whether the
      // OS displays notification banners — the FCM token must always be
      // registered with the backend so the server can reach this device.
      NotificationSettings settings =
          await FirebaseMessaging.instance.requestPermission();

      final authorized =
          settings.authorizationStatus == AuthorizationStatus.authorized ||
          settings.authorizationStatus == AuthorizationStatus.provisional;
      print(authorized
          ? "Push permission granted"
          : "Push permission not granted - banners won't show, but token will still be registered");

      await Future.delayed(const Duration(milliseconds: 300));

      String? fcmToken = await FirebaseMessaging.instance.getToken();

      if (fcmToken == null) {
        await Future.delayed(const Duration(milliseconds: 500));
        fcmToken = await FirebaseMessaging.instance.getToken();
      }

      if (fcmToken == null) {
        print("FCM token still null after retry");
        return;
      }

      print("FCM TOKEN => $fcmToken");

      // Always update the token on the server. This ensures the backend stays
      // in sync after a Firebase project key change, a DB reset, or a token
      // that was previously blocked from reaching the server.
      await prefs.setString('fcm_token', fcmToken);
      await updateFcmToken(userId, fcmToken);
      print("FCM TOKEN synced with server");

      FirebaseMessaging.instance.onTokenRefresh.listen((newToken) async {
        await prefs.setString('fcm_token', newToken);
        await updateFcmToken(userId, newToken);
        print("FCM TOKEN refreshed => $newToken");
      });
    } catch (e) {
      print("FCM ERROR => $e");
    }
    OnlineStatusService().start();
  }

  Future<void> updateFcmToken(String userId, String token) async {
    final response = await http.post(
      Uri.parse("${kApiBaseUrl}/Api2/update_token.php"),
      body: {
        "user_id": userId,
        "fcm_token": token,
      },
    );
    print(response.body);
  }

  void _goTo(Widget screen) {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => screen),
    );
  }

  // ─── 3 bouncing brand-red dots ───────────────────────────────────────────────
  Widget _buildLoadingDots() {
    // If no animation controller (subsequent launches), show static dots
    if (_dotsController == null) {
      return Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: List.generate(3, (index) {
          return Container(
            margin: const EdgeInsets.symmetric(horizontal: 5),
            width: 8,
            height: 8,
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.7),
              shape: BoxShape.circle,
            ),
          );
        }),
      );
    }

    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(3, (index) {
        final delay = index * 0.28;
        return AnimatedBuilder(
          animation: _dotsController!,
          builder: (context, child) {
            final rawProgress = (_dotsController!.value - delay).clamp(0.0, 1.0);
            final bounceProgress = rawProgress < 0.5 ? rawProgress * 2.0 : (1.0 - rawProgress) * 2.0;
            final bounce = Curves.easeInOut.transform(bounceProgress);
            return Container(
              margin: const EdgeInsets.symmetric(horizontal: 5),
              width: 8,
              height: 8,
              transform: Matrix4.translationValues(0, -9 * bounce, 0),
              decoration: BoxDecoration(
                color: AppColors.primary.withOpacity(0.3 + 0.7 * bounce),
                shape: BoxShape.circle,
              ),
            );
          },
        );
      }),
    );
  }

  @override
  Widget build(BuildContext context) {
    // On subsequent launches, show a minimal splash screen
    if (!_isFirstLaunch) {
      return Scaffold(
        backgroundColor: AppColors.white,
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Show logo without animation on subsequent launches
              const Image(
                image: AssetImage('assets/images/Mslogo.gif'),
                height: 250,
                width: 250,
                fit: BoxFit.contain,
              ),
              const SizedBox(height: 28),
              ShaderMask(
                shaderCallback: (bounds) =>
                    AppColors.primaryGradient.createShader(bounds),
                child: const Text(
                  'Marriage Station',
                  style: TextStyle(
                    fontSize: 32,
                    fontWeight: FontWeight.bold,
                    color: AppColors.white,
                    letterSpacing: 0.5,
                  ),
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                "Nepal's #1 Matrimony Platform",
                style: TextStyle(
                  fontSize: 16,
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w500,
                ),
              ),
              if (_isCheckingVersion) ...[
                const SizedBox(height: 40),
                const CircularProgressIndicator(
                  valueColor: AlwaysStoppedAnimation<Color>(AppColors.primary),
                ),
              ],
              if (_errorMessage != null) ...[
                const SizedBox(height: 40),
                Icon(Icons.wifi_off_rounded,
                    color: AppColors.error.withOpacity(0.7), size: 36),
                const SizedBox(height: 12),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 32),
                  child: Text(
                    _errorMessage!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 14,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                ElevatedButton.icon(
                  onPressed: () {
                    setState(() {
                      _isCheckingVersion = true;
                      _errorMessage = null;
                    });
                    _checkAppVersion();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 28, vertical: 12),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                  icon: const Icon(Icons.refresh,
                      color: AppColors.white),
                  label: const Text(
                    'Retry',
                    style: TextStyle(
                        color: AppColors.white,
                        fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ],
          ),
        ),
      );
    }

    // First launch: Show full animated splash screen
    return Scaffold(
      backgroundColor: AppColors.white,
      body: Stack(
        children: [
          // ── Centred logo + text ──────────────────────────────────────────────
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Logo: elastic zoom-in + subtle pulse while loading
                if (_entranceController != null && _pulseController != null)
                  AnimatedBuilder(
                    animation: Listenable.merge([_entranceController!, _pulseController!]),
                    builder: (context, child) {
                      final scale = _logoScale!.value *
                          (_isCheckingVersion ? _pulseScale!.value : 1.0);
                      return Opacity(
                        opacity: _logoOpacity!.value,
                        child: Transform.scale(scale: scale, child: child),
                      );
                    },
                    child: const Image(
                      image: AssetImage('assets/images/Mslogo.gif'),
                      height: 250,
                      width: 250,
                      fit: BoxFit.contain,
                    ),
                  )
                else
                  // Fallback if animations not initialized
                  const Image(
                    image: AssetImage('assets/images/Mslogo.gif'),
                    height: 250,
                    width: 250,
                    fit: BoxFit.contain,
                  ),

                const SizedBox(height: 28),

                // App name: slide-up + fade
                if (_textOpacity != null && _textSlide != null)
                  FadeTransition(
                    opacity: _textOpacity!,
                    child: SlideTransition(
                      position: _textSlide!,
                      child: ShaderMask(
                        shaderCallback: (bounds) =>
                            AppColors.primaryGradient.createShader(bounds),
                        child: const Text(
                          'Marriage Station',
                          style: TextStyle(
                            fontSize: 32,
                            fontWeight: FontWeight.bold,
                            color: AppColors.white,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ),
                    ),
                  )
                else
                  ShaderMask(
                    shaderCallback: (bounds) =>
                        AppColors.primaryGradient.createShader(bounds),
                    child: const Text(
                      'Marriage Station',
                      style: TextStyle(
                        fontSize: 32,
                        fontWeight: FontWeight.bold,
                        color: AppColors.white,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ),

                const SizedBox(height: 8),

                // Tagline: fade in last
                if (_taglineOpacity != null)
                  FadeTransition(
                    opacity: _taglineOpacity!,
                    child: const Text(
                      "Nepal's #1 Matrimony Platform",
                      style: TextStyle(
                        fontSize: 16,
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  )
                else
                  const Text(
                    "Nepal's #1 Matrimony Platform",
                    style: TextStyle(
                      fontSize: 16,
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
              ],
            ),
          ),

          // ── Bottom: dots while loading, error card when failed ─────────────
          Positioned(
            bottom: 56,
            left: 0,
            right: 0,
            child: _isCheckingVersion
                ? _buildLoadingDots()
                : _errorMessage != null
                    ? Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 32),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.wifi_off_rounded,
                                color: AppColors.error.withOpacity(0.7), size: 36),
                            const SizedBox(height: 12),
                            Text(
                              _errorMessage!,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: AppColors.textSecondary,
                                fontSize: 14,
                              ),
                            ),
                            const SizedBox(height: 16),
                            ElevatedButton.icon(
                              onPressed: () {
                                setState(() {
                                  _isCheckingVersion = true;
                                  _errorMessage = null;
                                });
                                _checkAppVersion();
                              },
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primary,
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 28, vertical: 12),
                                shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12)),
                              ),
                              icon: const Icon(Icons.refresh,
                                  color: AppColors.white),
                              label: const Text(
                                'Retry',
                                style: TextStyle(
                                    color: AppColors.white,
                                    fontWeight: FontWeight.w600),
                              ),
                            ),
                          ],
                        ),
                      )
                    : const SizedBox.shrink(),
          ),
        ],
      ),
    );
  }
}
