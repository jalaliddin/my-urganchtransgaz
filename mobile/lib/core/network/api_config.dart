import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform, kIsWeb, kReleaseMode;

/// The default API base URL.
///
/// A release build (what `flutter build apk/appbundle --release` produces,
/// and therefore what ships to Google Play) always defaults to the real
/// production server — a Play Store install must work out of the box with
/// no `--dart-define` and no manual server-address entry.
///
/// A debug/profile build instead defaults to a local dev backend, resolved
/// per-platform the same way any Flutter app talking to `localhost` has to:
/// an Android *emulator* reaches the host machine at 10.0.2.2, not
/// localhost; iOS simulator/desktop/web all reach it directly. A real
/// device on the same LAN as the backend needs its own address — which is
/// exactly why the login screen also lets a tester override this at
/// runtime (see `AuthController`/`SecureStorage.readServerUrl`) instead of
/// only ever baking one value in at build time.
///
/// Override at build/run time with:
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api/v1
class ApiConfig {
  ApiConfig._();

  static const _override = String.fromEnvironment('API_BASE_URL');
  static const productionBaseUrl = 'https://my.urtg.uz/api/v1';

  static String get defaultBaseUrl {
    if (_override.isNotEmpty) {
      return _override;
    }

    if (kReleaseMode) {
      return productionBaseUrl;
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
