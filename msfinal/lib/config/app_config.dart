// ─────────────────────────────────────────────────────────────────────────────
// AppConfig — single source of truth for every API endpoint used by the user app.
//
// Pass the server at build time with:
//   flutter build apk --dart-define=API_BASE_URL=https://yourserver.com
//
// All URL assembly happens here; no other file should build raw URL strings.
// ─────────────────────────────────────────────────────────────────────────────

// ignore_for_file: constant_identifier_names

class AppConfig {
  AppConfig._();

  // ── Base URL ───────────────────────────────────────────────────────────────
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://react.marriagestation.com.np',
  );

  // ── API namespace roots ────────────────────────────────────────────────────
  static const String _api2 = '$baseUrl/Api2';
  static const String _api3 = '$baseUrl/Api3';
  static const String _api9 = '$baseUrl/api9';
  static const String _api19 = '$baseUrl/api19';
  static const String _request = '$baseUrl/request';

  // ── Payment base (may differ from API base) ────────────────────────────────
  static const String paymentBaseUrl = String.fromEnvironment(
    'PAYMENT_BASE_URL',
    defaultValue: baseUrl,
  );

  // ── Socket URL ─────────────────────────────────────────────────────────────
  static const String _socketEnv = String.fromEnvironment(
    'SOCKET_SERVER_URL',
    defaultValue: '',
  );
  static String get socketUrl {
    if (_socketEnv.isNotEmpty) return _socketEnv;
    final uri = Uri.tryParse(baseUrl);
    if (uri != null && uri.host.isNotEmpty) {
      if (uri.host.contains('marriagestation.com.np')) {
        return 'https://adminnew.marriagestation.com.np';
      }
      final scheme = uri.scheme.isNotEmpty ? uri.scheme : 'https';
      final port = uri.hasPort ? uri.port : 3001;
      return Uri(scheme: scheme, host: uri.host, port: port).toString();
    }
    return 'https://adminnew.marriagestation.com.np';
  }

  // ── Image base URL ─────────────────────────────────────────────────────────
  /// Prefix for relative profile-picture paths returned by the API.
  static const String imageBase = _api2;

  // ── App-level ──────────────────────────────────────────────────────────────
  static const String appCheck = '$baseUrl/app.php';
  static const String appSettings = '$_api2/app_settings.php';
  static const String updateToken = '$_api2/update_token.php';

  // ── Auth ───────────────────────────────────────────────────────────────────
  static const String signIn = '$_api2/signin.php';
  static const String signUp = '$_api2/signup.php';
  static const String googleSignIn = '$_api2/google_signin.php';
  static const String forgotPasswordSendOtp = '$_api2/forgot_password_send_otp.php';
  static const String forgotPasswordVerifyOtp = '$_api2/forgot_password_verify_otp.php';
  static const String forgotPasswordReset = '$_api2/forgot_password_reset.php';

  // ── Profile ────────────────────────────────────────────────────────────────
  static const String myProfile = '$_api2/myprofile.php';
  static const String profilePicture = '$_api2/profile_picture.php';
  static const String aboutMe = '$_api2/aboutme.php';
  static const String masterData = '$_api2/masterdata.php';
  static const String getPage = '$_api2/get_page.php';
  static const String sendDeleteRequest = '$_api2/send_delete_request.php';

  // ── Personal details ───────────────────────────────────────────────────────
  static const String getPersonalDetail = '$_api2/get_personal_detail.php';
  static const String savePersonalDetail = '$_api2/save_personal_detail.php';
  static const String updateReligion = '$_api2/update_religion.php';

  // ── Education / Career ─────────────────────────────────────────────────────
  static const String educationCareer = '$_api2/educationcareer.php';
  static const String getEducationCareer = '$_api2/get_educationcareer.php';

  // ── Family ────────────────────────────────────────────────────────────────
  static const String getFamilyDetails = '$_api2/get_family_details.php';
  static const String updateFamily = '$_api2/updatefamily.php';

  // ── Lifestyle ─────────────────────────────────────────────────────────────
  static const String getLifestyle = '$_api2/get_lifestyle.php';
  static const String userLifestyle = '$_api2/user_lifestyle.php';

  // ── Astrology ─────────────────────────────────────────────────────────────
  static const String userAstrologic = '$_api2/user_astrologic.php';

  // ── Address ───────────────────────────────────────────────────────────────
  static const String updateAddress = '$_api2/updateadress.php';

  // ── Partner preferences ────────────────────────────────────────────────────
  static const String getPartnerPreferences = '$_api2/get_partner_preferences.php';
  static const String userPartner = '$_api2/user_partner.php';

  // ── Documents ─────────────────────────────────────────────────────────────
  static const String checkDocumentStatus = '$_api2/check_document_status.php';
  static const String uploadDocument = '$_api2/upload_document.php';

  // ── Matching / Search ─────────────────────────────────────────────────────
  static const String match = '$_api2/match.php';
  static const String searchOppositeGender = '$_api2/search_opposite_gender.php';
  static const String likeList = '$_api2/likelist.php';
  static const String premiumMembers = '$_api2/premiuimmember.php';
  static const String likeAction = '$_api2/like_action.php';
  static const String likeProfile = '$_api2/like_profile.php';
  static const String servicesApi = '$_api2/services_api.php';

  // ── Proposals ─────────────────────────────────────────────────────────────
  static const String proposalsApi = '$_api2/proposals_api.php';
  static const String acceptProposal = '$_api2/acceptProposal.php';
  static const String rejectProposal = '$_api2/rejectProposal.php';
  static const String deleteProposal = '$_api2/purposal_delete.php';

  // ── Chat / Requests ───────────────────────────────────────────────────────
  static const String sendRequest = '$_api2/send_request.php';
  static const String requestList = '$_request/request_list.php';
  static const String updateLastLogin = '$_request/update_last_login.php';

  // ── Notifications ─────────────────────────────────────────────────────────
  static const String sendNotification = '$_api2/send_notification.php';
  static const String getNotifications = '$_api2/get_notifications.php';
  static const String updateNotificationSettings = '$_api2/update_notification_settings.php';

  // ── Blocked users ─────────────────────────────────────────────────────────
  static const String getBlockedUsers = '$_api2/get_blocked_users.php';
  static const String unblockUser = '$_api2/unblock_user.php';

  // ── Privacy ───────────────────────────────────────────────────────────────
  static const String getPrivacy = '$_api3/get_privacy.php';
  static const String updatePrivacy = '$_api3/privacy.php';

  // ── Package / Payment ─────────────────────────────────────────────────────
  static const String userPackage = '$_api2/user_package.php';
  static const String packageList = '$_api2/packagelist.php';
  static const String purchasePackage = '$_api3/purchase_package.php';
  static const String cancelPayment = '$_api3/cancel_payment.php';
  static String get khaltiPayment => '$paymentBaseUrl/khalti_payment.php';
  static String get hblPayment => '$paymentBaseUrl/hbl/index.php';

  // ── Location ──────────────────────────────────────────────────────────────
  static const String countries = '$_api3/countries.php';
  static const String states = '$_api3/states.php';
  static const String cities = '$_api3/cities.php';

  // ── Marital status (api19) ────────────────────────────────────────────────
  static const String maritalStatusGet = '$_api19/get.php';

  // ── Agora token ───────────────────────────────────────────────────────────
  static const String testToken = '$_api2/test_token.php';

  // ── WebRTC ────────────────────────────────────────────────────────────────
  static const String webrtc = '$_api2/webrtc.php';

  // ── Users list (admin-facing endpoint, used from user app) ───────────────
  static const String getUsers = '$_api2/getusers.php';

  // ── Admin API (api9) ──────────────────────────────────────────────────────
  static const String api9base = _api9;
}
