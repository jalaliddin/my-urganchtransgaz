import 'package:flutter/foundation.dart' show TargetPlatform, defaultTargetPlatform, kIsWeb;

/// The default API base URL, resolved per-platform the same way any Flutter
/// app talking to a `localhost` dev backend has to: an Android *emulator*
/// reaches the host machine at 10.0.2.2, not localhost; iOS
/// simulator/desktop/web all reach it directly. A real device on the same
/// LAN as the backend needs its own address — which is exactly why the
/// login screen also lets a tester override this at runtime (see
/// `AuthController`/`SecureStorage.readServerUrl`) instead of only ever
/// baking one value in at build time.
///
/// Override at build/run time with:
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api/v1
class ApiConfig {
  ApiConfig._();

  static const _override = String.fromEnvironment('API_BASE_URL');

  static String get defaultBaseUrl {
    if (_override.isNotEmpty) {
      return _override;
    }

    if (kIsWeb) {
      return 'http://127.0.0.1:8000/api/v1';
    }

    if (defaultTargetPlatform == TargetPlatform.android) {
      return 'http://10.0.2.2:8000/api/v1';
    }

    return 'http://127.0.0.1:8000/api/v1';
  }
}
