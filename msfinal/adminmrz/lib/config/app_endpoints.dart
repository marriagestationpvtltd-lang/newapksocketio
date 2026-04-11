const String kAdminApiBaseUrl = String.fromEnvironment(
  'ADMIN_API_BASE_URL',
  defaultValue: 'http://192.168.1.5/www/wwwroot/digitallami.com',
);

const String kAdminSocketBaseUrl = String.fromEnvironment(
  'ADMIN_SOCKET_URL',
  defaultValue: 'https://adminnew.marriagestation.com.np',
);

const String kAdminApi2BaseUrl = '$kAdminApiBaseUrl/Api2';
const String kAdminApi9BaseUrl = '$kAdminApiBaseUrl/api9';
