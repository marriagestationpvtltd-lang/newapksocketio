import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:adminmrz/config/app_endpoints.dart';

class AuthProvider with ChangeNotifier {
  String? _token;
  Map<String, dynamic>? _adminData;
  bool _isLoading = false;
  String? _error;

  String? get token => _token;
  Map<String, dynamic>? get adminData => _adminData;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _token != null;

  AuthProvider() {
    _loadToken();
  }

  Future<void> _loadToken() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('token');
    final adminDataString = prefs.getString('adminData');
    if (adminDataString != null) {
      _adminData = json.decode(adminDataString);
    }
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final url = Uri.parse('${kAdminApiBaseUrl}/api9/login.php');

      final response = await http.post(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: json.encode({
          'email': email,
          'password': password,
        }),
      );

      if (response.statusCode == 200) {
        final responseData = json.decode(response.body);

        if (responseData['success'] == true) {
          _token = responseData['data']['token'];
          _adminData = responseData['data']['admin'];

          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('token', _token!);
          await prefs.setString('adminData', json.encode(_adminData));

          _isLoading = false;
          notifyListeners();
          return true;
        } else {
          _error = responseData['message'] ?? 'Login failed';
          _isLoading = false;
          notifyListeners();
          return false;
        }
      } else {
        _error = 'Server error: ${response.statusCode}';
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = 'Network error: $e';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('token');
    await prefs.remove('adminData');

    _token = null;
    _adminData = null;
    notifyListeners();
  }
}