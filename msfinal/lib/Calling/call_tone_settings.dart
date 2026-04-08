import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class CallToneSettings {
  static const defaultToneId = 'default';
  static const defaultAssetPath = 'audio/outcall.mp3';
  static const legacyDefaultAssetPath = 'images/outcall.mp3';
  static const _toneAssets = <String, String>{
    'classic': 'audio/ring_classic.wav',
    'soft': 'audio/ring_soft.wav',
    'modern': 'audio/ring_modern.wav',
    defaultToneId: defaultAssetPath,
  };

  final String toneId;

  const CallToneSettings({this.toneId = defaultToneId});

  String get assetPath => _toneAssets[toneId] ?? defaultAssetPath;

  List<String> get fallbackAssetPaths {
    final primaryAsset = assetPath;
    if (toneId == defaultToneId && primaryAsset != legacyDefaultAssetPath) {
      return [primaryAsset, legacyDefaultAssetPath];
    }
    return [primaryAsset];
  }

  static String normalizeToneId(String? toneId) {
    return _toneAssets.containsKey(toneId) ? toneId! : defaultToneId;
  }
}

class CallToneSettingsService {
  CallToneSettingsService._();

  static final CallToneSettingsService instance = CallToneSettingsService._();

  static const _settingsUrl = 'https://digitallami.com/Api2/app_settings.php';
  static const _cachedToneIdKey = 'cached_call_tone_id';

  Future<CallToneSettings> load() async {
    final prefs = await SharedPreferences.getInstance();
    final cachedToneId =
        CallToneSettings.normalizeToneId(prefs.getString(_cachedToneIdKey));

    try {
      final response = await http
          .get(Uri.parse(_settingsUrl))
          .timeout(const Duration(seconds: 5));

      if (response.statusCode == 200) {
        final body = jsonDecode(response.body);
        if (body is Map<String, dynamic>) {
          final data = body['data'];
          if (data is Map<String, dynamic>) {
            final remoteToneId = CallToneSettings.normalizeToneId(
              data['call_tone_id']?.toString(),
            );
            await prefs.setString(_cachedToneIdKey, remoteToneId);
            return CallToneSettings(toneId: remoteToneId);
          }
        }
      }
    } catch (e) {
      debugPrint('Error loading caller tone settings: $e');
    }

    return CallToneSettings(toneId: cachedToneId);
  }
}
