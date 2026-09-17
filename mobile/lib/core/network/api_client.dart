import 'package:dio/dio.dart';

import '../storage/secure_storage.dart';
import 'api_config.dart';

/// One Dio instance for the whole app, mirroring
/// `frontend/src/services/http.ts` exactly: a base URL, `Accept:
/// application/json` (without it, Laravel's default `Authenticate`
/// middleware treats an unauthenticated request as a browser navigation
/// and tries to redirect to a named `login` route that doesn't exist in
/// this API-only app, producing a confusing 500 instead of a clean 401 —
/// confirmed directly against the real backend), a request interceptor
/// attaching the stored Bearer token, and a response interceptor that
/// notifies the app on a 401 so it can force a logout from anywhere.
class ApiClient {
  ApiClient() : dio = Dio() {
    dio.options
      ..baseUrl = ApiConfig.defaultBaseUrl
      ..connectTimeout = const Duration(seconds: 15)
      ..receiveTimeout = const Duration(seconds: 20)
      ..headers = {'Accept': 'application/json'};

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await SecureStorage.readToken();

          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }

          handler.next(options);
        },
        onError: (error, handler) {
          if (error.response?.statusCode == 401) {
            onUnauthorized?.call();
          }

          handler.next(error);
        },
      ),
    );
  }

  final Dio dio;

  /// Set by the auth layer at startup; fired on any 401 so the app can
  /// clear the session and bounce to the login screen regardless of which
  /// screen triggered the request.
  void Function()? onUnauthorized;

  Future<void> setBaseUrl(String baseUrl) async {
    dio.options.baseUrl = baseUrl;
    await SecureStorage.writeServerUrl(baseUrl);
  }

  Future<void> restoreBaseUrl() async {
    final stored = await SecureStorage.readServerUrl();

    if (stored != null && stored.isNotEmpty) {
      dio.options.baseUrl = stored;
    }
  }

  String get baseUrl => dio.options.baseUrl;

  /// Downloads an authenticated file (photos, documents, attachments) as
  /// raw bytes. Endpoints like `EmployeeResource.photo_url` /
  /// `EmployeeDocumentResource.download_url` require the same Bearer
  /// token as any other request — never a public URL — so this goes
  /// through the same Dio instance rather than a plain HTTP GET.
  Future<List<int>> downloadBytes(String url) async {
    final response = await dio.get<List<int>>(
      url,
      options: Options(responseType: ResponseType.bytes),
    );

    return response.data!;
  }

  /// Headers to pass to `Image.network(url, headers: ...)` for
  /// authenticated images (employee photo, announcement image) —
  /// Flutter's `NetworkImage` supports custom headers natively, so no
  /// blob/object-URL workaround like the web app needed is required here.
  Future<Map<String, String>> authHeaders() async {
    final token = await SecureStorage.readToken();

    return {
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }
}
