import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/paginated.dart';
import '../domain/notification_item.dart';

class NotificationsRepository {
  NotificationsRepository(this._client);

  final ApiClient _client;

  Future<Paginated<NotificationItem>> list({int page = 1, bool unreadOnly = false}) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/notifications',
        queryParameters: {'page': page, if (unreadOnly) 'unread_only': 1},
      );

      return Paginated.fromJson(response.data!, NotificationItem.fromJson);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<int> unreadCount() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/notifications/unread-count');

      return (response.data!['data'] as Map<String, dynamic>)['count'] as int;
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> markRead(String id) async {
    try {
      await _client.dio.post<void>('/notifications/$id/read');
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> markAllRead() async {
    try {
      await _client.dio.post<void>('/notifications/read-all');
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
