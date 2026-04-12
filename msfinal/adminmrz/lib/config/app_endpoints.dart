const String kAdminApiBaseUrl = String.fromEnvironment(
  'ADMIN_API_BASE_URL',
  defaultValue: 'https://react.marriagestation.com.np/landing.php',
);

const String kAdminSocketBaseUrl = String.fromEnvironment(
  'ADMIN_SOCKET_URL',
  defaultValue: 'http://adminnew.marriagestation.com.np',
);

const String kAdminApi2BaseUrl = '$kAdminApiBaseUrl/Api2';
const String kAdminApi9BaseUrl = '$kAdminApiBaseUrl/api9';
