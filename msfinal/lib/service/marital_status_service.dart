import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:ms2026/config/app_endpoints.dart';

class MaritalStatusService {
  static final String _baseUrl = '${AppConfig.baseUrl}/api19';

  /// Fetch the marital status for a given [userId] from api19/get.php.
  /// Returns a map with `maritalStatusId` and `maritalStatusName` on success.
  static Future<Map<String, dynamic>> fetchMaritalStatus(int userId) async {
    try {
      final url = Uri.parse('$_baseUrl/get.php?userid=$userId');
      final response = await http.get(
        url,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == true && data['data'] != null) {
          return {'status': true, 'data': data['data']};
        }
        return {'status': false, 'message': data['message'] ?? 'No record found'};
      } else {
        return {
          'status': false,
          'message': 'Server returned status code ${response.statusCode}',
        };
      }
    } catch (e) {
      return {'status': false, 'message': e.toString()};
    }
  }
}
