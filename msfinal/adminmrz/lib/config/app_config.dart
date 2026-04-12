// ─────────────────────────────────────────────────────────────────────────────
// AdminAppConfig — single source of truth for every API endpoint used by the
// admin app.
//
// Pass the server at build time with:
//   flutter build web --dart-define=ADMIN_API_BASE_URL=https://yourserver.com
//
// All URL assembly happens here; no other file should build raw URL strings.
// ─────────────────────────────────────────────────────────────────────────────

// ignore_for_file: constant_identifier_names

class AdminAppConfig {
  AdminAppConfig._();

  // ── Base URL ───────────────────────────────────────────────────────────────
  static const String baseUrl = String.fromEnvironment(
    'ADMIN_API_BASE_URL',
    defaultValue: 'https://react.marriagestation.com.np',
  );

  // ── Socket URL ─────────────────────────────────────────────────────────────
  static const String socketUrl = String.fromEnvironment(
    'ADMIN_SOCKET_URL',
    defaultValue: 'https://adminnew.marriagestation.com.np',
  );

  // ── API namespace roots ────────────────────────────────────────────────────
  static const String _api2 = '$baseUrl/Api2';
  static const String _api9 = '$baseUrl/api9';

  /// Image / file base URL (same as root).
  static const String imageBase = baseUrl;

  // ── Auth (api9) ───────────────────────────────────────────────────────────
  static const String login = '$_api9/login.php';

  // ── Dashboard (api9) ──────────────────────────────────────────────────────
  static const String api9base = _api9;

  // ── Users (api9) ──────────────────────────────────────────────────────────
  static const String getUsers = '$baseUrl/get.php';
  static const String getUsersPaginated = '$baseUrl/get.php';
  static const String getMatches = '$baseUrl/get_matches.php';
  static const String getMatchDetails = '$baseUrl/get_match_details.php';
  static const String matchAdmin = '$baseUrl/match_admin.php';
  static const String userProfile = '$baseUrl/profile.php';
  static const String getUsers2 = '$_api2/getusers.php';

  // ── Documents (api9) ──────────────────────────────────────────────────────
  static const String getDocuments = '$_api9/get_documents.php';
  static const String updateDocumentStatus = '$_api9/update_document_status.php';

  // ── Packages (api9) ───────────────────────────────────────────────────────
  static const String api9PackageBase = _api9;

  // ── Payments (api9) ───────────────────────────────────────────────────────
  static const String api9PaymentBase = _api9;

  // ── Settings ──────────────────────────────────────────────────────────────
  static const String appSettings = '$_api2/app_settings.php';
  static const String updateAppSettings = '$_api9/update_app_settings.php';
  static const String uploadCallTone = '$_api9/upload_call_tone.php';

  // ── Notifications ─────────────────────────────────────────────────────────
  static const String sendNotification = '$_api2/send_notification.php';

  // ── Agora token ───────────────────────────────────────────────────────────
  static const String testToken = '$_api2/test_token.php';

  // ── Api2 base ─────────────────────────────────────────────────────────────
  static const String api2base = _api2;
}
