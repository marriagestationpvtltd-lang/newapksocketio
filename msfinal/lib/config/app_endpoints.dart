// ─────────────────────────────────────────────────────────────────────────────
// app_endpoints.dart — backward-compatible re-exports derived from AppConfig.
//
// New code should import app_config.dart and use AppConfig.* directly.
// These constants are kept so existing callers continue to compile unchanged.
// ─────────────────────────────────────────────────────────────────────────────
export 'app_config.dart';

import 'app_config.dart';

/// Single base URL for the entire user app (env-configurable at build time).
const String kApiBaseUrl = AppConfig.baseUrl;

/// Convenience namespace roots (derived from AppConfig).
const String kApi2BaseUrl = '${AppConfig.baseUrl}/Api2';
const String kApi3BaseUrl = '${AppConfig.baseUrl}/Api3';
const String kApi9BaseUrl = '${AppConfig.baseUrl}/api9';
const String kApi19BaseUrl = '${AppConfig.baseUrl}/api19';
const String kRequestBaseUrl = '${AppConfig.baseUrl}/request';

/// Payment base URL (may differ from API base).
const String kPaymentBaseUrl = AppConfig.paymentBaseUrl;

/// Socket server base URL (runtime-resolved — see AppConfig.socketUrl).
String get kSocketServerBaseUrl => AppConfig.socketUrl;
