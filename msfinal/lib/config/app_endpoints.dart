// ⚠️  IMPORTANT: Change the defaultValue IP to your machine's LAN IP before
// building for a physical device or running on an emulator (use 10.0.2.2 for
// Android emulator instead of the LAN IP).
// Pass at build time without editing: --dart-define=API_BASE_URL=http://X.X.X.X/...
String _normalizeBaseUrl(String raw) {
  if (raw.startsWith('http://') || raw.startsWith('https://')) return raw;
  return 'http://$raw';
}

final String kApiBaseUrl = _normalizeBaseUrl(
  const String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://192.168.1.4/newapksocketio/www/wwwroot/digitallami.com',
  ),
);

// ⚠️  IMPORTANT: Change the defaultValue IP to your machine's LAN IP.
// Android emulator: use http://10.0.2.2:3001
// Physical device : use http://<YOUR_LAN_IP>:3001 (e.g. http://192.168.1.5:3001)
// Pass at build time: --dart-define=SOCKET_SERVER_URL=http://X.X.X.X:3001
final String kSocketServerBaseUrl = _normalizeBaseUrl(
  const String.fromEnvironment(
    'SOCKET_SERVER_URL',
    defaultValue: 'http://adminnew.marriagestation.com.np',
  ),
);

final String kApi2BaseUrl = '$kApiBaseUrl/Api2';
final String kApi3BaseUrl = '$kApiBaseUrl/Api3';
final String kApi9BaseUrl = '$kApiBaseUrl/api9';
final String kRequestBaseUrl = '$kApiBaseUrl/request';
