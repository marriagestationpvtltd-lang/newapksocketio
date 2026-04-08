import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Available ringtone options bundled with the app.
class RingtoneTone {
  final String id;
  final String label;
  final String asset; // relative to assets/
  const RingtoneTone({required this.id, required this.label, required this.asset});
}

class CallSettingsProvider extends ChangeNotifier {
  static const _keyToneId         = 'call_tone_id';
  static const _keyRepeatInterval = 'call_repeat_interval';
  static const _settingsUrl       = 'https://digitallami.com/Api2/app_settings.php';
  static const _updateSettingsUrl = 'https://digitallami.com/api9/update_app_settings.php';

  static const List<RingtoneTone> availableTones = [
    RingtoneTone(
      id: 'classic',
      label: 'Classic Ring (440 + 480 Hz)',
      asset: 'audio/ring_classic.wav',
    ),
    RingtoneTone(
      id: 'soft',
      label: 'Soft Professional (800 + 1000 Hz)',
      asset: 'audio/ring_soft.wav',
    ),
    RingtoneTone(
      id: 'modern',
      label: 'Modern Double-Beep',
      asset: 'audio/ring_modern.wav',
    ),
    RingtoneTone(
      id: 'default',
      label: 'Original Tone',
      asset: 'audio/outcall.mp3',
    ),
  ];

  String _selectedToneId       = 'default';
  int    _repeatIntervalSeconds = 3;

  String get selectedToneId        => _selectedToneId;
  int    get repeatIntervalSeconds => _repeatIntervalSeconds;

  RingtoneTone get selectedTone =>
      availableTones.firstWhere(
        (t) => t.id == _selectedToneId,
        orElse: () => availableTones.first,
      );

  CallSettingsProvider() {
    _load();
  }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    _selectedToneId        = _normalizeToneId(prefs.getString(_keyToneId));
    _repeatIntervalSeconds = prefs.getInt(_keyRepeatInterval)    ?? 3;
    notifyListeners();
    await _syncToneFromServer();
  }

  Future<void> setTone(String toneId) async {
    _selectedToneId = _normalizeToneId(toneId);
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyToneId, _selectedToneId);
    await _saveToneToServer();
  }

  Future<void> setRepeatInterval(int seconds) async {
    _repeatIntervalSeconds = seconds.clamp(1, 30);
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_keyRepeatInterval, _repeatIntervalSeconds);
  }

  String _normalizeToneId(String? toneId) {
    final normalizedToneId = toneId ?? 'default';
    return availableTones.any((tone) => tone.id == normalizedToneId)
        ? normalizedToneId
        : 'default';
  }

  Future<void> _syncToneFromServer() async {
    try {
      final response = await http
          .get(Uri.parse(_settingsUrl))
          .timeout(const Duration(seconds: 5));
      if (response.statusCode != 200) return;

      final data = jsonDecode(response.body);
      if (data is! Map<String, dynamic>) return;

      final settings = data['data'];
      if (settings is! Map<String, dynamic>) return;

      final remoteToneId = _normalizeToneId(settings['call_tone_id']?.toString());
      if (remoteToneId == _selectedToneId) return;

      _selectedToneId = remoteToneId;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_keyToneId, _selectedToneId);
      notifyListeners();
    } catch (e) {
      debugPrint('Error loading remote call tone settings: ${e.runtimeType} - $e');
    }
  }

  Future<void> _saveToneToServer() async {
    try {
      await http
          .post(
            Uri.parse(_updateSettingsUrl),
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            body: jsonEncode({'call_tone_id': _selectedToneId}),
          )
          .timeout(const Duration(seconds: 5));
    } catch (e) {
      debugPrint('Error saving remote call tone settings: ${e.runtimeType} - $e');
    }
  }
}
