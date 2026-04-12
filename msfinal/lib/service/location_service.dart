import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:ms2026/config/app_endpoints.dart';

class LocationService {
  /// Countries
  static Future<List<Map<String, dynamic>>> fetchCountries() async {
    return await _getList(AppConfig.countries);
  }

  /// States by Country
  static Future<List<Map<String, dynamic>>> fetchStates(int countryId) async {
    return await _getList('${AppConfig.states}?country_id=$countryId');
  }

  /// Cities by State
  static Future<List<Map<String, dynamic>>> fetchCities(int stateId) async {
    return await _getList('${AppConfig.cities}?state_id=$stateId');
  }

  /// Generic GET request
  static Future<List<Map<String, dynamic>>> _getList(String url) async {
    final response = await http.get(Uri.parse(url));

    if (response.statusCode == 200) {
      final body = json.decode(response.body);

      if (body["status"] == "success") {
        return List<Map<String, dynamic>>.from(body["data"]);
      } else {
        throw Exception(body["message"] ?? "API Error");
      }
    } else {
      throw Exception("Failed to load data");
    }
  }
}
