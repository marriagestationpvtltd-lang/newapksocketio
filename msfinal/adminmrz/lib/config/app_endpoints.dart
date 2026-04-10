const String kAdminApiBaseUrl = String.fromEnvironment(
  'ADMIN_API_BASE_URL',
  defaultValue: 'https://digitallami.com',
);

const String kAdminSocketBaseUrl = String.fromEnvironment(
  'ADMIN_SOCKET_URL',
  defaultValue: 'https://digitallami.com:3001',
);

const String kAdminApi2BaseUrl = '$kAdminApiBaseUrl/Api2';
const String kAdminApi9BaseUrl = '$kAdminApiBaseUrl/api9';
