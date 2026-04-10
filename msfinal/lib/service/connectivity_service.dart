import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

class ConnectivityService extends ChangeNotifier {
  static final ConnectivityService _instance = ConnectivityService._internal();
  factory ConnectivityService() => _instance;
  ConnectivityService._internal();

  final Connectivity _connectivity = Connectivity();
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;
  Timer? _delayedRecheckTimer;

  List<ConnectivityResult> _connectionStatus = [];
  bool _hasInternet = true;
  bool _isChecking = false;
  int _consecutiveProbeFailures = 0;
  DateTime? _startupGraceUntil;

  List<ConnectivityResult> get connectionStatus => _connectionStatus;
  bool get hasInternet => _hasInternet;
  bool get isWifiConnected => _connectionStatus.contains(ConnectivityResult.wifi);
  bool get isMobileConnected => _connectionStatus.contains(ConnectivityResult.mobile);

  /// Returns true when connected.  Before [initialize] completes we treat the
  /// state as "undetermined" and optimistically report connected so that the
  /// connectivity banner never flickers offline→online on a normal launch.
  bool get isConnected {
    if (_connectionStatus.isEmpty) return true; // not yet initialised
    return _hasInternet && !_connectionStatus.contains(ConnectivityResult.none);
  }

  /// Initialize connectivity monitoring
  Future<void> initialize() async {
    try {
      _startupGraceUntil = DateTime.now().add(const Duration(seconds: 10));
      // Get initial status
      _connectionStatus = await _connectivity.checkConnectivity();
      await _checkActualInternetConnection();

      // Listen to connectivity changes
      _connectivitySubscription = _connectivity.onConnectivityChanged.listen(
        (List<ConnectivityResult> result) async {
          _connectionStatus = result;
          await _checkActualInternetConnection();
          notifyListeners();

          if (kDebugMode) {
            print('📡 Connectivity changed: $_connectionStatus, Internet: $_hasInternet');
          }
        },
      );
    } catch (e) {
      if (kDebugMode) {
        print('❌ Connectivity service initialization error: $e');
      }
      // Populate the status list so isConnected no longer returns the
      // optimistic "not yet initialised" value.
      if (_connectionStatus.isEmpty) {
        _connectionStatus = [ConnectivityResult.none];
      }
      _hasInternet = false;
      notifyListeners();
    }
  }

  /// Check actual internet connection by trying to reach a reliable server
  Future<bool> _checkActualInternetConnection() async {
    if (_isChecking) return _hasInternet;

    if (_connectionStatus.contains(ConnectivityResult.none)) {
      _consecutiveProbeFailures = 0;
      _hasInternet = false;
      notifyListeners();
      return _hasInternet;
    }

    _isChecking = true;
    try {
      // Try multiple reliable endpoints.
      final results = await Future.wait([
        _checkHost(Uri.https('clients3.google.com', '/generate_204')),
        _checkHost(Uri.https('www.gstatic.com', '/generate_204')),
        _checkHost(Uri.https('cloudflare.com', '/cdn-cgi/trace')),
      ]);

      final hasInternetNow = results.any((result) => result);

      if (hasInternetNow) {
        _consecutiveProbeFailures = 0;
        _hasInternet = true;
      } else {
        _consecutiveProbeFailures += 1;
        final inStartupGrace = _startupGraceUntil != null &&
            DateTime.now().isBefore(_startupGraceUntil!);
        final shouldKeepPreviousOnlineState = _hasInternet &&
            (_consecutiveProbeFailures < 2 || inStartupGrace);

        if (shouldKeepPreviousOnlineState) {
          _scheduleDelayedRecheck();
        } else {
          _hasInternet = false;
        }
      }
    } catch (e) {
      _consecutiveProbeFailures += 1;
      if (!_hasInternet || _consecutiveProbeFailures >= 2) {
        _hasInternet = false;
      } else {
        _scheduleDelayedRecheck();
      }
      if (kDebugMode) {
        print('❌ Internet check error: $e');
      }
    } finally {
      _isChecking = false;
    }

    notifyListeners();
    return _hasInternet;
  }

  /// Check if a specific endpoint is reachable.
  /// Uses HTTP GET on all platforms (avoids dart:io dependency on web).
  Future<bool> _checkHost(Uri uri) async {
    try {
      if (kIsWeb) {
        // On web, trust connectivity_plus; InternetAddress.lookup is unavailable.
        return !_connectionStatus.contains(ConnectivityResult.none);
      }
      final response = await http.get(uri).timeout(const Duration(seconds: 4));
      return response.statusCode >= 200 && response.statusCode < 500;
    } catch (_) {
      return false;
    }
  }

  void _scheduleDelayedRecheck() {
    _delayedRecheckTimer?.cancel();
    _delayedRecheckTimer = Timer(const Duration(seconds: 2), () {
      _checkActualInternetConnection();
    });
  }

  /// Manual check for internet connectivity (use before important API calls)
  Future<bool> checkConnectivity() async {
    _connectionStatus = await _connectivity.checkConnectivity();
    return await _checkActualInternetConnection();
  }

  /// Get connection type as string (for display)
  String getConnectionType() {
    if (!_hasInternet) return 'No Internet';
    if (_connectionStatus.contains(ConnectivityResult.wifi)) return 'WiFi';
    if (_connectionStatus.contains(ConnectivityResult.mobile)) return 'Mobile Data';
    if (_connectionStatus.contains(ConnectivityResult.ethernet)) return 'Ethernet';
    if (_connectionStatus.contains(ConnectivityResult.vpn)) return 'VPN';
    return 'Unknown';
  }

  /// Dispose subscription when not needed
  void dispose() {
    _connectivitySubscription?.cancel();
    _delayedRecheckTimer?.cancel();
    super.dispose();
  }
}
