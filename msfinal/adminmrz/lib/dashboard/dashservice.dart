import 'dart:convert';
import 'package:http/http.dart' as http;
import 'dashmodel.dart';
import 'package:adminmrz/config/app_endpoints.dart';

class DashboardService {
  Future<DashboardResponse> getDashboardData() async {
    try {
      final response = await http.get(
        Uri.parse('${AdminAppConfig.api9base}/get_dashboard.php'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return DashboardResponse.fromJson(data);
      } else {
        throw Exception('Failed to load dashboard data: ${response.statusCode}');
      }
    } catch (e) {
      rethrow;
    }
  }
}