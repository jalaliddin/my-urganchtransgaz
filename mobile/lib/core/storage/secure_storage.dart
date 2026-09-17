import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Wraps [FlutterSecureStorage] for the two small pieces of state the app
/// needs to survive a restart: the auth token and the chosen API server
/// address. Keychain/Keystore-backed, unlike the web app's own
/// `localStorage` token (see `frontend/src/services/http.ts`).
class SecureStorage {
  SecureStorage._();

  static const _storage = FlutterSecureStorage();

  static const _tokenKey = 'urtg_token';
  static const _serverUrlKey = 'urtg_server_url';

  static Future<String?> readToken() => _storage.read(key: _tokenKey);

  static Future<void> writeToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  static Future<void> deleteToken() => _storage.delete(key: _tokenKey);

  static Future<String?> readServerUrl() => _storage.read(key: _serverUrlKey);

  static Future<void> writeServerUrl(String url) =>
      _storage.write(key: _serverUrlKey, value: url);
}
