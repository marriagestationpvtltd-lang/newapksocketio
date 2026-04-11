const String kAdminApiBaseUrl = String.fromEnvironment(
  'ADMIN_API_BASE_URL',
  defaultValue: 'http://192.168.18.214',
);

const String kAdminSocketBaseUrl = String.fromEnvironment(
  'ADMIN_SOCKET_URL',
  defaultValue: 'http://192.168.18.214:3001',
);

const String kAdminApi2BaseUrl = '$kAdminApiBaseUrl/Api2';
const String kAdminApi9BaseUrl = '$kAdminApiBaseUrl/api9';
