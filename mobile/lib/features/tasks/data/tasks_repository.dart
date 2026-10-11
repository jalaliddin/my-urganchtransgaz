import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/paginated.dart';
import '../domain/task.dart';
import '../domain/task_filters.dart';

class TasksRepository {
  TasksRepository(this._client);

  final ApiClient _client;

  /// `GET /tasks` is already self-scoped for a plain employee (created by
  /// or assigned to them — see `TaskController::index()`), so no extra
  /// filter is needed here.
  Future<List<Task>> list() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/tasks');
      final data = response.data!['data'] as List<dynamic>;

      return data.map((e) => Task.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  /// One page of the Tasks screen's list, newest first (the web list's
  /// default order).
  Future<Paginated<Task>> page(TaskFilters filters, {int page = 1}) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/tasks',
        queryParameters: {...filters.toQuery(includeStatus: true), 'page': page, 'sort': '-created_at'},
      );

      return Paginated.fromJson(response.data!, Task.fromJson);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<TaskSummary> summary(TaskFilters filters) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>(
        '/tasks/summary',
        queryParameters: filters.toQuery(includeStatus: false),
      );

      return TaskSummary.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  /// Active categories only — what the filter offers.
  Future<List<TaskCategory>> categories() async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/tasks/options');
      final data = response.data!['data'] as Map<String, dynamic>;

      return (data['categories'] as List<dynamic>? ?? [])
          .map((e) => TaskCategory.fromJson(e as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<Task> show(int id) async {
    try {
      final response = await _client.dio.get<Map<String, dynamic>>('/tasks/$id');

      return Task.fromJson(response.data!['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> updateProgress(int id, int progress) async {
    try {
      await _client.dio.patch<void>('/tasks/$id/progress', data: {'progress': progress});
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> complete(int id, {String? result}) async {
    try {
      await _client.dio.post<void>('/tasks/$id/complete', data: {'result': ?result});
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> addComment(int id, String body) async {
    try {
      await _client.dio.post<void>('/tasks/$id/comments', data: {'body': body});
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> uploadAttachment(int id, String filePath, String fileName) async {
    try {
      await _client.dio.post<void>(
        '/tasks/$id/attachments',
        data: FormData.fromMap({'file': await MultipartFile.fromFile(filePath, filename: fileName)}),
      );
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<List<int>> downloadAttachment(String downloadUrl) => _client.downloadBytes(downloadUrl);
}
