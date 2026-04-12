// ─────────────────────────────────────────────────────────────────────────────
// app_endpoints.dart — backward-compatible re-exports derived from AdminAppConfig.
//
// New code should import app_config.dart and use AdminAppConfig.* directly.
// These constants are kept so existing callers continue to compile unchanged.
// ─────────────────────────────────────────────────────────────────────────────
export 'app_config.dart';

import 'app_config.dart';

/// Single base URL for the admin app (env-configurable at build time).
const String kAdminApiBaseUrl = AdminAppConfig.baseUrl;

/// Socket server URL for the admin app.
const String kAdminSocketBaseUrl = AdminAppConfig.socketUrl;

/// Convenience namespace roots (derived from AdminAppConfig).
const String kAdminApi2BaseUrl = '${AdminAppConfig.baseUrl}/Api2';
const String kAdminApi9BaseUrl = '${AdminAppConfig.baseUrl}/api9';
