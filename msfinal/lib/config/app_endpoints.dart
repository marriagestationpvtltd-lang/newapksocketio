// ⚠️  IMPORTANT: Change the defaultValue IP to your machine's LAN IP before
// building for a physical device or running on an emulator (use 10.0.2.2 for
// Android emulator instead of the LAN IP).
// Pass at build time without editing: --dart-define=API_BASE_URL=https://react.marriagestation.com.np
const String kApiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://react.marriagestation.com.np',
);

const String _socketServerEnv = String.fromEnvironment(
  'SOCKET_SERVER_URL',
  defaultValue: '',
);

const String _defaultProdSocketUrl = 'https://adminnew.marriagestation.com.np';

/// Determines the socket server URL.
/// Priority:
/// 1) Explicit SOCKET_SERVER_URL dart-define
/// 2) For custom API hosts, fall back to the same host on port 3001
/// 3) For the production marriagestation host, keep the existing admin domain
String _resolveSocketServerBaseUrl() {
  if (_socketServerEnv.isNotEmpty) return _socketServerEnv;

  final apiUri = Uri.tryParse(kApiBaseUrl);
  if (apiUri != null && apiUri.host.isNotEmpty) {
    if (apiUri.host.contains('marriagestation.com.np')) {
      return _defaultProdSocketUrl;
    }
    final scheme = apiUri.scheme.isNotEmpty ? apiUri.scheme : 'https';
    final port = apiUri.hasPort ? apiUri.port : 3001;
    return Uri(
      scheme: scheme,
      host: apiUri.host,
      port: port,
    ).toString();
  }

  return _defaultProdSocketUrl;
}

final String kSocketServerBaseUrl = _resolveSocketServerBaseUrl();

const String kPaymentBaseUrl = String.fromEnvironment(
  'PAYMENT_BASE_URL',
  defaultValue: 'https://react.marriagestation.com.np',
);

const String kApi2BaseUrl = '$kApiBaseUrl/Api2';
const String kApi3BaseUrl = '$kApiBaseUrl/Api3';
const String kApi9BaseUrl = '$kApiBaseUrl/api9';
const String kApi19BaseUrl = '$kApiBaseUrl/api19';
const String kRequestBaseUrl = '$kApiBaseUrl/request';
