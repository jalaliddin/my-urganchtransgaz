import 'package:device_info_plus/device_info_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart' show TargetPlatform, defaultTargetPlatform, kIsWeb;

import '../../../core/models/user.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/storage/secure_storage.dart';

class AuthRepository {
  AuthRepository(this._client);

  final ApiClient _client;

  Future<User> login(String login, String password) async {
    try {
      final response = await _client.dio.post<Map<String, dynamic>>(
        '/auth/login',
        data: {
          'login': login,
          'password': password,
          'device_name': await _deviceName(),
        },
      );

      final data = response.data!['data'] as Map<String, dynamic>;
      final token = data['token'] as String;

      await SecureStorage.writeToken(token);

      return User.fromJson(data['user'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<User> me() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/auth/me');

      return User.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> logout() async {
    try {
      await _client.dio.post<void>('/auth/logout');
    } on DioException {
      // Even if the server call fails (e.g. token already invalid), the
      // local session must still be cleared below.
    } finally {
      await SecureStorage.deleteToken();
    }
  }

  Future<void> forgotPassword(String email) async {
    try {
      await _client.dio.post<void>('/auth/forgot-password', data: {'email': email});
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    try {
      await _client.dio.post<void>('/auth/change-password', data: {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': newPassword,
      });
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<String> _deviceName() async {
    if (kIsWeb) {
      return 'Web browser';
    }

    try {
      final deviceInfo = DeviceInfoPlugin();

      if (defaultTargetPlatform == TargetPlatform.android) {
        final info = await deviceInfo.androidInfo;
        return '${info.manufacturer} ${info.model}';
      }

      if (defaultTargetPlatform == TargetPlatform.iOS) {
        final info = await deviceInfo.iosInfo;
        return info.utsname.machine;
      }
    } catch (_) {
      // Falls through to the generic name below — this is a nice-to-have
      // for the login history feature, not something worth failing over.
    }

    return 'Mobile app';
  }
}
