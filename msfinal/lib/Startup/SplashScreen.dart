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

  // Animation duration – the logo GIF needs ~3s to complete its cycle, so
  // keep the entrance long enough that the full animation plays before
  // navigation fires.
  static const int _entranceDurationMs = 3000;

  // Current app versions - Update these with your actual current versions
  final String currentAndroidVersion = '24.0.0'; // Your current Android version
  final String currentIOSVersion = '1.0.0';     // Your current iOS version

  // Animation controllers
  AnimationController? _entranceController;
  AnimationController? _pulseController;
  AnimationController? _dotsController;
  AnimationController? _ringController;

  // Entrance animations
  Animation<double>? _logoScale;
  Animation<double>? _logoOpacity;
  Animation<Offset>? _logoSlideIn;
  Animation<double>? _glowOpacity;
  Animation<double>? _textOpacity;
  Animation<Offset>? _textSlide;
  Animation<double>? _taglineOpacity;

  // Pulse (breathing) scale while loading
  Animation<double>? _pulseScale;

  // Decorative ring ripple animations (expand outward from logo center)
  Animation<double>? _ring1Scale;
  Animation<double>? _ring1Opacity;
  Animation<double>? _ring2Scale;
  Animation<double>? _ring2Opacity;
  Animation<double>? _ring3Scale;
  Animation<double>? _ring3Opacity;

  @override
  void initState() {
    super.initState();
    _initializeApp();
  }

  Future<void> _initializeApp() async {
    // Check first launch status before doing anything else
    await _checkFirstLaunch();

    // Always setup animations for a premium "wow" experience on every launch.
    // Subsequent launches use a slightly shorter entrance so power users aren't
    // slowed down, but still get the full branded feel.
    _setupAnimations();
    // TickerCanceled is expected when the widget disposes while the animation
    // is still running (e.g. user leaves the app); swallow it intentionally.
    _animationCompleted = _entranceController!.forward().orCancel
        .catchError((Object e) {
          if (e is! TickerCanceled) debugPrint('Splash animation error: $e');
        });

    // Rings start 80 ms after the logo so they feel like a response to it
    Future.delayed(const Duration(milliseconds: 80), () {
      if (mounted) _ringController?.forward();
    });

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
    // Both first launch and subsequent launches use 3000ms so the full logo
    // GIF animation plays before navigation fires.
    final entranceMs = _entranceDurationMs;

    _entranceController = AnimationController(
      vsync: this,
      duration: Duration(milliseconds: entranceMs),
    );

    // Slow breathing pulse while navigation data loads
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    )..repeat(reverse: true);

    // 3-dot bounce loop
    _dotsController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..repeat();

    // Decorative rings: runs once, 2200 ms (scales with the longer entrance)
    _ringController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2200),
    );

    // ── Logo ────────────────────────────────────────────────────────────────
    // Start semi-visible (0.7) so the logo is immediately visible on the very
    // first Flutter frame — seamless continuity from the native splash logo.
    // Then polishes up to full opacity in the first 35% of the entrance.
    _logoOpacity = Tween<double>(begin: 0.7, end: 1.0).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.0, 0.35, curve: Curves.easeIn),
      ),
    );
    // Drops in from slightly above + elastic bounce (0 – 70%)
    _logoSlideIn = Tween<Offset>(
      begin: const Offset(0, -0.12),
      end: Offset.zero,
    ).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.0, 0.70, curve: Curves.elasticOut),
      ),
    );
    // Scales from 0.7 → 1.0 so the logo is already clearly visible on the
    // first frame (brand continuity with the native splash ic_launcher).
    _logoScale = Tween<double>(begin: 0.7, end: 1.0).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.0, 0.70, curve: Curves.elasticOut),
      ),
    );

    // ── Glow behind logo (fades in 0 – 55%) ─────────────────────────────────
    _glowOpacity = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.0, 0.55, curve: Curves.easeOut),
      ),
    );

    // ── Breathing pulse while loading (1.0 → 1.04) ──────────────────────────
    _pulseScale = Tween<double>(begin: 1.0, end: 1.04).animate(
      CurvedAnimation(parent: _pulseController!, curve: Curves.easeInOut),
    );

    // ── Title: slides up + fades in (52 – 84%) ──────────────────────────────
    _textOpacity = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.52, 0.84, curve: Curves.easeOut),
      ),
    );
    _textSlide = Tween<Offset>(
      begin: const Offset(0, 0.45),
      end: Offset.zero,
    ).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.52, 0.84, curve: Curves.easeOutCubic),
      ),
    );

    // ── Tagline: fades in last (72 – 100%) ──────────────────────────────────
    _taglineOpacity = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _entranceController!,
        curve: const Interval(0.72, 1.0, curve: Curves.easeOut),
      ),
    );

    // ── Decorative rings (3 staggered ripples from logo center) ─────────────
    _ring1Scale = Tween<double>(begin: 0.3, end: 2.1).animate(
      CurvedAnimation(
        parent: _ringController!,
        curve: const Interval(0.0, 0.72, curve: Curves.easeOut),
      ),
    );
    _ring1Opacity = Tween<double>(begin: 0.75, end: 0.0).animate(
      CurvedAnimation(
        parent: _ringController!,
        curve: const Interval(0.0, 0.72, curve: Curves.easeOut),
      ),
    );
    _ring2Scale = Tween<double>(begin: 0.3, end: 2.1).animate(
      CurvedAnimation(
        parent: _ringController!,
        curve: const Interval(0.18, 0.85, curve: Curves.easeOut),
      ),
    );
    _ring2Opacity = Tween<double>(begin: 0.55, end: 0.0).animate(
      CurvedAnimation(
        parent: _ringController!,
        curve: const Interval(0.18, 0.85, curve: Curves.easeOut),
      ),
    );
    _ring3Scale = Tween<double>(begin: 0.3, end: 2.1).animate(
      CurvedAnimation(
        parent: _ringController!,
        curve: const Interval(0.35, 1.0, curve: Curves.easeOut),
      ),
    );
    _ring3Opacity = Tween<double>(begin: 0.40, end: 0.0).animate(
      CurvedAnimation(
        parent: _ringController!,
        curve: const Interval(0.35, 1.0, curve: Curves.easeOut),
      ),
    );
  }

  @override
  void dispose() {
    _entranceController?.dispose();
    _pulseController?.dispose();
    _dotsController?.dispose();
    _ringController?.dispose();
    super.dispose();
  }

  /// Performs an app version check. When [isBackground] is true, the call
  /// respects the cached timestamp to avoid hammering the server and never
  /// surfaces user-facing errors.
  Future<void> _checkAppVersion({bool isBackground = false}) async {
    const sixHoursMs = 6 * 60 * 60 * 1000;
    const thirtyMinutesMs = 30 * 60 * 1000;
    SharedPreferences? prefs;

    if (!isBackground && mounted) {
      setState(() {
        _isCheckingVersion = true;
        _errorMessage = null;
      });
    }

    try {
      prefs = await SharedPreferences.getInstance();
      // 0 means "never checked" → always proceeds on fresh install.
      if (isBackground) {
        final lastCheck = prefs.getInt('last_version_check_ok') ?? 0;
        final msElapsed = DateTime.now().millisecondsSinceEpoch - lastCheck;
        if (msElapsed < sixHoursMs) {
          if (mounted) setState(() => _isCheckingVersion = false);
          return; // Checked recently — nothing to do.
        }
      }

      final response = await http
          .get(Uri.parse('${kApiBaseUrl}/app.php'))
          .timeout(const Duration(seconds: 5));

      // Always update the cache after a real HTTP attempt so that a server
      // returning non-success or an update-not-needed result doesn't trigger
      // a repeat check on the very next launch.
      await prefs.setInt(
          'last_version_check_ok', DateTime.now().millisecondsSinceEpoch);

      if (response.statusCode != 200) {
        throw Exception('Non-200 response');
      }

      final data = jsonDecode(response.body);
      if (data['success'] != true) {
        throw Exception('Invalid response');
      }

      _versionData = data['data'];
      if (mounted) {
        setState(() {
          _isCheckingVersion = false;
          _errorMessage = null;
        });
      }

      _showUpdateDialogIfNeeded();
    } catch (_) {
      if (isBackground) {
        // Network unavailable or timeout.  Save a shortened cache timestamp so
        // that we retry in ~30 min rather than on every single launch.
        try {
          prefs ??= await SharedPreferences.getInstance();
          // Back-date the timestamp by (sixHours - thirtyMinutes) so the next
          // cache check fires after ~30 minutes instead of another 6 hours.
          final retryAfter30Min = DateTime.now().millisecondsSinceEpoch -
              (sixHoursMs - thirtyMinutesMs);
          await prefs.setInt('last_version_check_ok', retryAfter30Min);
          if (mounted) setState(() => _isCheckingVersion = false);
        } catch (_) {}
      } else if (mounted) {
        setState(() {
          _isCheckingVersion = false;
          _errorMessage =
              'Unable to check for updates. Please check your connection and try again.';
        });
      }
    }
  }

  /// Background version check — runs after navigation has already started so
  /// it never blocks the user from reaching the app.
  ///
  /// • Within 6 h of the last check  → skipped entirely.
  /// • HTTP success with new version  → shows update dialog on the current screen.
  /// • HTTP error / timeout           → saves a shortened cache time (30 min) so
  ///   we retry later but don't hammer the server on every launch.
  Future<void> _checkAppVersionInBackground() async {
    await _checkAppVersion(isBackground: true);
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

    // Prefer the global navigator's context (always points to the currently
    // active screen) over the splash screen's own context, which becomes
    // invalid once pushReplacement has disposed the widget.
    final ctx = navigatorKey.currentContext ?? (mounted ? context : null);
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

    // Always wait for the entrance animation so every launch feels premium.
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
      PageRouteBuilder(
        pageBuilder: (_, __, ___) => screen,
        transitionDuration: const Duration(milliseconds: 600),
        reverseTransitionDuration: const Duration(milliseconds: 300),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          return FadeTransition(opacity: animation, child: child);
        },
      ),
    );
  }

  // ─── Single decorative ring ────────────────────────────────────────────────
  Widget _buildRing(
      Animation<double>? scale, Animation<double>? opacity, double baseSize) {
    if (scale == null || opacity == null) return const SizedBox.shrink();
    return AnimatedBuilder(
      animation: Listenable.merge([scale, opacity]),
      builder: (context, child) => Opacity(
        opacity: opacity.value.clamp(0.0, 1.0),
        child: Transform.scale(
          scale: scale.value,
          child: child,
        ),
      ),
      child: Container(
        width: baseSize,
        height: baseSize,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(
            color: AppColors.primary,
            width: 1.5,
          ),
        ),
      ),
    );
  }

  // ─── 3 bouncing brand-red dots ───────────────────────────────────────────────
  Widget _buildLoadingDots() {
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
    // Make the logo fill most of the screen width (≈3-4× the previous 220dp).
    final screenWidth = MediaQuery.of(context).size.width;
    final logoSize = screenWidth * 0.85;          // ~85% of screen width
    final containerSize = logoSize * 1.15;         // breathing room for rings/glow
    final glowSize = logoSize * 1.1;
    final ringBaseSize = logoSize * 0.95;

    // Single unified animated build — every launch gets the premium experience.
    return Scaffold(
      backgroundColor: AppColors.white,
      body: Stack(
        alignment: Alignment.center,
        children: [
          // ── Centred logo + text ──────────────────────────────────────────────
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // ── Logo area: glow + rings + logo stacked ───────────────────
                SizedBox(
                  width: containerSize,
                  height: containerSize,
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      // Soft radial glow behind the logo
                      if (_glowOpacity != null)
                        AnimatedBuilder(
                          animation: _glowOpacity!,
                          builder: (context, child) => Opacity(
                            opacity: (_glowOpacity!.value * 0.45).clamp(0.0, 1.0),
                            child: child,
                          ),
                          child: Container(
                            width: glowSize,
                            height: glowSize,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              gradient: RadialGradient(
                                colors: [
                                  AppColors.primary.withOpacity(0.22),
                                  AppColors.primary.withOpacity(0.07),
                                  Colors.transparent,
                                ],
                                stops: const [0.0, 0.55, 1.0],
                              ),
                            ),
                          ),
                        ),

                      // Decorative expanding rings (matrimony celebration)
                      _buildRing(_ring1Scale, _ring1Opacity, ringBaseSize),
                      _buildRing(_ring2Scale, _ring2Opacity, ringBaseSize),
                      _buildRing(_ring3Scale, _ring3Opacity, ringBaseSize),

                      // Logo: drops in from above + elastic bounce + pulse
                      if (_entranceController != null &&
                          _pulseController != null)
                        AnimatedBuilder(
                          animation: Listenable.merge(
                              [_entranceController!, _pulseController!]),
                          builder: (context, child) {
                            final pulse =
                                _isCheckingVersion ? _pulseScale!.value : 1.0;
                            return Opacity(
                              opacity: _logoOpacity!.value,
                              child: SlideTransition(
                                position: _logoSlideIn!,
                                child: Transform.scale(
                                  scale: _logoScale!.value * pulse,
                                  child: child,
                                ),
                              ),
                            );
                          },
                          child: Image(
                            image: const AssetImage('assets/images/Mslogo.gif'),
                            height: logoSize,
                            width: logoSize,
                            fit: BoxFit.contain,
                          ),
                        )
                      else
                        Image(
                          image: const AssetImage('assets/images/Mslogo.gif'),
                          height: logoSize,
                          width: logoSize,
                          fit: BoxFit.contain,
                        ),
                    ],
                  ),
                ),

                const SizedBox(height: 24),

                // ── App name: slide-up + fade ─────────────────────────────────
                FadeTransition(
                  opacity: _textOpacity ??
                      const AlwaysStoppedAnimation(1.0),
                  child: SlideTransition(
                    position: _textSlide ??
                        const AlwaysStoppedAnimation(Offset.zero),
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
                ),

                const SizedBox(height: 8),

                // ── Tagline: fades in last ────────────────────────────────────
                FadeTransition(
                  opacity: _taglineOpacity ??
                      const AlwaysStoppedAnimation(1.0),
                  child: const Text(
                    "Nepal's #1 Matrimony Platform",
                    style: TextStyle(
                      fontSize: 16,
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w500,
                    ),
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
                                color: AppColors.error.withOpacity(0.7),
                                size: 36),
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
                                _checkAppVersionInBackground();
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
