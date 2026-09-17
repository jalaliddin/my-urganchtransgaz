import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/paginated.dart';
import '../domain/announcement.dart';

class AnnouncementsRepository {
  AnnouncementsRepository(this._client);

  final ApiClient _client;

  Future<Paginated<Announcement>> list({int page = 1}) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/announcements',
        queryParameters: {'page': page},
      );

      return Paginated.fromJson(response.data!, Announcement.fromJson);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  /// Fetching one also marks it read server-side — no separate "mark as
  /// read" action exists for announcements (see `AnnouncementController`).
  Future<Announcement> show(int id) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/announcements/$id');

      return Announcement.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  String imageUrl(int id) => '${_client.baseUrl}/announcements/$id/image';

  String attachmentUrl(int id) => '${_client.baseUrl}/announcements/$id/attachment';

  Future<List<int>> downloadAttachment(int id) => _client.downloadBytes(attachmentUrl(id));
}
