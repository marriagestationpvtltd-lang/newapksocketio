import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class CallTonePlaybackSource {
  final String value;
  final bool isRemote;

  const CallTonePlaybackSource.asset(this.value) : isRemote = false;

  const CallTonePlaybackSource.remote(this.value) : isRemote = true;
}

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
  final String customToneUrl;

  const CallToneSettings({
    this.toneId = defaultToneId,
    this.customToneUrl = '',
  });

  String get assetPath => _toneAssets[toneId] ?? defaultAssetPath;

  List<CallTonePlaybackSource> get playbackSources {
    final sources = <CallTonePlaybackSource>[];
    if (customToneUrl.isNotEmpty) {
      sources.add(CallTonePlaybackSource.remote(customToneUrl));
    }

    final primaryAsset = assetPath;
    sources.add(CallTonePlaybackSource.asset(primaryAsset));
    if (toneId == defaultToneId && primaryAsset != legacyDefaultAssetPath) {
      sources.add(const CallTonePlaybackSource.asset(legacyDefaultAssetPath));
    }

    return sources;
  }

  static String normalizeToneId(String? toneId) {
    return toneId != null && _toneAssets.containsKey(toneId)
        ? toneId
        : defaultToneId;
  }

  static String normalizeCustomToneUrl(String? customToneUrl) {
    return customToneUrl?.trim() ?? '';
  }
}

class CallToneSettingsService {
  CallToneSettingsService._();

  static final CallToneSettingsService instance = CallToneSettingsService._();

  static const _settingsUrl = 'https://digitallami.com/Api2/app_settings.php';
  static const _cachedToneIdKey = 'cached_call_tone_id';
  static const _cachedCustomToneUrlKey = 'cached_custom_call_tone_url';

  Future<CallToneSettings> load() async {
    final prefs = await SharedPreferences.getInstance();
    final cachedToneId = CallToneSettings.normalizeToneId(
      prefs.getString(_cachedToneIdKey),
    );
    final cachedCustomToneUrl = CallToneSettings.normalizeCustomToneUrl(
      prefs.getString(_cachedCustomToneUrlKey),
    );

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
            final remoteCustomToneUrl = CallToneSettings.normalizeCustomToneUrl(
              data['custom_call_tone_url']?.toString(),
            );
            await prefs.setString(_cachedToneIdKey, remoteToneId);
            await prefs.setString(_cachedCustomToneUrlKey, remoteCustomToneUrl);
            return CallToneSettings(
              toneId: remoteToneId,
              customToneUrl: remoteCustomToneUrl,
            );
          }
        }
      }
    } catch (e) {
      debugPrint('Error loading caller tone settings: $e');
    }

    return CallToneSettings(
      toneId: cachedToneId,
      customToneUrl: cachedCustomToneUrl,
    );
  }
}
